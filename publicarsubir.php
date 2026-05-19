<?php
session_start();
// Evitar caché
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

// 🔹 Solo editores y admins pueden publicar
$rol = $_SESSION['rol'] ?? 'visitante';
if ($rol !== 'editor' && $rol !== 'admin' && $rol !== 'moderador') {
    header("Location: inicioSesion.php");
    exit();
}

// Funcion de normalización y detección de lenguaje inapropiado (para títulos y contenido de publicaciones)
function mb_lower_safe(string $value): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

// Función para contar longitud de string de forma segura con multibytes
function mb_len_safe(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    return strlen($value);
}

//Función para normalizar texto y detectar lenguaje inapropiado incluso con caracteres especiales o separados por espacios/símbolos
function normalize_for_moderation_spaces(string $text): string
{
    $value = mb_lower_safe($text);
     // Transliteración básica para convertir caracteres acentuados a su forma base, y algunos símbolos comunes usados para evadir filtros.
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

//Funcion para detectar lenguaje inapropiado incluso con caracteres especiales o separados por espacios/símbolos
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

// Función para conectar a la base de datos
function db_connect(): mysqli
{
    $conn = new mysqli("localhost", "root", "", "cineblog_db");
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// Función para asegurar que las tablas necesarias para posts existen (imágenes, categorías, TMDB)
function ensure_post_tables(mysqli $conn): void
{
    // Tabla principal de posts
    $conn->query("CREATE TABLE IF NOT EXISTS post_imagenes (
        id_imagen INT NOT NULL AUTO_INCREMENT,
        post_id INT NOT NULL,
        ruta VARCHAR(255) NOT NULL,
        PRIMARY KEY (id_imagen),
        KEY (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Tabla de categorías (relación muchos a muchos)
    $conn->query("CREATE TABLE IF NOT EXISTS post_categorias (
        post_id INT NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        PRIMARY KEY (post_id, categoria),
        KEY (post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // Tabla para almacenar relación de posts con TMDB (película/serie seleccionada)
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

// Función para generar un nombre de archivo seguro a partir del original, evitando caracteres problemáticos.
function safe_filename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^\w.\-]+/u', '_', $name) ?? 'archivo';
    return trim($name, '._-') ?: 'archivo';
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$isEdit = $editId > 0;
$returnTo = ($_GET['from'] ?? '') === 'perfil' ? 'perfil' : 'index';

$editPost = null;
$existingCats = [];
$existingImages = [];
$existingTmdb = null;

if ($isEdit) {
    $conn = db_connect();
    ensure_post_tables($conn);

    $stmt = $conn->prepare("SELECT id_post, titulo, contenido, autor_id FROM posts WHERE id_post = ?");
    $stmt->bind_param("i", $editId);
    $stmt->execute();
    $res = $stmt->get_result();
    $editPost = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$editPost) {
        $conn->close();
        die("Publicacion no encontrada");
    }

    $autorId = (int)($editPost['autor_id'] ?? 0);
    $canEdit = $rol === 'admin' || $rol === 'moderador' || (int)$_SESSION['usuario_id'] === $autorId;
    if (!$canEdit) {
        $conn->close();
        die("Acceso denegado");
    }

    $resCats = $conn->query("SELECT categoria FROM post_categorias WHERE post_id = " . (int)$editId);
    if ($resCats) {
        while ($row = $resCats->fetch_assoc()) $existingCats[] = (string)$row['categoria'];
        $resCats->free();
    }

    $resImgs = $conn->query("SELECT ruta FROM post_imagenes WHERE post_id = " . (int)$editId . " ORDER BY id_imagen ASC");
    if ($resImgs) {
        while ($row = $resImgs->fetch_assoc()) $existingImages[] = (string)$row['ruta'];
        $resImgs->free();
    }

    $resTmdb = $conn->query("SELECT tmdb_id, media_type, titulo, poster_url, release_date, overview FROM post_tmdb WHERE post_id = " . (int)$editId . " LIMIT 1");
    if ($resTmdb) {
        $existingTmdb = $resTmdb->fetch_assoc() ?: null;
        $resTmdb->free();
    }

    $conn->close();
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

// Variables para mantener selección de TMDB en caso de error de validación
$oldTmdbId = trim((string)($_POST['tmdb_id'] ?? ''));
$oldTmdbType = trim((string)($_POST['tmdb_type'] ?? ''));
$oldTmdbTitle = trim((string)($_POST['tmdb_title'] ?? ''));
$oldTmdbPoster = trim((string)($_POST['tmdb_poster'] ?? ''));
$oldTmdbRelease = trim((string)($_POST['tmdb_release_date'] ?? ''));
$oldTmdbOverview = trim((string)($_POST['tmdb_overview'] ?? ''));

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST' && $existingTmdb) {
    $oldTmdbId = (string)($existingTmdb['tmdb_id'] ?? '');
    $oldTmdbType = (string)($existingTmdb['media_type'] ?? '');
    $oldTmdbTitle = (string)($existingTmdb['titulo'] ?? '');
    $oldTmdbPoster = (string)($existingTmdb['poster_url'] ?? '');
    $oldTmdbRelease = (string)($existingTmdb['release_date'] ?? '');
    $oldTmdbOverview = (string)($existingTmdb['overview'] ?? '');
}

// Manejo del formulario al enviar la publicación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $editId = filter_var($_POST['edit_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $isEdit = $editId > 0;
    $returnTo = ($_POST['return_to'] ?? '') === 'perfil' ? 'perfil' : $returnTo;

    if ($isEdit) {
        $conn = db_connect();
        ensure_post_tables($conn);

        $stmt = $conn->prepare("SELECT autor_id, imagen FROM posts WHERE id_post = ?");
        $stmt->bind_param("i", $editId);
        $stmt->execute();
        $res = $stmt->get_result();
        $editPost = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$editPost) {
            $conn->close();
            die("Publicacion no encontrada");
        }

        $autorId = (int)($editPost['autor_id'] ?? 0);
        $canEdit = $rol === 'admin' || $rol === 'moderador' || (int)$_SESSION['usuario_id'] === $autorId;
        if (!$canEdit) {
            $conn->close();
            die("Acceso denegado");
        }

        $existingImages = [];
        $resImgs = $conn->query("SELECT ruta FROM post_imagenes WHERE post_id = " . (int)$editId);
        if ($resImgs) {
            while ($row = $resImgs->fetch_assoc()) $existingImages[] = (string)$row['ruta'];
            $resImgs->free();
        }

        $conn->close();
    }

    // Validación de campos de texto
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $contenido = trim((string)($_POST['contenido'] ?? ''));
    $categoriasSel = $_POST['categorias'] ?? [];
    if (!is_array($categoriasSel)) $categoriasSel = [];

    // Validación de selección de película/serie de TMDB (si se hizo)
    $tmdbId = filter_var($_POST['tmdb_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $tmdbType = trim((string)($_POST['tmdb_type'] ?? ''));
    $tmdbTitle = trim((string)($_POST['tmdb_title'] ?? ''));
    $tmdbPoster = trim((string)($_POST['tmdb_poster'] ?? ''));
    $tmdbReleaseDate = trim((string)($_POST['tmdb_release_date'] ?? ''));
    $tmdbOverview = trim((string)($_POST['tmdb_overview'] ?? ''));

    // Si hay algún dato relacionado con TMDB, se consideran como selección de película/serie, y se validan en conjunto.
    $hasTmdbSelection = $tmdbId !== null || $tmdbType !== '' || $tmdbTitle !== '';
    if ($hasTmdbSelection) {
        if ($tmdbId === null || !in_array($tmdbType, ['movie', 'tv'], true) || $tmdbTitle === '') {
            $mensaje = 'La pelicula/serie seleccionada no es valida. Intenta seleccionarla otra vez.';
        }

        // Para evitar problemas de almacenamiento, se limitan las longitudes de los campos relacionados con TMDB
        if (strlen($tmdbTitle) > 255) $tmdbTitle = substr($tmdbTitle, 0, 255);
        if (strlen($tmdbPoster) > 500) $tmdbPoster = substr($tmdbPoster, 0, 500);
        if (strlen($tmdbReleaseDate) > 20) $tmdbReleaseDate = substr($tmdbReleaseDate, 0, 20);
        if (strlen($tmdbOverview) > 2000) $tmdbOverview = substr($tmdbOverview, 0, 2000);
    }

    // Validación de campos de texto y contenido, solo si no hay error previo
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
        // Validación de imágenes
        $imagenes = $_FILES['imagenes'] ?? null;
        $maxImages = 4;
        $maxSizeBytes = 5 * 1024 * 1024;
        $allowedExt = ['jpg', 'jpeg', 'png'];

        // Contar solo los archivos que realmente se intentaron subir (ignorando inputs vacíos)
        $selectedCount = 0;
        if ($imagenes && isset($imagenes['name']) && is_array($imagenes['name'])) {
            foreach ($imagenes['name'] as $name) {
                if ($name !== null && $name !== '') $selectedCount++;
            }
        }

        // Validar cantidad de imágenes seleccionadas
        $existingCount = $isEdit ? count($existingImages) : 0;
        if ($selectedCount + $existingCount > $maxImages) {
            $mensaje = 'Solo puedes subir hasta 4 imágenes.';
        } else {
            $conn = db_connect();
            ensure_post_tables($conn);
            // Insertar o actualizar post principal
            if ($isEdit) {
                if ($rol === 'admin') {
                    $stmt = $conn->prepare("UPDATE posts SET titulo = ?, contenido = ?, editado_por_admin = 1 WHERE id_post = ?");
                    $stmt->bind_param("ssi", $titulo, $contenido, $editId);
                } else {
                    $stmt = $conn->prepare("UPDATE posts SET titulo = ?, contenido = ? WHERE id_post = ?");
                    $stmt->bind_param("ssi", $titulo, $contenido, $editId);
                }
                $ok = $stmt->execute();
                $stmt->close();
                $postId = $editId;
            } else {
                $stmt = $conn->prepare("INSERT INTO posts (titulo, contenido, autor_id, imagen) VALUES (?, ?, ?, NULL)");
                $autorId = (int)$_SESSION['usuario_id'];
                $stmt->bind_param("ssi", $titulo, $contenido, $autorId);
                $ok = $stmt->execute();
                $postId = (int)$conn->insert_id;
                $stmt->close();
            }

            // Si no se pudo guardar, no se procede con imágenes ni categorías.
            if (!$ok) {
                $mensaje = 'Error al guardar la publicación.';
            } else {

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
                $requiredCat = null;
                if ($tmdbType === 'tv') $requiredCat = 'Serie';
                if ($tmdbType === 'movie') $requiredCat = 'Película';

                if ($requiredCat !== null) {
                    $remove = $requiredCat === 'Serie' ? 'Película' : 'Serie';
                    $cats = array_values(array_filter($cats, fn ($c) => $c !== $remove));
                    if (!in_array($requiredCat, $cats, true)) $cats[] = $requiredCat;
                }
                // Si no se selecciona ninguna categoría válida, se asigna "Película" por defecto.
                if (count($cats) > 3) {
                    if ($requiredCat !== null) {
                        $trimmed = [$requiredCat];
                        foreach ($cats as $c) {
                            if ($c === $requiredCat) continue;
                            $trimmed[] = $c;
                            if (count($trimmed) >= 3) break;
                        }
                        $cats = $trimmed;
                    } else {
                        $mensaje = 'Puedes escoger máximo 3 categorías.';
                    }
                }

                if ($mensaje === '') {
                    // Insertar categorías seleccionadas
                    if (!count($cats)) $cats = ['Película'];
                    $conn->query("DELETE FROM post_categorias WHERE post_id = " . (int)$postId);
                    $insertCat = $conn->prepare("INSERT INTO post_categorias (post_id, categoria) VALUES (?, ?)");
                    foreach ($cats as $cat) {
                        $insertCat->bind_param("is", $postId, $cat);
                        $insertCat->execute();
                    }
                    $insertCat->close();
                }

                // Manejo de imágenes: se validan, se guardan en el servidor, y se insertan sus rutas en la base de datos
                $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'posts' . DIRECTORY_SEPARATOR;
                $publicPrefix = 'uploads/posts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                // Para mostrar una imagen representativa del post, se guarda la ruta de la primera imagen subida en la tabla principal de posts. Las demás imágenes se guardan solo en la tabla de post_imagenes.
                $firstImagePath = null;
                if ($imagenes && isset($imagenes['tmp_name']) && is_array($imagenes['tmp_name'])) {
                    $insertImg = $conn->prepare("INSERT INTO post_imagenes (post_id, ruta) VALUES (?, ?)");

                    // Se itera sobre los archivos subidos, validando cada uno y guardándolo si es correcto
                    $idx = 0;
                    foreach ($imagenes['tmp_name'] as $i => $tmp) {
                        $name = (string)($imagenes['name'][$i] ?? '');
                        $err = (int)($imagenes['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        $size = (int)($imagenes['size'][$i] ?? 0);
                        if ($name === '' || $err === UPLOAD_ERR_NO_FILE) continue;

                        // Si hay algún error con una imagen, se detiene el proceso de guardado de imágenes y se muestra el error.
                        if ($err !== UPLOAD_ERR_OK) {
                            $mensaje = 'Una imagen no se pudo subir (error de archivo).';
                            break;
                        }
                        // Validar tamaño y tipo de archivo
                        if ($size <= 0 || $size > $maxSizeBytes) {
                            $mensaje = 'Cada imagen debe pesar máximo 5MB.';
                            break;
                        }
                        // Validar extensión del archivo (basado en el nombre original, no es 100% seguro pero ayuda a filtrar)
                        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        if (!in_array($ext, $allowedExt, true)) {
                            $mensaje = 'Solo se permiten imágenes JPG o PNG.';
                            break;
                        }
                        // Validar que el archivo es realmente una imagen (esto ayuda a evitar que se suban archivos maliciosos con extensión de imagen)
                        $info = @getimagesize($tmp);
                        if ($info === false) {
                            $mensaje = 'Uno de los archivos no es una imagen válida.';
                            break;
                        }
                        // Generar un nombre de archivo seguro y único para evitar colisiones y problemas de seguridad.
                        $safe = safe_filename(pathinfo($name, PATHINFO_FILENAME));
                        $finalName = 'post_' . $postId . '_' . $idx . '_' . $safe . '.' . $ext;
                        $target = $uploadDir . $finalName;
                        $publicPath = $publicPrefix . $finalName;
                        // Mover el archivo subido a la ubicación final
                        if (!move_uploaded_file($tmp, $target)) {
                            $mensaje = 'No se pudo guardar una de las imágenes.';
                            break;
                        }
                        // Si esta es la primera imagen válida, se guarda su ruta para actualizar el campo "imagen" del post principal, que se usará como imagen representativa del post.
                        if ($firstImagePath === null) $firstImagePath = $publicPath;

                        $insertImg->bind_param("is", $postId, $publicPath);
                        $insertImg->execute();
                        $idx++;
                    }

                    $insertImg->close();
                }
                // Si no hubo ningún error durante el proceso, se guardan los datos relacionados con TMDB (si se seleccionó)
                if ($mensaje === '') {
                    if ($hasTmdbSelection && $tmdbId !== null) {
                        $stmtTmdb = $conn->prepare("REPLACE INTO post_tmdb (post_id, tmdb_id, media_type, titulo, poster_url, release_date, overview) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmtTmdb->bind_param("iisssss", $postId, $tmdbId, $tmdbType, $tmdbTitle, $tmdbPoster, $tmdbReleaseDate, $tmdbOverview);
                        $stmtTmdb->execute();
                        $stmtTmdb->close();
                    } elseif ($isEdit) {
                        $conn->query("DELETE FROM post_tmdb WHERE post_id = " . (int)$postId);
                    }

                    // Si se subió al menos una imagen y no existe imagen principal, se actualiza imagen
                    if ($firstImagePath !== null && (!$isEdit || $existingCount === 0)) {
                        $stmtUp = $conn->prepare("UPDATE posts SET imagen = ? WHERE id_post = ?");
                        $stmtUp->bind_param("si", $firstImagePath, $postId);
                        $stmtUp->execute();
                        $stmtUp->close();
                    }
                    // Mensajes de exito y redirección
                    $mensajeTipo = 'ok';
                    $mensaje = $isEdit ? 'Publicación actualizada.' : 'Publicación guardada.';
                    if ($returnTo === 'perfil') {
                        header("Location: perfil.php");
                    } else {
                        $redirectType = in_array('Serie', $cats, true) ? 'series' : 'movies';
                        header("Location: index.php?tipo=" . $redirectType);
                    }
                    exit();
                }
            }
            $conn->close();
        }
    }
}

$pageTitle = $isEdit ? 'Editar publicacion - CineBlog' : 'Nueva publicacion - CineBlog';
$formTitle = $isEdit ? 'Editar publicacion' : 'Nueva publicacion';
$submitLabel = $isEdit ? 'Guardar cambios' : 'Publicar ✨';
$cancelHref = $returnTo === 'perfil' ? 'perfil.php' : 'index.php';

$defaultTitulo = $_POST['titulo'] ?? ($editPost['titulo'] ?? '');
$defaultContenido = $_POST['contenido'] ?? ($editPost['contenido'] ?? '');

$selectedCats = $_POST['categorias'] ?? ($existingCats ?: ['Película']);
if (!is_array($selectedCats)) $selectedCats = ['Película'];
$selectedCats = array_values(array_unique(array_filter(array_map('strval', $selectedCats))));

$existingImagesJson = htmlspecialchars(json_encode($existingImages), ENT_QUOTES, 'UTF-8');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="css/style_switch.css">
    <link rel="stylesheet" href="css/style_publicarsubir.css?v=3">
    <!-- 🔹 Estilos globales de tema -->
    <link rel="stylesheet" href="css/temas.css">
    <!-- 🔹 Script global de tema -->
    <script src="js/temas.js" defer></script>
</head>
<body>
    <main class="ps-overlay">
        <section class="ps-modal" role="dialog" aria-modal="true" aria-label="<?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?>">
            <!-- Encabezado del modal -->
            <header class="ps-head">
                <h1><?php echo htmlspecialchars($formTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <!-- 🔹 Switch de tema (arriba a la derecha) -->
                <div class="theme-toggle"style="margin-right: 55px; margin-top: 15px;">
                    <input type="checkbox" id="theme-switch">
                    <label for="theme-switch" class="switch">
                        <span class="icon-sun">☀️</span>
                        <span class="icon-moon">🌙</span>
                    </label>
                </div>
                <a class="ps-close" href="<?php echo htmlspecialchars($cancelHref, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Cerrar">×</a>
            </header>
            <!-- Mensajes de alerta -->
            <?php if ($mensaje !== '') : ?>
                <div class="ps-alert <?php echo $mensajeTipo === 'ok' ? 'ok' : 'error'; ?>">
                    <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <!-- Formulario de publicación / post -->
            <form class="ps-form" method="POST" enctype="multipart/form-data" novalidate>
                <?php if ($isEdit): ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int)$editId; ?>">
                <?php endif; ?>
                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="ps-grid">
                    <div class="ps-left">
                        <!-- Campo del Titulo-->
                        <label class="ps-label" for="titulo">Título</label>
                        <input
                            class="ps-input"
                            id="titulo"
                            name="titulo"
                            type="text"
                            maxlength="120"
                            placeholder="Ej. ¿Qué película recomiendas?"
                            value="<?php echo htmlspecialchars($defaultTitulo, ENT_QUOTES, 'UTF-8'); ?>"
                            required
                        >
                        <!-- Campo de búsqueda de películas o series (TMDB, opcional) -->
                        <label class="ps-label" for="psTmdbSearch">Pelicula o serie (opcional)</label>
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
                            <!-- Campos ocultos para almacenar los datos de la película o serie seleccionada -->
                            <input type="hidden" name="tmdb_id" id="tmdbId" value="<?php echo htmlspecialchars($oldTmdbId, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_type" id="tmdbType" value="<?php echo htmlspecialchars($oldTmdbType, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_title" id="tmdbTitle" value="<?php echo htmlspecialchars($oldTmdbTitle, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_poster" id="tmdbPoster" value="<?php echo htmlspecialchars($oldTmdbPoster, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_release_date" id="tmdbReleaseDate" value="<?php echo htmlspecialchars($oldTmdbRelease, ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="tmdb_overview" id="tmdbOverview" value="<?php echo htmlspecialchars($oldTmdbOverview, ENT_QUOTES, 'UTF-8'); ?>">
                            <!-- Área para mostrar la película o serie seleccionada -->
                            <div class="ps-tmdb-selected" id="psTmdbSelected"></div>
                            <div class="ps-tmdb-results" id="psTmdbResults" aria-live="polite"></div>
                        </div>
                        <!-- Campo del Contenido-->
                        <label class="ps-label" for="contenido">Contenido</label>
                        <textarea
                            class="ps-textarea"
                            id="contenido"
                            name="contenido"
                            maxlength="500"
                            placeholder="¿Qué quieres compartir?"
                            required
                        ><?php echo htmlspecialchars($defaultContenido, ENT_QUOTES, 'UTF-8'); ?></textarea> 
                         <!-- Contadores de caracteres e imágenes, y selección de categorías -->
                        <div class="ps-meta">
                            <span id="psCount">0 / 500</span>
                            <span id="psImgsCount">Imágenes: 0 / 4</span>
                        </div>

                        <label class="ps-label">Categorías (máx 3)</label>
                        <div class="ps-catmeta">
                            <span id="psCatCount">Categorías: 0 / 3</span>
                        </div>
                        <div class="ps-catgrid" id="psCatGrid">
                            <!-- Opciones de categoría -->
                            <?php
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
                    <!-- Campo de Imágenes (el usuario selecciona)-->
                    <div class="ps-right">
                        <div class="ps-uplabel">Imágenes</div>
                        <div class="ps-drop" id="psDrop">
                            <div class="ps-drop-inner">
                                <button class="ps-upbtn" type="button" id="psPick">Subir imágenes</button>
                                <div class="ps-drop-hint">o arrastra y suelta aquí (PNG/JPG · máx 5MB c/u)</div>
                            </div>
                            <input class="ps-file" id="imagenes" name="imagenes[]" type="file" accept="image/png,image/jpeg" multiple>
                        </div>
                        <div class="ps-thumbs" id="psThumbs" aria-label="Vista previa de imágenes" data-existing-images="<?php echo $existingImagesJson; ?>"></div>
                    </div>
                </div>
                <!-- Botones de acción, cancelar la publicacion/post o publicarla-->
                <footer class="ps-actions">
                    <a class="ps-btn ghost" href="<?php echo htmlspecialchars($cancelHref, ENT_QUOTES, 'UTF-8'); ?>">Cancelar</a>
                    <button class="ps-btn primary" type="submit"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></button>
                </footer>
            </form>
        </section>
    </main>

    <script src="js/publicarsubir.js?v=6"></script>
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
