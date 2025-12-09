<?php
/**
 * CSRF Token Fix - Universal Batch Processor
 * Fixes ALL modals missing CSRF tokens in one pass
 * 
 * Usage: Open in browser: http://localhost/almoqadas/mtravels/run_csrf_fix.php
 */

set_time_limit(600);
ini_set('memory_limit', '512M');

$basedir = __DIR__;
$modalsdir = $basedir . '/modals';

$stats = [
    'total' => 0,
    'fixed' => 0,
    'protected' => 0,
    'skipped' => 0,
    'errors' => 0,
    'files' => []
];

function addcsrf($filepath) {
    $content = file_get_contents($filepath);
    
    // Skip if already has CSRF token
    if (stripos($content, 'csrf_token') !== false) {
        return ['status' => 'protected', 'content' => $content];
    }
    
    // Skip if no form tag
    if (stripos($content, '<form') === false) {
        return ['status' => 'skipped', 'content' => $content];
    }
    
    // Create CSRF token snippet
    $csrf_snippet = "\n                    <!-- CSRF Protection -->\n                    <input type=\"hidden\" name=\"csrf_token\" value=\"<?php echo h(\$_SESSION['csrf_token'] ?? ''); ?>\">";
    
    // Find and replace first <form tag
    $pattern = '/(<form[^>]*>)/i';
    if (preg_match($pattern, $content)) {
        $new_content = preg_replace($pattern, '$1' . $csrf_snippet, $content, 1);
        return ['status' => 'fixed', 'content' => $new_content];
    }
    
    return ['status' => 'error', 'content' => $content];
}

echo "<!DOCTYPE html>";
echo "<html><head><title>CSRF Fix</title>";
echo "<style>body{font-family:Arial;margin:20px;background:#f5f5f5;}";
echo ".fixed{color:green;}.protected{color:blue;}.skipped{color:orange;}.error{color:red;}";
echo "pre{background:white;padding:10px;border:1px solid #ddd;overflow-x:auto;max-height:300px;}";
echo ".summary{background:white;padding:15px;margin-top:20px;border-radius:4px;}";
echo "</style></head><body>";
echo "<h1>CSRF Token Batch Fix</h1>";
echo "<p>Processing all modals...</p>";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modalsdir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
        $filepath = $file->getRealPath();
        $relpath = str_replace([$basedir . '\\', $basedir . '/'], '', $filepath);
        
        $stats['total']++;
        
        $result = addcsrf($filepath);
        
        switch ($result['status']) {
            case 'fixed':
                if (file_put_contents($filepath, $result['content'])) {
                    $stats['fixed']++;
                    echo "<div class='fixed'>✓ FIXED: $relpath</div>";
                    $stats['files'][] = ['file' => $relpath, 'status' => 'fixed'];
                } else {
                    $stats['errors']++;
                    echo "<div class='error'>✗ ERROR writing: $relpath</div>";
                    $stats['files'][] = ['file' => $relpath, 'status' => 'write_error'];
                }
                break;
            case 'protected':
                $stats['protected']++;
                echo "<div class='protected'>→ PROTECTED: $relpath</div>";
                break;
            case 'skipped':
                $stats['skipped']++;
                // echo "<div class='skipped'>- SKIPPED: $relpath (no form)</div>";
                break;
            case 'error':
                $stats['errors']++;
                echo "<div class='error'>✗ ERROR: $relpath</div>";
                $stats['files'][] = ['file' => $relpath, 'status' => 'parse_error'];
                break;
        }
    }
}

echo "<div class='summary'>";
echo "<h2>Summary</h2>";
echo "<p><strong>Total files processed:</strong> " . $stats['total'] . "</p>";
echo "<p><strong style='color:green;'>Fixed:</strong> " . $stats['fixed'] . " modals</p>";
echo "<p><strong style='color:blue;'>Already protected:</strong> " . $stats['protected'] . " modals</p>";
echo "<p><strong style='color:orange;'>Skipped (no forms):</strong> " . $stats['skipped'] . " modals</p>";
echo "<p><strong style='color:red;'>Errors:</strong> " . $stats['errors'] . "</p>";

if ($stats['errors'] > 0) {
    echo "<h3>Files with errors:</h3>";
    echo "<pre>";
    foreach ($stats['files'] as $item) {
        if ($item['status'] !== 'fixed') {
            echo $item['file'] . " ({$item['status']})\n";
        }
    }
    echo "</pre>";
}

echo "<hr>";
echo "<p>✅ CSRF protection fix complete!</p>";
echo "<p>All " . $stats['fixed'] . " modal files have been updated with CSRF tokens.</p>";
echo "</div>";
echo "</body></html>";
?>
