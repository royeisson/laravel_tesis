<template>
  <div class="max-w-4xl mx-auto space-y-4">
    <Card>
      <template #title>
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <i class="pi pi-directions text-indigo-500"></i>
            <span>Guías</span>
          </div>
          <Button label="Nuevo" icon="pi pi-plus" @click="abrirCrear" />
        </div>
      </template>
      <template #content>
        <DataTable :value="guias" stripedRows size="small">
          <Column field="id" header="ID" style="width: 60px" />
          <Column field="nombre" header="Nombre" />
          <Column field="usuario" header="Usuario" />
          <Column header="Acciones" style="width: 120px">
            <template #body="{ data }">
              <Button icon="pi pi-pencil" text rounded @click="abrirEditar(data)" />
              <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog :key="dialogKey" v-model:visible="dialogoVisible" :header="esEditar ? 'Editar Guía' : 'Nuevo Guía'" :modal="true" :style="{ width: '90vw', maxWidth: '400px' }">
      <div class="flex flex-col gap-3">
        <div class="flex flex-col gap-1">
          <label>Nombre</label>
          <InputText v-model="form.nombre" placeholder="Juan Pérez" autocomplete="off" />
        </div>
        <div class="flex flex-col gap-1">
          <label>Usuario</label>
          <InputText v-model="form.usuario" placeholder="guia1" :disabled="esEditar" autocomplete="off" />
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

    <Dialog v-model:visible="dialogoEliminarVisible" header="Confirmar" :modal="true" :style="{ width: '90vw', maxWidth: '350px' }">
      <p>¿Eliminar a <strong>{{ guiaEliminar?.nombre }}</strong>?</p>
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
const guias = ref([]);
const dialogoVisible = ref(false);
const dialogoEliminarVisible = ref(false);
const esEditar = ref(false);
const guardando = ref(false);
const eliminando = ref(false);
const dialogKey = ref(0);

const form = ref({ nombre: '', usuario: '', password: '' });
const guiaEditarId = ref(null);
const guiaEliminar = ref(null);

async function cargar() {
    try {
        guias.value = await API.obtenerGuias();
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
    guiaEditarId.value = data.id;
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
            await API.editarGuia(guiaEditarId.value, data);
            toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Guía actualizado', life: 3000 });
        } else {
            await API.crearGuia(form.value);
            toast.add({ severity: 'success', summary: 'Creado', detail: 'Guía creado', life: 3000 });
        }
        dialogoVisible.value = false;
        await cargar();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo guardar', life: 3000 });
    } finally {
        guardando.value = false;
    }
}

function confirmarEliminar(data) {
    guiaEliminar.value = data;
    dialogoEliminarVisible.value = true;
}

async function eliminar() {
    eliminando.value = true;
    try {
        await API.eliminarGuia(guiaEliminar.value.id);
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Guía eliminado', life: 3000 });
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