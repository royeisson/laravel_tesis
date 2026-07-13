<template>
  <div class="flex flex-col lg:flex-row gap-4 h-full">
    <div class="flex-1 flex flex-col gap-3">
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span class="font-bold text-lg">Verificación Masiva</span>
            <div class="flex items-center gap-2">
              <Select v-if="aulas.length > 1" v-model="aulaSeleccionada" :options="aulas" optionLabel="nombre" optionValue="id" placeholder="Selecciona aula" class="w-52" @change="cargarAlumnos" />
              <Tag v-else-if="aulas.length === 1" :value="aulas[0].nombre" severity="info" class="text-sm" />
              <Button icon="pi pi-refresh" text rounded @click="resetAsistencias" v-tooltip.top="'Resetear asistencias'" />
            </div>
          </div>
        </template>
        <template #content>
          <div class="relative w-full bg-black rounded-xl overflow-hidden border border-gray-300 shadow-lg" style="height: 480px">
            <video ref="videoRef" autoplay playsinline muted class="w-full h-full object-cover" style="transform: scaleX(-1)"></video>
            <canvas ref="overlayRef" class="absolute inset-0 w-full h-full pointer-events-none"></canvas>

            <div v-if="!camaraActiva" class="absolute inset-0 flex items-center justify-center bg-black/80 text-white z-10">
              <div class="text-center">
                <i class="pi pi-camera text-5xl mb-3 animate-pulse"></i>
                <p class="text-lg">Iniciando cámara...</p>
              </div>
            </div>

            <div v-if="!wsConectado && camaraActiva" class="absolute top-3 left-1/2 -translate-x-1/2 bg-red-600 text-white px-3 py-1 rounded-full text-xs font-semibold z-10 flex items-center gap-2">
              <i class="pi pi-times-circle"></i>
              <span>Servidor facial desconectado</span>
            </div>
          </div>
        </template>
      </Card>
    </div>

    <div class="lg:w-80 flex flex-col gap-2">
      <Card>
        <template #title>
          <div class="flex items-center justify-between">
            <span>Lista del Aula</span>
            <Tag :value="`${alumnos.filter(a=>a.estado==='Asistió').length}/${alumnos.length}`" severity="info" />
          </div>
        </template>
        <template #content>
          <div v-if="cargandoAlumnos" class="flex justify-center py-4">
            <ProgressSpinner style="width: 30px; height: 30px" />
          </div>
          <div v-else-if="alumnos.length === 0" class="text-center text-gray-500 py-4">No hay alumnos en esta aula</div>
          <div v-else class="flex flex-col gap-2 max-h-[480px] overflow-y-auto">
            <div v-for="alumno in alumnos" :key="alumno.dni" class="flex items-center gap-2 p-2 rounded border" :class="alumno.estado === 'Asistió' ? 'border-green-400 bg-green-50' : 'border-gray-200'">
              <Avatar v-if="alumno.foto_path" :image="`/storage/fotos/${alumno.foto_path}`" shape="circle" size="normal" />
              <Avatar v-else icon="pi pi-user" shape="circle" size="normal" />
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold truncate">{{ alumno.nombre }}</p>
                <p class="text-xs text-gray-500">{{ alumno.dni }}</p>
              </div>
              <Tag :value="alumno.estado" :severity="alumno.estado === 'Asistió' ? 'success' : 'danger'" class="text-xs" />
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { initFaceMesh } from '../utils/faceValidator.js';
import faceSocket from '../services/faceSocket.js';
import API from '../services/api.js';
import { auth } from '../stores/auth.js';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const videoRef = ref(null);
const overlayRef = ref(null);
const camaraActiva = ref(false);
const wsConectado = ref(false);
const aulas = ref([]);
const aulaSeleccionada = ref(null);
const alumnos = ref([]);
const cargandoAlumnos = ref(false);

let stream = null;
let rafId = null;
let faceMesh = null;

const tracks = ref([]);
let nextTrackId = 1;
const cooldownPorDni = new Map();
const yaMarcadosEnSesion = new Set();
const COOLDOWN_MS = 4000;

async function iniciarCamara() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
        });
        if (videoRef.value) {
            videoRef.value.srcObject = stream;
            await new Promise((resolve) => {
                videoRef.value.onloadedmetadata = () => resolve();
            });
            await videoRef.value.play();
            camaraActiva.value = true;
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo acceder a la cámara', life: 3000 });
    }
}

