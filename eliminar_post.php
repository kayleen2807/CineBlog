<?php
session_start();
include 'includes/conexion.php';

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$id = (int)$_GET['id'];
$stmt = $conn->prepare("DELETE FROM posts WHERE id_post=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: dashboard.php?msg=eliminado");
exit();