<?php
/**
 * Free document name translation helper using MyMemory API
 * (https://mymemory.translated.net - free, no API key required)
 *
 * Anonymous quota: ~5000 chars/day.
 * To raise the quota to ~50000 chars/day, define MYMEMORY_EMAIL in config.php
 * (a registered MyMemory email address).
 */

if (!defined('MYMEMORY_EMAIL')) {
    define('MYMEMORY_EMAIL', '');
}

function is_arabic_script($text) {
    return (bool)preg_match('/[\x{0600}-\x{06FF}]/u', (string)$text);
}

function is_latin_script($text) {
    return (bool)preg_match('/[A-Za-z]/u', (string)$text);
}

function mymemory_translate($text, $langpair) {
    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($text)
         . '&langpair=' . rawurlencode($langpair);
    if (MYMEMORY_EMAIL !== '') {
        $url .= '&de=' . rawurlencode(MYMEMORY_EMAIL);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'MTravels-Document-Translator/1.0',
    ]);
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['responseStatus']) || (int)$data['responseStatus'] !== 200) {
        return null;
    }

    $translated = isset($data['responseData']['translatedText']) ? $data['responseData']['translatedText'] : null;
    if ($translated === null) {
        return null;
    }

    $translated = trim(html_entity_decode($translated, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // Strip punctuation/quotes MyMemory sometimes wraps around results
    // (e.g. ". محمود." or "هوټل:")
    $translated = preg_replace('/^[\p{P}\p{S}\s]+|[\p{P}\p{S}\s]+$/u', '', $translated);
    return $translated === '' ? null : $translated;
}

/**
 * True when a translation actually changed the text into Arabic script
 * (Pashto/Dari engine sometimes echoes the same English text back unchanged).
 */
function is_effectively_translated($text, $result) {
    $text = normalize_arabic_text(trim((string)$text));
    $result = normalize_arabic_text(trim((string)$result));
    if ($result === '') {
        return false;
    }
    if (!is_arabic_script($result)) {
        return false;
    }
    // Reject partial translations that still contain Latin letters
    if (preg_match('/[A-Za-z]/', $result)) {
        return false;
    }
    if (mb_strtolower($result) === mb_strtolower($text)) {
        return false;
    }
    // Reject results that still contain punctuation/symbols
    // (MyMemory artifacts such as "هوټل:" or ". محمود.")
    if (preg_match('/[\p{P}\p{S}]/u', $result)) {
        return false;
    }
    // A single-word name must never become a 3+ word phrase
    // (e.g. "Asma" -> "لکه څنګه چې" which means "as/like")
    if (!preg_match('/\s/u', $text) && preg_match('/\S+\s+\S+\s+\S+/u', $result)) {
        return false;
    }
    return true;
}

/**
 * Deterministic English/Latin -> Pashto/Dari script transliteration.
 * Used as a last-resort fallback when the dictionary and the free API
 * cannot translate a name. Preserves word boundaries.
 */
function transliterate_to_arabic($text, $targetLang) {
    static $cache = [];
    $key = $targetLang . '|' . $text;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }
    if (preg_match('/[0-9]/', $text)) {
        return $text;
    }
    if (is_arabic_script($text)) {
        return normalize_arabic_text($text);
    }

    // Strip Latin diacritics (e.g. "Mariamé" -> "Mariame")
    $text = strtr(mb_strtolower($text), [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'š' => 'sh', 'č' => 'ch', 'ž' => 'zh',
        'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe', 'ð' => 'd', 'þ' => 'th',
    ]);

    // 'g' -> Pashto 'ګ' vs Dari 'گ'
    $gLetter = ($targetLang === 'ps') ? 'ګ' : 'گ';

    // Multi-character and single-character rules, longest first
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
        // Trailing "ah" is pronounced as a silent -a (e.g. Fatimah)
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
                $char = isset($rules[$one]) ? $rules[$one] : '';
            }
            if ($char === '') {
                continue;
            }
            // 'h' before a vowel is usually ح, otherwise ه
            if ($char === 'ح') {
                $next = ($i + 1 < $len) ? substr($word, $i + 1, 1) : '';
                if ($next === '' || !in_array($next, ['a', 'e', 'i', 'o', 'u'])) {
                    $char = 'ه';
                }
            }
            // Collapse consecutive identical letters (e.g. "mm" -> one م)
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

