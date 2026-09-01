<?php
/**
 * MTravels Translation Engine — Database-driven hybrid name translation.
 *
 * Lookup order:
 *   1. DB dictionary (name_dictionary table) — verified, instant
 *   2. Gemini API — for unknown names
 *   3. Local transliteration — offline fallback for unusual names
 *
 * Gemini results are auto-saved to the dictionary (auto-learn).
 * Requires: includes/db.php (PDO $pdo), gemini_api_key in platform_settings
 */

if (!defined('GEMINI_API_KEY')) {
    $dbKey = null;
    if (isset($pdo)) {
        try {
            $row = $pdo->query("SELECT `value` FROM platform_settings WHERE `key` = 'gemini_api_key' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['value'] !== '') {
                $dbKey = $row['value'];
            }
        } catch (PDOException $e) {
            // Table may not exist yet, ignore
        }
    }
    if ($dbKey === null) {
        $dbKey = getenv('GEMINI_API_KEY');
        $dbKey = ($dbKey !== false && $dbKey !== '') ? $dbKey : null;
    }
    define('GEMINI_API_KEY', $dbKey ?? '');
}

// ── In-memory dictionary cache (loaded once per request) ───────────
static $_dictCache = null;

function _load_dictionary_cache(): array
{
    global $_dictCache, $pdo;
    if ($_dictCache !== null) {
        return $_dictCache;
    }

    $_dictCache = [];
    try {
        $rows = $pdo->query(
            "SELECT english_name, dari, pashto FROM name_dictionary WHERE dari IS NOT NULL OR pashto IS NOT NULL"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $_dictCache[$row['english_name']] = [
                'fa' => $row['dari'],
                'ps' => $row['pashto'],
            ];
        }
    } catch (PDOException $e) {
        error_log("Translation engine: failed to load dictionary cache: " . $e->getMessage());
    }

    return $_dictCache;
}

// ── Name normalization ─────────────────────────────────────────────

/**
 * Normalize a name for dictionary lookup:
 * - lowercase
 * - strip diacritics (accented Latin chars)
 * - collapse multiple spaces
 * - trim
 */
function normalize_name(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = strtr($text, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'š' => 'sh', 'č' => 'ch', 'ž' => 'zh',
        'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
        'ð' => 'd', 'þ' => 'th',
    ]);
    $text = preg_replace('/\s+/u', ' ', $text);
    return $text;
}

// ── Script detection helpers ───────────────────────────────────────

function is_arabic_script(string $text): bool
{
    return (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $text);
}

function is_latin_script(string $text): bool
{
    return (bool) preg_match('/[A-Za-z]/u', $text);
}

/**
 * Normalize inconsistent Arabic-script letters to Afghan spelling
 * (Persian Yeh, Kaf, final taa marbuta).
 */
