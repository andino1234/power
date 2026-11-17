<?php

/**
 * config.example.php — copia este archivo como config.php y completa tus valores.
 * Archivo de configuración central.
 * Para ingresar aquí la URL del reporte y los textos.
 */

// ── Power BI ────────────────────────────────────────────────────────────────

define('POWERBI_URL',   'https://app.powerbi.com/view?r=TU_REPORTE_PUBLICADO');
define('POWERBI_TITLE', 'Dashboard Mystery Shopper');

// ── Textos de la página ─────────────────────────────────────────────────────
define('SITE_TITLE', 'Evaluación Mistery Shopper Octubre 2026');
define('PAGE_HEADING', 'MS Octubre 2026');

// ── Entorno: 'development' | 'production' ──────────────────────────────────
// Cambiar a 'production' antes de subir al servidor
define('APP_ENV', 'development');

// ── Configuración de autenticación / base de datos ─────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'powerbi');
define('DB_USER', 'cambia_este_usuario');
define('DB_PASS', 'cambia_esta_contrasena');
define('DB_CHARSET', 'utf8mb4');

// ── Seguridad ───────────────────────────────────────────────────────────────
// Dominios permitidos en Content-Security-Policy para los iframes de Power BI
define('CSP_FRAME_SRC', 'https://app.powerbi.com https://msit.powerbi.com');
