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
    <link rel="stylesheet" href="css/styles_seleccion.css">
</head>
<body>
    <style>
        a {
            text-decoration: none;
            color: #dcd9d9;
        }
        .alerta_exito {
            background-color: #0d1e2f;   
            color: #dcd9d9;              
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #130f31;
            font-weight: bold;
            margin-bottom: 20px;
            animation: fadeOut 4s forwards; 
            

            @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
            }
        }
    </style>
    <div class="alerta_exito">
            <?php echo $mensaje; ?>
    </div>
    <script>
        setTimeout(() => {
            const alerta = document.querySelector('.alerta_exito');
            if (alerta) alerta.style.display = 'none';
        }, 4000); // 4 segundos
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