<!-- Parte de php para conectar con la base de datos y envíar una respuesta -->
<?php
session_start();

// Conexión a la base de datos
include 'includes/conexion.php';

$mailerAvailable = false;
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
    $mailerAvailable = true;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mensaje = ""; // Variable para mostrar mensajes en la misma página
$showTwoFactorForm = isset($_SESSION['pending_2fa']);

function send_two_factor_code(string $email, string $nombre, string $code): array
{
    if (!$GLOBALS['mailerAvailable']) {
        return [false, "PHPMailer no esta instalado."];
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cinebloguser@gmail.com';
        $mail->Password = 'jfshyqskunichgoh';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $caFile = 'C:\\xampp\\php\\extras\\ssl\\cacert.pem';
        if (file_exists($caFile)) {
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

        $mail->setFrom('cinebloguser@gmail.com', 'CineBlog');
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Codigo de verificacion CineBlog';
        $mail->Body = "
            <p>Hola " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ",</p>
            <p>Tu codigo de verificacion para iniciar sesion en CineBlog es:</p>
            <h2 style='letter-spacing:4px;'>$code</h2>
            <p>Este codigo vence en 10 minutos.</p>
        ";

        $mail->send();
        return [true, ""];
    } catch (Exception $e) {
        return [false, $mail->ErrorInfo];
    }
}

// Si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['verify_2fa'])) {
        $pending = $_SESSION['pending_2fa'] ?? null;
        $code = trim((string)($_POST['two_factor_code'] ?? ''));

        if (!$pending || !isset($pending['code_hash'], $pending['expires_at'])) {
            unset($_SESSION['pending_2fa']);
            $showTwoFactorForm = false;
            $mensaje = "La verificacion expiro. Inicia sesion de nuevo.";
        } elseif (time() > (int)$pending['expires_at']) {
            unset($_SESSION['pending_2fa']);
            $showTwoFactorForm = false;
            $mensaje = "El codigo expiro. Inicia sesion de nuevo.";
        } elseif (!preg_match('/^\d{6}$/', $code) || !password_verify($code, (string)$pending['code_hash'])) {
            $showTwoFactorForm = true;
            $mensaje = "Codigo incorrecto.";
        } else {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int)$pending['usuario_id'];
            $_SESSION['nombre'] = (string)$pending['nombre'];
            $_SESSION['rol'] = (string)$pending['rol'];
            $_SESSION['login_time'] = time();
            unset($_SESSION['pending_2fa']);
            header("Location: index.php");
            exit();
        }
    } else {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    // VALIDACIÓN DE FORMATO DE CORREO
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Por favor ingresa un correo válido.";
    } else {
        // VALIDACIÓN DE DOMINIO DEL CORREO (MX records)
        $dominio = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($dominio, "MX")) {
            $mensaje = "El dominio del correo no existe.";
        } else {
            // Consulta segura (se agregó lo del rol también para que solo puedan iniciar sesión los usuarios registrados, no los visitantes)
            $stmt = $conn->prepare("SELECT id_usuario, nombre, contraseña, rol FROM usuarios WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            // Verifica si se encontró un usuario con ese correo
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id_usuario, $nombre, $hashed_password, $rol);
                $stmt->fetch();

                // Verifica la contraseña usando password_verify
                if (password_verify($password, $hashed_password)) {
                    $twoFactor = 0;
                    $stmt2fa = $conn->prepare("SELECT two_factor FROM ajustes_usuario WHERE usuario_id = ? LIMIT 1");
                    if ($stmt2fa) {
                        $stmt2fa->bind_param("i", $id_usuario);
                        $stmt2fa->execute();
                        $stmt2fa->bind_result($twoFactor);
                        $stmt2fa->fetch();
                        $stmt2fa->close();
                    }

                    if ((int)$twoFactor === 1) {
                        $code = (string)random_int(100000, 999999);
                        [$sent, $mailError] = send_two_factor_code($email, $nombre, $code);

                        if ($sent) {
                            $_SESSION['pending_2fa'] = [
                                'usuario_id' => $id_usuario,
                                'nombre' => $nombre,
                                'rol' => $rol,
                                'email' => $email,
                                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                                'expires_at' => time() + 600,
                            ];
                            $showTwoFactorForm = true;
                            $mensaje = "Te enviamos un codigo de 6 digitos a tu correo.";
                        } else {
                            $mensaje = "No se pudo enviar el codigo de verificacion: " . $mailError;
                        }
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['usuario_id'] = $id_usuario;
                        $_SESSION['nombre'] = $nombre;
                        $_SESSION['rol'] = $rol;
                        $_SESSION['login_time'] = time();
                        $mensaje = "Inicio de sesión exitoso. Bienvenido a CineBlog.";
                        // Aquí podrías redirigir si quieres (ya implementado):
                        header("Location: index.php");
                        exit();
                    }
                } else {
                    // Contraseña incorrecta
                    $mensaje = "Contraseña incorrecta.";
                }
            } else {
                // No se encontró un usuario con ese correo
                $mensaje = "El correo no está registrado.";
            }

            $stmt->close();
        }
    }
    }
}

