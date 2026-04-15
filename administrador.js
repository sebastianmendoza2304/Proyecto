// ADMINISTRADOR
function registrarUsuario(tipo, datos) {
  let usuarios = JSON.parse(localStorage.getItem("usuarios")) || [];
  usuarios.push({ tipo, ...datos });
  localStorage.setItem("usuarios", JSON.stringify(usuarios));
  alert(`${tipo} registrado correctamente.`);
}