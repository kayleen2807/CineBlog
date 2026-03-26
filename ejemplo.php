<?php
// Inicia la sesión para manejar la autenticación
session_start();

//si no hay rol en la sesión, redirige a inicioSesion.php
if (!isset($_SESSION['usuario_id']) && (!isset($_SESSION['rol']) || $_SESSION['rol'] !== "visitante")) {
    header("Location: inicioSesion.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 'visitante'; // Si no hay rol, asigna 'visitante' por defecto

// Cargar publicaciones para mostrarlas en inicio
$posts = [];
$likedPosts = [];
$commentsByPost = [];
$dbError = '';
function format_fecha_sin_segundos(?string $value): string
{
    $value = (string)$value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('Y-m-d H:i', $ts);
}
try {
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        $dbError = "Error de conexión: " . $conn->connect_error;
    } else {
        $conn->set_charset("utf8mb4");
        $sql = "
            SELECT
                p.id_post,
                p.titulo,
                p.contenido,
                p.fecha,
                u.nombre AS autor,
                GROUP_CONCAT(DISTINCT pc.categoria SEPARATOR '||') AS categorias,
                GROUP_CONCAT(DISTINCT pi.ruta SEPARATOR '||') AS imagenes
            FROM posts p
            JOIN usuarios u ON u.id_usuario = p.autor_id
            LEFT JOIN post_categorias pc ON pc.post_id = p.id_post
            LEFT JOIN post_imagenes pi ON pi.post_id = p.id_post
            GROUP BY p.id_post
            ORDER BY p.fecha DESC
            LIMIT 50
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) $posts[] = $row;
            $res->free();
        }

        if (count($posts)) {
            $postIds = array_map(fn ($r) => (int)$r['id_post'], $posts);
            $postIds = array_values(array_filter($postIds, fn ($v) => $v > 0));
            if (count($postIds)) {
                $idList = implode(',', $postIds);
                $userId = (int)($_SESSION['usuario_id'] ?? 0);

                $resLikes = $conn->query("SELECT post_id FROM likes WHERE usuario_id = $userId AND post_id IN ($idList)");
                if ($resLikes) {
                    while ($r = $resLikes->fetch_assoc()) $likedPosts[(int)$r['post_id']] = true;
                    $resLikes->free();
                }

                $resCom = $conn->query("
                    SELECT c.post_id, c.contenido, c.fecha, u.nombre AS autor
                    FROM comentarios c
                    JOIN usuarios u ON u.id_usuario = c.usuario_id
                    WHERE c.post_id IN ($idList)
                    ORDER BY c.fecha ASC
                ");
                if ($resCom) {
                    while ($r = $resCom->fetch_assoc()) {
                        $pid = (int)$r['post_id'];
                        if (!isset($commentsByPost[$pid])) $commentsByPost[$pid] = [];
                        $commentsByPost[$pid][] = $r;
                    }
                    $resCom->free();
                }
            }
        }
        $conn->close();
    }
} catch (Throwable $e) {
    $dbError = "Error de base de datos.";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CineBlog</title>
<link rel="stylesheet" href="css/styles_inicio.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="cine-bg">
  <canvas id="cineBg"></canvas>
</div>

<aside class="sidebar">
  <div style="display:flex;flex-direction:column;gap:15px;">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="perfil.php"><?php echo $_SESSION['nombre']; ?></a></span>
                </button>  
            <?php elseif ($rol == "admin") : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="perfil.php"><?php echo $_SESSION['nombre']; ?></a></span>
                </button>
             <?php else : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="registro.php">¿Registrarse?</a></span>
                </button>
            <?php endif; ?>

    <div class="sb-section">
      <div class="sb-section-title">TIPO</div>
      <div class="type-filter">
        <button class="type-btn active" onclick="selType(this,'movies')">Películas</button>
        <button class="type-btn" onclick="selType(this,'series')">Series</button>
      </div>
    </div>


    <div class="sb-section">
      <div class="sb-section-title">CATEGORÍAS</div>
      <div class="pills">
        <span class="pill on" onclick="selPill(this)">Romance</span>
        <span class="pill" onclick="selPill(this)">Ciencia ficción</span>
        <span class="pill" onclick="selPill(this)">Acción</span>
        <span class="pill" onclick="selPill(this)">Comedia</span>
        <span class="pill" onclick="selPill(this)">Animación</span>
        <span class="pill" onclick="selPill(this)">Terror</span>
        <span class="pill" onclick="selPill(this)">Drama</span>
        <span class="pill" onclick="selPill(this)">Aventura</span>
        <span class="pill" onclick="selPill(this)">Suspenso</span>
      </div>
    </div>
  </div>

  
  <div style="display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--card);padding-top:15px;">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
    <?php if ($rol == "editor") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
    <?php elseif ($rol == "admin") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
    <?php endif; ?>
    <div class="sb-item">⚙️ Configuración</div>
    <div class="sb-item">🚪<span><a href="cerrarSesion.php">Cerrar sesión</a></span></div>
  </div>
</aside>

<div class="main">
  <header class="topbar">
    <div class="logo-text"><span>C</span>ineBlog</div>
    <span class="tendencies">Tendencias</span>
    <div class="search-wrap">
      🔍 <input type="text" placeholder="¿Que estas buscando?...">
    </div>
  </header>

  <div class="feed">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <button class="create-post" type="button" aria-label="Crear publicación" onclick="window.location.href='publicarsubir.php'">+</button>
                <!-- Se agregaran mas cosas para el editor-->
            <?php elseif ($rol == "admin") : ?>
                <button class="create-post" type="button" aria-label="Crear publicación" onclick="window.location.href='publicarsubir.php'">+</button>
                <!-- Se agregaran mas cosas para el admin-->
            <?php endif; ?>

            <div class="feed-inner">
                <?php if ($dbError !== '') : ?>
                    <div class="feed-alert error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($dbError === '' && !count($posts)) : ?>
                    <div class="feed-empty">Todavía no hay publicaciones.</div>
                <?php endif; ?>

                <?php foreach ($posts as $p) : ?>
                    <?php
                        $cats = [];
                        if (!empty($p['categorias'])) $cats = array_values(array_filter(explode('||', (string)$p['categorias'])));
                        $imgs = [];
                        if (!empty($p['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$p['imagenes'])));
                        $imgs = array_slice($imgs, 0, 4);
                        $pid = (int)($p['id_post'] ?? 0);
                        $isLiked = $pid && isset($likedPosts[$pid]);
                        $comments = $pid && isset($commentsByPost[$pid]) ? $commentsByPost[$pid] : [];
                    ?>
                    <article class="post-card">
                        <header class="post-head">
                            <div class="post-title"><?php echo htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="post-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </header>

                        <p class="post-body"><?php echo nl2br(htmlspecialchars($p['contenido'], ENT_QUOTES, 'UTF-8')); ?></p>

                        <?php if (count($cats)) : ?>
                            <div class="post-cats">
                                <?php foreach ($cats as $c) : ?>
                                    <span class="post-cat"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (count($imgs)) : ?>
                            <div class="post-imgs">
                                <?php foreach ($imgs as $src) : ?>
                                    <img class="post-img" src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de publicación">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="post-actions" aria-label="Acciones">
                            <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $pid; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                                <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                                </svg>
                            </button>
                            <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $pid; ?>" aria-label="Comentar">💬</button>
                        </div>

                        <section class="comments" data-post-id="<?php echo $pid; ?>" hidden>
                            <form class="comment-form" data-post-id="<?php echo $pid; ?>">
                                <textarea class="comment-input" name="contenido" maxlength="400" placeholder="Escribe un comentario..." required></textarea>
                                <div class="comment-actions">
                                    <button class="comment-send" type="submit">Comentar</button>
                                </div>
                            </form>

                            <div class="comment-list" aria-label="Comentarios">
                                <?php foreach ($comments as $c) : ?>
                                    <div class="comment-item">
                                        <div class="comment-head">
                                            <span class="comment-author"><?php echo htmlspecialchars($c['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="comment-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($c['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="comment-body"><?php echo nl2br(htmlspecialchars($c['contenido'] ?? '', ENT_QUOTES, 'UTF-8')); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    </article>
                <?php endforeach; ?>
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

function selType(el, type){
  el.closest('.type-filter').querySelectorAll('.type-btn').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
}

function selPill(el){
  el.closest('.pills').querySelectorAll('.pill').forEach(p => p.classList.remove('on'));
  el.classList.add('on');
}
</script>

</body>
</html>