<?php
// Archivo para el registro de nuevos usuarios con verificacion de correo.
session_start();

include 'includes/conexion.php';

$mensaje = "";
$tipoMensaje = "error";
$debugDbInfo = '';
$mailerAvailable = false;
$autoloadPath = __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (file_exists($autoloadPath)) {
    require $autoloadPath;
    $mailerAvailable = true;
}

$mailConfig = null;
$mailConfigPath = 'C:\\xampp\\config\\cineblog_mail.php';
if (file_exists($mailConfigPath)) {
    $mailConfig = require $mailConfigPath;
}

if (isset($_GET['debug_db'])) {
    $res = $conn->query("SELECT DATABASE() AS db, @@hostname AS host, @@port AS port");
    if ($res && ($row = $res->fetch_assoc())) {
        $debugDbInfo = "DB: " . $row['db'] . " @ " . $row['host'] . ":" . $row['port'];
    }
}

$serverHost = (string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '');
$isLocalHost = in_array($serverHost, ['localhost', '127.0.0.1'], true);
$shouldCheckMx = !$isLocalHost;

function obtenerCampoRegistro(string $campo, string $default = ''): string
{
    return htmlspecialchars((string)($_POST[$campo] ?? $_SESSION['registro_pendiente'][$campo] ?? $default), ENT_QUOTES, 'UTF-8');
}

function enviarCodigoVerificacion(string $email, string $nombre, string $codigo, ?array $mailConfig, bool $mailerAvailable): void
{
    if (!$mailerAvailable) {
        throw new Exception("PHPMailer no esta instalado. Ejecuta composer install.");
    }

    if (!$mailConfig || empty($mailConfig['host']) || empty($mailConfig['username']) || empty($mailConfig['password'])) {
        throw new Exception("Configuracion SMTP incompleta.");
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string)$mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string)$mailConfig['username'];
    $mail->Password = (string)$mailConfig['password'];
    $mail->SMTPSecure = (string)($mailConfig['secure'] ?? 'tls');
    $mail->Port = (int)($mailConfig['port'] ?? 587);
    $mail->Timeout = 10;
    $mail->SMTPKeepAlive = false;

    $possiblePaths = [
        'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
        __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
    ];

    $caFile = null;
    foreach ($possiblePaths as $path) {
        if (file_exists($path)) {
            $caFile = $path;
            break;
        }
    }

    $verifySsl = (bool)($mailConfig['verify_ssl'] ?? false);
    if ($verifySsl && $caFile) {
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

    $mail->setFrom((string)$mailConfig['from_email'], (string)$mailConfig['from_name']);
    $mail->addAddress($email, $nombre);
    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);
    $mail->Subject = 'Codigo de verificacion CineBlog';
    $mail->Body = "
        <p>Hola " . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . ",</p>
        <p>Tu codigo para terminar el registro en CineBlog es:</p>
        <h2 style='letter-spacing: 4px;'>" . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . "</h2>
        <p>Este codigo vence en 15 minutos. Si no intentaste registrarte, puedes ignorar este correo.</p>
    ";
    $mail->AltBody = "Tu codigo de verificacion de CineBlog es: $codigo. Vence en 15 minutos.";
    $mail->send();
}

function validarDatosRegistro(mysqli $conn, bool $shouldCheckMx, string &$mensaje): ?array
{
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');
    $fecha_nacimiento = (string)($_POST['fecha_nacimiento'] ?? '');

    if ($nombre === '') {
        $mensaje = "Ingresa tu nombre completo.";
        return null;
    }

    $fecha_actual = new DateTime();
    try {
        $fecha_nac = new DateTime($fecha_nacimiento);
    } catch (Exception $e) {
        $fecha_nac = null;
    }

    if (!$fecha_nac) {
        $mensaje = "La fecha de nacimiento no es valida.";
        return null;
    }

    if ($fecha_nac > $fecha_actual) {
        $mensaje = "La fecha de nacimiento no puede ser posterior a la actual.";
        return null;
    }

    $edad = $fecha_actual->diff($fecha_nac)->y;
    if ($edad < 18) {
        $mensaje = "Debes tener al menos 18 años para registrarte.";
        return null;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Por favor ingresa un correo valido.";
        return null;
    }

    $dominio = substr(strrchr($email, "@"), 1);
    if ($shouldCheckMx && function_exists('checkdnsrr') && !checkdnsrr($dominio, "MX")) {
        $mensaje = "El dominio del correo no existe.";
        return null;
    }

    $pattern = "/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/";
    if (!preg_match($pattern, $password)) {
        $mensaje = "La contraseña debe tener al menos 8 caracteres, incluir una mayuscula, un numero y un caracter especial.";
        return null;
    }

    if ($password !== $confirm_password) {
        $mensaje = "Las contraseñas no coinciden.";
        return null;
    }

    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt_check) {
        $mensaje = "Error al validar el correo: " . $conn->error;
        return null;
    }

    $stmt_check->bind_param("s", $email);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $mensaje = "La cuenta ya esta registrada.";
        $stmt_check->close();
        return null;
    }

    $stmt_check->close();

    return [
        'nombre' => $nombre,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'fecha_nacimiento' => $fecha_nacimiento,
    ];
}

