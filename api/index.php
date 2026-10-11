<?php

/*
|--------------------------------------------------------------------------
| Vercel serverless entry point
|--------------------------------------------------------------------------
|
| Vercel (vercel-php runtime) invokes this file for every non-static request.
| The function filesystem is read-only except /tmp, so Laravel's compiled
| caches are redirected there before the framework boots. Everything else is
| the normal public/index.php front controller.
|
*/

// Laravel only treats cache paths starting with "/" as absolute, so this block is
// Linux-only (which is what Vercel runs). On Windows it is skipped on purpose.
if ((getenv('VERCEL') !== false || getenv('VERCEL_ENV') !== false) && DIRECTORY_SEPARATOR === '/') {
    $tmp = rtrim(sys_get_temp_dir(), '/') ?: '/tmp';

    foreach ([
        'APP_CONFIG_CACHE' => $tmp.'/config.php',
        'APP_EVENTS_CACHE' => $tmp.'/events.php',
        'APP_PACKAGES_CACHE' => $tmp.'/packages.php',
        'APP_ROUTES_CACHE' => $tmp.'/routes-v7.php',
        'APP_SERVICES_CACHE' => $tmp.'/services.php',
        'VIEW_COMPILED_PATH' => $tmp.'/views',
    ] as $key => $value) {
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    if (! is_dir($tmp.'/views')) {
        @mkdir($tmp.'/views', 0777, true);
    }
}

// The function lives under /api, but the application's own routes also start
// with /api/... . Without this, Symfony would strip "/api" as the base path and
// "/api/v1/..." would 404. Pretend the front controller is public/index.php.
if (str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/index.php')) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/../public/index.php';
}

require __DIR__.'/../public/index.php';
