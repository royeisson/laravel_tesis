<template>
  <div class="flex flex-col gap-4">
    <div class="flex gap-4 flex-wrap">
      <Card class="flex-1 min-w-[180px]">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-indigo-600">{{ stats.total }}</div>
            <div class="text-sm text-gray-500">Total Registros</div>
          </div>
        </template>
      </Card>
      <Card class="flex-1 min-w-[180px]">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-green-600">{{ stats.exitosos }}</div>
            <div class="text-sm text-gray-500">Exitosos</div>
          </div>
        </template>
      </Card>
      <Card class="flex-1 min-w-[180px]">
        <template #content>
          <div class="text-center">
            <div class="text-2xl font-bold text-red-600">{{ stats.fallidos }}</div>
            <div class="text-sm text-gray-500">Fallidos</div>
          </div>
        </template>
      </Card>
    </div>
    <div class="flex flex-col lg:flex-row gap-4">
      <div class="flex-1">
        <Card>
          <template #title>Registros por Hora</template>
          <template #content>
            <canvas ref="chartCanvas" height="200"></canvas>
          </template>
        </Card>
      </div>
      <div class="flex flex-col gap-2 lg:min-w-[350px]">
        <div class="flex gap-2">
          <InputText v-model="filtroDni" placeholder="Filtrar por DNI" class="flex-1" />
          <Button label="Exportar CSV" icon="pi pi-download" severity="secondary" @click="exportar" />
        </div>
        <DataTable
          :value="logs"
          paginator
          :rows="10"
          :rowsPerPageOptions="[5, 10, 25]"
          size="small"
          stripedRows
          scrollable
          scrollHeight="400px"
        >
          <Column field="id" header="ID" style="width: 50px" />
          <Column field="alumno_nombre" header="Alumno" />
          <Column field="alumno_dni" header="DNI" style="width: 100px" />
          <Column field="resultado" header="Resultado" style="width: 120px">
            <template #body="{ data }">
              <Tag
                :severity="String(data.resultado).includes('exitos') ? 'success' : 'danger'"
                :value="data.resultado"
              />
            </template>
          </Column>
          <Column field="creado_en" header="Fecha" style="width: 160px" />
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';
import API from '../services/api';

const chartCanvas = ref(null);
const logs = ref([]);
const filtroDni = ref('');
const stats = ref({ total: 0, exitosos: 0, fallidos: 0 });
let chart = null;

async function cargarLogs() {
    try {
        const params = {};
        if (filtroDni.value) params.dni = filtroDni.value;
        const data = await API.obtenerLogs(params);
        logs.value = data;
        stats.value.total = data.length;
        stats.value.exitosos = data.filter((r) => r.resultado && r.resultado.includes('exitos')).length;
        stats.value.fallidos = stats.value.total - stats.value.exitosos;
        actualizarGrafico(data);
    } catch { }
}

function actualizarGrafico(data) {
    const horas = Array.from({ length: 24 }, (_, i) => `${i}h`);
    const conteo = Array(24).fill(0);
    data.forEach((r) => {
        if (r.creado_en) {
            const h = new Date(r.creado_en).getHours();
            if (h >= 0 && h < 24) conteo[h]++;
        }
    });
    if (chart) chart.destroy();
    if (chartCanvas.value) {
        chart = new Chart(chartCanvas.value, {
            type: 'bar',
            data: {
                labels: horas,
                datasets: [{ label: 'Registros', data: conteo, backgroundColor: '#6366f1' }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
            },
        });
    }
}

function exportar() {
    window.open('/api/reportes/exportar', '_blank');
}

watch(filtroDni, () => cargarLogs());

onMounted(() => cargarLogs());
</script>
