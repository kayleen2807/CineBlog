<?php
session_start();
// Conexión a la base de datos
include 'includes/conexion.php';

// Se agregó el uso de PHPMailer para enviar correos de recuperación de contraseña
$mailerAvailable = false;
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
    $mailerAvailable = true;
}
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensaje = "";

$mailConfig = null;
$mailConfigPath = 'C:\\xampp\\config\\cineblog_mail.php';
if (file_exists($mailConfigPath)) {
    $mailConfig = require $mailConfigPath;
}

// Paso 1: Usuario solicita recuperación
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = $_POST['email'];

    // Verificar si el correo existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
    if (!$stmt) {
        $mensaje = "Error al preparar la consulta: " . $conn->error;
    } else {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            if (!$mailerAvailable) {
                $mensaje = "PHPMailer no esta instalado. Ejecuta composer install.";
            } else {
                $token = bin2hex(random_bytes(32)); // Generar token seguro
                $expira = date("Y-m-d H:i:s", strtotime("+1 hour"));

                // Guardar token en BD
                $stmt_token = $conn->prepare("UPDATE usuarios SET reset_token=?, reset_expira=? WHERE email=?");
                if (!$stmt_token) {
                    $mensaje = "Error al preparar la actualizacion del token: " . $conn->error;
                } else {
                    $stmt_token->bind_param("sss", $token, $expira, $email);
                    $stmt_token->execute();
                    $stmt_token->close();
                }

                // Enviar correo con PHPMailer
                $enlace = "http://localhost/CineBlog/resetPassword.php?token=" . $token;

                $mail = new PHPMailer(true);
                try {
                    if (!$mailConfig || empty($mailConfig['host']) || empty($mailConfig['username']) || empty($mailConfig['password'])) {
                        $mensaje = "Configuracion SMTP incompleta.";
                        throw new Exception($mensaje);
                    }

                    $mail->isSMTP();
                    $mail->Host = (string)$mailConfig['host'];
                    $mail->SMTPAuth = true;
                    $mail->Username = (string)$mailConfig['username'];
                    $mail->Password = (string)$mailConfig['password'];
                    $mail->SMTPSecure = (string)($mailConfig['secure'] ?? 'tls');
                    $mail->Port = (int)($mailConfig['port'] ?? 587);

                    $caFile = 'C:\\xampp\\php\\extras\\ssl\\cacert.pem';
                    $verifySsl = (bool)($mailConfig['verify_ssl'] ?? false);
                    if ($verifySsl && file_exists($caFile)) {
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => true,
                                'verify_peer_name' => true,
                                'cafile' => $caFile,
                            ],
                        ];
                    } else {
                        $mail->SMTPOptions = [
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true,
                            ],
                        ];
                    }

                    // Configuración del correo
                    $mail->setFrom((string)$mailConfig['from_email'], (string)$mailConfig['from_name']);
                    $mail->addAddress($email);

                    // Contenido del correo
                    $mail->isHTML(true);
                    $mail->Subject = 'Recuperar contraseña CineBlog';
                    $mail->Body    = "Haz clic en el siguiente enlace para restablecer tu contraseña: 
                                      <a href='$enlace'>$enlace</a>";

                    // Enviar el correo
                    if (empty($mensaje)) {
                        $mail->send();
                        $mensaje = "Se ha enviado un correo con instrucciones para recuperar tu contraseña.";
                    }
                } catch (Exception $e) {
                    $mensaje = "Error al enviar el correo: {$mail->ErrorInfo}";
                }
            }
        } else {
            $mensaje = "El correo no está registrado.";
        }
        $stmt->close();
    }
}
if ($conn) $conn->close();
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>CineBlog - Recuperar contraseña</title>
        <link rel="stylesheet" href="css/styles_inicioSesion.css">
        <link rel="stylesheet" href="css/style_switch.css">
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
                <p class="form-message1"><?php echo $mensaje; ?></p>
            <?php endif; ?>
        </div>
    </body>
</html>