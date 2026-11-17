<?php

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function start_session_if_needed(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function current_user(): ?array
{
    start_session_if_needed();
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        redirect('login.php');
    }
    return $user;
}

function is_admin_user(?array $user): bool
{
    return is_array($user) && (($user['role_name'] ?? '') === 'admin');
}

function require_admin(): array
{
    $user = require_auth();

    if (!is_admin_user($user)) {
        flash_set('error', 'No tienes permisos para acceder a la administración de usuarios.');
        redirect('index.php');
    }

    return $user;
}

function flash_set(string $type, string $message): void
{
    start_session_if_needed();
    $_SESSION['flash_' . $type] = $message;
}

function flash_get(string $type): ?string
{
    start_session_if_needed();
    $key = 'flash_' . $type;
    if (!isset($_SESSION[$key])) {
        return null;
    }

    $message = $_SESSION[$key];
    unset($_SESSION[$key]);
    return $message;
}

function app_base_path(string $relativePath = ''): string
{
    $base = __DIR__ . '/..';
    if ($relativePath === '') {
        return $base;
    }

    return $base . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

function avatar_upload_dir(): string
{
    return app_base_path('assets/uploads/avatars');
}

function avatar_public_path(string $filename): string
{
    return 'assets/uploads/avatars/' . ltrim($filename, '/');
}
