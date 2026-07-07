import sys
import json
import cv2
import numpy as np
import insightface
from insightface.app import FaceAnalysis
from http.server import HTTPServer, BaseHTTPRequestHandler
import os
import traceback

# Log a archivo para depuracion
LOG_FILE = os.path.join(os.path.dirname(__file__), 'face_server.log')
def log(msg):
    with open(LOG_FILE, 'a') as f:
        f.write(msg + '\n')
    print(msg, flush=True)

log("[FaceServer] Iniciando...")

EMBEDDINGS_DB = []

def cargar_embeddings_desde_bd():
    global EMBEDDINGS_DB
    try:
        import psycopg2
        db_host = os.environ.get('DB_HOST', '127.0.0.1')
        db_port = os.environ.get('DB_PORT', '5433')
        db_name = os.environ.get('DB_DATABASE', 'laravel_biometria')
        db_user = os.environ.get('DB_USERNAME', 'postgres')
        db_pass = os.environ.get('DB_PASSWORD', 'postgres')

        log(f"[FaceServer] Conectando a BD: {db_host}:{db_port}/{db_name}")
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
        for row in rows:
            vec_text = row[7]
            vec_text = vec_text.replace('{', '[').replace('}', ']')
            vec = json.loads(vec_text)
            EMBEDDINGS_DB.append({
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
        log(f"[FaceServer] {len(EMBEDDINGS_DB)} embeddings cargados desde BD")
    except Exception as e:
        log(f"[FaceServer] ERROR cargando BD: {e}")
        log(traceback.format_exc())

log("[FaceServer] Cargando modelo InsightFace...")
app = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])
app.prepare(ctx_id=0, det_size=(320, 320))
log("[FaceServer] Modelo listo.")

cargar_embeddings_desde_bd()

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
        log(f"[FaceServer] ERROR procesando imagen: {e}")
        log(traceback.format_exc())
        return {'success': False, 'error': str(e)}

class Handler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass

    def do_POST(self):
        try:
            if self.path == '/reload':
                log("[FaceServer] Recargando embeddings desde BD...")
                EMBEDDINGS_DB.clear()
                cargar_embeddings_desde_bd()
                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Access-Control-Allow-Origin', '*')
                self.end_headers()
                self.wfile.write(json.dumps({'success': True, 'mensaje': f'{len(EMBEDDINGS_DB)} embeddings recargados'}).encode())
                return

            if self.path == '/verificar':
                content_length = int(self.headers.get('Content-Length', 0))
                if content_length == 0:
                    self.send_response(400)
                    self.end_headers()
                    return

                body = self.rfile.read(content_length)
                resultado = procesar_imagen(body)

                self.send_response(200)
                self.send_header('Content-Type', 'application/json')
                self.send_header('Access-Control-Allow-Origin', '*')
                self.end_headers()
                self.wfile.write(json.dumps(resultado).encode())
                return

            self.send_response(404)
            self.end_headers()

        except Exception as e:
            log(f"[FaceServer] ERROR en request: {e}")
            self.send_response(500)
            self.send_header('Content-Type', 'application/json')
            self.end_headers()
            self.wfile.write(json.dumps({'success': False, 'error': str(e)}).encode())

    def do_OPTIONS(self):
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

if __name__ == '__main__':
    port = 5001
    if len(sys.argv) > 1:
        port = int(sys.argv[1])
    server = HTTPServer(('127.0.0.1', port), Handler)
    log(f"[FaceServer] Escuchando en http://127.0.0.1:{port}")
    server.serve_forever()

