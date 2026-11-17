<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Database.php';
require_once __DIR__ . '/app/helpers.php';

$user = require_admin();
$pdo = Database::connect();

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

$availableRoles = ['admin', 'viewer'];
$errors = [];
$successMessage = flash_get('success');
$errorMessage = flash_get('error');
$shouldOpenEditModal = false;
$shouldOpenCreateModal = false;

$createForm = [
    'full_name' => '',
    'email' => '',
    'username' => '',
    'role_name' => 'viewer',
    'is_active' => '1',
];

$editForm = [
    'id' => 0,
    'full_name' => '',
    'email' => '',
    'username' => '',
    'avatar_path' => '',
    'role_name' => 'viewer',
    'is_active' => 1,
];

function normalize_upload_extension(string $mimeType): ?string
{
    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    return $map[$mimeType] ?? null;
}

function handle_avatar_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el avatar. Verifica el archivo e inténtalo nuevamente.');
    }
    if (($file['size'] ?? 0) > (2 * 1024 * 1024)) {
        throw new RuntimeException('El avatar no puede exceder 2 MB.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('El archivo de avatar no es válido.');
    }

    $imageInfo = @getimagesize($tmpPath);
    if ($imageInfo === false) {
        throw new RuntimeException('El avatar debe ser una imagen válida.');
    }

    $mimeType = $imageInfo['mime'] ?? '';
    $extension = normalize_upload_extension($mimeType);
    if ($extension === null) {
        throw new RuntimeException('Formato de avatar no permitido. Usa JPG, PNG, GIF o WEBP.');
    }

    $uploadDir = avatar_upload_dir();
    if (is_dir($uploadDir)) {
        if (!is_writable($uploadDir)) {
            throw new RuntimeException('El directorio de avatares existe pero no tiene permisos de escritura. Ajusta permisos antes de subir imágenes.');
        }
    } else {
        if (!mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new RuntimeException('No se pudo preparar el directorio de avatares en el servidor.');
        }
        if (!is_writable($uploadDir)) {
            throw new RuntimeException('El directorio de avatares fue creado pero no quedó escribible. Revisa permisos del servidor.');
        }
    }

    $filename = sprintf('avatar_%s_%s.%s', date('YmdHis'), bin2hex(random_bytes(6)), $extension);
    $destination = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $destination)) {
        throw new RuntimeException('No se pudo guardar el avatar en el servidor.');
    }

    return avatar_public_path($filename);
}

function build_edit_form(array $dbUser): array
{
    return [
        'id' => (int)($dbUser['id'] ?? 0),
        'full_name' => (string)($dbUser['full_name'] ?? ''),
        'email' => (string)($dbUser['email'] ?? ''),
        'username' => (string)($dbUser['username'] ?? ''),
        'avatar_path' => (string)($dbUser['avatar_path'] ?? ''),
        'role_name' => (string)($dbUser['role_name'] ?? 'viewer'),
        'is_active' => (int)($dbUser['is_active'] ?? 1),
    ];
}

