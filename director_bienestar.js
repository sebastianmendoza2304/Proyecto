// DIRECTOR DE BIENESTAR
function obtenerReportes() {
  return JSON.parse(localStorage.getItem("reportes")) || [];
}

function guardarReportes(reportes) {
  localStorage.setItem("reportes", JSON.stringify(reportes));
}

function actualizarEstadoDirector(id, nuevoEstado, remitido, motivo) {
  let reportes = obtenerReportes();
  if(reportes[id-1]) {
    reportes[id-1].estado = nuevoEstado;
    reportes[id-1].remitido_a = remitido;
    reportes[id-1].motivo_remision = motivo;
    guardarReportes(reportes);
    alert("Caso actualizado correctamente por Director de Bienestar.");
  } else {
    alert("Reporte no encontrado.");
  }
}