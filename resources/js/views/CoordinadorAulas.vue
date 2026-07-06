<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <Card class="w-full max-w-lg mx-4">
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
        <div v-else class="grid grid-cols-1 gap-3">
          <Card v-for="aula in aulas" :key="aula.id" class="border border-gray-200">
            <template #content>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                    <i class="pi pi-building text-indigo-600"></i>
                  </div>
                  <div>
                    <p class="font-semibold text-gray-800">{{ aula.nombre }}</p>
                    <p class="text-xs text-gray-500">{{ aula.total_alumnos || 0 }} postulantes</p>
                  </div>
                </div>
                <Button icon="pi pi-eye" text severity="secondary" @click="verAula(aula.id)" />
              </div>
            </template>
          </Card>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import API from '../services/api.js';
import { auth } from '../stores/auth.js';

const router = useRouter();
const aulas = ref([]);
const cargando = ref(true);

onMounted(async () => {
    try {
        aulas.value = await API.obtenerMisAulas(auth.usuario?.usuario);
    } catch {
        // Silencioso
    } finally {
        cargando.value = false;
    }
});

function verAula(id) {
    router.push(`/coordinador/aula/${id}`);
}
</script>
