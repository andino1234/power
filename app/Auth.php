<?php
/**
 * Portal de reportes Power BI — autenticación de usuarios.
 *
 * @author Ariel Geovanny Andino Chinchilla
 * @link   https://github.com/andino1234
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';

final class Auth
{
    public static function attempt(string $login, string $password): bool
    {
        $pdo = Database::connect();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE (username = :login OR email = :login) AND is_active = 1 LIMIT 1');
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch();

        $success = $user && password_verify($password, $user['password_hash']);
        self::logAttempt($pdo, $user['id'] ?? null, $login, $success);

        if (!$success) {
            return false;
        }

        self::storeSessionUser($user);
        return true;
    }

    public static function logout(): void
    {
        start_session_if_needed();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private static function storeSessionUser(array $user): void
    {
        start_session_if_needed();
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role_name' => $user['role_name'],
            'avatar_path' => $user['avatar_path'] ?? null,
        ];
    }

    private static function logAttempt(PDO $pdo, ?int $userId, string $login, bool $success): void
    {
        $stmt = $pdo->prepare('INSERT INTO login_audit (user_id, login_value, ip_address, user_agent, was_successful) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $login,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $success ? 1 : 0,
        ]);
    }
}
