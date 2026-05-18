<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
include 'includes/conexion.php';

$id_post = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_post) {
    die("ID inválido.");
}

// Roles permitidos: admin, moderador, editor
if (!in_array($_SESSION['rol'], ['admin','moderador','editor'])) {
    die("Acceso denegado.");
}

// Verifica que el post exista
$sqlCheck = $conn->prepare("SELECT id_post, autor_id FROM posts WHERE id_post = ?");
$sqlCheck->bind_param("i", $id_post);
$sqlCheck->execute();
$res = $sqlCheck->get_result();
if ($res->num_rows === 0) {
    die("Publicación no encontrada.");
}
$post = $res->fetch_assoc();
$sqlCheck->close();

// Validar que el editor solo pueda borrar sus propios posts
if ($_SESSION['rol'] === 'editor' && $_SESSION['usuario_id'] != $post['autor_id']) {
    die("Acceso denegado. Solo puedes borrar tus publicaciones.");
}

// Borrar reportes asociados primero
$sqlRep = $conn->prepare("DELETE FROM reportes WHERE id_post = ?");
$sqlRep->bind_param("i", $id_post);
$sqlRep->execute();
$sqlRep->close();

// Borrar comentarios asociados
$sqlCom = $conn->prepare("DELETE FROM comentarios WHERE post_id = ?");
$sqlCom->bind_param("i", $id_post);
$sqlCom->execute();
$sqlCom->close();

// Borrar likes asociados al post
$sqlLikes = $conn->prepare("DELETE FROM likes WHERE post_id = ?");
$sqlLikes->bind_param("i", $id_post);
$sqlLikes->execute();
$sqlLikes->close();

// Borrar el post
$sqlDel = $conn->prepare("DELETE FROM posts WHERE id_post = ?");
$sqlDel->bind_param("i", $id_post);
if ($sqlDel->execute()) {
    // Redirección según rol y origen
    if ($_SESSION['rol'] === 'admin') {
        if (isset($_GET['from']) && $_GET['from'] === 'index') {
            header("Location: index.php?msg=eliminado");
        } else {
            header("Location: dashboard.php?msg=eliminado");
        }
    } elseif ($_SESSION['rol'] === 'moderador') {
        if (isset($_GET['from']) && $_GET['from'] === 'index') {
            header("Location: index.php?msg=eliminado");
        } else {
            header("Location: reportes.php?msg=eliminado");
        }
    } elseif ($_SESSION['rol'] === 'editor') {
        header("Location: index.php?msg=eliminado");
    }
    exit();
} else {
    die("Error al borrar la publicación.");
}
?>