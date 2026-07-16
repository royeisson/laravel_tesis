<template>
  <div class="flex flex-col lg:flex-row gap-4 max-w-5xl">
    <Card class="flex-1 w-full">
      <template #title>Captura Biométrica</template>
      <template #content>
        <div class="flex flex-col items-center gap-3">
          <div class="w-full max-w-[320px]">
            <CameraView
              ref="cameraRef"
              :auto-start="false"
              @stream-ready="onStreamReady"
              @stream-ended="onStreamEnded"
            />
          </div>
          <Message :severity="estadoSeverity" class="w-full">
            {{ estadoTexto }}
          </Message>
        </div>
      </template>
    </Card>
    <Card class="flex-1">
      <template #title>Datos del Alumno</template>
      <template #content>
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-1">
            <label for="dni">DNI</label>
            <InputText id="dni" v-model="dni" placeholder="12345678" maxlength="8" />
          </div>
          <div class="flex flex-col gap-1">
            <label for="nombre">Nombre Completo</label>
            <InputText id="nombre" v-model="nombre" placeholder="Juan Pérez" />
          </div>
          <div class="flex flex-col gap-1">
            <label for="carrera">Carrera</label>
            <Select id="carrera" v-model="carrera" :options="carreras" placeholder="Seleccionar carrera" />
          </div>
          <Message v-if="mensaje" :severity="mensaje.tipo" :closable="false">{{ mensaje.texto }}</Message>
          <Button label="Registrar" icon="pi pi-save" @click="registrar" :disabled="btnRegistrarDisabled || cargando" :loading="cargando" />
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import API from '../services/api';
import CameraView from '../components/CameraView.vue';
import { useToast } from 'primevue/usetoast';
import { initFaceMesh, analizarRostro } from '../utils/faceValidator.js';

const toast = useToast();
const cameraRef = ref(null);
const dni = ref('');
const nombre = ref('');
const carrera = ref(null);
const mensaje = ref(null);
const cargando = ref(false);

const estadoTexto = ref('Cámara apagada');
const estadoSeverity = ref('error');
const btnRegistrarDisabled = ref(true);

let deteccionActiva = false;
let animFrameId = null;
let faceMesh = null;
let consecutivosValidos = 0;
let consecutivosInvalidos = 0;

const carreras = [
    'Agronomía', 'Biología', 'Contabilidad', 'Economía', 'Administración',
    'Comercio y Negocios Internacionales', 'Física', 'Matemáticas', 'Estadística',
    'Ingeniería de Computación e Informática', 'Ingeniería Electrónica', 'Educación',
    'Sociología', 'Ciencias de la Comunicación', 'Psicología', 'Arte', 'Arqueología',
    'Derecho', 'Ciencia Política', 'Enfermería', 'Ingeniería Agrícola', 'Ingeniería Civil',
    'Arquitectura', 'Ingeniería de Sistemas', 'Ingeniería Mecánica y Eléctrica',
    'Medicina Humana', 'Medicina Veterinaria', 'Ingeniería Química',
    'Ingeniería en Industrias Alimentarias', 'Zootecnia',
];

function actualizarEstado(valido, msg) {
    estadoTexto.value = msg;
    estadoSeverity.value = valido ? 'success' : 'warn';
    if (valido) {
        consecutivosValidos++;
        consecutivosInvalidos = 0;
        if (consecutivosValidos >= 2) {
            btnRegistrarDisabled.value = false;
        }
    } else {
        consecutivosInvalidos++;
        consecutivosValidos = 0;
        if (consecutivosInvalidos >= 4) {
            btnRegistrarDisabled.value = true;
        }
    }
}

async function procesarFrame() {
    if (!deteccionActiva) return;

    const video = cameraRef.value?.videoEl;
    if (!video || !document.body.contains(video) || video.readyState < 2) {
        animFrameId = requestAnimationFrame(procesarFrame);
        return;
    }

    try {
        await faceMesh.send({ image: video });
    } catch {
        // Silencioso
    }

    animFrameId = requestAnimationFrame(procesarFrame);
}

async function onStreamReady() {
    deteccionActiva = true;
    consecutivosValidos = 0;
    consecutivosInvalidos = 0;
    estadoTexto.value = 'Iniciando detector facial...';
    estadoSeverity.value = 'info';

    try {
        faceMesh = await initFaceMesh();
        faceMesh.onResults((results) => {
            if (!deteccionActiva) return;
            if (results.multiFaceLandmarks && results.multiFaceLandmarks.length > 0) {
                const res = analizarRostro(results.multiFaceLandmarks[0]);
                actualizarEstado(res.valido, res.mensaje);
            } else {
                actualizarEstado(false, 'Rostro no detectado.');
            }
        });
        procesarFrame();
    } catch (e) {
        estadoTexto.value = 'Error al cargar detector facial';
        estadoSeverity.value = 'error';
        console.error(e);
    }
}

function onStreamEnded() {
    deteccionActiva = false;
    if (animFrameId) cancelAnimationFrame(animFrameId);
    consecutivosValidos = 0;
    consecutivosInvalidos = 0;
    estadoTexto.value = 'Cámara apagada';
    estadoSeverity.value = 'error';
    btnRegistrarDisabled.value = true;
}

async function registrar() {
    if (!dni.value || !nombre.value || !carrera.value) {
        mensaje.value = { tipo: 'warn', texto: 'Todos los campos son obligatorios' };
        return;
    }
    if (!cameraRef.value?.tieneStream()) {
        toast.add({ severity: 'warn', summary: 'Cámara apagada', detail: 'Enciende la cámara primero.', life: 3000 });
        return;
    }
    if (btnRegistrarDisabled.value) {
        toast.add({ severity: 'warn', summary: 'Rostro no válido', detail: 'Posiciona correctamente tu rostro en el óvalo.', life: 4000 });
        return;
    }

    cargando.value = true;
    mensaje.value = null;
    try {
        const blob = await cameraRef.value.capturarFotoNativa();
        const fd = new FormData();
        fd.append('dni', dni.value);
        fd.append('nombre', nombre.value);
        fd.append('carrera', carrera.value);
        fd.append('aula_id', '');
        fd.append('foto', blob, 'captura.jpg');
        const res = await API.registrarRostro(fd);
        const data = await res.json();
        if (res.ok) {
            toast.add({ severity: 'success', summary: 'Registrado', detail: data.mensaje, life: 4000 });
            dni.value = ''; nombre.value = ''; carrera.value = null;
        } else {
            mensaje.value = { tipo: 'error', texto: data.detalle || 'Error al registrar' };
            toast.add({ severity: 'error', summary: 'Error', detail: data.detalle || 'Error al registrar', life: 4000 });
        }
    } catch (e) {
        mensaje.value = { tipo: 'error', texto: e.message || 'Error de conexión' };
    } finally {
        cargando.value = false;
    }
}

onMounted(async () => {
    setTimeout(() => cameraRef.value?.toggleCam(), 800);
});

onUnmounted(() => {
    deteccionActiva = false;
    if (animFrameId) cancelAnimationFrame(animFrameId);
});
</script>
