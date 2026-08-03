<?php
// Combined JS bundle for the Umrah management page.
// Serves every page script as a single response, in load order.
// A trailing ";" after each file keeps concatenation safe.
$config = require __DIR__ . '/bundle_files.php';

header('Content-Type: application/javascript; charset=UTF-8');

$base = realpath(__DIR__ . '/..');
$out = '';
$maxMtime = 0;
foreach ($config as $file) {
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!is_file($path)) {
        $out .= "\n/* MISSING: $file */\n";
        continue;
    }
    $maxMtime = max($maxMtime, filemtime($path));
    $out .= "\n/* ===== $file ===== */\n";
    $out .= file_get_contents($path);
    $out .= "\n;\n";
}
header('X-Bundle-Version: ' . $maxMtime);
echo $out;
