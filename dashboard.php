<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: inicioSesion.php");
    exit();
}

// Conexión a la base
include 'includes/conexion.php';

// Estadísticas rápidas
$totalUsuarios = $conn->query("SELECT COUNT(*) AS c FROM usuarios")->fetch_assoc()['c'];
$totalPosts = $conn->query("SELECT COUNT(*) AS c FROM posts")->fetch_assoc()['c'];
$totalComentarios = $conn->query("SELECT COUNT(*) AS c FROM comentarios")->fetch_assoc()['c'];
$totalLikes = $conn->query("SELECT COUNT(*) AS c FROM likes")->fetch_assoc()['c'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - CineBlog</title>
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles_dashboard.css">
  <link rel="stylesheet" href="css/styles_inicio.css">
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

<?php include 'includes/sidebar_admin.php'; ?>

<div class="main">
    <header class="topbar">
      <h1>Dashboard CineBlog</h1>
    </header>

    <div class="feed">
        <div class="feed-inner">

        <!-- Tarjetas de estadísticas -->
        <div class="stats-grid">
          <div class="stat-card">
            <h3>Usuarios</h3>
            <p><?php echo $totalUsuarios; ?></p>
          </div>
          <div class="stat-card">
            <h3>Publicaciones</h3>
            <p><?php echo $totalPosts; ?></p>
          </div>
          <div class="stat-card">
            <h3>Comentarios</h3>
            <p><?php echo $totalComentarios; ?></p>
          </div>
          <div class="stat-card">
            <h3>Likes</h3>
            <p><?php echo $totalLikes; ?></p>
          </div>
        </div>

      </div>
    </div>
</div>
<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=3"></script>
</body>
</html>