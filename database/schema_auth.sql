CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    role_name VARCHAR(50) NOT NULL DEFAULT 'viewer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    password_reset_at TIMESTAMP NULL DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS login_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    login_value VARCHAR(150) NULL,
    ip_address VARCHAR(64) NULL,
    user_agent TEXT NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Usuario administrador inicial.
-- 1) Genera el hash de tu contraseña:  php -r "echo password_hash('TU_CONTRASEÑA', PASSWORD_DEFAULT);"
-- 2) Reemplaza <HASH_GENERADO> y descomenta el INSERT.
-- INSERT INTO users (full_name, email, username, password_hash, avatar_path, role_name, is_active)
-- VALUES ('Administrador', 'admin@example.com', 'admin', '<HASH_GENERADO>', NULL, 'admin', 1);
