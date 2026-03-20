<?php
session_start();
// Archivo para cerrar sesión, destruye la sesión actual y redirige a la página de inicio de sesión
session_unset(); // Elimina todas las variables de sesión
session_destroy(); // Destruye la sesión

header("Location: seleccion.php?mensaje=sesion_cerrada"); // Redirige a la página de inicio de sesión
exit();
?>