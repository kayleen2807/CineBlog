<?php
session_start();
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: inicioSesion.php");
    exit();
}

include 'includes/conexion.php';

$userId = (int)$_SESSION['usuario_id'];

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function flash_settings(string $message, string $type = 'success'): void
{
    $_SESSION['settings_message'] = $message;
    $_SESSION['settings_type'] = $type;
}

function checked_value($value): string
{
    return ((int)$value === 1) ? 'checked' : '';
}

function selected_value($value, string $expected): string
{
    return ((string)$value === $expected) ? 'selected' : '';
}

function sanitize_upload_name(string $filename): string
{
    $clean = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($filename));
    return $clean ?: 'perfil.jpg';
}

$conn->query("
    CREATE TABLE IF NOT EXISTS ajustes_usuario (
        usuario_id INT(11) NOT NULL,
        bio TEXT NULL,
        cuenta_privada TINYINT(1) NOT NULL DEFAULT 0,
        mostrar_actividad TINYINT(1) NOT NULL DEFAULT 1,
        quien_comenta VARCHAR(30) NOT NULL DEFAULT 'todos',
        bloqueados TEXT NULL,
        notif_likes TINYINT(1) NOT NULL DEFAULT 1,
        notif_comentarios TINYINT(1) NOT NULL DEFAULT 1,
        notif_respuestas TINYINT(1) NOT NULL DEFAULT 1,
        notif_seguidores TINYINT(1) NOT NULL DEFAULT 1,
        notif_estrenos TINYINT(1) NOT NULL DEFAULT 1,
        idioma VARCHAR(10) NOT NULL DEFAULT 'es',
        autoplay TINYINT(1) NOT NULL DEFAULT 0,
        two_factor TINYINT(1) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
");
$conn->query("ALTER TABLE ajustes_usuario DROP COLUMN IF EXISTS trailer_calidad");

$stmtDefaults = $conn->prepare("INSERT IGNORE INTO ajustes_usuario (usuario_id) VALUES (?)");
$stmtDefaults->bind_param("i", $userId);
function normalize_for_moderation_spaces(string $text): string
{
    $value = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false) $value = $converted;
    }

    $map = [
        '@' => 'a',
        '4' => 'a',
        '€' => 'e',
        '3' => 'e',
        '1' => 'i',
        '!' => 'i',
        '|' => 'i',
        '0' => 'o',
        '$' => 's',
        '5' => 's',
        '§' => 's',
        '7' => 't',
        '+' => 't',
        '2' => 'z',
        'ñ' => 'n',
        'ç' => 'c',
    ];

    $value = strtr($value, $map);
    $value = preg_replace('/[^a-z]+/i', ' ', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';
    return trim($value);
}

function contains_profanity(string $text): bool
{
    $normalized = normalize_for_moderation_spaces($text);
    if ($normalized === '') return false;

    $tokens = preg_split('/\s+/', $normalized) ?: [];
    $badWords = [
        'puta', 'puto', 'puts', 'pt', 'mierda', 'pendejo', 'pendeja', 'cabron',
        'chingar', 'chingada', 'verga', 'culero', 'pinche', 'joder', 'idiota',
        'imbecil', 'alv', 'alm', 'vtalv', 'ctm', 'putos', 'chingados', 'vergas',
        'culeros', 'pinches', 'jodidos', 'idiotas', 'imbeciles', 'culo', 'culito',
        'pene', 'penes', 'pito', 'pitos', 'pija', 'pijas', 'polla', 'pollas',
        'vagina', 'coño'
    ];

    foreach ($badWords as $bad) {
        $chars = preg_split('//u', $bad, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $regex = '(?:^|\s)';
        $total = count($chars);
        foreach ($chars as $i => $ch) {
            $regex .= preg_quote($ch, '/') . '+';
            if ($i < $total - 1) $regex .= '\s*';
        }
        $regex .= '(?:\s|$)';
        if (preg_match('/' . $regex . '/i', $normalized)) return true;

        $len = strlen($bad);
        foreach ($tokens as $token) {
            if ($token === $bad) return true;
            if ($len >= 5 && (str_starts_with($token, $bad) || str_ends_with($token, $bad))) return true;
        }
    }

    return false;
}
$stmtDefaults->execute();
$stmtDefaults->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_profile') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));
        $currentPassword = (string)($_POST['current_password'] ?? '');
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if (contains_profanity($bio)) {
            flash_settings("La biografia contiene lenguaje inapropiado.", "error");
            header("Location: ajustes.php#perfil");
            exit();
        }

        $nombreLength = function_exists('mb_strlen') ? mb_strlen($nombre, 'UTF-8') : strlen($nombre);
        if ($nombre === '' || $nombreLength > 100) {
            flash_settings("El nombre debe tener entre 1 y 100 caracteres.", "error");
            header("Location: ajustes.php#perfil");
            exit();
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_settings("Ingresa un correo electronico valido.", "error");
            header("Location: ajustes.php#perfil");
            exit();
        }

        $stmtEmail = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario <> ? LIMIT 1");
        $stmtEmail->bind_param("si", $email, $userId);
        $stmtEmail->execute();
        $emailTaken = $stmtEmail->get_result()->num_rows > 0;
        $stmtEmail->close();

        if ($emailTaken) {
            flash_settings("Ese correo ya esta en uso por otra cuenta.", "error");
            header("Location: ajustes.php#perfil");
            exit();
        }

        $profileUpdated = false;
        $photoUpdated = false;

        $stmtUser = $conn->prepare("SELECT `contraseña` FROM usuarios WHERE id_usuario = ? LIMIT 1");
        $stmtUser->bind_param("i", $userId);
        $stmtUser->execute();
        $passwordHash = (string)($stmtUser->get_result()->fetch_assoc()['contraseña'] ?? '');
        $stmtUser->close();

        if ($newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '') {
            if ($newPassword === '' || $confirmPassword === '' || $currentPassword === '') {
                flash_settings("Completa la contrasena actual, la nueva y la confirmacion.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            if (!password_verify($currentPassword, $passwordHash)) {
                flash_settings("La contrasena actual no coincide.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            if (strlen($newPassword) < 8) {
                flash_settings("La nueva contrasena debe tener al menos 8 caracteres.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            if ($newPassword !== $confirmPassword) {
                flash_settings("La confirmacion de contrasena no coincide.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, `contraseña` = ? WHERE id_usuario = ?");
            $stmt->bind_param("sssi", $nombre, $email, $newHash, $userId);
            $profileUpdated = $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ? WHERE id_usuario = ?");
            $stmt->bind_param("ssi", $nombre, $email, $userId);
            $profileUpdated = $stmt->execute();
            $stmt->close();
        }

        $stmtBio = $conn->prepare("UPDATE ajustes_usuario SET bio = ? WHERE usuario_id = ?");
        $stmtBio->bind_param("si", $bio, $userId);
        $stmtBio->execute();
        $stmtBio->close();

        if (isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $foto = $_FILES['foto'];
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
            ];

            if ($foto['error'] !== UPLOAD_ERR_OK) {
                flash_settings("No se pudo subir la foto seleccionada.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            if ($foto['size'] > 3 * 1024 * 1024) {
                flash_settings("La foto no debe superar 3 MB.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($foto['tmp_name']);
            if (!isset($allowed[$mime])) {
                flash_settings("Usa una imagen JPG, PNG, GIF o WEBP.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }

            $targetDir = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $safeBase = sanitize_upload_name((string)$foto['name']);
            $fileName = "perfil_" . $userId . "_" . time() . "_" . $safeBase;
            $targetFile = $targetDir . DIRECTORY_SEPARATOR . $fileName;

            if (move_uploaded_file($foto['tmp_name'], $targetFile)) {
                $stmtPhoto = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?");
                $stmtPhoto->bind_param("si", $fileName, $userId);
                $photoUpdated = $stmtPhoto->execute();
                $stmtPhoto->close();
            } else {
                flash_settings("No se pudo guardar la foto en el servidor.", "error");
                header("Location: ajustes.php#perfil");
                exit();
            }
        }

        if ($profileUpdated) {
            $_SESSION['nombre'] = $nombre;
        }

        flash_settings($photoUpdated ? "Perfil y foto actualizados correctamente." : "Perfil actualizado correctamente.");
        header("Location: ajustes.php#perfil");
        exit();
    }

    if ($action === 'delete_photo') {
        $defaultPhoto = "default.png";
        $stmt = $conn->prepare("UPDATE usuarios SET foto_perfil = ? WHERE id_usuario = ?");
        $stmt->bind_param("si", $defaultPhoto, $userId);
        $stmt->execute();
        $stmt->close();
        flash_settings("Foto de perfil restablecida.");
        header("Location: ajustes.php#perfil");
        exit();
    }

    if ($action === 'save_settings') {
        $cuentaPrivada = isset($_POST['cuenta_privada']) ? 1 : 0;
        $mostrarActividad = isset($_POST['mostrar_actividad']) ? 1 : 0;
        $quienComentaInput = $_POST['quien_comenta'] ?? 'todos';
        $quienComenta = in_array($quienComentaInput, ['todos', 'seguidores', 'nadie'], true) ? $quienComentaInput : 'todos';
        $bloqueados = trim((string)($_POST['bloqueados'] ?? ''));
        $notifLikes = isset($_POST['notif_likes']) ? 1 : 0;
        $notifComentarios = isset($_POST['notif_comentarios']) ? 1 : 0;
        $notifRespuestas = isset($_POST['notif_respuestas']) ? 1 : 0;
        $notifSeguidores = isset($_POST['notif_seguidores']) ? 1 : 0;
        $notifEstrenos = isset($_POST['notif_estrenos']) ? 1 : 0;
        $idioma = 'es';
        $autoplay = isset($_POST['autoplay']) ? 1 : 0;
        $twoFactor = isset($_POST['two_factor']) ? 1 : 0;

        $stmt = $conn->prepare("
            UPDATE ajustes_usuario
            SET cuenta_privada = ?, mostrar_actividad = ?, quien_comenta = ?, bloqueados = ?,
                notif_likes = ?, notif_comentarios = ?, notif_respuestas = ?, notif_seguidores = ?, notif_estrenos = ?,
                idioma = ?, autoplay = ?, two_factor = ?
            WHERE usuario_id = ?
        ");
        $stmt->bind_param(
            "iissiiiiisiii",
            $cuentaPrivada,
            $mostrarActividad,
            $quienComenta,
            $bloqueados,
            $notifLikes,
            $notifComentarios,
            $notifRespuestas,
            $notifSeguidores,
            $notifEstrenos,
            $idioma,
            $autoplay,
            $twoFactor,
            $userId
        );
        $stmt->execute();
        $stmt->close();

        flash_settings("Ajustes guardados correctamente.");
        header("Location: ajustes.php#preferencias");
        exit();
    }
}

$stmt = $conn->prepare("
    SELECT u.nombre, u.email, u.foto_perfil, u.rol,
           a.bio, a.cuenta_privada, a.mostrar_actividad, a.quien_comenta, a.bloqueados,
           a.notif_likes, a.notif_comentarios, a.notif_respuestas, a.notif_seguidores, a.notif_estrenos,
           a.idioma, a.autoplay, a.two_factor, a.updated_at
    FROM usuarios u
    LEFT JOIN ajustes_usuario a ON a.usuario_id = u.id_usuario
    WHERE u.id_usuario = ?
    LIMIT 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();
$conn->close();

$photo = !empty($user['foto_perfil']) ? $user['foto_perfil'] : 'default.png';
$bio = $user['bio'] ?? '';
$blockedUsers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string)($user['bloqueados'] ?? '')))));

$flashMessage = $_SESSION['settings_message'] ?? '';
$flashType = $_SESSION['settings_type'] ?? 'success';
unset($_SESSION['settings_message'], $_SESSION['settings_type']);

$currentDevice = $_SERVER['HTTP_USER_AGENT'] ?? 'Navegador actual';
$loginTime = $_SESSION['login_time'] ?? time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajustes - CineBlog</title>
    <link rel="stylesheet" href="css/main.css?v=<?php echo filemtime(__DIR__ . '/css/main.css'); ?>">
    <link rel="stylesheet" href="css/ajustes_inicio.css?v=<?php echo filemtime(__DIR__ . '/css/ajustes_inicio.css'); ?>">
    <link rel="stylesheet" href="css/style_switch.css?v=<?php echo filemtime(__DIR__ . '/css/style_switch.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Unbounded:wght@600;700&family=Space+Grotesk:wght@300..700&family=Anton&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="js/settings.js" defer></script>
    <script src="js/cinedbg.js" defer></script>
    <!-- 🔹 Estilos globales de tema -->
    <link rel="stylesheet" href="css/temas.css?v=<?php echo filemtime(__DIR__ . '/css/temas.css'); ?>">
    <!-- 🔹 Script global de tema -->
     <script src="js/temas.js" defer></script>
</head>
<body class="settings-body">
<svg class="settings-sprite" aria-hidden="true">
    <symbol id="icon-user" viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.4 0-8 2.1-8 4.7V20h16v-1.3c0-2.6-3.6-4.7-8-4.7Z"/></symbol>
    <symbol id="icon-lock" viewBox="0 0 24 24"><path d="M17 9h-1V7A4 4 0 0 0 8 7v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2Zm-7-2a2 2 0 0 1 4 0v2h-4Zm3 9.7V18h-2v-1.3a2 2 0 1 1 2 0Z"/></symbol>
    <symbol id="icon-bell" viewBox="0 0 24 24"><path d="M18 16v-5a6 6 0 0 0-5-5.9V3h-2v2.1A6 6 0 0 0 6 11v5l-2 2v1h16v-1Zm-6 6a2.5 2.5 0 0 0 2.4-2h-4.8A2.5 2.5 0 0 0 12 22Z"/></symbol>
    <symbol id="icon-sliders" viewBox="0 0 24 24"><path d="M6 4h2v7H6Zm0 9h2v7H6Zm10-9h2v3h-2Zm0 5h2v11h-2ZM3 10h8v2H3Zm10-4h8v2h-8Zm0 10h8v2h-8ZM3 18h8v2H3Z"/></symbol>
    <symbol id="icon-shield" viewBox="0 0 24 24"><path d="M12 2 4 5v6c0 5 3.4 9.7 8 11 4.6-1.3 8-6 8-11V5Zm0 17.8A10.5 10.5 0 0 1 6 11V6.4l6-2.2 6 2.2V11a10.5 10.5 0 0 1-6 8.8Z"/></symbol>
    <symbol id="icon-save" viewBox="0 0 24 24"><path d="M17 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7Zm-5 16a3 3 0 1 1 3-3 3 3 0 0 1-3 3ZM6 5h9v5H6Z"/></symbol>
</svg>

<div class="settings-cinema-bg">
    <canvas id="cineBg"></canvas>
</div>

<?php if ($flashMessage !== ''): ?>
    <div class="settings-toast <?php echo e($flashType); ?>" role="status">
        <?php echo e($flashMessage); ?>
    </div>
<?php endif; ?>

<div class="settings-shell">
    <aside class="settings-sidebar" aria-label="Navegacion de ajustes">
        <a class="settings-brand" href="index.php" aria-label="Volver a CineBlog">
            <img src="css/cineBlog_Logo.png" alt="CineBlog">
            <span>CineBlog</span>
        </a>

        <div class="settings-user-mini">
            <img src="uploads/<?php echo e($photo); ?>" alt="Foto de <?php echo e($user['nombre'] ?? 'Usuario'); ?>">
            <div>
                <strong><?php echo e($user['nombre'] ?? 'Usuario'); ?></strong>
                <span><?php echo e($user['rol'] ?? 'editor'); ?></span>
            </div>
        </div>

        <nav class="settings-nav">
            <a href="#perfil" class="settings-nav-link active"><svg><use href="#icon-user"></use></svg><span>Perfil</span></a>
            <a href="#privacidad" class="settings-nav-link"><svg><use href="#icon-lock"></use></svg><span>Privacidad</span></a>
            <a href="#preferencias" class="settings-nav-link"><svg><use href="#icon-sliders"></use></svg><span>Preferencias</span></a>
            <a href="#seguridad" class="settings-nav-link"><svg><use href="#icon-shield"></use></svg><span>Seguridad</span></a>
        </nav>

        <div class="settings-sidebar-footer">
            <a href="index.php">Inicio</a>
            <a href="perfil.php">Ver perfil</a>
        </div>
    </aside>

    <main class="settings-main">
        <header class="settings-topbar">
            <div class="settings-topbar-brand">
                <img src="css/cineBlog_Logo.png" alt="CineBlog">
                <span>CineBlog</span>
            </div>
            <div class="settings-search-pill" aria-label="Seccion actual">
                <span aria-hidden="true">⚙</span>
                <strong>Perfil, privacidad y preferencias</strong>
            </div>
            <div class="settings-save-status" id="saveStatus">
                Ultima actualizacion: <?php echo e($user['updated_at'] ?? 'pendiente'); ?>
            </div>
        </header>

        <form class="settings-grid" id="profileForm" action="ajustes.php#perfil" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_profile">

            <section class="settings-card settings-card-wide reveal" id="perfil">
                <div class="settings-card-head">
                    <span class="section-icon"><svg><use href="#icon-user"></use></svg></span>
                    <div>
                        <h2>Perfil</h2>
                        <p>Actualiza como te ve la comunidad cinéfila.</p>
                    </div>
                </div>

                <div class="profile-settings-layout">
                    <div class="avatar-editor">
                        <div class="avatar-preview">
                            <img id="avatarPreview" src="uploads/<?php echo e($photo); ?>" alt="Vista previa de perfil">
                        </div>
                        <label class="file-action">
                            Cambiar foto
                            <input type="file" id="foto" name="foto" accept="image/*">
                        </label>
                        <button class="ghost-action" type="button" id="deletePhotoButton">Eliminar foto</button>
                    </div>

                    <div class="settings-fields">
                        <label class="field">
                            <span>Nombre de usuario</span>
                            <input type="text" name="nombre" id="nombre" maxlength="100" value="<?php echo e($user['nombre'] ?? ''); ?>" required>
                        </label>

                        <label class="field">
                            <span>Biografía</span>
                            <textarea name="bio" id="bio" rows="4" maxlength="280" placeholder="Cuenta que tipo de cine te mueve..."><?php echo e($bio); ?></textarea>
                        </label>

                        <label class="field">
                            <span>Correo electrónico</span>
                            <input type="email" name="email" value="<?php echo e($user['email'] ?? ''); ?>" required>
                        </label>

                        <div class="password-grid">
                            <label class="field">
                                <span>Contraseña actual</span>
                                <input type="password" name="current_password" autocomplete="current-password">
                            </label>
                            <label class="field">
                                <span>Nueva contraseña</span>
                                <input type="password" name="new_password" minlength="8" autocomplete="new-password">
                            </label>
                            <label class="field">
                                <span>Confirmar contraseña</span>
                                <input type="password" name="confirm_password" minlength="8" autocomplete="new-password">
                            </label>
                        </div>
                    </div>

                    <aside class="profile-live-preview" aria-label="Resumen del perfil">
                        <span>Resumen</span>
                        <img id="previewPhotoCard" src="uploads/<?php echo e($photo); ?>" alt="">
                        <h3 id="previewName"><?php echo e($user['nombre'] ?? 'Usuario'); ?></h3>
                        <p id="previewBio"><?php echo e($bio !== '' ? $bio : 'Aficionado al cine, reseñas y estrenos.'); ?></p>
                        <div class="preview-stats">
                            <strong>128</strong><span>reviews</span>
                            <strong>42</strong><span>listas</span>
                        </div>
                    </aside>
                </div>

                <div class="form-actions">
                    <button class="save-button" type="submit">
                        <svg><use href="#icon-save"></use></svg>
                        Guardar perfil
                    </button>
                </div>
            </section>
        </form>

        <form id="deletePhotoForm" action="ajustes.php#perfil" method="POST">
            <input type="hidden" name="action" value="delete_photo">
        </form>

        <form class="settings-grid" id="settingsForm" action="ajustes.php#preferencias" method="POST">
            <input type="hidden" name="action" value="save_settings">

            <section class="settings-card reveal" id="privacidad">
                <div class="settings-card-head">
                    <span class="section-icon"><svg><use href="#icon-lock"></use></svg></span>
                    <div>
                        <h2>Privacidad</h2>
                        <p>Controla quien puede verte e interactuar contigo.</p>
                    </div>
                </div>

                <label class="toggle-row">
                    <span><strong>Cuenta privada</strong><small>Solo usuarios aprobados ven tu actividad completa.</small></span>
                    <input type="checkbox" name="cuenta_privada" <?php echo checked_value($user['cuenta_privada'] ?? 0); ?>>
                    <i></i>
                </label>

                <label class="toggle-row">
                    <span><strong>Mostrar actividad reciente</strong><small>Permite que otros vean likes y comentarios recientes.</small></span>
                    <input type="checkbox" name="mostrar_actividad" <?php echo checked_value($user['mostrar_actividad'] ?? 1); ?>>
                    <i></i>
                </label>

                <label class="field">
                    <span>Quién puede comentar</span>
                    <select name="quien_comenta">
                        <option value="todos" <?php echo selected_value($user['quien_comenta'] ?? 'todos', 'todos'); ?>>Todos</option>
                        <option value="seguidores" <?php echo selected_value($user['quien_comenta'] ?? 'todos', 'seguidores'); ?>>Solo seguidores</option>
                        <option value="nadie" <?php echo selected_value($user['quien_comenta'] ?? 'todos', 'nadie'); ?>>Nadie</option>
                    </select>
                </label>

                <label class="field">
                    <span>Bloquear usuarios</span>
                    <textarea name="bloqueados" id="blockedUsersInput" rows="3" placeholder="@usuario, @critico123"><?php echo e($user['bloqueados'] ?? ''); ?></textarea>
                </label>

                <div class="blocked-list" id="blockedList" aria-live="polite">
                    <?php if (count($blockedUsers)): ?>
                        <?php foreach ($blockedUsers as $blocked): ?>
                            <span><?php echo e($blocked); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <em>No hay usuarios bloqueados.</em>
                    <?php endif; ?>
                </div>
            </section>
            
            <section class="settings-card reveal" id="preferencias">
                <div class="settings-card-head">
                    <span class="section-icon"><svg><use href="#icon-sliders"></use></svg></span>
                    <div>
                        <h2>Preferencias</h2>
                        <p>Ajusta como se siente y reproduce CineBlog.</p>
                    </div>
                </div>

                <div class="preference-grid">
                    <label class="field">
                        <span>Tema</span>
                        <select id="themeSelect">
                            <option value="dark">Oscuro cinematográfico</option>
                            <option value="light">Claro</option>
                        </select>
                    </label>

                    <label class="field">
                        <span>Idioma</span>
                        <input type="text" value="Español" readonly>
                        <input type="hidden" name="idioma" value="es">
                    </label>
                </div>
            </section>

            <section class="settings-card settings-card-wide reveal" id="seguridad">
                <div class="settings-card-head">
                    <span class="section-icon"><svg><use href="#icon-shield"></use></svg></span>
                    <div>
                        <h2>Seguridad</h2>
                        <p>Protege tu cuenta y revisa accesos recientes.</p>
                    </div>
                </div>

                <label class="toggle-row">
                    <span><strong>Verificación en dos pasos</strong><small>Agrega una capa extra al iniciar sesion.</small></span>
                    <input type="checkbox" name="two_factor" <?php echo checked_value($user['two_factor'] ?? 0); ?>>
                    <i></i>
                </label>

                <div class="security-columns">
                    <div>
                        <div class="list-head">
                            <h3>Sesiones activas</h3>
                        </div>
                        <div class="session-list" id="sessionList">
                            <article class="session-item current">
                                <strong>Sesión actual</strong>
                                <span><?php echo e($currentDevice); ?> · <?php echo e(date('Y-m-d H:i', (int)$loginTime)); ?></span>
                            </article>
                        </div>
                    </div>

                    <div>
                        <div class="list-head">
                            <h3>Historial de inicios</h3>
                        </div>
                        <div class="session-list">
                            <article class="session-item">
                                <strong>Último inicio registrado</strong>
                                <span><?php echo e(date('Y-m-d H:i', (int)$loginTime)); ?></span>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <div class="sticky-save">
                <span id="dirtyMessage">Revisa tus cambios antes de guardar.</span>
                <button class="save-button" type="submit">
                    <svg><use href="#icon-save"></use></svg>
                    Guardar cambios
                </button>
            </div>
        </form>
    </main>
</div>
</body>
</html>
