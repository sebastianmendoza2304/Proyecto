// ============================================================
// index.js — Login conectado a login.php (base de datos)
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

  // Verificar que se usa http:// y no file://
  if (window.location.protocol === 'file:') {
    mostrarError('⚠️ Abre la página desde XAMPP: http://localhost/Proyecto-main/Index.html');
    return;
  }

  btnSubmit.textContent = 'Verificando...';
  btnSubmit.disabled    = true;

  try {
    const datos = await API.login(usuario, password);
    API.sesion.guardar(datos);
    window.location.href = datos.panel;

  } catch (err) {
    if (!err.message || err.message.includes('fetch') || err.message.includes('Network')) {
      mostrarError('❌ Sin conexión al servidor. ¿Está corriendo XAMPP (Apache + MySQL)?');
    } else {
      mostrarError('❌ Usuario o contraseña incorrectos.');
    }
    btnSubmit.textContent = 'Ingresar';
    btnSubmit.disabled    = false;
  }
}

function mostrarError(msg) {
  let el = document.getElementById('loginError');
  if (!el) {
    el = document.createElement('p');
    el.id = 'loginError';
    el.style.cssText = 'color:#b71c1c;font-weight:600;text-align:center;margin-top:10px;font-size:0.88rem;padding:8px;background:#ffebee;border-radius:6px;border:1px solid #ef9a9a';
    document.querySelector('.login-container form').appendChild(el);
  }
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => { el.textContent = ''; el.style.display = 'none'; }, 7000);
}