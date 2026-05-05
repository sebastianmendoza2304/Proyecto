// ============================================================
// index.js — Login conectado a login.php (base de datos)
// Sistema de Alertas por Riesgo Académico - Universidad Libre
// ============================================================

async function validarUsuario(event) {
  event.preventDefault();

  const btnSubmit = document.querySelector('button[type="submit"]');
  const usuario   = document.getElementById('usuario').value.trim();
  const password  = document.getElementById('password').value;

  if (!usuario || !password) {
    mostrarError('Por favor completa todos los campos.');
    return;
  }

  // Indicador de carga
  btnSubmit.textContent = 'Verificando...';
  btnSubmit.disabled    = true;

  try {
    // Llama a login.php que verifica contra la base de datos
    const datos = await API.login(usuario, password);

    // Guardar sesión en sessionStorage (no localStorage, para que cada
    // pestaña/usuario tenga su propia sesión)
    API.sesion.guardar(datos);

    // Redirigir al panel correspondiente al rol
    window.location.href = datos.panel;

  } catch (err) {
    mostrarError('Usuario o contraseña incorrectos.');
    btnSubmit.textContent = 'Ingresar';
    btnSubmit.disabled    = false;
  }
}

function mostrarError(msg) {
  // Reusar el contenedor de error si existe, si no crearlo
  let el = document.getElementById('loginError');
  if (!el) {
    el = document.createElement('p');
    el.id = 'loginError';
    el.style.cssText = 'color:#b71c1c;font-weight:600;text-align:center;margin-top:10px;font-size:0.9rem';
    document.querySelector('.login-container form').appendChild(el);
  }
  el.textContent = msg;
  setTimeout(() => { el.textContent = ''; }, 4000);
}