async function iniciarFaceMesh() {
    try {
        faceMesh = await initFaceMesh(5);
        faceMesh.onResults(onFaceMeshResults);
        loopFaceMesh();
    } catch (e) {
        console.error('FaceMesh no cargó:', e);
    }
}

async function loopFaceMesh() {
    if (!faceMesh || !videoRef.value || !camaraActiva.value) return;
    await faceMesh.send({ image: videoRef.value });
    rafId = requestAnimationFrame(loopFaceMesh);
}

function onFaceMeshResults(results) {
    const canvas = overlayRef.value;
    const video = videoRef.value;
    if (!canvas || !video) return;

    const ctx = canvas.getContext('2d');
    const cw = video.videoWidth || 640;
    const ch = video.videoHeight || 480;
    canvas.width = cw;
    canvas.height = ch;
    ctx.clearRect(0, 0, cw, ch);

    const ahora = Date.now();
    const nuevosTracks = [];

    if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
        results.multiFaceLandmarks.forEach((landmarks) => {
            let minX = 1, minY = 1, maxX = 0, maxY = 0;
            landmarks.forEach((lm) => {
                if (lm.x < minX) minX = lm.x;
                if (lm.y < minY) minY = lm.y;
                if (lm.x > maxX) maxX = lm.x;
                if (lm.y > maxY) maxY = lm.y;
            });

            const rawX1 = Math.round(minX * cw);
            const rawY1 = Math.round(minY * ch);
            const rawX2 = Math.round(maxX * cw);
            const rawY2 = Math.round(maxY * ch);
            const rawCx = (rawX1 + rawX2) / 2;
            const rawCy = (rawY1 + rawY2) / 2;

            let track = tracks.value.find((t) => {
                const dx = t.rawCx - rawCx;
                const dy = t.rawCy - rawCy;
                return Math.sqrt(dx * dx + dy * dy) < cw * 0.25;
            });

            if (!track) {
                track = {
                    id: nextTrackId++,
                    rawX1, rawY1, rawX2, rawY2,
                    rawCx, rawCy,
                    label: '', aula: '',
                    conocido: false, esMiAula: false,
                    confianza: 0, lastUpdate: 0,
                    identityExpiresAt: 0,
                };
            } else {
                track.rawX1 = rawX1; track.rawY1 = rawY1;
                track.rawX2 = rawX2; track.rawY2 = rawY2;
                track.rawCx = rawCx; track.rawCy = rawCy;
            }
            track.lastSeen = ahora;
            nuevosTracks.push(track);
        });
    }

    tracks.value = tracks.value.filter((t) => ahora - (t.lastSeen || 0) < 500).map((t) => {
        const existe = nuevosTracks.find((n) => n.id === t.id);
        if (!existe) {
            dibujarTrack(ctx, t, cw, ch, ahora);
            return t;
        }
        return t;
    });

    nuevosTracks.forEach((n) => {
        if (!tracks.value.find((t) => t.id === n.id)) {
            tracks.value.push(n);
        }
    });

    tracks.value.forEach((t) => dibujarTrack(ctx, t, cw, ch, ahora));

    // Enviar frame por WebSocket si hay rostros y la conexion esta lista
    if (wsConectado.value && results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
        enviarFramePorWebSocket();
    }
}

function dibujarTrack(ctx, track, cw, ch, ahora) {
    const x1 = cw - track.rawX2;
    const x2 = cw - track.rawX1;
    const y1 = track.rawY1;
    const y2 = track.rawY2;

    const w = x2 - x1;
    const h = y2 - y1;
    const cx = x1 + w / 2;

    const esConocido = track.conocido && ahora < (track.identityExpiresAt || 0);
    const color = esConocido ? '#22c55e' : '#ef4444';

    ctx.strokeStyle = color;
    ctx.lineWidth = 4;
    ctx.strokeRect(x1, y1, w, h);

    let texto = '';
    if (esConocido) {
        texto = `${track.label.toUpperCase()}  |  ${track.aula || ''}`;
        if (!track.esMiAula) texto += ' (NO ES TU AULA)';
    } else {
        texto = '...';
    }

    ctx.font = 'bold 15px sans-serif';
    const textW = ctx.measureText(texto).width + 16;
    const boxH = 28;
    const boxX = Math.max(4, cx - textW / 2);
    const boxY = Math.max(4, y1 - boxH - 6);

    ctx.fillStyle = esConocido ? 'rgba(34,197,94,0.95)' : 'rgba(239,68,68,0.95)';
    ctx.fillRect(boxX, boxY, textW, boxH);

    ctx.fillStyle = '#ffffff';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(texto, cx, boxY + boxH / 2 + 1);
}

