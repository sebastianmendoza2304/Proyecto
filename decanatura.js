// DECANATURA
function obtenerReportes() {
  return JSON.parse(localStorage.getItem("reportes")) || [];
}

function generarNotificacionesDecanatura(divId) {
  const reportes = obtenerReportes();
  const div = document.getElementById(divId);
  div.innerHTML = "";
  reportes.forEach(r => {
    if(r.estado === "Reportado") {
      div.innerHTML += `<p>Nuevo caso reportado: ${r.estudiante} - ${r.materia}</p>`;
    }
    if(r.estado === "Cerrado") {
      div.innerHTML += `<p>Caso cerrado: ${r.estudiante} - ${r.materia}</p>`;
    }
  });
}

function filtrarReportesDecanatura(tablaId, materia, estudiante, estado) {
  let reportes = obtenerReportes();
  if(materia) reportes = reportes.filter(r => r.materia.toLowerCase().includes(materia.toLowerCase()));
  if(estudiante) reportes = reportes.filter(r => r.estudiante.toLowerCase().includes(estudiante.toLowerCase()));
  if(estado) reportes = reportes.filter(r => r.estado === estado);

  const tabla = document.getElementById(tablaId);
  tabla.innerHTML = `
    <tr>
      <th>ID</th><th>Estudiante</th><th>Materia</th>
      <th>Docente</th><th>Estado</th><th>Fecha</th>
    </tr>`;
  reportes.forEach((r, i) => {
    const fila = document.createElement("tr");
    fila.innerHTML = `
      <td>${i+1}</td><td>${r.estudiante}</td><td>${r.materia}</td>
      <td>${r.docente || "N/A"}</td><td>${r.estado}</td><td>${r.fecha}</td>`;
    tabla.appendChild(fila);
  });
}