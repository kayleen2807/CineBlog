<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 'visitante';
if ($rol !== 'editor' && $rol !== 'admin') {
    header("Location: index.php");
    exit();
}

function mb_lower_safe(string $value): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

function mb_len_safe(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    return strlen($value);
}

function normalize_for_moderation_spaces(string $text): string
{
    $value = mb_lower_safe($text);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }

    $map = [
        '@' => 'a',
        '4' => 'a',
        'á' => 'a',
        'à' => 'a',
        'ä' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        '€' => 'e',
        '3' => 'e',
        'é' => 'e',
        'è' => 'e',
        'ë' => 'e',
        'ê' => 'e',
        '1' => 'i',
        '!' => 'i',
        '|' => 'i',
        'í' => 'i',
        'ì' => 'i',
        'ï' => 'i',
        'î' => 'i',
        '0' => 'o',
        'ó' => 'o',
        'ò' => 'o',
        'ö' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ø' => 'o',
        '$' => 's',
        '5' => 's',
        '§' => 's',
        '7' => 't',
        '+' => 't',
        '2' => 'z',
        'ñ' => 'n',
        'ç' => 'c',
    ];

    $value = strtr($value, $map);

    // Cualquier cosa que no sea letra se vuelve espacio (para conservar límites de palabra).
    $value = preg_replace('/[^a-z]+/i', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function contains_profanity(string $text): bool
{
    $normalized = normalize_for_moderation_spaces($text);
    if ($normalized === '') return false;

    $tokens = preg_split('/\s+/', $normalized) ?: [];

    // Lista corta de groserías comunes (ES)
    $badWords = [
        'puta',
        'puto',
        'puts',
        'pt',
        'mierda',
        'pendejo',
        'pendeja',
        'cabron',
        'chingar',
        'chingada',
        'verga',
        'culero',
        'pinche',
        'joder',
        'idiota',
        'imbecil',
        'alv',
        'alm',
        'vtalv',
        'ctm',
        'putos',
        'chingados',
        'vergas',
        'culeros',
        'pinches',
        'jodidos',
        'idiotas',
        'imbeciles',
        'culo',
        'culito',
        'pene',
        'penes',
        'pito',
        'pitos',
        'pija',
        'pijas',
        'polla',
        'pollas',
        'vagina',
        'coño',
    ];

    foreach ($badWords as $bad) {
        // Detecta con letras separadas por espacios/símbolos: p u t a, p* u + t @, etc.
        $chars = preg_split('//u', $bad, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '(?:^|\s)';
        $total = count($chars);
        foreach ($chars as $i => $ch) {
            $regex .= preg_quote($ch, '/') . '+';
            if ($i < $total - 1) $regex .= '\s*';
        }
        $regex .= '(?:\s|$)';
        if (preg_match('/' . $regex . '/i', $normalized)) return true;

        $len = strlen($bad);

        // Detecta token exacto; para palabras muy cortas evita "startswith/endswith" para no dar falsos positivos.
        foreach ($tokens as $token) {
            if ($token === $bad) return true;
            if ($len >= 5 && (str_starts_with($token, $bad) || str_ends_with($token, $bad))) return true;
        }
    }

    return false;
}

function db_connect(): mysqli
{
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

function ensure_post_tables(mysqli $conn): void
{
    $conn->query("CREATE TABLE IF NOT EXISTS post_imagenes (
        id_imagen INT NOT NULL AUTO_INCREMENT,
        post_id INT NOT NULL,
        ruta VARCHAR(255) NOT NULL,
        PRIMARY KEY (id_imagen),
        KEY (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS post_categorias (
        post_id INT NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        PRIMARY KEY (post_id, categoria),
        KEY (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS post_tmdb (
        post_id INT NOT NULL,
        tmdb_id INT NOT NULL,
        media_type ENUM('movie','tv') NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        poster_url VARCHAR(500) DEFAULT NULL,
        release_date VARCHAR(20) DEFAULT NULL,
        overview TEXT DEFAULT NULL,
        PRIMARY KEY (post_id),
        KEY (tmdb_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function safe_filename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^\w.\-]+/u', '_', $name) ?? 'archivo';
    return trim($name, '._-') ?: 'archivo';
}

#Lista de categorías permitidas para publicaciones
$categorias = [
    'Película',
    'Serie',
    'Acción',
    'Aventura',
    'Animación',
    'Ciencia ficción',
    'Comedia',
    'Drama',
    'Fantasía',
    'Romance',
    'Suspenso',
    'Terror',
    'Documental',
    'Reseñas',
    'Noticias',
    'Recomendaciones',
];

$mensaje = '';
$mensajeTipo = 'error';

$oldTmdbId = trim((string)($_POST['tmdb_id'] ?? ''));
$oldTmdbType = trim((string)($_POST['tmdb_type'] ?? ''));
$oldTmdbTitle = trim((string)($_POST['tmdb_title'] ?? ''));
$oldTmdbPoster = trim((string)($_POST['tmdb_poster'] ?? ''));
$oldTmdbRelease = trim((string)($_POST['tmdb_release_date'] ?? ''));
$oldTmdbOverview = trim((string)($_POST['tmdb_overview'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $contenido = trim((string)($_POST['contenido'] ?? ''));
    $categoriasSel = $_POST['categorias'] ?? [];
    if (!is_array($categoriasSel)) $categoriasSel = [];

    $tmdbId = filter_var($_POST['tmdb_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $tmdbType = trim((string)($_POST['tmdb_type'] ?? ''));
    $tmdbTitle = trim((string)($_POST['tmdb_title'] ?? ''));
    $tmdbPoster = trim((string)($_POST['tmdb_poster'] ?? ''));
    $tmdbReleaseDate = trim((string)($_POST['tmdb_release_date'] ?? ''));
    $tmdbOverview = trim((string)($_POST['tmdb_overview'] ?? ''));

    $hasTmdbSelection = $tmdbId !== null || $tmdbType !== '' || $tmdbTitle !== '';
    if ($hasTmdbSelection) {
        if ($tmdbId === null || !in_array($tmdbType, ['movie', 'tv'], true) || $tmdbTitle === '') {
            $mensaje = 'La pelicula/serie seleccionada no es valida. Intenta seleccionarla otra vez.';
        }

        if (strlen($tmdbTitle) > 255) $tmdbTitle = substr($tmdbTitle, 0, 255);
        if (strlen($tmdbPoster) > 500) $tmdbPoster = substr($tmdbPoster, 0, 500);
        if (strlen($tmdbReleaseDate) > 20) $tmdbReleaseDate = substr($tmdbReleaseDate, 0, 20);
        if (strlen($tmdbOverview) > 2000) $tmdbOverview = substr($tmdbOverview, 0, 2000);
    }

    if ($mensaje !== '') {
        // Ya existe error previo.
    } elseif ($titulo === '' || mb_len_safe($titulo) < 3) {
        $mensaje = 'El título debe tener al menos 3 caracteres.';
    } elseif ($contenido === '' || mb_len_safe($contenido) < 1) {
        $mensaje = 'El contenido no puede ir vacío.';
    } elseif (mb_len_safe($contenido) > 500) {
        $mensaje = 'El contenido no puede pasar de 500 caracteres.';
    } elseif (contains_profanity($titulo . ' ' . $contenido)) {
        $mensaje = 'No se puede publicar: se detectó lenguaje inapropiado.';
    } else {
        $imagenes = $_FILES['imagenes'] ?? null;
        $maxImages = 4;
        $maxSizeBytes = 5 * 1024 * 1024;
        $allowedExt = ['jpg', 'jpeg', 'png'];

        $selectedCount = 0;
        if ($imagenes && isset($imagenes['name']) && is_array($imagenes['name'])) {
            foreach ($imagenes['name'] as $name) {
                if ($name !== null && $name !== '') $selectedCount++;
            }
        }

        if ($selectedCount > $maxImages) {
            $mensaje = 'Solo puedes subir hasta 4 imágenes.';
        } else {
            $conn = db_connect();
            ensure_post_tables($conn);

            $stmt = $conn->prepare("INSERT INTO posts (titulo, contenido, autor_id, imagen) VALUES (?, ?, ?, NULL)");
            $autorId = (int)$_SESSION['usuario_id'];
            $stmt->bind_param("ssi", $titulo, $contenido, $autorId);
            $ok = $stmt->execute();

            if (!$ok) {
                $mensaje = 'Error al guardar la publicación.';
            } else {
                $postId = (int)$conn->insert_id;

                // Normaliza categorías: máximo 3, solo de lista permitida.
                $allowed = array_flip($categorias);
                $cats = [];
                foreach ($categoriasSel as $raw) {
                    $c = trim((string)$raw);
                    if ($c === '') continue;
                    if (!isset($allowed[$c])) continue;
                    if (in_array($c, $cats, true)) continue;
                    $cats[] = $c;
                }

                if (count($cats) > 3) {
                    $mensaje = 'Puedes escoger máximo 3 categorías.';
                } else {
                    if (!count($cats)) $cats = ['Película'];

                    $insertCat = $conn->prepare("INSERT INTO post_categorias (post_id, categoria) VALUES (?, ?)");
                    foreach ($cats as $cat) {
                        $insertCat->bind_param("is", $postId, $cat);
                        $insertCat->execute();
                    }
                    $insertCat->close();
                }

                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR;
                $publicPrefix = 'uploads/posts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $firstImagePath = null;
                if ($imagenes && isset($imagenes['tmp_name']) && is_array($imagenes['tmp_name'])) {
                    $insertImg = $conn->prepare("INSERT INTO post_imagenes (post_id, ruta) VALUES (?, ?)");

                    $idx = 0;
                    foreach ($imagenes['tmp_name'] as $i => $tmp) {
                        $name = (string)($imagenes['name'][$i] ?? '');
                        $err = (int)($imagenes['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        $size = (int)($imagenes['size'][$i] ?? 0);
                        if ($name === '' || $err === UPLOAD_ERR_NO_FILE) continue;

                        if ($err !== UPLOAD_ERR_OK) {
                            $mensaje = 'Una imagen no se pudo subir (error de archivo).';
                            break;
                        }
                        if ($size <= 0 || $size > $maxSizeBytes) {
                            $mensaje = 'Cada imagen debe pesar máximo 5MB.';
                            break;
                        }

                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExt, true)) {
                            $mensaje = 'Solo se permiten imágenes JPG o PNG.';
                            break;
                        }

                        $info = @getimagesize($tmp);
                        if ($info === false) {
                            $mensaje = 'Uno de los archivos no es una imagen válida.';
                            break;
                        }

                        $safe = safe_filename(pathinfo($name, PATHINFO_FILENAME));
                        $finalName = 'post_' . $postId . '_' . $idx . '_' . $safe . '.' . $ext;
                        $target = $uploadDir . $finalName;
                        $publicPath = $publicPrefix . $finalName;

                        if (!move_uploaded_file($tmp, $target)) {
                            $mensaje = 'No se pudo guardar una de las imágenes.';
                            break;
                        }

                        if ($firstImagePath === null) $firstImagePath = $publicPath;

                        $insertImg->bind_param("is", $postId, $publicPath);
                        $insertImg->execute();
                        $idx++;
                    }

                    $insertImg->close();
                }

                if ($mensaje === '') {
                    if ($hasTmdbSelection && $tmdbId !== null) {
                        $stmtTmdb = $conn->prepare("REPLACE INTO post_tmdb (post_id, tmdb_id, media_type, titulo, poster_url, release_date, overview) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmtTmdb->bind_param("iisssss", $postId, $tmdbId, $tmdbType, $tmdbTitle, $tmdbPoster, $tmdbReleaseDate, $tmdbOverview);
                        $stmtTmdb->execute();
                        $stmtTmdb->close();
                    }

                    if ($firstImagePath !== null) {
                        $stmtUp = $conn->prepare("UPDATE posts SET imagen = ? WHERE id_post = ?");
                        $stmtUp->bind_param("si", $firstImagePath, $postId);
                        $stmtUp->execute();
                        $stmtUp->close();
                    }

                    $mensajeTipo = 'ok';
                    $mensaje = 'Publicación guardada.';
                    header("Location: index.php");
                    exit();
                }
            }

            $stmt->close();
            $conn->close();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva publicación - CineBlog</title>
    <link rel="stylesheet" href="css/style_publicarsubir.css?v=3">
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
    <main class="ps-overlay">
        <section class="ps-modal" role="dialog" aria-modal="true" aria-label="Nueva publicación">
            <header class="ps-head">
                <h1>Nueva publicación</h1>
                <a class="ps-close" href="index.php" aria-label="Cerrar">×</a>
            </header>

            <?php if ($mensaje !== '') : ?>
                <div class="ps-alert <?php echo $mensajeTipo === 'ok' ? 'ok' : 'error'; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form class="ps-form" method="POST" enctype="multipart/form-data" novalidate>
                <div class="ps-grid">
                    <div class="ps-left">
                        <label class="ps-label" for="titulo">Título</label>
                        <input
                            class="ps-input"
                            id="titulo"
                            name="titulo"
                            type="text"
                            maxlength="120"
                            placeholder="Ej. ¿Qué película recomiendas?"
                            value="<?php echo htmlspecialchars($_POST['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >

                        <label class="ps-label" for="psTmdbSearch">Pelicula o serie (TMDB, opcional)</label>
                        <div class="ps-tmdb" id="psTmdb">
                            <div class="ps-tmdb-controls">
                                <input
                                    class="ps-input"
                                    id="psTmdbSearch"
                                    type="text"
                                    placeholder="Ej. Dune, The Last of Us..."
                                    autocomplete="off"
                                >
                                <select class="ps-select" id="psTmdbType">
                                    <option value="multi">Todo</option>
                                    <option value="movie">Peliculas</option>
                                    <option value="tv">Series</option>
                                </select>
                                <button class="ps-upbtn ps-tmdb-btn" type="button" id="psTmdbBtn">Buscar</button>
                            </div>

                            <input type="hidden" name="tmdb_id" id="tmdbId" value="<?php echo htmlspecialchars($oldTmdbId, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_type" id="tmdbType" value="<?php echo htmlspecialchars($oldTmdbType, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_title" id="tmdbTitle" value="<?php echo htmlspecialchars($oldTmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_poster" id="tmdbPoster" value="<?php echo htmlspecialchars($oldTmdbPoster, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_release_date" id="tmdbReleaseDate" value="<?php echo htmlspecialchars($oldTmdbRelease, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_overview" id="tmdbOverview" value="<?php echo htmlspecialchars($oldTmdbOverview, ENT_QUOTES, 'UTF-8'); ?>">

                            <div class="ps-tmdb-selected" id="psTmdbSelected"></div>
                            <div class="ps-tmdb-results" id="psTmdbResults" aria-live="polite"></div>
                        </div>

                        <label class="ps-label" for="contenido">Contenido</label>
                        <textarea
                            class="ps-textarea"
                            id="contenido"
                            name="contenido"
                            maxlength="500"
                            placeholder="¿Qué quieres compartir?"
                            required
                        ><?php echo htmlspecialchars($_POST['contenido'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div class="ps-meta">
                            <span id="psCount">0 / 500</span>
                            <span id="psImgsCount">Imágenes: 0 / 4</span>
                        </div>

                        <label class="ps-label">Categorías (máx 3)</label>
                        <div class="ps-catmeta">
                            <span id="psCatCount">Categorías: 0 / 3</span>
                        </div>
                        <div class="ps-catgrid" id="psCatGrid">
                            <?php
                            $selectedCats = $_POST['categorias'] ?? ['Película'];
                            if (!is_array($selectedCats)) $selectedCats = ['Película'];
                            $selectedCats = array_values(array_unique(array_filter(array_map('strval', $selectedCats))));
                            foreach ($categorias as $cat) {
                                $isOn = in_array($cat, $selectedCats, true) ? 'checked' : '';
                                echo '<label class="ps-cat">';
                                echo '<input type="checkbox" name="categorias[]" value="' . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . "\" $isOn>";
                                echo '<span>' . htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') . '</span>';
                                echo '</label>';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="ps-right">
                        <div class="ps-uplabel">Imágenes</div>
                        <div class="ps-drop" id="psDrop">
                            <div class="ps-drop-inner">
                                <button class="ps-upbtn" type="button" id="psPick">Subir imágenes</button>
                                <div class="ps-drop-hint">o arrastra y suelta aquí (PNG/JPG · máx 5MB c/u)</div>
                            </div>
                            <input class="ps-file" id="imagenes" name="imagenes[]" type="file" accept="image/png,image/jpeg" multiple>
                        </div>
                        <div class="ps-thumbs" id="psThumbs" aria-label="Vista previa de imágenes"></div>
                    </div>
                </div>

                <footer class="ps-actions">
                    <a class="ps-btn ghost" href="index.php">Cancelar</a>
                    <button class="ps-btn primary" type="submit">Publicar ✨</button>
                </footer>
            </form>
        </section>
    </main>

    <script src="js/publicarsubir.js?v=6"></script>
</body>
</html>
