<template>
  <div class="max-w-3xl mx-auto space-y-4">
    <Card>
      <template #title>
        <div class="text-center">
          <h2 class="text-xl font-bold text-gray-800">Mis Aulas Asignadas</h2>
          <p class="text-sm text-gray-500 mt-1">Aulas bajo tu coordinación</p>
        </div>
      </template>
      <template #content>
        <div v-if="cargando" class="flex justify-center py-8">
          <ProgressSpinner />
        </div>
        <div v-else-if="aulas.length === 0" class="text-center py-8 text-gray-500">
          <i class="pi pi-building text-4xl text-gray-300 mb-3"></i>
          <p>No tienes aulas asignadas</p>
        </div>
        <div v-else class="flex flex-col gap-6">
          <div v-for="aula in aulasConAlumnos" :key="aula.id">
            <!-- Cabecera del aula -->
            <div class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-200">
              <i class="pi pi-building text-indigo-500"></i>
              <span class="font-bold text-gray-800">{{ aula.nombre }}</span>
              <Tag :value="`${aula.alumnos?.length || 0} postulantes`" severity="secondary" class="text-xs" />
            </div>

            <!-- Lista de alumnos -->
            <div v-if="!aula.alumnos || aula.alumnos.length === 0" class="text-center text-gray-400 text-sm py-2">
              No hay alumnos registrados en esta aula
            </div>
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div
                v-for="alumno in aula.alumnos"
                :key="alumno.dni"
                class="flex items-center gap-2 p-2 rounded border"
                :class="alumno.estado === 'Asistió' ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'"
              >
                <Avatar v-if="alumno.foto_path" :image="`/storage/fotos/${alumno.foto_path}`" shape="circle" size="normal" />
                <Avatar v-else icon="pi pi-user" shape="circle" size="normal" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold truncate">{{ alumno.nombre }}</p>
                  <p class="text-xs text-gray-500">{{ alumno.dni }}</p>
                </div>
                <Tag :value="alumno.estado || 'Faltó'" :severity="(alumno.estado || 'Faltó') === 'Asistió' ? 'success' : 'danger'" class="text-xs" />
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import API from '../services/api.js';
import { auth } from '../stores/auth.js';

const aulasConAlumnos = ref([]);
const cargando = ref(true);

async function cargar() {
    // 1. Cache inmediato
    const cacheKey = `coordinador_aulas_${auth.usuario?.usuario}`;
    const cache = localStorage.getItem(cacheKey);
    if (cache) {
        aulasConAlumnos.value = JSON.parse(cache);
        cargando.value = false;
    }

    try {
        const aulas = await API.obtenerMisAulas(auth.usuario?.usuario);
        // Cargar alumnos de todas las aulas en paralelo
        const alumnosPorAula = await Promise.all(
            aulas.map((a) => API.listarAsistenciaPorAula(a.id).catch(() => []))
        );
        const resultado = aulas.map((aula, idx) => ({
            ...aula,
            alumnos: alumnosPorAula[idx],
        }));
        aulasConAlumnos.value = resultado;
        localStorage.setItem(cacheKey, JSON.stringify(resultado));
    } catch {
        // Silencioso: se mantiene cache si existe
    } finally {
        cargando.value = false;
    }
}

onMounted(cargar);
</script>
