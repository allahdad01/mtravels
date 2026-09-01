<?php
/**
 * Move temp uploaded files (passport + photo) to final location.
 * Called when a member is saved to the database.
 *
 * @param PDO   $pdo       Database connection
 * @param int   $tenantId  Tenant ID
 * @param int   $branchId  Branch ID
 * @param int   $familyId  Family ID
 * @param string $passportPath  Temp passport path (e.g. /uploads/temp/passport_document_xxx.jpg)
 * @param string $photoPath     Temp photo path (e.g. /uploads/temp/passport_photo_xxx.jpg)
 * @return array  ['passport_path' => ..., 'photo_path' => ...]  Final paths
 */
function move_temp_files_to_final($pdo, $tenantId, $branchId, $familyId, $passportPath, $photoPath)
{
    $uploadBase = __DIR__ . '/../uploads';
    $finalDir = $uploadBase . '/' . $tenantId . '/' . $branchId . '/umrah/' . $familyId . '/';

    if (!is_dir($finalDir)) {
        @mkdir($finalDir, 0755, true);
    }

    $result = ['passport_path' => null, 'photo_path' => null];

    // Move passport document
    if ($passportPath && strpos($passportPath, '/uploads/temp/') === 0) {
        $filename = basename($passportPath);
        $oldFile = $uploadBase . '/temp/' . $filename;
        $newFile = $finalDir . $filename;
        if (file_exists($oldFile)) {
            rename($oldFile, $newFile);
            $result['passport_path'] = '/uploads/' . $tenantId . '/' . $branchId . '/umrah/' . $familyId . '/' . $filename;
        }
    } elseif ($passportPath) {
        $result['passport_path'] = $passportPath;
    }

    // Move photo
    if ($photoPath && strpos($photoPath, '/uploads/temp/') === 0) {
        $filename = basename($photoPath);
        $oldFile = $uploadBase . '/temp/' . $filename;
        $newFile = $finalDir . $filename;
        if (file_exists($oldFile)) {
            rename($oldFile, $newFile);
            $result['photo_path'] = '/uploads/' . $tenantId . '/' . $branchId . '/umrah/' . $familyId . '/' . $filename;
        }
    } elseif ($photoPath) {
        $result['photo_path'] = $photoPath;
    }

    return $result;
}

/**
 * Delete temp files (called when user cancels adding a member).
 *
 * @param string $passportPath  Temp passport path
 * @param string $photoPath     Temp photo path
 */
function delete_temp_files($passportPath, $photoPath)
{
    $uploadBase = __DIR__ . '/../uploads';

    foreach ([$passportPath, $photoPath] as $path) {
        if ($path && strpos($path, '/uploads/temp/') === 0) {
            $file = $uploadBase . '/temp/' . basename($path);
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
