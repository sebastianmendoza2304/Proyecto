// DOCENTE
function obtenerReportes() {
  return JSON.parse(localStorage.getItem("reportes")) || [];
}

function guardarReportes(reportes) {
  localStorage.setItem("reportes", JSON.stringify(reportes));
}

function registrarReporte(estudiante, materia, motivo, observaciones, docente) {
  let reportes = obtenerReportes();
  const nuevoReporte = {
    estudiante,
    materia,
    motivo,
    observaciones,
    docente,
    fecha: new Date().toLocaleString(),
    estado: "Reportado"
  };
  reportes.push(nuevoReporte);
  guardarReportes(reportes);
  alert("Reporte registrado correctamente.");
}

function mostrarReportesDocente(tablaId) {
  const reportes = obtenerReportes();
  const tabla = document.getElementById(tablaId);
  tabla.innerHTML = `
    <tr>
      <th>ID</th><th>Estudiante</th><th>Materia</th>
      <th>Motivo</th><th>Estado</th><th>Fecha</th>
    </tr>`;
  reportes.forEach((r, i) => {
    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${i+1}</td><td>${r.estudiante}</td><td>${r.materia}</td>
      <td>${r.motivo}</td><td>${r.estado}</td><td>${r.fecha}</td>`;
    tabla.appendChild(fila);
  });
}