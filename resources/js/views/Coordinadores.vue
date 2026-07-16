<template>
  <div class="max-w-4xl mx-auto space-y-4">
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="pi pi-users text-indigo-500"></i>
            <span>Coordinadores</span>
          </div>
          <Button label="Nuevo" icon="pi pi-plus" @click="abrirCrear" />
        </div>
      </template>
      <template #content>
        <DataTable :value="coordinadores" stripedRows size="small">
          <Column field="id" header="ID" style="width: 60px" />
          <Column field="nombre" header="Nombre" />
          <Column field="usuario" header="Usuario" />
          <Column header="Aulas Asignadas">
            <template #body="{ data }">
              <div class="flex flex-wrap gap-1">
                <Tag v-for="aula in data.aulas" :key="aula.id" :value="aula.nombre" severity="info" class="text-xs" />
                <span v-if="data.aulas.length === 0" class="text-gray-400 text-sm">Sin aulas</span>
              </div>
            </template>
          </Column>
          <Column header="Acciones" style="width: 180px">
            <template #body="{ data }">
              <Button icon="pi pi-building" text rounded @click="abrirAsignar(data)" v-tooltip.top="'Asignar aulas'" />
              <Button icon="pi pi-pencil" text rounded @click="abrirEditar(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Dialog Crear/Editar -->
    <Dialog :key="dialogKey" v-model:visible="dialogoVisible" :header="esEditar ? 'Editar Coordinador' : 'Nuevo Coordinador'" :modal="true" :style="{ width: '90vw', maxWidth: '400px' }">
      <div class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <label>Nombre</label>
          <InputText v-model="form.nombre" placeholder="Juan Pérez" autocomplete="off" />
        </div>
        <div class="flex flex-col gap-1">
          <label>Usuario</label>
          <InputText v-model="form.usuario" placeholder="coordinador1" :disabled="esEditar" autocomplete="off" />
        </div>
        <div class="flex flex-col gap-1">
          <label>Contraseña {{ esEditar ? '(dejar en blanco para no cambiar)' : '' }}</label>
          <Password v-model="form.password" :feedback="false" toggleMask autocomplete="new-password" />
        </div>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="dialogoVisible = false" />
        <Button label="Guardar" @click="guardar" :loading="guardando" />
      </template>
    </Dialog>

    <!-- Dialog Asignar Aulas -->
    <Dialog v-model:visible="dialogoAulasVisible" header="Asignar Aulas" :modal="true" :style="{ width: '90vw', maxWidth: '420px' }">
      <div class="flex flex-col gap-3">
        <p class="text-sm text-gray-600">Selecciona las aulas para <strong>{{ coordinadorSeleccionado?.nombre }}</strong>:</p>
        <div class="flex flex-col gap-2 max-h-60 overflow-y-auto">
          <div v-for="aula in aulas" :key="aula.id" class="flex items-center gap-2 p-2 rounded hover:bg-gray-50">
            <Checkbox
              v-model="aulasSeleccionadas"
              :value="aula.id"
              :inputId="'aula_' + aula.id"
              :disabled="aulaOcupada(aula.id)"
            />
            <label :for="'aula_' + aula.id" class="cursor-pointer flex-1" :class="{ 'text-gray-400': aulaOcupada(aula.id) }">
              {{ aula.nombre }}
            </label>
            <Tag v-if="aulaOcupada(aula.id)" value="Ocupada" severity="warning" class="text-xs" />
          </div>
        </div>
        <p class="text-xs text-gray-500">Las aulas marcadas como <strong>Ocupada</strong> ya están asignadas a otro coordinador.</p>
      </div>
      <template #footer>
        <Button label="Cancelar" text @click="dialogoAulasVisible = false" />
        <Button label="Guardar" @click="guardarAulas" :loading="guardandoAulas" />
      </template>
    </Dialog>

    <!-- Dialog Eliminar -->
    <Dialog v-model:visible="dialogoEliminarVisible" header="Confirmar" :modal="true" :style="{ width: '90vw', maxWidth: '350px' }">
      <p>¿Eliminar a <strong>{{ coordinadorEliminar?.nombre }}</strong>?</p>
      <template #footer>
        <Button label="Cancelar" text @click="dialogoEliminarVisible = false" />
        <Button label="Eliminar" severity="danger" @click="eliminar" :loading="eliminando" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import API from '../services/api.js';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const coordinadores = ref([]);
