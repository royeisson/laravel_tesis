import { createRouter, createWebHistory } from 'vue-router';
import { auth } from '../stores/auth.js';

import Login from '../views/Login.vue';
import CoordinadorDashboard from '../views/CoordinadorDashboard.vue';
import RegistrarAlumno from '../views/RegistrarAlumno.vue';
import VerificarAlumno from '../views/VerificarAlumno.vue';
import ListaAlumnos from '../views/ListaAlumnos.vue';
import Reportes from '../views/Reportes.vue';
import VistaAula from '../views/VistaAula.vue';

const routes = [
    { path: '/login', name: 'Login', component: Login, meta: { public: true } },
    { path: '/coordinador', name: 'Coordinador', component: CoordinadorDashboard, meta: { rol: 'coordinador' } },
    { path: '/', redirect: '/registrar' },
    { path: '/registrar', name: 'Registrar', component: RegistrarAlumno, meta: { rol: 'admin' } },
    { path: '/verificar', name: 'Verificar', component: VerificarAlumno, meta: { rol: 'admin' } },
    { path: '/lista', name: 'Lista', component: ListaAlumnos, meta: { rol: 'admin' } },
    { path: '/reportes', name: 'Reportes', component: Reportes, meta: { rol: 'admin' } },
    { path: '/aula/:id', name: 'Aula', component: VistaAula, props: true, meta: { rol: 'admin' } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, from, next) => {
    const isPublic = to.meta?.public;
    const requiereRol = to.meta?.rol;

    if (isPublic) {
        if (auth.isLoggedIn) {
            next(auth.isAdmin ? '/registrar' : '/coordinador');
        } else {
            next();
        }
        return;
    }

    if (!auth.isLoggedIn) {
        next('/login');
        return;
    }

    if (requiereRol && auth.usuario?.rol !== requiereRol) {
        next(auth.isAdmin ? '/registrar' : '/coordinador');
        return;
    }

    next();
});

export default router;
