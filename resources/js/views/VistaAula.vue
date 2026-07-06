<template>
  <Card>
    <template #title>Aula: {{ aulaNombre }}</template>
    <template #content>
      <DataTable
        :value="alumnos"
        :paginator="true"
        :rows="15"
        stripedRows
        size="small"
      >
        <Column field="id" header="ID" style="width: 60px" />
        <Column field="dni" header="DNI" style="width: 100px" />
        <Column field="nombre" header="Nombre" />
        <Column field="carrera" header="Carrera" />
        <Column header="Foto" style="width: 70px">
          <template #body="{ data }">
            <Avatar :image="data.foto_url" shape="circle" size="large" />
          </template>
        </Column>
        <Column header="Acciones" style="width: 100px">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" severity="secondary" rounded size="small" @click="abrirEditar(data)" />
          </template>
        </Column>
      </DataTable>
    </template>
  </Card>

  <Dialog v-model:visible="dialogVisible" header="Editar Alumno" modal style="width: 400px">
    <div class="flex flex-col gap-3">
      <div class="flex flex-col gap-1">
        <label>Nombre</label>
        <InputText v-model="editForm.nombre" />
      </div>
      <div class="flex flex-col gap-1">
        <label>Carrera</label>
        <InputText v-model="editForm.carrera" />
      </div>
      <div class="flex flex-col gap-1">
        <label>Nuevo Aula</label>
        <Select v-model="editForm.aula_id" :options="aulas" optionLabel="nombre" optionValue="id" placeholder="Seleccionar aula" />
      </div>
      <div class="flex gap-2 justify-end">
        <Button label="Cancelar" severity="secondary" @click="dialogVisible = false" />
        <Button label="Guardar" @click="guardar" :disabled="cargando" />
      </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import API from '../services/api';
import { useToast } from 'primevue/usetoast';

const props = defineProps({ id: String });
const route = useRoute();
const toast = useToast();

const aulaId = ref(props.id || route.params.id);
const aulaNombre = ref('');
const alumnos = ref([]);
const aulas = ref([]);
const dialogVisible = ref(false);
const editForm = ref({ id: null, nombre: '', carrera: '', aula_id: null });
const cargando = ref(false);

async function cargarDatos() {
    if (!aulaId.value) return;
    try {
        const todo = await API.obtenerAlumnos({ aula_id: aulaId.value, sin_paginacion: 'true' });
        alumnos.value = todo;
        const listaAulas = await API.obtenerAulas();
        aulas.value = listaAulas;
        const aula = listaAulas.find((a) => a.id == aulaId.value);
        aulaNombre.value = aula ? aula.nombre : '—';
    } catch { }
}

watch(() => props.id, (val) => {
    aulaId.value = val || route.params.id;
    cargarDatos();
});

function abrirEditar(alumno) {
    editForm.value = { id: alumno.id, nombre: alumno.nombre, carrera: alumno.carrera, aula_id: alumno.aula_id };
    dialogVisible.value = true;
}

async function guardar() {
    cargando.value = true;
    try {
        await API.editarAlumno(editForm.value.id, {
            nombre: editForm.value.nombre,
            carrera: editForm.value.carrera,
            aula_id: editForm.value.aula_id,
        });
        toast.add({ severity: 'success', summary: 'Guardado', detail: 'Alumno actualizado', life: 3000 });
        dialogVisible.value = false;
        cargarDatos();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message, life: 4000 });
    } finally {
        cargando.value = false;
    }
}

onMounted(() => cargarDatos());
</script>
