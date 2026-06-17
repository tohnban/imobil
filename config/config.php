<?php
#Arquivos directorios raizes
$PastaInterna = "";

// Determine project root reliably (hosting often points DOCUMENT_ROOT to /public).
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    $projectRoot = __DIR__ . '/..';
}
define('DIRREQ', rtrim(str_replace('\\', '/', $projectRoot), '/') . '/');

require_once DIRREQ.'src/classes/ClassEnv.php';

# Load environment variables
$envFile = DIRREQ.'.env';
if (file_exists($envFile)) {
    Src\classes\ClassEnv::load($envFile);
} else {
    // In production we must not silently fall back to example values.
    $fallbackEnv = DIRREQ.'.env.example';
    $fallbackLoaded = false;
    if (file_exists($fallbackEnv)) {
        try {
            Src\classes\ClassEnv::load($fallbackEnv);
            $fallbackLoaded = true;
        } catch (\Throwable $_) {
            $fallbackLoaded = false;
        }
    }

    $envCandidate = Src\classes\ClassEnv::get('APP_ENV', 'development');
    if (strtolower((string) $envCandidate) === 'production') {
        http_response_code(500);
        echo 'Missing .env file in production.';
        exit;
    }

    if (!$fallbackLoaded) {
        http_response_code(500);
        echo 'Environment file not found.';
        exit;
    }
}

# Application settings
define('APP_ENV', Src\classes\ClassEnv::get('APP_ENV', 'development'));

$appDebugRaw = Src\classes\ClassEnv::get('APP_DEBUG', null);
if ($appDebugRaw === null || $appDebugRaw === '') {
    define('APP_DEBUG', strtolower((string) APP_ENV) !== 'production');
} else {
    define('APP_DEBUG', filter_var($appDebugRaw, FILTER_VALIDATE_BOOLEAN));
}

define('SESSION_LIFETIME', Src\classes\ClassEnv::get('SESSION_LIFETIME', 1800));

// Prefer a fixed base URL from env to avoid Host header injection.
$appUrl = trim((string) Src\classes\ClassEnv::get('APP_URL', ''));
if ($appUrl !== '') {
    $appUrl = rtrim($appUrl, '/') . '/';
    define('DIRPAGE', $appUrl . ($PastaInterna !== '' ? trim($PastaInterna, '/') . '/' : ''));
} else {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    // Basic hardening: strip invalid chars to avoid header injection / weird hosts.
    $host = preg_replace('/[^A-Za-z0-9.\\-:\\[\\]]/', '', $host);
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $scheme = $isHttps ? 'https' : 'http';
    define('DIRPAGE', $scheme . "://{$host}/{$PastaInterna}");
}

define('DIRIMG',DIRPAGE."public/img/");
define('DIRCSS',DIRPAGE."public/css/");
define('DIRJS',DIRPAGE."public/js/");
define('DIRADMIN',DIRPAGE."public/admin/");
define('DIRAUDIO',DIRPAGE."public/audio/");
define('DIRDESIGN',DIRPAGE."public/design/");
define('DIRFONTES',DIRPAGE."public/fontes/");
define('DIRVIDEO',DIRPAGE."public/video/");

#Acesso ao Banco de dados
define('HOST', Src\classes\ClassEnv::get('DB_HOST', 'localhost'));
define('DB', Src\classes\ClassEnv::get('DB_NAME', 'imobil_db'));
define('USER', Src\classes\ClassEnv::get('DB_USER', 'root'));
define('PASS', Src\classes\ClassEnv::get('DB_PASS', ''));
define('DB_PERSISTENT', filter_var(Src\classes\ClassEnv::get('DB_PERSISTENT', false), FILTER_VALIDATE_BOOLEAN));

# Email settings
define('EMAIL_ENABLED', Src\classes\ClassEnv::get('EMAIL_ENABLED', false));
define('MAIL_FROM_ADDRESS', Src\classes\ClassEnv::get('MAIL_FROM_ADDRESS', 'no-reply@imobil.local'));
define('MAIL_FROM_NAME', Src\classes\ClassEnv::get('MAIL_FROM_NAME', 'Imobil'));
define('SMTP_HOST', Src\classes\ClassEnv::get('SMTP_HOST', 'localhost'));
define('SMTP_PORT', Src\classes\ClassEnv::get('SMTP_PORT', 1025));
define('SMTP_USER', Src\classes\ClassEnv::get('SMTP_USER', ''));
define('SMTP_PASS', Src\classes\ClassEnv::get('SMTP_PASS', ''));
define('SMTP_SECURE', Src\classes\ClassEnv::get('SMTP_SECURE', ''));

define('LOG_CHANNEL', Src\classes\ClassEnv::get('LOG_CHANNEL', 'file'));
define('LOG_LEVEL', Src\classes\ClassEnv::get('LOG_LEVEL', 'info'));

if (strtolower((string) APP_ENV) === 'production' && trim((string) $appUrl) === '') {
    http_response_code(500);
    echo 'APP_URL must be set in production.';
    exit;
}

if (APP_DEBUG) {
    error_reporting(E_ALL);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}
ini_set('display_errors', '0');
ini_set('log_errors', '1');