/**
 * Normalize inconsistent Arabic-script letters to the Afghan spelling
 * (Persian Yeh, Kaf and final taa marbuta as used in Pashto/Dari documents).
 */
function normalize_arabic_text($text) {
    return strtr((string)$text, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ٱ' => 'ا', 'ة' => 'ه']);
}

/**
 * Load the canonical Pashto/Dari name dictionary, merged with any
 * user-provided custom_name_dictionary.json in the same directory.
 */
function load_name_dictionary() {
    static $dict = null;
    if ($dict !== null) {
        return $dict;
    }
    $dict = include __DIR__ . '/name_dictionary.php';
    if (!is_array($dict)) {
        $dict = [];
    }
    $custom = __DIR__ . '/custom_name_dictionary.json';
    if (file_exists($custom)) {
        $json = json_decode(file_get_contents($custom), true);
        if (is_array($json)) {
            foreach ($json as $english => $value) {
                $dict[mb_strtolower(trim($english))] = $value;
            }
        }
    }
    return $dict;
}

/**
 * Persistent translation cache (includes/translation_cache.json).
 * Successful API results are saved here so repeated documents never
 * call the free API twice for the same name (faster + saves quota).
 */
function translation_cache_file() {
    return __DIR__ . '/translation_cache.json';
}

function &translation_cache_ref() {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $file = translation_cache_file();
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $data = ($raw !== false) ? json_decode($raw, true) : null;
            if (is_array($data)) {
                $cache = $data;
            }
        }
    }
    return $cache;
}

function load_translation_cache() {
    return translation_cache_ref();
}

