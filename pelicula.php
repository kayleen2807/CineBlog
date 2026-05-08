<?php
session_start();

// Solo usuarios autenticados pueden ver esta página, los visitantes son redirigidos al inicio de sesión
if (!isset($_SESSION['usuario_id']) && (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'visitante')) {
    header('Location: inicioSesion.php');
    exit();
}

header('Content-Type: text/html; charset=utf-8');

// Validación y saneamiento de entrada
$tmdbId = filter_input(INPUT_GET, 'tmdb_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$mediaType = trim((string)($_GET['type'] ?? 'movie'));
// Solo se permiten 'movie' o 'tv', si no es válido se asume 'movie' por defecto
if (!in_array($mediaType, ['movie', 'tv'], true)) $mediaType = 'movie';

// Validación adicional para evitar valores no esperados
if ($tmdbId <= 0) {
    http_response_code(400);
    echo 'ID de titulo invalido.';
    exit();
}

// Funcion para obtener datos de TMDB con manejo básico de errores y tiempo de espera
function tmdb_get(string $path, array $params = []): ?array
{
    // Token de acceso a la API de TMDB (debe ser seguro y no expuesto públicamente en producción)
    $token = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI1ZTEyODBmMWVlYTcxZmJmNWI4ZjA4MzU3MDE5MTA3NCIsIm5iZiI6MTc3NjQzMjc3MS41ODcwMDAxLCJzdWIiOiI2OWUyMzY4M2RiY2EwYTZkYzlkMjE4ZDUiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.HK3ADaF_YdTo_2k26ndww-8DHa00GfBw534D0GRl-Us';
    $url = 'https://api.themoviedb.org/3/' . ltrim($path, '/'); //datos de peliculas y series

    // Por defecto se asume español de México, pero se puede sobrescribir con el parámetro 'language'
    if (!isset($params['language'])) $params['language'] = 'es-MX';
    $url .= '?' . http_build_query($params);

    // Realiza la solicitud HTTP a TMDB con cURL
    $ch = curl_init($url);
    if ($ch === false) return null;

    // Configuración de cURL con opciones seguras y tiempo de espera aceptable
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
    ]);

    // Ejecuta la solicitud y maneja posibles errores
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Si la respuesta no es exitosa (código HTTP 200) o no se obtuvo respuesta, retorna null
    if ($raw === false || $httpCode >= 400) return null;

    // Intenta decodificar la respuesta JSON, si falla retorna null
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// Funcion para formatear fechas
function format_date_short(?string $value): string
{
    $value = (string)$value;
    if ($value === '') return '';
    $ts = strtotime($value);
    if ($ts === false) return $value;
    return date('d/m/Y', $ts);
}

//Funcion para normalizar texto de filtros (tipo y categoría)
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

// Funcion para mapear géneros de TMDB a categorías usadas en la barra lateral de filtros, con normalización
function map_genre_to_sidebar(string $genre): string
{
    $norm = normalize_filter_value($genre);
    $map = [
        'science fiction' => 'ciencia ficcion',
        'sci-fi & fantasy' => 'ciencia ficcion',
        'horror' => 'terror',
        'thriller' => 'suspenso',
        'animation' => 'animacion',
        'action' => 'accion',
        'adventure' => 'aventura',
        'comedy' => 'comedia',
        'drama' => 'drama',
        'romance' => 'romance',
        'fantasy' => 'fantasia',
        'documentary' => 'documental',
        'movie' => 'pelicula',
        'tv movie' => 'pelicula',
    ];

    return $map[$norm] ?? $norm;
}

// Obtiene los datos del título (película o serie) desde TMDB usando la función definida, con manejo de errores
$tmdb = tmdb_get($mediaType . '/' . $tmdbId, ['append_to_response' => 'credits']);

$title = '';
$overview = '';
$posterUrl = '';
$backdropUrl = '';
$releaseDate = '';
$year = '';
$genres = [];
$director = 'No disponible';

