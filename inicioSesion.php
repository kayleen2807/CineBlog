<!-- Parte de php para conectar con la base de datos y envíar una respuesta -->
<?php
session_start();

// Conexión a la base de datos
include 'includes/conexion.php';

$mensaje = ""; // Variable para mostrar mensajes en la misma página

// Si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

            if ($stmt->num_rows > 0) {
                $stmt->bind_result($id_usuario, $nombre, $hashed_password, $rol);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['usuario_id'] = $id_usuario;
                    $_SESSION['nombre'] = $nombre;
                    $_SESSION['rol'] = $rol;
                    $mensaje = "Inicio de sesión exitoso. Bienvenido a CineBlog.";
                    // Aquí podrías redirigir si quieres (ya implementado):
                    header("Location: index.php");
                    exit();
                } else {
                    $mensaje = "Contraseña incorrecta.";
                }
            } else {
                $mensaje = "El correo no está registrado.";
            }

            $stmt->close();
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

    </head>

    <body>
        <div class="container">
        
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog"> <!-- Tener un logo.png en el proyecto -->
                <h1>CineBlog</h1>
            </div>

            <!-- Formulario de inicio de sesión -->
            <h2>Inicio de sesión</h2>
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