function obtenerColumnaPassword(mysqli $conn): ?string
{
    $res = $conn->query("SHOW COLUMNS FROM usuarios");
    if (!$res) {
        return null;
    }

    while ($row = $res->fetch_assoc()) {
        $field = (string)$row['Field'];
        if (strpos($field, 'contra') === 0) {
            return $field;
        }
    }

    return null;
}

function escaparIdentificadorMysql(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function insertarUsuarioVerificado(mysqli $conn, array $registro, string &$mensaje): bool
{
    $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1");
    if (!$stmt_check) {
        $mensaje = "Error al validar el correo: " . $conn->error;
        return false;
    }

    $stmt_check->bind_param("s", $registro['email']);
    $stmt_check->execute();
    $stmt_check->store_result();

    if ($stmt_check->num_rows > 0) {
        $mensaje = "La cuenta ya esta registrada.";
        $stmt_check->close();
        return false;
    }

    $stmt_check->close();

    $passwordColumn = obtenerColumnaPassword($conn);
    if (!$passwordColumn) {
        $mensaje = "No se encontro la columna de contraseña en la tabla usuarios.";
        return false;
    }

    $rol = 'editor';
    $stmt_insert = $conn->prepare(
        "INSERT INTO usuarios (nombre, email, " . escaparIdentificadorMysql($passwordColumn) . ", fecha_nac, rol) VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt_insert) {
        $mensaje = "Error al preparar el registro: " . $conn->error;
        return false;
    }

    $stmt_insert->bind_param(
        "sssss",
        $registro['nombre'],
        $registro['email'],
        $registro['password_hash'],
        $registro['fecha_nacimiento'],
        $rol
    );

    if (!$stmt_insert->execute()) {
        $mensaje = ((int)$stmt_insert->errno === 1062) ? "La cuenta ya esta registrada." : "Error al registrar: " . $stmt_insert->error;
        $stmt_insert->close();
        return false;
    }

    $stmt_insert->close();
    return true;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $accion = (string)($_POST['accion'] ?? 'enviar_codigo');

    if ($accion === 'reiniciar') {
        unset($_SESSION['registro_pendiente']);
        header("Location: registro.php");
        exit();
    }

    if ($accion === 'enviar_codigo') {
        $registro = validarDatosRegistro($conn, $shouldCheckMx, $mensaje);
        if ($registro) {
            $codigo = (string)random_int(100000, 999999);

            try {
                enviarCodigoVerificacion($registro['email'], $registro['nombre'], $codigo, $mailConfig, $mailerAvailable);

                $_SESSION['registro_pendiente'] = $registro + [
                    'codigo_hash' => password_hash($codigo, PASSWORD_DEFAULT),
                    'codigo_expira' => time() + (15 * 60),
                    'intentos' => 0,
                ];

                $tipoMensaje = "success";
                $mensaje = "Te enviamos un codigo de verificacion a " . $registro['email'] . ". Revisa tu bandeja de entrada.";
            } catch (Throwable $e) {
                $mensaje = "No se pudo enviar el codigo: " . $e->getMessage();
            }
        }
    }

    if ($accion === 'reenviar_codigo' && isset($_SESSION['registro_pendiente'])) {
        $codigo = (string)random_int(100000, 999999);

        try {
            enviarCodigoVerificacion(
                $_SESSION['registro_pendiente']['email'],
                $_SESSION['registro_pendiente']['nombre'],
                $codigo,
                $mailConfig,
                $mailerAvailable
            );

            $_SESSION['registro_pendiente']['codigo_hash'] = password_hash($codigo, PASSWORD_DEFAULT);
            $_SESSION['registro_pendiente']['codigo_expira'] = time() + (15 * 60);
            $_SESSION['registro_pendiente']['intentos'] = 0;

            $tipoMensaje = "success";
            $mensaje = "Enviamos un nuevo codigo de verificacion.";
        } catch (Throwable $e) {
            $mensaje = "No se pudo reenviar el codigo: " . $e->getMessage();
        }
    }

    if ($accion === 'verificar_codigo') {
        $registro = $_SESSION['registro_pendiente'] ?? null;
        $codigoIngresado = preg_replace('/\D/', '', (string)($_POST['codigo_verificacion'] ?? ''));

        if (!$registro) {
            $mensaje = "Primero solicita un codigo de verificacion.";
        } elseif (time() > (int)$registro['codigo_expira']) {
            unset($_SESSION['registro_pendiente']);
            $mensaje = "El codigo vencio. Vuelve a llenar el registro para recibir uno nuevo.";
        } elseif ((int)$registro['intentos'] >= 5) {
            unset($_SESSION['registro_pendiente']);
            $mensaje = "Se agotaron los intentos. Vuelve a iniciar el registro.";
        } elseif (!password_verify($codigoIngresado, (string)$registro['codigo_hash'])) {
            $_SESSION['registro_pendiente']['intentos'] = (int)$registro['intentos'] + 1;
            $mensaje = "El codigo no coincide. Revisa el correo e intenta de nuevo.";
        } else {
            if (insertarUsuarioVerificado($conn, $registro, $mensaje)) {
                unset($_SESSION['registro_pendiente']);
                header("Location: inicioSesion.php");
                exit();
            }
        }
    }
}

$registroPendiente = $_SESSION['registro_pendiente'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>CineBlog - Registro</title>
        <link rel="stylesheet" href="css/styles_registro.css">
        <link rel="stylesheet" href="css/style_switch.css">
        <link rel="stylesheet" href="css/temas.css">
        <script src="js/temas.js" defer></script>
    </head>
    <body>
        <div class="theme-toggle">
        <input type="checkbox" id="theme-switch">
        <label for="theme-switch" class="switch">
            <span class="icon-sun">☀️</span>
            <span class="icon-moon">🌙</span>
        </label>
        </div>
        <div class="container">
            <a href="inicioSesion.php" class="btn-regresar">
                <span class="icon-back" aria-hidden="true"></span> Regresar
            </a>
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog">
                <h1>CineBlog</h1>
            </div>

            <h2>Registro</h2>

            <?php if (!$registroPendiente) : ?>
            <form method="POST">
                <input type="hidden" name="accion" value="enviar_codigo">

                <label for="nombre">Nombre completo:</label>
                <input type="text" id="nombre" name="nombre" autocomplete="name" value="<?php echo obtenerCampoRegistro('nombre'); ?>" required>

                <label for="email">Correo electronico:</label>
                <input type="email" id="email" name="email" autocomplete="email" value="<?php echo obtenerCampoRegistro('email'); ?>" required
                    pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">

                <label for="password">Contraseña (minimo 8 caracteres, mayuscula, numero y caracter especial):</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" autocomplete="new-password"
                        minlength="8"
                        pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$"
                        title="Debe tener al menos 8 caracteres, una mayuscula, un numero y un caracter especial"
                        required>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <span class="icon-eye" aria-hidden="true"></span>
                    </span>
                </div>

                <label for="confirm_password">Confirmar contraseña:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">
                        <span class="icon-eye" aria-hidden="true"></span>
                    </span>
                </div>

                <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" autocomplete="bday"
                       max="<?php echo date('Y-m-d'); ?>" value="<?php echo obtenerCampoRegistro('fecha_nacimiento'); ?>" required>

                <button type="submit">Enviar codigo</button>
            </form>
            <?php else : ?>
            <div class="verification-box">
                <p>Escribe el codigo de 6 digitos que enviamos a:</p>
                <strong><?php echo htmlspecialchars((string)$registroPendiente['email'], ENT_QUOTES, 'UTF-8'); ?></strong>
            </div>

            <form method="POST">
                <input type="hidden" name="accion" value="verificar_codigo">
                <label for="codigo_verificacion">Codigo de verificacion:</label>
                <input type="text" id="codigo_verificacion" name="codigo_verificacion" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                <button type="submit">Verificar y crear cuenta</button>
            </form>

            <div class="verification-actions">
                <form method="POST">
                    <input type="hidden" name="accion" value="reenviar_codigo">
                    <button type="submit" class="btn-secondary">Reenviar codigo</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="accion" value="reiniciar">
                    <button type="submit" class="btn-secondary">Cambiar correo</button>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($mensaje)) : ?>
                <p class="form-message <?php echo $tipoMensaje === 'success' ? 'is-success' : ''; ?>"><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if (!empty($debugDbInfo)) : ?>
                <p class="form-message"><?php echo htmlspecialchars($debugDbInfo, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const icon = input.nextElementSibling.querySelector(".icon-eye");
            if (input.type === "password") {
                input.type = "text";
                icon.classList.add("is-hidden");
            } else {
                input.type = "password";
                icon.classList.remove("is-hidden");
            }
        }
        </script>
    </body>
</html>
