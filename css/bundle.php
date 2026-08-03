<?php
// Combined CSS bundle for the Umrah management page.
// Serves every page stylesheet as a single response, in load order.
$config = require __DIR__ . '/bundle_files.php';

header('Content-Type: text/css; charset=UTF-8');

$out = '';
$maxMtime = 0;
foreach ($config as $file) {
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        $out .= "\n/* MISSING: $file */\n";
        continue;
    }
    $maxMtime = max($maxMtime, filemtime($path));
    $out .= "\n/* ===== $file ===== */\n";
    $out .= file_get_contents($path);
    $out .= "\n";
}
header('X-Bundle-Version: ' . $maxMtime);
echo $out;
