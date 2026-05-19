<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0"); // Evita que el navegador almacene en caché esta página.
header("Pragma: no-cache"); // Para HTTP/1.0
header("Expires: 0"); // Para indicar que la página ya expiró

// Verificar si el usuario está autenticado y es admin.
//Verifica que el usuario es admin, si no lo es redirige al inicio de sesión
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

// Almacena los resultados en arrays para usarlos en los gráficos 
while($row = $result->fetch_assoc()) {
  $categorias[] = $row['categoria'];
  $totales[] = $row['total'];
}

// Contar usuarios por rol
$sqlRoles = "SELECT rol, COUNT(*) AS total FROM usuarios GROUP BY rol";
$resultRoles = $conn->query($sqlRoles);

$roles = [];
$totalesRoles = [];

// Almacena los resultados en arrays para usarlos en los gráficos de roles
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
  <link rel="stylesheet" href="css/style_switch.css">
  <link rel="stylesheet" href="css/styles_inicio.css">
  <link rel="stylesheet" href="css/styles_dashboard.css">
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

<!-- Sidebar del admin -->
<?php include 'includes/sidebar_admin.php'; ?>

<div class="main">
    <header class="topbar">
      <h1>Dashboard CineBlog</h1>
      <!-- 🔹 Switch de tema (arriba a la derecha) -->
      <div class="theme-toggle">
          <input type="checkbox" id="theme-switch">
          <label for="theme-switch" class="switch">
              <span class="icon-sun">☀️</span>
              <span class="icon-moon">🌙</span>
          </label>
      </div>
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
            <div class="stat-card reportes-card">
              <h3>🚩 Reportes</h3>
              <a href="reportes.php" class="reportes-btn">Ver reportes</a>
            </div>
          </div>
          <div class="chart-container" >
            <canvas id="postsPorCategoria" style="width:600px; margin-top:30px; margin-left: auto; margin-right: auto;"></canvas>>
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
  const themeAccent = window.getThemeColor ? window.getThemeColor('--primary') : '#4da6ff';
  const themeText = window.getThemeColor ? window.getThemeColor('--color-text') : '#858585';
  const themeLine = window.getThemeColor ? window.getThemeColor('--line') : 'rgba(255,255,255,0.06)';
  const themeSuccess = window.getThemeColor ? window.getThemeColor('--success') : 'rgba(29,148,73,0.6)';
  const themeDanger = window.getThemeColor ? window.getThemeColor('--danger') : 'rgba(191,42,42,0.6)';

  // Datos para el gráfico de posts por categoría
  const categorias = <?php echo json_encode($categorias); ?>;
  const totales = <?php echo json_encode($totales); ?>;
  // Configuración del gráfico de barras para posts por categoría
  const ctx = document.getElementById('postsPorCategoria').getContext('2d');
  new Chart(ctx, {
    type: 'bar', 
    data: {
      labels: categorias,
      datasets: [{
        label: 'Posts por categoría',
        data: totales,
        backgroundColor: themeAccent,
        borderColor: themeLine,
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        title: { display: true, color: themeText, text: 'Distribución de publicaciones por categoría' }
      }
    }
  });
</script>
<script>
  // Datos para el gráfico de usuarios por rol
  const roles = <?php echo json_encode($roles); ?>;
  const totalesRoles = <?php echo json_encode($totalesRoles); ?>;
  // Configuración del gráfico de pastel para usuarios por rol
  const ctxRoles = document.getElementById('usuariosPorRol').getContext('2d');
  new Chart(ctxRoles, {
    type: 'pie',
    data: {
      labels: roles,
      datasets: [{
        label: 'Usuarios por rol',
        data: totalesRoles,
        backgroundColor: [
          themeAccent,
          themeSuccess,
          themeDanger
        ],
        borderColor: themeLine,
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        title: { display: true, color: themeText, text: 'Distribución de usuarios por rol' }
      }
    }
  });
</script>
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