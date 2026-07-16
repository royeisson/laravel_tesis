<template>
  <div
    v-if="drawerOpen"
    class="fixed inset-0 bg-black/50 z-30 lg:hidden"
    @click="$emit('close')"
  ></div>

  <aside
    class="bg-gray-900 text-white shrink-0 flex flex-col fixed lg:relative inset-y-0 left-0 z-40 transition-transform duration-300"
    :class="drawerOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    style="width: 240px"
  >
    <div class="px-4 py-3 border-b border-gray-700 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <i class="pi pi-user text-indigo-400" style="font-size: 1.2rem"></i>
        <div>
          <p class="text-sm font-semibold truncate max-w-[140px]">{{ auth.usuario?.nombre }}</p>
          <p class="text-xs text-gray-400">Coordinador</p>
        </div>
      </div>
      <Button icon="pi pi-times" text class="!text-gray-400 lg:!hidden" @click="$emit('close')" />
    </div>
    <nav class="flex-1 overflow-y-auto py-2" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent">
      <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
        Menú
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
            'flex items-center gap-2 px-3 py-2 mx-2 rounded-md text-sm transition-colors cursor-pointer',
            isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover-bg-gray-700 hover-text-white'
          ]"
        >
          <i :class="item.icon" style="width: 18px"></i>
          <span>{{ item.label }}</span>
        </a>
      </router-link>

      <div class="px-3 py-2 mt-3 text-xs font-semibold text-gray-400 uppercase tracking-wide">
        Aulas
      </div>
      <router-link
        v-for="aula in aulas"
        :key="aula.id"
        :to="`/coordinador/aula/${aula.id}`"
        custom
        v-slot="{ navigate, href, isActive }"
      >
        <a
          :href="href"
          @click="navigate && $emit('close')"
          :class="[
            'flex items-center gap-2 px-3 py-2 mx-2 rounded-md text-sm transition-colors cursor-pointer',
            isActive ? 'bg-indigo-600 text-white' : 'text-gray-300 hover-bg-gray-700 hover-text-white'
          ]"
        >
          <i class="pi pi-building" style="width: 18px"></i>
          <span>{{ aula.nombre }}</span>
        </a>
      </router-link>
    </nav>
  </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { auth } from '../stores/auth.js';
import API from '../services/api.js';

defineProps({ drawerOpen: Boolean });
defineEmits(['close']);

const menuFijo = [
    { path: '/coordinador/verificar', label: 'Verificar', icon: 'pi pi-check-circle' },
];

const aulas = ref([]);

async function cargarAulas() {
    const cache = localStorage.getItem('coordinador_sidebar_aulas');
    if (cache) aulas.value = JSON.parse(cache);
    try {
        const data = await API.obtenerMisAulas(auth.usuario?.usuario);
        aulas.value = data;
        localStorage.setItem('coordinador_sidebar_aulas', JSON.stringify(data));
    } catch {
    }
}

onMounted(cargarAulas);
</script>

<style scoped>
.hover-bg-gray-700:hover { background-color: #374151; }
.hover-text-white:hover { color: white; }
</style>