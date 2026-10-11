<?php

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
$files = array_merge(
    glob($root . DIRECTORY_SEPARATOR . '*.html') ?: [],
    glob($root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '*.html') ?: []
);

foreach ($files as $file) {
    $html = file_get_contents($file);
    $next = preg_replace(
        '/\s*if\s*\(\s*!document\.getElementById\(\'mbms-fa-font\'\)\s*\)\s*\{.*?document\.head\.appendChild\(font\);\s*\}\s*/s',
        "\n",
        $html
    );
    $next = str_replace(
        "font.href = 'https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap';",
        '',
        $next ?? $html
    );
    if ($next !== null && $next !== $html) {
        file_put_contents($file, $next);
        echo 'stripped '.basename($file).PHP_EOL;
    }
}
