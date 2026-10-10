<?php

/**
 * EduVers — Settings form (database, email, address).
 *
 * A small stand-alone page that lets a non-technical person fill in the connection
 * settings instead of editing the .env file by hand. It does NOT start Laravel, so it
 * works even before the database is reachable.
 *
 * How it is started (see handover\Setup-EduVers.bat):
 *     php -S 127.0.0.1:8765 handover\setup\index.php
 * with these environment variables:
 *     EDUVERS_SETUP_TOKEN   random value; every request must carry it (blocks other programs/sites)
 *     EDUVERS_SETUP_DONE    (optional) file to create once the settings are saved
 *     EDUVERS_SETUP_MODE    "install" or "settings" (only changes the wording)
 *
 * Command-line helper for the installer:
 *     php index.php export-db <my.cnf to write> <.cmd to write>
 * writes MySQL client credentials and "set DB_...=" lines taken from .env.
 *
 * Security: it only listens on 127.0.0.1, checks the token and the Host/Origin headers,
 * never prints saved passwords or keys, and keeps one backup (.env.backup) of the last .env.
 */

declare(strict_types=1);

namespace EduVersSetup;

use Dotenv\Dotenv;
use PDO;
use PDOException;
use Throwable;

const APP_ROOT = __DIR__.'/../..';

require APP_ROOT.'/vendor/autoload.php';

/* ====================================================================== */
/*  .env reading and writing                                               */
/* ====================================================================== */

function envPath(): string
{
    return APP_ROOT.DIRECTORY_SEPARATOR.'.env';
}

/** @return array<string, string> */
function readEnv(): array
{
    $path = envPath();
    if (! is_file($path)) {
        return [];
    }

    try {
        return array_map(fn ($v) => (string) $v, Dotenv::parse((string) file_get_contents($path)));
    } catch (Throwable) {
        return [];
    }
}

/** Write one value the way Laravel's .env reader will read it back unchanged. */
function encodeEnvValue(string $value): string
{
    if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/@+\-]+$/', $value)) {
        return $value;
    }
    if (! str_contains($value, "'")) {
        return "'".$value."'";           // single quotes: taken literally
    }

    return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
}

/**
 * Update keys in .env in place (comments and other lines are kept); new keys go to the end.
 *
 * @param  array<string, string>  $values
 */
function writeEnv(array $values): void
{
    $path     = envPath();
    $original = is_file($path) ? (string) file_get_contents($path) : '';
    $eol      = str_contains($original, "\r\n") ? "\r\n" : "\n";
    $lines    = $original === '' ? [] : preg_split('/\r\n|\n|\r/', rtrim($original, "\r\n"));
    $pending  = $values;

    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:export\s+)?([A-Za-z_][A-Za-z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $pending)) {
            $lines[$i] = $m[1].'='.encodeEnvValue($pending[$m[1]]);
            unset($pending[$m[1]]);
        }
    }

    if ($pending !== []) {
        $lines[] = '';
        $lines[] = '# Added by the EduVers settings form';
        foreach ($pending as $key => $value) {
            $lines[] = $key.'='.encodeEnvValue($value);
        }
    }

    if ($original !== '') {
        @copy($path, $path.'.backup');   // one backup of the previous settings
    }

    $tmp = $path.'.tmp';
    if (file_put_contents($tmp, implode($eol, $lines).$eol) === false || ! @rename($tmp, $path)) {
        @unlink($tmp);
        throw new \RuntimeException('The settings file (.env) could not be written. Is it open in another program?');
    }
}

/* ====================================================================== */
/*  Database helpers                                                       */
/* ====================================================================== */

/** A certificate bundle for databases that require SSL (TiDB Cloud, managed MySQL…). */
function findCaBundle(): ?string
{
    $candidates = [
        (string) ini_get('openssl.cafile'),
        (string) ini_get('curl.cainfo'),
        dirname(PHP_BINARY, 4).'/etc/ssl/cacert.pem',     // <laragon>\etc\ssl\cacert.pem
        'C:/laragon/etc/ssl/cacert.pem',
        '/etc/ssl/certs/ca-certificates.crt',
    ];

    foreach ($candidates as $file) {
        if ($file !== '' && is_file($file)) {
            return str_replace('\\', '/', $file);
        }
    }

    return null;
}

function isLocalHost(string $host): bool
{
    return in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);
}

/**
 * Try to connect with the given settings.
 *
 * @param  array{driver:string,host:string,port:int,database:string,username:string,password:string,ssl:bool}  $db
 * @return array{ok:bool,title:string,message:string}
 */
function testDatabase(array $db): array
{
    $driver = $db['driver'] === 'pgsql' ? 'pgsql' : 'mysql';
    $base   = $driver === 'pgsql'
        ? "pgsql:host={$db['host']};port={$db['port']}"
        : "mysql:host={$db['host']};port={$db['port']};charset=utf8mb4";

    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 6];
    if ($driver === 'mysql' && $db['ssl']) {
        $ca = findCaBundle();
        if ($ca === null) {
            return ['ok' => false, 'title' => 'Secure connection is not available', 'message' => 'No certificate file (cacert.pem) was found on this computer. Untick "secure connection", or ask your developer.'];
        }
        $sslKey = class_exists(\Pdo\Mysql::class) ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
        $options[$sslKey] = $ca;
    }

    // 1) Can we reach the server and sign in?
    try {
        $serverDsn = $driver === 'pgsql' ? $base.';dbname=postgres' : $base;
        $pdo       = new PDO($serverDsn, $db['username'], $db['password'], $options);
        $version   = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $pdo       = null;
    } catch (PDOException $e) {
        return ['ok' => false] + explainDatabaseError($e, $db, $driver);
    }

    $server = preg_match('/\d+(\.\d+)+[0-9A-Za-z.\-]*/', $version, $m) ? $m[0] : 'unknown';

    // 2) Does the database itself exist?
    try {
        new PDO($base.';dbname='.$db['database'], $db['username'], $db['password'], $options);

        return ['ok' => true, 'title' => 'Connected', 'message' => "The database \"{$db['database']}\" was found (server version {$server})."];
    } catch (PDOException $e) {
        $code    = (int) ($e->errorInfo[1] ?? $e->getCode());
        $missing = $driver === 'mysql' ? $code === 1049 : str_contains($e->getMessage(), 'does not exist');

        if ($missing) {
            return ['ok' => true, 'title' => 'Connected', 'message' => "Sign-in works (server version {$server}). The database \"{$db['database']}\" does not exist yet. The installer creates it for you."];
        }

        return ['ok' => false, 'title' => 'Signed in, but the database cannot be opened', 'message' => "The user \"{$db['username']}\" is not allowed to use the database \"{$db['database']}\". Check the database name, or ask for access."];
    }
}

