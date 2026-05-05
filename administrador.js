// ============================================================
// admin.js — Panel administrador conectado a la BD
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

const sesion        = API.sesion.obtener();
const nombreUsuario = sesion?.nombre || 'Admin';

document.getElementById('hdrNombre').textContent = 'Bienvenido/a: ' + nombreUsuario;

function logout() { API.sesion.cerrar(); }

const X = s => { if(s==null)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

function msg(id, tipo, txt) {
  const el = document.getElementById(id);
  el.className = 'alerta ' + tipo;
  el.textContent = txt;
  setTimeout(() => el.className = 'alerta', 5000);
}

function cerrarModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if(e.target === m) m.classList.remove('open'); })
);

// ─────────────────────────────────────────────────────────
// TABS
// ─────────────────────────────────────────────────────────
function tab(nombre, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tp-' + nombre).classList.add('active');
  btn.classList.add('active');
  if (nombre === 'usuarios')    cargarUsuarios();
  if (nombre === 'estudiantes') cargarEstudiantes();
  if (nombre === 'reportes')    cargarReportes();
}

function miniTab(id, grupo, btn) {
  document.querySelectorAll(`#tp-${grupo} .mini-panel`).forEach(p => p.classList.remove('active'));
  document.querySelectorAll(`#tp-${grupo} .mini-tab`).forEach(b => b.classList.remove('active'));
  document.getElementById(id).classList.add('active');
  btn.classList.add('active');
  if (id === 'u-lista') cargarUsuarios();
  if (id === 'e-lista') cargarEstudiantes();
}

// ─────────────────────────────────────────────────────────
// STATS
// ─────────────────────────────────────────────────────────
async function actualizarStats() {
  try {
    const [usrs, ests, reps] = await Promise.all([
      API.usuarios.listar(),
      API.estudiantes.listar(),
      API.reportes.listar(),
    ]);
    document.getElementById('stUsuarios').textContent    = usrs.length;
    document.getElementById('stEstudiantes').textContent = ests.length;
    document.getElementById('stReportes').textContent    = reps.length;
    document.getElementById('stPendientes').textContent  = reps.filter(r => r.estado === 'Reportado').length;
  } catch {}
}

// ─────────────────────────────────────────────────────────
// Helpers de roles
// ─────────────────────────────────────────────────────────
const ROLES = {
  administrador:      { label:'Administrador',   cls:'b-admin',  av:'av-admin'  },
  docente:            { label:'Docente',          cls:'b-docente',av:'av-docente'},
  bienestar:          { label:'Bienestar',        cls:'b-bien',   av:'av-bien'   },
  director_bienestar: { label:'Dir. Bienestar',   cls:'b-dir',    av:'av-dir'    },
  decanatura:         { label:'Decanatura',       cls:'b-dec',    av:'av-dec'    },
};
const rolLabel = r => ROLES[r]?.label || r;
const rolCls   = r => ROLES[r]?.cls    || 'b-admin';
const avCls    = r => ROLES[r]?.av     || 'av-admin';
const iniciales= n => (n||'??').substring(0,2).toUpperCase();

// ═══════════════════════════════════════════════════════════
// USUARIOS
// ═══════════════════════════════════════════════════════════
let usrData = [];

async function crearUsuario(e) {
  e.preventDefault();
  const nombre = document.getElementById('u_nombre').value.trim();
  const pass   = document.getElementById('u_pass').value;
  const pass2  = document.getElementById('u_pass2').value;

  if (pass !== pass2) { msg('msgUsr','err','❌ Las contraseñas no coinciden.'); return; }

  try {
    await API.usuarios.crear({
      nombre,
      password: pass,
      correo:   document.getElementById('u_correo').value.trim(),
      rol:      document.getElementById('u_rol').value,
    });
    msg('msgUsr','ok','✅ Usuario registrado correctamente.');
    document.getElementById('frmUsr').reset();
    await actualizarStats();
  } catch (err) {
    msg('msgUsr','err','❌ ' + err.message);
  }
}

async function cargarUsuarios() {
  try {
    usrData = await API.usuarios.listar();
    renderUsuarios(usrData);
  } catch (err) {
    msg('msgUsrLista','err','❌ ' + err.message);
  }
}

