<?php
session_start();

// Evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Validar sesión o rol visitante
// Verifica que el usuario está autenticado, si no lo está redirige al inicio de sesión. Se permite el acceso a usuarios con rol "visitante" para que puedan ver los posts y comentar, pero no crear posts ni acceder al dashboard.
if (!isset($_SESSION['usuario_id']) && (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'visitante')) {
    header("Location: inicioSesion.php");
    exit();
}

// Función para formatear la fecha sin mostrar los segundos
function format_fecha_sin_segundos(?string $value): string
{
    $value = (string)$value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('Y-m-d H:i', $ts);
}

// Validación de entrada: se espera un parámetro "id_post" en la URL que indique el ID del post a mostrar, debe ser un entero positivo
$postId = filter_input(INPUT_GET, 'id_post', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if ($postId <= 0) {
    http_response_code(400);
    echo "ID de publicación inválido.";
    exit();
}

$post = null;
$comments = [];
$likesCount = 0;
$isLiked = false;
$dbError = '';

// Conexión a la base de datos
try {
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        $dbError = "Error de conexión.";
    } else {
        $conn->set_charset("utf8mb4");
        // Consulta para obtener los detalles del post, incluyendo su título, contenido, fecha, autor, categorías asociadas, imágenes asociadas, y datos de TMDB si están disponibles. Se usa GROUP_CONCAT para obtener todas las categorías e imágenes asociadas al post en una sola fila.
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
            WHERE p.id_post = ?
            GROUP BY p.id_post
            LIMIT 1
        ";
        // Se prepara la consulta para evitar inyecciones SQL, se ejecuta y se obtiene el resultado
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $res = $stmt->get_result();
        $post = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        // Si se encontró el post, se obtienen los datos adicionales como el conteo de likes, si el usuario actual ha marcado el post como "me gusta", y los comentarios asociados al post
        if ($post) {
            //Conteo de likes para el post
            $stmtLikes = $conn->prepare("SELECT COUNT(*) AS c FROM likes WHERE post_id = ?");
            $stmtLikes->bind_param("i", $postId);
            $stmtLikes->execute();
            $likesRow = $stmtLikes->get_result()->fetch_assoc();
            $likesCount = (int)($likesRow['c'] ?? 0);
            $stmtLikes->close();

            // Verifica si el usuario actual ha marcado el post como "me gusta"
            $userId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($userId > 0) {
                $stmtLiked = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND usuario_id = ? LIMIT 1");
                $stmtLiked->bind_param("ii", $postId, $userId);
                $stmtLiked->execute();
                $r = $stmtLiked->get_result();
                $isLiked = $r && $r->num_rows > 0;
                $stmtLiked->close();
            }

            // Obtiene los comentarios asociados al post, incluyendo el contenido del comentario, la fecha, y el nombre del autor. Se ordenan por fecha ascendente para mostrar primero los comentarios más antiguos.
            $stmtCom = $conn->prepare("
                SELECT c.contenido, c.fecha, u.nombre AS autor
                FROM comentarios c
                JOIN usuarios u ON u.id_usuario = c.usuario_id
                WHERE c.post_id = ?
                ORDER BY c.fecha ASC
            ");
            $stmtCom->bind_param("i", $postId);
            $stmtCom->execute();
            $resCom = $stmtCom->get_result();
            while ($row = $resCom->fetch_assoc()) $comments[] = $row;
            $stmtCom->close();
        }

        $conn->close();
    }
} catch (Throwable $e) {
    // En caso de cualquier error durante la conexión o las consultas, se captura la excepción y se muestra un mensaje de error
    $dbError = "No se pudo cargar la publicación.";
}

// Si no se encontró el post, se devuelve un error 404
if (!$post) {
    http_response_code(404);
    echo "Publicación no encontrada.";
    exit();
}

// Procesa las categorías e imágenes asociadas al post, que se obtuvieron como cadenas y las convierte en arrays.
if (!empty($post['categorias'])) $cats = array_values(array_filter(explode('||', (string)$post['categorias'])));
$imgs = [];
if (!empty($post['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$post['imagenes'])));

// Procesa los datos de TMDB, si están disponibles, para usarlos en la ficha de la película/serie asociada al post. Se valida cada campo para evitar problemas de formato o datos faltantes.
$tmdbId = (int)($post['tmdb_id'] ?? 0);
$tmdbType = trim((string)($post['tmdb_type'] ?? ''));
$tmdbTitle = trim((string)($post['tmdb_titulo'] ?? ''));
$tmdbPoster = trim((string)($post['tmdb_poster'] ?? ''));
$tmdbReleaseDate = trim((string)($post['tmdb_release_date'] ?? ''));
$tmdbYear = strlen($tmdbReleaseDate) >= 4 ? substr($tmdbReleaseDate, 0, 4) : '';

// Determina la imagen de portada para la publicación, se prioriza el poster de TMDB si está disponible, luego la primera imagen asociada al post, y si no hay imágenes se muestra una imagen por defecto.
$coverImg = "css/cineBlog_Logo.png";
if ($tmdbPoster !== '') $coverImg = $tmdbPoster;
elseif (count($imgs)) $coverImg = $imgs[0];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/style_post.css?v=1">
    <title><?php echo htmlspecialchars((string)($post['titulo'] ?? 'Publicación'), ENT_QUOTES, 'UTF-8'); ?> - CineBlog</title>
    <!-- 🔹 Estilos globales de tema -->
    <link rel="stylesheet" href="css/temas.css">
    <!-- 🔹 Script global de tema -->
    <script src="js/temas.js" defer></script>
</head>
<body>
    <!-- 🔹 Switch de tema (arriba a la derecha) -->
    <div class="theme-toggle">
        <input type="checkbox" id="theme-switch">
        <label for="theme-switch" class="switch"></label>
    </div>
    <div class="page-shell">
        <!-- Cabecera del sitio, con el logo y un enlace al perfil del usuario -->
        <main class="post-wrap">
            <a class="back-link" href="perfil.php">← Volver al perfil</a>
            <!-- Si hubo un error al cargar los datos del post, se muestra un mensaje de error en lugar del contenido del post -->
            <?php if ($dbError !== '') : ?>
                <div class="sidebar-box">
                    <p class="post-muted"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <!--Contenido principal del post-->
            <article class="post-panel">
                <header class="post-head">
                    <div class="post-head-left">
                        <!-- Título y contenido del post-->
                        <h1 class="post-title"><?php echo htmlspecialchars((string)($post['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="post-sub"><?php echo nl2br(htmlspecialchars((string)($post['contenido'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                    </div>
                    <!-- Fecha de publicación-->
                    <div class="post-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($post['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </header>

                <!-- Si el post tiene una película o serie de TMDB asociada, se muestra una ficha con la información de esa película/serie, incluyendo su título, año de lanzamiento, tipo (película o serie), y poster si está disponible. La ficha es un enlace que lleva a la página de detalles de esa película/serie -->
                <?php if ($tmdbId > 0 && $tmdbTitle !== '') : ?>
                    <a class="post-tmdb" href="pelicula.php?tmdb_id=<?php echo $tmdbId; ?>&type=<?php echo $tmdbType === 'tv' ? 'tv' : 'movie'; ?>" aria-label="Ver ficha de <?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if ($tmdbPoster !== '') : ?>
                            <img class="post-tmdb-poster" src="<?php echo htmlspecialchars($tmdbPoster, ENT_QUOTES, 'UTF-8'); ?>" alt="Poster de <?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else : ?>
                            <div class="post-tmdb-poster post-tmdb-poster--ph">Sin poster</div>
                        <?php endif; ?>
                        <div class="post-tmdb-meta">
                            <span class="post-tmdb-kicker"><?php echo $tmdbType === 'tv' ? 'Serie' : 'Pelicula'; ?></span>
                            <strong><?php echo htmlspecialchars($tmdbTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="post-muted"><?php echo htmlspecialchars($tmdbYear !== '' ? $tmdbYear : 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </a>
                <?php endif; ?>
                <!-- Si el post tiene categorías asociadas, se muestran como chips debajo del contenido del post -->
                <?php if (count($cats)) : ?>
                    <div class="post-tags">
                        <?php foreach ($cats as $c) : ?>
                            <span class="post-tag"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <!-- Si el post tiene imágenes asociadas, se muestran en una cuadrícula debajo del contenido del post, se limita a mostrar un máximo de 4 imágenes para evitar sobrecargar la página -->
                <?php if (count($imgs)) : ?>
                    <div class="post-media-grid" aria-label="Imágenes">
                        <?php foreach (array_slice($imgs, 0, 4) as $src) : ?>
                            <img class="post-media-img" src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de publicación">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Sección de acciones para interactuar con el post (me gusta, comentar y un enlace para ver la publicación) tambien muestra el autor del post y sus likes -->
                <footer class="post-actions">
                    <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $postId; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                        <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                        </svg>
                    </button>
                    <!--Contador de likes y nombre del autor del post-->
                    <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $postId; ?>" aria-label="Comentar">💬</button>
                    <span class="post-likecount">❤ <?php echo (int)$likesCount; ?></span>
                    <span class="post-muted">Por <?php echo htmlspecialchars((string)($post['autor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </footer>
                <!-- Sección de comentarios, inicialmente oculta, se muestra al hacer clic en el botón de comentar, muestra un formulario para agregar un nuevo comentario y una lista de comentarios existentes asociados a la publicación -->
                <section class="comments post-comments" data-post-id="<?php echo $postId; ?>" hidden>
                    <form class="comment-form" data-post-id="<?php echo $postId; ?>">
                        <textarea class="comment-input" name="contenido" maxlength="400" placeholder="Escribe un comentario..." required></textarea>
                        <div class="comment-actions">
                            <button class="comment-send" type="submit">Comentar</button>
                        </div>
                    </form>

                    <div class="comment-list" aria-label="Comentarios">
                        <?php foreach ($comments as $c) : ?>
                            <div class="comment-item">
                                <div class="comment-head">
                                    <span class="comment-author"><?php echo htmlspecialchars((string)($c['autor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="comment-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($c['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div class="comment-body"><?php echo nl2br(htmlspecialchars((string)($c['contenido'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </article>
        </main>
    </div>

    <script src="app.js?v=5"></script>
    <!-- 🔹 Script para forzar recarga al volver atrás -->
    <script>
  window.addEventListener("pageshow", function(event) {
        // Detecta si la página viene de caché (persisted)
        // o si el usuario llegó con la flecha atrás/adelante (back_forward)
        if (event.persisted || performance.getEntriesByType("navigation")[0].type === "back_forward") {
            window.location.href = window.location.href;
        }
    });
</script>
</body>
</html>