/** @return array{title:string,message:string} */
function explainDatabaseError(PDOException $e, array $db, string $driver): array
{
    $code = (int) ($e->errorInfo[1] ?? $e->getCode());
    $text = $e->getMessage();

    if ($driver === 'mysql' && in_array($code, [1045, 1698], true) || str_contains($text, 'password authentication failed') || preg_match('/role ".*" does not exist/', $text)) {
        return ['title' => 'Wrong username or password', 'message' => 'The database server answered, but it did not accept this username and password.'];
    }
    if (stripos($text, 'ssl') !== false || stripos($text, 'insecure transport') !== false || stripos($text, 'certificate') !== false) {
        return ['title' => 'Secure connection problem', 'message' => $db['ssl']
            ? 'The secure (SSL) connection could not be set up. Check the server address, or untick "secure connection" if this is a database on this computer.'
            : 'This database only accepts secure connections. Tick "This database needs a secure (SSL) connection" and try again.'];
    }
    if (($driver === 'mysql' && in_array($code, [2002, 2003, 2005, 2006, 2013], true)) || preg_match('/refused|timed out|timeout|getaddrinfo|could not translate host|no such host|unreachable|did not properly respond/i', $text)) {
        return ['title' => 'Could not reach the database server', 'message' => isLocalHost($db['host'])
            ? 'Nothing answered on this computer at port '.$db['port'].'. Open Laragon, click "Start All", wait until MySQL shows as started, then test again.'
            : 'Nothing answered at '.$db['host'].' port '.$db['port'].'. Check the server address and port, and that this computer has internet access.'];
    }

    return ['title' => 'The connection failed', 'message' => 'The database server said: '.trim((string) preg_replace('/^SQLSTATE\[[^\]]*\]\s*(\[\d+\])?\s*/', '', $text))];
}

/* ====================================================================== */
/*  Command line: export-db                                                */
/* ====================================================================== */

if (PHP_SAPI === 'cli') {
    $argv = $_SERVER['argv'] ?? [];
    if (($argv[1] ?? '') !== 'export-db' || ! isset($argv[2], $argv[3])) {
        fwrite(STDERR, "Usage: php index.php export-db <my.cnf to write> <cmd file to write>\n");
        exit(2);
    }

    $env  = readEnv();
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $name = $env['DB_DATABASE'] ?? 'eduvers';
    $user = $env['DB_USERNAME'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? '';
    $ssl  = ($env['MYSQL_ATTR_SSL_CA'] ?? '') !== '';

    if (($env['DB_CONNECTION'] ?? 'mysql') !== 'mysql') {
        fwrite(STDERR, "The .env file does not use a MySQL database (DB_CONNECTION).\n");
        exit(3);
    }
    if (! preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $host) || ! preg_match('/^[0-9]{1,5}$/', $port) || ! preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
        fwrite(STDERR, "The database settings in .env contain characters the installer cannot use. Open the settings form and save again.\n");
        exit(4);
    }

    $quote = fn (string $v) => '"'.strtr($v, ['\\' => '\\\\', '"' => '\\"']).'"';
    $cnf   = "[client]\r\nhost={$host}\r\nport={$port}\r\nuser=".$quote($user)."\r\npassword=".$quote($pass)."\r\n".($ssl ? "ssl-mode=REQUIRED\r\n" : '');
    $cmd   = "set \"DB_HOST={$host}\"\r\nset \"DB_PORT={$port}\"\r\nset \"DB_DATABASE={$name}\"\r\nset \"DB_LOCAL=".(isLocalHost($host) ? '1' : '0')."\"\r\n";

    if (file_put_contents($argv[2], $cnf) === false || file_put_contents($argv[3], $cmd) === false) {
        fwrite(STDERR, "Could not write the temporary files.\n");
        exit(5);
    }
    exit(0);
}

/* ====================================================================== */
/*  Web: security checks                                                   */
/* ====================================================================== */

$token = (string) getenv('EDUVERS_SETUP_TOKEN');
$mode  = getenv('EDUVERS_SETUP_MODE') === 'install' ? 'install' : 'settings';
$path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$host  = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

// Only answer to this computer's own browser (also stops DNS-rebinding tricks)
if (! preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/', $host)) {
    http_response_code(403);
    exit('Forbidden');
}

if ($path === '/logo.jpg') {
    $logo = APP_ROOT.'/public/images/logo.jpg';
    if (is_file($logo)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: max-age=3600');
        readfile($logo);
    } else {
        http_response_code(404);
    }
    exit;
}

$given = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
if ($token === '' || ! hash_equals($token, $given)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>EduVers settings</title><body style="font-family:Segoe UI,Arial,sans-serif;max-width:34rem;margin:15vh auto;padding:0 1rem;color:#0F172A"><h1 style="font-family:Georgia,serif">This page has expired</h1><p>For safety, the settings form only opens from the <b>EduVers Settings</b> shortcut (or the installer). Close this tab and open it from there again.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    // Browsers send "null" in some privacy modes; the secret token above is the real check then.
    if ($origin !== '' && $origin !== 'null' && strtolower((string) parse_url($origin, PHP_URL_HOST)).':'.parse_url($origin, PHP_URL_PORT) !== $host) {
        http_response_code(403);
        exit('Forbidden');
    }
}