function count_active_admins(PDO $pdo, ?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM users WHERE role_name = :role_name AND is_active = 1 AND (email LIKE :domain1 OR email LIKE :domain2)';
    $params = ['role_name' => 'admin', 'domain1' => '%@insightlatam.com', 'domain2' => '%@jetstereo.com'];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function count_admins(PDO $pdo, ?int $excludeId = null): int
{
    $sql = 'SELECT COUNT(*) FROM users WHERE role_name = :role_name AND (email LIKE :domain1 OR email LIKE :domain2)';
    $params = ['role_name' => 'admin', 'domain1' => '%@insightlatam.com', 'domain2' => '%@jetstereo.com'];
    if ($excludeId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $shouldOpenCreateModal = true;
        $createForm['full_name'] = trim($_POST['full_name'] ?? '');
        $createForm['email'] = trim($_POST['email'] ?? '');
        $createForm['username'] = trim($_POST['username'] ?? '');
        $createForm['role_name'] = trim($_POST['role_name'] ?? 'viewer');
        $createForm['is_active'] = ($_POST['is_active'] ?? '1') === '1' ? '1' : '0';
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');

        if ($createForm['full_name'] === '') $errors[] = 'El nombre es obligatorio.';
        if ($createForm['email'] === '' || !filter_var($createForm['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Ingresa un correo válido.';
        if (!str_ends_with($createForm['email'], '@insightlatam.com') && !str_ends_with($createForm['email'], '@jetstereo.com')) $errors[] = 'El correo debe terminar en @insightlatam.com o @jetstereo.com.';
        if ($createForm['username'] === '' || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $createForm['username'])) $errors[] = 'El username debe tener entre 3 y 80 caracteres y solo usar letras, números, punto, guion o guion bajo.';
        if (!in_array($createForm['role_name'], $availableRoles, true)) $errors[] = 'Selecciona un rol válido.';
        if (strlen($password) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($password !== $passwordConfirmation) $errors[] = 'La confirmación de contraseña no coincide.';

        if (!$errors) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email OR username = :username');
            $stmt->execute(['email' => $createForm['email'], 'username' => $createForm['username']]);
            if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Ya existe un usuario con ese correo o username.';
        }

        $avatarPath = null;
        if (!$errors) {
            try {
                $avatarPath = handle_avatar_upload($_FILES['avatar'] ?? []);
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, username, password_hash, avatar_path, role_name, is_active) VALUES (:full_name, :email, :username, :password_hash, :avatar_path, :role_name, :is_active)');
            $stmt->execute([
                'full_name' => $createForm['full_name'],
                'email' => $createForm['email'],
                'username' => $createForm['username'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'avatar_path' => $avatarPath,
                'role_name' => $createForm['role_name'],
                'is_active' => (int)$createForm['is_active'],
            ]);
            flash_set('success', 'Usuario creado correctamente.');
            redirect('users.php');
        }
    }

    if (in_array($action, ['update', 'toggle_active', 'reset_password', 'delete'], true)) {
        $targetId = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser) {
            $errors[] = 'El usuario seleccionado ya no existe.';
        } else {
            $editForm = build_edit_form($targetUser);
        }

        if ($action === 'update' && $targetUser) {
            $shouldOpenEditModal = true;
            $editForm['full_name'] = trim($_POST['full_name'] ?? '');
            $editForm['email'] = trim($_POST['email'] ?? '');
            $editForm['username'] = trim($_POST['username'] ?? '');
            $editForm['role_name'] = trim($_POST['role_name'] ?? 'viewer');
            $editForm['is_active'] = ($_POST['is_active'] ?? '1') === '1' ? 1 : 0;
            $newPassword = (string)($_POST['password'] ?? '');
            $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');

            if ($editForm['full_name'] === '') $errors[] = 'El nombre es obligatorio.';
            if ($editForm['email'] === '' || !filter_var($editForm['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Ingresa un correo válido.';
            if (!str_ends_with($editForm['email'], '@insightlatam.com') && !str_ends_with($editForm['email'], '@jetstereo.com')) $errors[] = 'El correo debe terminar en @insightlatam.com o @jetstereo.com.';
            if ($editForm['username'] === '' || !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $editForm['username'])) $errors[] = 'El username debe tener entre 3 y 80 caracteres y solo usar letras, números, punto, guion o guion bajo.';
            if (!in_array($editForm['role_name'], $availableRoles, true)) $errors[] = 'Selecciona un rol válido.';
            if ($newPassword !== '' && strlen($newPassword) < 8) $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
            if ($newPassword !== $passwordConfirmation) $errors[] = 'La confirmación de contraseña no coincide.';
            if ((int)$targetUser['id'] === (int)$user['id'] && $editForm['is_active'] !== 1) $errors[] = 'No puedes desactivar tu propio usuario desde esta sesión.';
            if ((int)$targetUser['id'] === (int)$user['id'] && $editForm['role_name'] !== 'admin') $errors[] = 'No puedes quitarte a ti mismo el rol de administrador.';
            if (($targetUser['role_name'] ?? '') === 'admin' && $editForm['role_name'] !== 'admin' && count_admins($pdo, (int)$targetUser['id']) === 0) $errors[] = 'Debe existir al menos un administrador en el sistema.';
            if (($targetUser['role_name'] ?? '') === 'admin' && (int)$targetUser['is_active'] === 1 && $editForm['is_active'] !== 1 && count_active_admins($pdo, (int)$targetUser['id']) === 0) $errors[] = 'Debe existir al menos un administrador activo en el sistema.';

            if (!$errors) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE (email = :email OR username = :username) AND id <> :id');
                $stmt->execute(['email' => $editForm['email'], 'username' => $editForm['username'], 'id' => $targetId]);
                if ((int)$stmt->fetchColumn() > 0) $errors[] = 'Ya existe otro usuario con ese correo o username.';
            }

            if (!$errors) {
                try {
                    $newAvatar = handle_avatar_upload($_FILES['avatar'] ?? []);
                    if ($newAvatar !== null) {
                        $editForm['avatar_path'] = $newAvatar;
                    }
                } catch (RuntimeException $exception) {
                    $errors[] = $exception->getMessage();
                }
            }

            if (!$errors) {
                $sql = 'UPDATE users SET full_name=:full_name, email=:email, username=:username, avatar_path=:avatar_path, role_name=:role_name, is_active=:is_active';
                $params = [
                    'full_name' => $editForm['full_name'],
                    'email' => $editForm['email'],
                    'username' => $editForm['username'],
                    'avatar_path' => $editForm['avatar_path'],
                    'role_name' => $editForm['role_name'],
                    'is_active' => (int)$editForm['is_active'],
                    'id' => $targetId,
                ];
                if ($newPassword !== '') {
                    $sql .= ', password_hash=:password_hash';
                    $params['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                $sql .= ' WHERE id=:id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                flash_set('success', 'Usuario actualizado correctamente.');
                redirect('users.php');
            }
        }

        if ($action === 'toggle_active' && $targetUser) {
            $nextState = ((int)$targetUser['is_active'] === 1) ? 0 : 1;
            if ((int)$targetUser['id'] === (int)$user['id'] && $nextState === 0) {
                flash_set('error', 'No puedes desactivar tu propio usuario mientras estás autenticado.');
                redirect('users.php');
            }
            if (($targetUser['role_name'] ?? '') === 'admin' && (int)$targetUser['is_active'] === 1 && $nextState === 0 && count_active_admins($pdo, (int)$targetUser['id']) === 0) {
                flash_set('error', 'No se puede desactivar al último administrador activo.');
                redirect('users.php');
            }

            $stmt = $pdo->prepare('UPDATE users SET is_active = :is_active WHERE id = :id');
            $stmt->execute(['is_active' => $nextState, 'id' => $targetId]);
            flash_set('success', $nextState === 1 ? 'Usuario activado correctamente.' : 'Usuario desactivado correctamente.');
            redirect('users.php');
        }

        if ($action === 'reset_password' && $targetUser) {
            $generatedPassword = substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(12))), 0, 12);
            if (strlen($generatedPassword) < 10) {
                $generatedPassword .= 'A9x';
            }
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
            $stmt->execute([
                'password_hash' => password_hash($generatedPassword, PASSWORD_DEFAULT),
                'id' => $targetId,
            ]);
            flash_set('success', 'Contraseña reseteada para ' . $targetUser['username'] . '. Temporal: ' . $generatedPassword);
            redirect('users.php');
        }

        if ($action === 'delete' && $targetUser) {
            if ((int)$targetUser['id'] === (int)$user['id']) {
                flash_set('error', 'No puedes eliminar tu propio usuario desde esta sesión.');
                redirect('users.php');
            }
            if (($targetUser['role_name'] ?? '') === 'admin' && count_admins($pdo, (int)$targetUser['id']) === 0) {
                flash_set('error', 'No se puede eliminar al último administrador del sistema.');
                redirect('users.php');
            }
            if (($targetUser['role_name'] ?? '') === 'admin' && (int)$targetUser['is_active'] === 1 && count_active_admins($pdo, (int)$targetUser['id']) === 0) {
                flash_set('error', 'No se puede eliminar al último administrador activo.');
                redirect('users.php');
            }

            $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $targetId]);
            flash_set('success', 'Usuario eliminado correctamente.');
            redirect('users.php');
        }
    }
}

$stmt = $pdo->prepare('SELECT id, full_name, email, username, avatar_path, role_name, is_active, created_at FROM users WHERE email LIKE ? OR email LIKE ? ORDER BY created_at DESC, id DESC');
$stmt->execute(['%@insightlatam.com', '%@jetstereo.com']);
$users = $stmt->fetchAll();
$totalUsers = count($users);
$activeUsers = count(array_filter($users, static fn(array $listedUser): bool => (int)$listedUser['is_active'] === 1));
$adminUsers = count(array_filter($users, static fn(array $listedUser): bool => ($listedUser['role_name'] ?? '') === 'admin'));

require_once __DIR__ . '/includes/header.php';
?>
<section class="page-section users-admin-page">
    <div class="users-hero card">
        <div>
            <span class="users-kicker">Módulo administrativo</span>
            <h1 class="users-title">Administración de usuarios</h1>
            <p class="users-subtitle">Centraliza accesos del portal desde una vista limpia, enfocada en el listado y acciones seguras mediante modales.</p>
        </div>
        <div class="users-hero-actions">
            <a class="btn-secondary btn-home-users" href="index.php">Inicio</a>
            <div class="users-hero-badge">Solo administradores</div>
            <button class="btn-primary btn-create-user" type="button" data-modal-open="user-create-modal">Crear nuevo usuario</button>
        </div>
    </div>

    <?php if ($successMessage): ?><div class="alert alert-success"><?php echo e($successMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div class="alert alert-error"><?php echo e($errorMessage); ?></div><?php endif; ?>
    <?php if ($errors): ?><div class="alert alert-error"><?php echo $shouldOpenEditModal ? '<strong>Revisa la edición:</strong>' : '<strong>Revisa el formulario:</strong>'; ?><ul class="error-list"><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <div class="users-summary-grid">
        <article class="users-summary-card card">
            <span class="users-summary-label">Total</span>
            <strong><?php echo e((string)$totalUsers); ?></strong>
            <p>Usuarios registrados.</p>
        </article>
        <article class="users-summary-card card">
            <span class="users-summary-label">Activos</span>
            <strong><?php echo e((string)$activeUsers); ?></strong>
            <p>Con acceso habilitado.</p>
        </article>
        <article class="users-summary-card card">
            <span class="users-summary-label">Admins</span>
            <strong><?php echo e((string)$adminUsers); ?></strong>
            <p>Perfiles con control administrativo.</p>
        </article>
    </div>

    <section class="users-panel card users-table-panel users-table-panel-full">
        <div class="panel-head panel-head-split">
            <div>
                <span class="panel-mini">Directorio</span>
                <h2>Listado de usuarios</h2>
                <p><?php echo count($users); ?> usuario(s) registrados en el portal.</p>
            </div>
            <button class="btn-secondary users-inline-create-btn" type="button" data-modal-open="user-create-modal">Nuevo usuario</button>
        </div>
        <div class="users-table-wrap">
            <table class="users-pretty-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $listedUser): ?>
                        <?php
                        $isSelf = (int)$listedUser['id'] === (int)$user['id'];
                        $toggleLabel = (int)$listedUser['is_active'] === 1 ? 'Desactivar' : 'Activar';
                        $toggleTitle = $toggleLabel . ' usuario';
                        $toggleDescription = 'Confirma si deseas ' . strtolower($toggleLabel) . ' a ' . ($listedUser['full_name'] ?: $listedUser['username']) . '.';
                        $toggleNote = (int)$listedUser['is_active'] === 1
                            ? 'El usuario perderá acceso al portal hasta volver a activarlo.'
                            : 'El usuario recuperará acceso al portal inmediatamente.';
                        $resetDescription = 'Se generará una nueva contraseña temporal para ' . ($listedUser['full_name'] ?: $listedUser['username']) . '.';
                        $deleteDescription = 'Vas a eliminar definitivamente a ' . ($listedUser['full_name'] ?: $listedUser['username']) . '.';
                        ?>
                        <tr>
                            <td>
                                <div class="pretty-user-row">
                                    <?php if (!empty($listedUser['avatar_path'])): ?><img class="pretty-avatar" src="<?php echo e($listedUser['avatar_path']); ?>" alt="Avatar de <?php echo e($listedUser['full_name']); ?>"><?php else: ?><div class="pretty-avatar placeholder"><?php echo e(strtoupper(substr($listedUser['full_name'], 0, 1))); ?></div><?php endif; ?>
                                    <div>
                                        <strong><?php echo e($listedUser['full_name']); ?></strong>
                                        <div class="pretty-muted"><?php echo e($listedUser['username']); ?><?php if ($isSelf): ?> · Tú<?php endif; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo e($listedUser['email']); ?></td>
                            <td><span class="pretty-badge pretty-role"><?php echo e($listedUser['role_name']); ?></span></td>
                            <td><span class="pretty-badge <?php echo (int)$listedUser['is_active'] === 1 ? 'pretty-ok' : 'pretty-off'; ?>"><?php echo (int)$listedUser['is_active'] === 1 ? 'Activo' : 'Inactivo'; ?></span></td>
                            <td><?php echo e(date('Y-m-d H:i', strtotime($listedUser['created_at']))); ?></td>
                            <td>
                                <div class="table-actions-stack">
                                    <button
                                        class="table-action-link table-action-button js-open-user-modal"
                                        type="button"
                                        data-modal-open="user-edit-modal"
                                        data-user-id="<?php echo e($listedUser['id']); ?>"
                                        data-full-name="<?php echo e($listedUser['full_name']); ?>"
                                        data-email="<?php echo e($listedUser['email']); ?>"
                                        data-username="<?php echo e($listedUser['username']); ?>"
                                        data-role-name="<?php echo e($listedUser['role_name']); ?>"
                                        data-is-active="<?php echo e((string)(int)$listedUser['is_active']); ?>"
                                        data-avatar-path="<?php echo e($listedUser['avatar_path'] ?? ''); ?>"
                                    >Editar</button>

                                    <button
                                        class="table-action-link action-warning js-open-action-modal"
                                        type="button"
                                        data-modal-open="user-reset-modal"
                                        data-action-user-id="<?php echo e($listedUser['id']); ?>"
                                        data-action-username="<?php echo e($listedUser['username']); ?>"
                                        data-action-name="<?php echo e($listedUser['full_name']); ?>"
                                        data-action-title="Reset pass"
                                        data-action-description="<?php echo e($resetDescription); ?>"
                                        data-action-note="La contraseña actual dejará de funcionar de inmediato."
                                        data-action-submit-label="Generar nueva contraseña"
                                        data-action-submit-class="action-warning"
                                    >Reset pass</button>

                                    <button
                                        class="table-action-link <?php echo (int)$listedUser['is_active'] === 1 ? 'action-muted' : 'action-success'; ?> js-open-action-modal"
                                        type="button"
                                        data-modal-open="user-toggle-modal"
                                        data-action-user-id="<?php echo e($listedUser['id']); ?>"
                                        data-action-username="<?php echo e($listedUser['username']); ?>"
                                        data-action-name="<?php echo e($listedUser['full_name']); ?>"
                                        data-action-title="<?php echo e($toggleTitle); ?>"
                                        data-action-description="<?php echo e($toggleDescription); ?>"
                                        data-action-note="<?php echo e($toggleNote); ?>"
                                        data-action-submit-label="<?php echo e($toggleLabel); ?>"
                                        data-action-submit-class="<?php echo (int)$listedUser['is_active'] === 1 ? 'action-muted' : 'action-success'; ?>"
                                    ><?php echo e($toggleLabel); ?></button>

                                    <button
                                        class="table-action-link action-danger js-open-action-modal"
                                        type="button"
                                        data-modal-open="user-delete-modal"
                                        data-action-user-id="<?php echo e($listedUser['id']); ?>"
                                        data-action-username="<?php echo e($listedUser['username']); ?>"
                                        data-action-name="<?php echo e($listedUser['full_name']); ?>"
                                        data-action-title="Eliminar usuario"
                                        data-action-description="<?php echo e($deleteDescription); ?>"
                                        data-action-note="Esta acción no se puede deshacer."
                                        data-action-submit-label="Eliminar definitivamente"
                                        data-action-submit-class="action-danger"
                                    >Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="users-modal <?php echo $shouldOpenCreateModal ? 'is-visible' : ''; ?>" id="user-create-modal" data-modal-root aria-hidden="<?php echo $shouldOpenCreateModal ? 'false' : 'true'; ?>">
        <div class="users-modal-backdrop" data-modal-close></div>
        <div class="users-modal-dialog card" role="dialog" aria-modal="true" aria-labelledby="users-create-modal-title">
            <div class="panel-head panel-head-split users-modal-head">
                <div>
                    <span class="panel-mini">Nuevo acceso</span>
                    <h2 id="users-create-modal-title">Crear nuevo usuario</h2>
                    <p>Registra un nuevo perfil con credenciales, rol y foto de perfil.</p>
                </div>
                <button class="modal-close-btn" type="button" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="post" enctype="multipart/form-data" class="pretty-form" id="user-create-form">
                <input type="hidden" name="action" value="create">
                <div class="pretty-grid">
                    <div class="pretty-field span-2"><label>Nombre completo</label><input name="full_name" type="text" value="<?php echo e($createForm['full_name']); ?>" placeholder="Ej. Ariel Martínez" required></div>
                    <div class="pretty-field"><label>Correo electrónico</label><input name="email" type="email" value="<?php echo e($createForm['email']); ?>" placeholder="usuario@empresa.com" required></div>
                    <div class="pretty-field"><label>Username</label><input name="username" type="text" value="<?php echo e($createForm['username']); ?>" placeholder="usuario" required></div>
                    <div class="pretty-field"><label>Rol</label><select name="role_name"><?php foreach ($availableRoles as $role): ?><option value="<?php echo e($role); ?>" <?php echo $createForm['role_name'] === $role ? 'selected' : ''; ?>><?php echo e($role); ?></option><?php endforeach; ?></select></div>
                    <div class="pretty-field"><label>Estado</label><select name="is_active"><option value="1" <?php echo $createForm['is_active'] === '1' ? 'selected' : ''; ?>>Activo</option><option value="0" <?php echo $createForm['is_active'] === '0' ? 'selected' : ''; ?>>Inactivo</option></select></div>
                    <div class="pretty-field"><label>Contraseña</label><input name="password" type="password" placeholder="Mínimo 8 caracteres" required></div>
                    <div class="pretty-field"><label>Confirmar contraseña</label><input name="password_confirmation" type="password" placeholder="Repite la contraseña" required></div>
                    <div class="pretty-field span-2 upload-card">
                        <label>Foto de perfil</label>
                        <div class="avatar-upload-layout">
                            <div class="avatar-live-card">
                                <div class="edit-preview-avatar placeholder avatar-live-preview" data-avatar-preview data-placeholder="N">N</div>
                                <div class="avatar-live-meta">
                                    <strong>Previsualización</strong>
                                    <span>Se actualiza al elegir una imagen.</span>
                                </div>
                            </div>
                            <div class="avatar-live-input">
                                <input name="avatar" type="file" accept="image/png,image/jpeg,image/gif,image/webp" data-avatar-input data-preview-target="#user-create-form [data-avatar-preview]" data-placeholder-source="#user-create-form input[name='full_name']">
                                <small>Opcional. Formatos permitidos: JPG, PNG, GIF o WEBP. Máximo 2 MB.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pretty-actions users-modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Cancelar</button>
                    <button class="btn-primary" type="submit">Crear usuario</button>
                </div>
            </form>
        </div>
    </div>

    <div class="users-modal <?php echo $shouldOpenEditModal ? 'is-visible' : ''; ?>" id="user-edit-modal" data-modal-root aria-hidden="<?php echo $shouldOpenEditModal ? 'false' : 'true'; ?>">
        <div class="users-modal-backdrop" data-modal-close></div>
        <div class="users-modal-dialog card" role="dialog" aria-modal="true" aria-labelledby="users-modal-title">
            <div class="panel-head panel-head-split users-modal-head">
                <div>
                    <span class="panel-mini">Edición</span>
                    <h2 id="users-modal-title">Editar usuario</h2>
                    <p>Actualiza perfil, permisos, avatar o cambia la contraseña si hace falta.</p>
                </div>
                <button class="modal-close-btn" type="button" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="post" enctype="multipart/form-data" class="pretty-form" id="user-edit-form">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" value="<?php echo e((string)$editForm['id']); ?>" data-edit-field="user_id">
                <div class="edit-preview-box">
                    <?php if (!empty($editForm['avatar_path'])): ?>
                        <img class="edit-preview-avatar avatar-live-preview" src="<?php echo e($editForm['avatar_path']); ?>" alt="Avatar actual" data-edit-avatar-preview>
                    <?php else: ?>
                        <div class="edit-preview-avatar placeholder avatar-live-preview" data-edit-avatar-preview data-placeholder="<?php echo e(strtoupper(substr($editForm['full_name'] ?: 'U', 0, 1))); ?>"><?php echo e(strtoupper(substr($editForm['full_name'] ?: 'U', 0, 1))); ?></div>
                    <?php endif; ?>
                    <div class="edit-preview-meta"><strong data-edit-name-preview><?php echo e($editForm['full_name'] ?: 'Usuario'); ?></strong><span data-edit-meta-preview><?php echo e(($editForm['username'] ?: 'usuario') . ' · ' . ($editForm['role_name'] ?: 'viewer')); ?></span></div>
                </div>
                <div class="pretty-grid">
                    <div class="pretty-field span-2"><label>Nombre completo</label><input name="full_name" type="text" value="<?php echo e($editForm['full_name']); ?>" required data-edit-field="full_name"></div>
                    <div class="pretty-field"><label>Correo electrónico</label><input name="email" type="email" value="<?php echo e($editForm['email']); ?>" required data-edit-field="email"></div>
                    <div class="pretty-field"><label>Username</label><input name="username" type="text" value="<?php echo e($editForm['username']); ?>" required data-edit-field="username"></div>
                    <div class="pretty-field"><label>Rol</label><select name="role_name" data-edit-field="role_name"><?php foreach ($availableRoles as $role): ?><option value="<?php echo e($role); ?>" <?php echo $editForm['role_name'] === $role ? 'selected' : ''; ?>><?php echo e($role); ?></option><?php endforeach; ?></select></div>
                    <div class="pretty-field"><label>Estado</label><select name="is_active" data-edit-field="is_active"><option value="1" <?php echo (int)$editForm['is_active'] === 1 ? 'selected' : ''; ?>>Activo</option><option value="0" <?php echo (int)$editForm['is_active'] === 0 ? 'selected' : ''; ?>>Inactivo</option></select></div>
                    <div class="pretty-field"><label>Nueva contraseña</label><input name="password" type="password" placeholder="Déjala vacía para no cambiarla"></div>
                    <div class="pretty-field"><label>Confirmar nueva contraseña</label><input name="password_confirmation" type="password" placeholder="Repite la nueva contraseña"></div>
                    <div class="pretty-field span-2 upload-card">
                        <label>Cambiar foto de perfil</label>
                        <div class="avatar-upload-layout">
                            <div class="avatar-live-card compact">
                                <div class="avatar-live-meta">
                                    <strong>Previsualización</strong>
                                    <span>Se actualiza al elegir una imagen.</span>
                                </div>
                            </div>
                            <div class="avatar-live-input">
                                <input name="avatar" type="file" accept="image/png,image/jpeg,image/gif,image/webp" data-avatar-input data-preview-target="#user-edit-form [data-edit-avatar-preview]" data-placeholder-source="#user-edit-form input[name='full_name']">
                                <small>Opcional. Solo si deseas reemplazar el avatar actual.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="pretty-actions users-modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Cancelar</button>
                    <button class="btn-primary" type="submit">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div class="users-modal" id="user-reset-modal" data-modal-root aria-hidden="true">
        <div class="users-modal-backdrop" data-modal-close></div>
        <div class="users-modal-dialog users-confirm-modal card" role="dialog" aria-modal="true" aria-labelledby="user-reset-title">
            <div class="panel-head panel-head-split users-modal-head">
                <div>
                    <span class="panel-mini">Confirmación</span>
                    <h2 id="user-reset-title" data-action-modal-title>Reset pass</h2>
                    <p data-action-modal-description>Se generará una nueva contraseña temporal.</p>
                </div>
                <button class="modal-close-btn" type="button" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="post" class="pretty-form users-confirm-form">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" value="" data-action-user-id-field>
                <div class="confirm-user-card">
                    <strong data-action-user-name>Usuario</strong>
                    <span data-action-user-meta>@usuario</span>
                </div>
                <div class="users-confirm-note" data-action-modal-note>La contraseña actual dejará de funcionar de inmediato.</div>
                <div class="pretty-actions users-modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Cancelar</button>
                    <button class="table-action-link action-warning users-confirm-submit" type="submit" data-action-submit>Generar nueva contraseña</button>
                </div>
            </form>
        </div>
    </div>

    <div class="users-modal" id="user-toggle-modal" data-modal-root aria-hidden="true">
        <div class="users-modal-backdrop" data-modal-close></div>
        <div class="users-modal-dialog users-confirm-modal card" role="dialog" aria-modal="true" aria-labelledby="user-toggle-title">
            <div class="panel-head panel-head-split users-modal-head">
                <div>
                    <span class="panel-mini">Estado</span>
                    <h2 id="user-toggle-title" data-action-modal-title>Actualizar estado</h2>
                    <p data-action-modal-description>Confirma el cambio de estado del usuario.</p>
                </div>
                <button class="modal-close-btn" type="button" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="post" class="pretty-form users-confirm-form">
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" value="" data-action-user-id-field>
                <div class="confirm-user-card">
                    <strong data-action-user-name>Usuario</strong>
                    <span data-action-user-meta>@usuario</span>
                </div>
                <div class="users-confirm-note" data-action-modal-note>Este cambio se aplicará de inmediato.</div>
                <div class="pretty-actions users-modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Cancelar</button>
                    <button class="table-action-link users-confirm-submit" type="submit" data-action-submit>Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="users-modal" id="user-delete-modal" data-modal-root aria-hidden="true">
        <div class="users-modal-backdrop" data-modal-close></div>
        <div class="users-modal-dialog users-confirm-modal card" role="dialog" aria-modal="true" aria-labelledby="user-delete-title">
            <div class="panel-head panel-head-split users-modal-head">
                <div>
                    <span class="panel-mini">Peligro</span>
                    <h2 id="user-delete-title" data-action-modal-title>Eliminar usuario</h2>
                    <p data-action-modal-description>Vas a eliminar definitivamente el registro.</p>
                </div>
                <button class="modal-close-btn" type="button" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="post" class="pretty-form users-confirm-form">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" value="" data-action-user-id-field>
                <div class="confirm-user-card confirm-user-card-danger">
                    <strong data-action-user-name>Usuario</strong>
                    <span data-action-user-meta>@usuario</span>
                </div>
                <div class="users-confirm-note" data-action-modal-note>Esta acción no se puede deshacer.</div>
                <div class="pretty-actions users-modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Cancelar</button>
                    <button class="table-action-link action-danger users-confirm-submit" type="submit" data-action-submit>Eliminar definitivamente</button>
                </div>
            </form>
        </div>
    </div>
</section>
<script>
window.usersEditModalAutoOpen = <?php echo $shouldOpenEditModal ? 'true' : 'false'; ?>;
window.usersCreateModalAutoOpen = <?php echo $shouldOpenCreateModal ? 'true' : 'false'; ?>;
</script>
<?php require_once __DIR__ . '/includes/footer.php';
