<?php
/**
 * MRZ (Machine Readable Zone) Parser for Passports and ID Documents
 * Extracts data from the standardized machine-readable lines at the bottom of documents
 * Supports:
 * - Passports (P<) - Format: P<CCCNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
 * - International ID Cards (I<) - Format: I<CCCNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
 * - Afghan Tazkira/Identity Cards (I<AFG) - Format: I<AFGNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
 * Most accurate method for document data extraction
 * Afghan Tazkira also recognized by: تذکره تابعیت (Dari) / د تابعیت تذکره (Pashto)
 */

/**
 * Parse MRZ lines from document image OCR text (Passport or ID)
 * Format:
 * PASSPORT:
 * Line 1: P<CCCNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
 * Line 2: NNNNNNNNCCCYYMMDDCCCYYMMDDCCCCCCCCCCN
 * 
 * ID CARD:
 * Line 1: I<CCCNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
 * Line 2: NNNNNNNNCCCYYMMDDCCCYYMMDDCCCCCCCCCCN
 */
function parseMRZLines($text) {
    $data = [
        'full_name' => null,
        'date_of_birth' => null,
        'expiry_date' => null,
        'passport_number' => null,
        'id_number' => null,
        'nationality' => null,
        'gender' => null,
        'extraction_method' => 'mrz',
        'mrz_valid' => false,
        'mrz_debug' => [],
    ];
    
    // Extract MRZ lines - usually the last 2-3 lines with specific pattern
    $lines = preg_split('/\n/', $text);
    $mrzLines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Skip empty lines
        if (empty($line)) {
            continue;
        }
        
        // MRZ line should be at least 30 characters for passports/IDs
        // Allow some OCR errors and formatting variations
        if (strlen($line) >= 30) {
            // Check if line starts with P< (passport) or I< (ID) - these are clear MRZ markers
            if (preg_match('/^P<|^I</', $line)) {
                // Clean up the line - remove extra spaces/artifacts but keep <
                $cleanLine = cleanMRZLine($line);
                // Make sure it still meets minimum length after cleaning
                if (strlen($cleanLine) >= 30) {
                    $mrzLines[] = $cleanLine;
                    continue;
                }
            }
            
            // Alternative: Check if line looks like MRZ (mostly letters/numbers with < chars)
            // Should have mostly letters/numbers with some special chars like <
            $letterCount = preg_match_all('/[A-Z0-9<]/', $line);
            $totalRelevant = strlen($line);
            
            // If at least 70% of line is valid MRZ characters
            if ($letterCount >= ($totalRelevant * 0.7)) {
                // Clean up the line - remove extra spaces/artifacts but keep <
                $cleanLine = cleanMRZLine($line);
                // Make sure it still meets minimum length after cleaning
                if (strlen($cleanLine) >= 30) {
                    // Avoid duplicates
                    if (!in_array($cleanLine, $mrzLines)) {
                        $mrzLines[] = $cleanLine;
                    }
                }
            }
        }
    }
    
    $data['mrz_debug']['lines_found'] = count($mrzLines);
    $data['mrz_debug']['all_lines_processed'] = count($lines);
    
    // Need at least 2 MRZ lines
    if (count($mrzLines) < 2) {
        $data['mrz_debug']['status'] = 'Not enough MRZ lines';
        return $data;
    }
    
    // Take last 2 MRZ lines
    $line1 = $mrzLines[count($mrzLines) - 2]; // Second to last
    $line2 = $mrzLines[count($mrzLines) - 1]; // Last line
    
    $data['mrz_debug']['line1'] = $line1;
    $data['mrz_debug']['line2'] = $line2;
    
    // Determine document type: Passport (P<), ID Card (I<), or Afghan Tazkira (I<AFG)
    $docType = 'passport'; // default
    $isAfghanID = false;
    
    if (preg_match('/^I[<\s]?AFG/', $line1)) {
        // Specific Afghan Tazkira detection: I<AFG or I AFG
        $docType = 'id';
        $isAfghanID = true;
        $data['mrz_debug']['detected_as_afghan_tazkira'] = true;
    } elseif (preg_match('/^I[<\s]?[A-Z]{3}/', $line1)) {
        // Generic ID Card (I<XXX format where XXX is country code)
        $docType = 'id';
        // Check if it's still Afghan even without explicit AFG
        if (preg_match('/AFG/', $line1)) {
            $isAfghanID = true;
            $data['mrz_debug']['detected_as_afghan_tazkira'] = true;
        }
    } elseif (!preg_match('/^P[<\s]?[A-Z]{3}/', $line1)) {
        // Neither P<, I<, nor proper header - try alternate formats
        if (!preg_match('/^[A-Z]{1,2}[<\s]?[A-Z]{3}/', $line1)) {
            $data['mrz_debug']['status'] = 'Invalid MRZ header - must start with P< or I<';
            return $data;
        }
        // Looks like ID card from the structure
        $docType = 'id';
    }
    
    $data['mrz_debug']['document_type'] = $docType;
    
    try {
        // Parse Line 1: P<CCCNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNNN
        // P< + country code (3 chars) + name field (39 chars)
        
        $nationality = substr($line1, 2, 3);  // CCC (country code) at position 2-4
        
        // Extract name (surnames and given names separated by <<)
        // Name field starts at position 5
        $nameField = substr($line1, 5);
        
        // Split by << to get surname and given names
        $nameParts = explode('<<', $nameField);
        $surnameRaw = isset($nameParts[0]) ? trim($nameParts[0]) : '';
        // Multiple given names are separated by << (e.g. AHMAD<<WALI) - keep them all
        $givenRaw = isset($nameParts[1]) ? trim(implode(' ', array_slice($nameParts, 1))) : '';
        
        // Letter tokens of each part. OCR may fuse padding onto the last word
        // (e.g. "MUSTAFA" + '<' read as 'L' = "MUSTAFAL"), so do not trim yet.
        preg_match_all('/[A-Z]+/', $surnameRaw, $sm);
        preg_match_all('/[A-Z]+/', $givenRaw, $gm);
        $surnameTokens = splitFillerRunsAndFilter($sm[0]);
        $givenTokens = splitFillerRunsAndFilter($gm[0]);
        
        // Primary: composite-check-digit validated name. Only a variant whose
        // ICAO composite checksum matches is accepted, so real names like
        // KHALIL/JAMIL are never modified (see correctNameByCompositeCheck).
        $corrected = correctNameByCompositeCheck($line1, $line2, $surnameTokens, $givenTokens);
        if ($corrected !== null) {
            $surname = $corrected['surname'];
            $givenName = $corrected['given'];
            $data['mrz_debug']['name_checksum_corrected'] = true;
            $data['mrz_debug']['name_checksum_variant'] = $corrected['variant'];
        } else {
            // Fallback: lenient padding cleanup for heavily-garbled MRZ lines
            // (e.g. the whole padding run read as "LLLLL...KKKKL")
            $surname = cleanMRZNameComponent($surnameRaw);
            $givenName = cleanMRZNameComponent($givenRaw);
        }
        
        // Display in natural reading order: given names first, surname last.
        // MRZ stores SURNAME<<GIVEN, but humans read "NAZAR MOHAMMAD WATAN DOST".
        $data['full_name'] = trim($givenName . ' ' . $surname);
        if (empty($data['full_name'])) {
            $data['full_name'] = null;
        }
        
        $data['nationality'] = $nationality;
        
        // Parse Line 2: NNNNNNNNCCCYYMMDDCCCYYMMDDCCCCCCCCCCN
        // Positions: 
        // 0-8: ID/passport number (9 digits)
        // 9-11: country code (3 letters)
        // 12-17: birth date YYMMDD
        // 18-20: expiry date YY or country code (3 letters)
        // 20: gender (M/F)
        // 21+: expiry date and check digits
        
        // Extract ID/Passport number from Line 2 (first 9 characters)
        $idNum = substr($line2, 0, 9);
        $idNum = str_replace('<', '', $idNum);
        $idNum = trim($idNum);
        
        // Store in appropriate field based on document type
        if ($docType === 'id') {
            $data['id_number'] = $idNum;
        } else {
            $data['passport_number'] = $idNum;
        }
        
        $data['mrz_debug']['id_number_extracted'] = $idNum;
        $data['mrz_debug']['document_type_confirmed'] = $docType;
        
        // Extract date of birth from Line 2
        // For ID: Position 13-14: DOB YY, 15-16: DOB MM, 17-18: DOB DD
        // For Passport: Position 13-14: DOB YY, 15-16: DOB MM, 17-18: DOB DD  
        // (ID and Passport have same positions for DOB)
        $dobYear = substr($line2, 13, 2);
        $dobMonth = substr($line2, 15, 2);
        $dobDay = substr($line2, 17, 2);
        
        $data['mrz_debug']['dob_raw'] = "Year:$dobYear Month:$dobMonth Day:$dobDay";
        
        // Convert 2-digit year to 4-digit
        $fullDobYear = convertMRZYear($dobYear);
        if (isValidDate($dobDay, $dobMonth, $fullDobYear)) {
            $data['date_of_birth'] = sprintf('%04d-%02d-%02d', $fullDobYear, $dobMonth, $dobDay);
        }
        
        // Extract expiry date and gender
        // For ID: Position 19: gender (M/F), Position 20-21: EXP YY, 22-23: EXP MM, 24-25: EXP DD
        // For Passport: Position 20: gender (M/F), Position 21-22: EXP YY, 23-24: EXP MM, 25-26: EXP DD
        $genderPos = ($docType === 'id') ? 19 : 20;
        $expYYPos = ($docType === 'id') ? 20 : 21;
        $expMMPos = ($docType === 'id') ? 22 : 23;
        $expDDPos = ($docType === 'id') ? 24 : 25;
        
        $expYear = substr($line2, $expYYPos, 2);
        $expMonth = substr($line2, $expMMPos, 2);
        $expDay = substr($line2, $expDDPos, 2);
        
        $data['mrz_debug']['exp_raw'] = "Year:$expYear Month:$expMonth Day:$expDay";
        
        $fullExpYear = convertMRZYear($expYear);
        if (isValidDate($expDay, $expMonth, $fullExpYear)) {
            $data['expiry_date'] = sprintf('%04d-%02d-%02d', $fullExpYear, $expMonth, $expDay);
        }
        
        // Extract gender
        $genderChar = substr($line2, $genderPos, 1);
        if ($genderChar === 'M') {
            $data['gender'] = 'Male';
        } elseif ($genderChar === 'F') {
            $data['gender'] = 'Female';
        }
        
        $data['mrz_valid'] = true;
        
        // ICAO checksum validation of the parsed fields (informational -
        // OCR corruption makes strict rejection unsafe, but a valid checksum
        // proves the extracted value was read correctly)
        $data['mrz_debug']['checksum_passport'] = validateFieldChecksum(substr($line2, 0, 9), substr($line2, 9, 1));
        $data['mrz_debug']['checksum_dob'] = validateFieldChecksum(substr($line2, 13, 6), substr($line2, 19, 1));
        $data['mrz_debug']['checksum_expiry'] = validateFieldChecksum(substr($line2, 21, 6), substr($line2, 27, 1));
        $data['checksum_valid'] = $data['mrz_debug']['checksum_passport']
            && $data['mrz_debug']['checksum_dob']
            && $data['mrz_debug']['checksum_expiry'];
        
        return $data;
        
    } catch (Exception $e) {
        return $data;
    }
}

