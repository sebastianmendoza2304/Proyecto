const nombreUsuario = localStorage.getItem("nombreUsuario") || "Director/a";
document.getElementById("nombreHeader").textContent = "Bienvenido/a: " + nombreUsuario;
 
function cerrarSesion() {
  localStorage.removeItem("nombreUsuario");
  localStorage.removeItem("rolUsuario");
}
 
function obtenerReportes() {
  return JSON.parse(localStorage.getItem("reportes")) || [];
}
 
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;")
    .replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}
 
// ==================================================
// STATE
// ==================================================
let todos = [];
 
function init() {
  todos = obtenerReportes();
  renderTabla(todos);
  actualizarStats(todos);
}
 
function actualizarStats(r) {
  document.getElementById("statTotal").textContent       = r.length;
  document.getElementById("statPendientes").textContent  = r.filter(x => x.estado === "Reportado").length;
  document.getElementById("statEnProceso").textContent   = r.filter(x => ["En revisión","En seguimiento"].includes(x.estado)).length;
  document.getElementById("statCerrados").textContent    = r.filter(x => x.estado === "Cerrado").length;
  const uniq = [...new Set(r.filter(x => x.estado !== "Cerrado").map(x => x.estudiante))];
  document.getElementById("statEstudiantes").textContent = uniq.length;
}
 
// ==================================================
// TABS
// ==================================================
function cambiarTab(nombre, btn) {
  document.querySelectorAll(".tab-panel").forEach(p => p.classList.remove("active"));
  document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
  document.getElementById("tab-" + nombre).classList.add("active");
  btn.classList.add("active");
  if (nombre === "analisis") renderAnalisis();
  if (nombre === "alertas")  renderAlertas();
}
 
// ==================================================
// TABLA DE CASOS
// ==================================================
function badgeClass(estado) {
  return {
    "Reportado":      "badge-reportado",
    "En revisión":    "badge-revision",
    "En seguimiento": "badge-seguimiento",
    "Remitido":       "badge-remitido",
    "Cerrado":        "badge-cerrado"
  }[estado] || "badge-reportado";
}
 
