// Usuarios simulados para login
const usuarios = [
  { nombre: "admin", password: "admin123", rol: "administrador.html" },
  { nombre: "docente1", password: "1234", rol: "docentes.html" },
  { nombre: "bienestar1", password: "xyz", rol: "bienestar.html" },
  { nombre: "bienestar_director", password: "12345", rol: "director_bienestar.html" },
  { nombre: "decano1", password: "abcd", rol: "decanatura.html" },
  { nombre: "director1", password: "321", rol: "director_programa.html" }
];

// Validar usuario y redirigir
function validarUsuario(event) {
  event.preventDefault();
  const usuario = document.getElementById("usuario").value;
  const password = document.getElementById("password").value;

  const encontrado = usuarios.find(u => u.nombre === usuario && u.password === password);

  if (encontrado) {
    localStorage.setItem("nombreUsuario", encontrado.nombre);
    localStorage.setItem("rolUsuario", encontrado.rol);
    window.location.href = encontrado.rol;
  } else {
    alert("Usuario o contraseña incorrectos.");
  }
}
