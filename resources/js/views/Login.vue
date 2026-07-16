<template>
  <div class="flex items-center justify-center min-h-screen bg-gray-900 p-4 sm:p-6">
    <Card class="w-full max-w-sm shadow-2xl border-0">
      <template #content>
        <div class="flex flex-col items-center gap-5 py-2">
          <!-- Logo admin -->
          <div class="w-20 h-20 rounded-full bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
            <i class="pi pi-user-shield text-white" style="font-size: 2.5rem"></i>
          </div>

          <div class="text-center space-y-1">
            <h1 class="text-2xl font-bold text-gray-800">Control Biométrico</h1>
            <p class="text-sm text-gray-500">Inicia sesión para continuar</p>
          </div>

          <div class="w-full flex flex-col gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-gray-700">Usuario</label>
              <div class="relative">
                <InputText
                  v-model="usuario"
                  placeholder="admin"
                  class="w-full pl-10"
                  @keyup.enter="iniciarSesion"
                />
              </div>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-gray-700">Contraseña</label>
              <div class="relative">
                <Password
                  v-model="password"
                  placeholder="••••••••"
                  :feedback="false"
                  toggleMask
                  inputClass="w-full pl-10"
                  @keyup.enter="iniciarSesion"
                />
              </div>
            </div>

            <Message v-if="error" severity="error" :closable="false" class="text-sm">
              {{ error }}
            </Message>

            <Button
              label="Ingresar"
              icon="pi pi-sign-in"
              @click="iniciarSesion"
              :loading="cargando"
              class="w-full mt-2"
              severity="primary"
            />
          </div>

          <div class="text-xs text-gray-400 text-center pt-2">
            Sistema de Control de Acceso Biométrico
          </div>
        </div>
      </template>
    </Card>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../stores/auth.js';

const router = useRouter();
const usuario = ref('');
const password = ref('');
const error = ref('');
const cargando = ref(false);

async function iniciarSesion() {
    error.value = '';
    if (!usuario.value || !password.value) {
        error.value = 'Ingresa usuario y contraseña';
        return;
    }
    cargando.value = true;
    const res = await login(usuario.value, password.value);
    cargando.value = false;
    if (res.exito) {
        if (res.rol === 'admin') {
            router.push('/registrar');
        } else if (res.rol === 'guia') {
            router.push('/guia/verificar');
        } else {
            router.push('/coordinador');
        }
    } else {
        error.value = res.error;
    }
}
</script>
