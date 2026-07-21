import asyncio
import concurrent.futures
import json
import os
import signal
import sys
import traceback
from dataclasses import dataclass
from typing import List, Optional, Tuple

import cv2
import numpy as np
import websockets
from aiohttp import web
from insightface.app import FaceAnalysis


# ============== CONFIGURACION ==============
@dataclass(frozen=True)
class Config:
    model_name: str = 'buffalo_l'
    det_size: Tuple[int, int] = (320, 320)
    http_port: int = 5001
    ws_port: int = 5002
    gpu_providers: Tuple[str, ...] = ('CUDAExecutionProvider', 'CPUExecutionProvider')
    cpu_providers: Tuple[str, ...] = ('CPUExecutionProvider',)
    min_detection_score: float = 0.50
    min_registration_score: float = 0.75
    max_cosine_distance: float = 0.55
    min_eye_distance_ratio: float = 0.28
    min_face_area_ratio: float = 0.015
    center_min_ratio: float = 0.15
    center_max_ratio: float = 0.85
    aspect_ratio_min: float = 0.8
    aspect_ratio_max: float = 1.8
    nose_center_tolerance: float = 0.15
    reload_interval_seconds: int = 5
    log_file: str = os.path.join(os.path.dirname(__file__), 'face_server.log')

    def db_params(self):
        return {
            'host': os.environ.get('DB_HOST', '127.0.0.1'),
            'port': os.environ.get('DB_PORT', '5432'),
            'database': os.environ.get('DB_DATABASE', 'laravel'),
            'user': os.environ.get('DB_USERNAME', 'postgres'),
            'password': os.environ.get('DB_PASSWORD', 'postgres'),
        }


# ============== LOGGER ==============
class Logger:
    def __init__(self, log_file: str):
        self.log_file = log_file

    def info(self, msg: str) -> None:
        self._write('INFO', msg)

    def error(self, msg: str) -> None:
        self._write('ERROR', msg)

    def exception(self, msg: str) -> None:
        self.error(msg)
        self._write('TRACE', traceback.format_exc())

    def _write(self, level: str, msg: str) -> None:
        line = f"[{level}] {msg}"
        try:
            with open(self.log_file, 'a', encoding='utf-8') as f:
                f.write(line + '\n')
        except Exception:
            pass
        print(line, flush=True)


# ============== REPOSITORIO DE EMBEDDINGS (Single Responsibility: cargar datos) ==============
class EmbeddingRepository:
    def __init__(self, config: Config, logger: Logger):
        self.config = config
        self.logger = logger

    def fetch_all(self) -> List[dict]:
        try:
            import psycopg2
            params = self.config.db_params()
            self.logger.info(f"Conectando a BD: {params['host']}:{params['port']}/{params['database']}")
            with psycopg2.connect(**params) as conn:
                with conn.cursor() as cur:
                    cur.execute("""
                        SELECT id, dni, nombre, carrera, aula_id, estado, foto_path,
                               vector_rostro::text
                        FROM alumnos
                        WHERE vector_rostro IS NOT NULL
                    """)
                    rows = cur.fetchall()
            embeddings = []
            for row in rows:
                vec_text = row[7].replace('{', '[').replace('}', ']')
                vec = json.loads(vec_text)
                embeddings.append({
                    'id': row[0],
                    'dni': row[1],
                    'nombre': row[2],
                    'carrera': row[3],
                    'aula_id': row[4],
                    'estado': row[5],
                    'foto_path': row[6],
                    'embedding': np.array(vec, dtype=np.float32),
                })
            self.logger.info(f"{len(embeddings)} embeddings cargados desde BD")
            return embeddings
        except Exception as e:
            self.logger.exception(f"ERROR cargando BD: {e}")
            return []


