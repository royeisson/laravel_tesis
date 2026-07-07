function cargarScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.crossOrigin = 'anonymous';
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

let faceMeshInstance = null;
let FaceMeshClass = null;

export async function initFaceMesh(maxFaces = 1) {
    if (faceMeshInstance && faceMeshInstance._maxFaces === maxFaces) return faceMeshInstance;

    if (!window.FaceMesh) {
        await cargarScript('https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/face_mesh.js');
    }
    FaceMeshClass = window.FaceMesh;

    const faceMesh = new FaceMeshClass({
        locateFile: (file) => {
            return `https://cdn.jsdelivr.net/npm/@mediapipe/face_mesh/${file}`;
        },
    });

    faceMesh.setOptions({
        maxNumFaces: maxFaces,
        refineLandmarks: true,
        minDetectionConfidence: 0.5,
        minTrackingConfidence: 0.5,
    });

    faceMesh._maxFaces = maxFaces;
    faceMeshInstance = faceMesh;
    return faceMesh;
}

function calcularYaw(landmarks) {
    const leftEye = landmarks[33];
    const rightEye = landmarks[263];
    const noseTip = landmarks[1];

    const dLeft = Math.abs(leftEye.x - noseTip.x);
    const dRight = Math.abs(rightEye.x - noseTip.x);
    const ratio = Math.min(dLeft, dRight) / Math.max(dLeft, dRight);
    return ratio;
}

function calcularPitch(landmarks) {
    const noseTip = landmarks[1];
    const chin = landmarks[152];
    const forehead = landmarks[10];

    const dNoseChin = Math.abs(chin.y - noseTip.y);
    const dNoseForehead = Math.abs(forehead.y - noseTip.y);
    const ratio = dNoseChin / (dNoseChin + dNoseForehead);
    return ratio;
}

function verificarOjosVisibles(landmarks) {
    // Usar solo los puntos clave de apertura de ojo en MediaPipe Face Mesh
    // Ojo izquierdo: 159 (superior), 145 (inferior)
    // Ojo derecho: 386 (superior), 374 (inferior)
    const leftTop = landmarks[159];
    const leftBottom = landmarks[145];
    const rightTop = landmarks[386];
    const rightBottom = landmarks[374];

    // Verificar que los puntos existan
    if (!leftTop || !leftBottom || !rightTop || !rightBottom) {
        return false;
    }

    // Calcular apertura vertical de cada ojo
    const leftOpen = Math.abs(leftTop.y - leftBottom.y);
    const rightOpen = Math.abs(rightTop.y - rightBottom.y);

    // Calcular distancia entre ojos como referencia de escala
    const leftEyeOuter = landmarks[33];
    const rightEyeOuter = landmarks[263];
    const eyeDist = Math.abs(rightEyeOuter.x - leftEyeOuter.x);

    // La apertura del ojo debe ser al menos 8% de la distancia entre ojos
    // (ojo humano normal: ~10-15%)
    const minOpen = eyeDist * 0.06;

    if (leftOpen < minOpen || rightOpen < minOpen) {
        return false;
    }

    // Verificar que los puntos no esten colapsados (estimados por el modelo)
    // Si superior e inferior son casi iguales, el modelo estimo sin ver el ojo real
    if (Math.abs(leftTop.x - leftBottom.x) > leftOpen * 2) {
        // Los puntos estan mas separados horizontalmente que verticalmente = anomalo
        return false;
    }
    if (Math.abs(rightTop.x - rightBottom.x) > rightOpen * 2) {
        return false;
    }

    return true;
}

function verificarBocaVisible(landmarks) {
    // Verificar que los puntos de la boca no esten comprimidos (tapados)
    // Usar varios puntos de los labios: 13, 14, 308, 78, 308, 14
    const upperLip = landmarks[13];
    const lowerLip = landmarks[14];
    const leftMouth = landmarks[78];
    const rightMouth = landmarks[308];

    // La boca debe tener cierto ancho (no estar tapada en una linea)
    const mouthWidth = Math.abs(rightMouth.x - leftMouth.x);
    const leftEye = landmarks[33];
    const rightEye = landmarks[263];
    const eyeDist = Math.abs(rightEye.x - leftEye.x);

    // El ancho de la boca debe ser al menos 25% de la distancia entre ojos
    return mouthWidth > eyeDist * 0.25;
}

export function analizarRostro(landmarks) {
    if (!landmarks || landmarks.length < 468) {
        return { valido: false, mensaje: 'No se detecto rostro completo' };
    }

    // 1. Verificar ojos visibles
    if (!verificarOjosVisibles(landmarks)) {
        return { valido: false, mensaje: 'Ojos tapados o no visibles. Retira cualquier objeto de tu rostro.' };
    }

    // 2. Verificar boca visible
    if (!verificarBocaVisible(landmarks)) {
        return { valido: false, mensaje: 'Boca tapada o rostro incompleto.' };
    }

    // 3. Verificar yaw (de frente)
    const yawRatio = calcularYaw(landmarks);
    if (yawRatio < 0.40) {
        return { valido: false, mensaje: 'Rostro de perfil. Mira directamente a la camara.' };
    }

    // 4. Verificar pitch (mirada horizontal)
    const pitchRatio = calcularPitch(landmarks);
    if (pitchRatio < 0.35 || pitchRatio > 0.65) {
        return { valido: false, mensaje: 'Mira directamente a la camara, sin inclinar la cabeza.' };
    }

    // 5. Verificar que el rostro ocupe suficiente espacio
    const faceTop = landmarks[10];
    const faceBottom = landmarks[152];
    const faceLeft = landmarks[234];
    const faceRight = landmarks[454];

    const faceHeight = Math.abs(faceBottom.y - faceTop.y);
    const faceWidth = Math.abs(faceRight.x - faceLeft.x);

    if (faceHeight < 0.25 || faceWidth < 0.20) {
        return { valido: false, mensaje: 'Acercate un poco mas a la camara.' };
    }

    // 6. Centrado
    const faceCenterX = (faceLeft.x + faceRight.x) / 2;
    if (faceCenterX < 0.30 || faceCenterX > 0.70) {
        return { valido: false, mensaje: 'Centra tu rostro en el ovalo.' };
    }

    return { valido: true, mensaje: 'Rostro listo para registrar' };
}
