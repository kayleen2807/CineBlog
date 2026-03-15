<?php
// Inicia la sesión para manejar la autenticación
session_start();

//si no hay rol en la sesión, redirige a inicioSesion.php
if(!isset($_SESSION['rol'])){
    header("Location: inicioSesion.php");
    exit();
}

$rol = $_SESSION['rol'] ?? 'visitante'; // Si no hay rol, asigna 'visitante' por defecto
?>
<!--Maquetado en html para el inicio -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CineBlog</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles_inicio.css">
    
</head>
<body>

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="sb-top">
            <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="perfil.html">Perfil</a></span>
                </button>  
            <?php elseif ($rol == "admin") : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="perfil.html">Perfil</a></span>
                </button>
             <?php else : ?>
                <button class="perfil-btn">
                    <div class="avatar">👤</div>
                        <span><a href="registro.php">¿Registrarse?</a></span>
                </button>
            <?php endif; ?>
        </div>

        <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <div class="sb-item">🔔 <span>Notificaciones</span></div>
            <?php elseif ($rol == "admin") : ?>
                <div class="sb-item">🔔 <span>Notificaciones</span></div>
            <?php endif; ?>

        <div class="sb-cats">

            <div class="cat-label">Categories</div>

            <div class="pills">
                <span class="pill on" onclick="selPill(this)">Movies</span>
                <span class="pill" onclick="selPill(this)">Horror</span>
                <span class="pill" onclick="selPill(this)">Action</span>
                <span class="pill" onclick="selPill(this)">Science fiction</span>
            </div>

        </div>

        <div class="sb-footer">

            <div class="sb-fitem">
                <span>Configuración</span>
                <span>⚙️</span>
            </div>

            <div class="sb-fitem">
            <span><a href="cerrarSesion.php">Cerrar sesión</a></span>
            <span>🚪</span>
            </div>

        </div>

    </aside>

    <!-- MAIN -->

    <div class="main">

        <header class="topbar">

        <div class="logo-text"><span>C</span>ineBlog</div>

        <span style="font-weight:600;text-decoration:underline;text-underline-offset:3px;cursor:pointer;">
        Tendencies
        </span>

        <div class="search-wrap">
            <span style="color:var(--muted)">🔍</span>
            <input type="text" placeholder="Search">
        </div>
        </header>

        <!-- AREA CENTRAL -->
        <div class="feed">
            <!-- Logica php para mostrar funciones dependiendo el rol (por el momento pruebas) -->
            <?php if ($rol == "editor") : ?>
                <button class="create-post">+</button>
                <!-- Se agregaran mas cosas para el editor-->
            <?php elseif ($rol == "admin") : ?>
                <button class="create-post">+</button>
                <!-- Se agregaran mas cosas para el admin-->
            <?php endif; ?>
        </div>
                
    </div>

    <script>
        function selPill(el){
            el.closest('.pills').querySelectorAll('.pill').forEach(p=>p.classList.remove('on'));
            el.classList.add('on');
        }   
    </script>

</body>
</html>