header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

/* ====================================================================== */
/*  Web: current values, input, validation                                 */
/* ====================================================================== */

$env = readEnv();

$savedMailMode = match (true) {
    ($env['MAIL_MAILER'] ?? '') === 'brevo'                                                       => 'brevo',
    ($env['MAIL_MAILER'] ?? '') === 'smtp' && strtolower($env['MAIL_HOST'] ?? '') === 'smtp.gmail.com' => 'gmail',
    ($env['MAIL_MAILER'] ?? '') === 'smtp'                                                        => 'smtp',
    default                                                                                       => 'off',
};

$saved = [
    'db_pass'    => ($env['DB_PASSWORD'] ?? '') !== '',
    'mail_pass'  => in_array($savedMailMode, ['gmail', 'smtp'], true) && ($env['MAIL_PASSWORD'] ?? '') !== '' && ($env['MAIL_PASSWORD'] ?? '') !== 'null',
    'brevo_key'  => ($env['BREVO_API_KEY'] ?? '') !== '',
];

$fromAddress = $env['MAIL_FROM_ADDRESS'] ?? '';
if ($fromAddress === 'hello@example.com') {
    $fromAddress = '';
}

// What the form shows first (saved settings), later overwritten by what was typed
$form = [
    'db_host'       => $env['DB_HOST'] ?? '127.0.0.1',
    'db_port'       => $env['DB_PORT'] ?? '3306',
    'db_name'       => $env['DB_DATABASE'] ?? 'eduvers',
    'db_user'       => $env['DB_USERNAME'] ?? 'root',
    'db_ssl'        => ($env['MYSQL_ATTR_SSL_CA'] ?? '') !== '' ? '1' : '',
    'db_nopass'     => '',
    'mail_mode'     => $savedMailMode,
    'gmail_address' => $savedMailMode === 'gmail' ? ($env['MAIL_USERNAME'] ?? '') : '',
    'brevo_sender'  => $savedMailMode === 'brevo' ? $fromAddress : '',
    'smtp_host'     => $savedMailMode === 'smtp' ? ($env['MAIL_HOST'] ?? '') : '',
    'smtp_port'     => $savedMailMode === 'smtp' ? ($env['MAIL_PORT'] ?? '587') : '587',
    'smtp_user'     => $savedMailMode === 'smtp' && ($env['MAIL_USERNAME'] ?? 'null') !== 'null' ? ($env['MAIL_USERNAME'] ?? '') : '',
    'smtp_scheme'   => ($env['MAIL_SCHEME'] ?? '') === 'smtps' ? 'smtps' : 'smtp',
    'smtp_from'     => $savedMailMode === 'smtp' ? $fromAddress : '',
    'from_name'     => ($env['MAIL_FROM_NAME'] ?? '') !== '' ? $env['MAIL_FROM_NAME'] : 'EduVers',
    'app_url'       => $env['APP_URL'] ?? 'http://127.0.0.1:8000',
];

$errors = [];
$notice = null;

/** One-line text input from the POST body. */
$in = fn (string $key): string => trim(str_replace(["\r", "\n", "\0"], '', (string) ($_POST[$key] ?? '')));

/**
 * Validate the database part and return the connection settings.
 *
 * @return array{driver:string,host:string,port:int,database:string,username:string,password:string,ssl:bool}
 */
$readDatabase = function () use ($in, $env, &$errors, &$form): array {
    foreach (['db_host', 'db_port', 'db_name', 'db_user'] as $key) {
        $form[$key] = $in($key);
    }
    $form['db_ssl']    = isset($_POST['db_ssl']) ? '1' : '';
    $form['db_nopass'] = isset($_POST['db_nopass']) ? '1' : '';

    if (! preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $form['db_host'])) {
        $errors['db_host'] = 'Type the server address, for example 127.0.0.1 (letters, numbers, dots and dashes only).';
    }
    if (! ctype_digit($form['db_port']) || (int) $form['db_port'] < 1 || (int) $form['db_port'] > 65535) {
        $errors['db_port'] = 'The port is a number, usually 3306.';
    }
    if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $form['db_name'])) {
        $errors['db_name'] = 'Use letters, numbers and underscores only, for example eduvers.';
    }
    if (! preg_match('/^[A-Za-z0-9._\-@]{1,80}$/', $form['db_user'])) {
        $errors['db_user'] = 'Type the database username, for example root.';
    }

    $typed    = str_replace(["\r", "\n", "\0"], '', (string) ($_POST['db_pass'] ?? ''));
    $password = $form['db_nopass'] === '1' ? '' : ($typed !== '' ? $typed : ($env['DB_PASSWORD'] ?? ''));

    return [
        'driver'   => ($env['DB_CONNECTION'] ?? 'mysql') === 'pgsql' ? 'pgsql' : 'mysql',
        'host'     => $form['db_host'],
        'port'     => (int) $form['db_port'],
        'database' => $form['db_name'],
        'username' => $form['db_user'],
        'password' => $password,
        'ssl'      => $form['db_ssl'] === '1',
    ];
};

