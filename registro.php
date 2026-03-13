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