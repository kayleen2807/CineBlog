<?php
session_start();
include 'includes/conexion.php';

// Verifica rol moderador
if ($_SESSION['rol'] !== 'moderador') {
    die("Acceso denegado");
}

// Consulta publicaciones reportadas
$sql_posts = "SELECT 
    pr.id_reporte,
    pr.motivo,
    pr.fecha,
    pr.estado,
    p.titulo AS titulo_post,
    u.nombre AS autor_post,
    ur.nombre AS usuario_reporta
FROM posts_reporte pr
JOIN posts p ON p.id_post = pr.post_id
JOIN usuarios u ON u.id_usuario = p.autor_id
JOIN usuarios ur ON ur.id_usuario = pr.usuario_id
ORDER BY pr.fecha DESC";
$posts_reportados = $conn->query($sql_posts);

// Consulta comentarios reportados
$sql_coment = "SELECT 
    cr.id_reporte,
    cr.motivo,
    cr.fecha,
    cr.estado,
    c.contenido AS comentario,
    u.nombre AS autor_comentario,
    p.titulo AS titulo_post,
    ur.nombre AS usuario_reporta
FROM coment_reporte cr
JOIN comentarios c ON c.id_comentario = cr.comentario_id
JOIN usuarios u ON u.id_usuario = c.usuario_id
JOIN posts p ON p.id_post = c.post_id
JOIN usuarios ur ON ur.id_usuario = cr.usuario_id
ORDER BY cr.fecha DESC";
$coment_reportados = $conn->query($sql_coment);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Moderador - Cineblog</title>
<link rel="stylesheet" href="css/styles_dashboard.css">
<link rel="stylesheet" href="css/styles_inicio.css">
<link rel="stylesheet" href="css/style_switch.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<!-- 🔹 Estilos globales de tema -->
<link rel="stylesheet" href="css/temas.css">
<!-- 🔹 Script global de tema -->
<script src="js/temas.js" defer></script>
</head>
<style>
  a {
    color: var(--muted);
    text-decoration: none;
  }
  a:hover{
    text-decoration: none;
    color: white;
  }
  p{
    color: var(--muted);
    font-size: 1.3em;
  }
</style>
<body>
  <div class="cine-bg">
    <canvas id="cineBg"></canvas>
  </div>
<div class="main">
    <header class="topbar">
      <h1>Panel del Moderador</h1>
      <div class="back-container">
        <a href="index.php" class="btn-back">↩ Regresar</a>
      </div>
      <!-- 🔹 Switch de tema (arriba a la derecha) -->
      <div class="theme-toggle">
          <input type="checkbox" id="theme-switch">
          <label for="theme-switch" class="switch"></label>
      </div>
    </header>
  <!-- Publicaciones reportadas -->
   <div class="feed">
    <div class="feed-inner">
      <div class="admin-main">
        <section>
          <h1>📰 Publicaciones reportadas</h1>
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th><th>Título</th><th>Autor</th><th>Reportado por</th><th>Motivo</th><th>Estado</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $posts_reportados->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id_reporte'] ?></td>
                <td><?= htmlspecialchars($row['titulo_post']) ?></td>
                <td><?= htmlspecialchars($row['autor_post']) ?></td>
                <td><?= htmlspecialchars($row['usuario_reporta']) ?></td>
                <td><?= htmlspecialchars($row['motivo']) ?></td>
                <td><?= $row['estado'] ?></td>
                <td>
                  <a href="acciones/revisar_post.php?id=<?= $row['id_reporte'] ?>">✔ Revisar</a> |
                  <a href="acciones/eliminar_post.php?id=<?= $row['id_reporte'] ?>" onclick="return confirm('¿Eliminar publicación?')">🗑 Eliminar</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </section>

        <!-- Comentarios reportados -->
        <section>
          <h1>💬 Comentarios reportados</h1>
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th><th>Comentario</th><th>Autor</th><th>Post</th><th>Reportado por</th><th>Motivo</th><th>Estado</th><th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = $coment_reportados->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id_reporte'] ?></td>
                <td><?= htmlspecialchars($row['comentario']) ?></td>
                <td><?= htmlspecialchars($row['autor_comentario']) ?></td>
                <td><?= htmlspecialchars($row['titulo_post']) ?></td>
                <td><?= htmlspecialchars($row['usuario_reporta']) ?></td>
                <td><?= htmlspecialchars($row['motivo']) ?></td>
                <td><?= $row['estado'] ?></td>
                <td>
                  <a href="acciones/revisar_coment.php?id=<?= $row['id_reporte'] ?>">✔ Revisar</a> |
                  <a href="acciones/eliminar_coment.php?id=<?= $row['id_reporte'] ?>" onclick="return confirm('¿Eliminar comentario?')">🗑 Eliminar</a>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </section>
      </div>
    </div>
   </div>
  
</div>
<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=3"></script>
</body>
</html>
