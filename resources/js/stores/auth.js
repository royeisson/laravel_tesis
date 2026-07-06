import { reactive, readonly } from 'vue';

const state = reactive({
    usuario: JSON.parse(localStorage.getItem('usuario') || 'null'),
});

export const auth = readonly({
    get usuario() { return state.usuario; },
    get isAdmin() { return state.usuario?.rol === 'admin'; },
    get isCoordinador() { return state.usuario?.rol === 'coordinador'; },
    get isLoggedIn() { return !!state.usuario; },
});

export function login(usuario, password) {
    // Admin hardcodeado
    if (usuario === 'admin' && password === '12345678') {
        const u = { nombre: 'Administrador', usuario: 'admin', rol: 'admin' };
        state.usuario = u;
        localStorage.setItem('usuario', JSON.stringify(u));
        return { exito: true, rol: 'admin' };
    }
    // Coordinadores (patron)
    if (usuario.startsWith('coordinador') && password === '12345678') {
        const num = usuario.replace('coordinador', '').trim();
        const u = { nombre: `Coordinador ${num}`, usuario, rol: 'coordinador', numero: num };
        state.usuario = u;
        localStorage.setItem('usuario', JSON.stringify(u));
        return { exito: true, rol: 'coordinador' };
    }
    return { exito: false, error: 'Usuario o contraseña incorrectos' };
}

export function logout() {
    state.usuario = null;
    localStorage.removeItem('usuario');
}
