import { createRouter, createWebHistory } from 'vue-router';

import RegistrarAlumno from '../views/RegistrarAlumno.vue';
import VerificarAlumno from '../views/VerificarAlumno.vue';
import ListaAlumnos from '../views/ListaAlumnos.vue';
import Reportes from '../views/Reportes.vue';
import VistaAula from '../views/VistaAula.vue';

const routes = [
    { path: '/', redirect: '/registrar' },
    { path: '/registrar', name: 'Registrar', component: RegistrarAlumno },
    { path: '/verificar', name: 'Verificar', component: VerificarAlumno },
    { path: '/lista', name: 'Lista', component: ListaAlumnos },
    { path: '/reportes', name: 'Reportes', component: Reportes },
    { path: '/aula/:id', name: 'Aula', component: VistaAula, props: true },
];

export default createRouter({
    history: createWebHistory(),
    routes,
});
