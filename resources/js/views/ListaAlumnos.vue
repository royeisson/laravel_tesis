<template>
  <Card>
    <template #title>Lista de Alumnos</template>
    <template #content>
      <DataTable
        :value="alumnos"
        :paginator="true"
        :rows="15"
        :rowsPerPageOptions="[10, 15, 25, 50]"
        v-model:filters="filters"
        :globalFilterFields="['nombre', 'dni', 'carrera', 'aula_nombre']"
        sortField="id"
        :sortOrder="-1"
        stripedRows
        size="small"
      >
        <template #header>
          <div class="flex flex-col sm:flex-row justify-between gap-2">
            <InputText v-model="filters.global.value" placeholder="Buscar..." class="w-full sm:w-80" />
            <div class="flex gap-2">
              <Button label="Exportar Excel" icon="pi pi-file-excel" severity="success" @click="exportarExcel" :loading="exportando" />
              <Button label="Importar Excel" icon="pi pi-file-import" severity="info" @click="dialogoImportarVisible = true" />
            </div>
          </div>
        </template>
        <Column field="id" header="ID" sortable style="width: 60px" />
        <Column field="dni" header="DNI" sortable style="width: 100px" />
        <Column field="nombre" header="Nombre" sortable />
        <Column field="carrera" header="Carrera" sortable />
        <Column field="aula_nombre" header="Aula" sortable />
        <Column header="Foto" style="width: 70px">
          <template #body="{ data }">
            <Avatar v-if="data.foto_url" :image="data.foto_url" shape="circle" size="large" />
            <Avatar v-else icon="pi pi-user" shape="circle" size="large" />
          </template>
        </Column>
        <Column header="Acciones" style="width: 160px">
          <template #body="{ data }">
            <Button icon="pi pi-building" text rounded @click="abrirAsignarAula(data)" v-tooltip.top="'Asignar aula'" />
            <Button icon="pi pi-pencil" text rounded @click="abrirEditar(data)" />
            <Button icon="pi pi-trash" text rounded severity="danger" @click="confirmarEliminar(data)" />
          </template>
        </Column>
      </DataTable>

      <!-- Dialog Editar -->
      <Dialog v-model:visible="dialogoEditarVisible" header="Editar Alumno" :modal="true" :style="{ width: '90vw', maxWidth: '400px' }">
        <div class="flex flex-col gap-3">
          <div class="flex flex-col gap-1">
            <label>Nombre</label>
            <InputText v-model="alumnoEdit.nombre" />
          </div>
          <div class="flex flex-col gap-1">
            <label>Carrera</label>
            <Select v-model="alumnoEdit.carrera" :options="carreras" placeholder="Seleccionar carrera" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoEditarVisible = false" />
          <Button label="Guardar" @click="guardarEdicion" :loading="guardando" />
        </template>
      </Dialog>

      <!-- Dialog Asignar Aula -->
      <Dialog v-model:visible="dialogoAulaVisible" header="Asignar Aula" :modal="true" :style="{ width: '90vw', maxWidth: '400px' }">
        <div class="flex flex-col gap-3">
          <p class="text-sm text-gray-600">Selecciona el aula para <strong>{{ alumnoAula?.nombre }}</strong>:</p>
          <Select v-model="aulaSeleccionada" :options="aulas" optionLabel="nombre" optionValue="id" placeholder="Sin aula" class="w-full" />
          <p class="text-xs text-gray-500">Selecciona "Sin aula" (vacío) para desasignar.</p>
        </div>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoAulaVisible = false" />
          <Button label="Quitar Aula" severity="warn" text @click="asignarAula(null)" v-if="alumnoAula?.aula_id" />
          <Button label="Asignar" @click="asignarAula(aulaSeleccionada)" :loading="guardandoAula" :disabled="!aulaSeleccionada" />
        </template>
      </Dialog>

      <Dialog v-model:visible="dialogoEliminarVisible" header="Confirmar Eliminación" :modal="true" :style="{ width: '90vw', maxWidth: '350px' }">
        <p>¿Estás seguro de eliminar a <strong>{{ alumnoEliminar?.nombre }}</strong>?</p>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoEliminarVisible = false" />
          <Button label="Eliminar" severity="danger" @click="eliminarAlumno" :loading="eliminando" />
        </template>
      </Dialog>

      <!-- Dialog Importar Excel -->
      <Dialog v-model:visible="dialogoImportarVisible" header="Importar desde Excel" :modal="true" :style="{ width: '90vw', maxWidth: '450px' }">
        <div class="flex flex-col gap-3">
          <p class="text-sm text-gray-600">Selecciona un archivo Excel (.xlsx) exportado previamente desde el sistema.</p>
          <p class="text-xs text-amber-600 bg-amber-50 p-2 rounded">Nota: Solo se importaran alumnos NUEVOS. Si un DNI ya existe en la BD o esta repetido en el Excel, se omitira y se notificara.</p>
          <FileUpload
            mode="basic"
            accept=".xlsx,.xls"
            :auto="false"
            chooseLabel="Seleccionar archivo"
            @select="onFileSelect"
            :customUpload="true"
            class="w-full"
          />
          <div v-if="resultadoImportacion" class="text-sm p-3 rounded" :class="resultadoImportacion.errores.length > 0 ? 'bg-amber-50 text-amber-700' : 'bg-green-50 text-green-700'">
            <p>{{ resultadoImportacion.mensaje }}</p>
            <div v-if="resultadoImportacion.errores.length > 0" class="mt-2">
              <p class="font-semibold">Errores:</p>
              <ul class="list-disc pl-4">
                <li v-for="err in resultadoImportacion.errores" :key="err">{{ err }}</li>
              </ul>
            </div>
          </div>
        </div>
        <template #footer>
          <Button label="Cancelar" text @click="dialogoImportarVisible = false" />
          <Button label="Importar" icon="pi pi-upload" @click="importarExcel" :loading="importando" :disabled="!archivoImportar" />
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
const dialogoEditarVisible = ref(false);
const dialogoEliminarVisible = ref(false);
const dialogoAulaVisible = ref(false);
const guardando = ref(false);
const eliminando = ref(false);
const guardandoAula = ref(false);
const alumnoEdit = ref({});
const alumnoEliminar = ref(null);
const alumnoAula = ref(null);
const aulaSeleccionada = ref(null);
const exportando = ref(false);
const importando = ref(false);
const dialogoImportarVisible = ref(false);
const archivoImportar = ref(null);
const resultadoImportacion = ref(null);

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