function renderTabla(datos) {
  const tbody = document.getElementById("tbodyCasos");
  if (!datos.length) {
    tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;color:#aaa;padding:24px;">
      Sin casos registrados.</td></tr>`;
    return;
  }
  tbody.innerHTML = datos.map((r, i) => {
    const seg = r.seguimiento;
    return `
      <tr>
        <td>${i + 1}</td>
        <td><strong>${escapeHtml(r.estudiante)}</strong></td>
        <td>${escapeHtml(r.materia)}</td>
        <td>${escapeHtml(r.docente || "N/A")}</td>
        <td style="font-size:0.82rem">${escapeHtml(r.motivo)}</td>
        <td><span class="badge ${badgeClass(r.estado)}">${escapeHtml(r.estado)}</span></td>
        <td>${seg
          ? `<span style="font-size:0.78rem;color:#2e7d32;font-weight:700">✓ ${escapeHtml(seg.intervencion || "Reg.")}</span>`
          : `<span style="font-size:0.78rem;color:#aaa">—</span>`}</td>
        <td style="font-size:0.78rem;color:#888;white-space:nowrap">${escapeHtml(r.fecha || "")}</td>
        <td>
          <button class="btn btn-secondary btn-sm" onclick='verDetalle(${JSON.stringify(r)})'>Ver</button>
        </td>
      </tr>`;
  }).join("");
}
 
function aplicarFiltros() {
  const est    = document.getElementById("fEstudiante").value.toLowerCase();
  const mat    = document.getElementById("fMateria").value.toLowerCase();
  const estado = document.getElementById("fEstado").value;
  const motivo = document.getElementById("fMotivo").value;
  const filtrados = todos.filter(r =>
    (!est    || (r.estudiante || "").toLowerCase().includes(est)) &&
    (!mat    || (r.materia    || "").toLowerCase().includes(mat)) &&
    (!estado || r.estado === estado) &&
    (!motivo || r.motivo === motivo)
  );
  renderTabla(filtrados);
  actualizarStats(filtrados);
}
 
function limpiarFiltros() {
  ["fEstudiante","fMateria","fEstado","fMotivo"].forEach(id => {
    document.getElementById(id).value = "";
  });
  renderTabla(todos);
  actualizarStats(todos);
}
 
// ==================================================
// MODAL DETALLE
// ==================================================
function verDetalle(r) {
  const seg = r.seguimiento;
  document.getElementById("detalleContenido").innerHTML = `
    <div class="detalle-item"><div class="detalle-label">Estudiante</div><div class="detalle-val">${escapeHtml(r.estudiante)}</div></div>
    <div class="detalle-item"><div class="detalle-label">Documento</div><div class="detalle-val">${escapeHtml(r.documento || "—")}</div></div>
    <div class="detalle-item"><div class="detalle-label">Materia</div><div class="detalle-val">${escapeHtml(r.materia)}</div></div>
    <div class="detalle-item"><div class="detalle-label">Docente</div><div class="detalle-val">${escapeHtml(r.docente || "—")}</div></div>
    <div class="detalle-item"><div class="detalle-label">Motivo</div><div class="detalle-val">${escapeHtml(r.motivo)}</div></div>
    <div class="detalle-item"><div class="detalle-label">Estado</div>
      <div class="detalle-val"><span class="badge ${badgeClass(r.estado)}">${escapeHtml(r.estado)}</span></div></div>
    <div class="detalle-item"><div class="detalle-label">Fecha</div><div class="detalle-val">${escapeHtml(r.fecha || "—")}</div></div>
    <div class="detalle-item"><div class="detalle-label">Remitido a</div><div class="detalle-val">${escapeHtml(r.remitido_a || "—")}</div></div>
    <div class="detalle-item full"><div class="detalle-label">Observaciones docente</div>
      <div class="detalle-val">${escapeHtml(r.observaciones || "—")}</div></div>
    ${seg ? `
      <div class="detalle-item full" style="background:#e8f5e9">
        <div class="detalle-label">Seguimiento bienestar (${escapeHtml(seg.fecha || "")})</div>
        <div class="detalle-val" style="line-height:1.8">
          <strong>Intervención:</strong> ${escapeHtml(seg.intervencion || "—")}<br>
          <strong>Observaciones:</strong> ${escapeHtml(seg.observaciones || "—")}<br>
          <strong>Recomendaciones:</strong> ${escapeHtml(seg.recomendaciones || "—")}<br>
          <strong>Profesional:</strong> ${escapeHtml(seg.profesional || "—")}
        </div>
      </div>
    ` : `
      <div class="detalle-item full" style="color:#aaa;font-size:0.88rem">
        Sin seguimiento registrado.
      </div>
    `}
  `;
  document.getElementById("modalDetalle").classList.add("open");
}
 
function cerrarModal() {
  document.getElementById("modalDetalle").classList.remove("open");
}
 
document.getElementById("modalDetalle").addEventListener("click", function(e) {
  if (e.target === this) cerrarModal();
});
 
// ==================================================
// ANÁLISIS (gráficos de barras)
// ==================================================
function barChart(containerId, datos, colorMap) {
  const el = document.getElementById(containerId);
  const entries = Object.entries(datos);
  if (!entries.length) {
    el.innerHTML = `<p class="chart-empty">Sin datos para mostrar.</p>`;
    return;
  }
  const max = Math.max(...Object.values(datos), 1);
  el.innerHTML = entries.map(([label, val]) => `
    <div class="bar-row">
      <span class="bar-label">${escapeHtml(label)}</span>
      <div class="bar-track">
        <div class="bar-fill ${colorMap[label] || "fill-bajo"}" style="width:${Math.round(val / max * 100)}%"></div>
      </div>
      <span class="bar-val">${val}</span>
    </div>`).join("");
}
 
function renderAnalisis() {
  const r = obtenerReportes();
 
  // Por motivo
  const motivos = {};
  r.forEach(x => { motivos[x.motivo] = (motivos[x.motivo] || 0) + 1; });
  barChart("chartMotivo", motivos, {
    "Bajo rendimiento":      "fill-bajo",
    "Inasistencia":          "fill-inasis",
    "Seguimiento preventivo":"fill-seg"
  });
 
  // Por estado
  const estados = {};
  r.forEach(x => { estados[x.estado] = (estados[x.estado] || 0) + 1; });
  barChart("chartEstado", estados, {
    "Reportado":      "fill-reportado",
    "En revisión":    "fill-revision",
    "En seguimiento": "fill-seguim",
    "Remitido":       "fill-remitido",
    "Cerrado":        "fill-cerrado"
  });
 
  // Top 5 materias
  const mats = {};
  r.forEach(x => { mats[x.materia] = (mats[x.materia] || 0) + 1; });
  const top5 = Object.fromEntries(
    Object.entries(mats).sort((a,b) => b[1] - a[1]).slice(0, 5)
  );
  const matColors = {};
  const paleta = ["fill-bajo","fill-inasis","fill-revision","fill-seguim","fill-remitido"];
  Object.keys(top5).forEach((k, i) => { matColors[k] = paleta[i % paleta.length]; });
  barChart("chartMaterias", top5, matColors);
}
 
// ==================================================
// ALERTAS CRÍTICAS
// ==================================================
function renderAlertas() {
  const r = obtenerReportes();
 
  // Sin seguimiento
  const sinSeg = r.filter(x => x.estado === "Reportado" && !x.seguimiento);
  const elCrit = document.getElementById("alertasCriticas");
  if (!sinSeg.length) {
    elCrit.innerHTML = `<p class="vacio-ok">✅ Sin casos críticos pendientes.</p>`;
  } else {
    elCrit.innerHTML = sinSeg.map(r => `
      <div class="alerta-item critica">
        <span class="icono">🚨</span>
        <div class="info">
          <strong style="color:#c62828">${escapeHtml(r.estudiante)}</strong> — ${escapeHtml(r.materia)}
          <small>Motivo: ${escapeHtml(r.motivo)} &nbsp;|&nbsp; Fecha: ${escapeHtml(r.fecha || "N/D")}</small>
        </div>
        <span class="badge badge-reportado">Sin intervención</span>
      </div>`).join("");
  }
 
  // Remitidos
  const remitidos = r.filter(x => x.estado === "Remitido");
  const elRem = document.getElementById("alertasRemitidos");
  if (!remitidos.length) {
    elRem.innerHTML = `<p class="vacio-ok">✅ Sin casos remitidos actualmente.</p>`;
  } else {
    elRem.innerHTML = remitidos.map(r => `
      <div class="alerta-item warning">
        <span class="icono">⚠️</span>
        <div class="info">
          <strong style="color:#7a5500">${escapeHtml(r.estudiante)}</strong> — ${escapeHtml(r.materia)}
          <small>Remitido a: ${escapeHtml(r.remitido_a || "N/D")}
            ${r.motivo_remision ? " &nbsp;|&nbsp; " + escapeHtml(r.motivo_remision) : ""}
          </small>
        </div>
        <span class="badge badge-remitido">Remitido</span>
      </div>`).join("");
  }
}
 
// ==================================================
// EXPORTAR CSV
// ==================================================
function exportarCSV() {
  if (!todos.length) { alert("No hay datos para exportar."); return; }
  const cabecera = ["#","Estudiante","Documento","Materia","Docente","Motivo","Estado","Fecha"];
  const filas = todos.map((r, i) =>
    [i + 1, r.estudiante, r.documento || "", r.materia, r.docente || "N/A",
     r.motivo, r.estado, r.fecha || ""]
      .map(v => `"${String(v).replace(/"/g,'""')}"`).join(",")
  );
  const csv = "\uFEFF" + [cabecera.join(","), ...filas].join("\n");
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const a = document.createElement("a");
  a.href = URL.createObjectURL(blob);
  a.download = "casos_programa_" + new Date().toLocaleDateString("es-CO").replace(/\//g,"-") + ".csv";
  a.click();
}
 
// ==================================================
// INIT
// ==================================================
init();
 