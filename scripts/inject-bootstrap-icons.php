<?php

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public';
$files = array_merge(
    glob($root . DIRECTORY_SEPARATOR . '*.html') ?: [],
    glob($root . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '*.html') ?: []
);

foreach ($files as $file) {
    $html = file_get_contents($file);
    if (str_contains($html, 'bootstrap-icons.css')) {
        continue;
    }

    $isRoot = basename(dirname($file)) === 'public';
    $href = $isRoot
        ? 'assets/vendor/bootstrap-icons/bootstrap-icons.css'
        : '../assets/vendor/bootstrap-icons/bootstrap-icons.css';

    $tag = '<link rel="stylesheet" href="'.$href.'">';
    $needle = 'bootstrap.min.css" rel="stylesheet">';
    if (! str_contains($html, $needle)) {
        echo 'skip '.basename($file).PHP_EOL;
        continue;
    }

    $html = str_replace(
        $needle,
        $needle."\n  ".$tag,
        $html
    );
    file_put_contents($file, $html);
    echo 'injected '.basename($file).PHP_EOL;
}
