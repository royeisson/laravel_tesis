<template>
  <div
    v-if="drawerOpen"
    class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    @click="$emit('close')"
  ></div>

  <aside
    class="shrink-0 flex flex-col bg-gradient-to-b from-indigo-900 via-indigo-800 to-violet-900 text-white shadow-2xl fixed lg:relative inset-y-0 left-0 z-40 transition-transform duration-300"
    :class="drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    style="width: 260px"
  >
    <div class="px-5 py-5 border-b border-indigo-700/50 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="w-11 h-11 rounded-xl bg-indigo-400/20 flex items-center justify-center ring-2 ring-indigo-300/30">
          <i class="pi pi-compass text-indigo-300" style="font-size: 1.3rem"></i>
        </div>
        <div>
          <p class="text-base font-bold tracking-wide">Orientación</p>
          <p class="text-xs text-indigo-300/80">Punto de Información</p>
        </div>
      </div>
      <Button icon="pi pi-times" text class="!text-indigo-300 lg:!hidden" @click="$emit('close')" />
    </div>

    <div class="px-5 py-4 border-b border-indigo-700/30">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-indigo-400 flex items-center justify-center text-indigo-900 font-bold text-sm shrink-0">
          {{ iniciales }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate">{{ auth.usuario?.nombre }}</p>
          <div class="flex items-center gap-1 mt-0.5">
            <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-pulse"></span>
            <p class="text-xs text-indigo-300/80">Guía Activo</p>
          </div>
        </div>
      </div>
    </div>

    <nav class="flex-1 overflow-y-auto py-4 px-3" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.1) transparent">
      <div class="px-2 pb-2 text-[10px] font-bold text-indigo-400/60 uppercase tracking-widest">
        Opciones
      </div>
      <router-link
        v-for="item in menuFijo"
        :key="item.path"
        :to="item.path"
        custom
        v-slot="{ navigate, href, isActive }"
      >
        <a
          :href="href"
          @click="navigate && $emit('close')"
          :class="[
            'group flex items-center gap-3 px-3 py-3 my-1 rounded-xl text-sm font-medium transition-all duration-200 cursor-pointer',
            isActive
              ? 'bg-indigo-400 text-indigo-900 shadow-lg shadow-indigo-400/30'
              : 'text-indigo-100 hover:bg-indigo-700/50 hover:text-white'
          ]"
        >
          <i :class="item.icon" style="font-size: 1.1rem; width: 20px"></i>
          <span>{{ item.label }}</span>
          <i v-if="isActive" class="pi pi-angle-right ml-auto" style="font-size: 0.9rem"></i>
        </a>
      </router-link>
    </nav>

    <div class="px-5 py-3 border-t border-indigo-700/40">
      <div class="flex items-center justify-center gap-1.5 text-indigo-400/40 text-xs">
        <i class="pi pi-shield"></i>
        <span>Biometría Universitaria</span>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { auth } from '../stores/auth.js';

defineProps({ drawerOpen: Boolean });
defineEmits(['close']);

const menuFijo = [
    { path: '/guia/verificar', label: 'Identificar Alumno', icon: 'pi pi-compass' },
];

const iniciales = computed(() => {
    const nombre = auth.usuario?.nombre || '';
    const partes = nombre.trim().split(' ');
    return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase();
});
</script>