/**
 * Clean an OCR'd MRZ line into MRZ characters only.
 * Junk characters (dashes, dots, etc.) are removed. Spaces are meaningful:
 * OCR frequently inserts them between name words (e.g. "ZAMIR KL" or
 * "WATAN DOST"). Inside the name field (after the P<CCC header) a space is
 * converted to '<' - its semantic MRZ word-separator meaning - so the word
 * structure survives tokenization. A space in the header zone is dropped.
 */
function cleanMRZLine($line) {
    $tmp = preg_replace('/[^A-Z0-9< ]/', '', $line);
    // Repair the P</I< header: OCR often misreads the '<' after P/I as a
    // letter or digit (e.g. "PSAFG..."), or inserts a space. A space is
    // dropped; a misread letter/digit is replaced with the required '<'.
    // Falls back to deleting all spaces only if the header is unrecoverable.
    if (preg_match('/^([PI])(.?)(AFG|[A-Z]{3})/', $tmp, $m)) {
        $consumed = strlen($m[1]) + strlen($m[2]) + strlen($m[3]);
        return $m[1] . '<' . $m[3] . str_replace(' ', '<', substr($tmp, $consumed));
    }
    return str_replace(' ', '', $tmp);
}

/**
 * Convert 2-digit year to 4-digit year (ICAO standard)
 * Uses rolling window: 00-30 = 2000-2030, 31-99 = 1931-1999
 */
