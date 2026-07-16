<template>
  <div class="max-w-4xl mx-auto space-y-4">
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="pi pi-building text-indigo-500"></i>
            <span>Aulas</span>
          </div>
          <Button label="Nueva Aula" icon="pi pi-plus" @click="abrirCrear" />
        </div>
      </template>
      <template #content>
        <DataTable :value="aulas" stripedRows size="small">
          <Column field="id" header="ID" style="width: 60px" />
          <Column field="nombre" header="Nombre" />
          <Column header="Coordinador">
            <template #body="{ data }">
              <div v-if="data.coordinadores && data.coordinadores.length" class="flex flex-wrap gap-1">
                <Tag v-for="c in data.coordinadores" :key="c.id" :value="c.nombre" severity="success" class="text-xs" />
              </div>
              <span v-else class="text-gray-400 text-sm">Sin coordinador</span>
            </template>
          </Column>
          <Column header="Postulantes">
            <template #body="{ data }">
              <span>{{ data.total_alumnos ?? 0 }}</span>
            </template>
          </Column>
          <Column header="Acciones" style="width: 150px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded @click="abrirEditar(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Dialog Crear/Editar -->
    <Dialog v-model:visible="dialogoVisible" :header="esEditar ? 'Editar Aula' : 'Nueva Aula'" :modal="true" :style="{ width: '90vw', maxWidth: '350px' }">
      <div class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <label>Nombre del Aula</label>
          <InputText v-model="form.nombre" placeholder="Aula 1" @keyup.enter="guardar" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="dialogoVisible = false" />
        <Button label="Guardar" @click="guardar" :loading="guardando" />
      </template>
    </Dialog>

    <!-- Dialog Eliminar -->
    <Dialog v-model:visible="dialogoEliminarVisible" header="Confirmar" :modal="true" :style="{ width: '90vw', maxWidth: '400px' }">
      <div class="flex flex-col gap-2">
        <p>¿De verdad quieres eliminar el aula <strong>{{ aulaEliminar?.nombre }}</strong>?</p>
        <p v-if="aulaEliminar?.total_alumnos > 0" class="text-sm text-amber-600">
          Esta aula tiene <strong>{{ aulaEliminar.total_alumnos }}</strong> postulante(s). Los alumnos no se eliminarán, quedarán sin aula asignada.
        </p>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="dialogoEliminarVisible = false" />
        <Button label="Eliminar" severity="danger" @click="eliminar" :loading="eliminando" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import API from '../services/api.js';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const aulas = ref([]);

const dialogoVisible = ref(false);
const dialogoEliminarVisible = ref(false);
const esEditar = ref(false);
const guardando = ref(false);
const eliminando = ref(false);

const form = ref({ nombre: '' });
const aulaEditarId = ref(null);
const aulaEliminar = ref(null);

async function cargar() {
    try {
        aulas.value = await API.obtenerAulas();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo cargar', life: 3000 });
    }
}

function abrirCrear() {
    esEditar.value = false;
    form.value = { nombre: '' };
    dialogoVisible.value = true;
}

function abrirEditar(data) {
    esEditar.value = true;
    form.value = { nombre: data.nombre };
    aulaEditarId.value = data.id;
    dialogoVisible.value = true;
}

async function guardar() {
    if (!form.value.nombre.trim()) {
        toast.add({ severity: 'warn', summary: 'Atención', detail: 'Ingresa un nombre', life: 3000 });
        return;
    }
    guardando.value = true;
    try {
        if (esEditar.value) {
            await API.editarAula(aulaEditarId.value, form.value);
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Aula actualizada', life: 3000 });
        } else {
            await API.crearAula(form.value);
            toast.add({ severity: 'success', summary: 'Creado', detail: 'Aula creada', life: 3000 });
        }
        dialogoVisible.value = false;
        window.location.reload();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo guardar', life: 3000 });
    } finally {
        guardando.value = false;
    }
}

function confirmarEliminar(data) {
    aulaEliminar.value = data;
    dialogoEliminarVisible.value = true;
}

async function eliminar() {
    eliminando.value = true;
    try {
        await API.eliminarAula(aulaEliminar.value.id);
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Aula eliminada', life: 3000 });
        dialogoEliminarVisible.value = false;
        window.location.reload();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo eliminar', life: 3000 });
    } finally {
        eliminando.value = false;
    }
}

onMounted(cargar);
</script>
