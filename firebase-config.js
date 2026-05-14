// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyBROZRphe3IzjABVWN0YN4FmpYopr9gT6E",
  authDomain: "unilibre-alertas-academicas.firebaseapp.com",
  databaseURL: "https://unilibre-alertas-academicas-default-rtdb.firebaseio.com",
  projectId: "unilibre-alertas-academicas",
  storageBucket: "unilibre-alertas-academicas.firebasestorage.app",
  messagingSenderId: "308096674979",
  appId: "1:308096674979:web:8793ca05b316443ea46316",
  measurementId: "G-SB40G5KN8C"
};

// Inicializar Firebase
firebase.initializeApp(firebaseConfig);
const database = firebase.database();

// Referencias a las colecciones
const usuariosRef = database.ref('usuarios');
const reportesRef = database.ref('reportes');
const estudiantesRef = database.ref('estudiantes');
const seguimientosRef = database.ref('seguimientos');

console.log("✅ Firebase conectado correctamente");
