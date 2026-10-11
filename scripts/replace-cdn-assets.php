<?php

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
$files = array_merge(
    glob($root . DIRECTORY_SEPARATOR . '*.html') ?: [],
    glob($root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '*.html') ?: []
);

foreach ($files as $file) {
    $html = file_get_contents($file);
    $isRoot = basename(dirname($file)) === 'public';
    $cssPrefix = $isRoot ? 'assets/vendor/bootstrap/css/' : '../assets/vendor/bootstrap/css/';
    $jsPrefix = $isRoot ? 'assets/vendor/bootstrap/js/' : '../assets/vendor/bootstrap/js/';

    $html = str_replace(
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
        $cssPrefix . 'bootstrap.min.css',
        $html
    );
    $html = str_replace(
        'bootstrap.rtl.min.css',
        'bootstrap.rtl.min.css',
        $html
    );
    // If RTL rewrite still points at CDN absolute path after first replace of min.css only:
    $html = str_replace(
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css',
        $cssPrefix . 'bootstrap.rtl.min.css',
        $html
    );
    $html = str_replace(
        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
        $jsPrefix . 'bootstrap.bundle.min.js',
        $html
    );

    $html = preg_replace(
        '/\s*if \(!document\.getElementById\(\'mbms-fa-font\'\)\) \{\s*var font = document\.createElement\(\'link\'\);\s*font\.id = \'mbms-fa-font\';\s*font\.rel = \'stylesheet\';\s*font\.href = \'https:\/\/fonts\.googleapis\.com[^;]+;\s*document\.head\.appendChild\(font\);\s*\}\s*/s',
        "\n",
        $html
    );

    file_put_contents($file, $html);
    echo basename($file) . PHP_EOL;
}

echo 'updated=' . count($files) . PHP_EOL;
