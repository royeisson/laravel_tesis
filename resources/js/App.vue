<template>
  <!-- LOGIN (sin layout) -->
  <div v-if="!auth.isLoggedIn" class="h-screen">
    <router-view />
  </div>

  <!-- ADMIN -->
  <div v-else-if="auth.isAdmin" class="flex h-screen overflow-hidden">
    <!-- Sidebar fijo en desktop, drawer en movil -->
    <SidebarComponent :drawerOpen="drawerOpen" @close="drawerOpen = false" />
    <div class="flex flex-col flex-1 min-w-0">
      <header class="bg-indigo-700 text-white px-4 py-2 flex items-center justify-between gap-2 shrink-0" style="height: 48px">
        <div class="flex items-center gap-2">
          <Button icon="pi pi-bars" text class="!text-white lg:!hidden" @click="drawerOpen = true" />
          <i class="pi pi-shield" style="font-size: 1.2rem"></i>
          <span class="font-semibold hidden sm:inline">Control Biométrico</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm opacity-90 hidden sm:inline">{{ auth.usuario?.nombre }}</span>
          <Button icon="pi pi-sign-out" text class="text-white" @click="cerrarSesion" />
        </div>
      </header>
      <main class="flex-1 overflow-auto p-3 sm:p-4">
        <router-view :key="auth.usuario?.rol || 'guest'" />
      </main>
    </div>
    <Toast position="bottom-right" />
  </div>

  <!-- GUIA -->
  <div v-else-if="auth.isGuia" class="flex h-screen overflow-hidden">
    <GuiaSidebar :drawerOpen="drawerOpen" @close="drawerOpen = false" />
    <div class="flex flex-col flex-1 min-w-0">
      <header class="bg-indigo-700 text-white px-4 py-2 flex items-center justify-between gap-2 shrink-0" style="height: 48px">
        <div class="flex items-center gap-2">
          <Button icon="pi pi-bars" text class="!text-white lg:!hidden" @click="drawerOpen = true" />
          <i class="pi pi-compass" style="font-size: 1.2rem"></i>
          <span class="font-semibold hidden sm:inline">Panel Guía</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm opacity-90 hidden sm:inline">{{ auth.usuario?.nombre }}</span>
          <Button icon="pi pi-sign-out" text class="text-white" @click="cerrarSesion" />
        </div>
      </header>
      <main class="flex-1 overflow-auto p-3 sm:p-4 bg-gray-50">
        <router-view :key="auth.usuario?.rol || 'guest'" />
      </main>
    </div>
    <Toast position="bottom-right" />
  </div>

  <!-- COORDINADOR -->
  <div v-else class="flex h-screen overflow-hidden">
    <CoordinadorSidebar :drawerOpen="drawerOpen" @close="drawerOpen = false" />
    <div class="flex flex-col flex-1 min-w-0">
      <header class="bg-gray-800 text-white px-4 py-2 flex items-center justify-between gap-2 shrink-0" style="height: 48px">
        <div class="flex items-center gap-2">
          <Button icon="pi pi-bars" text class="!text-white lg:!hidden" @click="drawerOpen = true" />
          <i class="pi pi-user" style="font-size: 1.2rem"></i>
          <span class="font-semibold hidden sm:inline">Panel Coordinador</span>
        </div>
        <div class="flex items-center gap-3">
          <span class="text-sm opacity-90 hidden sm:inline">{{ auth.usuario?.nombre }}</span>
          <Button icon="pi pi-sign-out" text class="text-white" @click="cerrarSesion" />
        </div>
      </header>
      <main class="flex-1 overflow-auto p-3 sm:p-4 bg-gray-50">
        <router-view :key="auth.usuario?.rol || 'guest'" />
      </main>
    </div>
    <Toast position="bottom-right" />
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import SidebarComponent from './components/Sidebar.vue';
import CoordinadorSidebar from './components/CoordinadorSidebar.vue';
import GuiaSidebar from './components/GuiaSidebar.vue';
import { auth, logout } from './stores/auth.js';

const router = useRouter();
const drawerOpen = ref(false);

function cerrarSesion() {
    logout();
    window.location.href = '/login';
}
</script>