function convertMRZYear($year2digit) {
    $year = intval($year2digit);
    if ($year <= 30) {
        return 2000 + $year;
    } else {
        return 1900 + $year;
    }
}

/**
 * Clean a MRZ name component (surname or given names) corrupted by OCR.
 * The '<' padding characters that fill the MRZ name field are frequently
 * misread as letters (typically L, I, K, O). Without cleaning, the final
 * name ends up with garbage like "WALI MUSTAFALLLLLLLLLLLLLLLLLLLKKKKL".
 *
 * Strategy (safe against real names like ALI, KHALIL, JAMIL, ABDUL):
 * - Split into letter-only words
 * - Trim trailing same-character runs of padding letters (length >= 2)
 * - Drop tokens that are provably padding: all-identical characters,
 *   tokens made only of padding letters, or implausibly long tokens
 */
function cleanMRZNameComponent($raw) {
    if (!is_string($raw) || trim($raw) === '') {
        return '';
    }
    
    $name = strtoupper($raw);
    // OCR misreads the '<' padding as letters (usually L/I/K/O/C/E/X). A run
    // of 3+ identical padding letters is filler - split it into a word
    // separator so the real name before it survives (e.g. "MUSTAFALLLLLL...
    // KKKKL" -> "MUSTAFA K")
    $name = preg_replace('/([LIKOCEX])\1{2,}/', ' ', $name);
    
    preg_match_all('/[A-Z]+/', $name, $matches);
    $clean = [];
    
    foreach ($matches[0] as $token) {
        // Trim trailing runs of the same padding letter (L/I/K/O/C/E/X), length >= 2.
        // E.g. "MUSTAFALL" -> "MUSTAFA", "WALIKKK" -> "WALI"
        // Single trailing padding letters are kept: "KHALIL", "ALI" stay intact.
        $token = preg_replace('/([LIKOCEX])\1{1,}$/', '', $token);
        
        if ($token === '' || isMRZPaddingToken($token)) {
            continue;
        }
        
        $clean[] = $token;
    }
    
    // Real passports list up to 3 given names; anything after is padding noise
    return implode(' ', array_slice($clean, 0, 3));
}

