<?php
/**
 * Automated CSRF Token Fix for All Modals
 * Adds missing CSRF tokens to all modal files
 * 
 * Usage: php fix_csrf_modals.php
 */

$modals_dir = __DIR__ . '/modals';
$csrf_token_pattern = '/<input[^>]*type="hidden"[^>]*name="csrf_token"[^>]*>/i';
$form_pattern = '/<form[^>]*>/i';

$stats = [
    'total_files' => 0,
    'already_protected' => 0,
    'fixed' => 0,
    'skipped' => 0,
    'errors' => []
];

// Recursively scan all modal files
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($modals_dir),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $stats['total_files']++;
    $filepath = $file->getRealPath();
    $content = file_get_contents($filepath);

    // Skip if already has CSRF token
    if (preg_match($csrf_token_pattern, $content)) {
        $stats['already_protected']++;
        echo "✓ Already protected: " . str_replace($modals_dir, 'modals', $filepath) . "\n";
        continue;
    }

    // Skip if no form tag
    if (!preg_match($form_pattern, $content)) {
        $stats['skipped']++;
        echo "- Skipped (no form): " . str_replace($modals_dir, 'modals', $filepath) . "\n";
        continue;
    }

    // Add CSRF token after opening form tag
    $csrf_input = "\n\t\t\t<!-- CSRF Protection -->\n\t\t\t<input type=\"hidden\" name=\"csrf_token\" value=\"<?php echo h(\$_SESSION['csrf_token'] ?? ''); ?>\">";
    
    // Find first form tag and add CSRF token right after it
    $new_content = preg_replace(
        '/(<form[^>]*>)/',
        '$1' . $csrf_input,
        $content,
        1
    );

    // Write updated content
    if (file_put_contents($filepath, $new_content)) {
        $stats['fixed']++;
        echo "✓ Fixed: " . str_replace($modals_dir, 'modals', $filepath) . "\n";
    } else {
        $stats['errors'][] = $filepath;
        echo "✗ Error: " . str_replace($modals_dir, 'modals', $filepath) . "\n";
    }
}

// Display summary
echo "\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "CSRF TOKEN FIX SUMMARY\n";
echo "=" . str_repeat("=", 70) . "\n";
echo "Total files scanned: " . $stats['total_files'] . "\n";
echo "Already protected: " . $stats['already_protected'] . "\n";
echo "Fixed: " . $stats['fixed'] . "\n";
echo "Skipped (no forms): " . $stats['skipped'] . "\n";
echo "Errors: " . count($stats['errors']) . "\n";
echo "=" . str_repeat("=", 70) . "\n";

if (!empty($stats['errors'])) {
    echo "\nFiles with errors:\n";
    foreach ($stats['errors'] as $error) {
        echo "  - " . str_replace($modals_dir, 'modals', $error) . "\n";
    }
}

echo "\nDone! All modals updated with CSRF protection.\n";
?>
