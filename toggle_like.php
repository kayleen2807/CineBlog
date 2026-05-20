<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Solo usuarios autenticados pueden dar like
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado.']);
    exit();
}

// Validación de entrada
$postId = filter_input(INPUT_POST, 'post_id', FILTER_VALIDATE_INT);
if (!$postId || $postId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'post_id inválido.']);
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

// Toggle like
$stmtCheck = $conn->prepare("SELECT id_like FROM likes WHERE usuario_id = ? AND post_id = ? LIMIT 1");
$stmtCheck->bind_param("ii", $userId, $postId);
$stmtCheck->execute();
$stmtCheck->store_result();
$already = $stmtCheck->num_rows > 0;
$stmtCheck->close();

$liked = false;
if ($already) {
    $stmtDel = $conn->prepare("DELETE FROM likes WHERE usuario_id = ? AND post_id = ? LIMIT 1");
    $stmtDel->bind_param("ii", $userId, $postId);
    $stmtDel->execute();
    $stmtDel->close();
    $liked = false;
} else {
    $stmtIns = $conn->prepare("INSERT INTO likes (usuario_id, post_id) VALUES (?, ?)");
    $stmtIns->bind_param("ii", $userId, $postId);
    $ok = $stmtIns->execute();
    $stmtIns->close();
    $liked = $ok ? true : false;
}

$stmtCount = $conn->prepare("SELECT COUNT(*) AS c FROM likes WHERE post_id = ?");
$stmtCount->bind_param("i", $postId);
$stmtCount->execute();
$countRow = $stmtCount->get_result()->fetch_assoc();
$likesCount = (int)($countRow['c'] ?? 0);
$stmtCount->close();

$conn->close();

echo json_encode(['ok' => true, 'liked' => $liked, 'likes_count' => $likesCount]);
