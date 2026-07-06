import { createRouter, createWebHistory } from 'vue-router';
import { auth } from '../stores/auth.js';

import Login from '../views/Login.vue';
import RegistrarAlumno from '../views/RegistrarAlumno.vue';
import VerificarAlumno from '../views/VerificarAlumno.vue';
import ListaAlumnos from '../views/ListaAlumnos.vue';
import Reportes from '../views/Reportes.vue';
import VistaAula from '../views/VistaAula.vue';
import CoordinadorAulas from '../views/CoordinadorAulas.vue';
import Coordinadores from '../views/Coordinadores.vue';

const routes = [
    { path: '/login', name: 'Login', component: Login, meta: { public: true } },

    // Rutas de Admin
    { path: '/', redirect: '/registrar' },
    { path: '/registrar', name: 'Registrar', component: RegistrarAlumno, meta: { rol: 'admin' } },
    { path: '/verificar', name: 'VerificarAdmin', component: VerificarAlumno, meta: { rol: 'admin' } },
    { path: '/lista', name: 'Lista', component: ListaAlumnos, meta: { rol: 'admin' } },
    { path: '/reportes', name: 'Reportes', component: Reportes, meta: { rol: 'admin' } },
    { path: '/aula/:id', name: 'AulaAdmin', component: VistaAula, props: true, meta: { rol: 'admin' } },
    { path: '/coordinadores', name: 'Coordinadores', component: Coordinadores, meta: { rol: 'admin' } },

    // Rutas de Coordinador
    { path: '/coordinador', redirect: '/coordinador/verificar' },
    { path: '/coordinador/verificar', name: 'VerificarCoord', component: VerificarAlumno, meta: { rol: 'coordinador' } },
    { path: '/coordinador/aulas', name: 'AulasCoord', component: CoordinadorAulas, meta: { rol: 'coordinador' } },
    { path: '/coordinador/aula/:id', name: 'AulaCoord', component: VistaAula, props: true, meta: { rol: 'coordinador' } },
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
            next(auth.isAdmin ? '/registrar' : '/coordinador/verificar');
        } else {
            next();
        }
        return;
    }

    if (!auth.isLoggedIn) {
        next('/login');
        return;
    }

    // Admin puede entrar a todo
    if (auth.isAdmin) {
        next();
        return;
    }

    // Coordinador solo a rutas de coordinador
    if (requiereRol === 'admin' && auth.isCoordinador) {
        next('/coordinador/verificar');
        return;
    }

    next();
});

export default router;
