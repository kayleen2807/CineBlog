<!--Mensaje de sesión cerrada -->
<?php 
$mensaje = "";
if (isset($_GET['mensaje']) && $_GET['mensaje'] == "sesion_cerrada") {
    $mensaje = "Sesión cerrada exitosamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cineblog - Acceso</title>
    <link rel="stylesheet" href="css/style_switch.css">
    <link rel="stylesheet" href="css/styles_seleccion.css">
    <!-- 🔹 Estilos globales de tema -->
    <link rel="stylesheet" href="css/temas.css">
    <!-- 🔹 Script global de tema -->
    <script src="js/temas.js" defer></script>
</head>
<style>
  a {
    color: var(--muted);
    text-decoration: none;
  }
  a:hover{
    text-decoration: none;
    color: white;
  }
</style>
<body>
    <!-- 🔹 Switch de tema (arriba a la derecha) -->
    <div class="theme-toggle">
        <input type="checkbox" id="theme-switch">
        <label for="theme-switch" class="switch"></label>
    </div>

    <!-- Estilos de enlace y alerta movidos a css/styles_seleccion.css -->
    <!-- Mensaje de sesión cerrada -->
    <div class="alerta_exito">
            <?php echo $mensaje; ?>
    </div>
    <script>
        setTimeout(() => {
            const alerta = document.querySelector('.alerta_exito');
            if (alerta) alerta.style.display = 'none';
        }, 4000); // Alerta de 4 segundos
    </script>
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