// Si no se pudieron obtener los datos de TMDB, se muestra un mensaje de error y se detiene la ejecución
if ($tmdb) {
    // Extrae y normaliza los datos necesarios para mostrar en la ficha del título, con validaciones básicas
    $title = trim((string)($tmdb['title'] ?? $tmdb['name'] ?? ''));
    // El overview puede contener texto largo, se muestra tal cual pero se escapa para evitar problemas de seguridad, no se limita su longitud
    $overview = trim((string)($tmdb['overview'] ?? ''));

    // Construye las URLs completas para el poster y el backdrop si están disponibles, usando los tamaños recomendados por TMDB
    $posterPath = trim((string)($tmdb['poster_path'] ?? ''));
    $backdropPath = trim((string)($tmdb['backdrop_path'] ?? ''));
    if ($posterPath !== '') $posterUrl = 'https://image.tmdb.org/t/p/w500' . $posterPath;
    if ($backdropPath !== '') $backdropUrl = 'https://image.tmdb.org/t/p/w1280' . $backdropPath;
    
    // El release date puede venir en diferentes formatos o incluso no estar disponible, se intenta extraer el año para mostrarlo junto al título
    $releaseDate = trim((string)($tmdb['release_date'] ?? $tmdb['first_air_date'] ?? ''));
    // Si el release date tiene al menos 4 caracteres, se asume que el año está presente al inicio
    if (strlen($releaseDate) >= 4) $year = substr($releaseDate, 0, 4);

    // Extrae los géneros, asegurándose de que sean cadenas válidas, y los normaliza para mostrarlos como chips y usarlos en los filtros
    if (isset($tmdb['genres']) && is_array($tmdb['genres'])) {
        foreach ($tmdb['genres'] as $g) {
            if (!is_array($g)) continue;
            $name = trim((string)($g['name'] ?? ''));
            if ($name !== '') $genres[] = $name;
        }
    }

    // Para obtener el director, se revisa primero el crew buscando el job "Director" para películas, y para series se busca "Series Director" o "Director", si no se encuentra se muestra "No disponible"
    if ($mediaType === 'movie' && isset($tmdb['credits']['crew']) && is_array($tmdb['credits']['crew'])) {
        foreach ($tmdb['credits']['crew'] as $person) {
            if (!is_array($person)) continue;
            if (($person['job'] ?? '') === 'Director') {
                $director = trim((string)($person['name'] ?? 'No disponible'));
                break;
            }
        }
    } elseif ($mediaType === 'tv') {
        if (isset($tmdb['created_by'][0]['name'])) {
            $director = trim((string)$tmdb['created_by'][0]['name']);
        } elseif (isset($tmdb['credits']['crew']) && is_array($tmdb['credits']['crew'])) {
            foreach ($tmdb['credits']['crew'] as $person) {
                if (!is_array($person)) continue;
                $job = (string)($person['job'] ?? '');
                if ($job === 'Director' || $job === 'Series Director') {
                    $director = trim((string)($person['name'] ?? 'No disponible'));
                    break;
                }
            }
        }
    }
}

$reviews = [];
$dbError = '';

// Conexión a la base de datos para obtener las reseñas de este título, con manejo básico de errores
try {
    $conn = new mysqli('localhost', 'root', '', 'cineblog_db');
    if ($conn->connect_error) {
        $dbError = 'Error de conexion.';
    } else {
        $conn->set_charset('utf8mb4');
        // Consulta para obtener las reseñas de este título, incluyendo el conteo de likes, categorías y imágenes asociadas a cada reseña
        $sql = "
            SELECT
                p.id_post,
                p.titulo,
                p.contenido,
                p.fecha,
                u.nombre AS autor,
                COALESCE(lk.likes_count, 0) AS likes_count,
                GROUP_CONCAT(DISTINCT pc.categoria SEPARATOR '||') AS categorias,
                GROUP_CONCAT(DISTINCT pi.ruta SEPARATOR '||') AS imagenes
            FROM post_tmdb pt
            JOIN posts p ON p.id_post = pt.post_id
            JOIN usuarios u ON u.id_usuario = p.autor_id
            LEFT JOIN post_categorias pc ON pc.post_id = p.id_post
            LEFT JOIN post_imagenes pi ON pi.post_id = p.id_post
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS likes_count
                FROM likes
                GROUP BY post_id
            ) lk ON lk.post_id = p.id_post
            WHERE pt.tmdb_id = ? AND pt.media_type = ?
            GROUP BY p.id_post
            ORDER BY p.fecha DESC
            LIMIT 100
        ";

        // Se utiliza una consulta preparada para evitar inyecciones SQL, y se pasan los parámetros de forma segura
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $tmdbId, $mediaType);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) $reviews[] = $row;
        $stmt->close();
        $conn->close();
    }
} catch (Throwable $e) {
    // En caso de cualquier error durante la conexión o consulta a la base de datos, se captura la excepción y se muestra un mensaje de error
    $dbError = 'No se pudieron cargar las reseñas.';
}


