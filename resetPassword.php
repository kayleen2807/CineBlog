<?php
session_start();
include 'includes/conexion.php';

$mensaje = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Verificar token
    $stmt = $conn->prepare("SELECT id_usuario, reset_expira FROM usuarios WHERE reset_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id_usuario, $reset_expira);
        $stmt->fetch();

        if (strtotime($reset_expira) > time()) {
            // Si se envió nueva contraseña
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $new_password = $_POST['new_password'];
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt_update = $conn->prepare("UPDATE usuarios SET contraseña=?, reset_token=NULL, reset_expira=NULL WHERE id_usuario=?");
                $stmt_update->bind_param("si", $hashed_password, $id_usuario);
                $stmt_update->execute();

                $mensaje = "Contraseña actualizada correctamente. Ahora puedes iniciar sesión.";
            }
        } else {
            $mensaje = "El enlace ha expirado.";
        }
    } else {
        $mensaje = "Token inválido.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CineBlog - Restablecer contraseña</title>
    <link rel="stylesheet" href="css/styles_inicioSesion.css">
</head>
<body>
    <div class="container">
        <h2>Restablecer contraseña</h2>
        <?php if (isset($_GET['token'])): ?>
            <form method="POST">
                <label for="new_password">Nueva contraseña:</label>
                <input type="password" id="new_password" name="new_password" minlength="8" required>
                <button type="submit">Actualizar contraseña</button>
            </form>
        <?php endif; ?>

        <!-- Botón para regresar al inicio de sesión -->
        <form action="inicioSesion.php" method="get" style="margin-top: 15px;">
            <button type="submit" class="btn-small">Regresar al inicio de sesión</button>
        </form>

        <?php if (!empty($mensaje)) : ?>
            <p style="color: yellow; margin-top: 15px;"><?php echo $mensaje; ?></p>
        <?php endif; ?>
    </div>
</body>
</html>
