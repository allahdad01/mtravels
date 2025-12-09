<?php
/**
 * Batch CSRF Token Fix for All Modals
 * Fixes all modals that are missing CSRF tokens
 */

set_time_limit(300);
$modals_dir = __DIR__ . '/modals';
$files_fixed = 0;
$files_protected = 0;
$files_skipped = 0;
$files_error = 0;

function fix_modal($filepath) {
    $content = file_get_contents($filepath);
    
    // Check if already has CSRF token
    if (preg_match('/<input[^>]*type=["\']hidden[""][^>]*name=["\']csrf_token/', $content)) {
        return 'protected';
    }
    
    // Check if has form tag
    if (!preg_match('/<form[^>]*>/i', $content)) {
        return 'skipped';
    }
    
    // Add CSRF token after first form opening tag
    $csrf_input = "\n                    <!-- CSRF Protection -->\n                    <input type=\"hidden\" name=\"csrf_token\" value=\"<?php echo h(\$_SESSION['csrf_token'] ?? ''); ?>\">";
    
    $new_content = preg_replace(
        '/<form([^>]*)>/i',
        '<form$1>' . $csrf_input,
        $content,
        1
    );
    
    if (file_put_contents($filepath, $new_content)) {
        return 'fixed';
    }
    return 'error';
}

echo "Starting CSRF token batch fix...\n\n";

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modals_dir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $filepath = $file->getRealPath();
        $relpath = str_replace($modals_dir . '\\', 'modals/', $filepath);
        $relpath = str_replace($modals_dir . '/', 'modals/', $relpath);
        
        $result = fix_modal($filepath);
        
        switch ($result) {
            case 'fixed':
                $files_fixed++;
                echo "✓ FIXED: $relpath\n";
                break;
            case 'protected':
                $files_protected++;
                echo "  PROTECTED: $relpath\n";
                break;
            case 'skipped':
                $files_skipped++;
                echo "  SKIPPED: $relpath (no form)\n";
                break;
            case 'error':
                $files_error++;
                echo "✗ ERROR: $relpath\n";
                break;
        }
    }
}

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "CSRF BATCH FIX SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "Fixed: $files_fixed modals\n";
echo "Already protected: $files_protected modals\n";
echo "Skipped (no forms): $files_skipped modals\n";
echo "Errors: $files_error modals\n";
echo str_repeat("=", 70) . "\n";
echo "\nDone! All modals have been processed.\n";
?>