# ============== MODELO DE ROSTROS (Single Responsibility: inferencia) ==============
class FaceModel:
    def __init__(self, config: Config, logger: Logger):
        self.config = config
        self.logger = logger
        self._app = self._load_model()

    def _load_model(self) -> FaceAnalysis:
        self.logger.info("Cargando modelo InsightFace (intentando GPU/CUDA)...")
        try:
            app = FaceAnalysis(name=self.config.model_name, providers=list(self.config.gpu_providers))
            self.logger.info("CUDA/GPU disponible y activo")
        except Exception as e:
            self.logger.error(f"CUDA no disponible, usando CPU: {e}")
            app = FaceAnalysis(name=self.config.model_name, providers=list(self.config.cpu_providers))
        app.prepare(ctx_id=0, det_size=self.config.det_size)
        self.logger.info("Modelo listo.")
        return app

    def detect_faces(self, img: np.ndarray):
        rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
        return self._app.get(rgb)


# ============== SERVICIO DE VALIDACION (Open/Closed: reglas intercambiables) ==============
class FaceValidator:
    def __init__(self, config: Config):
        self.config = config

    def validate_registration(self, face, img_w: int, img_h: int) -> Tuple[bool, str]:
        if float(getattr(face, 'det_score', 0)) < self.config.min_registration_score:
            return False, 'Rostro incompleto o tapado. Asegurate de que tu rostro completo sea visible de frente.'

        kps = getattr(face, 'kps', None)
        if kps is None or len(kps) < 5:
            return False, 'No se detectaron puntos faciales completos. Rostro tapado o incompleto.'

        left_eye, right_eye, nose, mouth_left, mouth_right = (np.array(kps[i]) for i in range(5))
        eye_dist = np.linalg.norm(left_eye - right_eye)
        bbox = face.bbox.astype(float)
        face_width = bbox[2] - bbox[0]
        face_height = bbox[3] - bbox[1]

        if eye_dist < face_width * self.config.min_eye_distance_ratio:
            return False, 'Rostro de perfil o incompleto. Mira directamente a la camara.'

        aspect_ratio = face_height / face_width if face_width > 0 else 0
        if aspect_ratio < self.config.aspect_ratio_min or aspect_ratio > self.config.aspect_ratio_max:
            return False, 'Rostro inclinado o posicion anormal.'

        eyes_center_x = (left_eye[0] + right_eye[0]) / 2
        if abs(nose[0] - eyes_center_x) > face_width * self.config.nose_center_tolerance:
            return False, 'Rostro girado. Mira directamente a la camara.'

        eyes_center_y = (left_eye[1] + right_eye[1]) / 2
        if nose[1] <= eyes_center_y:
            return False, 'Rostro incompleto. Posicion facial anormal.'

        mouth_y = (mouth_left[1] + mouth_right[1]) / 2
        if mouth_y <= nose[1]:
            return False, 'Rostro incompleto. Boca no detectada.'

        face_area = face_width * face_height
        frame_area = img_w * img_h
        if face_area / frame_area < self.config.min_face_area_ratio:
            return False, 'Acercate un poco mas a la camara.'

        if bbox[0] < 0 or bbox[1] < 0 or bbox[2] > img_w or bbox[3] > img_h:
            return False, 'Rostro cortado. Acomodate mejor en el ovalo.'

        centro_x = (bbox[0] + bbox[2]) / 2
        if centro_x < img_w * self.config.center_min_ratio or centro_x > img_w * self.config.center_max_ratio:
            return False, 'Centra tu rostro en el ovalo.'

        return True, 'Rostro listo'

    def is_detectable(self, face) -> bool:
        if float(getattr(face, 'det_score', 0)) < self.config.min_detection_score:
            return False
        kps = getattr(face, 'kps', None)
        return kps is not None and len(kps) >= 5


