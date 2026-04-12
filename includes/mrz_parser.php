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
                $cleanLine = preg_replace('/[^A-Z0-9<]/', '', $line);
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
                $cleanLine = preg_replace('/[^A-Z0-9<]/', '', $line);
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
        $surname = isset($nameParts[0]) ? trim($nameParts[0]) : '';
        $givenName = isset($nameParts[1]) ? trim($nameParts[1]) : '';
        
        // Clean up surnames - extract only consecutive letters A-Z
        // Surname should be all letters until first non-letter
        if (preg_match('/^([A-Z]+)/', $surname, $m)) {
            $surname = $m[1];
        } else {
            $surname = '';
        }
        
        // Clean up given names - may contain multiple names separated by spaces/< 
        // Extract all word groups of letters A-Z
        $givenNameParts = [];
        if (preg_match_all('/[A-Z]+/', $givenName, $matches)) {
            $givenNameParts = $matches[0];
            // Take only the first 2 parts (real names)
            // Padding usually starts after first 1-2 legitimate name words
            $givenNameParts = array_slice($givenNameParts, 0, 2);
        }
        $givenName = implode(' ', $givenNameParts);
        
        $data['full_name'] = trim($surname . ' ' . $givenName);
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
        return $data;
        
    } catch (Exception $e) {
        return $data;
    }
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

?>
