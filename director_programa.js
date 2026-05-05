// ============================================================
// director_programa.js — Panel Director de Programa con BD
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

const sesion        = API.sesion.obtener();
const nombreUsuario = sesion?.nombre || 'Director/a';
document.getElementById('hdrNombre').textContent = 'Bienvenido/a: ' + nombreUsuario;

function logout() { API.sesion.cerrar(); }

const X = s => { if(s==null)return''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); };

const BADGE = {
  'Reportado':'badge-reportado','En revisión':'badge-revision',
  'En seguimiento':'badge-seguimiento','Remitido':'badge-remitido','Cerrado':'badge-cerrado'
};

let todos = [];

// ─────────────────────────────────────────────────────────
// INIT — carga reportes desde la BD
// ─────────────────────────────────────────────────────────
async function init() {
  try {
    todos = await API.reportes.listar();
    renderTabla(todos);
    actualizarStats(todos);
  } catch (err) {
    console.error('Error cargando reportes:', err);
  }
}

function actualizarStats(r) {
  document.getElementById('stTotal').textContent = r.length;
  document.getElementById('stPend').textContent  = r.filter(x => x.estado === 'Reportado').length;
  document.getElementById('stProc').textContent  = r.filter(x => ['En revisión','En seguimiento'].includes(x.estado)).length;
  document.getElementById('stCerr').textContent  = r.filter(x => x.estado === 'Cerrado').length;
  const uniq = [...new Set(r.map(x => x.estudiante))];
  document.getElementById('stEst').textContent   = uniq.length;
}

// ─────────────────────────────────────────────────────────
// TABS
// ─────────────────────────────────────────────────────────
function tab(nombre, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tp-' + nombre).classList.add('active');
  btn.classList.add('active');
  if (nombre === 'analisis') renderAnalisis();
  if (nombre === 'alertas')  renderAlertas();
}

function cerrarModal() { document.getElementById('modalDet').classList.remove('open'); }
document.getElementById('modalDet').addEventListener('click', e => {
  if (e.target.id === 'modalDet') cerrarModal();
});

function badgeCls(e) { return BADGE[e] || 'b-reportado'; }

// ─────────────────────────────────────────────────────────
// TABLA
// ─────────────────────────────────────────────────────────
function renderTabla(datos) {
  const tbody = document.getElementById('tbodyCasos');
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="sin-datos">Sin casos registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map((r, i) => {
    const seg = r.seguimientos && r.seguimientos.length ? r.seguimientos[r.seguimientos.length-1] : null;
    return `
      <tr>
        <td>${i+1}</td>
        <td><strong>${X(r.estudiante)}</strong></td>
        <td>${X(r.materia)}</td>
        <td>${X(r.docente||'N/A')}</td>
        <td style="font-size:.8rem">${X(r.motivo)}</td>
        <td><span class="badge ${badgeCls(r.estado)}">${X(r.estado)}</span></td>
        <td>${seg
          ? `<span style="font-size:.76rem;color:var(--verde);font-weight:700">✓ ${X(seg.intervencion||'Reg.')}</span>`
          : `<span style="font-size:.76rem;color:var(--muted)">—</span>`}</td>
        <td style="font-size:.77rem;color:var(--muted);white-space:nowrap">${X(r.fecha||'')}</td>
        <td><button class="btn btn-secondary btn-icon" onclick="verDetalle(${r.id})">Ver</button></td>
      </tr>`;
  }).join('');
}

function filtrar() {
  const est  = document.getElementById('bEst').value.toLowerCase();
  const mat  = document.getElementById('bMat').value.toLowerCase();
  const est2 = document.getElementById('bEst2').value;
  const mot  = document.getElementById('bMot').value;
  renderTabla(todos.filter(r =>
    (!est  || (r.estudiante||'').toLowerCase().includes(est)) &&
    (!mat  || (r.materia   ||'').toLowerCase().includes(mat)) &&
    (!est2 || r.estado === est2) &&
    (!mot  || r.motivo === mot)
  ));
}