/* ---- "Test connection" button (answers with JSON) ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'test') {
    $db = $readDatabase();
    header('Content-Type: application/json; charset=utf-8');

    if ($errors !== []) {
        echo json_encode(['ok' => false, 'title' => 'Please check the fields', 'message' => implode(' ', array_values($errors))]);
        exit;
    }

    echo json_encode(testDatabase($db));
    exit;
}

/* ---- "Save settings" ---------------------------------------------------- */
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = $readDatabase();

    foreach (['mail_mode', 'gmail_address', 'brevo_sender', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_scheme', 'smtp_from', 'from_name', 'app_url'] as $key) {
        $form[$key] = $in($key);
    }
    if (! in_array($form['mail_mode'], ['off', 'gmail', 'brevo', 'smtp'], true)) {
        $form['mail_mode'] = 'off';
    }

    $isEmail  = fn (string $v): bool => filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    $fromName = mb_substr(str_replace(['"', '\\'], '', $form['from_name']), 0, 60) ?: 'EduVers';
    $form['from_name'] = $fromName;

    $set = [
        'DB_HOST'           => $db['host'],
        'DB_PORT'           => (string) $db['port'],
        'DB_DATABASE'       => $db['database'],
        'DB_USERNAME'       => $db['username'],
        'DB_PASSWORD'       => $db['password'],
        'MYSQL_ATTR_SSL_CA' => '',
    ];

    if ($db['ssl'] && $db['driver'] === 'mysql') {
        $ca = findCaBundle();
        if ($ca === null) {
            $errors['db_ssl'] = 'No certificate file (cacert.pem) was found on this computer, so a secure connection cannot be set up here.';
        } else {
            $set['MYSQL_ATTR_SSL_CA'] = $ca;
        }
    }

    switch ($form['mail_mode']) {
        case 'gmail':
            $appPassword = str_replace(' ', '', (string) ($_POST['gmail_pass'] ?? ''));
            $keep        = $appPassword === '' && $savedMailMode === 'gmail' && $saved['mail_pass'];
            if (! $isEmail($form['gmail_address'])) {
                $errors['gmail_address'] = 'Type the full Gmail address, for example school@gmail.com.';
            }
            if ($appPassword === '' && ! $keep) {
                $errors['gmail_pass'] = 'Paste the 16-letter App Password from Google (not the normal Gmail password).';
            }
            $set += [
                'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => 'smtp', 'MAIL_HOST' => 'smtp.gmail.com', 'MAIL_PORT' => '587',
                'MAIL_USERNAME' => $form['gmail_address'], 'MAIL_PASSWORD' => $keep ? ($env['MAIL_PASSWORD'] ?? '') : $appPassword,
                'MAIL_FROM_ADDRESS' => $form['gmail_address'],
            ];
            break;

        case 'brevo':
            $key  = trim(str_replace(["\r", "\n", ' '], '', (string) ($_POST['brevo_key'] ?? '')));
            $keep = $key === '' && $saved['brevo_key'];
            if ($key === '' && ! $keep) {
                $errors['brevo_key'] = 'Paste the Brevo API key (it starts with xkeysib-).';
            }
            if (! $isEmail($form['brevo_sender'])) {
                $errors['brevo_sender'] = 'Type the sender email address you verified in Brevo.';
            }
            $set += [
                'MAIL_MAILER' => 'brevo', 'BREVO_API_KEY' => $keep ? ($env['BREVO_API_KEY'] ?? '') : $key,
                'MAIL_FROM_ADDRESS' => $form['brevo_sender'],
            ];
            break;

        case 'smtp':
            $smtpPassword = str_replace(["\r", "\n", "\0"], '', (string) ($_POST['smtp_pass'] ?? ''));
            $keep         = $smtpPassword === '' && $savedMailMode === 'smtp' && $saved['mail_pass'];
            if (! preg_match('/^[A-Za-z0-9.\-]{1,253}$/', $form['smtp_host'])) {
                $errors['smtp_host'] = 'Type the mail server address, for example smtp.example.com.';
            }
            if (! ctype_digit($form['smtp_port']) || (int) $form['smtp_port'] < 1 || (int) $form['smtp_port'] > 65535) {
                $errors['smtp_port'] = 'The port is a number, usually 587 or 465.';
            }
            if (! $isEmail($form['smtp_from'])) {
                $errors['smtp_from'] = 'Type the email address the alerts are sent from.';
            }
            $form['smtp_scheme'] = $form['smtp_scheme'] === 'smtps' ? 'smtps' : 'smtp';
            $set += [
                'MAIL_MAILER' => 'smtp', 'MAIL_SCHEME' => $form['smtp_scheme'], 'MAIL_HOST' => $form['smtp_host'], 'MAIL_PORT' => $form['smtp_port'],
                'MAIL_USERNAME' => $form['smtp_user'] !== '' ? $form['smtp_user'] : 'null',
                'MAIL_PASSWORD' => $keep ? ($env['MAIL_PASSWORD'] ?? '') : ($smtpPassword !== '' ? $smtpPassword : 'null'),
                'MAIL_FROM_ADDRESS' => $form['smtp_from'],
            ];
            break;

        default: // off: emails are only written to the log file
            $set += ['MAIL_MAILER' => 'log'];
    }

    $sendsEmail = $form['mail_mode'] !== 'off';
    $set += [
        'MAIL_FROM_NAME'     => $fromName,
        'NOTIFICATIONS_MAIL' => $sendsEmail ? 'true' : 'false',
        // Real emails are sent by a background worker (started by Start-EduVers.bat) so
        // that posting a lesson to a whole class does not make the teacher wait.
        'QUEUE_CONNECTION'   => $sendsEmail ? 'database' : 'sync',
    ];

    $url = rtrim($form['app_url'], '/');
    if (! preg_match('#^https?://[A-Za-z0-9.\-]+(:\d{1,5})?(/[A-Za-z0-9._~/\-]*)?$#', $url)) {
        $errors['app_url'] = 'Type a web address that starts with http:// or https://, for example http://127.0.0.1:8000.';
    }
    $form['app_url'] = $url;
    $set['APP_URL']  = $url;

    if ($errors === []) {
        try {
            writeEnv($set);
            $done = true;

            // Tells the installer that the form has been completed at least once
            @file_put_contents(APP_ROOT.'/storage/app/eduvers-setup-saved.txt', date('c')."\r\n");

            $marker = (string) getenv('EDUVERS_SETUP_DONE');
            if ($marker !== '') {
                @file_put_contents($marker, "saved\r\n");
            }

            // The form's small web server is no longer needed: stop it a moment after this page is sent
            register_shutdown_function(function (): void {
                $pid = getmypid();
                if (PHP_OS_FAMILY === 'Windows') {
                    @pclose(@popen('start "" /b cmd /c "ping -n 4 127.0.0.1 >nul & taskkill /F /PID '.$pid.' >nul 2>&1"', 'r'));
                } else {
                    @exec('(sleep 3; kill '.$pid.') >/dev/null 2>&1 &');
                }
            });
        } catch (Throwable $e) {
            $notice = $e->getMessage();
        }
    } else {
        $notice = 'Some fields need your attention. They are marked in red below.';
    }
}