$pageTitle = $title !== '' ? $title : 'Ficha del titulo';
$subtitleType = $mediaType === 'tv' ? 'Serie' : 'Pelicula';
$typeFilter = $mediaType === 'tv' ? 'series' : 'movies';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Reseñas | CineBlog</title>
    <link rel="stylesheet" href="css/styles_media.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    <!-- Main Content -->
    <main class="media-wrap">
        <a class="back-link" href="index.php">← Volver al inicio</a> <!-- Volver a la pagina principal -->

        <!-- Sección del hero con el backdrop y el poster -->
        <section class="hero" style="<?php echo $backdropUrl !== '' ? 'background-image:linear-gradient(rgba(6,12,22,.80), rgba(6,12,22,.88)), url(' . htmlspecialchars($backdropUrl, ENT_QUOTES, 'UTF-8') . ')' : ''; ?>">
            <div class="hero-poster">
                <?php if ($posterUrl !== '') : ?>
                    <img src="<?php echo htmlspecialchars($posterUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Poster de <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <?php else : ?>
                    <div class="poster-empty">Sin poster</div>
                <?php endif; ?>
            </div>
            <!-- Seccion principal del hero con información del título -->
            <div class="hero-main">
                <h1>
                    <?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($year !== '') : ?>
                        <span>(<?php echo htmlspecialchars($year, ENT_QUOTES, 'UTF-8'); ?>)</span>
                    <?php endif; ?>
                </h1>
                <!-- Chips de géneros y otros filtros -->
                <div class="chips">
                    <a class="chip" href="index.php?tipo=<?php echo htmlspecialchars($typeFilter, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($subtitleType, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php foreach ($genres as $genre) : ?>
                        <?php $genreFilter = map_genre_to_sidebar($genre); ?>
                        <a class="chip" href="index.php?tipo=<?php echo htmlspecialchars($typeFilter, ENT_QUOTES, 'UTF-8'); ?>&categoria=<?php echo htmlspecialchars($genreFilter, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($genre, ENT_QUOTES, 'UTF-8'); ?></a>
                    <?php endforeach; ?>
                </div>
                <!-- Estadísticas principales del título (director, fecha de estreno y número de reseñas en la pagina) -->
                <div class="stats">
                    <div class="stat">
                        <label>Director</label>
                        <strong><?php echo htmlspecialchars($director, ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="stat">
                        <label>Estreno</label>
                        <strong><?php echo htmlspecialchars(format_date_short($releaseDate) !== '' ? format_date_short($releaseDate) : 'N/D', ENT_QUOTES, 'UTF-8'); ?></strong>
                    </div>
                    <div class="stat">
                        <label>Reseñas en CineBlog</label>
                        <strong><?php echo (int)count($reviews); ?></strong>
                    </div>
                </div>
                <!-- Sinopsis o descripción del título, se muestra solo si está disponible -->
                <?php if ($overview !== '') : ?>
                    <p class="overview"><?php echo htmlspecialchars($overview, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
            </div>
        </section>

        <!-- Sección de reseñas de la comunidad -->
        <section class="reviews">
            <h2>Reseñas de nuestra comunidad</h2>
                
            <!-- Muestra un mensaje si no hay reseñas o si hubo un error al cargar-->
            <?php if ($dbError !== '') : ?>
                <div class="alert error"><?php echo htmlspecialchars($dbError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <?php if ($dbError === '' && !count($reviews)) : ?>
                <div class="alert">Todavía no hay reseñas de este título en CineBlog.</div>
            <?php endif; ?>

            <div class="review-list">
            <!-- Se muestran las reseñas en tarjetas con su información, categorías y imágenes asociadas-->
                <?php foreach ($reviews as $r) : ?>
                    <?php
                        $cats = [];
                        if (!empty($r['categorias'])) $cats = array_values(array_filter(explode('||', (string)$r['categorias'])));
                        $imgs = [];
                        if (!empty($r['imagenes'])) $imgs = array_values(array_filter(explode('||', (string)$r['imagenes'])));
                        $imgs = array_slice($imgs, 0, 4);
                    ?>
                    <article class="review-card">
                        <!-- Encabezado de la reseña (titulo y fecha)-->
                        <header class="review-head">
                            <h3><?php echo htmlspecialchars($r['titulo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h3>
                            <span><?php echo htmlspecialchars(format_date_short($r['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                        </header>
                        <!-- Meta información de la reseña (autor y número de likes)-->
                        <div class="review-meta">
                            <span>Por <?php echo htmlspecialchars($r['autor'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <span>❤ <?php echo (int)($r['likes_count'] ?? 0); ?></span>
                        </div>
                        <!-- Contenido de la reseña, se muestra tal cual pero se escapa para evitar problemas de seguridad, no se limita su longitud-->
                        <p><?php echo nl2br(htmlspecialchars($r['contenido'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>

                        <!-- Categorías asociadas a la reseña, mostradas como chips, solo si hay categorías-->
                        <?php if (count($cats)) : ?>
                            <div class="chips">
                                <?php foreach ($cats as $cat) : ?>
                                    <?php $catFilter = map_genre_to_sidebar((string)$cat); ?>
                                    <a class="chip muted" href="index.php?tipo=<?php echo htmlspecialchars($typeFilter, ENT_QUOTES, 'UTF-8'); ?>&categoria=<?php echo htmlspecialchars($catFilter, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Imágenes asociadas a la reseña, se muestran solo si hay imágenes, y se limita a mostrar máximo 4 imágenes por reseña para evitar sobrecargar la página-->
                        <?php if (count($imgs)) : ?>
                            <div class="review-imgs">
                                <?php foreach ($imgs as $src) : ?>
                                    <img src="<?php echo htmlspecialchars($src, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de reseña">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</body>
</html>