/**
 * Split OCR-fused filler runs (3+ identical L/I/K/O/C) inside name tokens
 * into separate words (e.g. "MOHAMMADLLLULLAH" -> "MOHAMMAD ULLAH"), then
 * drop tokens that are pure padding misreads (e.g. "KL", "KLLLLLLKKK").
 * Real name words are preserved, so the composite validation below works on
 * clean tokens.
 *
 * @param string[] $tokens
 * @return string[]
 */
function splitFillerRunsAndFilter($tokens) {
    $out = [];
    foreach ((array)$tokens as $token) {
        $split = preg_replace('/([LIKOCEX])\1{2,}/', ' ', $token);
        foreach (preg_split('/\s+/', trim($split)) as $piece) {
            if ($piece !== '' && !isMRZPaddingToken($piece)) {
                $out[] = $piece;
            }
        }
    }
    return $out;
}

/**
 * Detect tokens that are OCR-misread MRZ padding rather than real name words.
 */
function isMRZPaddingToken($token) {
    $len = strlen($token);
    if ($len < 2) {
        // A lone padding letter (L/I/K/O/C/E/X) is a misread filler, not a name.
        // NOTE: 'S' is deliberately excluded - it is a common real initial
        // (e.g. "S BIBI", "S KHAN") and must never be dropped.
        return strpos('LIKOCEX', $token) !== false;
    }
    
    // "KKKKK", "LLLLLL", "IIII" - all identical characters are padding
    if (preg_match('/^([A-Z])\1+$/', $token)) {
        return true;
    }
    
    // Only made of padding letters (L/I/K/O/C/E/X/S), e.g. "KLLLLLLLLLLKKKKL",
    // "ECEEEEEEEC", or the 2-char fusion "KL"/"EK" after a name (ZAMIR + '<'
    // '<<' misread). 'S' is safe here: no real 2+ letter word is all filler.
    if (preg_match('/^[LIKOCEXS]+$/', $token) && $len >= 2) {
        return true;
    }
    
    // Implausibly long tokens - real MRZ name words are rarely longer than 14 chars
    if ($len > 14) {
        return true;
    }
    
    return false;
}

