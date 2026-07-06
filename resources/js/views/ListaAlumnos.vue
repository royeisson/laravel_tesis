<template>
  <Card>
    <template #title>Lista de Alumnos</template>
    <template #content>
      <DataTable
        :value="alumnos"
        :paginator="true"
        :rows="15"
        :rowsPerPageOptions="[10, 15, 25, 50]"
        :globalFilterFields="['nombre', 'dni', 'carrera', 'aula_nombre']"
        sortField="id"
        :sortOrder="-1"
        stripedRows
        size="small"
      >
        <template #header>
          <div class="flex justify-end">
            <InputText v-model="filtro" placeholder="Buscar..." class="w-80" />
          </div>
        </template>
        <Column field="id" header="ID" sortable style="width: 60px" />
        <Column field="dni" header="DNI" sortable style="width: 100px" />
        <Column field="nombre" header="Nombre" sortable />
        <Column field="carrera" header="Carrera" sortable />
        <Column field="aula_nombre" header="Aula" sortable />
        <Column header="Foto" style="width: 70px">
          <template #body="{ data }">
            <Avatar :image="data.foto_url" shape="circle" size="large" />
          </template>
        </Column>
        <Column header="Acciones" style="width: 120px">
          <template #body="{ data }">
            <Button icon="pi pi-pencil" text rounded @click="abrirEditar(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
          </template>
        </Column>
      </DataTable>

      <Dialog v-model:visible="dialogoEditarVisible" header="Editar Alumno" :modal="true" :style="{ width: '400px' }">
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-1">
            <label>Nombre</label>
            <InputText v-model="alumnoEdit.nombre" />
          </div>
          <div class="flex flex-col gap-1">
            <label>Carrera</label>
            <Select v-model="alumnoEdit.carrera" :options="carreras" placeholder="Seleccionar carrera" />
          </div>
          <div class="flex flex-col gap-1">
            <label>Aula</label>
            <Select v-model="alumnoEdit.aula_id" :options="aulas" optionLabel="nombre" optionValue="id" placeholder="Seleccionar aula" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoEditarVisible = false" />
          <Button label="Guardar" @click="guardarEdicion" :loading="guardando" />
        </template>
      </Dialog>

      <Dialog v-model:visible="dialogoEliminarVisible" header="Confirmar Eliminación" :modal="true" :style="{ width: '350px' }">
        <p>¿Estás seguro de eliminar a <strong>{{ alumnoEliminar?.nombre }}</strong>?</p>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoEliminarVisible = false" />
          <Button label="Eliminar" severity="danger" @click="eliminarAlumno" :loading="eliminando" />
        </template>
      </Dialog>
    </template>
  </Card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import API from '../services/api';
import { useToast } from 'primevue/usetoast';

const toast = useToast();
const alumnos = ref([]);
const aulas = ref([]);
const filtro = ref('');
const dialogoEditarVisible = ref(false);
const dialogoEliminarVisible = ref(false);
const guardando = ref(false);
const eliminando = ref(false);
const alumnoEdit = ref({});
const alumnoEliminar = ref(null);

const carreras = [
    'Agronomía', 'Biología', 'Contabilidad', 'Economía', 'Administración',
    'Comercio y Negocios Internacionales', 'Física', 'Matemáticas', 'Estadística',
    'Ingeniería de Computación e Informática', 'Ingeniería Electrónica', 'Educación',
    'Sociología', 'Ciencias de la Comunicación', 'Psicología', 'Arte', 'Arqueología',
    'Derecho', 'Ciencia Política', 'Enfermería', 'Ingeniería Agrícola', 'Ingeniería Civil',
    'Arquitectura', 'Ingeniería de Sistemas', 'Ingeniería Mecánica y Eléctrica',
    'Medicina Humana', 'Medicina Veterinaria', 'Ingeniería Química',
    'Ingeniería en Industrias Alimentarias', 'Zootecnia',
];

async function cargarAlumnos() {
    try {
        const data = await API.obtenerAlumnos({ sin_paginacion: 'true' });
        alumnos.value = data.map((a) => ({ ...a, aula_nombre: a.aula_nombre || '—' }));
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar la lista', life: 3000 });
    }
}

function abrirEditar(data) {
    alumnoEdit.value = { id: data.id, nombre: data.nombre, carrera: data.carrera, aula_id: data.aula_id };
    dialogoEditarVisible.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        await API.editarAlumno(alumnoEdit.value.id, {
            nombre: alumnoEdit.value.nombre,
            carrera: alumnoEdit.value.carrera,
            aula_id: alumnoEdit.value.aula_id,
        });
        toast.add({ severity: 'success', summary: 'Actualizado', detail: 'Alumno actualizado correctamente', life: 3000 });
        dialogoEditarVisible.value = false;
        await cargarAlumnos();
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo actualizar', life: 3000 });
    } finally {
        guardando.value = false;
    }
}

function confirmarEliminar(data) {
    alumnoEliminar.value = data;
    dialogoEliminarVisible.value = true;
}

async function eliminarAlumno() {
    eliminando.value = true;
    try {
        await API.eliminarAlumno(alumnoEliminar.value.id);
        toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Alumno eliminado correctamente', life: 3000 });
        dialogoEliminarVisible.value = false;
        await cargarAlumnos();
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar', life: 3000 });
    } finally {
        eliminando.value = false;
    }
}

onMounted(async () => {
    await cargarAlumnos();
    try {
        aulas.value = await API.obtenerAulas();
    } catch { }
});
</script>