function limpiarF() {
  ['bEst','bMat'].forEach(id => document.getElementById(id).value = '');
  ['bEst2','bMot'].forEach(id => document.getElementById(id).value = '');
  renderTabla(todos);
}

// ─────────────────────────────────────────────────────────
// DETALLE del caso
// ─────────────────────────────────────────────────────────
async function verDetalle(id) {
  try {
    const r   = await API.reportes.obtener(id);
    const seg = r.seguimientos && r.seguimientos.length ? r.seguimientos[r.seguimientos.length-1] : null;

    document.getElementById('detContenido').innerHTML = `
      <div class="det-item"><div class="det-lbl">Estudiante</div><div class="det-val">${X(r.estudiante)}</div></div>
      <div class="det-item"><div class="det-lbl">Documento</div><div class="det-val">${X(r.documento||'—')}</div></div>
      <div class="det-item"><div class="det-lbl">Materia</div><div class="det-val">${X(r.materia)}</div></div>
      <div class="det-item"><div class="det-lbl">Docente</div><div class="det-val">${X(r.docente||'—')}</div></div>
      <div class="det-item"><div class="det-lbl">Motivo</div><div class="det-val">${X(r.motivo)}</div></div>
      <div class="det-item"><div class="det-lbl">Estado</div>
        <div class="det-val"><span class="badge ${badgeCls(r.estado)}">${X(r.estado)}</span></div></div>
      <div class="det-item"><div class="det-lbl">Fecha</div><div class="det-val">${X(r.fecha||'—')}</div></div>
      <div class="det-item"><div class="det-lbl">Remitido a</div><div class="det-val">${X(r.remitido_a||'—')}</div></div>
      <div class="det-item span2"><div class="det-lbl">Observaciones docente</div>
        <div class="det-val">${X(r.observaciones||'—')}</div></div>
      ${seg ? `<div class="det-seg span2">
        <div class="det-lbl">Seguimiento — ${X(seg.fecha||'')}</div>
        <div class="det-val" style="margin-top:6px;line-height:1.8">
          <strong>Intervención:</strong> ${X(seg.intervencion||'—')}<br>
          <strong>Observaciones:</strong> ${X(seg.observaciones||'—')}<br>
          <strong>Recomendaciones:</strong> ${X(seg.recomendaciones||'—')}<br>
          <strong>Profesional:</strong> ${X(seg.profesional||'—')}
        </div>
      </div>` : `<div class="det-item span2" style="color:var(--muted);font-size:.85rem">Sin seguimiento registrado.</div>`}
    `;
    document.getElementById('modalDet').classList.add('open');
  } catch (err) { alert('Error cargando detalle: ' + err.message); }
}

// ─────────────────────────────────────────────────────────
// ANÁLISIS
// ─────────────────────────────────────────────────────────
function barChart(containerId, datos, colorMap) {
  const el  = document.getElementById(containerId);
  const max = Math.max(...Object.values(datos), 1);
  el.innerHTML = Object.entries(datos).map(([label, val]) => `
    <div class="bar-row">
      <span class="bar-label">${X(label)}</span>
      <div class="bar-track">
        <div class="bar-fill ${colorMap[label]||'fill-bajo'}" style="width:${Math.round(val/max*100)}%"></div>
      </div>
      <span class="bar-val">${val}</span>
    </div>`).join('');
}

function renderAnalisis() {
  const motivos = {};
  todos.forEach(x => { motivos[x.motivo] = (motivos[x.motivo]||0)+1; });
  barChart('chartMotivo', motivos, {
    'Bajo rendimiento':'fill-bajo','Inasistencia':'fill-inasis','Seguimiento preventivo':'fill-seg'
  });

  const estados = {};
  todos.forEach(x => { estados[x.estado] = (estados[x.estado]||0)+1; });
  barChart('chartEstado', estados, {
    'Reportado':'fill-reportado','En revisión':'fill-revision',
    'En seguimiento':'fill-seguim','Remitido':'fill-remitido','Cerrado':'fill-cerrado'
  });

  const mats = {};
  todos.forEach(x => { mats[x.materia] = (mats[x.materia]||0)+1; });
  const top5 = Object.fromEntries(Object.entries(mats).sort((a,b)=>b[1]-a[1]).slice(0,5));
  const matColors = {};
  Object.keys(top5).forEach((k,i) => {
    matColors[k] = ['fill-bajo','fill-inasis','fill-revision','fill-seguim','fill-remitido'][i%5];
  });
  barChart('chartMaterias', top5, matColors);
}

