<?php
session_start();
include 'includes/conexion.php';

// Solo admins pueden ver reportes
if ($_SESSION['rol'] !== 'admin') {
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
</head>
<body>
  <h2>Reportes de publicaciones</h2>

  <?php if ($res->num_rows === 0): ?>
    <p>No hay reportes pendientes</p>
  <?php else: ?>
    <table class="tabla-reportes">
      <thead>
        <tr>
          <th>ID</th>
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
            <td><?= $r['id_reporte'] ?></td>
            <td><?= htmlspecialchars($r['titulo_post']) ?></td>
            <td><?= htmlspecialchars($r['usuario']) ?></td>
            <td><?= htmlspecialchars($r['motivo']) ?></td>
            <td><?= htmlspecialchars($r['fecha_reporte']) ?></td>
            <td>
                <a href="post.php?id=<?= $r['id_post'] ?>" class="btn-ver">👁 Ver publicación</a>
                <a href="eliminar_post.php?id=<?= $r['id_post'] ?>" 
                    class="btn-borrar" 
                    onclick="return confirm('¿Seguro que quieres borrar esta publicación?')">
                    🗑 Borrar
                </a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>