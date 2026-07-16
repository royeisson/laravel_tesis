<template>
  <div class="camera-wrapper" :style="{ maxWidth: width + 'px', height: height + 'px' }">
    <video
      ref="videoEl"
      autoplay
      playsinline
      muted
      class="camera-video"
      :class="{ hidden: !streamActive }"
    ></video>
    <canvas
      ref="canvasEl"
      :width="width"
      :height="height"
      class="camera-canvas"
    ></canvas>
    <div v-if="!streamActive" class="camera-placeholder">
      <i class="pi pi-camera" style="font-size: 3rem; opacity: 0.4"></i>
      <p class="text-sm text-gray-400 mt-2">Cámara apagada</p>
    </div>
    <svg
      class="camera-oval"
      :width="width"
      :height="height"
      viewBox="0 0 320 240"
    >
      <ellipse cx="160" cy="120" rx="90" ry="100" fill="none" stroke="white" stroke-width="2" opacity="0.5" />
    </svg>
  </div>
</template>

<script setup>
import { ref, onBeforeUnmount } from 'vue';

const props = defineProps({
    width: { type: Number, default: 320 },
    height: { type: Number, default: 240 },
});
const emit = defineEmits(['frame', 'stream-ready', 'stream-ended']);

const videoEl = ref(null);
const canvasEl = ref(null);
const streamActive = ref(false);

let stream = null;
let interval = null;

function toggleCam() {
    if (streamActive.value) {
        stopCam();
    } else {
        startCam();
    }
}

async function startCam() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
        });
        if (!videoEl.value) {
            stream.getTracks().forEach((t) => t.stop());
            stream = null;
            return;
        }
        videoEl.value.srcObject = stream;

        await new Promise((resolve) => {
            const onMeta = () => {
                if (videoEl.value) videoEl.value.onloadedmetadata = null;
                resolve();
            };
            if (videoEl.value) videoEl.value.onloadedmetadata = onMeta;
            if (videoEl.value && videoEl.value.readyState >= 1) {
                if (videoEl.value) videoEl.value.onloadedmetadata = null;
                resolve();
            }
        });

        if (!videoEl.value) return;
        await videoEl.value.play();
        streamActive.value = true;
        emit('stream-ready', stream);
        interval = setInterval(capture, 500);
    } catch (e) {
        console.error('Error al iniciar cámara:', e);
        streamActive.value = false;
    }
}

function stopCam() {
    clearInterval(interval);
    interval = null;
    if (stream) {
        stream.getTracks().forEach((t) => t.stop());
        stream = null;
    }
    streamActive.value = false;
    emit('stream-ended');
}

function capture() {
    const canvas = canvasEl.value;
    const video = videoEl.value;
    if (!canvas || !video || video.readyState < 2) return;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, props.width, props.height);
    canvas.toBlob((blob) => {
        if (blob) emit('frame', blob);
    }, 'image/jpeg', 0.85);
}

function capturarFoto() {
    return new Promise((resolve) => {
        canvasEl.value.toBlob((blob) => resolve(blob), 'image/jpeg', 0.9);
    });
}

function capturarFotoNativa() {
    return new Promise((resolve) => {
        const video = videoEl.value;
        if (!video) {
            resolve(capturarFoto());
            return;
        }
        const canvas = document.createElement('canvas');
        // Usar la resolución real del stream
        canvas.width = video.videoWidth || 640;
        canvas.height = video.videoHeight || 480;
        const ctx = canvas.getContext('2d');
        // NO reflejamos — la imagen original del stream es la correcta para reconocimiento.
        // El reflejo CSS scaleX(-1) es solo para que el usuario se vea como en espejo.
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', 0.92);
    });
}

function tieneStream() {
    return streamActive.value;
}

function getCanvasBlob() {
    return new Promise((resolve) => {
        canvasEl.value.toBlob((blob) => resolve(blob), 'image/jpeg', 0.85);
    });
}

onBeforeUnmount(() => stopCam());

defineExpose({ toggleCam, tieneStream, getCanvasBlob, capturarFoto, capturarFotoNativa, startCam, stopCam, videoEl });
</script>

<style scoped>
.camera-wrapper {
    position: relative;
    border-radius: 12px;
    overflow: hidden;
    background: #111;
    display: flex;
    align-items: center;
    justify-content: center;
}
.camera-video, .camera-canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.camera-video {
    transform: scaleX(-1);
}
.camera-video.hidden { display: none; }
.camera-canvas { display: none; }
.camera-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: absolute;
    inset: 0;
}
.camera-oval {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
}
</style>
