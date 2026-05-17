<?php
session_start();

// Se maneja la adición de un comentario a un post específico.
header('Content-Type: application/json; charset=utf-8');

# Solo usuarios autenticados pueden comentar
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit();
}

// Validación de entrada
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
$contenido = trim((string)($_POST['contenido'] ?? ''));

// Validaciones básicas
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

//Función para convertir a minúsculas de forma segura con UTF-8
function mb_lower_safe(string $value): string
{
    if (function_exists('mb_strtolower')) return mb_strtolower($value, 'UTF-8');
    return strtolower($value);
}

//Función para normalizar texto y detectar malas palabras incluso si están separadas por espacios o símbolos
function normalize_for_moderation_spaces(string $text): string
{
    $value = mb_lower_safe($text);
    // Convierte caracteres acentuados a su forma base
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }
    // Caracteres comunes usados para evadir filtros a su equivalente alfabético
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

// Lista de malas palabras comunes en español, detectando si se escriben con símbolos o espacios intercalados
function contains_profanity(string $text): bool
{
    $normalized = normalize_for_moderation_spaces($text);
    if ($normalized === '') return false;
    // Divide el texto normalizado en tokens para detectar palabras completas o con prefijos/sufijos
    $tokens = preg_split('/\s+/', $normalized) ?: [];
    $badWords = [
        'puta',
        'puto',
        'puts',
        'pt',
        'tonto',
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
    // Detecta si se escribe con símbolos/separado
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

// Verifica si el contenido contiene lenguaje inapropiado
if (contains_profanity($contenido)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No se permite lenguaje inapropiado.']);
    exit();
}

$userId = (int)$_SESSION['usuario_id'];

// Conexión a la base de datos
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

// Inserta el comentario en la base de datos
$stmt = $conn->prepare("INSERT INTO comentarios (contenido, usuario_id, post_id) VALUES (?, ?, ?)");
$stmt->bind_param("sii", $contenido, $userId, $postId);
$ok = $stmt->execute();
$stmt->close();

// Si no se pudo guardar el comentario, devuelve un error
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
