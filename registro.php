<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8"> <!-- Esto es para que se muestren correctamente los caracteres especiales -->
        <title>CineBlog - Registro</title>
        <link rel="stylesheet" href="css/styles_registro.css"> <!-- Enlaza con tu archivo CSS para estilos personalizados -->
    </head>

    <body>
        <div class="container">
            <div class="logo">
                <img src="css/cineBlog_Logo.png" alt="Logo CineBlog"> <!-- Tener un logo.png en el proyecto -->
                <h1>CineBlog</h1>
            </div>

            <!-- Formulario de registro -->
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