/* ====================================================================== */
/*  Web: page                                                              */
/* ====================================================================== */

$h       = fn (?string $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$err     = fn (string $key): string => isset($errors[$key]) ? '<p class="error" id="'.$key.'-error">'.htmlspecialchars($errors[$key], ENT_QUOTES, 'UTF-8').'</p>' : '';
$invalid = fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="'.$key.'-error"' : '';
$caFound = findCaBundle() !== null;

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>EduVers settings</title>
<style>
    :root {
        --gold: #D4AF37; --gold-dark: #AA7C11; --gold-deep: #8A640F; --gold-50: #FBF8EE; --gold-200: #EBDAA5;
        --ivory: #FAF8F3; --ivory-200: #F3EFE6; --line: #E8E2D5; --ink: #0F172A; --slate: #475569; --muted: #64748B;
        --red: #B91C1C; --red-50: #FEF2F2; --green: #166534; --green-50: #F0FDF4;
    }
    *, *::before, *::after { box-sizing: border-box; }
    [hidden] { display: none !important; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; background: var(--ivory); color: var(--ink); font: 16px/1.55 "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif; overflow-wrap: break-word; }
    .wrap { max-width: 46rem; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
    header.brand { display: flex; align-items: center; gap: .9rem; padding: .5rem 0 1.25rem; }
    header.brand img { width: 3.25rem; height: 3.25rem; border-radius: 50%; box-shadow: 0 0 0 2px var(--gold); background: #fff; flex: none; }
    header.brand .name { font: 600 1.35rem/1.1 Georgia, "Times New Roman", serif; }
    header.brand .sub { font-size: .68rem; font-weight: 600; letter-spacing: .22em; text-transform: uppercase; color: #9C7C3C; margin-top: .25rem; }
    h1 { font: 600 clamp(1.7rem, 5vw, 2.25rem)/1.15 Georgia, "Times New Roman", serif; margin: .5rem 0 .5rem; }
    .lead { color: var(--slate); margin: 0 0 1.5rem; }
    .card { background: #fff; border: 1px solid var(--line); border-radius: 1rem; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 4px 16px rgba(15,23,42,.06); padding: 1.25rem; margin-top: 1.25rem; }
    @media (min-width: 40rem) { .card { padding: 1.75rem; } .wrap { padding-top: 2.5rem; } }
    .card > .step { display: flex; gap: .85rem; align-items: flex-start; }
    .num { flex: none; width: 2.1rem; height: 2.1rem; border-radius: 50%; display: grid; place-items: center; font: 600 1rem Georgia, serif; color: var(--ink); background: linear-gradient(135deg, #E0C677, var(--gold) 45%, var(--gold-dark)); }
    h2 { font: 600 1.25rem/1.25 Georgia, "Times New Roman", serif; margin: .15rem 0 .15rem; }
    .hint { color: var(--muted); font-size: .9rem; margin: 0; }
    .grid { display: grid; gap: 1rem; margin-top: 1.25rem; }
    .grid > * { min-width: 0; }
    @media (min-width: 36rem) { .cols-2 { grid-template-columns: 1fr 1fr; } .cols-host { grid-template-columns: 1fr 9rem; } }
    label, .label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
    label small, .label small { font-weight: 400; color: var(--muted); }
    input[type=text], input[type=password], input[type=email], input[type=number], input[type=url], select {
        width: 100%; font: inherit; color: var(--ink); background: var(--ivory); border: 1px solid var(--line); border-radius: .7rem; padding: .65rem .8rem; min-height: 2.9rem;
    }
    input:focus-visible, select:focus-visible, button:focus-visible, summary:focus-visible, a:focus-visible { outline: 3px solid rgba(212,175,55,.55); outline-offset: 2px; }
    input[aria-invalid=true] { border-color: var(--red); background: var(--red-50); }
    .field-note { font-size: .82rem; color: var(--muted); margin: .35rem 0 0; }
    .error { font-size: .85rem; color: var(--red); margin: .35rem 0 0; font-weight: 600; }
    .check { display: flex; gap: .6rem; align-items: flex-start; font-weight: 400; font-size: .92rem; margin: 0; }
    .check input { width: 1.15rem; height: 1.15rem; margin-top: .2rem; accent-color: var(--gold-dark); flex: none; }
    .pw { position: relative; }
    .pw input { padding-right: 4.4rem; }
    .pw button { position: absolute; right: .35rem; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--gold-deep); font-family: inherit; font-size: .82rem; font-weight: 600; padding: .45rem .55rem; border-radius: .5rem; cursor: pointer; }
    .choices { display: grid; gap: .6rem; margin-top: 1.25rem; }
    @media (min-width: 36rem) { .choices { grid-template-columns: 1fr 1fr; } }
    .choice { position: relative; display: block; margin: 0; cursor: pointer; }
    .choice input { position: absolute; opacity: 0; inset: 0; cursor: pointer; }
    .choice span { display: block; height: 100%; border: 1px solid var(--line); border-radius: .8rem; padding: .75rem .9rem; background: #fff; font-weight: 600; }
    .choice span small { display: block; font-weight: 400; color: var(--muted); font-size: .82rem; margin-top: .1rem; }
    .choice input:checked + span { border-color: var(--gold); background: var(--gold-50); box-shadow: 0 0 0 2px rgba(212,175,55,.25); }
    .choice input:focus-visible + span { outline: 3px solid rgba(212,175,55,.55); outline-offset: 2px; }
    .panel { margin-top: 1rem; padding: 1rem; border-radius: .8rem; background: var(--ivory); border: 1px solid var(--line); }
    .panel[hidden] { display: none; }
    .panel ol { margin: .25rem 0 0; padding-left: 1.2rem; color: var(--slate); font-size: .9rem; }
    .panel .grid { margin-top: 1rem; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: .5rem; font-family: inherit; font-size: 1rem; font-weight: 600; border-radius: .8rem; padding: .7rem 1.2rem; min-height: 2.9rem; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
    .btn-gold { color: var(--ink); background: linear-gradient(135deg, #E0C677, var(--gold) 45%, var(--gold-dark)); box-shadow: 0 4px 20px rgba(212,175,55,.25); }
    .btn-outline { color: var(--gold-deep); background: #fff; border-color: var(--gold-200); }
    .btn[disabled] { opacity: .6; cursor: progress; }
    .result { margin-top: .9rem; padding: .75rem .9rem; border-radius: .7rem; font-size: .92rem; border: 1px solid; }
    .result[hidden] { display: none; }
    .result strong { display: block; }
    .result.ok { background: var(--green-50); border-color: #BBF7D0; color: var(--green); }
    .result.bad { background: var(--red-50); border-color: #FECACA; color: var(--red); }
    .banner { margin: 0 0 1rem; padding: .8rem 1rem; border-radius: .8rem; background: var(--red-50); border: 1px solid #FECACA; color: var(--red); font-weight: 600; }
    details { margin-top: 1rem; }
    summary { cursor: pointer; font-weight: 600; color: var(--gold-deep); padding: .35rem 0; }
    .actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; margin-top: 1.5rem; }
    .actions .hint { flex: 1 1 14rem; }
    .done { text-align: center; padding: 2.25rem 1.25rem; }
    .tick { width: 4rem; height: 4rem; border-radius: 50%; margin: 0 auto 1rem; display: grid; place-items: center; background: var(--green-50); color: var(--green); font-size: 2rem; border: 1px solid #BBF7D0; }
    .summary { text-align: left; margin: 1.5rem auto 0; max-width: 28rem; border-top: 1px solid var(--line); }
    .summary div { display: flex; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--line); font-size: .92rem; }
    .summary dt { color: var(--muted); } .summary dd { margin: 0; font-weight: 600; text-align: right; min-width: 0; }
    footer { margin-top: 2rem; font-size: .8rem; color: var(--muted); text-align: center; }
    @media (prefers-reduced-motion: no-preference) { .btn, .choice span, input, select { transition: border-color .15s, background-color .15s, box-shadow .15s; } }
</style>
</head>
<body>
<div class="wrap">
    <header class="brand">
        <img src="/logo.jpg" alt="" width="52" height="52">
        <div>
            <div class="name">EduVers</div>
            <div class="sub">Movers Institute</div>
        </div>
    </header>

<?php if ($done): ?>
    <main class="card done">
        <div class="tick" aria-hidden="true">&#10003;</div>
        <h1>Settings saved</h1>
        <?php if ($mode === 'install'): ?>
            <p class="lead">Go back to the <strong>installer window</strong>. It continues by itself and sets up the database.<br>You can close this tab.</p>
        <?php else: ?>
            <p class="lead">Go back to the <strong>settings window</strong> and wait until it says it is finished.<br>Then close EduVers and start it again so it uses the new settings. You can close this tab.</p>
        <?php endif; ?>
        <dl class="summary">
            <div><dt>Database</dt><dd><?= $h($form['db_name']) ?> on <?= $h($form['db_host']) ?>:<?= $h($form['db_port']) ?></dd></div>
            <div><dt>Database user</dt><dd><?= $h($form['db_user']) ?></dd></div>
            <div><dt>Email alerts</dt><dd><?= $h(['off' => 'Off', 'gmail' => 'Gmail: '.$form['gmail_address'], 'brevo' => 'Brevo: '.$form['brevo_sender'], 'smtp' => 'Mail server: '.$form['smtp_host']][$form['mail_mode']]) ?></dd></div>
            <div><dt>Address</dt><dd><?= $h($form['app_url']) ?></dd></div>
        </dl>
        <p class="hint" style="margin-top:1.25rem">Passwords and keys are stored only on this computer, in EduVers&rsquo;s settings file.</p>
    </main>
<?php else: ?>
    <main>
        <h1><?= $mode === 'install' ? 'Connect EduVers' : 'EduVers settings' ?></h1>
        <p class="lead">
            <?= $mode === 'install'
                ? 'Tell EduVers where its database is and whether it should send emails. If Laragon is installed on this computer, the database values below are already right: test the connection and save.'
                : 'Change where EduVers keeps its data and how it sends emails. Nothing changes until you click Save.' ?>
        </p>

        <?php if ($notice): ?><p class="banner" role="alert"><?= $h($notice) ?></p><?php endif; ?>

        <form method="post" action="/" id="settings" novalidate autocomplete="off">
            <input type="hidden" name="token" value="<?= $h($token) ?>">

            <!-- 1. Database -->
            <section class="card" aria-labelledby="db-title">
                <div class="step">
                    <span class="num" aria-hidden="true">1</span>
                    <div>
                        <h2 id="db-title">Database</h2>
                        <p class="hint">Where EduVers keeps accounts, lessons, quizzes and grades.</p>
                    </div>
                </div>

                <div class="grid cols-host">
                    <div>
                        <label for="db_host">Server address</label>
                        <input type="text" id="db_host" name="db_host" value="<?= $h($form['db_host']) ?>" spellcheck="false" autocapitalize="off" required<?= $invalid('db_host') ?>>
                        <p class="field-note">Use <b>127.0.0.1</b> when the database is on this computer (Laragon).</p>
                        <?= $err('db_host') ?>
                    </div>
                    <div>
                        <label for="db_port">Port</label>
                        <input type="text" inputmode="numeric" id="db_port" name="db_port" value="<?= $h($form['db_port']) ?>" required<?= $invalid('db_port') ?>>
                        <?= $err('db_port') ?>
                    </div>
                </div>

                <div class="grid cols-2">
                    <div>
                        <label for="db_name">Database name</label>
                        <input type="text" id="db_name" name="db_name" value="<?= $h($form['db_name']) ?>" spellcheck="false" autocapitalize="off" required<?= $invalid('db_name') ?>>
                        <?= $err('db_name') ?>
                    </div>
                    <div>
                        <label for="db_user">Username</label>
                        <input type="text" id="db_user" name="db_user" value="<?= $h($form['db_user']) ?>" spellcheck="false" autocapitalize="off" required<?= $invalid('db_user') ?>>
                        <?= $err('db_user') ?>
                    </div>
                </div>

                <div class="grid">
                    <div>
                        <label for="db_pass">Password <small><?= $saved['db_pass'] ? '(one is saved: leave empty to keep it)' : '(Laragon has none by default: leave empty)' ?></small></label>
                        <div class="pw">
                            <input type="password" id="db_pass" name="db_pass" value="" autocomplete="new-password">
                            <button type="button" data-show="db_pass" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    <?php if ($saved['db_pass']): ?>
                        <label class="check"><input type="checkbox" name="db_nopass" value="1"<?= $form['db_nopass'] ? ' checked' : '' ?>> <span>This user has no password (remove the saved one)</span></label>
                    <?php endif; ?>
                    <div>
                        <label class="check"><input type="checkbox" name="db_ssl" value="1"<?= $form['db_ssl'] ? ' checked' : '' ?><?= $caFound ? '' : ' disabled' ?>> <span>This database needs a secure (SSL) connection <small style="color:var(--muted)">&mdash; cloud databases such as TiDB Cloud. Leave unticked for Laragon.</small></span></label>
                        <?= $err('db_ssl') ?>
                    </div>
                </div>

                <div class="actions" style="margin-top:1.1rem">
                    <button type="button" class="btn btn-outline" id="test-db">Test connection</button>
                    <p class="hint">Checks these values without saving them.</p>
                </div>
                <div class="result" id="test-result" role="status" aria-live="polite" hidden></div>
            </section>

            <!-- 2. Email -->
            <section class="card" aria-labelledby="mail-title">
                <div class="step">
                    <span class="num" aria-hidden="true">2</span>
                    <div>
                        <h2 id="mail-title">Email alerts</h2>
                        <p class="hint">Students can get an email when a lesson, quiz or announcement is posted. The bell inside EduVers works either way.</p>
                    </div>
                </div>

                <div class="choices" role="radiogroup" aria-labelledby="mail-title">
                    <?php foreach ([
                        'off'   => ['No emails for now', 'You can turn this on later.'],
                        'gmail' => ['Gmail', 'Send from a Gmail address.'],
                        'brevo' => ['Brevo', 'Free email service, 300 emails a day.'],
                        'smtp'  => ['Another mail server', 'Your school or hosting email (SMTP).'],
                    ] as $value => [$title, $sub]): ?>
                        <label class="choice">
                            <input type="radio" name="mail_mode" value="<?= $value ?>"<?= $form['mail_mode'] === $value ? ' checked' : '' ?>>
                            <span><?= $title ?><small><?= $sub ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="panel" data-panel="gmail" hidden>
                    <p class="label">Before you start</p>
                    <ol>
                        <li>Turn on 2-Step Verification for the Gmail account.</li>
                        <li>Open <b>myaccount.google.com/apppasswords</b> and create an App Password named EduVers.</li>
                        <li>Paste the 16 letters below. Your normal Gmail password will not work.</li>
                    </ol>
                    <div class="grid cols-2">
                        <div>
                            <label for="gmail_address">Gmail address</label>
                            <input type="email" id="gmail_address" name="gmail_address" value="<?= $h($form['gmail_address']) ?>" spellcheck="false" autocapitalize="off"<?= $invalid('gmail_address') ?>>
                            <?= $err('gmail_address') ?>
                        </div>
                        <div>
                            <label for="gmail_pass">App Password <small><?= $savedMailMode === 'gmail' && $saved['mail_pass'] ? '(saved: leave empty to keep)' : '' ?></small></label>
                            <div class="pw">
                                <input type="password" id="gmail_pass" name="gmail_pass" value="" autocomplete="new-password"<?= $invalid('gmail_pass') ?>>
                                <button type="button" data-show="gmail_pass" aria-pressed="false">Show</button>
                            </div>
                            <?= $err('gmail_pass') ?>
                        </div>
                    </div>
                </div>

                <div class="panel" data-panel="brevo" hidden>
                    <p class="label">Before you start</p>
                    <ol>
                        <li>Create a free account at <b>brevo.com</b>.</li>
                        <li>Under <b>Senders, Domains, IPs &rarr; Senders</b>, add and verify the address emails come from.</li>
                        <li>Under <b>SMTP &amp; API &rarr; API Keys</b>, generate a key and paste it below.</li>
                    </ol>
                    <div class="grid cols-2">
                        <div>
                            <label for="brevo_key">API key <small><?= $saved['brevo_key'] ? '(saved: leave empty to keep)' : '' ?></small></label>
                            <div class="pw">
                                <input type="password" id="brevo_key" name="brevo_key" value="" autocomplete="new-password"<?= $invalid('brevo_key') ?>>
                                <button type="button" data-show="brevo_key" aria-pressed="false">Show</button>
                            </div>
                            <?= $err('brevo_key') ?>
                        </div>
                        <div>
                            <label for="brevo_sender">Verified sender email</label>
                            <input type="email" id="brevo_sender" name="brevo_sender" value="<?= $h($form['brevo_sender']) ?>" spellcheck="false" autocapitalize="off"<?= $invalid('brevo_sender') ?>>
                            <?= $err('brevo_sender') ?>
                        </div>
                    </div>
                </div>

                <div class="panel" data-panel="smtp" hidden>
                    <p class="hint">Ask your email provider for these values.</p>
                    <div class="grid cols-host">
                        <div>
                            <label for="smtp_host">Mail server address</label>
                            <input type="text" id="smtp_host" name="smtp_host" value="<?= $h($form['smtp_host']) ?>" placeholder="smtp.example.com" spellcheck="false" autocapitalize="off"<?= $invalid('smtp_host') ?>>
                            <?= $err('smtp_host') ?>
                        </div>
                        <div>
                            <label for="smtp_port">Port</label>
                            <input type="text" inputmode="numeric" id="smtp_port" name="smtp_port" value="<?= $h($form['smtp_port']) ?>"<?= $invalid('smtp_port') ?>>
                            <?= $err('smtp_port') ?>
                        </div>
                    </div>
                    <div class="grid cols-2">
                        <div>
                            <label for="smtp_user">Username <small>(often the email address)</small></label>
                            <input type="text" id="smtp_user" name="smtp_user" value="<?= $h($form['smtp_user']) ?>" spellcheck="false" autocapitalize="off">
                        </div>
                        <div>
                            <label for="smtp_pass">Password <small><?= $savedMailMode === 'smtp' && $saved['mail_pass'] ? '(saved: leave empty to keep)' : '' ?></small></label>
                            <div class="pw">
                                <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password">
                                <button type="button" data-show="smtp_pass" aria-pressed="false">Show</button>
                            </div>
                        </div>
                        <div>
                            <label for="smtp_scheme">Security</label>
                            <select id="smtp_scheme" name="smtp_scheme">
                                <option value="smtp"<?= $form['smtp_scheme'] === 'smtp' ? ' selected' : '' ?>>STARTTLS (usually port 587)</option>
                                <option value="smtps"<?= $form['smtp_scheme'] === 'smtps' ? ' selected' : '' ?>>SSL/TLS (usually port 465)</option>
                            </select>
                        </div>
                        <div>
                            <label for="smtp_from">Send emails from</label>
                            <input type="email" id="smtp_from" name="smtp_from" value="<?= $h($form['smtp_from']) ?>" spellcheck="false" autocapitalize="off"<?= $invalid('smtp_from') ?>>
                            <?= $err('smtp_from') ?>
                        </div>
                    </div>
                </div>

                <div class="grid" data-panel-any hidden>
                    <div>
                        <label for="from_name">Sender name <small>(what students see as the sender)</small></label>
                        <input type="text" id="from_name" name="from_name" value="<?= $h($form['from_name']) ?>" maxlength="60">
                        <p class="field-note">After saving, sign in as the admin and use <b>System check &rarr; Send test</b> to confirm emails arrive.</p>
                    </div>
                </div>
            </section>

            <!-- 3. Address -->
            <section class="card" aria-labelledby="url-title">
                <div class="step">
                    <span class="num" aria-hidden="true">3</span>
                    <div>
                        <h2 id="url-title">Address of EduVers</h2>
                        <p class="hint">Used for the links inside emails. Keep the value below unless your developer gave you another address.</p>
                    </div>
                </div>
                <div class="grid">
                    <div>
                        <label for="app_url">Web address</label>
                        <input type="text" inputmode="url" id="app_url" name="app_url" value="<?= $h($form['app_url']) ?>" spellcheck="false" autocapitalize="off"<?= $invalid('app_url') ?>>
                        <?= $err('app_url') ?>
                    </div>
                </div>
            </section>

            <div class="actions">
                <button type="submit" class="btn btn-gold" id="save">Save settings</button>
                <p class="hint">Saved on this computer only. Passwords are never shown again on this page.</p>
            </div>
        </form>
    </main>

    <script>
    (function () {
        var form = document.getElementById('settings');

        // Show only the panel for the chosen email option
        function syncMail() {
            var mode = (form.querySelector('input[name=mail_mode]:checked') || {}).value || 'off';
            form.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== mode; });
            form.querySelector('[data-panel-any]').hidden = mode === 'off';
        }
        form.querySelectorAll('input[name=mail_mode]').forEach(function (r) { r.addEventListener('change', syncMail); });
        syncMail();

        // Show / hide password fields
        form.querySelectorAll('button[data-show]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-show'));
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-pressed', String(show));
            });
        });

        // Test the database connection without saving
        var testBtn = document.getElementById('test-db');
        var result = document.getElementById('test-result');
        function showResult(ok, title, message) {
            result.hidden = false;
            result.className = 'result ' + (ok ? 'ok' : 'bad');
            result.textContent = '';
            var strong = document.createElement('strong');
            strong.textContent = (ok ? '\u2713 ' : '\u2717 ') + title;
            result.appendChild(strong);
            result.appendChild(document.createTextNode(message));
        }
        testBtn.addEventListener('click', function () {
            testBtn.disabled = true;
            testBtn.textContent = 'Testing\u2026';
            fetch('/?action=test', { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
                .then(function (res) { return res.json(); })
                .then(function (data) { showResult(!!data.ok, data.title || '', data.message || ''); })
                .catch(function () { showResult(false, 'The test could not run', 'The settings window may have been closed. Open the settings form again.'); })
                .then(function () { testBtn.disabled = false; testBtn.textContent = 'Test connection'; });
        });

        // Avoid double saves
        form.addEventListener('submit', function () {
            var save = document.getElementById('save');
            save.disabled = true;
            save.textContent = 'Saving\u2026';
        });

        var firstError = form.querySelector('[aria-invalid=true]');
        if (firstError) { firstError.focus(); }
    })();
    </script>
<?php endif; ?>

    <footer>EduVers Learning Management System &middot; Movers Institute of Technology and Education</footer>
</div>
</body>
</html>