/**
 * Validate if date is real
 */
function isValidDate($day, $month, $year) {
    $day = intval($day);
    $month = intval($month);
    $year = intval($year);
    
    if ($month < 1 || $month > 12) return false;
    if ($day < 1 || $day > 31) return false;
    if ($year < 1900 || $year > 2100) return false;
    
    return checkdate($month, $day, $year);
}

/**
 * Validate MRZ checksum (optional but increases confidence)
 */
function validateMRZChecksum($line) {
    // ICAO uses a specific checksum algorithm
    $weights = [7, 3, 1];
    $sum = 0;
    
    for ($i = 0; $i < strlen($line) - 1; $i++) {
        $char = $line[$i];
        $weight = $weights[$i % 3];
        
        if (is_numeric($char)) {
            $sum += intval($char) * $weight;
        } elseif ($char === '<') {
            $sum += 0 * $weight;
        } else {
            $sum += (ord($char) - ord('A') + 10) * $weight;
        }
    }
    
    $checksum = $sum % 10;
    $providedChecksum = intval(substr($line, -1));
    
    return $checksum === $providedChecksum;
}

/**
 * Compute the ICAO check digit for a single MRZ field
 * (e.g. the 9-char passport number field, or a 6-char date field)
 */
function computeMRZChecksum($field) {
    $weights = [7, 3, 1];
    $sum = 0;
    
    for ($i = 0; $i < strlen($field); $i++) {
        $char = $field[$i];
        $weight = $weights[$i % 3];
        
        if ($char === '<') {
            $sum += 0;
        } elseif (ctype_digit($char)) {
            $sum += intval($char) * $weight;
        } else {
            $sum += (ord(strtoupper($char)) - ord('A') + 10) * $weight;
        }
    }
    
    return $sum % 10;
}

/**
 * Check that a field's check digit matches the ICAO computed value
 */
function validateFieldChecksum($field, $checkDigit) {
    if ($checkDigit === '' || !ctype_digit((string)$checkDigit)) {
        return false;
    }
    return computeMRZChecksum($field) === intval($checkDigit);
}

/**
 * Recover the true name when OCR fused a padding '<' onto the last name word
 * (e.g. "MUSTAFAL" instead of "MUSTAFA", "ZAMIRKL" instead of "ZAMIR").
 *
 * The ICAO composite check digit (last char of line 2) covers the entire
 * line 1 + line2[0..42]. Candidates are generated by trimming 0-3 trailing
 * padding letters (L/I/K/O) from the last given-name word and 0-1 from the
 * surname, rebuilding line 1 and validating each against the composite digit.
 *
 * Only a candidate whose checksum matches is accepted, so real names like
 * KHALIL, JAMIL or ALI are never modified. Returns null when no candidate
 * validates (OCR corruption elsewhere, or the name was already correct).
 *
 * @param string[] $surnameTokens Letter words of the surname
 * @param string[] $givenTokens   Letter words of the given names
 */
