<template>
  <div class="flex flex-col gap-4">
    <!-- Stats cards -->
    <div class="grid grid-cols-3 gap-2 sm:gap-4">
      <Card class="min-w-0">
        <template #content>
          <div class="text-center">
            <div class="text-lg sm:text-2xl font-bold text-indigo-600">{{ stats.total }}</div>
            <div class="text-xs sm:text-sm text-gray-500">Total</div>
          </div>
        </template>
      </Card>
      <Card class="min-w-0">
        <template #content>
          <div class="text-center">
            <div class="text-lg sm:text-2xl font-bold text-green-600">{{ stats.exitosos }}</div>
            <div class="text-xs sm:text-sm text-gray-500">Exitosos</div>
          </div>
        </template>
      </Card>
      <Card class="min-w-0">
        <template #content>
          <div class="text-center">
            <div class="text-lg sm:text-2xl font-bold text-red-600">{{ stats.fallidos }}</div>
            <div class="text-xs sm:text-sm text-gray-500">Fallidos</div>
          </div>
        </template>
      </Card>
    </div>

    <!-- Charts -->
    <div class="flex flex-col lg:flex-row gap-4">
      <Card class="flex-1">
        <template #title>Registros por Hora</template>
        <template #content>
          <canvas ref="chartBarCanvas" height="220"></canvas>
        </template>
      </Card>
      <Card class="lg:min-w-[300px] lg:max-w-[380px] flex flex-col items-center justify-center">
        <template #title>Resultados</template>
        <template #content>
          <canvas ref="chartPieCanvas" height="220"></canvas>
        </template>
      </Card>
    </div>

    <!-- Logs table -->
    <div class="flex flex-col gap-2">
      <div class="flex flex-col sm:flex-row gap-2">
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
        <Column field="resultado" header="Resultado" style="width: 220px">
          <template #body="{ data }">
            <Tag
              :severity="String(data.resultado).toLowerCase().includes('exitos') ? 'success' : 'danger'"
              :value="data.resultado"
            />
          </template>
        </Column>
        <Column field="creado_en" header="Fecha" style="width: 160px" />
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';
import API from '../services/api';

const chartBarCanvas = ref(null);
const chartPieCanvas = ref(null);
const logs = ref([]);
const filtroDni = ref('');
const stats = ref({ total: 0, exitosos: 0, fallidos: 0 });
let chartBar = null;
let chartPie = null;

async function cargarLogs() {
    try {
        const params = {};
        if (filtroDni.value) params.dni = filtroDni.value;
        const data = await API.obtenerLogs(params);
        logs.value = data;
        stats.value.total = data.length;
        stats.value.exitosos = data.filter((r) => r.resultado && r.resultado.toLowerCase().includes('exitos')).length;
        stats.value.fallidos = stats.value.total - stats.value.exitosos;
        actualizarGraficoBarra(data);
        actualizarGraficoCircular(stats.value.exitosos, stats.value.fallidos);
    } catch { }
}

function actualizarGraficoBarra(data) {
    const horas = Array.from({ length: 24 }, (_, i) => `${i}h`);
    const conteo = Array(24).fill(0);
    data.forEach((r) => {
        if (r.creado_en) {
            const h = new Date(r.creado_en).getHours();
            if (h >= 0 && h < 24) conteo[h]++;
        }
    });
    if (chartBar) chartBar.destroy();
    if (chartBarCanvas.value) {
        chartBar = new Chart(chartBarCanvas.value, {
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

function actualizarGraficoCircular(exitosos, fallidos) {
    if (chartPie) chartPie.destroy();
    if (chartPieCanvas.value) {
        chartPie = new Chart(chartPieCanvas.value, {
            type: 'doughnut',
            data: {
                labels: ['Exitosos', 'Fallidos'],
                datasets: [{
                    data: [exitosos, fallidos],
                    backgroundColor: ['#22c55e', '#ef4444'],
                    borderWidth: 0,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                },
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