function filtrarUsuarios() {
  const nom  = document.getElementById('buNom').value.toLowerCase();
  const rol  = document.getElementById('buRol').value;
  const act  = document.getElementById('buAct').value;
  renderUsuarios(usrData.filter(u =>
    (!nom || u.nombre.toLowerCase().includes(nom)) &&
    (!rol || u.rol === rol) &&
    (act === '' || String(u.activo) === act)
  ));
}

function renderUsuarios(datos) {
  const tbody = document.getElementById('tbodyUsr');
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="sin-datos">Sin usuarios registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map((u,i) => `
    <tr>
      <td>${i+1}</td>
      <td><div class="td-user">
        <span class="avatar ${avCls(u.rol)}">${iniciales(u.nombre)}</span>
        <strong>${X(u.nombre)}</strong>
      </div></td>
      <td><span class="badge ${rolCls(u.rol)}">${rolLabel(u.rol)}</span></td>
      <td style="font-size:.83rem;color:var(--muted)">${X(u.correo||'—')}</td>
      <td><span class="badge ${u.activo?'b-activo':'b-inactivo'}">${u.activo?'Activo':'Inactivo'}</span></td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-secondary btn-icon" onclick="abrirEditUsr(${u.id})">✏️ Editar</button>
        <button class="btn btn-danger btn-icon" onclick="abrirEliminar('usr',${u.id},'${u.nombre.replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('');
}

async function abrirEditUsr(id) {
  try {
    const u = await API.usuarios.obtener(id);
    document.getElementById('eu_idx').value    = id;
    document.getElementById('eu_rol').value    = u.rol;
    document.getElementById('eu_nombre').value = u.nombre;
    document.getElementById('eu_correo').value = u.correo || '';
    document.getElementById('eu_pass').value   = '';
    document.getElementById('eu_activo').value = u.activo ? '1' : '0';
    document.getElementById('modalEditUsr').classList.add('open');
  } catch (err) { alert('Error: ' + err.message); }
}

async function guardarUsuario() {
  const id    = document.getElementById('eu_idx').value;
  const datos = {
    rol:    document.getElementById('eu_rol').value,
    nombre: document.getElementById('eu_nombre').value.trim(),
    correo: document.getElementById('eu_correo').value.trim(),
    activo: parseInt(document.getElementById('eu_activo').value),
  };
  const newPass = document.getElementById('eu_pass').value;
  if (newPass) datos.password = newPass;

  try {
    await API.usuarios.actualizar(id, datos);
    cerrarModal('modalEditUsr');
    await cargarUsuarios();
    await actualizarStats();
    msg('msgUsrLista','ok','✅ Usuario actualizado correctamente.');
  } catch (err) {
    msg('msgUsrLista','err','❌ ' + err.message);
  }
}

// ═══════════════════════════════════════════════════════════
// ESTUDIANTES
// ═══════════════════════════════════════════════════════════
let estData = [];

async function crearEstudiante(e) {
  e.preventDefault();
  try {
    await API.estudiantes.crear({
      documento:  document.getElementById('e_doc').value.trim(),
      nombres:    document.getElementById('e_nom').value.trim(),
      apellidos:  document.getElementById('e_ape').value.trim(),
      carrera:    document.getElementById('e_car').value.trim(),
      semestre:   document.getElementById('e_sem').value,
      periodo:    document.getElementById('e_per').value.trim(),
      telefono:   document.getElementById('e_tel').value.trim(),
      correo:     document.getElementById('e_cor').value.trim(),
    });
    msg('msgEst','ok','✅ Estudiante registrado correctamente.');
    document.getElementById('frmEst').reset();
    await actualizarStats();
  } catch (err) {
    msg('msgEst','err','❌ ' + err.message);
  }
}

async function cargarEstudiantes() {
  try {
    estData = await API.estudiantes.listar();
    renderEstudiantes(estData);
  } catch (err) {
    msg('msgEstLista','err','❌ ' + err.message);
  }
}

function filtrarEstudiantes() {
  const nom  = document.getElementById('beNom').value.toLowerCase();
  const car  = document.getElementById('beCar').value.toLowerCase();
  const est2 = document.getElementById('beEst').value;
  renderEstudiantes(estData.filter(e =>
    (!nom  || (e.nombres+' '+e.apellidos).toLowerCase().includes(nom) || (e.documento||'').includes(nom)) &&
    (!car  || (e.carrera||'').toLowerCase().includes(car)) &&
    (!est2 || e.estado === est2)
  ));
}

function renderEstudiantes(datos) {
  const tbody = document.getElementById('tbodyEst');
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="sin-datos">Sin estudiantes registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map((e,i) => `
    <tr>
      <td>${i+1}</td>
      <td><div class="td-user">
        <span class="avatar av-est">${iniciales(e.nombres)}</span>
        <strong>${X(e.nombres)} ${X(e.apellidos)}</strong>
      </div></td>
      <td style="font-size:.83rem">${X(e.documento)}</td>
      <td style="font-size:.83rem">${X(e.carrera)}</td>
      <td style="text-align:center">${e.semestre ? e.semestre+'°' : '—'}</td>
      <td style="font-size:.8rem;color:var(--muted)">
        ${e.telefono ? '📞 '+X(e.telefono)+'<br>':''}
        ${e.correo   ? '✉️ '+X(e.correo):'—'}
      </td>
      <td><span class="badge ${e.estado==='Activo'?'b-est-act':'b-est-ina'}">${X(e.estado)}</span></td>
      <td style="display:flex;gap:6px;flex-wrap:wrap">
        <button class="btn btn-secondary btn-icon" onclick="abrirEditEst(${e.id})">✏️ Editar</button>
        <button class="btn btn-danger btn-icon" onclick="abrirEliminar('est',${e.id},'${(e.nombres+' '+e.apellidos).replace(/'/g,"\\'")}')">🗑️</button>
      </td>
    </tr>`).join('');
}

async function abrirEditEst(id) {
  try {
    const e = await API.estudiantes.obtener(id);
    document.getElementById('ee_idx').value = id;
    document.getElementById('ee_nom').value = e.nombres   || '';
    document.getElementById('ee_ape').value = e.apellidos || '';
    document.getElementById('ee_car').value = e.carrera   || '';
    document.getElementById('ee_sem').value = e.semestre  || '';
    document.getElementById('ee_tel').value = e.telefono  || '';
    document.getElementById('ee_cor').value = e.correo    || '';
    document.getElementById('ee_est').value = e.estado    || 'Activo';
    document.getElementById('modalEditEst').classList.add('open');
  } catch (err) { alert('Error: ' + err.message); }
}

async function guardarEstudiante() {
  const id = document.getElementById('ee_idx').value;
  try {
    await API.estudiantes.actualizar(id, {
      nombres:   document.getElementById('ee_nom').value.trim(),
      apellidos: document.getElementById('ee_ape').value.trim(),
      carrera:   document.getElementById('ee_car').value.trim(),
      semestre:  document.getElementById('ee_sem').value,
      telefono:  document.getElementById('ee_tel').value.trim(),
      correo:    document.getElementById('ee_cor').value.trim(),
      estado:    document.getElementById('ee_est').value,
    });
    cerrarModal('modalEditEst');
    await cargarEstudiantes();
    await actualizarStats();
    msg('msgEstLista','ok','✅ Estudiante actualizado correctamente.');
  } catch (err) {
    msg('msgEstLista','err','❌ ' + err.message);
  }
}

// ═══════════════════════════════════════════════════════════
// ELIMINAR (compartido)
// ═══════════════════════════════════════════════════════════
let _delTipo = null, _delId = null;

function abrirEliminar(tipo, id, nombre) {
  _delTipo = tipo; _delId = id;
  document.getElementById('del_nombre').textContent = nombre;
  document.getElementById('modalEliminar').classList.add('open');
}

async function confirmarEliminar() {
  try {
    if (_delTipo === 'usr') {
      await API.usuarios.eliminar(_delId);
      cerrarModal('modalEliminar');
      await cargarUsuarios();
      msg('msgUsrLista','ok','🗑️ Usuario desactivado.');
    } else {
      await API.estudiantes.eliminar(_delId);
      cerrarModal('modalEliminar');
      await cargarEstudiantes();
      msg('msgEstLista','ok','🗑️ Estudiante eliminado.');
    }
    await actualizarStats();
  } catch (err) {
    cerrarModal('modalEliminar');
    alert('❌ ' + err.message);
  }
}

// ═══════════════════════════════════════════════════════════
// REPORTES (solo lectura para admin)
// ═══════════════════════════════════════════════════════════
let repData = [];

async function cargarReportes() {
  try {
    repData = await API.reportes.listar();
    renderReportes(repData);
  } catch (err) {
    console.error(err);
  }
}

function filtrarReportes() {
  const est  = document.getElementById('brEst').value.toLowerCase();
  const mat  = document.getElementById('brMat').value.toLowerCase();
  const est2 = document.getElementById('brEst2').value;
  renderReportes(repData.filter(r =>
    (!est  || (r.estudiante||'').toLowerCase().includes(est)) &&
    (!mat  || (r.materia   ||'').toLowerCase().includes(mat)) &&
    (!est2 || r.estado === est2)
  ));
}

function limpiarRep() {
  ['brEst','brMat'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('brEst2').value = '';
  renderReportes(repData);
}

const badgeCls = e => ({
  'Reportado':'b-reportado','En revisión':'b-revision',
  'En seguimiento':'b-seguimiento','Remitido':'b-remitido','Cerrado':'b-cerrado'
}[e]||'b-reportado');

function renderReportes(datos) {
  const tbody = document.getElementById('tbodyRep');
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="8" class="sin-datos">Sin reportes registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map((r,i) => `
    <tr>
      <td>${i+1}</td>
      <td><strong>${X(r.estudiante)}</strong></td>
      <td>${X(r.materia)}</td>
      <td>${X(r.docente||'N/A')}</td>
      <td>${X(r.motivo)}</td>
      <td><span class="badge ${badgeCls(r.estado)}">${X(r.estado)}</span></td>
      <td style="font-size:.8rem;color:var(--muted)">${X(r.fecha||'')}</td>
      <td><button class="btn btn-secondary btn-icon" onclick="verReporte(${r.id})">Ver</button></td>
    </tr>`).join('');
}

async function verReporte(id) {
  try {
    const r = await API.reportes.obtener(id);
    const seg = r.seguimientos && r.seguimientos.length ? r.seguimientos[r.seguimientos.length-1] : null;
    document.getElementById('repDetContenido').innerHTML = `
      <table style="width:100%;border-collapse:collapse;font-size:.88rem">
        <tr><td style="padding:5px 0;color:var(--muted);width:38%"><strong>Estudiante</strong></td><td>${X(r.estudiante)}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Documento</strong></td><td>${X(r.documento||'—')}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Materia</strong></td><td>${X(r.materia)}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Docente</strong></td><td>${X(r.docente||'—')}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Motivo</strong></td><td>${X(r.motivo)}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Estado</strong></td>
          <td><span class="badge ${badgeCls(r.estado)}">${X(r.estado)}</span></td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Fecha</strong></td><td>${X(r.fecha||'—')}</td></tr>
        <tr><td style="padding:5px 0;color:var(--muted)"><strong>Observaciones</strong></td><td>${X(r.observaciones||'—')}</td></tr>
        ${r.remitido_a ? `<tr><td style="padding:5px 0;color:var(--muted)"><strong>Remitido a</strong></td><td>${X(r.remitido_a)}</td></tr>` : ''}
      </table>
      ${seg ? `<div style="background:#e8f5e9;border-radius:8px;padding:14px;margin-top:16px">
        <p style="font-weight:700;color:#1b5e20;font-size:.85rem;margin-bottom:8px">Seguimiento — ${X(seg.fecha||'')}</p>
        <p><strong>Intervención:</strong> ${X(seg.intervencion||'—')}</p>
        <p><strong>Observaciones:</strong> ${X(seg.observaciones||'—')}</p>
        <p><strong>Recomendaciones:</strong> ${X(seg.recomendaciones||'—')}</p>
        <p><strong>Profesional:</strong> ${X(seg.profesional||'—')}</p>
      </div>` : `<p style="color:var(--muted);font-size:.85rem;margin-top:14px">Sin seguimiento registrado.</p>`}
    `;
    document.getElementById('modalRepDet').classList.add('open');
  } catch (err) { alert('Error: ' + err.message); }
}

function exportarCSV() {
  if (!repData.length) { alert('No hay datos para exportar.'); return; }
  const hdr  = ['#','Estudiante','Documento','Materia','Docente','Motivo','Estado','Fecha'];
  const rows = repData.map((r,i) =>
    [i+1,r.estudiante,r.documento||'',r.materia,r.docente||'N/A',r.motivo,r.estado,r.fecha||'']
    .map(v=>`"${String(v).replace(/"/g,'""')}"`).join(','));
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['\uFEFF'+[hdr.join(','),...rows].join('\n')],{type:'text/csv;charset=utf-8;'}));
  a.download = 'reportes_admin_' + new Date().toLocaleDateString('es-CO').replace(/\//g,'-') + '.csv';
  a.click();
}

// ── Init ──
actualizarStats();