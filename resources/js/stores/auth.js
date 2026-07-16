import { reactive, readonly } from 'vue';

const state = reactive({
    usuario: JSON.parse(localStorage.getItem('usuario') || 'null'),
});

export const auth = readonly({
    get usuario() { return state.usuario; },
    get isAdmin() { return state.usuario?.rol === 'admin'; },
    get isCoordinador() { return state.usuario?.rol === 'coordinador'; },
    get isGuia() { return state.usuario?.rol === 'guia'; },
    get isLoggedIn() { return !!state.usuario; },
});

export async function login(usuario, password) {
    // Admin hardcodeado
    if (usuario === 'admin' && password === '12345678') {
        const u = { nombre: 'Administrador', usuario: 'admin', rol: 'admin' };
        state.usuario = u;
        localStorage.setItem('usuario', JSON.stringify(u));
        return { exito: true, rol: 'admin' };
    }
    // Coordinadores desde backend
    try {
        const res = await fetch('/api/coordinadores/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ usuario, password }),
        });
        const data = await res.json();
        if (res.ok) {
            const u = { nombre: data.nombre, usuario: data.usuario, rol: 'coordinador' };
            state.usuario = u;
            localStorage.setItem('usuario', JSON.stringify(u));
            return { exito: true, rol: 'coordinador' };
        }
    } catch { }

    // Guias desde backend
    try {
        const res = await fetch('/api/guias/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ usuario, password }),
        });
        const data = await res.json();
        if (res.ok) {
            const u = { nombre: data.nombre, usuario: data.usuario, rol: 'guia' };
            state.usuario = u;
            localStorage.setItem('usuario', JSON.stringify(u));
            return { exito: true, rol: 'guia' };
        }
    } catch { }

    return { exito: false, error: 'Credenciales incorrectas' };
}

export function logout() {
    state.usuario = null;
    localStorage.removeItem('usuario');
}