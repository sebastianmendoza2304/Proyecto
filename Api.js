// ============================================================
// api.js — Capa de acceso a los endpoints PHP
// IMPORTANTE: URLs relativas (sin / al inicio) para que funcione
// en cualquier subcarpeta del servidor.
// ============================================================

const API = (() => {

  // ── Helper interno ────────────────────────────────────
  async function req(url, opciones = {}) {
    try {
      const resp = await fetch(url, {   // <-- sin BASE, rutas relativas
        headers: { 'Content-Type': 'application/json', ...opciones.headers },
        ...opciones,
      });
      const json = await resp.json();
      if (!json.ok) throw new Error(json.data || 'Error del servidor');
      return json.data;
    } catch (err) {
      console.error('[API]', url, err.message);
      throw err;
    }
  }

  // Construye query string desde un objeto
  function qs(filtros = {}) {
    const p = new URLSearchParams(
      Object.entries(filtros).filter(([, v]) => v !== null && v !== undefined && v !== '')
    );
    const s = p.toString();
    return s ? '?' + s : '';
  }

  // ── AUTH ──────────────────────────────────────────────
  async function login(nombre, password) {
    return req('login.php', {           // SIN barra inicial
      method: 'POST',
      body: JSON.stringify({ nombre, password }),
    });
  }

  // ── USUARIOS ──────────────────────────────────────────
  const usuarios = {
    listar:    (f = {}) => req('usuarios.php' + qs(f)),
    obtener:   (id)     => req(`usuarios.php?id=${id}`),
    crear:     (d)      => req('usuarios.php',        { method: 'POST',   body: JSON.stringify(d) }),
    actualizar:(id, d)  => req(`usuarios.php?id=${id}`,{ method: 'PUT',   body: JSON.stringify(d) }),
    eliminar:  (id)     => req(`usuarios.php?id=${id}`,{ method: 'DELETE' }),
  };

  // ── ESTUDIANTES ───────────────────────────────────────
  const estudiantes = {
    listar:    (f = {}) => req('estudiantes.php' + qs(f)),
    obtener:   (id)     => req(`estudiantes.php?id=${id}`),
    crear:     (d)      => req('estudiantes.php',        { method: 'POST',   body: JSON.stringify(d) }),
    actualizar:(id, d)  => req(`estudiantes.php?id=${id}`,{ method: 'PUT',   body: JSON.stringify(d) }),
    eliminar:  (id)     => req(`estudiantes.php?id=${id}`,{ method: 'DELETE' }),
  };

  // ── REPORTES ──────────────────────────────────────────
  const reportes = {
    listar:    (f = {}) => req('reportes.php' + qs(f)),
    obtener:   (id)     => req(`reportes.php?id=${id}`),
    crear:     (d)      => req('reportes.php',        { method: 'POST',   body: JSON.stringify(d) }),
    actualizar:(id, d)  => req(`reportes.php?id=${id}`,{ method: 'PUT',   body: JSON.stringify(d) }),
    eliminar:  (id)     => req(`reportes.php?id=${id}`,{ method: 'DELETE' }),
  };

  // ── SEGUIMIENTOS ──────────────────────────────────────
  const seguimientos = {
    listar:    (f = {}) => req('seguimientos.php' + qs(f)),
    obtener:   (id)     => req(`seguimientos.php?id=${id}`),
    crear:     (d)      => req('seguimientos.php',        { method: 'POST',   body: JSON.stringify(d) }),
    actualizar:(id, d)  => req(`seguimientos.php?id=${id}`,{ method: 'PUT',   body: JSON.stringify(d) }),
    eliminar:  (id)     => req(`seguimientos.php?id=${id}`,{ method: 'DELETE' }),
  };

  // ── SESIÓN (sessionStorage por pestaña) ───────────────
  const sesion = {
    guardar(u)  { sessionStorage.setItem('usuario', JSON.stringify(u)); },
    obtener()   { try { return JSON.parse(sessionStorage.getItem('usuario')) || null; } catch { return null; } },
    cerrar()    { sessionStorage.removeItem('usuario'); },
    get nombre(){ return this.obtener()?.nombre || ''; },
    get rol()   { return this.obtener()?.rol    || ''; },
    get id()    { return this.obtener()?.id     || null; },
  };

  // ── PERÍODOS ──────────────────────────────────────────
  const periodos = {
    listar:   ()      => req('periodos.php'),
    activo:   ()      => req('periodos.php?activo=1'),
    crear:    (d)     => req('periodos.php',        { method: 'POST', body: JSON.stringify(d) }),
    activar:  (id)    => req(`periodos.php?id=${id}`, { method: 'PUT',  body: JSON.stringify({ activo: 1 }) }),
    actualizar:(id,d) => req(`periodos.php?id=${id}`, { method: 'PUT',  body: JSON.stringify(d) }),
  };

  return { login, usuarios, estudiantes, reportes, seguimientos, periodos, sesion };
})();
// cambios en carpeta