let lastFrameTime = 0;
async function enviarFramePorWebSocket() {
    const ahora = Date.now();
    if (ahora - lastFrameTime < 200) return; // max 5 FPS al servidor (reduce carga y desconexiones)
    lastFrameTime = ahora;

    const v = videoRef.value;
    const w = v.videoWidth || 640;
    const h = v.videoHeight || 480;

    const canvasTmp = document.createElement('canvas');
    canvasTmp.width = w;
    canvasTmp.height = h;
    const ctx = canvasTmp.getContext('2d');
    ctx.drawImage(v, 0, 0, w, h);

    const blob = await new Promise((res) => canvasTmp.toBlob(res, 'image/jpeg', 0.6));
    faceSocket.sendFrame(blob);
}

function calcularIoU(a, b) {
    // a, b: [x1, y1, x2, y2]
    const xi1 = Math.max(a[0], b[0]);
    const yi1 = Math.max(a[1], b[1]);
    const xi2 = Math.min(a[2], b[2]);
    const yi2 = Math.min(a[3], b[3]);
    const interArea = Math.max(0, xi2 - xi1) * Math.max(0, yi2 - yi1);
    const boxAArea = (a[2] - a[0]) * (a[3] - a[1]);
    const boxBArea = (b[2] - b[0]) * (b[3] - b[1]);
    const unionArea = boxAArea + boxBArea - interArea;
    return unionArea > 0 ? interArea / unionArea : 0;
}

function manejarResultadoWebSocket(res) {
    const ahora = Date.now();
    const misAulasIds = aulas.value.map((a) => a.id);

    if (!res.rostros || !Array.isArray(res.rostros)) return;

    // Solo considerar tracks activos (vistos en los ultimos 800ms)
    const tracksActivos = tracks.value.filter((t) => ahora - (t.lastSeen || 0) < 800);

    // ================================================================
    // ASIGNACION OPTIMA GLOBAL (Hungarian-like greedy)
    // Construimos una matriz de costos entre TODOS los resultados
    // del servidor y TODOS los tracks de MediaPipe, luego asignamos
    // iterativamente el par con menor costo. Esto evita que un
    // resultado "robe" el track de otro rostro.
    // ================================================================
    const costos = [];
    res.rostros.forEach((r, ri) => {
        const serverBbox = r.bbox || [0, 0, 100, 100];
        tracksActivos.forEach((t, ti) => {
            const trackBbox = [t.rawX1, t.rawY1, t.rawX2, t.rawY2];
            const iou = calcularIoU(serverBbox, trackBbox);
            if (iou < 0.10) return; // Descartar temprano si casi no se solapan

            let costo = 1 - iou; // base: menor IoU = mayor costo

            // Penalizar fuertemente si el track ya tiene otra identidad
            // confirmada (evita que un rostro "robe" el nombre de otro)
            const graciaActiva = t.conocido && ahora < (t.identityExpiresAt || 0);
            if (graciaActiva && t.dni && r.dni && t.dni !== r.dni) {
                costo += 2.0; // Penalizacion muy alta
            }
            // Penalizar moderadamente si el track ya tiene otra identidad
            // aunque haya expirado la gracia (preferir mantener identidad)
            else if (t.conocido && t.dni && r.dni && t.dni !== r.dni) {
                costo += 0.5;
            }

            costos.push({ ri, ti, costo, iou, r, t });
        });
    });

    // Ordenar por costo ascendente (mejor asignacion primero)
    costos.sort((a, b) => a.costo - b.costo);

    const resultadosUsados = new Set();
    const tracksUsados = new Set();

    costos.forEach(({ ri, ti, iou, r, t }) => {
        if (resultadosUsados.has(ri) || tracksUsados.has(t.id)) return;
        if (iou < 0.15) return; // Umbral minimo de solapamiento

        resultadosUsados.add(ri);
        tracksUsados.add(t.id);

        if (r.conocido) {
            const esMiAula = misAulasIds.includes(r.aula_id);
            const aulaNombre = aulas.value.find((a) => a.id === r.aula_id)?.nombre || '';

            t.conocido = true;
            t.label = r.nombre || '';
            t.aula = aulaNombre;
            t.esMiAula = esMiAula;
            t.dni = r.dni || '';
            t.estado = r.estado || '';
            t.confianza = r.confianza || 0;
            t.lastUpdate = ahora;
            t.identityExpiresAt = ahora + 15000; // 15 segundos de gracia

            const alumnoEnLista = alumnos.value.find((a) => a.dni === r.dni);
            if (esMiAula && alumnoEnLista && alumnoEnLista.estado !== 'Asistió' && !yaMarcadosEnSesion.has(r.dni)) {
                const last = cooldownPorDni.get(r.dni);
                if (!last || ahora - last > COOLDOWN_MS) {
                    cooldownPorDni.set(r.dni, ahora);
                    yaMarcadosEnSesion.add(r.dni);
                    API.marcarAsistencia(r.dni).then(() => {
                        const alumno = alumnos.value.find((a) => a.dni === r.dni);
                        if (alumno) alumno.estado = 'Asistió';
                        toast.add({ severity: 'success', summary: 'Asistencia', detail: `${r.nombre} marcado como Asistió`, life: 2500 });
                        cargarAlumnos();
                    }).catch(() => {});
                }
            }
        } else {
            // Resultado DESCONOCIDO del servidor
            // NUNCA resetear un track que esta identificado y en periodo de gracia
            const graciaActiva = t.conocido && ahora < (t.identityExpiresAt || 0);
            if (graciaActiva) {
                return; // Ignorar completamente
            }

            t.conocido = false;
            t.label = '';
            t.aula = '';
            t.esMiAula = false;
            t.dni = '';
            t.estado = '';
            t.confianza = 0;
            t.identityExpiresAt = 0;
        }
    });
}