function normalize_arabic_text(string $text): string
{
    return strtr($text, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ٱ' => 'ا', 'ة' => 'ه']);
}

// ── DB dictionary lookup ───────────────────────────────────────────

/**
 * Look up a normalized name in the DB dictionary.
 * Returns ['dari' => '...', 'pashto' => '...'] or null.
 */
function lookup_dictionary(string $normalized): ?array
{
    $cache = _load_dictionary_cache();
    return $cache[$normalized] ?? null;
}

/**
 * Look up a single word in the dictionary for a specific language.
 * Returns the script text or null.
 */
function lookup_word_script(string $text, string $targetLang): ?string
{
    $key = normalize_name($text);
    $entry = lookup_dictionary($key);
    if ($entry === null) {
        return null;
    }
    $lang = $targetLang === 'dari' ? 'fa' : $targetLang;
    return $entry[$lang] ?? $entry['ps'] ?? $entry['fa'] ?? null;
}

// ── Gemini AI Translation API ──────────────────────────────────────

/**
 * Validate that a translation result is effective.
 * Rejects: empty, not Arabic script, still contains Latin, same as input,
 * contains punctuation, single-word becoming 3+ word phrase.
 */
function is_effectively_translated(string $original, string $result): bool
{
    $result = trim($result);
    if ($result === '') {
        return false;
    }
    if (!is_arabic_script($result)) {
        return false;
    }
    if (preg_match('/[A-Za-z]/', $result)) {
        return false;
    }
    $normOriginal = normalize_arabic_text(trim($original));
    $normResult = normalize_arabic_text($result);
    if (mb_strtolower($normResult) === mb_strtolower($normOriginal)) {
        return false;
    }
    if (preg_match('/[\p{P}\p{S}]/u', $normResult)) {
        return false;
    }
    if (!preg_match('/\s/u', $original) && preg_match('/\S+\s+\S+\s+\S+/u', $normResult)) {
        return false;
    }
    return true;
}

/**
 * Send a single name to Gemini for translation.
 * Returns translated text or null on failure.
 */
function google_translate_single(string $text, string $targetLang): ?string
{
    $apiKey = GEMINI_API_KEY;
    if ($apiKey === '' || $apiKey === false) {
        return null;
    }

    $langLabel = $targetLang === 'fa' ? 'Dari' : ($targetLang === 'ps' ? 'Pashto' : $targetLang);

    $prompt = "Translate the following English name to {$langLabel} script. "
            . "Return ONLY the translated name, nothing else. "
            . "If it is a proper name, transliterate it naturally. "
            . "Do not add quotes, explanation, or any extra text.\n\n"
            . $text;

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 128,
        ],
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'MTravels-Translation-Engine/2.0',
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $curlError) {
        error_log("Translation engine: Gemini API curl error: {$curlError}");
        return null;
    }

    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? ("HTTP {$httpCode}");
        error_log("Translation engine: Gemini API error: {$msg}");
        return null;
    }

    $data = json_decode($response, true);
    $translated = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

    if ($translated === '') {
        return null;
    }

    // Strip markdown fences if present
    if (strpos($translated, '```') === 0) {
        $translated = preg_replace('/^```(?:\w+)?\s*/i', '', $translated);
        $translated = preg_replace('/\s*```$/', '', $translated);
        $translated = trim($translated);
    }

    $translated = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $translated);

    return $translated === '' ? null : $translated;
}

/**
 * Translate multiple names via Gemini in a single request.
 * Input: array of English names
 * Output: array of [english_name => translated_text]
 */
function google_translate_batch(array $names, string $targetLang): array
{
    $apiKey = GEMINI_API_KEY;
    if ($apiKey === '' || $apiKey === false || empty($names)) {
        return [];
    }

    $langLabel = $targetLang === 'fa' ? 'Dari' : ($targetLang === 'ps' ? 'Pashto' : $targetLang);

    $numberedList = '';
    foreach ($names as $i => $name) {
        $numberedList .= ($i + 1) . ". " . $name . "\n";
    }

    $prompt = "Translate each of the following English names to {$langLabel} script.\n"
            . "Return ONLY a JSON array of translated names in the same order, one per entry.\n"
            . "No explanation, no markdown, just the raw JSON array.\n\n"
            . $numberedList;

    $payload = json_encode([
        'contents' => [
            ['parts' => [['text' => $prompt]]],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 1024,
        ],
    ]);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'MTravels-Translation-Engine/2.0',
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
        error_log("Translation engine: Gemini batch API curl error: {$curlError}");
        return [];
    }

    $data = json_decode($response, true);
    $text = trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');

    if ($text === '') {
        return [];
    }

    // Strip markdown fences if present
    if (strpos($text, '```') === 0) {
        $text = preg_replace('/^```(?:\w+)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);
    }

    $translations = json_decode($text, true);
    if (!is_array($translations)) {
        error_log("Translation engine: Gemini batch API returned invalid JSON: {$text}");
        return [];
    }

    $results = [];
    $keys = array_values($names);
    foreach ($translations as $i => $translated) {
        if (!is_string($translated) || $translated === '') continue;
        $translated = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $translated);
        if ($translated === '') continue;
        $original = $keys[$i] ?? null;
        if ($original !== null && is_effectively_translated($original, $translated)) {
            $results[$original] = normalize_arabic_text($translated);
        }
    }

    return $results;
}

// ── Save learned translations ──────────────────────────────────────

/**
 * Save a Gemini-sourced or manually verified translation to the dictionary.
 * If the entry already exists, increment hit_count.
 */
