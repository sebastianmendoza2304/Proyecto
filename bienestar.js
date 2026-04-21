const nombreUsuario = localStorage.getItem("nombreUsuario") || "Profesional Bienestar";
document.getElementById("nombreHeader").textContent = "Bienvenido/a: " + nombreUsuario;
 
function cerrarSesion() {
  localStorage.removeItem("nombreUsuario");
  localStorage.removeItem("rolUsuario");
}
 
function obtenerReportes() { return JSON.parse(localStorage.getItem("reportes")) || []; }
function guardarReportes(r) { localStorage.setItem("reportes", JSON.stringify(r)); }
 
let todosLos = [];
 
function init() {
  todosLos = obtenerReportes();
  renderCasos(todosLos);
  actualizarStats(todosLos);
}
 
function actualizarStats(r) {
  document.getElementById("statTotal").textContent      = r.length;
  document.getElementById("statPendientes").textContent = r.filter(x => x.estado === "Reportado").length;
  document.getElementById("statEnProceso").textContent  = r.filter(x => ["En revisión","En seguimiento"].includes(x.estado)).length;
  document.getElementById("statCerrados").textContent   = r.filter(x => x.estado === "Cerrado").length;
}
 
function renderCasos(datos) {
  const tbody = document.getElementById("tbodyCasos");
  if (datos.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:#aaa;padding:24px">Sin casos asignados.</td></tr>`;
    return;
  }
  const bCls = {
    "Reportado":"badge-reportado","En revisión":"badge-revision",
    "En seguimiento":"badge-seguimiento","Remitido":"badge-remitido","Cerrado":"badge-cerrado"
  };
  tbody.innerHTML = datos.map(r => {
    // buscar índice real en storage
    const idx = obtenerReportes().findIndex(x =>
      x.estudiante === r.estudiante && x.materia === r.materia && x.fecha === r.fecha
    );
    return `<tr>
      <td>${idx + 1}</td>
      <td><strong>${escapeHtml(r.estudiante)}</strong></td>
      <td>${escapeHtml(r.materia)}</td>
      <td>${escapeHtml(r.motivo)}</td>
      <td><span class="badge ${bCls[r.estado] || 'badge-reportado'}">${r.estado}</span></td>
      <td style="font-size:0.8rem;color:#888">${r.fecha || ""}</td>
      <td><button class="btn btn-secondary btn-sm" onclick="abrirModal(${idx})">Gestionar</button></td>
    </tr>`;
  }).join("");
}
 
function filtrarCasos() {
  const est    = document.getElementById("buscarEst").value.toLowerCase();
  const mat    = document.getElementById("buscarMat").value.toLowerCase();
  const estado = document.getElementById("filtroEst").value;
  const f = todosLos.filter(r =>
    (!est    || (r.estudiante || "").toLowerCase().includes(est)) &&
    (!mat    || (r.materia    || "").toLowerCase().includes(mat)) &&
    (!estado || r.estado === estado)
  );
  renderCasos(f);
}
 
// --- Modal gestión ---
function abrirModal(idx) {
  const r = obtenerReportes()[idx];
  if (!r) return;
  document.getElementById("modalIdx").value = idx;
  document.getElementById("modalSubtitle").textContent = `#${idx + 1} — ${r.estudiante} | ${r.materia}`;
  document.getElementById("infoCasoModal").innerHTML = `
    <p><strong>Motivo:</strong> ${escapeHtml(r.motivo)}</p>
    <p><strong>Estado actual:</strong> ${escapeHtml(r.estado)}</p>
    <p><strong>Observaciones docente:</strong> ${escapeHtml(r.observaciones || "—")}</p>
  `;
  const estadoValido = ["En revisión","En seguimiento","Cerrado"].includes(r.estado) ? r.estado : "En seguimiento";
  document.getElementById("modalEstado").value = estadoValido;
  document.getElementById("modalObservaciones").value  = r.seguimiento?.observaciones   || "";
  document.getElementById("modalRecomendaciones").value = r.seguimiento?.recomendaciones || "";
  document.getElementById("modalGestion").classList.add("open");
}
 
