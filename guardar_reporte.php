<?php
session_start();
include 'includes/conexion.php';

$id_post = (int)($_POST['id_post'] ?? 0);
$id_usuario = $_SESSION['usuario_id'] ?? 0;
$motivo = trim($_POST['motivo'] ?? '');

if ($id_usuario === 0) {
    die("Debes iniciar sesión para reportar.");
}

if ($id_post === 0 || $motivo === '') {
    die("Datos incompletos.");
}

// 🔹 Insertar reporte
$stmt = $conn->prepare("INSERT INTO reportes (id_post, id_usuario, motivo, fecha_reporte) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $id_post, $id_usuario, $motivo);
$stmt->execute();
$stmt->close();

// 🔹 Redirigir al feed principal
header("Location: index.php?msg=reportado");
exit();
?>
