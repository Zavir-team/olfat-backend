<?php

/**
 * Validates that the current .env looks production-ready.
 * Run: php scripts/prepare-production.php
 */
$root = dirname(__DIR__);
$envFile = $root.DIRECTORY_SEPARATOR.'.env';

if (! is_file($envFile)) {
    fwrite(STDERR, ".env not found. Copy .env.production.example to .env first.\n");
    exit(1);
}

$env = parseEnvFile($envFile);
$errors = [];

foreach ([
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
] as $key => $expected) {
    $actual = strtolower((string) ($env[$key] ?? ''));
    if ($actual !== strtolower($expected)) {
        $errors[] = "{$key} must be {$expected} (got: ".($env[$key] ?? 'missing').')';
    }
}

$dbConnection = strtolower((string) ($env['DB_CONNECTION'] ?? ''));
if (! in_array($dbConnection, ['mysql', 'mariadb', 'pgsql'], true)) {
    $errors[] = 'DB_CONNECTION must be mysql, mariadb or pgsql in production (got: '.($env['DB_CONNECTION'] ?? 'missing').'). SQLite is for local development only.';
}

if (($env['APP_KEY'] ?? '') === '') {
    $errors[] = 'APP_KEY is empty. Run: php artisan key:generate';
}

if (! str_starts_with((string) ($env['APP_URL'] ?? ''), 'https://')) {
    $errors[] = 'APP_URL must start with https://';
}

$dbUrl = (string) ($env['DB_URL'] ?? '');
$dbPassword = (string) ($env['DB_PASSWORD'] ?? '');
if ($dbUrl === '' && ($dbPassword === '' || $dbPassword === 'change-me-strong-password')) {
    $errors[] = 'Set a real DB_PASSWORD (or a full DB_URL connection string)';
}

if (strtolower((string) ($env['LOG_CHANNEL'] ?? '')) === 'stack' && strtolower((string) ($env['LOG_LEVEL'] ?? 'debug')) === 'debug') {
    $errors[] = 'LOG_LEVEL should not be debug in production';
}

if (($env['CRON_SECRET'] ?? '') !== '' && strlen((string) $env['CRON_SECRET']) < 32) {
    $errors[] = 'CRON_SECRET must be at least 32 characters when set';
}

$adminPassword = (string) ($env['SEED_ADMIN_PASSWORD'] ?? '');
if ($adminPassword !== '' && strlen($adminPassword) < 12) {
    $errors[] = 'SEED_ADMIN_PASSWORD must be at least 12 characters when set';
}

if ($errors) {
    fwrite(STDERR, "Production readiness check FAILED:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

echo "Production readiness check PASSED.\n";
echo "Next: composer install --no-dev --optimize-autoloader\n";
echo "Then: php artisan migrate --force && php artisan config:cache\n";
exit(0);

/**
 * @return array<string, string>
 */
function parseEnvFile(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $out[trim($key)] = $value;
    }

    return $out;
}
