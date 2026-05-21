<?php
// Archivo para mostrar el perfil del usuario, solo accesible si el usuario ha iniciado sesión
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0"); // Evita que el navegador almacene en caché esta página.
header("Pragma: no-cache"); // Para HTTP/1.0
header("Expires: 0"); // Para indicar que la página ya expiró

//Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

// Función para formatear fechas sin mostrar los segundos
function format_fecha_sin_segundos(?string $value): string
{
    $value = (string)$value;
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('Y-m-d H:i', $ts);
}

//conexión a la base de datos para obtener la información del usuario
include 'includes/conexion.php';
$conn->query("ALTER TABLE posts ADD COLUMN IF NOT EXISTS rating TINYINT UNSIGNED DEFAULT NULL");

//Dectectar si se está viendo el propio perfil o el de otro usuario
$idPerfil = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['usuario_id'];
$esPropio = ($idPerfil === $_SESSION['usuario_id']);
$foto = "uploads/default.png";

// Foto de perfil (si la columna existe)
$hasFotoCol = false;
$hasBioCol = false;
$colRes = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'");
if ($colRes && $colRes->num_rows > 0) $hasFotoCol = true;
if ($colRes) $colRes->free();

$colRes = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'bio'");
if ($colRes && $colRes->num_rows > 0) $hasBioCol = true;
if ($colRes) $colRes->free();

$bio = '';
$selectCols = "nombre";
if ($hasFotoCol) $selectCols .= ", foto_perfil";
if ($hasBioCol) $selectCols .= ", bio";

