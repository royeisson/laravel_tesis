<template>
  <aside
    class="bg-gray-900 text-white shrink-0 flex flex-col"
    style="width: 240px"
  >
    <div class="px-4 py-3 border-b border-gray-700">
      <div class="flex items-center gap-2">
        <i class="pi pi-user text-indigo-400" style="font-size: 1.2rem"></i>
        <div>
          <p class="text-sm font-semibold">{{ auth.usuario?.nombre }}</p>
          <p class="text-xs text-gray-400">Coordinador</p>
        </div>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-2" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent">
      <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
        Menú
      </div>
      <router-link
        v-for="item in menuItems"
        :key="item.path"
        :to="item.path"
        custom
        v-slot="{ navigate, href, isActive }"
      >
        <a
          :href="href"
          @click="navigate"
          :class="[
            'flex items-center gap-2 px-3 py-2 mx-2 rounded-md text-sm transition-colors cursor-pointer',
            isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover-bg-gray-700 hover-text-white'
          ]"
        >
          <i :class="item.icon" style="width: 18px"></i>
          <span>{{ item.label }}</span>
        </a>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { auth } from '../stores/auth.js';

const menuItems = [
    { path: '/coordinador/verificar', label: 'Verificar', icon: 'pi pi-check-circle' },
    { path: '/coordinador/aulas', label: 'Mis Aulas', icon: 'pi pi-building' },
];
</script>

<style scoped>
.hover-bg-gray-700:hover { background-color: #374151; }
.hover-text-white:hover { color: white; }
</style>
