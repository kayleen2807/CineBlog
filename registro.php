<?php
//Archivo para el registro de nuevos usuarios, con validación de datos y almacenamiento seguro de contraseñas (hashing)
session_start();

// Crear conexión
include 'includes/conexion.php';

$mensaje = "";
$debugDbInfo = '';

if (isset($_GET['debug_db'])) {
    $res = $conn->query("SELECT DATABASE() AS db, @@hostname AS host, @@port AS port");
    if ($res && ($row = $res->fetch_assoc())) {
        $debugDbInfo = "DB: " . $row['db'] . " @ " . $row['host'] . ":" . $row['port'];
    }
}

$serverHost = (string)($_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '');
$isLocalHost = in_array($serverHost, ['localhost', '127.0.0.1'], true);
$shouldCheckMx = !$isLocalHost;

// Validar que el formulario se haya enviado
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $email = strtolower($email);
    $password = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');
    $fecha_nacimiento = (string)($_POST['fecha_nacimiento'] ?? '');

    // Validación de fecha de nacimiento
    $fecha_actual = new DateTime();
    try {
        $fecha_nac = new DateTime($fecha_nacimiento);
    } catch (Exception $e) {
        $fecha_nac = null;
    }

    if (!$fecha_nac) {
        $mensaje = "La fecha de nacimiento no es valida.";
    } elseif ($fecha_nac > $fecha_actual) {
        $mensaje = "La fecha de nacimiento no puede ser posterior a la actual."; // Agregado.
    } else {
        $edad = $fecha_actual->diff($fecha_nac)->y;
        if ($edad < 18) {
            $mensaje = "Debes tener al menos 18 años para registrarte."; // Agregado.
        } else {
            // VALIDACIÓN DE FORMATO DE CORREO
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mensaje = "Por favor ingresa un correo válido.";
            } else {
                // VALIDACIÓN DE DOMINIO DEL CORREO (MX records)
                $dominio = substr(strrchr($email, "@"), 1);
                if ($shouldCheckMx && function_exists('checkdnsrr') && !checkdnsrr($dominio, "MX")) {
                    $mensaje = "El dominio del correo no existe.";
                } else {
                    // Validar que la contraseña cumpla requisitos de seguridad
                    $pattern = "/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/";
                    if (!preg_match($pattern, $password)) {
                        $mensaje = "La contraseña debe tener al menos 8 caracteres, incluir una mayúscula, un número y un carácter especial.";
                    } elseif ($password !== $confirm_password) {
                        $mensaje = "Las contraseñas no coinciden.";
                    } else {
                        // Verificar si el correo ya está registrado
                            $stmt_check = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1");
                            if (!$stmt_check) {
                                $mensaje = "Error al validar el correo: " . $conn->error;
                            } else {
                                $stmt_check->bind_param("s", $email);
                                $stmt_check->execute();
                                $stmt_check->store_result();

                                if ($stmt_check->num_rows > 0) {
                                    $mensaje = "La cuenta ya esta registrada.";
                                } else {
                                    // Insertar usuario con contraseña hasheada
                                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                                    $rol = 'editor';

                                    $stmt_insert = $conn->prepare(
                                        "INSERT INTO usuarios (nombre, email, `contraseña`, fecha_nac, rol) 
                                         VALUES (?, ?, ?, ?, ?)"
                                    );
                                    if (!$stmt_insert) {
                                        $mensaje = "Error al preparar el registro: " . $conn->error;
                                    } else {
                                        $stmt_insert->bind_param("sssss", $nombre, $email, $hashed_password, $fecha_nacimiento, $rol);

                                        if ($stmt_insert->execute()) {
                                            $mensaje = "Registro exitoso. Ahora puedes iniciar sesion.";
                                            header("Location: inicioSesion.php");
                                            exit();
                                        } else {
                                            if ((int)$conn->errno === 1062) {
                                                $mensaje = "La cuenta ya esta registrada.";
                                            } else {
                                                $mensaje = "Error al registrar: " . $stmt_insert->error;
                                            }
                                        }
                                        $stmt_insert->close();
                                    }
                                }
                                $stmt_check->close();
                            }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>CineBlog - Registro</title>
        <link rel="stylesheet" href="css/styles_registro.css">
        <link rel="stylesheet" href="css/style_switch.css">
        <!-- Librería de Bootstrap Icons -->
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
        <label for="theme-switch" class="switch">
            <span class="icon-sun">☀️</span>
            <span class="icon-moon">🌙</span>
        </label>
        </div>
        <div class="container">
            <a href="inicioSesion.php" class="btn-regresar">
                <i class="bi bi-arrow-left-circle"></i> Regresar
            </a>
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog">
                <h1>CineBlog</h1>
            </div>

            <h2>Registro</h2>
            <form method='POST'>
            <!-- Formulario de registro con validación HTML5 y un botón para mostrar/ocultar contraseña -->
                <label for="nombre">Nombre completo:</label>
                <input type="text" id="nombre" name="nombre" autocomplete="name" required>

                <label for="email">Correo electrónico:</label>
                <input type="email" id="email" name="email" autocomplete="email" required
                    pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">

                <label for="password">Contraseña (mínimo 8 caracteres, mayúscula, número y carácter especial):</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" autocomplete="new-password"
                        minlength="8" 
                        pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$" 
                        title="Debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial" 
                        required>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>

                <label for="confirm_password">Confirmar contraseña:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="bi bi-eye"></i>
                    </span>
                </div>

                <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                <!-- 🔹 Evitar poner fechas futuras -->
                  <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" autocomplete="bday"
                       max="<?php echo date('Y-m-d'); ?>" required>

                <button type="submit">Registrarse</button>
            </form>

            <!-- Mensaje de error o exito-->
            <?php if (!empty($mensaje)) : ?>
                <p class="form-message"><?php echo $mensaje; ?></p>
            <?php endif; ?>
            <?php if (!empty($debugDbInfo)) : ?>
                <p class="form-message"><?php echo htmlspecialchars($debugDbInfo, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
        </div>

        <script>
        // Función para mostrar/ocultar contraseña
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
