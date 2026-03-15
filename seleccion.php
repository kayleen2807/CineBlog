<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cineblog - Acceso</title>
    <link rel="stylesheet" href="css/styles_seleccion.css">
</head>
<body>
    <div class="container">
        <!--Logo y el titulo (header de este apartado) -->
        <header>
            <img src="css/cineBlog_Logo.png" alt="Logo" class="logo">
            <h1>Cineblog</h1>
        </header>

        <h2>Seleccione cómo desea entrar</h2>
        
        <!--Botones de acceso (usuario y visitante) -->
        <div class="options">
            <div class="container1">
                <div class="icon">👤</div>
                <h3>Visitante</h3>
                <a href="visitante.php">
                <p>Acceso limitado sin registro</p>
                </a>
            </div>
            <div class="container1">
                <div class="icon">👥</div>
                <h3>Usuario</h3>
                <a href="inicioSesion.php">
                    <p>Usuarios ya registrados</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>