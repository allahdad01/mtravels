<?php
/**
 * Gemini AI Passport Extraction
 * Sends passport images to Google Gemini 2.5 Flash for structured data extraction.
 * Falls back gracefully if the API key is missing or the request fails.
 */

/**
 * Load the Gemini API key from platform_settings.
 */
function getGeminiApiKey() {
    static $key = null;
    if ($key !== null) return $key;
    try {
        // Use existing $pdo if available, otherwise create a quick connection
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            require_once dirname(__DIR__) . '/config.php';
            $dsn = "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        $stmt = $pdo->prepare("SELECT `value` FROM platform_settings WHERE `key` = 'gemini_api_key' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();
        $key = $row ? trim($row['value']) : '';
    } catch (Exception $e) {
        $key = '';
    }
    return $key;
}

/**
 * Send a passport image to Gemini and extract structured data.
 *
 * @param string $imagePath  Absolute path to the image file
 * @param string $mimeType   MIME type (image/jpeg, image/png)
 * @return array{success: bool, data: ?array, raw: ?string, error: ?string}
 */
function extractPassportWithGemini($imagePath, $mimeType = 'image/jpeg') {
    $apiKey = getGeminiApiKey();
    if (empty($apiKey)) {
        return ['success' => false, 'data' => null, 'raw' => null, 'error' => 'No Gemini API key configured'];
    }

    $imageData = @file_get_contents($imagePath);
    if ($imageData === false) {
        return ['success' => false, 'data' => null, 'raw' => null, 'error' => 'Could not read image file'];
    }
    $base64 = base64_encode($imageData);

    $prompt = <<<'PROMPT'
You are a passport document extraction system.

Analyze the passport image carefully.

Extract ONLY information that is actually visible in the passport.
Do NOT guess or infer missing information.
If a field cannot be read clearly, return null.

Return ONLY valid JSON, no markdown, no explanation.

Extract these fields:
- passport_number
- surname
- given_names
- name_in_script (the full name in Arabic/Pashto/Dari script — must be in the SAME ORDER as given_names then surname, NOT surname first)
- nationality
- date_of_birth (YYYY-MM-DD)
- gender (M or F)
- date_of_issue (YYYY-MM-DD)
- date_of_expiry (YYYY-MM-DD)
- place_of_birth
- father_name
- occupation

For MRZ lines if visible:
- mrz_line1
- mrz_line2

Return JSON:
{
  "passport_number": null,
  "surname": null,
  "given_names": null,
  "name_in_script": null,
  "nationality": null,
  "date_of_birth": null,
  "gender": null,
  "date_of_issue": null,
  "date_of_expiry": null,
  "place_of_birth": null,
  "father_name": null,
  "occupation": null,
  "mrz_line1": null,
  "mrz_line2": null
}
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    ['inline_data' => ['mime_type' => $mimeType, 'data' => $base64]],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 1024,
            'responseMimeType' => 'application/json',
        ],
    ];

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'data' => null, 'raw' => null, 'error' => 'cURL error: ' . $curlError];
    }

    if ($httpCode !== 200) {
        $decoded = json_decode($response, true);
        $msg = $decoded['error']['message'] ?? ("HTTP $httpCode");
        return ['success' => false, 'data' => null, 'raw' => $response, 'error' => 'Gemini API error: ' . $msg];
    }

    $decoded = json_decode($response, true);
    $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (empty($text)) {
        return ['success' => false, 'data' => null, 'raw' => $response, 'error' => 'Empty response from Gemini'];
    }

    // Strip markdown fences if present
    $text = trim($text);
    if (strpos($text, '```') === 0) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
    }

    $data = json_decode(trim($text), true);
    if (!is_array($data)) {
        return ['success' => false, 'data' => null, 'raw' => $text, 'error' => 'Invalid JSON from Gemini'];
    }

    // Normalize: ensure all expected keys exist
    $fields = ['passport_number','surname','given_names','name_in_script','nationality','date_of_birth',
               'gender','date_of_issue','date_of_expiry','place_of_birth','father_name',
               'occupation','mrz_line1','mrz_line2'];
    $normalised = [];
    foreach ($fields as $f) {
        $normalised[$f] = $data[$f] ?? null;
    }

    return ['success' => true, 'data' => $normalised, 'raw' => $text, 'error' => null];
}

