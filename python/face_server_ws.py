import asyncio
import concurrent.futures
import json
import cv2
import numpy as np
import insightface
from insightface.app import FaceAnalysis
import os
import traceback
import websockets
import signal
import sys

# ==========================================
# CONFIGURACION
# ==========================================
EMBEDDINGS_DB = []
LOG_FILE = os.path.join(os.path.dirname(__file__), 'face_server_ws.log')
SHUTDOWN_EVENT = asyncio.Event()

# ThreadPool con UN SOLO worker para procesar imagenes sin bloquear asyncio
# InsightFace NO es thread-safe; multiples workers corrompen los resultados
# Un solo worker serializa el procesamiento pero mantiene el event loop libre
EXECUTOR = concurrent.futures.ThreadPoolExecutor(max_workers=1)

def log(msg):
    with open(LOG_FILE, 'a') as f:
        f.write(msg + '\n')
    print(msg, flush=True)

# Manejo de señales para servicio (SIGTERM en Linux, CTRL_BREAK en Windows)
def handle_signal(signum, frame):
    log(f"[FaceServerWS] Señal recibida ({signum}), cerrando servidor...")
    SHUTDOWN_EVENT.set()

if sys.platform == 'win32':
    signal.signal(signal.SIGBREAK, handle_signal)
else:
    signal.signal(signal.SIGTERM, handle_signal)
    signal.signal(signal.SIGINT, handle_signal)

log("[FaceServerWS] Iniciando servidor WebSocket...")

# Cargar embeddings desde PostgreSQL (asignación atómica para thread-safety)
def cargar_embeddings_desde_bd():
    global EMBEDDINGS_DB
    try:
        import psycopg2
        db_host = os.environ.get('DB_HOST', '127.0.0.1')
        db_port = os.environ.get('DB_PORT', '5433')
        db_name = os.environ.get('DB_DATABASE', 'laravel_biometria')
        db_user = os.environ.get('DB_USERNAME', 'postgres')
        db_pass = os.environ.get('DB_PASSWORD', 'postgres')

        log(f"[FaceServerWS] Conectando a BD: {db_host}:{db_port}/{db_name}")
        conn = psycopg2.connect(
            host=db_host, port=db_port, database=db_name,
            user=db_user, password=db_pass
        )
        cur = conn.cursor()
        cur.execute("""
            SELECT id, dni, nombre, carrera, aula_id, estado, foto_path,
                   vector_rostro::text
            FROM alumnos
            WHERE vector_rostro IS NOT NULL
        """)
        rows = cur.fetchall()
        nuevos = []
        for row in rows:
            vec_text = row[7]
            vec_text = vec_text.replace('{', '[').replace('}', ']')
            vec = json.loads(vec_text)
            nuevos.append({
                'id': row[0],
                'dni': row[1],
                'nombre': row[2],
                'carrera': row[3],
                'aula_id': row[4],
                'estado': row[5],
                'foto_path': row[6],
                'embedding': np.array(vec, dtype=np.float32),
            })
        cur.close()
        conn.close()
        EMBEDDINGS_DB = nuevos  # swap atómico
        log(f"[FaceServerWS] {len(EMBEDDINGS_DB)} embeddings cargados desde BD")
    except Exception as e:
        log(f"[FaceServerWS] ERROR cargando BD: {e}")
        log(traceback.format_exc())

# Cargar modelo InsightFace con soporte GPU (CUDA) y fallback CPU
log("[FaceServerWS] Cargando modelo InsightFace (intentando GPU/CUDA)...")
try:
    app = FaceAnalysis(name='buffalo_l', providers=['CUDAExecutionProvider', 'CPUExecutionProvider'])
    log("[FaceServerWS] CUDA/GPU disponible y activo")
except Exception as e:
    log(f"[FaceServerWS] CUDA no disponible, usando CPU: {e}")
    app = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])

app.prepare(ctx_id=0, det_size=(320, 320))
log("[FaceServerWS] Modelo listo.")

cargar_embeddings_desde_bd()

