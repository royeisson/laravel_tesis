<template>
  <div class="flex flex-col lg:flex-row gap-4 max-w-5xl">
    <div class="flex flex-col items-center gap-2">
      <CameraView ref="cameraRef" :width="320" :height="240" />
      <Button label="Encender / Apagar" icon="pi pi-camera" @click="toggleCam" severity="secondary" />
    </div>
    <Card class="flex-1">
      <template #title>Resultado</template>
      <template #content>
        <div v-if="resultado" class="flex flex-col items-center gap-3">
          <Avatar :image="resultado.foto_url" size="xlarge" shape="circle" />
          <div class="text-center">
            <h3 class="text-xl font-bold">{{ resultado.nombre }}</h3>
            <p class="text-sm text-gray-500">{{ resultado.carrera }} - {{ resultado.aula }}</p>
            <p class="text-sm text-gray-400">DNI: {{ resultado.dni }}</p>
          </div>
          <Tag :severity="resultado.exitoso ? 'success' : 'danger'" :value="resultado.mensaje" />
          <small class="text-gray-400">{{ resultado.timestamp }}</small>
          <small v-if="resultado.distancia !== undefined" class="text-gray-300">Distancia: {{ resultado.distancia.toFixed(4) }}</small>
        </div>
        <div v-else-if="cargando" class="flex justify-center p-4">
          <ProgressSpinner style="width: 40px; height: 40px" />
        </div>
        <div v-else class="text-center text-gray-400 p-4">
          <i class="pi pi-camera" style="font-size: 2rem; opacity: 0.3"></i>
          <p class="mt-2">Muestra tu rostro a la cámara para verificar</p>
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

const toast = useToast();
const cameraRef = ref(null);
const resultado = ref(null);
const cargando = ref(false);

let intervaloVerificacion = null;
let timeoutLimpiar = null;

onMounted(() => {
    setTimeout(() => {
        cameraRef.value?.toggleCam();
        iniciarVerificacionAutomatica();
    }, 1500);
});

onUnmounted(() => {
    detenerVerificacionAutomatica();
});

function toggleCam() {
    if (cameraRef.value?.tieneStream()) {
        cameraRef.value?.toggleCam();
        detenerVerificacionAutomatica();
    } else {
        cameraRef.value?.toggleCam();
        iniciarVerificacionAutomatica();
    }
}

function iniciarVerificacionAutomatica() {
    detenerVerificacionAutomatica();
    intervaloVerificacion = setInterval(async () => {
        if (!cameraRef.value?.tieneStream() || cargando.value) return;
        await verificar();
    }, 1800);
}

function detenerVerificacionAutomatica() {
    if (intervaloVerificacion) {
        clearInterval(intervaloVerificacion);
        intervaloVerificacion = null;
    }
    if (timeoutLimpiar) {
        clearTimeout(timeoutLimpiar);
        timeoutLimpiar = null;
    }
}

function programarLimpieza() {
    if (timeoutLimpiar) clearTimeout(timeoutLimpiar);
    timeoutLimpiar = setTimeout(() => {
        resultado.value = null;
    }, 5000);
}

async function verificar() {
    if (!cameraRef.value?.tieneStream()) return;

    cargando.value = true;
    try {
        // Usar captura a resolución nativa para mejor comparación
        const blob = await cameraRef.value.capturarFotoNativa();
        const fd = new FormData();
        fd.append('foto', blob, 'captura.jpg');
        const res = await API.verificarRostro(fd);
        const data = await res.json();
        if (res.ok) {
            resultado.value = data;
            programarLimpieza();
        } else {
            toast.add({ severity: 'warn', summary: 'Verificación', detail: data.detalle || 'No se pudo verificar', life: 2500 });
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'Error de red', life: 2500 });
    } finally {
        cargando.value = false;
    }
}
</script>