/**
 * Cross-validate Gemini results against MRZ data.
 * Returns a summary of matches, conflicts, and confidence level.
 *
 * @param array $gemini  Extracted data from Gemini
 * @param array $mrz     Parsed data from MRZ (from parseMRZLines / extractPassportData)
 * @return array{confidence: string, matches: array, conflicts: array}
 */
function crossValidatePassport($gemini, $mrz) {
    $matches = [];
    $conflicts = [];

    $checks = [
        'passport_number' => ['Passport number'],
        'date_of_birth'   => ['Date of birth'],
        'date_of_expiry'  => ['Expiry date'],
    ];

    foreach ($checks as $field => $labelArr) {
        $label = $labelArr[0];
        $gVal = strtoupper(trim($gemini[$field] ?? ''));
        $mVal = strtoupper(trim($mrz[$field] ?? ''));
        if ($gVal === '' || $mVal === '') continue;
        if ($gVal === $mVal) {
            $matches[] = "$label: $gVal";
        } else {
            $conflicts[] = "$label — Gemini: $gVal, MRZ: $mVal";
        }
    }

    // Gender: normalise M/Male, F/Female
    $gGender = strtoupper(trim($gemini['gender'] ?? ''));
    $mGender = strtoupper(trim($mrz['gender'] ?? ''));
    if ($gGender !== '' && $mGender !== '') {
        $gNorm = ($gGender === 'MALE') ? 'M' : (($gGender === 'FEMALE') ? 'F' : $gGender);
        $mNorm = ($mGender === 'MALE') ? 'M' : (($mGender === 'FEMALE') ? 'F' : $mGender);
        if ($gNorm === $mNorm) $matches[] = "Gender: $mNorm";
        else $conflicts[] = "Gender — Gemini: $gGender, MRZ: $mGender";
    }

    // Nationality: normalise AFGHAN/AFG
    $gNat = strtoupper(trim($gemini['nationality'] ?? ''));
    $mNat = strtoupper(trim($mrz['nationality'] ?? ''));
    if ($gNat !== '' && $mNat !== '') {
        $gNatNorm = ($gNat === 'AFGHAN') ? 'AFG' : $gNat;
        if ($gNatNorm === $mNat) $matches[] = "Nationality: $mNat";
        else $conflicts[] = "Nationality — Gemini: $gNat, MRZ: $mNat";
    }

    // Name comparison (more lenient — allow partial match)
    $gName = strtoupper(trim(($gemini['surname'] ?? '') . ' ' . ($gemini['given_names'] ?? '')));
    $mName = strtoupper(trim($mrz['full_name'] ?? ''));
    if ($gName !== '' && $mName !== '') {
        // Check if all words in one appear in the other
        $gWords = array_unique(explode(' ', preg_replace('/\s+/', ' ', $gName)));
        $mWords = array_unique(explode(' ', preg_replace('/\s+/', ' ', $mName)));
        $common = array_intersect($gWords, $mWords);
        if (count($common) === count($gWords) && count($common) === count($mWords)) {
            $matches[] = "Name: $mName";
        } elseif (count($common) >= max(1, count($mWords) - 1)) {
            $matches[] = "Name (partial): Gemini=" . trim($gName) . ", MRZ=" . trim($mName);
        } else {
            $conflicts[] = "Name — Gemini: " . trim($gName) . ", MRZ: " . trim($mName);
        }
    }

    $nConflicts = count($conflicts);
    $nMatches   = count($matches);
    if ($nConflicts === 0 && $nMatches >= 3) {
        $confidence = 'high';
    } elseif ($nConflicts === 0) {
        $confidence = 'medium';
    } elseif ($nConflicts <= 1) {
        $confidence = 'low';
    } else {
        $confidence = 'conflict';
    }

    return ['confidence' => $confidence, 'matches' => $matches, 'conflicts' => $conflicts];
}