async function cargarAulas() {
    try {
        const data = await API.obtenerMisAulas(auth.usuario?.usuario);
        aulas.value = data;
        if (data.length === 1) {
            aulaSeleccionada.value = data[0].id;
            await cargarAlumnos();
        }
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las aulas', life: 3000 });
    }
}

async function cargarAlumnos() {
    if (!aulaSeleccionada.value) return;
    cargandoAlumnos.value = true;
    try {
        alumnos.value = await API.listarAsistenciaPorAula(aulaSeleccionada.value);
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los alumnos', life: 3000 });
    } finally {
        cargandoAlumnos.value = false;
    }
}

async function resetAsistencias() {
    if (!aulaSeleccionada.value) return;
    try {
        await API.resetAsistencia(aulaSeleccionada.value);
        toast.add({ severity: 'success', summary: 'Reseteado', detail: 'Asistencias reiniciadas', life: 3000 });
        await cargarAlumnos();
        // LIMPIAR: permitir que los alumnos vuelvan a marcar asistencia
        yaMarcadosEnSesion.clear();
        cooldownPorDni.clear();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo resetear', life: 3000 });
    }
}

onMounted(async () => {
    await cargarAulas();
    await iniciarCamara();
    await iniciarFaceMesh();

    // Conectar WebSocket (sin desconectar en unmount: singleton persistente)
    faceSocket.onConnect = () => {
        wsConectado.value = true;
        toast.add({ severity: 'success', summary: 'Servidor Facial', detail: 'Conectado (modo rápido)', life: 3000 });
    };
    faceSocket.onDisconnect = () => {
        wsConectado.value = false;
        toast.add({ severity: 'warn', summary: 'Servidor Facial', detail: 'Desconectado. Intentando reconectar...', life: 3000 });
    };
    faceSocket.onMessage = manejarResultadoWebSocket;
    faceSocket.connect();

    // Si ya estaba conectado (viniendo de otra vista), activar inmediatamente
    if (faceSocket.connected) {
        wsConectado.value = true;
    }
});

onUnmounted(() => {
    if (rafId) cancelAnimationFrame(rafId);
    if (stream) stream.getTracks().forEach((t) => t.stop());
    // NO llamamos faceSocket.disconnect() para mantener la conexión viva al navegar
});
</script>
