// BIENESTAR (Profesional)
function obtenerReportes() {
  return JSON.parse(localStorage.getItem("reportes")) || [];
}

function guardarReportes(reportes) {
  localStorage.setItem("reportes", JSON.stringify(reportes));
}

function actualizarEstadoBienestar(id, nuevoEstado) {
  let reportes = obtenerReportes();
  if(reportes[id-1]) {
    reportes[id-1].estado = nuevoEstado;
    guardarReportes(reportes);
    alert("Estado actualizado correctamente.");
  } else {
    alert("Reporte no encontrado.");
  }
}

function registrarSeguimiento(id, intervencion, observaciones, recomendaciones, profesional) {
  let reportes = obtenerReportes();
  if(reportes[id-1]) {
    const seguimiento = {
      fecha: new Date().toLocaleString(),
      intervencion,
      observaciones,
      recomendaciones,
      profesional
    };
    reportes[id-1].seguimiento = seguimiento;
    reportes[id-1].estado = "En seguimiento";
    guardarReportes(reportes);
    alert("Seguimiento registrado correctamente.");
  } else {
    alert("Reporte no encontrado.");
  }
}