# ============== SERVICIO DE EMPAREJAMIENTO ==============
class FaceMatcher:
    def __init__(self, repository: EmbeddingRepository):
        self.repository = repository
        self.embeddings: List[dict] = []

    def reload(self) -> None:
        self.embeddings = self.repository.fetch_all()

    def find_best_match(self, embedding: np.ndarray) -> Tuple[Optional[dict], float]:
        best = None
        best_dist = 1.5
        for alumno in self.embeddings:
            dist = self.cosine_distance(embedding, alumno['embedding'])
            if dist < best_dist:
                best_dist = dist
                best = alumno
        return best, best_dist

    @staticmethod
    def cosine_distance(a: np.ndarray, b: np.ndarray) -> float:
        dot = np.dot(a, b)
        norm_a = np.linalg.norm(a)
        norm_b = np.linalg.norm(b)
        if norm_a == 0 or norm_b == 0:
            return 2.0
        return 1.0 - (dot / (norm_a * norm_b))


# ============== DECODIFICADOR DE IMAGEN ==============
class ImageDecoder:
    @staticmethod
    def decode(img_bytes: bytes) -> Optional[np.ndarray]:
        nparr = np.frombuffer(img_bytes, np.uint8)
        img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
        return img


# ============== CASOS DE USO (Application layer) ==============
class VerifyFacesUseCase:
    def __init__(self, model: FaceModel, matcher: FaceMatcher, validator: FaceValidator, config: Config):
        self.model = model
        self.matcher = matcher
        self.validator = validator
        self.config = config

    def execute(self, img_bytes: bytes) -> dict:
        img = ImageDecoder.decode(img_bytes)
        if img is None:
            return {'success': False, 'error': 'No se pudo decodificar imagen'}

        faces = self.model.detect_faces(img)
        resultados = []
        for face in faces:
            if not self.validator.is_detectable(face):
                continue
            embedding = face.normed_embedding
            if embedding is None or len(embedding) == 0:
                continue
            bbox = face.bbox.astype(int).tolist()
            mejor, dist = self.matcher.find_best_match(embedding)

            if mejor and dist <= self.config.max_cosine_distance:
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
                resultados.append({'conocido': False, 'bbox': bbox})

        return {'success': True, 'rostros': resultados}


class RegisterFaceUseCase:
    def __init__(self, model: FaceModel, validator: FaceValidator):
        self.model = model
        self.validator = validator

    def execute(self, img_bytes: bytes) -> Tuple[Optional[dict], Optional[str]]:
        img = ImageDecoder.decode(img_bytes)
        if img is None:
            return None, 'No se pudo decodificar imagen'

        faces = self.model.detect_faces(img)
        if len(faces) == 0:
            return None, 'No se detecto rostro'
        if len(faces) > 1:
            return None, 'Se detectaron multiples rostros. Solo uno permitido.'

        face = faces[0]
        valido, msg = self.validator.validate_registration(face, img.shape[1], img.shape[0])
        if not valido:
            return None, msg

        embedding = face.normed_embedding.tolist()
        return {'embedding': embedding}, None


