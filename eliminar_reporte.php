<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/conexion.php';

$id_post = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_post) {
    die("ID inválido.");
}

// Solo admins pueden borrar y mods
if ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'moderador') {
    die("Acceso denegado.");
}

// Verifica que el post exista
$sqlCheck = $conn->prepare("SELECT id_reporte FROM reportes WHERE id_reporte = ?");
$sqlCheck->bind_param("i", $id_post);
$sqlCheck->execute();
$res = $sqlCheck->get_result();
if ($res->num_rows === 0) {
    die("Reporte no encontrada.");
}
$sqlCheck->close();

// Borra el reporte
$sqlDel = $conn->prepare("DELETE FROM reportes WHERE id_reporte = ?");
$sqlDel->bind_param("i", $id_post);
if ($sqlDel->execute()) {
    header("Location: reportes.php");
    exit();
} else {
    die("Error al borrar la publicación.");
}
?>