# Funciones de procesamiento
def cosine_distance(a, b):
    dot = np.dot(a, b)
    norm_a = np.linalg.norm(a)
    norm_b = np.linalg.norm(b)
    if norm_a == 0 or norm_b == 0:
        return 2.0
    return 1.0 - (dot / (norm_a * norm_b))

def buscar_mejor_coincidencia(embedding):
    mejor = None
    mejor_dist = 1.5
    for alumno in EMBEDDINGS_DB:
        dist = cosine_distance(embedding, alumno['embedding'])
        if dist < mejor_dist:
            mejor_dist = dist
            mejor = alumno
    return mejor, mejor_dist

def procesar_imagen(img_bytes):
    try:
        nparr = np.frombuffer(img_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        if img is None:
            return {'success': False, 'error': 'No se pudo decodificar imagen'}

        rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        faces = app.get(rgb)

        resultados = []
        for face in faces:
            det_score = float(getattr(face, 'det_score', 0))
            if det_score < 0.50:
                continue
            kps = getattr(face, 'kps', None)
            if kps is None or len(kps) < 5:
                continue

            embedding = face.normed_embedding
            if embedding is None or len(embedding) == 0:
                continue

            bbox = face.bbox.astype(int).tolist()
            mejor, dist = buscar_mejor_coincidencia(embedding)

            if mejor and dist <= 0.55:
                resultados.append({
                    'conocido': True,
                    'dni': mejor['dni'],
                    'nombre': mejor['nombre'],
                    'carrera': mejor['carrera'],
                    'aula_id': mejor['aula_id'],
                    'estado': mejor['estado'],
                    'foto_path': mejor['foto_path'],
                    'bbox': bbox,
                    'distancia': round(float(dist), 3),
                    'confianza': round((1 - float(dist)) * 100),
                })
            else:
                resultados.append({
                    'conocido': False,
                    'bbox': bbox,
                })

        return {'success': True, 'rostros': resultados}
    except Exception as e:
        log(f"[FaceServerWS] ERROR procesando: {e}")
        log(traceback.format_exc())
        return {'success': False, 'error': str(e)}

# WebSocket handler
async def handler(websocket):
    client_addr = websocket.remote_address
    log(f"[FaceServerWS] Cliente conectado: {client_addr}")
    loop = asyncio.get_event_loop()
    try:
        async for message in websocket:
            if isinstance(message, bytes):
                # Procesar imagen en thread pool para NO bloquear el event loop
                # Esto mantiene la conexion viva incluso bajo carga masiva
                resultado = await loop.run_in_executor(EXECUTOR, procesar_imagen, message)
                await websocket.send(json.dumps(resultado))
            elif isinstance(message, str):
                data = json.loads(message)
                if data.get('action') == 'reload':
                    log("[FaceServerWS] Recargando embeddings...")
                    cargar_embeddings_desde_bd()
                    await websocket.send(json.dumps({'success': True, 'mensaje': f'{len(EMBEDDINGS_DB)} embeddings recargados'}))
                else:
                    await websocket.send(json.dumps({'success': False, 'error': 'Accion desconocida'}))
    except websockets.exceptions.ConnectionClosed:
        log(f"[FaceServerWS] Cliente desconectado: {client_addr}")
    except Exception as e:
        log(f"[FaceServerWS] ERROR: {e}")

async def recargar_periodicamente():
    """Recarga embeddings cada 5 segundos para detectar nuevos registros."""
    while not SHUTDOWN_EVENT.is_set():
        await asyncio.sleep(5)
        cargar_embeddings_desde_bd()

async def main():
    port = 5002
    log(f"[FaceServerWS] Escuchando en ws://0.0.0.0:{port}")
    stop = asyncio.get_event_loop().create_future()
    original_set = SHUTDOWN_EVENT.set
    SHUTDOWN_EVENT.set = lambda: (original_set(), stop.done() or stop.set_result(None))

    # Iniciar recarga periódica en segundo plano
    asyncio.create_task(recargar_periodicamente())

    async with websockets.serve(handler, '0.0.0.0', port, max_size=5*1024*1024):
        try:
            await stop  # Espera señal de cierre
        except asyncio.CancelledError:
            pass
    log("[FaceServerWS] Servidor cerrado correctamente.")

if __name__ == '__main__':
    asyncio.run(main())
