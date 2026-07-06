<template>
  <aside
    class="bg-gray-900 text-white shrink-0 flex flex-col"
    style="width: 240px"
  >
    <div class="px-4 py-3 border-b border-gray-700">
      <div class="flex items-center gap-2">
        <i class="pi pi-shield text-indigo-400" style="font-size: 1.2rem"></i>
        <div>
          <p class="text-sm font-semibold">{{ auth.usuario?.nombre }}</p>
          <p class="text-xs text-gray-400">Administrador</p>
        </div>
      </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-2" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent">
      <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wide">
        Administración
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

      <div class="px-3 pt-3 pb-1 text-xs font-semibold text-gray-400 uppercase tracking-wide">
        Aulas
      </div>
      <div v-for="aula in aulas" :key="aula.id">
        <router-link
          :to="`/aula/${aula.id}`"
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
            <i class="pi pi-building" style="width: 18px"></i>
            <span>{{ aula.nombre }}</span>
          </a>
        </router-link>
      </div>
    </nav>
  </aside>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import API from '../services/api';
import { auth } from '../stores/auth.js';

const aulas = ref([]);

onMounted(async () => {
    try {
        aulas.value = await API.obtenerAulas();
    } catch { }
});

const menuItems = [
    { path: '/registrar', label: 'Registrar', icon: 'pi pi-user-plus' },
    { path: '/verificar', label: 'Verificar', icon: 'pi pi-check-circle' },
    { path: '/lista', label: 'Lista de Alumnos', icon: 'pi pi-list' },
    { path: '/reportes', label: 'Reportes', icon: 'pi pi-chart-bar' },
    { path: '/coordinadores', label: 'Coordinadores', icon: 'pi pi-users' },
    { path: '/aulas', label: 'Aulas', icon: 'pi pi-building' },
];
</script>

<style scoped>
.hover-bg-gray-700:hover { background-color: #374151; }
.hover-text-white:hover { color: white; }
</style>
