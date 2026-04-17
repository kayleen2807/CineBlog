<?php
//Archivo para el registro de nuevos usuarios, con validación de datos y almacenamiento seguro de contraseñas (hashing)
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cineblog_db";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error){
    die("Error de conexión: " . $conn->connect_error);
}

$mensaje = "";

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];

    // VALIDACIÓN DE FORMATO DE CORREO
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensaje = "Por favor ingresa un correo válido.";
    } else {
        // VALIDACIÓN DE DOMINIO DEL CORREO (MX records)
        $dominio = substr(strrchr($email, "@"), 1);
        if (!checkdnsrr($dominio, "MX")) {
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
                $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if($stmt->num_rows > 0){
                    $mensaje = "El correo ya está registrado.";
                } else {
                    // Insertar nuevo usuario con contraseña hasheada
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $rol = 'editor';

                    $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, contraseña, fecha_nac, rol) VALUES (?, ?, ?, ?, ?)");
                    $stmt_insert->bind_param("sssss", $nombre, $email, $hashed_password, $fecha_nacimiento, $rol);

                    if($stmt_insert->execute()){
                        $mensaje = "Registro exitoso. Ahora puedes iniciar sesión.";
                        header("Location: inicioSesion.php");
                        exit();
                    } else {
                        $mensaje = "Error al registrar: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                }
                $stmt->close();
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
        <!-- 🔹 Librería de Bootstrap Icons -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"> <!-- Librería de bootstrap icons -->
    </head>
    <body>
        <div class="container">
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog">
                <h1>CineBlog</h1>
            </div>

            <h2>Registro</h2>
            <form method='POST'>
                <label for="nombre">Nombre completo:</label>
                <input type="text" id="nombre" name="nombre" required>

                <label for="email">Correo electrónico:</label>
                <!-- 🔹 CAMBIO EN EL FORMULARIO: type="email" + required + pattern -->
                <input type="email" id="email" name="email" required
                    pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">

                <label for="password">Contraseña (mínimo 8 caracteres, mayúscula, número y carácter especial):</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" 
                        minlength="8" 
                        pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$" 
                        title="Debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial" 
                        required>
                    <span class="toggle-password" onclick="togglePassword('password')">
                        <i class="bi bi-eye"></i> <!-- 🔹 Ícono de Bootstrap -->
                    </span>
                </div>

                <label for="confirm_password">Confirmar contraseña:</label>
                <div class="password-container">
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
                    <span class="toggle-password" onclick="togglePassword('confirm_password')">
                        <i class="bi bi-eye"></i> <!-- 🔹 Ícono de Bootstrap -->
                    </span>
                </div>

                <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>

                <button type="submit">Registrarse</button>
            </form>

            <?php if (!empty($mensaje)) : ?>
                <p style="color: yellow; margin-top: 15px;"><?php echo $mensaje; ?></p>
            <?php endif; ?>
        </div>

        <!-- 🔹 Script para alternar íconos de Bootstrap -->
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
