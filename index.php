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

  function normalize_filter_value(string $value): string
  {
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);

    if (function_exists('iconv')) {
      $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      if ($converted !== false) $value = $converted;
    }

    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
  }

  $initialType = trim((string)($_GET['tipo'] ?? 'movies'));
  if ($initialType !== 'series') $initialType = 'movies';

  $initialCat = normalize_filter_value((string)($_GET['categoria'] ?? ''));

  $sidebarCats = [
    'Romance',
    'Ciencia ficción',
    'Acción',
    'Comedia',
    'Animación',
    'Terror',
    'Drama',
    'Aventura',
    'Suspenso',
  ];
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
            GROUP_CONCAT(DISTINCT pi.ruta SEPARATOR '||') AS imagenes,
            MAX(pt.tmdb_id) AS tmdb_id,
            MAX(pt.media_type) AS tmdb_type,
            MAX(pt.titulo) AS tmdb_titulo,
            MAX(pt.poster_url) AS tmdb_poster,
            MAX(pt.release_date) AS tmdb_release_date
            FROM posts p
            JOIN usuarios u ON u.id_usuario = p.autor_id
            LEFT JOIN post_categorias pc ON pc.post_id = p.id_post
            LEFT JOIN post_imagenes pi ON pi.post_id = p.id_post
          LEFT JOIN post_tmdb pt ON pt.post_id = p.id_post
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
<!--Maquetado en html para el inicio -->
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CineBlog</title>
<link rel="stylesheet" href="css/styles_inicio.css">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
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
  <!-- 🔹 Switch de tema (arriba a la derecha) -->
  <div class="theme-toggle">
    <input type="checkbox" id="theme-switch">
    <label for="theme-switch" class="switch"></label>
  </div>

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
        <button class="type-btn <?php echo $initialType === 'movies' ? 'active' : ''; ?>" onclick="selType(this,'movies')">Películas</button>
        <button class="type-btn <?php echo $initialType === 'series' ? 'active' : ''; ?>" onclick="selType(this,'series')">Series</button>
      </div>
    </div>


    <div class="sb-section">
      <div class="sb-section-title">CATEGORÍAS</div>
      <div class="pills">
        <?php foreach ($sidebarCats as $sidebarCat) : ?>
          <?php $sidebarCatNorm = normalize_filter_value($sidebarCat); ?>
          <span class="pill <?php echo $initialCat !== '' && $initialCat === $sidebarCatNorm ? 'on' : ''; ?>" onclick="selPill(this)"><?php echo htmlspecialchars($sidebarCat, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  
  <div style="display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--card);padding-top:15px;">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
    <?php if ($rol == "editor") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
    <?php elseif ($rol == "admin") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
      <div class="sb-item">📊 <span><a href="dashboard.php">Administracion</a></span></div>
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
      🔍 <input type="text" id="tmdbGlobalSearch" placeholder="Busca peliculas o series en TMDB...">
      <div class="search-results" id="tmdbGlobalResults" aria-live="polite"></div>
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

            <div class="feed-inner" data-initial-type="<?php echo htmlspecialchars($initialType, ENT_QUOTES, 'UTF-8'); ?>" data-initial-cat="<?php echo htmlspecialchars($initialCat, ENT_QUOTES, 'UTF-8'); ?>">
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
                        $tmdbId = (int)($p['tmdb_id'] ?? 0);
                        $tmdbTitle = trim((string)($p['tmdb_titulo'] ?? ''));
                        $tmdbType = trim((string)($p['tmdb_type'] ?? ''));
                        $tmdbPoster = trim((string)($p['tmdb_poster'] ?? ''));
                        $tmdbReleaseDate = trim((string)($p['tmdb_release_date'] ?? ''));
                        $tmdbYear = strlen($tmdbReleaseDate) >= 4 ? substr($tmdbReleaseDate, 0, 4) : '';

                        $postType = 'movie';
                        if ($tmdbType === 'tv') {
                          $postType = 'series';
                        } elseif (in_array('Serie', $cats, true)) {
                          $postType = 'series';
                        }

                        $catsFilterValue = implode('|', array_map('strval', $cats));
                    ?>
                      <article class="post-card" data-type="<?php echo htmlspecialchars($postType, ENT_QUOTES, 'UTF-8'); ?>" data-cats="<?php echo htmlspecialchars($catsFilterValue, ENT_QUOTES, 'UTF-8'); ?>">
                        <header class="post-head">
                            <div class="post-title"><?php echo htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="post-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </header>

                        <p class="post-body"><?php echo nl2br(htmlspecialchars($p['contenido'], ENT_QUOTES, 'UTF-8')); ?></p>

                        <?php if ($tmdbId > 0 && $tmdbTitle !== '') : ?>
                          <a
                            class="post-media"
                            href="pelicula.php?tmdb_id=<?php echo $tmdbId; ?>&type=<?php echo $tmdbType === 'tv' ? 'tv' : 'movie'; ?>"
                          >
                            <?php if ($tmdbPoster !== '') : ?>
                              <img class="post-media-poster" src="<?php echo htmlspecialchars($tmdbPoster, ENT_QUOTES, 'UTF-8'); ?>" alt="Poster de <?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else : ?>
                              <div class="post-media-poster placeholder">Sin poster</div>
                            <?php endif; ?>

                            <div class="post-media-meta">
                              <span class="post-media-kicker"><?php echo $tmdbType === 'tv' ? 'Serie' : 'Pelicula'; ?></span>
                              <strong><?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                              <span><?php echo htmlspecialchars($tmdbYear !== '' ? $tmdbYear : 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                          </a>
                        <?php endif; ?>

                        <?php if (count($cats)) : ?>
                            <div class="post-cats">
                                <?php foreach ($cats as $c) : ?>
                              <span class="post-cat" data-cat="<?php echo htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
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

<script src="js/cinedbg.js"></script>
<script src="app.js?v=3"></script>
</body>
</html>
