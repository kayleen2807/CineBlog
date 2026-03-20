<?php
// Archivo para mostrar el perfil del usuario, solo accesible si el usuario ha iniciado sesión
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}
//conexión a la base de datos para obtener la información del usuario
$conn = new mysqli("localhost", "root", "", "cineblog_db");
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$id_usuario = $_SESSION['usuario_id'];
$stmt = $conn->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

$foto = !empty($usuario['foto_perfil']) ? $usuario['foto_perfil'] : "uploads/default.png";

$stmt->close();
$conn->close();


?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/main.css">
    <title>Perfil CineBlog</title>
</head>
<body>

    <div class="page-shell">
        <nav class="head">
            <div class="logo">
                <a href="#" aria-label="CineBlog">
                    <img class="logo-c" src="css/cineBlog_Logo.png" alt="C">
                    <span class="logo-rest">ineBlog</span>
                </a>
            </div>
            <div class="navbar" aria-label="Navegación principal">
                <a href="#" class="nav-link active">Tendencias</a>
                <a href="#" class="nav-link">Estrenos</a>
                <a href="#" class="nav-link">Recomendado</a>
            </div>
        </nav>

        <header class="header-container">
            <div class="cover-photo" role="img" aria-label="Foto de portada"></div>
            <div class="profile-section">
                <div class="profile-pic-container">
                    <img src="<?php echo $foto; ?>" alt="Foto de perfil" class="profile-pic">
                    <form action="subirFoto.php" method="POST" enctype="multipart/form-data" class="form_foto">
                        <label for="foto" class="file-label">Seleccionar foto</label>
                        <input type="file" name="foto" id="foto" accept="image/*" required>
                        <button type="submit" class="btn_foto">Actualizar foto</button>
                    </form>
                </div>
                <div class="user-info">
                    <h1><?php echo $_SESSION['nombre']; ?></h1>
                    <div class="metrics-row" aria-label="Métricas del perfil">
                        <div class="metric-list">
                            <button type="button" class="metric follower-count metric-btn" aria-label="Followers">
                                <span class="follower-num">1.2k</span>
                                <span class="follower-label">Followers</span>
                            </button>
                            <button type="button" class="stat metric" data-stat="Reviews">
                                <span class="stat-num">10</span>
                                <span class="stat-label">Reviews</span>
                            </button>
                
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <nav class="tab-nav" aria-label="Secciones del perfil">
            <a href="#" class="tab">My Reviews</a>
            <a href="#" class="tab">Activity</a>
            <a href="#" class="tab">WatchList</a>
            <a href="#" class="tab">Likes</a>
            <a href="#" class="tab">Settings</a>
        </nav>

        <main class="main-layout">

            <section class="left-column">
                <h2 class="section-title">Last Review</h2>

                <article class="card">
                    <img src="assets/poster-arcane.jpg" alt="Arcane">
                    <div class="card-content">
                        <h3>Arcane</h3>
                        <div class="stars">★★★★★</div>
                        <p class="review-copy collapsed">
                            blablablabla texto de la reseña... blablablabla texto de la reseña... blablablabla texto de la reseña...
                            blablablabla texto de la reseña... blablablabla texto de la reseña... blablablabla texto de la reseña...
                        </p>
                        <div class="card-footer">
                            <button type="button" class="toggle-review">Mostrar mas</button>
                            <div class="card-icons" aria-label="Acciones">
                                <button type="button" class="icon-btn icon-heart" aria-label="Me gusta">♡</button>
                                <button type="button" class="icon-btn" aria-label="Guardar">◌</button>
                                <button type="button" class="icon-btn" aria-label="Compartir">↗</button>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="card">
                    <img src="assets/poster-substance.png" alt="The Substance">
                    <div class="card-content">
                        <h3>The Substance</h3>
                        <div class="stars">★★★★☆</div>
                        <p class="review-copy collapsed">
                            blablablabla texto de la reseña... blablablabla texto de la reseña... blablablabla texto de la reseña...
                            blablablabla texto de la reseña... blablablabla texto de la reseña... blablablabla texto de la reseña...
                        </p>
                        <div class="card-footer">
                            <button type="button" class="toggle-review">Show More</button>
                            <div class="card-icons" aria-label="Acciones">
                                <button type="button" class="icon-btn icon-heart" aria-label="Me gusta">♡</button>
                                <button type="button" class="icon-btn" aria-label="Guardar">◌</button>
                                <button type="button" class="icon-btn" aria-label="Compartir">↗</button>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="right-column">
                <div class="sidebar-box">
                    <h3>Best Reviewed</h3>
                    <div class="best-grid">
                        <div class="best-slot">
                            <img class="best-img" src="assets/poster-1.jpeg" alt="Best reviewed 1">
                        </div>
                        <div class="best-slot">
                            <img class="best-img" src="assets/poster-2.jpg" alt="Best reviewed 2">
                        </div>
                    </div>
                </div>

                <div class="sidebar-box">
                    <h3>Favorite Genres</h3>
                    <div class="tags">
                        <button type="button" class="tag">Comedy</button>
                        <button type="button" class="tag">Anime</button>
                        <button type="button" class="tag">Drama</button>
                        <button type="button" class="tag">Suspense</button>
                    </div>
                </div>
            </aside>

        </main>
    </div>

    <script src="app.js"></script>
</body>
</html>