function save_learned(string $englishName, ?string $dari, ?string $pashto, string $source = 'gemini'): void
{
    global $pdo;
    $normalized = normalize_name($englishName);

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO name_dictionary (english_name, dari, pashto, category, source, verified, hit_count)
             VALUES (?, ?, ?, 'person', ?, 0, 1)
             ON DUPLICATE KEY UPDATE
                dari = COALESCE(VALUES(dari), dari),
                pashto = COALESCE(VALUES(pashto), pashto),
                hit_count = hit_count + 1,
                source = IF(VALUES(source) = 'manual', 'manual', source)"
        );
        $stmt->execute([$normalized, $dari, $pashto, $source]);
    } catch (PDOException $e) {
        error_log("Translation engine: save_learned failed: " . $e->getMessage());
    }

    // Update in-memory cache
    global $_dictCache;
    if (is_array($_dictCache)) {
        $_dictCache[$normalized] = [
            'fa' => $dari ?? ($_dictCache[$normalized]['fa'] ?? null),
            'ps' => $pashto ?? ($_dictCache[$normalized]['ps'] ?? null),
        ];
    }
}

/**
 * Increment hit count for a dictionary entry.
 */
function touch_dictionary_entry(string $englishName): void
{
    global $pdo;
    $normalized = normalize_name($englishName);
    try {
        $stmt = $pdo->prepare(
            "UPDATE name_dictionary SET hit_count = hit_count + 1 WHERE english_name = ?"
        );
        $stmt->execute([$normalized]);
    } catch (PDOException $e) {
        // Non-critical, ignore
    }
}

// ── Core translation functions ─────────────────────────────────────

/**
 * Offline transliteration (last-resort fallback).
 * Converts Latin characters to Arabic-script equivalents.
 */
function transliterate_to_arabic(string $text, string $targetLang): string
{
    static $cache = [];
    $key = $targetLang . '|' . $text;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $text = trim($text);
    if ($text === '' || preg_match('/[0-9]/', $text)) {
        return $text;
    }
    if (is_arabic_script($text)) {
        return normalize_arabic_text($text);
    }

    $text = strtr(mb_strtolower($text), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'š' => 'sh', 'č' => 'ch', 'ž' => 'zh',
        'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ð' => 'd', 'þ' => 'th',
    ]);

    $gLetter = ($targetLang === 'ps') ? 'ګ' : 'گ';

    $rules = [
        'sh' => 'ش', 'ch' => 'چ', 'gh' => 'غ', 'zh' => 'ژ', 'ph' => 'ف',
        'th' => 'ث', 'kh' => 'خ', 'aa' => 'آ', 'oo' => 'و',
        'ee' => 'ی', 'ou' => 'و', 'au' => 'و', 'ai' => 'ی',
        'ay' => 'ی', 'ey' => 'ی', 'aw' => 'و', 'ow' => 'و',
        'a' => 'ا', 'b' => 'ب', 'p' => 'پ', 't' => 'ت', 's' => 'س',
        'j' => 'ج', 'k' => 'ک', 'd' => 'د', 'r' => 'ر', 'z' => 'ز',
        'f' => 'ف', 'q' => 'ق', 'l' => 'ل', 'm' => 'م', 'n' => 'ن',
        'w' => 'و', 'y' => 'ی', 'e' => 'ی', 'i' => 'ی', 'o' => 'و',
        'u' => 'و', 'v' => 'و', 'x' => 'خ', 'c' => 'ک', 'g' => $gLetter,
        'h' => 'ح',
    ];

    $words = preg_split('/\s+/u', $text);
    $out = [];
    foreach ($words as $word) {
        $trailingAh = '';
        if (preg_match('/ah$/u', $word)) {
            $word = substr($word, 0, -2);
            $trailingAh = 'ه';
        }
        $result = '';
        $len = strlen($word);
        for ($i = 0; $i < $len; $i++) {
            $two = substr($word, $i, 2);
            if (isset($rules[$two])) {
                $char = $rules[$two];
                $i++;
            } else {
                $one = substr($word, $i, 1);
                $char = $rules[$one] ?? '';
            }
            if ($char === '') {
                continue;
            }
            if ($char === 'ح') {
                $next = ($i + 1 < $len) ? substr($word, $i + 1, 1) : '';
                if ($next === '' || !in_array($next, ['a', 'e', 'i', 'o', 'u'])) {
                    $char = 'ه';
                }
            }
            if (mb_substr($result, -1) !== $char) {
                $result .= $char;
            }
        }
        if ($trailingAh !== '' && $result !== '') {
            $result .= $trailingAh;
        }
        if ($result !== '') {
            $out[] = $result;
        }
    }

    $joined = implode(' ', $out);
    return $cache[$key] = ($joined !== '' ? $joined : $text);
}

