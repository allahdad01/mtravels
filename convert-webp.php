<?php
/**
 * Convert all PNG/JPG/JPEG images in uploads/ to WebP format.
 * Run via CLI: php convert-webp.php
 * Options:
 *   --delete-originals  Remove source files after successful conversion
 *   --quality=N         Set WebP quality (default: 80)
 *   --max-dimension=N   Resize images larger than N px on longest side (default: 0 = no resize)
 */

ini_set('memory_limit', '512M');
set_time_limit(0);

$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$deleteOriginals = false;
$quality = 80;
$maxDimension = 0;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--delete-originals') {
        $deleteOriginals = true;
    } elseif (strncmp($arg, '--quality=', 10) === 0) {
        $quality = max(1, min(100, (int) substr($arg, 10)));
    } elseif (strncmp($arg, '--max-dimension=', 16) === 0) {
        $maxDimension = (int) substr($arg, 16);
    }
}

$extensions = ['png', 'jpg', 'jpeg'];
$stats = ['total' => 0, 'converted' => 0, 'skipped' => 0, 'errors' => 0, 'memory_errors' => 0];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

echo "Scanning $baseDir ...\n\n";

foreach ($iterator as $file) {
    if (!$file->isFile()) continue;

    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $extensions)) continue;

    $stats['total']++;
    $srcPath = $file->getPathname();
    $webpPath = $file->getPath() . DIRECTORY_SEPARATOR . pathinfo($file->getFilename(), PATHINFO_FILENAME) . '.webp';

    if (file_exists($webpPath) && filemtime($webpPath) >= filemtime($srcPath)) {
        $stats['skipped']++;
        continue;
    }

    try {
        switch ($ext) {
            case 'png':
                $img = @imagecreatefrompng($srcPath);
                if (!$img) throw new Exception('Failed to load PNG');
                imagepalettetotruecolor($img);
                imagealphablending($img, true);
                imagesavealpha($img, false);
                break;
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($srcPath);
                if (!$img) throw new Exception('Failed to load JPEG');
                break;
            default:
                continue 2;
        }

        // Resize if needed
        if ($maxDimension > 0) {
            $w = imagesx($img);
            $h = imagesy($img);
            if (max($w, $h) > $maxDimension) {
                $ratio = $maxDimension / max($w, $h);
                $nw = (int) round($w * $ratio);
                $nh = (int) round($h * $ratio);
                $resized = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($resized, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagedestroy($img);
                $img = $resized;
            }
        }

        $success = imagewebp($img, $webpPath, $quality);
        imagedestroy($img);

        if (!$success) throw new Exception('imagewebp() failed');

        if ($deleteOriginals && file_exists($srcPath)) {
            unlink($srcPath);
        }

        $stats['converted']++;
        echo "  OK  " . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $webpPath) . "\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Memory') !== false) {
            $stats['memory_errors']++;
            echo "SKIP  " . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $srcPath) . " — {$e->getMessage()}\n";
        } else {
            $stats['errors']++;
            echo "FAIL  " . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $srcPath) . " — {$e->getMessage()}\n";
        }
    }
}

echo "\n--- Done ---\n";
echo "Total files found:    {$stats['total']}\n";
echo "Converted:            {$stats['converted']}\n";
echo "Skipped (up to date): {$stats['skipped']}\n";
echo "Memory errors:        {$stats['memory_errors']}\n";
echo "Errors:               {$stats['errors']}\n";
echo "Originals:            " . ($deleteOriginals ? 'Deleted' : 'Kept') . "\n";
echo "WebP quality:         $quality\n";
if ($maxDimension > 0) echo "Max dimension:        {$maxDimension}px\n";