// ─────────────────────────────────────────────────────────
// ALERTAS
// ─────────────────────────────────────────────────────────
function renderAlertas() {
  const sinSeg   = todos.filter(x => x.estado === 'Reportado' && !(x.seguimientos && x.seguimientos.length));
  const elCrit   = document.getElementById('alertasCriticas');
  if (!sinSeg.length) {
    elCrit.innerHTML = `<p style="color:var(--verde);font-weight:700;font-size:.88rem">✅ Sin casos críticos pendientes.</p>`;
  } else {
    elCrit.innerHTML = sinSeg.map(r => `
      <div style="background:var(--rojo-bg);border:1px solid var(--rojo-borde);border-radius:8px;
        padding:13px 16px;margin-bottom:10px;display:flex;align-items:center;gap:12px">
        <span style="font-size:1.3rem">🚨</span>
        <div style="flex:1">
          <strong style="color:var(--rojo)">${X(r.estudiante)}</strong>
          <span style="font-size:.83rem;color:var(--text-sec)"> — ${X(r.materia)}</span><br>
          <span style="font-size:.8rem;color:var(--text-sec)">
            Motivo: ${X(r.motivo)} | Fecha: ${X(r.fecha||'N/D')}
          </span>
        </div>
        <span class="badge b-reportado">Sin intervención</span>
      </div>`).join('');
  }

  const remitidos = todos.filter(x => x.estado === 'Remitido');
  const elRem     = document.getElementById('alertasRemitidos');
  if (!remitidos.length) {
    elRem.innerHTML = `<p style="color:var(--verde);font-weight:700;font-size:.88rem">✅ Sin casos remitidos actualmente.</p>`;
  } else {
    elRem.innerHTML = remitidos.map(r => `
      <div style="background:var(--warn-bg);border:1px solid var(--warn-borde);border-radius:8px;
        padding:13px 16px;margin-bottom:10px;display:flex;align-items:center;gap:12px">
        <span style="font-size:1.3rem">⚠️</span>
        <div style="flex:1">
          <strong style="color:#7a5500">${X(r.estudiante)}</strong>
          <span style="font-size:.83rem;color:var(--text-sec)"> — ${X(r.materia)}</span><br>
          <span style="font-size:.8rem;color:var(--text-sec)">
            Remitido a: ${X(r.remitido_a||'N/D')} ${r.motivo_remision ? ' | '+X(r.motivo_remision) : ''}
          </span>
        </div>
        <span class="badge b-remitido">Remitido</span>
      </div>`).join('');
  }
}

// ─────────────────────────────────────────────────────────
// EXPORTAR CSV
// ─────────────────────────────────────────────────────────
function exportarCSV() {
  if (!todos.length) { alert('No hay datos.'); return; }
  const hdr  = ['#','Estudiante','Materia','Docente','Motivo','Estado','Fecha'];
  const rows = todos.map((r,i) =>
    [i+1,r.estudiante,r.materia,r.docente||'N/A',r.motivo,r.estado,r.fecha||'']
    .map(v=>`"${String(v).replace(/"/g,'""')}"`).join(','));
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob(['\uFEFF'+[hdr.join(','),...rows].join('\n')],{type:'text/csv;charset=utf-8;'}));
  a.download = 'casos_programa_' + new Date().toLocaleDateString('es-CO').replace(/\//g,'-') + '.csv';
  a.click();
}

// Arrancar
init();