$conn->close();
?>

<!-- Parte de HTML para mostrar el formulario de inicio de sesión -->
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8"> <!-- Esto es para que se muestren correctamente los caracteres especiales -->
        <title>CineBlog - Inicio de sesión</title>
        <link rel="stylesheet" href="css/styles_inicioSesion.css"> <!-- Enlaza con tu archivo CSS para estilos personalizados -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> <!-- Librería de bootstrap icons -->
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

        <a href="seleccion.php" class="btn-regresar">
    <i class="bi bi-arrow-left-circle"></i> Regresar
</a>
        
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog"> <!-- Tener un logo.png en el proyecto -->
                <h1>CineBlog</h1>
            </div>

            <!-- Formulario de inicio de sesión -->
            <h2><?php echo $showTwoFactorForm ? 'Verificacion en dos pasos' : 'Inicio de sesion'; ?></h2>
            <?php if ($showTwoFactorForm): ?>
            <form method="POST">
                <label for="two_factor_code">Codigo enviado a tu correo:</label>
                <input type="text" id="two_factor_code" name="two_factor_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required>
                <button type="submit" name="verify_2fa" value="1">Verificar codigo</button>
                <footer>
                    <p>Si no recibiste el codigo, vuelve a iniciar sesion para generar uno nuevo.</p>
                </footer>
            </form>
            <?php else: ?>
            <form method="POST">
                <label for="email">Correo electrónico:</label>
                <!-- CAMBIO EN EL FORMULARIO: type="email" + required + pattern -->
                <input type="email" id="email" name="email" required 
                       pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">

                <label for="password">Contraseña:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" required>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="bi bi-eye"></i> <!-- Ícono de ojo -->
                    </span>
                </div>

                <button type="submit">Iniciar sesión</button> <!-- Botón para enviar el formulario -->

                <!-- Enlace a la página de recuperación de contraseña -->
                <footer>
                    <p>¿Olvidaste tu contraseña? <a href="recuperarContrasena.php">Recupérala aquí</a></p>
                    <p>¿No tienes una cuenta? <a href="registro.php">Regístrate aquí</a></p>
                </footer>
                
            </form>
            <?php endif; ?>

            <!-- Mensaje para saber si puede iniciar sesión -->
             <?php if (!empty($mensaje)) : ?>
                <p style="color: red; margin-top: 15px;"><?php echo $mensaje; ?></p>
            <?php endif; ?>
            
        </div>

        <!-- Parte de JavaScript para mostrar/ocultar contraseña -->
         <script>
            function togglePassword(id) {
                const input = document.getElementById(id);
                const icon = input.nextElementSibling.querySelector("i");
                if (input.type === "password") {
                    input.type = "text";
                    icon.classList.remove("bi-eye");
                    icon.classList.add("bi-eye-slash");
                } else {
                    input.type = "password";
                    icon.classList.remove("bi-eye-slash");
                    icon.classList.add("bi-eye");
                }
            }
        </script>
    </body>
</html>
