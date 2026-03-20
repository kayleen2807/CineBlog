<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

$targetDir = "uploads/"; //carpeta donde se guardarán las fotos
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true); //crea la carpeta si no existe
}

$foto = $_FILES['foto'];
// se genera un nombre único para la foto usando el ID del usuario y el nombre original del archivo
$nombreArchivo = "perfil_" . $_SESSION['usuario_id'] . "_" . basename($foto['name']);
$targetFile = $targetDir . $nombreArchivo; //ruta completa donde se guardará la foto

//validar tipo de archivo (solo imágenes)
$tipoArchivo = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
$tiposPermitidos = ['jpg', 'jpeg', 'png', 'gif'];


if(in_array($tipoArchivo, $tiposPermitidos)) {
    // mover la foto a la carpeta de destino
    if(move_uploaded_file($foto['tmp_name'], $targetFile)) {
        //guarademos la ruta de la foto en la base
        $conn = new mysqli("localhost", "root", "", "cineblog_db");
        $stmt = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $targetFile, $_SESSION['usuario_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        header("Location: perfil.php"); //redirecciona al perfil para ver la foto actualizada
        exit();
    } else {
        echo "Error al subir la foto.";
    }
} else {
    echo "Solo se permiten archivos de imagen (JPG, JPEG, PNG, GIF).";
}
?>