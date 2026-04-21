<?php
session_start();
include 'includes/conexion.php';

if ($_SESSION['rol'] !== 'admin') { header("Location: inicioSesion.php"); exit(); }

$result = $conn->query("SELECT id_usuario, nombre, email, rol FROM usuarios");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Usuarios - CineBlog</title>
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
      <h1>Usuarios</h1>
    </header>

    <div class="feed">
        <div class="feed-inner">
          <main class="admin-main">
            <h1>Gestión de Usuarios</h1>
            <table class="admin-table">
              <thead><tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Rol</th></tr></thead>
              <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td><?= $row['id_usuario'] ?></td>
                    <td><?= $row['nombre'] ?></td>
                    <td><?= $row['email'] ?></td>
                    <td><?= $row['rol'] ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </main>

      </div>
    </div>
</div>
<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=3"></script>

</body>
</html>