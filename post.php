<?php
session_start();

if (!isset($_SESSION['usuario_id']) && (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'visitante')) {
    header("Location: inicioSesion.php");
    exit();
}

function format_fecha_sin_segundos(?string $value): string
{
    $value = (string)$value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('Y-m-d H:i', $ts);
}

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

try {
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        $dbError = "Error de conexión.";
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
            WHERE p.id_post = ?
            GROUP BY p.id_post
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $postId);
        $stmt->execute();
        $res = $stmt->get_result();
        $post = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if ($post) {
            $stmtLikes = $conn->prepare("SELECT COUNT(*) AS c FROM likes WHERE post_id = ?");
            $stmtLikes->bind_param("i", $postId);
            $stmtLikes->execute();
            $likesRow = $stmtLikes->get_result()->fetch_assoc();
            $likesCount = (int)($likesRow['c'] ?? 0);
            $stmtLikes->close();

            $userId = (int)($_SESSION['usuario_id'] ?? 0);
            if ($userId > 0) {
                $stmtLiked = $conn->prepare("SELECT 1 FROM likes WHERE post_id = ? AND usuario_id = ? LIMIT 1");
                $stmtLiked->bind_param("ii", $postId, $userId);
                $stmtLiked->execute();
                $r = $stmtLiked->get_result();
                $isLiked = $r && $r->num_rows > 0;
                $stmtLiked->close();
            }

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
    $dbError = "No se pudo cargar la publicación.";
}

if (!$post) {
    http_response_code(404);
    echo "Publicación no encontrada.";
    exit();
}

$cats = [];
if (!empty($post['categorias'])) $cats = array_values(array_filter(explode('||', (string)$post['categorias'])));
$imgs = [];
if (!empty($post['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$post['imagenes'])));

$tmdbId = (int)($post['tmdb_id'] ?? 0);
$tmdbType = trim((string)($post['tmdb_type'] ?? ''));
$tmdbTitle = trim((string)($post['tmdb_titulo'] ?? ''));
$tmdbPoster = trim((string)($post['tmdb_poster'] ?? ''));
$tmdbReleaseDate = trim((string)($post['tmdb_release_date'] ?? ''));
$tmdbYear = strlen($tmdbReleaseDate) >= 4 ? substr($tmdbReleaseDate, 0, 4) : '';

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
        <main class="post-wrap">
            <a class="back-link" href="perfil.php">← Volver al perfil</a>

            <?php if ($dbError !== '') : ?>
                <div class="sidebar-box">
                    <p class="post-muted"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>

            <article class="post-panel">
                <header class="post-head">
                    <div class="post-head-left">
                        <h1 class="post-title"><?php echo htmlspecialchars((string)($post['titulo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
                        <div class="post-sub"><?php echo nl2br(htmlspecialchars((string)($post['contenido'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                    </div>
                    <div class="post-date"><?php echo htmlspecialchars(format_fecha_sin_segundos($post['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </header>

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

                <?php if (count($cats)) : ?>
                    <div class="post-tags">
                        <?php foreach ($cats as $c) : ?>
                            <span class="post-tag"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($imgs)) : ?>
                    <div class="post-media-grid" aria-label="Imágenes">
                        <?php foreach (array_slice($imgs, 0, 4) as $src) : ?>
                            <img class="post-media-img" src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de publicación">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <footer class="post-actions">
                    <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $postId; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                        <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                        </svg>
                    </button>
                    <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $postId; ?>" aria-label="Comentar">💬</button>
                    <span class="post-likecount">❤ <?php echo (int)$likesCount; ?></span>
                    <span class="post-muted">Por <?php echo htmlspecialchars((string)($post['autor'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </footer>

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
</body>
</html>
