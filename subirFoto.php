<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

$targetDir = "uploads/";
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0755, true); // Permisos más seguros
}

// Función para sanitizar nombre de archivo
function sanitizeFilename($filename) {
    return preg_replace('/[^a-zA-Z0-9\._-]/', '', $filename);
}

// Función para redirigir con mensaje
function redirectWithMessage($message, $type = 'error') {
    $_SESSION['upload_message'] = $message;
    $_SESSION['upload_type'] = $type;
    header("Location: perfil.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_photo'])) {
        // Eliminar foto: volver a la predeterminada
        $conn = new mysqli("localhost", "root", "", "cineblog_db");
        $stmt = $conn->prepare("UPDATE usuarios SET foto_perfil = 'uploads/default.png' WHERE id_usuario = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        redirectWithMessage("Foto eliminada exitosamente.", "success");
    } elseif (isset($_FILES['foto'])) {
        $foto = $_FILES['foto'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $tipoArchivo = strtolower(pathinfo($foto['name'], PATHINFO_EXTENSION));

        // Validaciones
        if ($foto['error'] !== UPLOAD_ERR_OK) {
            redirectWithMessage("Error al subir el archivo.");
        }
        if ($foto['size'] > $maxSize) {
            redirectWithMessage("El archivo es demasiado grande (máximo 2MB).");
        }
        if (!in_array($tipoArchivo, $allowedTypes)) {
            redirectWithMessage("Solo se permiten archivos de imagen (JPG, JPEG, PNG, GIF).");
        }

        // Verificar dimensiones (opcional, requiere GD)
        if (extension_loaded('gd')) {
            $imageInfo = getimagesize($foto['tmp_name']);
            if ($imageInfo[0] > 1024 || $imageInfo[1] > 1024) {
                redirectWithMessage("La imagen es demasiado grande (máximo 1024x1024 píxeles).");
            }
        }

        // Generar nombre único y seguro
        $nombreArchivo = "perfil_" . $_SESSION['usuario_id'] . "_" . time() . "_" . sanitizeFilename(basename($foto['name']));
        $relativePath = $targetDir . $nombreArchivo;
        $targetFile = $targetDir . $nombreArchivo;

        if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
            // Actualizar DB
            $conn = new mysqli("localhost", "root", "", "cineblog_db");
            $stmt = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $relativePath, $_SESSION['usuario_id']);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            redirectWithMessage("Foto actualizada exitosamente.", "success");
        } else {
            redirectWithMessage("Error al guardar la foto.");
        }
    }
} else {
    header("Location: perfil.php");
    exit();
}
?>