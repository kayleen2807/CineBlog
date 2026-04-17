<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit();
}

$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$contenido = trim((string)($_POST['contenido'] ?? ''));

if (!$postId || $postId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'post_id inválido.']);
    exit();
}

if ($contenido === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'El comentario no puede ir vacío.']);
    exit();
}

if (strlen($contenido) > 1200) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Comentario demasiado largo.']);
    exit();
}

function mb_lower_safe(string $value): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
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
        '€' => 'e',
        '3' => 'e',
        '1' => 'i',
        '!' => 'i',
        '|' => 'i',
        '0' => 'o',
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
    $value = preg_replace('/[^a-z]+/i', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function contains_profanity(string $text): bool
{
    $normalized = normalize_for_moderation_spaces($text);
    if ($normalized === '') return false;

    $tokens = preg_split('/\s+/', $normalized) ?: [];
    $badWords = [
        'puta',
        'puto',
        'puts',
        'pt',
        'tonto'
        'tonta',
        'tontos',
        'tontas',
        'putas',
        'putos',
        'mierda',
        'mierdas',
        'pendejo',
        'pendeja',
        'pendejos',
        'pendejas',
        'cabron',
        'cabrones',
        'chingar',
        'chingada',
        'chingadas',
        'chingados',
        'verga',
        'vergas',
        'culero',
        'culeros',
        'culo',
        'culos',
        'culito',
        'culitos',
        'pinche',
        'pinches',
        'joder',
        'jodidos',
        'idiota',
        'idiotas',
        'imbecil',
        'imbeciles',
        'alv',
        'alm',
        'vtalv',
        'ctm',
        'panocha',
        'panochas',
        'joto',
        'jotos',
        'marimacha',
        'marimachas',
        'pene',
        'penes',
        'pito',
        'pitos',
        'pija',
        'pijas',
        'polla',
        'pollas',
        'vagina',
        'vaginas',
    ];
    // Detecta si se escribe con símbolos/separado (p u t a, p* u + t @, etc.)
    foreach ($badWords as $bad) {
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
        foreach ($tokens as $token) {
            if ($token === $bad) return true;
            if ($len >= 5 && (str_starts_with($token, $bad) || str_ends_with($token, $bad))) return true;
        }
    }
    return false;
}

if (contains_profanity($contenido)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se permite lenguaje inapropiado.']);
    exit();
}

$userId = (int)$_SESSION['usuario_id'];

$conn = new mysqli("localhost", "root", "", "cineblog_db");
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error de conexión.']);
    exit();
}
$conn->set_charset("utf8mb4");

// Verifica que exista el post
$stmtPost = $conn->prepare("SELECT id_post FROM posts WHERE id_post = ? LIMIT 1");
$stmtPost->bind_param("i", $postId);
$stmtPost->execute();
$stmtPost->store_result();
if ($stmtPost->num_rows === 0) {
    $stmtPost->close();
    $conn->close();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Post no existe.']);
    exit();
}
$stmtPost->close();

$stmt = $conn->prepare("INSERT INTO comentarios (contenido, usuario_id, post_id) VALUES (?, ?, ?)");
$stmt->bind_param("sii", $contenido, $userId, $postId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el comentario.']);
    exit();
}

// Devuelve el comentario recién creado
$res = $conn->query("
    SELECT c.id_comentario, c.contenido, c.fecha, u.nombre AS autor
    FROM comentarios c
    JOIN usuarios u ON u.id_usuario = c.usuario_id
    WHERE c.usuario_id = $userId
    ORDER BY c.id_comentario DESC
    LIMIT 1
");
$row = $res ? $res->fetch_assoc() : null;
if ($res) $res->free();
$conn->close();

echo json_encode(['ok' => true, 'comment' => $row]);
