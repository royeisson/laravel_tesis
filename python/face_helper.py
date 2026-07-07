import sys
import json
import cv2
import numpy as np
import insightface
from insightface.app import FaceAnalysis

app = FaceAnalysis(name='buffalo_l', providers=['CPUExecutionProvider'])
# Usar 320x320 para validacion rapida, 640x640 solo para embedding final
app.prepare(ctx_id=0, det_size=(320, 320))

def validar_rostro_completo(face, img_w, img_h):
    """Valida que el rostro este completo, de frente, sin tapar."""
    
    # 1. Score de confianza - subir a 0.75 para rechazar tapados/perfiles
    det_score = float(getattr(face, 'det_score', 0))
    if det_score < 0.75:
        return False, 'Rostro incompleto o tapado. Asegurate de que tu rostro completo sea visible de frente.'

    # 2. Keypoints de 5 puntos
    kps = getattr(face, 'kps', None)
    if kps is None or len(kps) < 5:
        return False, 'No se detectaron puntos faciales completos. Rostro tapado o incompleto.'

    left_eye = np.array(kps[0])
    right_eye = np.array(kps[1])
    nose = np.array(kps[2])
    mouth_left = np.array(kps[3])
    mouth_right = np.array(kps[4])

    # 3. Distancia entre ojos - para detectar perfiles
    eye_dist = np.linalg.norm(left_eye - right_eye)
    bbox = face.bbox.astype(float)
    face_width = bbox[2] - bbox[0]
    face_height = bbox[3] - bbox[1]
    
    # Ojos deben estar separados al menos el 28% del ancho del rostro
    # (de frente es ~35-40%, de perfil es <20%)
    if eye_dist < face_width * 0.28:
        return False, 'Rostro de perfil o incompleto. Mira directamente a la camara.'

    # 4. Relacion de aspecto del rostro
    aspect_ratio = face_height / face_width if face_width > 0 else 0
    if aspect_ratio < 0.8 or aspect_ratio > 1.8:
        return False, 'Rostro inclinado o posicion anormal.'

    # 5. Nariz debe estar centrada horizontalmente entre los ojos
    eyes_center_x = (left_eye[0] + right_eye[0]) / 2
    if abs(nose[0] - eyes_center_x) > face_width * 0.15:
        return False, 'Rostro girado. Mira directamente a la camara.'

    # 6. Nariz debajo de los ojos
    eyes_center_y = (left_eye[1] + right_eye[1]) / 2
    if nose[1] <= eyes_center_y:
        return False, 'Rostro incompleto. Posicion facial anormal.'

    # 7. Boca debajo de la nariz
    mouth_y = (mouth_left[1] + mouth_right[1]) / 2
    if mouth_y <= nose[1]:
        return False, 'Rostro incompleto. Boca no detectada.'

    # 8. Tamano minimo del rostro en el frame
    face_area = face_width * face_height
    frame_area = img_w * img_h
    if face_area / frame_area < 0.015:
        return False, 'Acercate un poco mas a la camara.'

    # 9. Bordes del rostro
    if bbox[0] < 0 or bbox[1] < 0 or bbox[2] > img_w or bbox[3] > img_h:
        return False, 'Rostro cortado. Acomodate mejor en el ovalo.'

    # 10. Centro del rostro
    centro_x = (bbox[0] + bbox[2]) / 2
    if centro_x < img_w * 0.15 or centro_x > img_w * 0.85:
        return False, 'Centra tu rostro en el ovalo.'

    return True, 'Rostro listo'

def obtener_embedding_y_calidad(img):
    rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    faces = app.get(rgb)
    if len(faces) == 0:
        return None, None, "No se detecto rostro"
    face = faces[0]

    valido, msg = validar_rostro_completo(face, img.shape[1], img.shape[0])
    if not valido:
        return None, None, msg

    embedding = face.normed_embedding.tolist()
    bbox = face.bbox.astype(int)
    x1, y1, x2, y2 = bbox[0], bbox[1], bbox[2], bbox[3]
    area = (x2 - x1) * (y2 - y1)
    img_area = img.shape[0] * img.shape[1]
    calidad = "buena" if area / img_area > 0.03 else "regular"
    return embedding, calidad, None

def validar_rostro(img):
    rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    faces = app.get(rgb)
    if len(faces) == 0:
        return False, 'Rostro no detectado'
    face = faces[0]
    valido, msg = validar_rostro_completo(face, img.shape[1], img.shape[0])
    if not valido:
        return False, msg
    return True, 'Rostro listo para registrar'

def obtener_todos_embeddings(img):
    rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    faces = app.get(rgb)
    resultados = []
    for face in faces:
        # Usar la MISMA validacion estricta que el registro/verificacion individual
        det_score = float(getattr(face, 'det_score', 0))
        if det_score < 0.75:
            continue
        kps = getattr(face, 'kps', None)
        if kps is None or len(kps) < 5:
            continue
        embedding = face.normed_embedding
        if embedding is None or len(embedding) == 0:
            continue
        embedding = embedding.tolist()
        bbox = face.bbox.astype(int).tolist()
        resultados.append({'embedding': embedding, 'bbox': bbox})
    return resultados

if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(json.dumps({'success': False, 'error': 'Argumentos insuficientes'}))
        sys.exit(0)

    accion = sys.argv[1]
    ruta = sys.argv[2]

    try:
        img = cv2.imread(ruta, cv2.IMREAD_COLOR)
        if img is None:
            print(json.dumps({'success': False, 'error': 'No se pudo leer la imagen: ' + ruta}))
            sys.exit(0)

        if accion == 'validar':
            valido, mensaje = validar_rostro(img)
            print(json.dumps({'success': True, 'valido': valido, 'mensaje': mensaje}))
        elif accion == 'detectar':
            valido, mensaje = validar_rostro(img)
            print(json.dumps({'success': True, 'detectado': valido, 'mensaje': mensaje}))
        elif accion == 'registrar':
            embedding, calidad, error = obtener_embedding_y_calidad(img)
            if embedding is None:
                print(json.dumps({'success': False, 'error': error}))
            else:
                print(json.dumps({'success': True, 'embedding': embedding, 'calidad': calidad}))
        elif accion == 'verificar':
            embedding, calidad, error = obtener_embedding_y_calidad(img)
            if embedding is None:
                print(json.dumps({'success': False, 'error': error}))
            else:
                print(json.dumps({'success': True, 'embedding': embedding}))
        elif accion == 'verificar_multi':
            rostros = obtener_todos_embeddings(img)
            if len(rostros) == 0:
                print(json.dumps({'success': False, 'error': 'No se detecto ningun rostro valido'}))
            else:
                print(json.dumps({'success': True, 'rostros': rostros}))
        else:
            print(json.dumps({'success': False, 'error': f'Accion desconocida: {accion}'}))
    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}))
