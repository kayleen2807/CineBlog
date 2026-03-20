<?php
// Inicia la sesión para manejar la autenticación
session_start();

//si no hay rol en la sesión, redirige a inicioSesion.php
if(!isset($_SESSION['rol'])){
    header("Location: inicioSesion.php");
    exit();
}

if (!isset($_SESSION['usuario_id'])) {
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
<!--Maquetado en html para el inicio -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineBlog</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles_inicio.css">
    
</head>
<body>
    <style>
        a {
            text-decoration: none;
            color: #b9b9b9;
        }
    </style>
    
    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="sb-top">
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


        </div>

        <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <div class="sb-item">🔔 <span>Notificaciones</span></div>
            <?php elseif ($rol == "admin") : ?>
                <div class="sb-item">🔔 <span>Notificaciones</span></div>
            <?php endif; ?>

        <div class="sb-cats">

            <div class="cat-label">Categories</div>

            <div class="pills">
                <span class="pill on" onclick="selPill(this)">Movies</span>
                <span class="pill" onclick="selPill(this)">Horror</span>
                <span class="pill" onclick="selPill(this)">Action</span>
                <span class="pill" onclick="selPill(this)">Science fiction</span>
            </div>

        </div>

        <div class="sb-footer">

            <div class="sb-fitem">
                <span>Configuración</span>
                <span>⚙️</span>
            </div>

            <div class="sb-fitem">
            <span><a href="cerrarSesion.php">Cerrar sesión</a></span>
            <span>🚪</span>
            </div>

        </div>

    </aside>

    <!-- MAIN -->

    <div class="main">

        <header class="topbar">

        <div class="logo-text"><span>C</span>ineBlog</div>

        <span style="font-weight:600;text-decoration:underline;text-underline-offset:3px;cursor:pointer;">
        Tendencies
        </span>

        <div class="search-wrap">
            <span style="color:var(--muted)">🔍</span>
            <input type="text" placeholder="Search">
        </div>
        </header>

        <!-- AREA CENTRAL -->
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
                
    </div>

    <script>
        function selPill(el){
            el.closest('.pills').querySelectorAll('.pill').forEach(p=>p.classList.remove('on'));
            el.classList.add('on');
        }   
    </script>
    <script src="app.js?v=3"></script>

</body>
</html>
