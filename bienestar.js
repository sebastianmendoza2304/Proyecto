// ============================================================
// bienestar.js — Panel bienestar conectado a la BD
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

const sesion        = API.sesion.obtener();
const nombreUsuario = sesion?.nombre || 'Profesional Bienestar';
const profesionalId = sesion?.id     || null;

document.getElementById('nombreHeader').textContent = 'Bienvenido/a: ' + nombreUsuario;

function cerrarSesion() { API.sesion.cerrar(); }

const X = s => { if(s==null)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

const BADGE = {
  'Reportado':'badge-reportado','En revisión':'badge-revision',
  'En seguimiento':'badge-seguimiento','Remitido':'badge-remitido','Cerrado':'badge-cerrado'
};

let todosLos = [];

// ─────────────────────────────────────────────────────────
// INIT
// ─────────────────────────────────────────────────────────
async function init() {
  await cargarCasos();
}

async function cargarCasos() {
  try {
    todosLos = await API.reportes.listar();
    renderCasos(todosLos);
    actualizarStats(todosLos);
  } catch (err) {
    mostrarMsg('msgCasos', 'error', '❌ Error cargando casos: ' + err.message);
  }
}

function actualizarStats(r) {
  document.getElementById('statTotal').textContent      = r.length;
  document.getElementById('statPendientes').textContent = r.filter(x => x.estado === 'Reportado').length;
  document.getElementById('statEnProceso').textContent  = r.filter(x => ['En revisión','En seguimiento'].includes(x.estado)).length;
  document.getElementById('statCerrados').textContent   = r.filter(x => x.estado === 'Cerrado').length;
}

function renderCasos(datos) {
  const tbody = document.getElementById('tbodyCasos');
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#aaa;padding:24px">Sin casos asignados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map(r => `
    <tr>
      <td>${r.id}</td>
      <td><strong>${X(r.estudiante)}</strong></td>
      <td>${X(r.materia)}</td>
      <td>${X(r.motivo)}</td>
      <td><span class="badge ${BADGE[r.estado]||'badge-reportado'}">${X(r.estado)}</span></td>
      <td style="font-size:0.8rem;color:#888">${X(r.fecha||'')}</td>
      <td><button class="btn btn-secondary btn-sm" onclick="abrirModal(${r.id})">Gestionar</button></td>
    </tr>`).join('');
}

function filtrarCasos() {
  const est    = document.getElementById('buscarEst').value.toLowerCase();
  const mat    = document.getElementById('buscarMat').value.toLowerCase();
  const estado = document.getElementById('filtroEst').value;
  const f = todosLos.filter(r =>
    (!est    || (r.estudiante||'').toLowerCase().includes(est)) &&
    (!mat    || (r.materia   ||'').toLowerCase().includes(mat)) &&
    (!estado || r.estado === estado)
  );
  renderCasos(f);
}

// ─────────────────────────────────────────────────────────
// MODAL GESTIÓN → PUT /reportes.php + POST /seguimientos.php
// ─────────────────────────────────────────────────────────
let modalReporteId = null;

async function abrirModal(id) {
  modalReporteId = id;
  try {
    const r = await API.reportes.obtener(id);
    document.getElementById('modalSubtitle').textContent = `#${r.id} — ${r.estudiante} | ${r.materia}`;
    document.getElementById('infoCasoModal').innerHTML = `
      <p><strong>Motivo:</strong> ${X(r.motivo)}</p>
      <p><strong>Estado actual:</strong> ${X(r.estado)}</p>
      <p><strong>Observaciones docente:</strong> ${X(r.observaciones||'—')}</p>`;

    const estadoValido = ['En revisión','En seguimiento','Cerrado'].includes(r.estado) ? r.estado : 'En seguimiento';
    document.getElementById('modalEstado').value = estadoValido;

    // Precargar último seguimiento si existe
    if (r.seguimientos && r.seguimientos.length) {
      const seg = r.seguimientos[r.seguimientos.length - 1];
      document.getElementById('modalObservaciones').value   = seg.observaciones   || '';
      document.getElementById('modalRecomendaciones').value = seg.recomendaciones || '';
    } else {
      document.getElementById('modalObservaciones').value   = '';
      document.getElementById('modalRecomendaciones').value = '';
    }

    document.getElementById('modalGestion').classList.add('open');
  } catch (err) {
    mostrarMsg('msgCasos', 'error', '❌ ' + err.message);
  }
}

