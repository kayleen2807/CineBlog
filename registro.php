<?php
//Archivo para el registro de nuevos usuarios, con validación de datos y almacenamiento seguro de contraseñas (hashing)
session_start();
// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cineblog_db";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error){
    die("Error de conexión: " . $conn->connect_error);
}

$mensaje = ""; // Variable para mostrar mensajes en la misma página

// Registro de usuario
if($_SERVER["REQUEST_METHOD"] == "POST"){
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];

    // Validar que las contraseñas coincidan
    if($password !== $confirm_password){
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
            $rol = 'editor'; // Asignar rol de usuario en este caso editor

            $stmt_insert = $conn->prepare("INSERT INTO usuarios (nombre, email, contraseña, fecha_nac, rol) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssss", $nombre, $email, $hashed_password, $fecha_nacimiento, $rol);

            if($stmt_insert->execute()){
                $mensaje = "Registro exitoso. Ahora puedes iniciar sesión.";
                // Redirigir a la página de inicio de sesión después del registro 
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
?>

<!DOCTYPE html>
<html lang="es"> <head>
        <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
        
        <title>CineBlog - Registro</title>
        <link rel="stylesheet" href="css/styles_registro.css"> </head>

    <body>
        <div class="container">
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog"> <h1>CineBlog</h1>
            </div>

            <h2>Registro</h2>
            <form method='POST'>
                <label for="nombre">Nombre completo:</label>
                <input type="text" id="nombre" name="nombre" required>

                <label for="email">Correo electrónico:</label>
                <input type="email" id="email" name="email" required>

                <label for="password">Contraseña (mínimo 8 caracteres):</label>
                <input type="password" id="password" name="password" minlength="8" required>

                <label for="confirm_password">Confirmar contraseña (mínimo 8 caracteres):</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>

                <label for="fecha_nacimiento">Fecha de nacimiento:</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>

                <button type="submit">Registrarse</button>
            </form>
        </div>
    </body>
</html>