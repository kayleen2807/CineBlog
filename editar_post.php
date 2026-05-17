<?php
session_start();
include 'includes/conexion.php';

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$id = (int)$_GET['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $contenido = $_POST['contenido'];

    $stmt = $conn->prepare("UPDATE posts SET titulo=?, contenido=?, editado_por_admin=1 WHERE id_post=?");
    $stmt->bind_param("ssi", $titulo, $contenido, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: dashboard.php?msg=editado");
    exit();
}

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
<link rel="stylesheet" href="css/style_switch.css">
<!-- 🔹 Estilos globales de tema -->
<link rel="stylesheet" href="css/temas.css">
<!-- 🔹 Script global de tema -->
<script src="js/temas.js" defer></script>
</head>
<body>
    <div class="form-container">
    <h2 class="form-title"> Editar publicación</h2>
    <!-- 🔹 Switch de tema (arriba a la derecha) -->
    <div class="theme-toggle">
        <input type="checkbox" id="theme-switch">
        <label for="theme-switch" class="switch"></label>
    </div>
    
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
        <a href="dashboard.php" class="btn btn-secondary">↩ Cancelar</a>
        </div>
    </form>
    </div>

</body>
</html>
