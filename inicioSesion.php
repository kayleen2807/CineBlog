<!-- Parte de php para conectar con la base de datos y envíar una respuesta -->
<?php
session_start();

// Conexión a la base de datos
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "cineblog_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$mensaje = ""; // Variable para mostrar mensajes en la misma página

// Si se envió el formulario
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = $_POST['email'];
    $password = $_POST['password'];

    // Consulta segura
    $stmt = $conn->prepare("SELECT contraseña FROM usuarios WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($hashed_password);
        $stmt->fetch();

        if (password_verify($password, $hashed_password)) {
            $_SESSION['usuario'] = $email;
            $mensaje = "Inicio de sesión exitoso. Bienvenido a CineBlog.";
            // Aquí podrías redirigir si quieres:
            // header("Location: pagina_principal.php");
            // exit();
        } else {
            $mensaje = "Contraseña incorrecta.";
        }
    } else {
        $mensaje = "El correo no está registrado.";
    }

    $stmt->close();
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
                <input type="email" id="email" name="email" required> <!-- El atributo "required" asegura que el campo no se deje vacío -->

                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Iniciar sesión</button> <!-- Botón para enviar el formulario -->

                <!-- Enlace a la página de recuperación de contraseña -->
                <footer>
                    <p>¿Olvidaste tu contraseña? <a href="recuperarContrasena.php">Recupérala aquí</a></p>
                </footer>
            </form>

            <!-- Mensaje para saber si puede iniciar sesión -->
             <?php if (!empty($mensaje)) : ?>
                <p style="color: red; margin-top: 15px;"><?php echo $mensaje; ?></p>
            <?php endif; ?>
        </div>
    </body>
</html>