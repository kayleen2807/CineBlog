<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¿Quiénes somos? – CineBlog</title>
    <link rel="stylesheet" href="css/temas.css?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Unbounded:wght@600;700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/temas.js" defer></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg, #0f0f13);
            color: var(--text, #e8e8f0);
            min-height: 100vh;
            padding: 40px 20px 60px;
        }

        .page-wrap {
            max-width: 860px;
            margin: 0 auto;
        }

        .logo-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 40px;
        }

        .logo-header img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }

        .logo-header span {
            font-family: 'Unbounded', sans-serif;
            font-size: 1.4rem;
            color: var(--text, #e8e8f0);
        }

        .section-title {
            font-family: 'Unbounded', sans-serif;
            font-size: 1.6rem;
            margin-bottom: 10px;
        }

        .section-subtitle {
            color: var(--muted, #888);
            font-size: 0.95rem;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 50px;
        }

        .card {
            background: var(--card, #1a1a24);
            border: 1px solid var(--border, #2a2a38);
            border-radius: 16px;
            padding: 28px 22px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 10px;
            transition: transform 0.2s, border-color 0.2s;
        }

        .card:hover {
            transform: translateY(-4px);
            border-color: var(--accent, #7c6aff);
        }

        .avatar-circle {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: var(--accent, #7c6aff);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            font-family: 'Unbounded', sans-serif;
            font-weight: 700;
        }

        .card-name {
            font-weight: 700;
            font-size: 1rem;
        }

        .card-role {
            font-size: 0.8rem;
            color: var(--muted, #888);
            background: var(--bg, #0f0f13);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .card-github {
            font-size: 0.82rem;
            color: var(--muted, #888);
            text-decoration: none;
            margin-top: 4px;
        }

        .card-github:hover { color: var(--text, #e8e8f0); }

        .about-block {
            background: var(--card, #1a1a24);
            border: 1px solid var(--border, #2a2a38);
            border-radius: 16px;
            padding: 32px 28px;
            line-height: 1.75;
            font-size: 0.97rem;
            color: var(--muted, #aaa);
            margin-bottom: 30px;
        }

        .about-block strong {
            color: var(--text, #e8e8f0);
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--muted, #888);
            text-decoration: none;
            font-size: 0.9rem;
            margin-bottom: 32px;
            transition: color 0.2s;
        }

        .back-btn:hover { color: var(--text, #e8e8f0); }
    </style>
</head>
<body>

<div class="page-wrap">

    <div class="logo-header">
        <img src="css/cineBlog_Logo.png" alt="Logo CineBlog">
        <span>CineBlog</span>
    </div>

    <a class="back-btn" href="javascript:window.close()">← Cerrar pestaña</a>

    <div class="section-title">🎬 ¿Quiénes somos?</div>
    <p class="section-subtitle">
        CineBlog es un espacio para los amantes del cine y las series. Aquí puedes leer reseñas,
        compartir opiniones y descubrir qué ver. Este proyecto fue creado por un equipo de cinco
        estudiantes apasionados por el desarrollo web y la cultura audiovisual.
    </p>

    <div class="team-grid">

        <div class="card">
            <div class="avatar-circle">KA</div>
            <div class="card-name">Kayleen Avendaño Reguera</div>
            <span class="card-role">Líder del proyecto</span>
            <a class="card-github" href="https://github.com/kayleen2807" target="_blank">@kayleen2807</a>
        </div>

        <div class="card">
            <div class="avatar-circle">JC</div>
            <div class="card-name">Josue Felipe Cruz Espinosa</div>
            <span class="card-role">Desarrollador</span>
            <a class="card-github" href="https://github.com/jcruz31-hue" target="_blank">@jcruz31-hue</a>
        </div>

        <div class="card">
            <div class="avatar-circle">AD</div>
            <div class="card-name">Abril Azucena Díaz Ruelas</div>
            <span class="card-role">Desarrolladora</span>
            <a class="card-github" href="https://github.com/adiaz108" target="_blank">@adiaz108</a>
        </div>

        <div class="card">
            <div class="avatar-circle">CM</div>
            <div class="card-name">Carolina Molina Pimentel</div>
            <span class="card-role">Desarrolladora</span>
            <a class="card-github" href="https://github.com/Carolina234184" target="_blank">@Carolina234184</a>
        </div>

        <div class="card">
            <div class="avatar-circle">MT</div>
            <div class="card-name">Maximiliano Tejeda Figueroa</div>
            <span class="card-role">Desarrollador</span>
            <a class="card-github" href="https://github.com/mtejeda4" target="_blank">@mtejeda4</a>
        </div>

    </div>

    <div class="about-block">
        <strong>Sobre el proyecto</strong><br><br>
        CineBlog nació como un proyecto académico con el objetivo de crear una plataforma donde
        cualquier persona pueda explorar y compartir su amor por el cine y las series.
        Está construido con <strong>PHP</strong>, <strong>HTML</strong>, <strong>CSS</strong> y
        <strong>JavaScript</strong>, conectado a una base de datos MySQL y con integración a la
        API de <strong>TMDB</strong> para enriquecer la información de cada reseña.<br><br>
        El proyecto sigue en desarrollo activo — ¡hay más funciones por venir!
    </div>

</div>

</body>
</html>