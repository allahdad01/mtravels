<?php
/**
 * Seed the name_dictionary table from the current PHP dictionary + custom JSON.
 *
 * Usage:
 *   php scripts/seed_name_dictionary.php          # seed only
 *   php scripts/seed_name_dictionary.php --rebuild # truncate and re-seed
 *
 * Run once after creating the name_dictionary table via the migration SQL.
 * Idempotent: safe to run multiple times (uses INSERT IGNORE).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$rebuild = in_array('--rebuild', $argv ?? []);

if ($rebuild) {
    echo "Rebuild mode: truncating name_dictionary...\n";
    $pdo->exec("TRUNCATE TABLE name_dictionary");
}

// ── Load the canonical PHP dictionary ──────────────────────────────
$phpDict = include __DIR__ . '/../includes/name_dictionary.php';
if (!is_array($phpDict)) {
    $phpDict = [];
}

// ── Load custom JSON overrides ─────────────────────────────────────
$customFile = __DIR__ . '/../includes/custom_name_dictionary.json';
$customDict = [];
if (file_exists($customFile)) {
    $json = json_decode(file_get_contents($customFile), true);
    if (is_array($json)) {
        $customDict = $json;
    }
}

// ── Category detection ─────────────────────────────────────────────
// The PHP dictionary has comment sections. We classify by position.
// These are the section boundaries (approximate) in name_dictionary.php:
$documentKeys = [
    'id original + passport original', 'id original + passport copy',
    'id copy + passport original',     'id copy + passport copy',
    'id original', 'id copy', 'passport original', 'passport copy',
    'passport', 'copy', 'id', 'national id', 'national id card',
    'tazkira', 'tazkera', 'tazkare', 'birth certificate', 'b certificate',
];

$placeKeys = [
    'makkah', 'mecca', 'makkah al mukarramah', 'al madinah',
    'madinah munawwarah', 'madinah', 'medina', 'madina', 'jeddah',
    'jiddah', 'jedda', 'taif', 'riyadh', 'dammam', 'tabuk', 'yanbu',
    'saudi arabia', 'arabia', 'afghanistan', 'kabul', 'kandahar', 'herat',
    'mazar i sharif', 'mazar', 'sharif', 'jalalabad', 'kunduz', 'ghazni',
    'balkh', 'helmand', 'nangarhar', 'parwan', 'panjshir', 'bamyan',
    'badakhshan', 'logar', 'baghlan', 'takhar', 'faryab', 'nimroz',
    'paktia', 'kapisa', 'pakistan', 'islamabad', 'karachi', 'peshawar',
    'quetta', 'lahore', 'khyber', 'iran', 'tehran', 'mashhad', 'dubai',
    'dubayy', 'sharjah', 'abu dhabi', 'united arab emirates', 'qatar',
    'doha', 'kuwait', 'oman', 'muscat', 'bahrain', 'turkey', 'istanbul',
    'egypt', 'cairo', 'jordan', 'lebanon', 'syria', 'iraq', 'india',
    'delhi', 'bangladesh', 'tajikistan', 'malaysia', 'kuala lumpur',
    'china', 'england', 'united kingdom', 'germany', 'france', 'america',
    'usa', 'canada', 'australia', 'europe', 'africa',
    'abu dhabi', 'ajman', 'ras al khaimah', 'fujairah', 'al ain',
    'manama', 'kuwait city', 'isfahan', 'new delhi',
    'maidan wardak', 'maydan wardak', 'badghis', 'ghor', 'daikundi',
    'daykundi', 'uzurghan', 'uruzgan', 'paktika', 'khost', 'kunar',
    'nuristan', 'samangan', 'sar e pul', 'sare pul', 'jawzjan', 'jowzjan',
    'bagram', 'pul e khumri', 'pul khumri',
];

function categorize(string $key, array $documentKeys, array $placeKeys): string
{
    $lower = mb_strtolower(trim($key));
    if (in_array($lower, $documentKeys, true)) {
        return 'document';
    }
    if (in_array($lower, $placeKeys, true)) {
        return 'place';
    }
    // Compound names (contain spaces and are not places/documents)
    if (str_contains($lower, ' ')) {
        return 'compound';
    }
    return 'person';
}

// ── Prepare insert statement ───────────────────────────────────────
$sql = "INSERT INTO name_dictionary (english_name, dari, pashto, category, source, verified)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            dari = IF(VALUES(dari) IS NOT NULL, VALUES(dari), dari),
            pashto = IF(VALUES(pashto) IS NOT NULL, VALUES(pashto), pashto),
            category = VALUES(category),
            source = LEAST(source, VALUES(source)),
            verified = GREATEST(verified, VALUES(verified))";
$stmt = $pdo->prepare($sql);

$inserted = 0;
$skipped = 0;

// ── Insert PHP dictionary entries ──────────────────────────────────
foreach ($phpDict as $english => $value) {
    $key = mb_strtolower(trim($english));
    if ($key === '') {
        continue;
    }

    $dari = null;
    $pashto = null;

    if (is_string($value)) {
        // Same translation for both Dari and Pashto
        $dari = $value;
        $pashto = $value;
    } elseif (is_array($value)) {
        $pashto = $value['ps'] ?? null;
        $dari = $value['fa'] ?? null;
    }

    $category = categorize($key, $documentKeys, $placeKeys);

    $stmt->execute([
        $key,
        $dari,
        $pashto,
        $category,
        'seeded',
        1, // verified
    ]);
    $inserted++;
}

// ── Insert custom JSON entries (source='manual', verified=1) ───────
foreach ($customDict as $english => $value) {
    $key = mb_strtolower(trim($english));
    if ($key === '') {
        continue;
    }

    $dari = null;
    $pashto = null;

    if (is_string($value)) {
        $dari = $value;
        $pashto = $value;
    } elseif (is_array($value)) {
        $pashto = $value['ps'] ?? null;
        $dari = $value['fa'] ?? null;
    }

    $category = categorize($key, $documentKeys, $placeKeys);

    $stmt->execute([
        $key,
        $dari,
        $pashto,
        $category,
        'manual',
        1,
    ]);
    $inserted++;
}

// ── Report ─────────────────────────────────────────────────────────
$count = $pdo->query("SELECT COUNT(*) FROM name_dictionary")->fetchColumn();
echo "Done. Inserted/updated {$inserted} entries. Total in table: {$count}\n";

if ($inserted === 0 && $count > 0) {
    echo "Table already seeded. Use --rebuild to re-seed from scratch.\n";
}
