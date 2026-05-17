<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/conexion.php';

$id_post = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_post) {
    die("ID inválido.");
}

// Solo admins pueden borrar
if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

// Verifica que el post exista
$sqlCheck = $conn->prepare("SELECT id_post FROM posts WHERE id_post = ?");
$sqlCheck->bind_param("i", $id_post);
$sqlCheck->execute();
$res = $sqlCheck->get_result();
if ($res->num_rows === 0) {
    die("Publicación no encontrada.");
}
$sqlCheck->close();

// Borra el post
$sqlDel = $conn->prepare("DELETE FROM posts WHERE id_post = ?");
$sqlDel->bind_param("i", $id_post);
if ($sqlDel->execute()) {
    header("Location: dashboard.php");
    exit();
} else {
    die("Error al borrar la publicación.");
}
?>
