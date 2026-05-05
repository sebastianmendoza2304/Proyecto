// ============================================================
// api.js — Capa de acceso a los endpoints PHP
// Sistema de Alertas por Riesgo Académico - Universidad Libre
//
// Todos los paneles importan este archivo y usan API.*
// en lugar de localStorage directamente.
// ============================================================

const API = (() => {

  // ── Ajusta esta ruta si los PHP están en una subcarpeta ──
  const BASE = '';   // ej: '/alertas' si está en /alertas/

  // ─────────────────────────────────────────────────────────
  // Helper interno: fetch con manejo de errores
  // ─────────────────────────────────────────────────────────
  async function req(url, opciones = {}) {
    try {
      const resp = await fetch(BASE + url, {
        headers: { 'Content-Type': 'application/json', ...opciones.headers },
        ...opciones,
      });
      const json = await resp.json();
      if (!json.ok) throw new Error(json.data || 'Error del servidor');
      return json.data;
    } catch (err) {
      console.error('[API]', url, err);
      throw err;
    }
  }

  // Construye query string desde un objeto de filtros
  function qs(filtros = {}) {
    const p = new URLSearchParams(
      Object.entries(filtros).filter(([, v]) => v !== null && v !== undefined && v !== '')
    );
    const s = p.toString();
    return s ? '?' + s : '';
  }

  // ─────────────────────────────────────────────────────────
  // AUTH / LOGIN
  // ─────────────────────────────────────────────────────────
  async function login(nombre, password) {
    return req('/login.php', {
      method: 'POST',
      body: JSON.stringify({ nombre, password }),
    });
  }

  // ─────────────────────────────────────────────────────────
  // USUARIOS
  // ─────────────────────────────────────────────────────────
  const usuarios = {
    listar:    (filtros = {}) => req('/usuarios.php' + qs(filtros)),
    obtener:   (id)           => req(`/usuarios.php?id=${id}`),
    crear:     (datos)        => req('/usuarios.php', { method: 'POST', body: JSON.stringify(datos) }),
    actualizar:(id, datos)    => req(`/usuarios.php?id=${id}`, { method: 'PUT', body: JSON.stringify(datos) }),
    eliminar:  (id)           => req(`/usuarios.php?id=${id}`, { method: 'DELETE' }),
  };

  // ─────────────────────────────────────────────────────────
  // ESTUDIANTES
  // ─────────────────────────────────────────────────────────
  const estudiantes = {
    listar:    (filtros = {}) => req('/estudiantes.php' + qs(filtros)),
    obtener:   (id)           => req(`/estudiantes.php?id=${id}`),
    crear:     (datos)        => req('/estudiantes.php', { method: 'POST', body: JSON.stringify(datos) }),
    actualizar:(id, datos)    => req(`/estudiantes.php?id=${id}`, { method: 'PUT', body: JSON.stringify(datos) }),
    eliminar:  (id)           => req(`/estudiantes.php?id=${id}`, { method: 'DELETE' }),
  };

  // ─────────────────────────────────────────────────────────
  // REPORTES
  // ─────────────────────────────────────────────────────────
  const reportes = {
    listar:    (filtros = {}) => req('/reportes.php' + qs(filtros)),
    obtener:   (id)           => req(`/reportes.php?id=${id}`),
    crear:     (datos)        => req('/reportes.php', { method: 'POST', body: JSON.stringify(datos) }),
    actualizar:(id, datos)    => req(`/reportes.php?id=${id}`, { method: 'PUT', body: JSON.stringify(datos) }),
    eliminar:  (id)           => req(`/reportes.php?id=${id}`, { method: 'DELETE' }),
  };

  // ─────────────────────────────────────────────────────────
  // SEGUIMIENTOS
  // ─────────────────────────────────────────────────────────
  const seguimientos = {
    listar:    (filtros = {}) => req('/seguimientos.php' + qs(filtros)),
    obtener:   (id)           => req(`/seguimientos.php?id=${id}`),
    crear:     (datos)        => req('/seguimientos.php', { method: 'POST', body: JSON.stringify(datos) }),
    actualizar:(id, datos)    => req(`/seguimientos.php?id=${id}`, { method: 'PUT', body: JSON.stringify(datos) }),
    eliminar:  (id)           => req(`/seguimientos.php?id=${id}`, { method: 'DELETE' }),
  };

  // ─────────────────────────────────────────────────────────
  // Sesión en memoria (reemplaza localStorage para datos de sesión)
  // ─────────────────────────────────────────────────────────
  const sesion = {
    guardar(usuario) {
      sessionStorage.setItem('usuario', JSON.stringify(usuario));
    },
    obtener() {
      try { return JSON.parse(sessionStorage.getItem('usuario')) || null; }
      catch { return null; }
    },
    cerrar() {
      sessionStorage.removeItem('usuario');
    },
    get nombre() { return this.obtener()?.nombre || ''; },
    get rol()    { return this.obtener()?.rol    || ''; },
    get id()     { return this.obtener()?.id     || null; },
  };

  return { login, usuarios, estudiantes, reportes, seguimientos, sesion };
})();