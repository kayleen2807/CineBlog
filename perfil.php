<?php
// Archivo para mostrar el perfil del usuario, solo accesible si el usuario ha iniciado sesión
session_start();
if (!isset($_SESSION['usuario_id'])) {
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
//conexión a la base de datos para obtener la información del usuario
$conn = new mysqli("localhost", "root", "", "cineblog_db");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

$id_usuario = $_SESSION['usuario_id'];
$foto = "uploads/default.png";

// Foto de perfil (si la columna existe)
$hasFotoCol = false;
$colRes = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'foto_perfil'");
if ($colRes && $colRes->num_rows > 0) $hasFotoCol = true;
if ($colRes) $colRes->free();

if ($hasFotoCol) {
    $stmt = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado ? $resultado->fetch_assoc() : null;
    if (!empty($usuario['foto_perfil'])) $foto = $usuario['foto_perfil'];
    $stmt->close();
}

// Publicaciones del usuario
$misPosts = [];
$stmtPosts = $conn->prepare("
    SELECT
        p.id_post,
        p.titulo,
        p.contenido,
        p.fecha,
        GROUP_CONCAT(DISTINCT pc.categoria SEPARATOR '||') AS categorias,
        GROUP_CONCAT(DISTINCT pi.ruta SEPARATOR '||') AS imagenes
    FROM posts p
    LEFT JOIN post_categorias pc ON pc.post_id = p.id_post
    LEFT JOIN post_imagenes pi ON pi.post_id = p.id_post
    WHERE p.autor_id = ?
    GROUP BY p.id_post
    ORDER BY p.fecha DESC
    LIMIT 50
");
$stmtPosts->bind_param("i", $id_usuario);
$stmtPosts->execute();
$resPosts = $stmtPosts->get_result();
if ($resPosts) {
    while ($row = $resPosts->fetch_assoc()) $misPosts[] = $row;
    $resPosts->free();
}
$stmtPosts->close();

$likedPosts = [];
$commentsByPost = [];

if (count($misPosts)) {
    $postIds = array_map(fn ($r) => (int)$r['id_post'], $misPosts);
    $postIds = array_values(array_filter($postIds, fn ($v) => $v > 0));
    if (count($postIds)) {
        $idList = implode(',', $postIds);

        $resLikes = $conn->query("SELECT post_id FROM likes WHERE usuario_id = " . (int)$id_usuario . " AND post_id IN ($idList)");
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
    <title>Perfil CineBlog</title>
</head>
<body>

    <div class="page-shell">
        <nav class="head">
            <div class="logo">
                <a href="#" aria-label="CineBlog">
                    <img class="logo-c" src="css/cineBlog_Logo.png" alt="C">
                    <span class="logo-rest">CineBlog</span>
                </a>
            </div>
            <div class="navbar" aria-label="Navegación principal">
                <a href="#" class="nav-link active">Tendencias</a>
                <a href="#" class="nav-link">Estrenos</a>
                <a href="#" class="nav-link">Recomendado</a>
            </div>
        </nav>

        <header class="header-container">
            <div class="cover-photo" role="img" aria-label="Foto de portada"></div>
            <div class="profile-section">
                <div class="profile-pic-container">
                    <img src="<?php echo $foto; ?>" alt="Foto de perfil" class="profile-pic">
                    <form action="subirFoto.php" method="POST" enctype="multipart/form-data" class="form_foto">
                        <label for="foto" class="file-label">Seleccionar foto</label>
                        <input type="file" name="foto" id="foto" accept="image/*" required>
                        <button type="submit" class="btn_foto">Actualizar foto</button>
                    </form>
                </div>
                <div class="user-info">
                    <h1><?php echo $_SESSION['nombre']; ?></h1>
                </div>
            </div>
        </header>

        <nav class="tab-nav" aria-label="Secciones del perfil">
            <a href="#" class="tab">My Reviews</a>
            <a href="#" class="tab">Likes</a>
            <a href="#" class="tab">Settings</a>
        </nav>

        <main class="main-layout">

            <section class="left-column">
                <h2 class="section-title">Mis publicaciones</h2>

                <?php if (!count($misPosts)) : ?>
                    <div class="sidebar-box">
                        <p style="color: var(--muted); font-weight: 600;">Todavía no has publicado nada.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($misPosts as $p) : ?>
                    <?php
                        $cats = [];
                        if (!empty($p['categorias'])) $cats = array_values(array_filter(explode('||', (string)$p['categorias'])));
                        $imgs = [];
                        if (!empty($p['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$p['imagenes'])));
                        $img = count($imgs) ? $imgs[0] : "css/cineBlog_Logo.png";
                        $pid = (int)($p['id_post'] ?? 0);
                        $isLiked = $pid && isset($likedPosts[$pid]);
                        $comments = $pid && isset($commentsByPost[$pid]) ? $commentsByPost[$pid] : [];
                    ?>
                    <article class="card">
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="Publicación">
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($p['titulo'], ENT_QUOTES, 'UTF-8'); ?></h3>

                            <div style="color: var(--muted); font-weight: 600; margin-bottom: 10px;">
                                <?php echo htmlspecialchars(format_fecha_sin_segundos($p['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <?php if (count($cats)) : ?>
                                <div class="tags" style="margin-bottom: 10px;">
                                    <?php foreach ($cats as $c) : ?>
                                        <button type="button" class="tag"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <p class="review-copy collapsed"><?php echo nl2br(htmlspecialchars($p['contenido'], ENT_QUOTES, 'UTF-8')); ?></p>
                            <div class="card-footer">
                                <button type="button" class="toggle-review">Mostrar más</button>
                                <div class="card-icons" aria-label="Acciones">
                                    <button type="button" class="icon-btn icon-heart like-btn <?php echo $isLiked ? 'liked' : ''; ?>" data-post-id="<?php echo $pid; ?>" aria-label="Me gusta" aria-pressed="<?php echo $isLiked ? 'true' : 'false'; ?>">
                                        <svg class="heart-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                            <path class="heart-path" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" class="icon-btn comment-btn" data-post-id="<?php echo $pid; ?>" aria-label="Comentar">💬</button>
                                    <button type="button" class="icon-btn" aria-label="Compartir">↗</button>
                                </div>
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
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <aside class="right-column">
                <div class="sidebar-box">
                    <h3>Best Reviewed</h3>
                    <div class="best-grid">
                        <div class="best-slot">
                            <img class="best-img" src="assets/poster-1.jpeg" alt="Best reviewed 1">
                        </div>
                        <div class="best-slot">
                            <img class="best-img" src="assets/poster-2.jpg" alt="Best reviewed 2">
                        </div>
                    </div>
                </div>

                <div class="sidebar-box">
                    <h3>Favorite Genres</h3>
                    <div class="tags">
                        <button type="button" class="tag">Comedy</button>
                        <button type="button" class="tag">Anime</button>
                        <button type="button" class="tag">Drama</button>
                        <button type="button" class="tag">Suspense</button>
                    </div>
                </div>
            </aside>

        </main>
    </div>

    <script src="app.js?v=3"></script>
</body>
</html>