function save_translation_cache() {
    $cache = &translation_cache_ref();
    if (empty($cache)) {
        return;
    }
    // Keep the file bounded: drop the oldest entries beyond 2500
    if (count($cache) > 2500) {
        uasort($cache, function ($a, $b) {
            return (int)($a['ts'] ?? 0) <=> (int)($b['ts'] ?? 0);
        });
        $cache = array_slice($cache, -2500, null, true);
    }
    @file_put_contents(translation_cache_file(), json_encode($cache, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function translation_cache_store($key, $value) {
    $cache = &translation_cache_ref();
    $cache[$key] = ['t' => $value, 'ts' => time()];
    if (!isset($GLOBALS['__translation_cache_shutdown'])) {
        $GLOBALS['__translation_cache_shutdown'] = true;
        register_shutdown_function('save_translation_cache');
    }
}

/**
 * Look up a whole name (or single word) in the dictionary.
 * Returns the Pashto/Dari script for the requested language or null.
 */
function lookup_name_script($text, $targetLang) {
    $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    $dict = load_name_dictionary();
    if (!isset($dict[$key])) {
        // Try without spaces so "Abdul Hadi" matches the key "abdulhadi"
        $joined = preg_replace('/\s+/u', '', $key);
        if ($joined !== $key && isset($dict[$joined])) {
            $key = $joined;
        } else {
            return null;
        }
    }
    $value = $dict[$key];
    if (is_string($value)) {
        return $value;
    }
    return $value[$targetLang] ?? $value['ps'] ?? $value['fa'] ?? null;
}

/**
 * Transliterate/translate a person name into the target document language.
 * Languages: 'en', 'ps' (Pashto), 'fa' (Dari/Farsi).
 *
 * Lookup order for ps/fa targets:
 *   1. canonical name dictionary (most accurate, no network needed)
 *   2. MyMemory free API (with validation + Dari fallback for Pashto)
 *   3. local transliteration (offline, for unusual names)
 *
 * Names already in Arabic script are left untouched for ps/fa targets.
 * Returns the original text if translation is not needed or fails.
 */
function translate_name($text, $targetLang) {
    static $cache = [];

    $text = trim((string)$text);
    if ($text === '') {
        return $text;
    }
    if (mb_strlen($text) < 2) {
        return $text;
    }

    $targetLang = $targetLang === 'dari' ? 'fa' : $targetLang;
    $cacheKey = $targetLang . '|' . $text;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Persistent cache (saved API results from previous documents)
    $stored = load_translation_cache();
    if (isset($stored[$cacheKey])) {
        return $cache[$cacheKey] = $stored[$cacheKey]['t'];
    }

    if ($targetLang === 'en') {
        // English documents only need names that are not already Latin script
        if (is_latin_script($text)) {
            return $cache[$cacheKey] = $text;
        }
        if (!is_arabic_script($text)) {
            return $cache[$cacheKey] = $text;
        }
        foreach (['ps', 'fa'] as $src) {
            $result = mymemory_translate($text, $src . '|en');
            if (is_effectively_translated($text, $result)) {
                translation_cache_store($cacheKey, $result);
                return $cache[$cacheKey] = $result;
            }
        }
        return $cache[$cacheKey] = $text;
    }

    if (!in_array($targetLang, ['ps', 'fa'])) {
        return $cache[$cacheKey] = $text;
    }

    // Already in Arabic script (Pashto/Dari) - normalize spelling only
    if (is_arabic_script($text)) {
        return $cache[$cacheKey] = normalize_arabic_text($text);
    }

    // 1) Canonical dictionary - whole-name match
    $dictHit = lookup_name_script($text, $targetLang);
    if ($dictHit !== null) {
        return $cache[$cacheKey] = normalize_arabic_text($dictHit);
    }

    // 2) Word-by-word dictionary match (e.g. "Mohammad Rahim Khan")
    $tokens = preg_split('/\s+/u', trim($text));
    $mapped = [];
    $allKnown = true;
    foreach ($tokens as $token) {
        $word = lookup_name_script($token, $targetLang);
        if ($word !== null) {
            $mapped[] = $word;
        } else {
            $mapped[] = null;
            $allKnown = false;
        }
    }
    if ($allKnown) {
        return $cache[$cacheKey] = implode(' ', $mapped);
    }

    // ALL-CAPS names (e.g. "HAMEED") are stored uppercase; the free API
    // often matches them to dictionary words - use dict + transliteration
    if (!preg_match('/[a-z]/', $text)) {
        $resolved = [];
        foreach ($tokens as $i => $token) {
            $resolved[] = $mapped[$i] !== null ? $mapped[$i] : transliterate_to_arabic($token, $targetLang);
        }
        $joined = implode(' ', array_filter($resolved, function ($v) { return $v !== ''; }));
        return $cache[$cacheKey] = ($joined !== '' ? $joined : $text);
    }

    // 3) Free API, with validation and Dari fallback for Pashto
    $result = mymemory_translate($text, 'en|' . $targetLang);
    if (is_effectively_translated($text, $result)) {
        $result = normalize_arabic_text($result);
        translation_cache_store($cacheKey, $result);
        return $cache[$cacheKey] = $result;
    }
    $sister = ($targetLang === 'ps') ? 'fa' : 'ps';
    $alt = mymemory_translate($text, 'en|' . $sister);
    if (is_effectively_translated($text, $alt)) {
        $alt = normalize_arabic_text($alt);
        translation_cache_store($cacheKey, $alt);
        return $cache[$cacheKey] = $alt;
    }

    // 4) Final fallback: per-word dictionary + local transliteration
    $resolved = [];
    foreach ($tokens as $i => $token) {
        $resolved[] = $mapped[$i] !== null ? $mapped[$i] : transliterate_to_arabic($token, $targetLang);
    }
    $joined = implode(' ', array_filter($resolved, function ($v) { return $v !== ''; }));
    return $cache[$cacheKey] = ($joined !== '' ? $joined : $text);
}

/**
 * Translate a place name (city, country, airport, etc.) - thin wrapper
 * around translate_name(); place names live in the same dictionary
 * (name_dictionary.php) under the "Places" section.
 */
function translate_place($text, $targetLang) {
    return translate_name($text, $targetLang);
}

/**
 * Translate the given name fields in a row/rows array in place.
 * $data can be a single assoc row or a list of rows.
 */
function translate_name_fields(&$data, $lang, array $fields) {
    if (empty($data)) {
        return;
    }
    // Normalize to list of rows
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
