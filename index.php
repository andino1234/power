<?php
/**
 * index.php – Página principal del dashboard Mystery Shopper
 *
 * @author Ariel Geovanny Andino Chinchilla
 * @link   https://github.com/andino1234
 */

require_once 'config.php';
require_once __DIR__ . '/app/helpers.php';

$user = require_auth();

// ── Cabeceras de seguridad HTTP ──────────────────────────────────────────────
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "style-src 'self' 'unsafe-inline'; " .
    "script-src 'self' 'unsafe-inline'; " .
    "frame-src " . CSP_FRAME_SRC . "; " .
    "img-src 'self' data:; " .
    "font-src 'self';"
);

// Ocultar errores PHP en producción
if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

require_once 'includes/header.php';
?>

    <section class="page-section">

        <div class="section-heading">
            <h1 class="page-title"><?php echo htmlspecialchars(PAGE_HEADING); ?></h1>
            <p class="page-subtitle">
                Datos recolectados entre Octubre 2026.
            </p>
        </div>

        <div class="divider"></div>

        <!-- Contenedor responsive del iframe Power BI -->
        <div class="dashboard-wrapper">
            <div class="dashboard-container">
                <iframe
                    title="<?php echo htmlspecialchars(POWERBI_TITLE); ?>"
                    src="<?php echo htmlspecialchars(POWERBI_URL); ?>"
                    frameborder="0"
                    allowfullscreen="true"
                    loading="lazy">
                    <p>Tu navegador no soporta iframes.
                        <a href="<?php echo htmlspecialchars(POWERBI_URL); ?>" target="_blank" rel="noopener noreferrer">
                            Ver el reporte directamente en Power BI.
                        </a>
                    </p>
                </iframe>
            </div>
            <p class="dashboard-note">
                Si el dashboard no carga, verifica tu conexión a internet o accede directamente al
                <a href="<?php echo htmlspecialchars(POWERBI_URL); ?>" target="_blank" rel="noopener noreferrer">reporte en Power BI Service</a>.
            </p>
        </div>

    </section>

<?php require_once 'includes/footer.php'; ?>
