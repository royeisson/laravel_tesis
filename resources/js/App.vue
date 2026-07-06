<template>
  <div v-if="mostrarLogin" class="h-screen">
    <router-view />
  </div>
  <div v-else-if="mostrarAdmin" class="flex h-screen overflow-hidden">
    <SidebarComponent />
    <div class="flex flex-col flex-1 min-w-0">
      <header class="bg-indigo-700 text-white px-4 py-2 flex items-center justify-between gap-2 shrink-0" style="height: 48px">
        <div class="flex items-center gap-2">
          <i class="pi pi-shield" style="font-size: 1.2rem"></i>
          <span class="font-semibold">Control Biométrico</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm opacity-90">{{ nombreUsuario }}</span>
          <Button icon="pi pi-sign-out" text class="text-white" @click="cerrarSesion" />
        </div>
      </header>
      <main class="flex-1 overflow-auto p-4">
        <router-view />
      </main>
    </div>
    <Toast position="bottom-right" />
  </div>
  <div v-else class="h-screen">
    <router-view />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import SidebarComponent from './components/Sidebar.vue';
import { auth, logout } from './stores/auth.js';
import { useRouter } from 'vue-router';

const router = useRouter();

const mostrarLogin = computed(() => !auth.isLoggedIn);
const mostrarAdmin = computed(() => auth.isLoggedIn && auth.isAdmin);
const nombreUsuario = computed(() => auth.usuario?.nombre || '');

function cerrarSesion() {
    logout();
    router.push('/login');
}
</script>
