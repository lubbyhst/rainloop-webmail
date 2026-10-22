<?php
// Run both with CLI and through Apache in the disposable smoke-test container.
declare(strict_types=1);

$errors = [];
$minimum = [802 => 80235, 803 => 80336, 804 => 80427, 805 => 80512];
$branch = intdiv(PHP_VERSION_ID, 100);
if (!isset($minimum[$branch]) || PHP_VERSION_ID < $minimum[$branch]) {
    $errors[] = 'PHP ' . PHP_VERSION . ' lacks the required supported security release';
}
foreach (['curl', 'dom', 'fileinfo', 'gd', 'iconv', 'intl', 'json', 'mbstring', 'openssl', 'pdo_mysql', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Missing extension: ' . $extension;
    }
}
foreach (['display_errors', 'display_startup_errors', 'expose_php', 'session.upload_progress.enabled', 'allow_url_include'] as $setting) {
    if (filter_var(ini_get($setting), FILTER_VALIDATE_BOOLEAN)) {
        $errors[] = 'Unsafe runtime setting: ' . $setting;
    }
}
if (!filter_var(ini_get('log_errors'), FILTER_VALIDATE_BOOLEAN)) {
    $errors[] = 'PHP error logging is disabled';
}
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'apache2handler') {
    $errors[] = 'Unexpected PHP SAPI: ' . PHP_SAPI;
}
if ($errors) {
    http_response_code(500);
    echo implode("\n", $errors) . "\n";
    exit(1);
}
header('Content-Type: text/plain');
echo "Runtime checks passed\n";
