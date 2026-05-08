<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Validar entrada (GET)
$query = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'multi'));
$page = (int)($_GET['page'] ?? 1);

// Validaciones básicas
if ($query === '' || strlen($query) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Consulta invalida.']);
    exit();
}

// Limitar página a un rango razonable
if ($page < 1) $page = 1;
if ($page > 3) $page = 3;

// Validar tipo de búsqueda
$allowedTypes = ['movie', 'tv', 'multi'];
if (!in_array($type, $allowedTypes, true)) $type = 'multi';

// Consulta a TMDB
$token = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI1ZTEyODBmMWVlYTcxZmJmNWI4ZjA4MzU3MDE5MTA3NCIsIm5iZiI6MTc3NjQzMjc3MS41ODcwMDAxLCJzdWIiOiI2OWUyMzY4M2RiY2EwYTZkYzlkMjE4ZDUiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.HK3ADaF_YdTo_2k26ndww-8DHa00GfBw534D0GRl-Us';
$baseUrl = 'https://api.themoviedb.org/3/search/' . $type;
$params = http_build_query([
    'query' => $query,
    'language' => 'es-MX',
    'include_adult' => 'false',
    'page' => (string)$page,
]);
$url = $baseUrl . '?' . $params;

// Realizar la solicitud a TMDB
$ch = curl_init($url);
if ($ch === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo iniciar la solicitud.']);
    exit();
}

// Configuración de cURL con opciones de seguridad mejoradas
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

// Ejecutar la solicitud
$raw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

// Manejar errores de la solicitud
if ($raw === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Error consultando TMDB.',
        'details' => $curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode),
    ]);
    exit();
}

// Procesar la respuesta de TMDB
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Respuesta invalida de TMDB.']);
    exit();
}

// Extraer y formatear resultados
$results = [];
$items = $data['results'] ?? [];
if (!is_array($items)) $items = [];

// Filtrar y formatear resultados válidos
foreach ($items as $item) {
    // Validar que el item tenga la estructura esperada
    if (!is_array($item)) continue;

    // Determinar el tipo de media (película o serie)
    $mediaType = (string)($item['media_type'] ?? ($type === 'multi' ? '' : $type));
    if ($mediaType === '') {
        $mediaType = isset($item['first_air_date']) ? 'tv' : 'movie';
    }
    if ($mediaType !== 'movie' && $mediaType !== 'tv') continue;

    // Validar campos esenciales como id y título
    $id = (int)($item['id'] ?? 0);
    if ($id <= 0) continue;

    $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
    if ($title === '') continue;

    // Construir URLs de imágenes de forma segura
    $posterPath = trim((string)($item['poster_path'] ?? ''));
    $posterUrl = $posterPath !== '' ? ('https://image.tmdb.org/t/p/w342' . $posterPath) : null;

    $releaseDate = trim((string)($item['release_date'] ?? $item['first_air_date'] ?? ''));
    $year = '';
    if (strlen($releaseDate) >= 4) $year = substr($releaseDate, 0, 4);

    // Agregar el resultado formateado al array de resultados
    $results[] = [
        'tmdb_id' => $id,
        'media_type' => $mediaType,
        'title' => $title,
        'original_title' => (string)($item['original_title'] ?? $item['original_name'] ?? $title),
        'overview' => (string)($item['overview'] ?? ''),
        'poster_url' => $posterUrl,
        'backdrop_url' => isset($item['backdrop_path']) && $item['backdrop_path'] !== ''
            ? ('https://image.tmdb.org/t/p/w780' . $item['backdrop_path'])
            : null,
        'release_date' => $releaseDate,
        'year' => $year,
        'vote_average' => (float)($item['vote_average'] ?? 0),
    ];
}

// Responder con los resultados formateados
echo json_encode([
    'ok' => true,
    'query' => $query,
    'type' => $type,
    'page' => $page,
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
