<template>
  <div class="flex flex-col lg:flex-row gap-6 max-w-6xl mx-auto">
    <!-- Panel Cámara -->
    <div class="flex flex-col items-center gap-3">
      <CameraView ref="cameraRef" :width="360" :height="270" />
      <Button label="Encender / Apagar" icon="pi pi-camera" @click="toggleCam" severity="secondary" />
    </div>

    <!-- Panel Resultado -->
    <Card class="flex-1 min-h-[320px]">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-id-card text-indigo-500"></i>
          <span>Resultado de Verificación</span>
        </div>
      </template>
      <template #content>
        <!-- Resultado exitoso -->
        <div v-if="resultado && resultado.exitoso" class="flex flex-col items-center gap-5 py-2">
          <Avatar :image="resultado.foto_url" size="xlarge" shape="circle" class="w-28 h-28 shadow-lg" />
          <div class="text-center space-y-1">
            <h3 class="text-3xl font-bold text-gray-800">{{ resultado.nombre }}</h3>
            <p class="text-lg text-gray-500">{{ resultado.carrera }}</p>
            <p class="text-base text-gray-400 flex items-center justify-center gap-2">
              <i class="pi pi-map-marker text-indigo-400"></i>
              {{ resultado.aula }}
            </p>
            <p class="text-base text-gray-400 font-mono tracking-wide">DNI: {{ resultado.dni }}</p>
          </div>
          <Tag severity="success" value="Verificación Exitosa" class="text-lg px-4 py-2" />
          <div class="text-center space-y-1">
            <p class="text-sm text-gray-400">{{ resultado.timestamp }}</p>
            <p v-if="resultado.distancia !== undefined" class="text-xs text-gray-300">
              Confianza: {{ (1 - resultado.distancia).toFixed(3) }}
            </p>
          </div>
        </div>

        <!-- Resultado fallido -->
        <div v-else-if="resultado && !resultado.exitoso" class="flex flex-col items-center gap-5 py-2">
          <div class="w-28 h-28 rounded-full bg-red-50 flex items-center justify-center">
            <i class="pi pi-times-circle text-red-400" style="font-size: 4rem"></i>
          </div>
          <div class="text-center space-y-1">
            <h3 class="text-2xl font-bold text-gray-800">Coincidencia no encontrada</h3>
            <p class="text-base text-gray-500">El rostro no está registrado en el sistema</p>
          </div>
          <Tag severity="danger" value="No registrado" class="text-lg px-4 py-2" />
          <p class="text-sm text-gray-400">{{ resultado.timestamp }}</p>
        </div>

        <!-- Cargando -->
        <div v-else-if="cargando" class="flex flex-col items-center justify-center gap-4 py-12">
          <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" />
          <p class="text-gray-500 text-lg">Verificando rostro...</p>
        </div>

        <!-- Estado inicial -->
        <div v-else class="flex flex-col items-center justify-center gap-4 py-12 text-gray-400">
          <div class="w-24 h-24 rounded-full bg-gray-100 flex items-center justify-center">
            <i class="pi pi-camera text-gray-300" style="font-size: 3rem"></i>
          </div>
          <p class="text-lg">Muestra tu rostro a la cámara para verificar</p>
          <p class="text-sm text-gray-300">La verificación es automática</p>
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
    }, 1200);
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
    }, 2200);
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
    }, 6000);
}

async function verificar() {
    if (!cameraRef.value?.tieneStream()) return;

    cargando.value = true;
    try {
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