// Obtener datos básicos del usuario (nombre, foto, bio si existen)
$stmt = $conn->prepare("SELECT $selectCols FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $idPerfil);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado ? $resultado->fetch_assoc() : null;
if ($hasFotoCol && !empty($usuario['foto_perfil'])) $foto = $usuario['foto_perfil'];
if ($hasBioCol && !empty($usuario['bio'])) $bio = (string)$usuario['bio'];
$stmt->close();

// Obtener bio desde ajustes_usuario si existe
$stmtAjustes = $conn->prepare("SELECT bio FROM ajustes_usuario WHERE usuario_id = ?");
if ($stmtAjustes) {
    $stmtAjustes->bind_param("i", $idPerfil);
    $stmtAjustes->execute();
    $resAjustes = $stmtAjustes->get_result();
    if ($resAjustes && $resAjustes->num_rows > 0) {
        $ajustes = $resAjustes->fetch_assoc();
        if (!empty($ajustes['bio'])) $bio = (string)$ajustes['bio'];
    }
    $stmtAjustes->close();
}

// Publicaciones del usuario
$misPosts = [];
$stmtPosts = $conn->prepare("
    SELECT
        p.id_post,
        p.titulo,
        p.contenido,
        p.fecha,
        p.rating,
        COALESCE(lk.likes_count, 0) AS likes_count,
        GROUP_CONCAT(DISTINCT pc.categoria SEPARATOR '||') AS categorias,
        GROUP_CONCAT(DISTINCT pi.ruta SEPARATOR '||') AS imagenes,
        MAX(pt.tmdb_id) AS tmdb_id,
        MAX(pt.media_type) AS tmdb_type,
        MAX(pt.titulo) AS tmdb_titulo,
        MAX(pt.poster_url) AS tmdb_poster
    FROM posts p
    LEFT JOIN post_categorias pc ON pc.post_id = p.id_post
    LEFT JOIN post_imagenes pi ON pi.post_id = p.id_post
    LEFT JOIN post_tmdb pt ON pt.post_id = p.id_post
    LEFT JOIN (
        SELECT post_id, COUNT(*) AS likes_count
        FROM likes
        GROUP BY post_id
    ) lk ON lk.post_id = p.id_post
    WHERE p.autor_id = ?
    GROUP BY p.id_post, p.titulo, p.contenido, p.fecha, p.rating, lk.likes_count
    ORDER BY p.fecha DESC
    LIMIT 50
");
// Se obtiene la información de las publicaciones del usuario, incluyendo categorías, imágenes asociadas y datos de TMDB si están disponibles, para mostrar en su perfil
$stmtPosts->bind_param("i", $idPerfil);
$stmtPosts->execute();
$resPosts = $stmtPosts->get_result();
if ($resPosts) {
    while ($row = $resPosts->fetch_assoc()) $misPosts[] = $row;
    $resPosts->free();
}
$stmtPosts->close();

$likedPosts = [];
$commentsByPost = [];
// Si el usuario tiene publicaciones, se obtienen los IDs de esas publicaciones y los comentarios asociados a esas publicaciones
if (count($misPosts)) {
    $postIds = array_map(fn ($r) => (int)$r['id_post'], $misPosts);
    $postIds = array_values(array_filter($postIds, fn ($v) => $v > 0));
    // Si el usuario tiene publicaciones, se obtienen los IDs de esas publicaciones y los comentarios asociados a esas publicaciones, así como qué publicaciones ha marcado como "me gusta" el usuario para mostrar esa información en su perfil
    if (count($postIds)) {
        $idList = implode(',', $postIds);

        $resLikes = $conn->query("SELECT post_id FROM likes WHERE usuario_id = " . (int)$_SESSION['usuario_id'] . " AND post_id IN ($idList)");
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

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/style_switch.css">
    <link rel="stylesheet" href="css/style_perfil.css">
    <link rel="stylesheet" href="lib/cropper.min.css">
    <title>Perfil CineBlog</title>
    <!-- 🔹 Estilos globales de tema -->
    <link rel="stylesheet" href="css/temas.css?v=2">
    <!-- 🔹 Script global de tema -->
    <script src="js/temas.js" defer></script>
</head>
<body>
    <div class="cine-bg">
        <canvas id="cineBg"></canvas>
    </div>
    <!--Agregar manejo de mensajes al inicio del body o main, muestra mensajes de éxito o error -->
    <?php if (isset($_SESSION['upload_message'])): ?>
        <div class="alert <?php echo $_SESSION['upload_type'] === 'success' ? 'success' : 'error'; ?>">
            <?php echo htmlspecialchars($_SESSION['upload_message'], ENT_QUOTES, 'UTF-8'); ?>
            <?php unset($_SESSION['upload_message'], $_SESSION['upload_type']); ?>
        </div>
    <?php endif; ?>

    <div class="page-shell">
        <nav class="head">
            <div class="nav-left">
                <a class="back-button" href="#" data-back aria-label="Regresar">
                    <span class="back-icon" aria-hidden="true">←</span>
                    <span>Regresar</span>
                </a>
                <div class="logo">
                    <a href="index.php" aria-label="CineBlog">
                        <img class="logo-c" src="css/cineBlog_Logo.png" alt="Logo CineBlog">
                        <span class="logo-rest">CineBlog</span>
                    </a>
                </div>
            </div>
            <div class="nav-right">
                <div class="theme-toggle theme-icon-toggle">
                    <input type="checkbox" id="theme-switch" aria-label="Cambiar tema">
                    <label for="theme-switch" class="theme-toggle-btn">
                        <span class="theme-icon sun" aria-hidden="true">☀</span>
                        <span class="theme-icon moon" aria-hidden="true">🌙</span>
                    </label>
                </div>
            </div>
        </nav>
        <!-- Contenedor principal del perfil, con una sección para mostrar la foto de perfil y acciones relacionadas (cambiar foto, eliminar foto) -->
        <header class="header-container">
            <div class="profile-hero">
                <div class="profile-card">
                    <div class="profile-avatar-wrap">
                        <img id="profile-pic" class="profile-avatar" src="uploads/<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="Foto de perfil">
                    </div>
                    <div class="profile-body">
                        <div class="profile-top">
                            <div class="profile-title">
                                <h1 class="profile-name"><?= htmlspecialchars($usuario['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8') ?></h1>
                                <p class="profile-hint">Reseñas y descubrimientos en CineBlog</p>
                            </div>
                            <div class="profile-actions">
                                <?php if ($esPropio): ?>
                                    <a class="btn-perfil primary" href="ajustes.php">Editar perfil</a>
                                <?php endif; ?>
                                <button class="btn-perfil ghost profile-share" type="button">Compartir perfil</button>
                            </div>
                        </div>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <strong><?= count($misPosts) ?></strong>
                                <span>RESEÑAS</span>
                            </div>
                        </div>
                        <div class="profile-bio">
                            <span class="bio-label">Bio</span>
                            <div class="bio-input" contenteditable="false" role="textbox" aria-multiline="true" data-placeholder="Escribe algo breve sobre ti."><?php echo htmlspecialchars($bio !== '' ? $bio : 'Aficionado al cine, reseñas y estrenos.', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="main-layout">
            <!-- Sección principal del perfil donde se muestran las publicaciones del usuario, con información relevante como título, fecha, categorías, imágenes asociadas, y acciones para interactuar con cada publicación (me gusta, comentar) -->
            <section class="left-column">
                <h2 class="section-title">Publicaciones</h2>

                <!-- Si el usuario no tiene publicaciones, se muestra un mensaje indicando que todavía no ha publicado nada, para informar al usuario sobre el estado de su perfil -->
                <?php if (!count($misPosts)) : ?>
                    <div class="sidebar-box">
                        Todavía no has publicado nada.
                    </div>
                <?php endif; ?>
                
                <!-- Se muestran las publicaciones del usuario en tarjetas, cada tarjeta muestra información relevante de la publicación como título, fecha, categorías, imágenes asociadas, y acciones para interactuar con cada publicación (me gusta, comentar) -->
                <?php foreach ($misPosts as $p) : ?>
                    <?php
                        $cats = [];
                        if (!empty($p['categorias'])) $cats = array_values(array_filter(explode('||', (string)$p['categorias'])));
                        $imgs = [];
                        if (!empty($p['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$p['imagenes'])));
                        $tmdbId = (int)($p['tmdb_id'] ?? 0);
                        $tmdbType = trim((string)($p['tmdb_type'] ?? ''));
                        $tmdbTitle = trim((string)($p['tmdb_titulo'] ?? ''));
                        $tmdbPoster = trim((string)($p['tmdb_poster'] ?? ''));

                        $img = "css/cineBlog_Logo.png";
                        if ($tmdbPoster !== '') {
                            $img = $tmdbPoster;
                        } elseif (count($imgs)) {
                            $img = $imgs[0];
                        }
                        $pid = (int)($p['id_post'] ?? 0);
                        $postUrl = "post.php?id=" . $pid . "&perfil=" . $idPerfil;
                        $isLiked = $pid && isset($likedPosts[$pid]);
                        $likesCount = (int)($p['likes_count'] ?? 0);
                        $rating = isset($p['rating']) ? (int)$p['rating'] : 0;
                        if ($rating < 1 || $rating > 5) $rating = 0;
                        $comments = $pid && isset($commentsByPost[$pid]) ? $commentsByPost[$pid] : [];
                    ?>
                    <article class="card">
                        <!-- Enlace que envuelve la imagen de la publicación, lleva a la página de la publicación, se muestra el poster de TMDB si está disponible, si no se muestra la primera imagen asociada a la publicación, y si no hay imágenes se muestra una imagen por defecto -->
                        <a class="card-media" href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ver publicación">
                            <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($tmdbTitle !== '' ? ('Poster de ' . $tmdbTitle) : 'Publicación', ENT_QUOTES, 'UTF-8'); ?>">
                        </a>
                        <div class="card-content">
                            <div class="card-header">
                                <div class="card-header-text">
                                    <h3><a class="card-title-link" href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
                                    <a class="card-date-link" href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(format_fecha_sin_segundos($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a>
                                </div>
                                <?php if ($esPropio): ?>
                                    <div class="card-actions-inline">
                                        <a class="card-action" href="publicarsubir.php?edit=<?php echo $pid; ?>&from=perfil" title="Editar publicación" aria-label="Editar publicación">
                                            <svg class="icon-svg icon-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="24" height="24" aria-hidden="true">
                                                <path d="M3 21v-3.6a2 2 0 0 1 .6-1.4l11-11a2 2 0 0 1 2.8 0l1.6 1.6a2 2 0 0 1 0 2.8l-11 11a2 2 0 0 1-1.4.6H3z"></path>
                                                <path d="M14 6l4 4"></path>
                                            </svg>
                                        </a>
                                        <a class="card-action card-action-delete" href="eliminar_post.php?id=<?php echo $pid; ?>&from=perfil" onclick="return confirm('¿Seguro que quieres borrar esta publicación?')" title="Borrar publicación" aria-label="Borrar publicación">
                                            <svg class="icon-svg icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="24" height="24" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                                            </svg>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Categorías asociadas a la publicación, mostradas como chips, solo si hay categorías -->
                            <?php if (count($cats)) : ?>
                                <div class="tags" style="margin-bottom: 10px;">
                                    <?php foreach ($cats as $c) : ?>
                                        <button type="button" class="tag"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Contenido de la publicación, se muestra limitado a 300 caracteres para evitar sobrecargar la tarjeta, se escapa para evitar problemas de seguridad -->
                            <p class="review-copy collapsed"><?php echo nl2br(htmlspecialchars($p['contenido'], ENT_QUOTES, 'UTF-8')); ?></p>
                            <div class="rating-pill" aria-label="Calificación de la reseña">
                                <span class="rating-stars" aria-hidden="true"><?php echo str_repeat('<span class="rating-star">★</span>', $rating) . str_repeat('<span class="rating-star empty">★</span>', 5 - $rating); ?></span>
                                <span><?php echo $rating > 0 ? $rating . '/5' : 'Sin calificar'; ?></span>
                            </div>
                            <div class="card-footer">
                                <!-- Botón para mostrar más o menos del contenido de la publicación, cambia su texto dependiendo de si el contenido está expandido o no -->
                                <button type="button" class="toggle-review">Mostrar más</button>
                                <!-- Acciones para interactuar con la publicación: me gusta, comentar, y un enlace para ver la publicación, el botón de me gusta cambia su apariencia si el usuario ya ha marcado la publicación como "me gusta" -->
                                <div class="card-icons" aria-label="Acciones">
                                    <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $pid; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                                        <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $pid; ?>" aria-label="Comentar">💬</button>
                                    <span class="post-likecount" data-post-id="<?php echo $pid; ?>">❤ <?php echo $likesCount; ?></span>
                                    <a class="icon-btn" href="<?php echo htmlspecialchars($postUrl, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Ver publicación">↗</a>
                                </div>
                            </div>
                            
                            <!-- Sección de comentarios, inicialmente oculta, se muestra al hacer clic en el botón de comentar, muestra un formulario para agregar un nuevo comentario y una lista de comentarios existentes asociados a la publicación -->
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
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>


        </main>
    </div>
    <script src="js/cinedbg.js"></script>
    <script src="lib/cropper.min.js"></script>
    <script>
    console.log("inline test - script works");
    </script>
    <script src="js/app.js?v=7"></script>
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
