<?php
/**
 * config/config.php
 */
require_once __DIR__ . '/../includes/security_headers.php';

// ---- Email (Gmail SMTP) ---------------------------------------------------
putenv('SMTP_USER=ibrahiman2468@gmail.com');
putenv('SMTP_PASS=ihozkguxvuigitff'); // Google Account → Security → App Passwords

// ---- SMS (TextBee) -----------------------------------------------------------
putenv('TEXTBEE_API_KEY=40b7cf91-3151-44b8-98ee-d73a87096429');
putenv('TEXTBEE_DEVICE_ID=6a6cd96b25b54ad14bef5b04');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'reception');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

if (file_exists(__DIR__ . '/secrets.php')) {
       require_once __DIR__ . '/secrets.php';
}

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('ATTACHMENT_DIR', UPLOAD_PATH . '/attachments');
define('SIGNATURE_DIR', UPLOAD_PATH . '/signatures');

/**
 * Auto-detect the base URL instead of hardcoding it. This works no matter
 * what the project folder is named, or how many subfolders deep it sits
 * under your document root (htdocs/reception-system, htdocs/rms, htdocs
 * itself, etc). It walks up from the current script until it finds the
 * "/public" segment and treats that as BASE_URL.
 */
function detect_base_url(): string
{
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $pos = strpos($scriptDir, '/public');
    if ($pos !== false) {
        return substr($scriptDir, 0, $pos + strlen('/public'));
    }
    // Fallback: assume current script's directory IS the public root.
    return rtrim($scriptDir, '/');
}

define('BASE_URL', detect_base_url());          // e.g. /reception-system/public  OR just ''
define('ASSET_URL', BASE_URL . '/assets');   // absolute, always correct

define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('SESSION_TIMEOUT_SECONDS', 900);

define('DISPLAY_ERRORS', 0);
ini_set('display_errors', DISPLAY_ERRORS);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax'); // CSRF defense-in-depth alongside the token system
    // ini_set('session.cookie_secure', 1); // uncomment once on HTTPS
    session_start();
}