async function guardarGestion() {
  if (!modalReporteId) return;

  const nuevoEstado    = document.getElementById('modalEstado').value;
  const intervencion   = document.getElementById('modalIntervencion').value;
  const observaciones  = document.getElementById('modalObservaciones').value;
  const recomendaciones= document.getElementById('modalRecomendaciones').value;

  try {
    // 1. Actualizar estado del reporte
    await API.reportes.actualizar(modalReporteId, { estado: nuevoEstado });

    // 2. Registrar seguimiento en la BD
    await API.seguimientos.crear({
      reporte_id:      modalReporteId,
      profesional_id:  profesionalId,
      intervencion,
      observaciones,
      recomendaciones,
      nuevo_estado:    nuevoEstado,
    });

    cerrarModal();
    await cargarCasos();
    mostrarMsg('msgCasos', 'success', '✅ Caso actualizado correctamente.');
  } catch (err) {
    mostrarMsg('msgCasos', 'error', '❌ ' + err.message);
  }
}

function cerrarModal() {
  document.getElementById('modalGestion').classList.remove('open');
  modalReporteId = null;
}
document.getElementById('modalGestion').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

// ─────────────────────────────────────────────────────────
// SEGUIMIENTO POR ID → GET + POST
// ─────────────────────────────────────────────────────────
async function buscarCaso() {
  const id = parseInt(document.getElementById('segId').value);
  const el = document.getElementById('infoCaso');
  if (!id) return;

  try {
    const r = await API.reportes.obtener(id);
    el.innerHTML = `
      <p><strong>Estudiante:</strong> ${X(r.estudiante)} &nbsp;|&nbsp; <strong>Materia:</strong> ${X(r.materia)}</p>
      <p><strong>Docente:</strong> ${X(r.docente||'N/A')} &nbsp;|&nbsp; <strong>Motivo:</strong> ${X(r.motivo)}</p>
      <p><strong>Estado:</strong> ${X(r.estado)} &nbsp;|&nbsp; <strong>Fecha:</strong> ${X(r.fecha||'N/D')}</p>`;
    el.style.display = 'block';
  } catch {
    el.innerHTML = `<p style="color:#c62828">❌ No se encontró el reporte con ID ${id}.</p>`;
    el.style.display = 'block';
  }
}

async function registrarSeguimiento(e) {
  e.preventDefault();

  const id             = parseInt(document.getElementById('segId').value);
  const nuevoEstado    = document.getElementById('segEstado').value;
  const intervencion   = document.getElementById('segIntervencion').value;
  const observaciones  = document.getElementById('segObservaciones').value;
  const recomendaciones= document.getElementById('segRecomendaciones').value;

  if (!id) {
    mostrarMsg('msgSeg', 'error', '❌ Ingresa el ID del reporte.');
    return;
  }

  try {
    // Actualizar estado del reporte
    await API.reportes.actualizar(id, { estado: nuevoEstado });

    // Registrar seguimiento
    await API.seguimientos.crear({
      reporte_id:      id,
      profesional_id:  profesionalId,
      intervencion,
      observaciones,
      recomendaciones,
      nuevo_estado:    nuevoEstado,
    });

    await cargarCasos();
    mostrarMsg('msgSeg', 'success', '✅ Seguimiento registrado correctamente.');
    limpiarSeg();
  } catch (err) {
    mostrarMsg('msgSeg', 'error', '❌ ' + err.message);
  }
}

function limpiarSeg() {
  document.getElementById('formSeguimiento').reset();
  document.getElementById('infoCaso').style.display = 'none';
}

// ─────────────────────────────────────────────────────────
// Tabs y utilidades
// ─────────────────────────────────────────────────────────
function cambiarTab(nombre, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + nombre).classList.add('active');
  el.classList.add('active');
}

function mostrarMsg(id, tipo, texto) {
  const el = document.getElementById(id);
  el.className = 'msg ' + tipo;
  el.textContent = texto;
  setTimeout(() => el.className = 'msg', 5000);
}

init();