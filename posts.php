<?php
session_start();

// 🔹 Evitar cache
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// 🔹 Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

include 'includes/conexion.php';

if ($_SESSION['rol'] !== 'admin') { header("Location: inicioSesion.php"); exit(); } // Verificar que el usuario es admin

// Consulta para obtener los posts junto con el nombre del autor y su categoría
$sql = "SELECT 
            p.id_post,
            p.titulo,
            p.contenido,
            p.fecha,
            u.id_usuario AS autor_id,
            u.nombre AS autor,
            GROUP_CONCAT(DISTINCT c.categoria SEPARATOR ', ') AS categorias
        FROM posts p
        LEFT JOIN usuarios u ON p.autor_id = u.id_usuario
        LEFT JOIN post_categorias c ON p.id_post = c.post_id
        GROUP BY p.id_post
        ORDER BY p.fecha DESC";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios - CineBlog</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles_dashboard.css">
  <link rel="stylesheet" href="css/styles_inicio.css">
  <link rel="stylesheet" href="css/style_switch.css">
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
</style>
<body>
  <div class="cine-bg">
    <canvas id="cineBg"></canvas>
  </div>
  <!-- Sidebar del admin-->
  <?php include 'includes/sidebar_admin.php'; ?>
  <div class="main">
    <header class="topbar">
      <h1>Publicaciones</h1>
      <!-- 🔹 Switch de tema (arriba a la derecha) -->
      <div class="theme-toggle">
          <input type="checkbox" id="theme-switch">
          <label for="theme-switch" class="switch"></label>
      </div>
    </header>

<!--Vista principal para el admin-->
    <div class="feed">
        <div class="feed-inner">
            <main class="admin-main">
              <!-- Contenido principal para el admin -->
                <h1>Gestión de Publicaciones</h1>
                <!-- Tabla para mostrar las publicaciones -->
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Categoría</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <!-- Se recorre el resultado de la consulta para mostrar cada publicación en una fila de la tabla, con opciones para editar o eliminar cada publicación -->
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                        <td><?= $row['titulo'] ?></td>
                        <td><a href="perfil.php?id=<?= $row['autor_id'] ?>"><?= $row['autor'] ?></a></td>
                        <td><?= htmlspecialchars($row['categorias']) ?></td>
                        <td><?= $row['fecha'] ?></td>
                        <td>
                            <a href="editar_post.php?id=<?= $row['id_post'] ?>">Editar</a> |
                            <a href="eliminar_post.php?id=<?= $row['id_post'] ?>" onclick="return confirm('¿Seguro que quieres eliminar este post?')">Eliminar</a> |
                            <a href="post.php?id_post=<?= $row['id_post'] ?>">Ver publicación</a>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>
</div>

<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=5"></script>
<!-- 🔹 Script para forzar recarga al volver atrás -->
<script>
window.addEventListener("pageshow", function(event) {
    if (event.persisted || performance.getEntriesByType("navigation")[0].type === "back_forward") {
        window.location.href = window.location.href;
    }
});
</script>
</body>
</html>