<template>
  <div class="flex flex-col lg:flex-row gap-4 sm:gap-6 max-w-6xl mx-auto">
    <!-- Panel Cámara -->
    <div class="flex flex-col items-center gap-3 w-full lg:w-auto">
      <div class="w-full max-w-[360px] mx-auto">
        <CameraView ref="cameraRef" :width="360" :height="270" />
      </div>
      <Button label="Encender / Apagar" icon="pi pi-camera" @click="toggleCam" severity="secondary" />
      <Tag v-if="wsConectado" value="Modo Rápido (WebSocket)" severity="success" class="text-xs" />
      <Tag v-else value="Conectando a servidor facial..." severity="warn" class="text-xs" />
    </div>

    <!-- Panel Resultado -->
    <Card class="flex-1 min-h-[320px]">
      <template #title>
        <div class="flex items-center gap-2">
          <i class="pi pi-compass text-indigo-500"></i>
          <span>Identificación de Alumno</span>
        </div>
      </template>
      <template #content>
        <!-- Resultado exitoso -->
        <transition name="slide-fade" mode="out-in">
          <div v-if="resultado && resultado.exitoso" key="ok" class="flex flex-col items-center gap-5 py-2">
            <div class="bg-gradient-to-br from-indigo-500 to-violet-600 p-1 rounded-full shadow-xl">
              <Avatar
                v-if="resultado.foto_url"
                :image="resultado.foto_url"
                size="xlarge"
                shape="circle"
                class="w-28 h-28 ring-4 ring-white shadow-lg"
              />
              <Avatar
                v-else
                icon="pi pi-user"
                size="xlarge"
                shape="circle"
                class="w-28 h-28 ring-4 ring-white shadow-lg !bg-white/20"
              />
            </div>
            <div class="text-center space-y-1">
              <div class="text-xs font-bold text-indigo-500 uppercase tracking-widest">Alumno Identificado</div>
              <h3 class="text-3xl font-bold text-gray-800">{{ resultado.nombre }}</h3>
              <p class="text-lg text-gray-500">{{ resultado.carrera }}</p>
              <p class="text-base text-gray-400 font-mono tracking-wide mt-1">DNI: {{ resultado.dni }}</p>
            </div>
            <div class="bg-indigo-50 px-6 py-3 rounded-xl flex items-center gap-3 border border-indigo-100">
              <div class="w-11 h-11 rounded-xl bg-indigo-500 flex items-center justify-center shadow-md">
                <i class="pi pi-map-marker text-white" style="font-size: 1.3rem"></i>
              </div>
              <div>
                <p class="text-xs text-indigo-500 font-semibold uppercase tracking-wide">Dirigir a</p>
                <p class="text-xl font-bold text-indigo-900">{{ resultado.aula || 'Aula no asignada' }}</p>
              </div>
            </div>
            <div class="text-center space-y-1">
              <p class="text-sm text-gray-400">{{ resultado.timestamp }}</p>
              <p v-if="resultado.distancia !== undefined" class="text-xs text-gray-300">
                Confianza: {{ (1 - resultado.distancia).toFixed(3) }}
              </p>
            </div>
          </div>

          <!-- Resultado fallido -->
          <div v-else-if="resultado && !resultado.exitoso" key="fail" class="flex flex-col items-center gap-5 py-2">
            <div class="w-28 h-28 rounded-full bg-red-50 flex items-center justify-center">
              <i class="pi pi-times-circle text-red-400" style="font-size: 4rem"></i>
            </div>
            <div class="text-center space-y-1">
              <h3 class="text-2xl font-bold text-gray-800">Alumno no encontrado</h3>
              <p class="text-base text-gray-500">Dirígete al área de registro más cercana</p>
            </div>
            <Tag severity="danger" value="No registrado" class="text-lg px-4 py-2" />
            <p class="text-sm text-gray-400">{{ resultado.timestamp }}</p>
          </div>

          <!-- Cargando -->
          <div v-else-if="cargando" key="loading" class="flex flex-col items-center justify-center gap-4 py-12">
            <ProgressSpinner style="width: 60px; height: 60px" strokeWidth="4" />
            <p class="text-gray-500 text-lg">Identificando alumno...</p>
          </div>

          <!-- Estado inicial -->
          <div v-else key="init" class="flex flex-col items-center justify-center gap-4 py-12 text-gray-400">
            <div class="w-24 h-24 rounded-full bg-indigo-50 flex items-center justify-center">
              <i class="pi pi-compass text-indigo-300" style="font-size: 3rem"></i>
            </div>
            <p class="text-lg">Muestra el rostro del alumno para identificarlo</p>
            <p class="text-sm text-gray-300">La verificación es automática</p>
          </div>
        </transition>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import API from '../services/api';
import faceSocket from '../services/faceSocket.js';
import CameraView from '../components/CameraView.vue';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const cameraRef = ref(null);
const resultado = ref(null);
const cargando = ref(false);
const wsConectado = ref(false);
const aulasMap = ref({});

let intervaloVerificacion = null;
let timeoutLimpiar = null;

onMounted(async () => {
    try {
        const aulas = await API.obtenerAulas();
        aulasMap.value = Object.fromEntries(aulas.map((a) => [a.id, a.nombre]));
    } catch { }

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

    if (faceSocket.connected) {
        wsConectado.value = true;
    }

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
        await verificarPorWebSocket();
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
    }, 8000);
}

async function verificarPorWebSocket() {
    if (!cameraRef.value?.tieneStream()) return;
    const blob = await cameraRef.value.capturarFotoNativa();
    const enviado = faceSocket.sendFrame(blob);
    if (enviado) {
        cargando.value = true;
    }
}

function manejarResultadoWebSocket(res) {
    cargando.value = false;

    if (!res.rostros || res.rostros.length === 0) {
        resultado.value = { exitoso: false, timestamp: new Date().toLocaleString() };
        programarLimpieza();
        return;
    }

    const r = res.rostros[0];

    if (r.conocido) {
        resultado.value = {
            exitoso: true,
            nombre: r.nombre,
            carrera: r.carrera,
            aula: aulasMap.value[r.aula_id] || 'Sin aula asignada',
            dni: r.dni,
            foto_url: r.foto_path ? `/storage/fotos/${r.foto_path}` : null,
            distancia: r.distancia,
            timestamp: new Date().toLocaleString(),
        };
    } else {
        resultado.value = { exitoso: false, timestamp: new Date().toLocaleString() };
    }

    programarLimpieza();
}
</script>

<style scoped>
.slide-fade-enter-active {
  transition: all 0.3s ease-out;
}
.slide-fade-leave-active {
  transition: all 0.2s cubic-bezier(1, 0.5, 0.8, 1);
}
.slide-fade-enter-from {
  opacity: 0;
  transform: translateY(15px);
}
.slide-fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>