// ── Main translation function ──────────────────────────────────────

/**
 * Translate a name into the target document language.
 *
 * Languages: 'en', 'ps' (Pashto), 'fa' (Dari/Farsi), 'dari' (alias for 'fa').
 *
 * Lookup order for ps/fa targets:
 *   1. DB dictionary (verified, instant, no network)
 *   2. Google Cloud Translation API (for unknown names)
 *   3. Local transliteration (offline fallback)
 *
 * Returns the original text if translation is not needed or fails.
 */
function translate_name(string $text, string $targetLang): string
{
    static $cache = [];

    $text = trim($text);
    if ($text === '' || mb_strlen($text) < 2) {
        return $text;
    }

    $targetLang = $targetLang === 'dari' ? 'fa' : $targetLang;
    $cacheKey = $targetLang . '|' . $text;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // English target: only translate Arabic → English
    if ($targetLang === 'en') {
        if (is_latin_script($text)) {
            return $cache[$cacheKey] = $text;
        }
        if (!is_arabic_script($text)) {
            return $cache[$cacheKey] = $text;
        }
        // Try Gemini for Arabic → English
        $result = google_translate_single($text, 'en');
        if ($result !== null && is_effectively_translated($text, $result)) {
            return $cache[$cacheKey] = $result;
        }
        return $cache[$cacheKey] = $text;
    }

    if (!in_array($targetLang, ['ps', 'fa'])) {
        return $cache[$cacheKey] = $text;
    }

    // Already in Arabic script — normalize only
    if (is_arabic_script($text)) {
        return $cache[$cacheKey] = normalize_arabic_text($text);
    }

    $normalized = normalize_name($text);

    // 1) DB dictionary — whole-name match
    $entry = lookup_dictionary($normalized);
    if ($entry !== null) {
        $translation = $entry[$targetLang] ?? $entry['ps'] ?? $entry['fa'] ?? null;
        if ($translation !== null) {
            touch_dictionary_entry($normalized);
            return $cache[$cacheKey] = normalize_arabic_text($translation);
        }
    }

    // 2) Word-by-word dictionary lookup
    $tokens = preg_split('/\s+/u', $text);
    $mapped = [];
    $allKnown = true;
    foreach ($tokens as $token) {
        $word = lookup_word_script($token, $targetLang);
        if ($word !== null) {
            $mapped[] = $word;
        } else {
            $mapped[] = null;
            $allKnown = false;
        }
    }
    if ($allKnown) {
        $result = implode(' ', $mapped);
        touch_dictionary_entry($normalized);
        return $cache[$cacheKey] = $result;
    }

    // 3) ALL-CAPS names: dict + transliteration
    if (!preg_match('/[a-z]/', $text)) {
        $resolved = [];
        foreach ($tokens as $i => $token) {
            $resolved[] = $mapped[$i] !== null ? $mapped[$i] : transliterate_to_arabic($token, $targetLang);
        }
        $joined = implode(' ', array_filter($resolved, fn($v) => $v !== ''));
        if ($joined !== '') {
            return $cache[$cacheKey] = $joined;
        }
    }

    // 4) Gemini API — single name
    $result = google_translate_single($text, $targetLang);
    if ($result !== null && is_effectively_translated($text, $result)) {
        $result = normalize_arabic_text($result);
        // Auto-learn: save to dictionary
        $dari = $targetLang === 'fa' ? $result : null;
        $pashto = $targetLang === 'ps' ? $result : null;
        save_learned($text, $dari, $pashto, 'gemini');
        return $cache[$cacheKey] = $result;
    }

    // 5) Try sister language as fallback
    $sister = ($targetLang === 'ps') ? 'fa' : 'ps';
    $alt = google_translate_single($text, $sister);
    if ($alt !== null && is_effectively_translated($text, $alt)) {
        $alt = normalize_arabic_text($alt);
        $dari = $sister === 'fa' ? $alt : null;
        $pashto = $sister === 'ps' ? $alt : null;
        save_learned($text, $dari, $pashto, 'gemini');
        return $cache[$cacheKey] = $alt;
    }

    // 6) Final fallback: per-word dictionary + local transliteration
    $resolved = [];
    foreach ($tokens as $i => $token) {
        $resolved[] = $mapped[$i] !== null ? $mapped[$i] : transliterate_to_arabic($token, $targetLang);
    }
    $joined = implode(' ', array_filter($resolved, fn($v) => $v !== ''));
    return $cache[$cacheKey] = ($joined !== '' ? $joined : $text);
}