const aulas = ref([]);
const cargando = ref(false);

const dialogoVisible = ref(false);
const dialogoAulasVisible = ref(false);
const dialogoEliminarVisible = ref(false);
const esEditar = ref(false);
const guardando = ref(false);
const guardandoAulas = ref(false);
const eliminando = ref(false);
const dialogKey = ref(0);

const form = ref({ nombre: '', usuario: '', password: '' });
const coordinadorEditarId = ref(null);
const coordinadorSeleccionado = ref(null);
const aulasSeleccionadas = ref([]);
const coordinadorEliminar = ref(null);

async function cargar() {
    try {
        const [coords, auls] = await Promise.all([
            API.obtenerCoordinadores(),
            API.obtenerAulas(),
        ]);
        coordinadores.value = coords;
        aulas.value = auls;
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo cargar', life: 3000 });
    }
}

async function abrirCrear() {
    dialogoVisible.value = false;
    await nextTick();
    esEditar.value = false;
    form.value = { nombre: '', usuario: '', password: '' };
    dialogKey.value++;
    await nextTick();
    dialogoVisible.value = true;
}

async function abrirEditar(data) {
    dialogoVisible.value = false;
    await nextTick();
    esEditar.value = true;
    form.value = { nombre: data.nombre, usuario: data.usuario, password: '' };
    coordinadorEditarId.value = data.id;
    dialogKey.value++;
    await nextTick();
    dialogoVisible.value = true;
}

async function guardar() {
    guardando.value = true;
    try {
        if (esEditar.value) {
            const data = { nombre: form.value.nombre };
            if (form.value.password) data.password = form.value.password;
            await API.editarCoordinador(coordinadorEditarId.value, data);
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Coordinador actualizado', life: 3000 });
        } else {
            await API.crearCoordinador(form.value);
            toast.add({ severity: 'success', summary: 'Creado', detail: 'Coordinador creado', life: 3000 });
        }
        dialogoVisible.value = false;
        await cargar();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo guardar', life: 3000 });
    } finally {
        guardando.value = false;
    }
}

function abrirAsignar(data) {
    coordinadorSeleccionado.value = data;
    aulasSeleccionadas.value = data.aulas.map((a) => a.id);
    dialogoAulasVisible.value = true;
}

function aulaOcupada(aulaId) {
    if (!coordinadorSeleccionado.value) return false;
    // Si el aula ya pertenece al coordinador actual, no está ocupada
    const esDelActual = coordinadorSeleccionado.value.aulas.some((a) => a.id === aulaId);
    if (esDelActual) return false;
    // Si está asignada a cualquier otro coordinador, está ocupada
    return coordinadores.value.some(
        (c) => c.id !== coordinadorSeleccionado.value.id && c.aulas.some((a) => a.id === aulaId)
    );
}

async function guardarAulas() {
    guardandoAulas.value = true;
    try {
        await API.asignarAulasCoordinador(coordinadorSeleccionado.value.id, {
            aula_ids: aulasSeleccionadas.value,
        });
        toast.add({ severity: 'success', summary: 'Asignado', detail: 'Aulas asignadas correctamente', life: 3000 });
        dialogoAulasVisible.value = false;
        await cargar();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo asignar', life: 3000 });
    } finally {
        guardandoAulas.value = false;
    }
}

function confirmarEliminar(data) {
    coordinadorEliminar.value = data;
    dialogoEliminarVisible.value = true;
}

async function eliminar() {
    eliminando.value = true;
    try {
        await API.eliminarCoordinador(coordinadorEliminar.value.id);
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Coordinador eliminado', life: 3000 });
        dialogoEliminarVisible.value = false;
        await cargar();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo eliminar', life: 3000 });
    } finally {
        eliminando.value = false;
    }
}

onMounted(cargar);
</script>
