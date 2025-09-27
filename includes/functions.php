<?php
/**
 * Get application settings
 * @param mysqli $conection_db Database connection
 * @return array|null Settings array or null if not found
 */
function getSettings($conection_db) {
    return getSettingsMysqli($conection_db);
}
?> 