function correctNameByCompositeCheck($line1, $line2, $surnameTokens, $givenTokens) {
    // line1 may be a non-44 char length (OCR inserted a spurious space) - the
    // candidate reconstructions below are always exactly 44, so validation
    // only requires an intact line2 and a usable header prefix.
    if (strlen($line2) !== 44 || !ctype_digit(substr($line2, 43, 1)) || strlen($line1) < 5) {
        return null;
    }
    
    $compositeDigit = (int)substr($line2, 43, 1);
    $prefix = substr($line1, 0, 5); // e.g. "P<AFG"
    
    // Evaluation order - first match wins, and each block is exhausted before
    // the next so a coincidental match can never steal a true one:
    //   1. plain trims, surname untouched, every layout (original first)
    //   2. plain trims, surname trimmed 1, every layout
    //   3. given-name last-letter repairs, surname untouched
    //   4. given-name last-letter repairs, surname trimmed 1
    //   5. surname last-letter repairs, surname untouched
    //   6. surname last-letter repairs, surname trimmed 1
    $candidateLists = buildCandidateTokenLists($surnameTokens, $givenTokens);
    for ($sTrim = 0; $sTrim <= 1; $sTrim++) {
        foreach ($candidateLists as $cl) {
            for ($gTrim = 0; $gTrim <= 3; $gTrim++) {
                $sTokens = trimTrailingFillerTokens($cl['surname'], $sTrim);
                $gTokens = trimTrailingFillerTokens($cl['given'], $gTrim);
                $m = validateNameAgainstComposite($prefix, $sTokens, $gTokens, $line2, $compositeDigit);
                if ($m !== null) {
                    return ['surname' => $m['surname'], 'given' => $m['given'],
                        'variant' => "given_trim=$gTrim,surname_trim=$sTrim" . ($cl['label'] !== '' ? ",{$cl['label']}" : '')];
                }
            }
        }
    }
    for ($sTrim = 0; $sTrim <= 1; $sTrim++) {
        foreach ($candidateLists as $cl) {
            for ($gTrim = 0; $gTrim <= 3; $gTrim++) {
                $r = tryLetterRepairAtLevel($prefix, $cl['given'], $cl['surname'], $sTrim, $gTrim, $line2, $compositeDigit, $cl['label'], 'given');
                if ($r !== null) {
                    return $r;
                }
            }
        }
    }
    for ($sTrim = 0; $sTrim <= 1; $sTrim++) {
        foreach ($candidateLists as $cl) {
            for ($gTrim = 0; $gTrim <= 3; $gTrim++) {
                $r = tryLetterRepairAtLevel($prefix, $cl['surname'], $cl['given'], $sTrim, $gTrim, $line2, $compositeDigit, $cl['label'], 'surname');
                if ($r !== null) {
                    return $r;
                }
            }
        }
    }
    
    return null;
}

/**
 * Try repairing the LAST character of the last word of $targetTokens via
 * common Tesseract letter confusions at one specific trim depth. The char is
 * substituted AFTER trimming, so "IZATULLANS" -> trim S -> "IZATULLAN" ->
 * N->H -> "IZATULLAH" works. The other part stays as $otherTokens.
 * Only a composite-validated repair is accepted, so real names are safe.
 *
 * @return array{surname: string, given: string, variant: string}|null
 */
function tryLetterRepairAtLevel($prefix, $targetTokens, $otherTokens, $sTrim, $gTrim, $line2, $compositeDigit, $label, $part) {
    $targetTokens = array_values($targetTokens);
    $otherTokens = array_values($otherTokens);
    if (empty($targetTokens)) {
        return null;
    }
    $trimmed = trimTrailingFillerTokens($targetTokens, $part === 'given' ? $gTrim : $sTrim);
    if (empty($trimmed)) {
        return null;
    }
    $lastIndex = count($trimmed) - 1;
    $lastChar = substr($trimmed[$lastIndex], -1);
    foreach (letterConfusions($lastChar) as $replacement) {
        $toks = $trimmed;
        $toks[$lastIndex] = substr($toks[$lastIndex], 0, -1) . $replacement;
        $other = trimTrailingFillerTokens($otherTokens, $part === 'given' ? $sTrim : $gTrim);
        if ($part === 'given') {
            $m = validateNameAgainstComposite($prefix, $other, $toks, $line2, $compositeDigit);
        } else {
            $m = validateNameAgainstComposite($prefix, $toks, $other, $line2, $compositeDigit);
        }
        if ($m !== null) {
            return ['surname' => $m['surname'], 'given' => $m['given'],
                'variant' => "given_trim=$gTrim,surname_trim=$sTrim,{$part}_letter=$lastChar>$replacement" . ($label !== '' ? ",$label" : '')];
        }
    }
    return null;
}

