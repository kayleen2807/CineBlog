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
            <form action="procesar_login.php" method="POST"> <!-- Cambia "procesar_login.php" por el nombre de tu archivo PHP que procesará el inicio de sesión -->
                <label for="email">Correo electrónico:</label>
                <input type="email" id="email" name="email" required> <!-- El atributo "required" asegura que el campo no se deje vacío -->

                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Iniciar sesión</button> <!-- Botón para enviar el formulario -->
            </form>
        </div>
    </body>
</html>