<?php
session_start();
include 'includes/conexion.php';

// Solo admins y moderradores pueden ver reportes
if ($_SESSION['rol'] !== 'admin' && $_SESSION['rol'] !== 'moderador') {
    die("Acceso denegado");
}

// Obtener reportes con datos del post y usuario
$sql = "SELECT r.id_reporte, r.id_post, r.motivo, r.fecha_reporte,
               u.nombre AS usuario,
               p.titulo AS titulo_post
        FROM reportes r
        JOIN usuarios u ON r.id_usuario = u.id_usuario
        JOIN posts p ON r.id_post = p.id_post
        ORDER BY r.fecha_reporte DESC";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reportes de publicaciones</title>
<link rel="stylesheet" href="css/main.css">
<link rel="stylesheet" href="css/reportes.css">
<link rel="stylesheet" href="css/styles_dashboard.css">
<link rel="stylesheet" href="css/styles_inicio.css">
<link rel="stylesheet" href="css/style_switch.css">
<!-- 🔹 Estilos globales de tema -->
<link rel="stylesheet" href="css/temas.css">
<!-- 🔹 Script global de tema -->
<script src="js/temas.js" defer></script>
</head>
</head>
<body>
  <div class="cine-bg">
    <canvas id="cineBg"></canvas>
  </div>

  <div class="main">
    <header class="topbar">
      <h1>Reportes de publicaciones</h1>
      <div class="back-container">
        <?php if ($_SESSION['rol'] === 'admin'): ?>
            <a href="dashboard.php" class="btn-back" style="margin-right: 70px">↩ Regresar al panel</a>
        <?php elseif ($_SESSION['rol'] === 'moderador'): ?>
            <a href="index.php" class="btn-back" style="margin-right: 70px">↩ Regresar al inicio</a>
        <?php endif; ?>
      </div>
      <!-- 🔹 Switch de tema (arriba a la derecha) -->
      <div class="theme-toggle" style="margin-top: 10px">
          <input type="checkbox" id="theme-switch">
          <label for="theme-switch" class="switch">
              <span class="icon-sun">☀️</span>
              <span class="icon-moon">🌙</span>
          </label>
      </div>
    </header>
  <!-- Publicaciones reportadas -->
   <div class="feed">
    <div class="feed-inner">
      <div class="admin-main">
        <section>
          <h1>📰 Publicaciones reportadas</h1>
          <?php if ($res->num_rows === 0): ?>
            <p>No hay reportes pendientes</p>
          <?php else: ?>
          <table class="admin-table">
            <thead>
              <tr>
                <th>Publicación</th>
                <th>Reportado por</th>
                <th>Motivo</th>
                <th>Fecha</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php while ($r = $res->fetch_assoc()) : ?>
                <tr>
                  <td><?= htmlspecialchars($r['titulo_post']) ?></td>
                  <td><?= htmlspecialchars($r['usuario']) ?></td>
                  <td><?= htmlspecialchars($r['motivo']) ?></td>
                  <td><?= htmlspecialchars($r['fecha_reporte']) ?></td>
                  <td>
                      <a href="post.php?id=<?= $r['id_post'] ?>" >👁 Ver publicación</a> |
                      <a href="eliminar_post.php?id=<?= $r['id_post'] ?>"  
                          onclick="return confirm('¿Seguro que quieres borrar esta publicación?')">
                          🗑 Borrar
                      </a> |
                      <a href="eliminar_reporte.php?id=<?= $r['id_reporte'] ?>"  
                          onclick="return confirm('¿Seguro que quieres borrar este reporte?')">
                          Descartar reporte
                      </a>

                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </section>
      </div>
    </div>
   </div>
  
</div>

<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=3"></script>
</body>
</html>