/**
 * Validate one reconstructed name against the ICAO composite check digit.
 *
 * @return array{surname: string, given: string}|null
 */
function validateNameAgainstComposite($prefix, $sTokens, $gTokens, $line2, $compositeDigit) {
    $nameField = implode('', $sTokens) . '<<' . implode('<', $gTokens);
    if (strlen($nameField) > 39) {
        return null;
    }
    $candidate = $prefix . str_pad($nameField, 39, '<');
    if (computeMRZChecksum($candidate . substr($line2, 0, 43)) === $compositeDigit) {
        return [
            'surname' => implode(' ', $sTokens),
            'given' => implode(' ', $gTokens),
        ];
    }
    return null;
}

/**
 * Build the token layouts to validate: the original tokens plus one variant
 * per short (<=2 char) word merged with its right neighbour - OCR frequently
 * splits one name word into two with a spurious space (e.g. "S INAC" from
 * "SINA"). Merging is only accepted if the composite check digit validates
 * the result, so genuine initials like "S BIBI" are never fused.
 *
 * @param string[] $surnameTokens
 * @param string[] $givenTokens
 * @return array<int, array{surname: string[], given: string[], label: string}>
 */
function buildCandidateTokenLists($surnameTokens, $givenTokens) {
    $lists = [
        ['surname' => array_values($surnameTokens), 'given' => array_values($givenTokens), 'label' => ''],
    ];
    foreach (['given' => $givenTokens, 'surname' => $surnameTokens] as $part => $tokens) {
        $count = count($tokens);
        for ($i = 0; $i < $count - 1; $i++) {
            if (strlen($tokens[$i]) <= 2) {
                $merged = $tokens;
                $merged[$i] = $tokens[$i] . $tokens[$i + 1];
                array_splice($merged, $i + 1, 1);
                if ($part === 'given') {
                    $lists[] = ['surname' => array_values($surnameTokens), 'given' => $merged, 'label' => "merge_given_$i"];
                } else {
                    $lists[] = ['surname' => $merged, 'given' => array_values($givenTokens), 'label' => "merge_surname_$i"];
                }
            }
        }
    }
    return $lists;
}

/**
 * Common Tesseract letter confusions for repairing the LAST character of a
 * name word. The composite check digit must validate any repaired candidate,
 * so these are only guesses that the checksum confirms or rejects.
 *
 * @param string $char
 * @return string[]
 */
function letterConfusions($char) {
    // Only the very common H/N/M family - ULLAH/ULLAN/ULLAM endings. Each
    // extra pair multiplies the ~10-19% checksum-coincidence surface, and
    // speculative pairs (C<->G, X, E, ...) created false matches in real
    // passports (IZATYARG, INAG).
    $map = [
        'H' => ['N', 'M'],
        'N' => ['H', 'M'],
        'M' => ['H', 'N'],
    ];
    return $map[$char] ?? [];
}

/**
 * Trim up to $n trailing padding letters (L/I/K/O/C/E/X/S) from the LAST
 * token of a name-word list. If a token is fully trimmed away it is removed,
 * so a fused filler token like "KL" or "ZAMIRKL" reduces back to the real
 * name.
 *
 * @param string[] $tokens
 * @param int      $n
 * @return string[]
 */
function trimTrailingFillerTokens($tokens, $n) {
    $tokens = array_values(array_filter((array)$tokens, 'is_string'));
    if (empty($tokens)) {
        return [];
    }
    
    $lastIndex = count($tokens) - 1;
    for ($i = 0; $i < $n; $i++) {
        $tokens[$lastIndex] = (string)preg_replace('/[LIKOCEXS]$/', '', $tokens[$lastIndex]);
        if ($tokens[$lastIndex] === '') {
            array_pop($tokens);
            $lastIndex = count($tokens) - 1;
            if ($lastIndex < 0) {
                break;
            }
        }
    }
    
    return $tokens;
}

?>
