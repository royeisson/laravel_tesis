const BASE = '/api';

async function request(method, path, data) {
    const opts = {
        method,
        headers: { Accept: 'application/json' },
    };
    if (data) {
        opts.body = JSON.stringify(data);
        opts.headers['Content-Type'] = 'application/json';
    }
    const res = await fetch(`${BASE}${path}`, opts);
    const text = await res.text();
    let json = null;
    try {
        json = JSON.parse(text);
    } catch {
        // no es JSON
    }
    if (!res.ok) {
        throw new Error(json?.error || json?.message || text || `HTTP ${res.status}`);
    }
    return json;
}

export default {
    // Aulas
    obtenerAulas: () => request('GET', '/aulas'),
    crearAula: (data) => request('POST', '/aulas', data),
    editarAula: (id, data) => request('PUT', `/aulas/${id}`, data),
    eliminarAula: (id) => request('DELETE', `/aulas/${id}`),

    // Alumnos
    obtenerAlumnos: (params) => {
        const q = new URLSearchParams(params).toString();
        return request('GET', `/alumnos?${q}`);
    },
    obtenerAlumno: (id) => request('GET', `/alumnos/${id}`),
    editarAlumno: (id, data) => request('PUT', `/alumnos/${id}`, data),
    moverAlumno: (id, data) => request('PUT', `/alumnos/${id}/mover`, data),
    eliminarAlumno: (id) => request('DELETE', `/alumnos/${id}`),

    // Verificación
    registrarRostro: (formData) =>
        fetch(`${BASE}/registrar-rostro`, { method: 'POST', body: formData }),
    verificarRostro: (formData) =>
        fetch(`${BASE}/verificar-rostro`, { method: 'POST', body: formData }),

    // Reportes
    obtenerLogs: (params) => {
        const q = new URLSearchParams(params).toString();
        return request('GET', `/reportes/logs?${q}`);
    },
    exportarReporte: () =>
        fetch(`${BASE}/reportes/exportar`).then((r) => r.blob()),

    // Detección simple
    detectarRostroSimple: (blob) => {
        const fd = new FormData();
        fd.append('file', blob, 'f.jpg');
        return fetch(`${BASE}/detectar-rostro-simple`, { method: 'POST', body: fd }).then((r) => r.json());
    },

    // Coordinadores
    obtenerCoordinadores: () => request('GET', '/coordinadores'),
    crearCoordinador: (data) => request('POST', '/coordinadores', data),
    editarCoordinador: (id, data) => request('PUT', `/coordinadores/${id}`, data),
    eliminarCoordinador: (id) => request('DELETE', `/coordinadores/${id}`),
    asignarAulasCoordinador: (id, data) => request('POST', `/coordinadores/${id}/aulas`, data),
    obtenerMisAulas: (usuario) => {
        return fetch(`${BASE}/coordinadores/mis-aulas`, {
            method: 'GET',
            headers: { 'X-Coordinador-Usuario': usuario },
        }).then((r) => r.json());
    },
};
