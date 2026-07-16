import { createRouter, createWebHistory } from 'vue-router';
import { auth } from '../stores/auth.js';

import Login from '../views/Login.vue';
import ListaAlumnos from '../views/ListaAlumnos.vue';
import Reportes from '../views/Reportes.vue';
import VistaAula from '../views/VistaAula.vue';
import CoordinadorAulas from '../views/CoordinadorAulas.vue';
import Coordinadores from '../views/Coordinadores.vue';
import Aulas from '../views/Aulas.vue';
import Guias from '../views/Guias.vue';

// Lazy loading de componentes pesados (cámara / MediaPipe)
const RegistrarAlumno = () => import('../views/RegistrarAlumno.vue');
const VerificarAlumno = () => import('../views/VerificarAlumno.vue');
const VerificacionMasiva = () => import('../views/VerificacionMasiva.vue');
const GuiaVerificar = () => import('../views/GuiaVerificar.vue');

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
    { path: '/guias', name: 'Guias', component: Guias, meta: { rol: 'admin' } },
    { path: '/aulas', name: 'Aulas', component: Aulas, meta: { rol: 'admin' } },

    // Rutas de Coordinador
    { path: '/coordinador', redirect: '/coordinador/verificar' },
    { path: '/coordinador/verificar', name: 'VerificarCoord', component: VerificacionMasiva, meta: { rol: 'coordinador' } },
    { path: '/coordinador/aulas', name: 'AulasCoord', component: CoordinadorAulas, meta: { rol: 'coordinador' } },
    { path: '/coordinador/aula/:id', name: 'AulaCoord', component: VistaAula, props: true, meta: { rol: 'coordinador' } },

    // Rutas de Guia
    { path: '/guia', redirect: '/guia/verificar' },
    { path: '/guia/verificar', name: 'VerificarGuia', component: GuiaVerificar, meta: { rol: 'guia' } },
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
            if (auth.isAdmin) next('/registrar');
            else if (auth.isGuia) next('/guia/verificar');
            else next('/coordinador/verificar');
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

    // Guia solo a rutas de guia
    if (auth.isGuia) {
        if (requiereRol === 'guia') {
            next();
        } else {
            next('/guia/verificar');
        }
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