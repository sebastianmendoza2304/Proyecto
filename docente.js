// ============================================================
// docente.js — Panel docente conectado a reportes.php
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

// ── Datos de sesión ──────────────────────────────────────
const sesion       = API.sesion.obtener();
const nombreUsuario = sesion?.nombre || 'Docente';
const docenteId    = sesion?.id     || null;

document.getElementById('nombreHeader').textContent = 'Bienvenido/a: ' + nombreUsuario;

function cerrarSesion() { API.sesion.cerrar(); }

// ── Escape HTML ──────────────────────────────────────────
const X = s => { if(s==null)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

// ── Estado ───────────────────────────────────────────────
let reportesData = [];

// ── INIT ─────────────────────────────────────────────────
async function init() {
  await actualizarStats();
}

// ─────────────────────────────────────────────────────────
// REGISTRAR REPORTE → POST /reportes.php
// ─────────────────────────────────────────────────────────
async function registrarReporte(e) {
  e.preventDefault();

  // Buscar el estudiante por nombre para obtener su ID
  const nombreEst  = document.getElementById('estudiante').value.trim();
  const materiaVal = document.getElementById('materia').value.trim();
  const motivoVal  = document.getElementById('motivo').value;
  const obsVal     = document.getElementById('observaciones').value.trim();

  if (!nombreEst || !materiaVal) {
    mostrarMsg('msgNuevo', 'error', '❌ Nombre del estudiante y materia son obligatorios.');
    return;
  }

  try {
    // Buscar estudiante en la BD por nombre
    const estLista = await API.estudiantes.listar({ buscar: nombreEst });
    if (!estLista.length) {
      mostrarMsg('msgNuevo', 'error', `❌ No se encontró ningún estudiante con el nombre "${nombreEst}". Verifícalo con el administrador.`);
      return;
    }

    // Si hay más de uno, tomar el primero que coincida exactamente
    const est = estLista.find(e =>
      (e.nombres + ' ' + e.apellidos).toLowerCase() === nombreEst.toLowerCase()
    ) || estLista[0];

    const payload = {
      estudiante_id: est.id,
      docente_id:    docenteId,
      materia:       materiaVal,
      motivo:        motivoVal,
      observaciones: obsVal,
    };

    await API.reportes.crear(payload);

    mostrarMsg('msgNuevo', 'success', '✅ Reporte registrado correctamente.');
    document.getElementById('formNuevo').reset();
    await actualizarStats();

  } catch (err) {
    mostrarMsg('msgNuevo', 'error', '❌ ' + err.message);
  }
}

// ─────────────────────────────────────────────────────────
// CARGAR TABLA DE REPORTES (del docente actual)
// ─────────────────────────────────────────────────────────
async function cargarTabla() {
  try {
    // Traer reportes del docente actual
    const filtros = docenteId ? { docente_id: docenteId } : {};
    reportesData  = await API.reportes.listar(filtros);
    renderTabla(reportesData);
    await actualizarStats();
  } catch (err) {
    mostrarMsg('msgNuevo', 'error', '❌ No se pudieron cargar los reportes: ' + err.message);
  }
}

function renderTabla(datos) {
  const tbody = document.getElementById('tbodyReportes');
  tbody.innerHTML = '';

  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#aaa;padding:24px">Sin reportes registrados aún.</td></tr>`;
    return;
  }

  const badgeClass = {
    'Reportado':      'badge-reportado',
    'En revisión':    'badge-revision',
    'En seguimiento': 'badge-seguimiento',
    'Remitido':       'badge-remitido',
    'Cerrado':        'badge-cerrado',
  };

  tbody.innerHTML = datos.map((r, i) => `
    <tr>
      <td>${i + 1}</td>
      <td><strong>${X(r.estudiante)}</strong><br>
        <span style="font-size:0.78rem;color:#999">${X(r.documento || '')}</span></td>
      <td>${X(r.materia)}</td>
      <td>${X(r.motivo)}</td>
      <td><span class="badge ${badgeClass[r.estado] || 'badge-reportado'}">${X(r.estado)}</span></td>
      <td style="font-size:0.8rem;color:#888">${X(r.fecha || '')}</td>
      <td>
        <button class="btn btn-secondary btn-sm" onclick="abrirModal(${r.id}, ${JSON.stringify(r).replace(/"/g,'&quot;')})">Editar</button>
      </td>
    </tr>`).join('');
}

function filtrarTabla() {
  const est   = document.getElementById('buscarEstudiante').value.toLowerCase();
  const mat   = document.getElementById('buscarMateria').value.toLowerCase();
  const estado = document.getElementById('filtroEstado').value;

  const f = reportesData.filter(r =>
    (!est   || (r.estudiante || '').toLowerCase().includes(est)) &&
    (!mat   || (r.materia    || '').toLowerCase().includes(mat)) &&
    (!estado || r.estado === estado)
  );
  renderTabla(f);
}

// ─────────────────────────────────────────────────────────
// MODAL EDITAR REPORTE → PUT /reportes.php?id=X
// ─────────────────────────────────────────────────────────
function abrirModal(id, r) {
  document.getElementById('editId').value            = id;
  document.getElementById('editEstudiante').value    = r.estudiante || '';
  document.getElementById('editMateria').value       = r.materia    || '';
  document.getElementById('editMotivo').value        = r.motivo     || '';
  document.getElementById('editObservaciones').value = r.observaciones || '';
  document.getElementById('modalEdicion').classList.add('open');
}

async function guardarEdicion() {
  const id     = document.getElementById('editId').value;
  const datos  = {
    observaciones: document.getElementById('editObservaciones').value,
    motivo:        document.getElementById('editMotivo').value,
  };

  try {
    await API.reportes.actualizar(id, datos);
    cerrarModal();
    await cargarTabla();
    mostrarMsg('msgNuevo', 'success', '✅ Reporte actualizado correctamente.');
  } catch (err) {
    mostrarMsg('msgNuevo', 'error', '❌ ' + err.message);
  }
}

function cerrarModal() {
  document.getElementById('modalEdicion').classList.remove('open');
}

// ─────────────────────────────────────────────────────────
// STATS → GET /reportes.php?docente_id=X
// ─────────────────────────────────────────────────────────
async function actualizarStats() {
  try {
    const filtros = docenteId ? { docente_id: docenteId } : {};
    const r = await API.reportes.listar(filtros);
    document.getElementById('statTotal').textContent       = r.length;
    document.getElementById('statPendientes').textContent  = r.filter(x => x.estado === 'Reportado').length;
    document.getElementById('statSeguimiento').textContent = r.filter(x => x.estado === 'En seguimiento').length;
    document.getElementById('statCerrados').textContent    = r.filter(x => x.estado === 'Cerrado').length;
  } catch {}
}

// ─────────────────────────────────────────────────────────
// Tabs y utilidades
// ─────────────────────────────────────────────────────────
function cambiarTab(nombre, el) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + nombre).classList.add('active');
  el.classList.add('active');
  if (nombre === 'lista') cargarTabla();
}

function limpiarFormulario() {
  document.getElementById('formNuevo').reset();
}

function mostrarMsg(id, tipo, texto) {
  const el = document.getElementById(id);
  el.className = 'msg ' + tipo;
  el.textContent = texto;
  setTimeout(() => el.className = 'msg', 5000);
}

document.getElementById('modalEdicion').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

// Arrancar
init();