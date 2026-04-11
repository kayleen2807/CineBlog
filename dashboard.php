<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: inicioSesion.php");
    exit();
}

// Conexión a la base
$conn = new mysqli("localhost", "root", "", "cineblog_db");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

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
<aside class="sidebar">
    <div style="display:flex;flex-direction:column;gap:15px;">
        <h2>Admin Panel</h2>
        <a href="dashboard.php" class="sb-item active">Dashboard</a>
        <a href="usuarios.php" class="sb-item">Usuarios</a>
        <a href="posts.php" class="sb-item">Publicaciones</a>
        <a href="index.php" class="sb-item">Regresar</a>
    </div>
</aside>
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
<script>
(function() {
  const canvas = document.getElementById('cineBg');
  const ctx = canvas.getContext('2d');

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
    draw();
  }

  function drawIcon(ctx, type, x, y, size, angle) {
    ctx.save();
    ctx.translate(x, y);
    ctx.rotate(angle);
    ctx.strokeStyle = '#3b82f6';
    ctx.fillStyle   = '#3b82f6';
    ctx.lineWidth   = size * 0.06;
    ctx.lineCap     = 'round';
    ctx.lineJoin    = 'round';

    const s = size;

//fondo
    switch(type) {

      case 'popcorn': {
        
        ctx.beginPath();
        ctx.moveTo(-s*0.32, -s*0.05);
        ctx.lineTo(-s*0.38, s*0.48);
        ctx.lineTo( s*0.38, s*0.48);
        ctx.lineTo( s*0.32, -s*0.05);
        ctx.closePath();
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(-s*0.1, -s*0.05);
        ctx.lineTo(-s*0.13, s*0.48);
        ctx.moveTo( s*0.1, -s*0.05);
        ctx.lineTo( s*0.13, s*0.48);
        ctx.stroke();
        
        ctx.lineWidth = size * 0.05;
        const pops = [
          [-s*0.28, -s*0.26, s*0.17],
          [ s*0.0,  -s*0.35, s*0.17],
          [ s*0.28, -s*0.26, s*0.17],
          [-s*0.14, -s*0.18, s*0.14],
          [ s*0.14, -s*0.18, s*0.14],
        ];
        pops.forEach(([px,py,pr]) => {
          ctx.beginPath();
          ctx.arc(px, py, pr, 0, Math.PI*2);
          ctx.stroke();
        });
        break;
      }

    
      case 'clapper': {
       
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.12, s*0.84, s*0.56, s*0.06);
        ctx.stroke();
       
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.42, s*0.84, s*0.3, [s*0.06, s*0.06, 0, 0]);
        ctx.stroke();
       
        for(let i = 0; i < 4; i++){
          const startX = -s*0.42 + i * s*0.22;
          ctx.beginPath();
          ctx.moveTo(startX, -s*0.42);
          ctx.lineTo(startX + s*0.14, -s*0.12);
          ctx.stroke();
        }
        
        ctx.lineWidth = size * 0.04;
        [-s*0.05, s*0.1, s*0.25].forEach(yy => {
          ctx.beginPath();
          ctx.moveTo(-s*0.32, yy); ctx.lineTo(s*0.32, yy);
          ctx.stroke();
        });
        break;
      }

    
      case 'camera': {
        ctx.beginPath();
        ctx.roundRect(-s*0.42, -s*0.28, s*0.64, s*0.56, s*0.06);
        ctx.stroke();
      
        ctx.beginPath();
        ctx.arc(-s*0.1, 0, s*0.2, 0, Math.PI*2);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(-s*0.1, 0, s*0.1, 0, Math.PI*2);
        ctx.stroke();
        
        ctx.beginPath();
        ctx.moveTo(s*0.22, -s*0.1);
        ctx.lineTo(s*0.42, -s*0.24);
        ctx.lineTo(s*0.42,  s*0.24);
        ctx.lineTo(s*0.22,  s*0.1);
        ctx.closePath();
        ctx.stroke();
        break;
      }

      
      case 'star': {
        const spikes = 5, outerR = s*0.42, innerR = s*0.18;
        ctx.beginPath();
        for(let i = 0; i < spikes*2; i++){
          const r = i%2===0 ? outerR : innerR;
          const a = (i * Math.PI / spikes) - Math.PI/2;
          i===0 ? ctx.moveTo(Math.cos(a)*r, Math.sin(a)*r)
                : ctx.lineTo(Math.cos(a)*r, Math.sin(a)*r);
        }
        ctx.closePath();
        ctx.stroke();
        break;
      }

      case 'film': {
        ctx.beginPath();
        ctx.roundRect(-s*0.48, -s*0.28, s*0.96, s*0.56, s*0.04);
        ctx.stroke();
        
        [-s*0.17, s*0.0, s*0.17].forEach(yy => {
          ctx.beginPath();
          ctx.roundRect(-s*0.44, yy - s*0.08, s*0.1, s*0.14, s*0.02);
          ctx.stroke();
        });
        [-s*0.17, s*0.0, s*0.17].forEach(yy => {
          ctx.beginPath();
          ctx.roundRect(s*0.34, yy - s*0.08, s*0.1, s*0.14, s*0.02);
          ctx.stroke();
        });
        [-s*0.18, s*0.04].forEach(xx => {
          ctx.beginPath();
          ctx.roundRect(xx - s*0.03, -s*0.18, s*0.22, s*0.36, s*0.02);
          ctx.stroke();
        });
        break;
      }
      case 'ticket': {
        ctx.beginPath();
        ctx.roundRect(-s*0.46, -s*0.24, s*0.92, s*0.48, s*0.06);
        ctx.stroke();

        ctx.setLineDash([s*0.05, s*0.05]);
        ctx.beginPath();
        ctx.moveTo(s*0.1, -s*0.24);
        ctx.lineTo(s*0.1,  s*0.24);
        ctx.stroke();
        ctx.setLineDash([]);
        
        ctx.beginPath();
        ctx.arc(-s*0.46, 0, s*0.08, -Math.PI/2, Math.PI/2);
        ctx.stroke();
        ctx.beginPath();
        ctx.arc( s*0.46, 0, s*0.08, Math.PI/2, -Math.PI/2);
        ctx.stroke();
        
        ctx.lineWidth = size*0.04;
        [[-s*0.08, -s*0.1], [-s*0.08, s*0.02], [-s*0.08, s*0.12]].forEach(([lx,ly]) => {
          ctx.beginPath();
          ctx.moveTo(-s*0.3, ly); ctx.lineTo(lx, ly);
          ctx.stroke();
        });
        break;
      }
    }

    ctx.restore();
  }

  
  function draw() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    const CELL = 90;          
    const ANGLE = Math.PI/6;  
    const icons = ['popcorn','clapper','camera','star','film','ticket'];

    const cols = Math.ceil(canvas.width  / CELL) + 4;
    const rows = Math.ceil(canvas.height / CELL) + 4;

    for(let row = -2; row < rows; row++){
      for(let col = -2; col < cols; col++){
        const offX = (row % 2) * (CELL / 2);
        const x = col * CELL + offX - CELL;
        const y = row * CELL - CELL;

        
        const idx = ((row * 7 + col * 13) & 0xffff) % icons.length;
       
        const baseAngle = (row + col) % 2 === 0 ? ANGLE : -ANGLE;

        drawIcon(ctx, icons[idx], x, y, 32, baseAngle);
      }
    }
  }

  window.addEventListener('resize', resize);
  resize();
})();
</script>
<script src="app.js?v=3"></script>
</body>
</html>