function guardarGestion() {
  const idx = parseInt(document.getElementById("modalIdx").value);
  const reportes = obtenerReportes();
  if (!reportes[idx]) return;
 
  reportes[idx].estado = document.getElementById("modalEstado").value;
  reportes[idx].seguimiento = {
    fecha:           new Date().toLocaleString("es-CO"),
    intervencion:    document.getElementById("modalIntervencion").value,
    observaciones:   document.getElementById("modalObservaciones").value,
    recomendaciones: document.getElementById("modalRecomendaciones").value,
    profesional:     nombreUsuario
  };
  guardarReportes(reportes);
  todosLos = reportes;
  cerrarModal();
  renderCasos(todosLos);
  actualizarStats(todosLos);
  mostrarMsg("msgCasos", "success", "✅ Caso actualizado correctamente.");
}
 
function cerrarModal() {
  document.getElementById("modalGestion").classList.remove("open");
}
document.getElementById("modalGestion").addEventListener("click", function(e) {
  if (e.target === this) cerrarModal();
});
 
// --- Seguimiento por ID ---
function buscarCaso() {
  const id = parseInt(document.getElementById("segId").value);
  const r  = obtenerReportes()[id - 1];
  const el = document.getElementById("infoCaso");
  if (!r) {
    el.innerHTML = `<p style="color:#c62828">❌ No se encontró un reporte con ID ${id}.</p>`;
    el.style.display = "block";
    return;
  }
  el.innerHTML = `
    <p><strong>Estudiante:</strong> ${escapeHtml(r.estudiante)} &nbsp;|&nbsp; <strong>Materia:</strong> ${escapeHtml(r.materia)}</p>
    <p><strong>Docente:</strong> ${escapeHtml(r.docente || "N/A")} &nbsp;|&nbsp; <strong>Motivo:</strong> ${escapeHtml(r.motivo)}</p>
    <p><strong>Estado:</strong> ${escapeHtml(r.estado)} &nbsp;|&nbsp; <strong>Fecha:</strong> ${escapeHtml(r.fecha || "N/D")}</p>
  `;
  el.style.display = "block";
}
 
function registrarSeguimiento(e) {
  e.preventDefault();
  const id = parseInt(document.getElementById("segId").value);
  const reportes = obtenerReportes();
  if (!reportes[id - 1]) {
    mostrarMsg("msgSeg", "error", "❌ Reporte no encontrado. Verifica el ID.");
    return;
  }
  reportes[id - 1].estado = document.getElementById("segEstado").value;
  reportes[id - 1].seguimiento = {
    fecha:           new Date().toLocaleString("es-CO"),
    intervencion:    document.getElementById("segIntervencion").value,
    observaciones:   document.getElementById("segObservaciones").value,
    recomendaciones: document.getElementById("segRecomendaciones").value,
    profesional:     nombreUsuario
  };
  guardarReportes(reportes);
  todosLos = reportes;
  actualizarStats(todosLos);
  renderCasos(todosLos);
  mostrarMsg("msgSeg", "success", "✅ Seguimiento registrado correctamente.");
  limpiarSeg();
}
 
function limpiarSeg() {
  document.getElementById("formSeguimiento").reset();
  document.getElementById("infoCaso").style.display = "none";
}
 
// --- Utilitarios ---
function cambiarTab(nombre, el) {
  document.querySelectorAll(".tab-panel").forEach(p => p.classList.remove("active"));
  document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
  document.getElementById("tab-" + nombre).classList.add("active");
  el.classList.add("active");
}
 
function mostrarMsg(id, tipo, texto) {
  const el = document.getElementById(id);
  el.className = "msg " + tipo;
  el.textContent = texto;
  setTimeout(() => el.className = "msg", 4000);
}
 
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
 
// Inicializar
init();