<?php
session_start();
include 'includes/conexion.php';

$id_post = (int)($_GET['id'] ?? 0);
$id_usuario = $_SESSION['usuario_id'] ?? 0;

if ($id_usuario === 0) {
    die("Debes iniciar sesión para reportar.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reportar publicación</title>
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/style_switch.css">
<link rel="stylesheet" href="css/reportes.css">
<!-- 🔹 Estilos globales de tema -->
<link rel="stylesheet" href="css/temas.css">
<!-- 🔹 Script global de tema -->
<script src="js/temas.js" defer></script>
</head>
<body>
  <!-- 🔹 Switch de tema (arriba a la derecha) -->
      <div class="theme-toggle" style="margin-top: 10px">
          <input type="checkbox" id="theme-switch">
          <label for="theme-switch" class="switch"></label>
      </div>
  <div class="form-container">
    <h2>🚩 Reportar publicación</h2>
    <form method="post" action="guardar_reporte.php">
      <input type="hidden" name="id_post" value="<?= $id_post ?>">
      <div class="form-group">
        <label for="motivo">Razón del reporte</label>
        <textarea id="motivo" name="motivo" rows="5" required></textarea>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enviar reporte</button>
        <a href="index.php" class="btn btn-secondary">↩ Cancelar</a>
      </div>
    </form>
  </div>
</body>
</html>
