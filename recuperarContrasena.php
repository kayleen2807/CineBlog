<?php
session_start();
include 'includes/conexion.php';

require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensaje = "";

// Paso 1: Usuario solicita recuperación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = $_POST['email'];

    // Verificar si el correo existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $token = bin2hex(random_bytes(32)); // Generar token seguro
        $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Guardar token en BD
        $stmt_token = $conn->prepare("UPDATE usuarios SET reset_token=?, reset_expira=? WHERE email=?");
        $stmt_token->bind_param("sss", $token, $expira, $email);
        $stmt_token->execute();

        // Enviar correo con PHPMailer
        $enlace = "http://localhost/CineBlog/resetPassword.php?token=" . $token;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'cinebloguser@gmail.com'; // correo de la aplicación
            $mail->Password = 'jfshyqskunichgoh'; // contraseña de aplicación de Gmail
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('cinebloguser@gmail.com', 'CineBlog');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Recuperar contraseña CineBlog';
            $mail->Body    = "Haz clic en el siguiente enlace para restablecer tu contraseña: 
                              <a href='$enlace'>$enlace</a>";

            $mail->send();
            $mensaje = "Se ha enviado un correo con instrucciones para recuperar tu contraseña.";
        } catch (Exception $e) {
            $mensaje = "Error al enviar el correo: {$mail->ErrorInfo}";
        }
    } else {
        $mensaje = "El correo no está registrado.";
    }
    $stmt->close();
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>CineBlog - Recuperar contraseña</title>
        <link rel="stylesheet" href="css/styles_inicioSesion.css">
    </head>
    <body>
        <div class="container">
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog">
                <h1>CineBlog</h1>
            </div>

            <!-- Formulario para solicitar recuperación de contraseña -->
            <h2>Recuperar contraseña</h2>
            <form method="POST">
                <label for="email">Introduce tu correo electrónico:</label>
                <input type="email" id="email" name="email" required>
                <button type="submit">Enviar enlace</button>
            </form>

            <!-- Botón para regresar al inicio de sesión -->
            <form action="inicioSesion.php" method="get" style="margin-top: 12px;">
                <button type="submit" class="btn-small">Regresar al inicio de sesión</button>
            </form>

            <?php if (!empty($mensaje)) : ?>
                <p style="color: yellow; margin-top: 15px;"><?php echo $mensaje; ?></p>
            <?php endif; ?>
        </div>
    </body>
</html>