<?php
session_start(); // Archivo para cerrar sesión, destruye la sesión actual y redirige a la pantalla de selección

// 🔹 Eliminar todas las variables de sesión
session_unset();

// 🔹 Destruir la sesión en el servidor
session_destroy();

// 🔹 Evitar que el navegador guarde en caché esta página
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// 🔹 Redirigir al usuario a la pantalla de selección
header("Location: inicioSesion.php?mensaje=sesion_cerrada");
exit();
?>
