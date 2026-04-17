<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

$query = trim((string)($_GET['q'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'multi'));
$page = (int)($_GET['page'] ?? 1);

if ($query === '' || strlen($query) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Consulta invalida.']);
    exit();
}

if ($page < 1) $page = 1;
if ($page > 3) $page = 3;

$allowedTypes = ['movie', 'tv', 'multi'];
if (!in_array($type, $allowedTypes, true)) $type = 'multi';

$token = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiI1ZTEyODBmMWVlYTcxZmJmNWI4ZjA4MzU3MDE5MTA3NCIsIm5iZiI6MTc3NjQzMjc3MS41ODcwMDAxLCJzdWIiOiI2OWUyMzY4M2RiY2EwYTZkYzlkMjE4ZDUiLCJzY29wZXMiOlsiYXBpX3JlYWQiXSwidmVyc2lvbiI6MX0.HK3ADaF_YdTo_2k26ndww-8DHa00GfBw534D0GRl-Us';
$baseUrl = 'https://api.themoviedb.org/3/search/' . $type;
$params = http_build_query([
    'query' => $query,
    'language' => 'es-MX',
    'include_adult' => 'false',
    'page' => (string)$page,
]);
$url = $baseUrl . '?' . $params;

$ch = curl_init($url);
if ($ch === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo iniciar la solicitud.']);
    exit();
}

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

$raw = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($raw === false || $httpCode >= 400) {
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'error' => 'Error consultando TMDB.',
        'details' => $curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode),
    ]);
    exit();
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Respuesta invalida de TMDB.']);
    exit();
}

$results = [];
$items = $data['results'] ?? [];
if (!is_array($items)) $items = [];

foreach ($items as $item) {
    if (!is_array($item)) continue;

    $mediaType = (string)($item['media_type'] ?? ($type === 'multi' ? '' : $type));
    if ($mediaType === '') {
        $mediaType = isset($item['first_air_date']) ? 'tv' : 'movie';
    }
    if ($mediaType !== 'movie' && $mediaType !== 'tv') continue;

    $id = (int)($item['id'] ?? 0);
    if ($id <= 0) continue;

    $title = trim((string)($item['title'] ?? $item['name'] ?? ''));
    if ($title === '') continue;

    $posterPath = trim((string)($item['poster_path'] ?? ''));
    $posterUrl = $posterPath !== '' ? ('https://image.tmdb.org/t/p/w342' . $posterPath) : null;

    $releaseDate = trim((string)($item['release_date'] ?? $item['first_air_date'] ?? ''));
    $year = '';
    if (strlen($releaseDate) >= 4) $year = substr($releaseDate, 0, 4);

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

echo json_encode([
    'ok' => true,
    'query' => $query,
    'type' => $type,
    'page' => $page,
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
