<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="author" content="Ariel Geovanny Andino Chinchilla">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars(SITE_TITLE); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .user-chip {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .user-chip strong {
            color: #fff;
        }
        .header-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,.45);
            background: rgba(255,255,255,.18);
        }
        .user-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .user-meta small {
            color: rgba(255,255,255,.82);
        }
        .header-nav {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .header-link,
        .logout-link {
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid rgba(255,255,255,.35);
            padding: 8px 12px;
            border-radius: 999px;
        }
        .header-link:hover,
        .logout-link:hover {
            background: rgba(255,255,255,.12);
        }
        @media (max-width: 768px) {
            .header-inner {
                gap: 12px;
            }
            .user-chip {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-inner">
        <div class="header-logo">
            <img src="assets/img/logo.png" alt="Logo empresa" onerror="this.style.display='none'">
        </div>
        <div class="header-title">
            <?php echo htmlspecialchars(SITE_TITLE); ?>
        </div>
        <?php if (isset($user) && is_array($user)): ?>
            <div class="user-chip">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img class="header-avatar" src="<?php echo e($user['avatar_path']); ?>" alt="Avatar de <?php echo e($user['full_name'] ?? $user['username'] ?? 'Usuario'); ?>">
                <?php endif; ?>
                <div class="user-meta">
                    <span><strong><?php echo e($user['full_name'] ?? $user['username'] ?? 'Usuario'); ?></strong></span>
                    <small><?php echo e($user['role_name'] ?? 'viewer'); ?></small>
                </div>
                <div class="header-nav">
                    <?php if (function_exists('is_admin_user') && is_admin_user($user)): ?>
                        <a class="header-link" href="users.php">Usuarios</a>
                    <?php endif; ?>
                    <a class="logout-link" href="logout.php">Cerrar sesión</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<main class="site-main">
