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

// Función para formatear fechas sin mostrar segundos
function format_fecha_sin_segundos(?string $value): string
{
    $value = (string)$value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('Y-m-d H:i', $ts);
}

// Función para normalizar texto de filtros (tipo, categoría) y evitar problemas con espacios, mayúsculas, acentos, etc.
function normalize_filter_value(string $value): string
  {
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    // Convierte caracteres acentuados a su forma base para evitar problemas de comparación
    if (function_exists('iconv')) {
      $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
      if ($converted !== false) $value = $converted;
    }

    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
  }
  // Normaliza el valor inicial del filtro de tipo y categoría desde la URL para que coincida con el formato esperado en los botones y las publicaciones
  $initialType = trim((string)($_GET['tipo'] ?? 'movies'));
  if ($initialType !== 'series') $initialType = 'movies';

  // Normaliza el valor inicial del filtro de categoría desde la URL para que coincida con el formato esperado en las publicaciones
  $initialCat = normalize_filter_value((string)($_GET['categoria'] ?? ''));

  // Lista de categorías para mostrar en el sidebar
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
  // Conexión a la base de datos
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        $dbError = "Error de conexión: " . $conn->connect_error;
    } else { // Si la conexión es exitosa, continúa con la consulta de publicaciones
        $conn->set_charset("utf8mb4");
        // Obtiene la foto de perfil del usuario para mostrarla en el sidebar
        $fotoPerfil = "uploads/default.png";
        $stmt = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $_SESSION['usuario_id']);
        $stmt->execute();
        $res1 = $stmt->get_result();
        if ($res1 && $row1 = $res1->fetch_assoc()) {
            if (!empty($row1['foto_perfil'])) {
                $fotoPerfil = "uploads/" . $row1['foto_perfil'];
            }
        }
        // Consulta para obtener las publicaciones junto con su autor, categorías, imágenes y datos de TMDB si existen
        $sql = "
            SELECT
                p.id_post,
                p.titulo,
                p.contenido,
                p.fecha,
                u.nombre AS autor,
                u.foto_perfil AS autor_foto,
                u.id_usuario AS autor_id,
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
            GROUP BY p.id_post, p.titulo, p.contenido, p.fecha, u.nombre, u.foto_perfil, u.id_usuario
            ORDER BY p.fecha DESC
            LIMIT 50
        ";
        // Ejecuta la consulta y almacena los resultados en arrays para usarlos en la página
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) $posts[] = $row;
            $res->free();
        }
        // Si se obtuvieron publicaciones, obtiene los IDs para luego consultar qué publicaciones ha dado like el usuario y cuáles son los comentarios de cada publicación
        if (count($posts)) {
          // Obtiene los IDs de las publicaciones
            $postIds = array_map(fn ($r) => (int)$r['id_post'], $posts);
            $postIds = array_values(array_filter($postIds, fn ($v) => $v > 0));
            // Si hay IDs válidos, consulta los likes del usuario y los comentarios de cada publicación para mostrarlos en la página
            if (count($postIds)) {
                $idList = implode(',', $postIds);
                $userId = (int)($_SESSION['usuario_id'] ?? 0);
                // Consulta para obtener los IDs de las publicaciones que el usuario ha dado like
                $resLikes = $conn->query("SELECT post_id FROM likes WHERE usuario_id = $userId AND post_id IN ($idList)");
                if ($resLikes) {
                    while ($r = $resLikes->fetch_assoc()) $likedPosts[(int)$r['post_id']] = true;
                    $resLikes->free();
                }
                // Consulta para obtener los comentarios de las publicaciones
                $resCom = $conn->query("
                    SELECT c.post_id, c.contenido, c.fecha, u.nombre AS autor
                    FROM comentarios c
                    JOIN usuarios u ON u.id_usuario = c.usuario_id
                    WHERE c.post_id IN ($idList)
                    ORDER BY c.fecha ASC
                ");
                // Almacena los comentarios en un array asociativo donde la clave es el ID de la publicación para luego mostrarlos debajo de cada publicación
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
        $stmt->close();
        $conn->close();
    }
} catch (Throwable $e) { // Si ocurre cualquier error durante la conexión o las consultas se muestra mensaje de error
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

<!-- Sidebar de navegación lateral -->
<aside class="sidebar">
  <div style="display:flex;flex-direction:column;gap:15px;">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <!-- Si el rol es editor o admin se muestra el avatar y su nombre de usuario en la side bar y lo lleva a su perfil (perfil.php)-->
            <?php if ($rol == "editor") : ?>
                <button class="perfil-btn">
                    <div class="avatar"><img src="<?= htmlspecialchars($fotoPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de <?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?>" class="avatar"></div>
                        <span><a href="perfil.php"><?php echo $_SESSION['nombre']; ?></a></span>
                </button>  
            <?php elseif ($rol == "admin") : ?>
                <button class="perfil-btn">
                    <div><img src="<?= htmlspecialchars($fotoPerfil, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de <?= htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') ?>" class="avatar"></div>
                        <span><a href="perfil.php"><?php echo $_SESSION['nombre']; ?></a></span>
                </button>
              <!-- Si el rol es visitante solo muestra un icono y en vez de llevarlo aun perfil, lo lleve a registrarse -->
             <?php else : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="registro.php">¿Registrarse?</a></span>
                </button>
            <?php endif; ?>
    <!-- Sidebar principal  -->
    <div class="sb-section">
      <div class="sb-section-title">TIPO</div>
      <div class="type-filter">
        <button class="type-btn <?php echo $initialType === 'movies' ? 'active' : ''; ?>" onclick="selType(this,'movies')">Películas</button>
        <button class="type-btn <?php echo $initialType === 'series' ? 'active' : ''; ?>" onclick="selType(this,'series')">Series</button>
      </div>
    </div>

    <!-- Categorias-->
    <div class="sb-section">
      <div class="sb-section-title">CATEGORÍAS</div>
      <div class="pills">
        <?php
          // Normaliza las categorías del sidebar para que coincidan con el formato esperado en las publicaciones y los filtros 
          foreach ($sidebarCats as $sidebarCat) : ?>
          <?php $sidebarCatNorm = normalize_filter_value($sidebarCat); ?>
          <span class="pill <?php echo $initialCat !== '' && $initialCat === $sidebarCatNorm ? 'on' : ''; ?>" onclick="selPill(this)"><?php echo htmlspecialchars($sidebarCat, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  
  <div style="display:flex;flex-direction:column;gap:10px;border-top:1px solid var(--card);padding-top:15px;">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
     <!-- Si el rol es editor o admin tendran apartado de notificaciones -->
    <?php if ($rol == "editor") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
    <!-- Si el rol es admin, este tendra un apartado unico de administracion -->
    <?php elseif ($rol == "admin") : ?>
      <div class="sb-item">🔔 <span>Notificaciones</span></div>
      <div class="sb-item">📊 <span><a href="dashboard.php">Administracion</a></span></div>
    <?php endif; ?>
    <!-- Culaquier rol puede cerrar sesion y tener un panel de configuracion-->
    <div class="sb-item">⚙️ Configuración</div>
    <div class="sb-item">🚪<a href="cerrarSesion.php">Cerrar sesión</a></div>
  </div>
</aside>
<!-- Termina Sidebar -->


<!-- Apartado principal (feed para posts y comentarios)-->
<div class="main">
  <header class="topbar">
    <div class="logo-text"><span>C</span>ineBlog</div>
    <span class="tendencies">Tendencias</span>
    <!-- Barra de búsqueda-->
    <div class="search-wrap">
      <!-- El buscador utiliza la API de TMDB para buscar películas o series -->
      🔍 <input type="text" id="tmdbGlobalSearch" placeholder="Busca peliculas o series en TMDB...">
      <div class="search-results" id="tmdbGlobalResults" aria-live="polite"></div>
    </div>
  </header>
  
  <!-- Feed de publicaciones -->
  <div class="feed">
    <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
      <!-- Si el rol es editor o admin se muestra un boton para crear publicaciones -->
            <?php if ($rol == "editor") : ?>
                <button class="create-post" type="button" aria-label="Crear publicación" onclick="window.location.href='publicarsubir.php'">+</button>
            <?php elseif ($rol == "admin") : ?>
                <button class="create-post" type="button" aria-label="Crear publicación" onclick="window.location.href='publicarsubir.php'">+</button>
            <?php endif; ?>
            <!-- Logica para el feed de publicaciones, en caso de no haber publicaciones muestra un mensaje-->
            <div class="feed-inner" data-initial-type="<?php echo htmlspecialchars($initialType, ENT_QUOTES, 'UTF-8'); ?>" data-initial-cat="<?php echo htmlspecialchars($initialCat, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($dbError !== '') : ?>
                    <div class="feed-alert error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($dbError === '' && !count($posts)) : ?>
                    <div class="feed-empty">Todavía no hay publicaciones.</div>
                <?php endif; ?>
                <!-- Logica para mostrar las publicaciones -->
                <?php foreach ($posts as $p) : ?>
                    <?php
                        // Normaliza las categorías de cada publicación para que coincidan con el formato esperado en los filtros y las publicaciones
                        $cats = [];
                        if (!empty($p['categorias'])) $cats = array_values(array_filter(explode('||', (string)$p['categorias'])));
                        $imgs = [];
                        if (!empty($p['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$p['imagenes'])));
                        // Limita a 4 imágenes por publicación para evitar sobrecargar el diseño y la experiencia de usuario, si hay más de 4 imágenes se muestran las primeras 4 y se ignoran las demás
                        $imgs = array_slice($imgs, 0, 4);
                        $pid = (int)($p['id_post'] ?? 0); // Id de la publicación para manejar likes y comentarios
                        $isLiked = $pid && isset($likedPosts[$pid]); // Si el usuario ha dado like a esta publicación, se marca como liked para mostrar el corazón relleno
                        $comments = $pid && isset($commentsByPost[$pid]) ? $commentsByPost[$pid] : []; // Comentarios de esta publicación para mostrarlos debajo de la publicación
                        // Datos de TMDB para mostrar el enlace a la película o serie relacionada si existe
                        $tmdbId = (int)($p['tmdb_id'] ?? 0);
                        $tmdbTitle = trim((string)($p['tmdb_titulo'] ?? ''));
                        $tmdbType = trim((string)($p['tmdb_type'] ?? ''));
                        $tmdbPoster = trim((string)($p['tmdb_poster'] ?? ''));
                        $tmdbReleaseDate = trim((string)($p['tmdb_release_date'] ?? ''));
                        $tmdbYear = strlen($tmdbReleaseDate) >= 4 ? substr($tmdbReleaseDate, 0, 4) : '';
                        // Determina el tipo de publicación (película o serie) para usarlo como filtro y mostrarlo en la etiqueta de la publicación, se basa en el tipo de TMDB si existe, si no se basa en las categorías buscando la categoría "Serie" para marcarla como serie, si no se encuentra se marca como película por defecto
                        $postType = 'movie';
                        if ($tmdbType === 'tv') {
                          $postType = 'series';
                        } elseif (in_array('Serie', $cats, true)) {
                          $postType = 'series';
                        }
                        // Se crea un valor de filtro que es una cadena con las categorías separadas por | para que se pueda usar para verificar si una publicación pertenece a una categoría específica al aplicar los filtros, este valor se almacena en un atributo data-cats de cada publicación
                        $catsFilterValue = implode('|', array_map('strval', $cats));
                    ?>
                      <!-- Maquetación de publicación / post-->
                      <article class="post-card" data-type="<?php echo htmlspecialchars($postType, ENT_QUOTES, 'UTF-8'); ?>" data-cats="<?php echo htmlspecialchars($catsFilterValue, ENT_QUOTES, 'UTF-8'); ?>">
                        <header class="post-head">
                          <!-- Encabezado de la publicación / post , contiene foto de perfil, nombre del autor y fecha -->
                          <img src="uploads/<?= $p['autor_foto'] ?: 'default.png' ?>" alt="Foto de <?= $p['autor'] ?>" class="avatar">
                          <div class="post-author"><?php echo htmlspecialchars($p['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
                          <a href="perfil.php?id= <?= $p['autor_id'] ?>" class="btn-perfil">Ver Perfil</a>
                          <div class="post-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </header>
                        <!-- Título de la publicación / post -->
                        <div class="post-title"><?php echo htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <!-- Contenido de la publicación / post -->
                        <p class="post-body"><?php echo nl2br(htmlspecialchars($p['contenido'], ENT_QUOTES, 'UTF-8')); ?></p>
                        <!-- Si la publicación tiene un TMDB ID válido y un título, se muestra un enlace a la película o serie relacionada con su poster, título, año y tipo (película o serie) -->
                        <?php if ($tmdbId > 0 && $tmdbTitle !== '') : ?>
                          <a
                            class="post-media"
                            href="pelicula.php?tmdb_id=<?php echo $tmdbId; ?>&type=<?php echo $tmdbType === 'tv' ? 'tv' : 'movie'; ?>"
                          >
                            <!-- Poster de la película o serie si contiene-->
                            <?php if ($tmdbPoster !== '') : ?>
                              <img class="post-media-poster" src="<?php echo htmlspecialchars($tmdbPoster, ENT_QUOTES, 'UTF-8'); ?>" alt="Poster de <?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php else : ?>
                              <!-- Placeholder si no hay poster -->
                              <div class="post-media-poster placeholder">Sin poster</div>
                            <?php endif; ?>

                            <div class="post-media-meta">
                              <!-- Etiqueta de tipo (Serie o Película) -->
                              <span class="post-media-kicker"><?php echo $tmdbType === 'tv' ? 'Serie' : 'Pelicula'; ?></span>
                              <strong><?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                              <span><?php echo htmlspecialchars($tmdbYear !== '' ? $tmdbYear : 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                          </a>
                        <?php endif; ?>
                          <!-- Si la publicación tiene categorías, se muestran como etiquetas debajo del contenido de la publicación, cada categoría es un span con la clase post-cat y un atributo data-cat con el nombre de la categoría para poder usarlo como filtro al hacer clic en ella -->
                        <?php if (count($cats)) : ?>
                            <div class="post-cats">
                                <?php foreach ($cats as $c) : ?>
                              <span class="post-cat" data-cat="<?php echo htmlspecialchars((string)$c, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <!-- Imágenes de la publicación / post -->
                        <?php if (count($imgs)) : ?>
                            <div class="post-imgs">
                                <?php foreach ($imgs as $src) : ?>
                                    <img class="post-img" src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de publicación">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Acciones de la publicación / post (comentar y dar like)-->
                        <div class="post-actions" aria-label="Acciones">
                            <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $pid; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                                <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                                </svg>
                            </button>
                            <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $pid; ?>" aria-label="Comentar">💬</button>
                        </div>
                        <!-- Sección de comentarios, inicialmente oculta, se muestra al hacer clic en el botón de comentar-->  
                        <section class="comments" data-post-id="<?php echo $pid; ?>" hidden>
                            <form class="comment-form" data-post-id="<?php echo $pid; ?>">
                                <textarea class="comment-input" name="contenido" maxlength="400" placeholder="Escribe un comentario..." required></textarea>
                                <div class="comment-actions">
                                    <button class="comment-send" type="submit">Comentar</button>
                                </div>
                            </form>
                            <!-- Lista de comentarios que se han agregado -->
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