# ============== SERVIDORES HTTP Y WEBSOCKET ==============
class FaceServer:
    def __init__(self, config: Config, logger: Logger):
        self.config = config
        self.logger = logger
        self.matcher = None
        self.verify_use_case = None
        self.register_use_case = None
        self.executor = concurrent.futures.ThreadPoolExecutor(max_workers=1)
        self.shutdown_event = asyncio.Event()

    def bootstrap(self) -> None:
        repository = EmbeddingRepository(self.config, self.logger)
        model = FaceModel(self.config, self.logger)
        validator = FaceValidator(self.config)
        self.matcher = FaceMatcher(repository)
        self.matcher.reload()
        self.verify_use_case = VerifyFacesUseCase(model, self.matcher, validator, self.config)
        self.register_use_case = RegisterFaceUseCase(model, validator)

    async def http_register(self, request: web.Request) -> web.Response:
        loop = asyncio.get_event_loop()
        body = await request.read()
        resultado, error = await loop.run_in_executor(self.executor, self.register_use_case.execute, body)
        if error:
            return web.json_response({'success': False, 'error': error}, status=400)
        return web.json_response({'success': True, 'embedding': resultado['embedding']})

    async def http_verify(self, request: web.Request) -> web.Response:
        loop = asyncio.get_event_loop()
        body = await request.read()
        resultado = await loop.run_in_executor(self.executor, self.verify_use_case.execute, body)
        return web.json_response(resultado)

    async def http_reload(self, request: web.Request) -> web.Response:
        self.matcher.reload()
        return web.json_response({
            'success': True,
            'mensaje': f'{len(self.matcher.embeddings)} embeddings recargados'
        })

    async def http_cors(self, request: web.Request) -> web.Response:
        return web.Response(status=200)

    async def http_up(self, request: web.Request) -> web.Response:
        return web.json_response({'status': 'ok'}, status=200)

    async def websocket_handler(self, websocket):
        client_addr = websocket.remote_address
        self.logger.info(f"Cliente conectado: {client_addr}")
        loop = asyncio.get_event_loop()
        try:
            async for message in websocket:
                if isinstance(message, bytes):
                    resultado = await loop.run_in_executor(self.executor, self.verify_use_case.execute, message)
                    await websocket.send(json.dumps(resultado))
                elif isinstance(message, str):
                    data = json.loads(message)
                    if data.get('action') == 'reload':
                        self.matcher.reload()
                        await websocket.send(json.dumps({
                            'success': True,
                            'mensaje': f'{len(self.matcher.embeddings)} embeddings recargados'
                        }))
                    else:
                        await websocket.send(json.dumps({'success': False, 'error': 'Accion desconocida'}))
        except websockets.exceptions.ConnectionClosed:
            self.logger.info(f"Cliente desconectado: {client_addr}")
        except Exception as e:
            self.logger.error(f"ERROR WebSocket: {e}")

    async def periodic_reload(self) -> None:
        while not self.shutdown_event.is_set():
            await asyncio.sleep(self.config.reload_interval_seconds)
            self.matcher.reload()

    def _setup_signal_handlers(self):
        def handle_signal(signum, frame):
            self.logger.info(f"Señal recibida ({signum}), cerrando servidor...")
            self.shutdown_event.set()

        if sys.platform == 'win32':
            signal.signal(signal.SIGBREAK, handle_signal)
        else:
            signal.signal(signal.SIGTERM, handle_signal)
            signal.signal(signal.SIGINT, handle_signal)

    async def run(self) -> None:
        self._setup_signal_handlers()
        self.bootstrap()

        app_http = web.Application()
        app_http.router.add_post('/registrar', self.http_register)
        app_http.router.add_post('/verificar', self.http_verify)
        app_http.router.add_post('/reload', self.http_reload)
        app_http.router.add_get('/up', self.http_up)
        app_http.router.add_route('OPTIONS', '/{tail:.*}', self.http_cors)

        runner = web.AppRunner(app_http)
        await runner.setup()
        site = web.TCPSite(runner, '0.0.0.0', self.config.http_port)
        await site.start()
        self.logger.info(f"HTTP en http://0.0.0.0:{self.config.http_port}")

        asyncio.create_task(self.periodic_reload())

        stop = asyncio.get_event_loop().create_future()
        original_set = self.shutdown_event.set
        self.shutdown_event.set = lambda: (original_set(), stop.done() or stop.set_result(None))

        self.logger.info(f"WebSocket en ws://0.0.0.0:{self.config.ws_port}")
        async with websockets.serve(self.websocket_handler, '0.0.0.0', self.config.ws_port, max_size=5*1024*1024):
            try:
                await stop
            except asyncio.CancelledError:
                pass

        await runner.cleanup()
        self.logger.info("Servidor cerrado correctamente.")


# ============== PUNTO DE ENTRADA ==============
if __name__ == '__main__':
    config = Config()
    if len(sys.argv) > 1:
        config = Config(http_port=int(sys.argv[1]), ws_port=int(sys.argv[1]) + 1)

    server = FaceServer(config, Logger(config.log_file))
    asyncio.run(server.run())
