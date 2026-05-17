<?php
session_start();
include 'includes/conexion.php';

$id = (int)($_GET['id'] ?? 0);

// Verificar que el post existe
$stmt = $conn->prepare("SELECT autor_id FROM posts WHERE id_post=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$postData = $res->fetch_assoc();
$stmt->close();

if (!$postData) {
    die("Publicación no encontrada");
}

$autorId = (int)$postData['autor_id'];

// Validar acceso: admin o autor
if ($_SESSION['rol'] !== 'admin' && $_SESSION['usuario_id'] != $autorId) {
    die("Acceso denegado");
}

// Procesar edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $contenido = $_POST['contenido'];

    if ($_SESSION['rol'] === 'admin') {
        $stmt = $conn->prepare("UPDATE posts SET titulo=?, contenido=?, editado_por_admin=1 WHERE id_post=?");
    } else {
        $stmt = $conn->prepare("UPDATE posts SET titulo=?, contenido=? WHERE id_post=?");
    }

    $stmt->bind_param("ssi", $titulo, $contenido, $id);
    $stmt->execute();
    $stmt->close();

    if ($_SESSION['rol'] === 'admin') {
    header("Location: dashboard.php?msg=editado");
    } else {
        header("Location: index.php?msg=editado");
    }
    exit();
}

// Cargar datos del post
$res = $conn->query("SELECT * FROM posts WHERE id_post=$id");
$post = $res->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar publicacion</title>
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/styles_editpost.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- 🔹 Estilos globales de tema -->
<link rel="stylesheet" href="css/temas.css">
<!-- 🔹 Script global de tema -->
<script src="js/temas.js" defer></script>
</head>
<body>
    <div class="form-container">
    <h2 class="form-title"> Editar publicación</h2>
    
    <form method="post" class="edit-form">
        <div class="form-group">
        <label for="titulo">Título</label>
        <input type="text" id="titulo" name="titulo" 
                value="<?= htmlspecialchars($post['titulo']) ?>" required>
        </div>

        <div class="form-group">
        <label for="contenido">Contenido</label>
        <textarea id="contenido" name="contenido" rows="6" required><?= htmlspecialchars($post['contenido']) ?></textarea>
        </div>

        <div class="form-actions">
        <button type="submit" class="btn btn-primary"> Guardar cambios</button>
        <a href="index.php" class="btn btn-secondary">↩ Cancelar</a>
        </div>
    </form>
    </div>

</body>
</html>
