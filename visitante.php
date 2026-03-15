<?php
//Archivo para el acceso como visitante, sin necesidad de iniciar sesión, con acceso limitado a ciertas funciones (como comentar o crear listas personalizadas)
session_start();
$_SESSION['usuario'] = 'visitante';
$_SESSION['rol'] = 'visitante';
// Redirige a la página principal después de establecer el rol de visitante
header("Location: index.php"); 
exit();
?>