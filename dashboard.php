<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: inicioSesion.php");
    exit();
}

// Conexión a la base
include 'includes/conexion.php';

// Consulta: contar posts por categoría
$sql = "SELECT categoria, COUNT(*) AS total 
        FROM post_categorias 
        GROUP BY categoria";
$result = $conn->query($sql);

$categorias = [];
$totales = [];

while($row = $result->fetch_assoc()) {
  $categorias[] = $row['categoria'];
  $totales[] = $row['total'];
}

// Contar usuarios por rol
$sqlRoles = "SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol";
$resultRoles = $conn->query($sqlRoles);

$roles = [];
$totalesRoles = [];

while($row = $resultRoles->fetch_assoc()) {
  $roles[] = $row['rol'];
  $totalesRoles[] = $row['total'];
}

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
<!-- 🔹 Switch de tema (arriba a la derecha) -->
  <div class="theme-toggle">
    <input type="checkbox" id="theme-switch">
    <label for="theme-switch" class="switch"></label>
  </div>
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
           <section class="admin-content">
            <p>Bienvenid@, <?php echo $_SESSION['nombre']; ?> 👋  Selecciona una sección del menú lateral para comenzar.</p>
          </section>
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
        <div class="chart-container" >
          <canvas id="postsPorCategoria"></canvas>
        </div>

        <div class="chart-container" style="width:400px; margin-top:30px; margin-left: auto; margin-right: auto;">
          <canvas id="usuariosPorRol"></canvas>
        </div>

      </div>
    </div>
</div>
<script src="js/cinedbg.js"></script>
<script src="js/app.js?v=3"></script>
<script>
  const categorias = <?php echo json_encode($categorias); ?>;
  const totales = <?php echo json_encode($totales); ?>;

  const ctx = document.getElementById('postsPorCategoria').getContext('2d');
  new Chart(ctx, {
    type: 'bar', // puedes cambiar a 'pie', 'line', etc.
    data: {
      labels: categorias,
      datasets: [{
        label: 'Posts por categoría',
        data: totales,
        backgroundColor: 'rgba(50, 54, 161, 0.74)',
        borderColor: 'rgb(9, 20, 39)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        title: { display: true, color: 'white', text: 'Distribución de publicaciones por categoría' }
      }
    }
  });
</script>
<script>
  const roles = <?php echo json_encode($roles); ?>;
  const totalesRoles = <?php echo json_encode($totalesRoles); ?>;

  const ctxRoles = document.getElementById('usuariosPorRol').getContext('2d');
  new Chart(ctxRoles, {
    type: 'pie',
    data: {
      labels: roles,
      datasets: [{
        label: 'Usuarios por rol',
        data: totalesRoles,
        backgroundColor: [
          'rgba(16, 66, 147, 0.6)', // azul para admin
          'rgba(29, 148, 73, 0.6)',  // verde para user
          'rgba(191, 42, 42, 0.6)'   // rojo si hubiera otro rol
        ],
        borderColor: 'rgba(198, 196, 196, 0.43)',
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        title: { display: true, color: 'white', text: 'Distribución de usuarios por rol' }
      }
    }
  });
</script>
</body>
</html>