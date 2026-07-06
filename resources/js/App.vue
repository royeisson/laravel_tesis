<template>
  <div class="flex h-screen overflow-hidden">
    <SidebarComponent />
    <div class="flex flex-col flex-1 min-w-0">
      <header class="bg-indigo-700 text-white px-4 py-2 flex items-center gap-2 shrink-0" style="height: 48px">
        <i class="pi pi-shield" style="font-size: 1.2rem"></i>
        <span class="font-semibold">Control Biométrico</span>
      </header>
      <main class="flex-1 overflow-auto p-4">
        <router-view />
      </main>
    </div>
    <Toast position="bottom-right" />
  </div>
</template>

<script setup>
import SidebarComponent from './components/Sidebar.vue';
</script>
