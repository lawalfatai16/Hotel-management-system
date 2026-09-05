<?php
/**
 * GMT Hotel and Events Centre — Application Configuration
 */

// ---- Environment -----------------------------------------------------
define('APP_ENV', 'local');            // local | production
define('APP_DEBUG', APP_ENV === 'local');

// ---- Database ----------------------------------------------------------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'gmt_hotel');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Paths ---------------------------------------------------------------
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_URL', 'assets');

// ---- Session / Security --------------------------------------------------
define('SESSION_TIMEOUT_MINUTES', 30);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---- Secure session bootstrap ---------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    if (APP_ENV === 'production') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// ---- Error handling ---------------------------------------------------
if (APP_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . '/storage/logs/app.log');

// ---- Timezone -----------------------------------------------------------
date_default_timezone_set('Africa/Lagos');