/**
 * Translate a place name — thin wrapper around translate_name().
 * Place names live in the same DB dictionary under category='place'.
 */
function translate_place(string $text, string $targetLang): string
{
    return translate_name($text, $targetLang);
}

/**
 * Translate specific name fields in a row or rows array in-place.
 * $data can be a single assoc row or a list of rows.
 */
function translate_name_fields(&$data, string $lang, array $fields): void
{
    if (empty($data)) {
        return;
    }

    $isList = array_keys($data) === range(0, count($data) - 1);
    $rows = $isList ? $data : [$data];

    foreach ($rows as &$row) {
        foreach ($fields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = translate_name($row[$field], $lang);
            }
        }
    }
    unset($row);

    $data = $isList ? $rows : $rows[0];
}

/**
 * Translate multiple names in batch (optimized for reports).
 * Deduplicates names, does one DB lookup, one Google call for unknowns.
 *
 * Returns map: [original_name => translated_name]
 */
function translate_name_batch(array $names, string $targetLang): array
{
    if (empty($names)) {
        return [];
    }

    $targetLang = $targetLang === 'dari' ? 'fa' : $targetLang;
    $results = [];
    $unknown = [];

    // Deduplicate and normalize
    $unique = [];
    foreach ($names as $original) {
        $norm = normalize_name($original);
        if ($norm === '' || mb_strlen($norm) < 2) {
            $results[$original] = $original;
            continue;
        }
        if (!isset($unique[$norm])) {
            $unique[$norm] = $original;
        }
    }

    // 1) DB dictionary lookup for all unique names
    $cache = _load_dictionary_cache();
    foreach ($unique as $norm => $original) {
        if (is_arabic_script($original)) {
            $results[$original] = normalize_arabic_text($original);
            continue;
        }

        $entry = $cache[$norm] ?? null;
        if ($entry !== null) {
            $translation = $entry[$targetLang] ?? $entry['ps'] ?? $entry['fa'] ?? null;
            if ($translation !== null) {
                $results[$original] = normalize_arabic_text($translation);
                touch_dictionary_entry($norm);
                continue;
            }
        }
        $unknown[$norm] = $original;
    }

    // 2) Gemini batch call for unknowns
    if (!empty($unknown) && GEMINI_API_KEY !== '' && GEMINI_API_KEY !== false) {
        $unknownNames = array_values($unknown);
        $googleResults = google_translate_batch($unknownNames, $targetLang);

        foreach ($unknown as $norm => $original) {
            if (isset($googleResults[$original])) {
                $translated = $googleResults[$original];
                $results[$original] = $translated;
                // Auto-learn
                $dari = $targetLang === 'fa' ? $translated : null;
                $pashto = $targetLang === 'ps' ? $translated : null;
                save_learned($original, $dari, $pashto, 'gemini');
            } else {
                // Fallback: word-by-word dict + transliteration
                $tokens = preg_split('/\s+/u', $original);
                $resolved = [];
                foreach ($tokens as $token) {
                    $word = lookup_word_script($token, $targetLang);
                    $resolved[] = $word !== null ? $word : transliterate_to_arabic($token, $targetLang);
                }
                $joined = implode(' ', array_filter($resolved, fn($v) => $v !== ''));
                $results[$original] = ($joined !== '' ? $joined : $original);
            }
        }
    } elseif (!empty($unknown)) {
        // No Gemini API key: use transliteration only
        foreach ($unknown as $norm => $original) {
            $tokens = preg_split('/\s+/u', $original);
            $resolved = [];
            foreach ($tokens as $token) {
                $word = lookup_word_script($token, $targetLang);
                $resolved[] = $word !== null ? $word : transliterate_to_arabic($token, $targetLang);
            }
            $joined = implode(' ', array_filter($resolved, fn($v) => $v !== ''));
            $results[$original] = ($joined !== '' ? $joined : $original);
        }
    }

    return $results;
}
