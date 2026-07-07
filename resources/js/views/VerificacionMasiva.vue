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
import API from '../services/api.js';
import { auth } from '../stores/auth.js';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const videoRef = ref(null);
const overlayRef = ref(null);
const camaraActiva = ref(false);
const aulas = ref([]);
const aulaSeleccionada = ref(null);
const alumnos = ref([]);
const cargandoAlumnos = ref(false);

let stream = null;
let rafId = null;
let verifInterval = null;
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

async function tickVerificacion() {
    if (!videoRef.value || !camaraActiva.value || tickVerificacion._busy) return;
    tickVerificacion._busy = true;

    const v = videoRef.value;
    const w = v.videoWidth || 640;
    const h = v.videoHeight || 480;

    const canvasTmp = document.createElement('canvas');
    canvasTmp.width = w;
    canvasTmp.height = h;
    const ctx = canvasTmp.getContext('2d');
    ctx.drawImage(v, 0, 0, w, h);

    const blob = await new Promise((res) => canvasTmp.toBlob(res, 'image/jpeg', 0.6));
    const fd = new FormData();
    fd.append('foto', blob, 'masivo.jpg');

    try {
        const res = await API.verificarMasivo(fd, auth.usuario?.usuario);
        const ahora = Date.now();

        if (res.rostros && Array.isArray(res.rostros)) {
            res.rostros.forEach((r) => {
                const bbox = r.bbox || [0, 0, 100, 100];
                const rcx = (bbox[0] + bbox[2]) / 2;
                const rcy = (bbox[1] + bbox[3]) / 2;

                let mejorTrack = null;
                let mejorDist = Infinity;
                tracks.value.forEach((t) => {
                    const dx = t.rawCx - rcx;
                    const dy = t.rawCy - rcy;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < mejorDist) {
                        mejorDist = dist;
                        mejorTrack = t;
                    }
                });

                if (mejorTrack && mejorDist < 150) {
                    if (r.conocido) {
                        mejorTrack.conocido = true;
                        mejorTrack.label = r.nombre || '';
                        mejorTrack.aula = r.aula || '';
                        mejorTrack.esMiAula = r.es_mi_aula ?? false;
                        mejorTrack.dni = r.dni || '';
                        mejorTrack.estado = r.estado || '';
                        mejorTrack.confianza = r.confianza || 0;
                        mejorTrack.lastUpdate = ahora;
                        mejorTrack.identityExpiresAt = ahora + 3000;

                        if (r.es_mi_aula && r.estado !== 'Asistió' && !yaMarcadosEnSesion.has(r.dni)) {
                            const last = cooldownPorDni.get(r.dni);
                            if (!last || ahora - last > COOLDOWN_MS) {
                                cooldownPorDni.set(r.dni, ahora);
                                yaMarcadosEnSesion.add(r.dni);
                                API.marcarAsistencia(r.dni).then(() => {
                                    toast.add({ severity: 'success', summary: 'Asistencia', detail: `${r.nombre} marcado como Asistió`, life: 2500 });
                                    cargarAlumnos();
                                }).catch(() => {});
                            }
                        }
                    } else {
                        // Backend dice DESCONOCIDO: resetear INMEDIATAMENTE
                        mejorTrack.conocido = false;
                        mejorTrack.label = '';
                        mejorTrack.aula = '';
                        mejorTrack.esMiAula = false;
                        mejorTrack.dni = '';
                        mejorTrack.estado = '';
                        mejorTrack.confianza = 0;
                        mejorTrack.identityExpiresAt = 0;
                    }
                }
            });
        }
    } catch (e) {
        console.error('Verificacion error:', e);
    } finally {
        tickVerificacion._busy = false;
    }
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
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo resetear', life: 3000 });
    }
}

onMounted(async () => {
    await cargarAulas();
    await iniciarCamara();
    await iniciarFaceMesh();
    verifInterval = setInterval(tickVerificacion, 300);
});

onUnmounted(() => {
    if (rafId) cancelAnimationFrame(rafId);
    if (verifInterval) clearInterval(verifInterval);
    if (stream) stream.getTracks().forEach((t) => t.stop());
});
</script>
