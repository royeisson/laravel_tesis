<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-100">
    <Card class="w-full max-w-lg mx-4">
      <template #title>
        <div class="text-center">
          <h2 class="text-xl font-bold text-gray-800">Bienvenido, {{ auth.usuario?.nombre }}</h2>
          <p class="text-sm text-gray-500 mt-1">Panel de Coordinador</p>
        </div>
      </template>
      <template #content>
        <div class="flex flex-col items-center gap-4 py-4">
          <i class="pi pi-user text-indigo-500" style="font-size: 3rem"></i>
          <p class="text-gray-600 text-center">
            Este panel está en construcción. Pronto podrás ver las funciones disponibles para coordinadores.
          </p>
          <Button label="Cerrar sesión" icon="pi pi-sign-out" severity="secondary" @click="cerrarSesion" />
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { auth, logout } from '../stores/auth.js';

const router = useRouter();

function cerrarSesion() {
    logout();
    router.push('/login');
}
</script>