const filters = ref({
    global: { value: null, matchMode: 'contains' }
});

async function cargarAlumnos() {
    try {
        const data = await API.obtenerAlumnos({ sin_paginacion: 'true' });
        alumnos.value = data.map((a) => ({ ...a, aula_nombre: a.aula_nombre || '—' }));
    } catch {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar la lista', life: 3000 });
    }
}

function abrirEditar(data) {
    alumnoEdit.value = { id: data.id, nombre: data.nombre, carrera: data.carrera };
    dialogoEditarVisible.value = true;
}

async function guardarEdicion() {
    guardando.value = true;
    try {
        await API.editarAlumno(alumnoEdit.value.id, {
            nombre: alumnoEdit.value.nombre,
            carrera: alumnoEdit.value.carrera,
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

function abrirAsignarAula(data) {
    alumnoAula.value = data;
    aulaSeleccionada.value = data.aula_id || null;
    dialogoAulaVisible.value = true;
}

async function asignarAula(aulaId) {
    guardandoAula.value = true;
    try {
        await API.moverAlumno(alumnoAula.value.id, { aula_id: aulaId || null });
        toast.add({ severity: 'success', summary: 'Asignado', detail: 'Aula asignada correctamente', life: 3000 });
        dialogoAulaVisible.value = false;
        await cargarAlumnos();
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'No se pudo asignar', life: 3000 });
    } finally {
        guardandoAula.value = false;
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

async function exportarExcel() {
    exportando.value = true;
    try {
        const blob = await API.exportarExcel();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'alumnos_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
        toast.add({ severity: 'success', summary: 'Exportado', detail: 'Excel descargado correctamente', life: 3000 });
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo exportar', life: 3000 });
    } finally {
        exportando.value = false;
    }
}

function onFileSelect(event) {
    archivoImportar.value = event.files[0];
}

async function importarExcel() {
    if (!archivoImportar.value) return;
    importando.value = true;
    resultadoImportacion.value = null;
    try {
        const fd = new FormData();
        fd.append('archivo', archivoImportar.value);
        const res = await API.importarExcel(fd);
        const data = await res.json();
        if (res.ok) {
            resultadoImportacion.value = data;
            toast.add({ severity: 'success', summary: 'Importado', detail: data.mensaje, life: 4000 });
            await cargarAlumnos();
        } else {
            resultadoImportacion.value = { mensaje: data.error || 'Error', errores: [] };
            toast.add({ severity: 'error', summary: 'Error', detail: data.error || 'No se pudo importar', life: 4000 });
        }
    } catch (e) {
        toast.add({ severity: 'error', summary: 'Error', detail: e.message || 'Error de conexión', life: 3000 });
    } finally {
        importando.value = false;
    }
}

onMounted(async () => {
    await cargarAlumnos();
    try {
        aulas.value = await API.obtenerAulas();
    } catch { }
});
</script>