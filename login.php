<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/Auth.php';

if (current_user()) {
    redirect('index.php');
}

$error = null;
$loggedOut = flash_get('success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (Auth::attempt($login, $password)) {
        redirect('index.php');
    }

    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="author" content="Ariel Geovanny Andino Chinchilla">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Acceso | <?php echo e(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .auth-shell { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; }
        .auth-card { width: 100%; max-width: 420px; background: #fff; border-radius: 18px; box-shadow: 0 18px 50px rgba(0,0,0,.12); padding: 28px; }
        .auth-title { margin: 0 0 8px; font-size: 1.8rem; }
        .auth-sub { margin: 0 0 24px; color: #555; }
        .auth-field { margin-bottom: 16px; }
        .auth-field label { display: block; margin-bottom: 6px; font-weight: 600; }
        .auth-field input { width: 100%; padding: 12px 14px; border: 1px solid #d0d7de; border-radius: 10px; font-size: 1rem; box-sizing: border-box; }
        .auth-btn { width: 100%; padding: 12px 16px; border: 0; border-radius: 10px; background: #0a5bd8; color: #fff; font-size: 1rem; font-weight: 700; cursor: pointer; }
        .auth-alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; font-size: .95rem; }
        .auth-alert.error { background: #fdeaea; color: #8f1d1d; }
        .auth-alert.success { background: #eaf8ee; color: #176b35; }
    </style>
</head>
<body>
    <div class="auth-shell">
        <div class="auth-card">
            <h1 class="auth-title">Iniciar sesión</h1>
            <p class="auth-sub">Accede al dashboard protegido de Power BI.</p>

            <?php if ($loggedOut): ?>
                <div class="auth-alert success"><?php echo e($loggedOut); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="auth-alert error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="auth-field">
                    <label for="login">Usuario o correo</label>
                    <input id="login" type="text" name="login" required>
                </div>

                <div class="auth-field">
                    <label for="password">Contraseña</label>
                    <input id="password" type="password" name="password" required>
                </div>

                <button class="auth-btn" type="submit">Entrar</button>
            </form>
        </div>
    </div>
</body>
</html>
