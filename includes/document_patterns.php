<?php
/**
 * Document Pattern Extraction for Umrah
 * Extracts data from Passport and ID documents
 * Uses MRZ parsing as primary method (most accurate)
 * Falls back to OCR pattern matching
 */

require_once __DIR__ . '/mrz_parser.php';

/**
 * Extract passport information
 * Priority: MRZ > Pattern matching > Fallback
 * Supports Afghan and international passport formats
 */
function extractPassportData($text) {
    // First, try MRZ parsing (most accurate)
    $mrzData = parseMRZLines($text);
    if ($mrzData['mrz_valid']) {
        // MRZ extraction successful - this is the most reliable method
        $mrzData['extraction_confidence'] = 0.95;
        $mrzData['extraction_method'] = 'mrz';
        $mrzData['document_type'] = 'passport';
        return $mrzData;
    }
    
    // Fallback to pattern matching
    $data = [
        'full_name' => null,
        'date_of_birth' => null,
        'passport_number' => null,
        'expiry_date' => null,
        'issue_date' => null,
        'father_name' => null,
        'gender' => null,
        'nationality' => null,
        'place_of_birth' => null,
        'extraction_confidence' => 0,
        'extraction_method' => 'pattern',
        'mrz_debug' => $mrzData['mrz_debug'] ?? [], // Include why MRZ failed
    ];
    
    // Extract passport number - patterns like "A12345678", "AFG9205026M", or "098220721AFG"
    // Priority 1: MRZ line with passport number (P098220721AFG pattern)
    if (preg_match('/\bP\s*(\d{9}[A-Z]{3})\b/i', $text, $match)) {
        $number = trim(str_replace(' ', '', $match[1]));
        if (preg_match('/^\d{9}[A-Z]{3}$/i', $number)) {
            $data['passport_number'] = $number;
        }
    }
    
    // Priority 2: Standalone 9-digit + 3-letter format (098220721AFG) - must have word boundary
    if (empty($data['passport_number']) && preg_match('/(?:^|\s)(\d{9}[A-Z]{3})(?:\s|$)/im', $text, $match)) {
        $number = trim($match[1]);
        if (preg_match('/^\d{9}[A-Z]{3}$/i', $number)) {
            $data['passport_number'] = $number;
        }
    }
    
    // Priority 3: Explicit "Passport Number:" label
    if (empty($data['passport_number']) && preg_match('/Passport\s+Number[:\s]+([A-Z]{1,2}\d{6,8})\b/i', $text, $match)) {
        $number = trim($match[1]);
        if (preg_match('/^[A-Z]{1,2}\d{6,8}$/', $number)) {
            $data['passport_number'] = $number;
        }
    }
    
    // Priority 4: Only accept if has clear boundary (not part of garbage text)
    if (empty($data['passport_number']) && preg_match('/(?:^|\s)([A-Z]{1,2}\d{6,8})(?:\s|$|\n)/im', $text, $match)) {
        $number = trim($match[1]);
        // Validate format strictly
        if (preg_match('/^[A-Z]{1,2}\d{6,8}$/', $number) && strlen($number) >= 7) {
            $data['passport_number'] = $number;
        }
    }
    
    // Extract date of birth - patterns like "02 MAY 1992" or "02/05/1992"
    if (preg_match('/Date\s+of\s+(?:Birth|birth)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
    } elseif (preg_match('/DOB[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
    } elseif (preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
    }
    
    // Extract issue date
    if (preg_match('/Date\s+of\s+(?:Issue|Issu)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i', $text, $match)) {
        $data['issue_date'] = formatPassportDate($match[1], $match[2], $match[3]);
    } elseif (preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})\s+(?:Issue|Issu)/i', $text, $match)) {
        $data['issue_date'] = formatPassportDate($match[1], $match[2], $match[3]);
    }
    
    // Extract expiry date
    if (preg_match('/(?:Date\s+of\s+)?(?:Expiry|Expires|Valid\s+Until|Expir)[:\s]+(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/i', $text, $match)) {
        $data['expiry_date'] = formatPassportDate($match[1], $match[2], $match[3]);
    } elseif (preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})\s+(?:Expiry|Expires|Valid)/i', $text, $match)) {
        $data['expiry_date'] = formatPassportDate($match[1], $match[2], $match[3]);
    }
    
    // Extract full name - usually after "Given Names" or "Name"
    // First priority: "Given Names" field (Afghan passports)
    if (preg_match('/Given\s+Names[:\s]*\n([A-Z][A-Za-z\s]+?)\n/im', $text, $match)) {
        $fullName = trim($match[1]);
        if (isValidName($fullName)) {
            $data['full_name'] = $fullName;
        }
    } 
    // Second: Explicit name fields
    if (empty($data['full_name']) && preg_match('/(?:Name|Surname)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|$|Nationality|Father|Father\'s)/i', $text, $match)) {
        $fullName = trim($match[1]);
        if (isValidName($fullName)) {
            $data['full_name'] = $fullName;
        }
    } 
    // Third: Generic name at start of line followed by known field
    if (empty($data['full_name']) && preg_match('/^([A-Z][A-Za-z\s]{2,}?)(?:\n|Date of Birth|Dato of Birth|AFGHAN|PASSPORT|Father)/im', $text, $match)) {
        $fullName = trim($match[1]);
        // Avoid capturing keywords
        if (!preg_match('/^(PASSPORT|ISLAMIC|REPUBLIC|AFGHAN|AFG|KABUL|PAKTIA)/i', $fullName) && strlen($fullName) > 2 && isValidName($fullName)) {
            $data['full_name'] = $fullName;
        }
    }
    
    // Extract father's name or parent name
    if (preg_match('/(?:Father|Father\'s|Parent)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|$|Nationality)/i', $text, $match)) {
        $data['father_name'] = trim($match[1]);
    }
    
    // Extract place of birth
    if (preg_match('/Place\s+of\s+(?:Birth|birth)[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|$|Sex|Gender|Date)/i', $text, $match)) {
        $data['place_of_birth'] = trim($match[1]);
    }
    
    // Extract gender
    if (preg_match('/(?:Gender|Sex)[:\s]+(M|F|Male|Female)/i', $text, $match)) {
        $gender = strtoupper(substr($match[1], 0, 1));
        $data['gender'] = ($gender === 'M') ? 'Male' : ($gender === 'F' ? 'Female' : $match[1]);
    }
    
    // Extract nationality
    if (preg_match('/Nationality[:\s]+([A-Z][A-Za-z\s]+?)(?:\n|$|Father)/i', $text, $match)) {
        $data['nationality'] = trim($match[1]);
    } elseif (preg_match('/AFGHAN/i', $text)) {
        // Fallback for Afghan passports
        $data['nationality'] = 'Afghan';
    }
    
    // Calculate confidence score
    $filled = 0;
    $total = 5; // passport_number, date_of_birth, expiry_date, full_name, gender
    if (!empty($data['passport_number'])) $filled++;
    if (!empty($data['date_of_birth'])) $filled++;
    if (!empty($data['expiry_date'])) $filled++;
    if (!empty($data['full_name'])) $filled++;
    if (!empty($data['gender'])) $filled++;
    
    $data['extraction_confidence'] = ($filled / $total);
    
    return $data;
}

/**
 * Extract ID document information (Afghan Tazkira, National IDs, etc.)
 * Priority: MRZ > Pattern matching > Fallback
 * Supports Afghan Tazkira (تذکره تابعیت / د تابعیت تذکره) and international ID formats
 * Languages: English, Dari (فارسی‌دری), Pashto (پښتو), Arabic (العربية)
 * Afghan Tazkira Formats:
 * - 12-digit ID number (e.g., 123456789012)
 * - Dashed format: YYYY-MMDD-NNNNN
 * - MRZ lines: I<AAFGNNNNNNNNNNN... (for newer cards)
 */
function extractIDData($text) {
    // Ensure proper UTF-8 encoding for Dari/Pashto text
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8');
    }
    
    // First, try MRZ parsing (most accurate)
    $mrzData = parseMRZLines($text);
    if ($mrzData['mrz_valid']) {
        // MRZ extraction successful
        $mrzData['extraction_confidence'] = 0.95;
        $mrzData['document_type'] = 'id';
        return $mrzData;
    }
    
    // Check if this looks like an Afghan identity card (Tazkira) by keywords
    $isAfghanID = isAfghanIdentityCard($text);
    if ($isAfghanID) {
        // Tag it for better pattern matching
        $mrzData['mrz_debug']['detected_document_type'] = 'afghan_tazkira';
        $mrzData['mrz_debug']['text_encoding'] = 'UTF-8 (Dari/Pashto)';
    }
    
    // Fallback to pattern matching
    $data = [
        'full_name' => null,
        'date_of_birth' => null,
        'father_name' => null,
        'guardian_name' => null,
        'gender' => null,
        'id_number' => null,
        'extraction_confidence' => 0,
        'extraction_method' => 'pattern',
        'mrz_debug' => $mrzData['mrz_debug'] ?? [], // Include why MRZ failed
    ];
    
    // Extract ID number - varies by country (Afghan Tazkira uses 12-digit format)
    // Priority 1: Explicit ID field (English, Dari, Pashto, Arabic)
    // تذکره تابعیت = Tazkira (Dari), د تابعیت تذکره = Tazkira (Pashto)
    if (preg_match('/(?:ID|National\s+ID|Tazkira|شناسنامه|شماره\s+شناسنامه|د\s+شناسنامې|شماره|تذکره\s+تابعیت|د\s+تابعیت\s+تذکره|تذکره)[:\s]+([A-Z0-9\-]+)/iu', $text, $match)) {
        $data['id_number'] = trim($match[1]);
    }
    // Priority 2: Number field with digits (English, Dari, Pashto)
    elseif (preg_match('/(?:Number|شماره|نمبر)[:\s]+([A-Z0-9\-]+)/iu', $text, $match)) {
        $data['id_number'] = trim($match[1]);
    }
    // Priority 3: Standalone 12-digit number (Afghan Tazkira format: 1234567890XX)
    elseif (preg_match('/\b(\d{12})\b/i', $text, $match)) {
        $data['id_number'] = trim($match[1]);
    }
    // Priority 4: ID with dashes (e.g., "1400-0302-54863")
    elseif (preg_match('/\b(\d{4}\-\d{4}\-\d{5})\b/i', $text, $match)) {
        $data['id_number'] = trim($match[1]);
    }
    // Priority 5: MRZ format first 9 characters (from MRZ line if available)
    elseif (preg_match('/^\d{9}[A-Z]{3}$/i', $text, $match)) {
        $data['id_number'] = trim($match[0]);
    }
    
    // Extract date of birth - multiple patterns (English, Dari, Pashto)
    // Pattern 1: "Date of Birth: 15 JAN 2000" or "Date of Birth: 23/10/1998" (most common in English section)
    if (preg_match('/(?:Date\s+of\s+(?:Birth|birth)|تاریخ\s+تولد|د\s+زیزې\s+نیته|Birth)[:\s\/]*(.+?)(?:\n|$|Gender|Place|جنسیت)/iu', $text, $match)) {
        $dateStr = trim($match[1]);
        // Try to extract date from the matched string
        if (preg_match('/(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/', $dateStr, $dateMatch)) {
            $data['date_of_birth'] = formatPassportDate($dateMatch[1], $dateMatch[2], $dateMatch[3]);
        }
    } 
    // Pattern 2: "DOB: 15 JAN 2000" or "DOB: 23/10/1998"
    if (empty($data['date_of_birth']) && preg_match('/\b(?:DOB|تولد)[:\s\/]*(.+?)(?:\n|$)/iu', $text, $match)) {
        $dateStr = trim($match[1]);
        if (preg_match('/(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/', $dateStr, $dateMatch)) {
            $data['date_of_birth'] = formatPassportDate($dateMatch[1], $dateMatch[2], $dateMatch[3]);
        }
    }
    // Pattern 3: Standalone date "15 JAN 2000"
    if (empty($data['date_of_birth']) && preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
    }
    // Pattern 4: Numeric date format "15/01/2000" or "15-01-2000" or "23/10/1998" for Afghan Tazkira
    if (empty($data['date_of_birth']) && preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
    }
    // Pattern 5: From dashed ID number format (1400-0302-54863 = year 1400 AH, month 03, day 02)
    // Afghan Tazkira uses Islamic Hijri calendar in dashed format
    if (empty($data['date_of_birth']) && preg_match('/\b(\d{4})\-(\d{2})(\d{2})\-\d{5}\b/i', $text, $match)) {
        // Convert Islamic year YYYY-MMDD to Gregorian approximation
        // YYYY-MMDD format: year in Islamic calendar, month, day
        $hijriYear = intval($match[1]);
        $month = intval($match[2]);
        $day = intval($match[3]);
        
        // Simple approximation: Islamic year ~1400 ≈ Gregorian 1978-1979
        // More accurate: Hijri year - 579 = approximate Gregorian year
        $gregorianYear = $hijriYear - 579;
        
        if (isValidDate($day, $month, $gregorianYear)) {
            $data['date_of_birth'] = formatPassportDate($day, $month, $gregorianYear);
        }
    }
    
    // Extract full name - multiple patterns (English, Dari, Pashto)
    // Pattern 1: "Full Name:" or "Name:" or Dari/Pashto labels (نام و تخلص = name and surname in Dari)
    if (preg_match('/(?:Full\s+Name|Name\s*\/?\s*SURNAME|Name\s|نام\s+و\s+تخلص|نام\s*[:\s]|نوم\s*[:\s])[:\s]*([^\n\r:]+?)(?:\n|$|Father|Father\'s|پدر|پلار|Date|Place|تاریخ|د|Gender|جنسیت)/iu', $text, $match)) {
        $name = trim($match[1]);
        // Filter out OCR artifacts and field names
        $name = preg_replace('/^\d+[\s\-]*/', '', $name); // Remove leading numbers
        $name = preg_replace('/[\s\-]*\d+$/', '', $name); // Remove trailing numbers
        $name = preg_replace('/\s+/', ' ', $name); // Collapse multiple spaces
        
        // More aggressive filtering: reject if mostly numbers or contains field labels
        if (!preg_match('/^(ISLAMIC|REPUBLIC|NATIONAL|TAZKIRA|AFGHANISTAN|KABUL|افغانستان|اسلامی|جمهوری|SURNAME|GIVEN|FULL|FATHER|MOTHER|GUARDIAN|ADDRESS|DOB)/iu', $name) 
            && strlen($name) > 3 
            && preg_match('/[A-Za-zآ-ی]+/', $name) // Must contain actual letters
            && !preg_match('/^\d/', $name)
            && isValidName($name)) { // Additional validation for actual names
            $data['full_name'] = $name;
        }
    }
    // Pattern 2: Name after Surname/Given Names labels with flexible spacing
    if (empty($data['full_name']) && preg_match('/(?:Surname|Given\s+Names|نام|تخلص)[:\s]*\n?([^\n\r:]+?)(?:\n|$|Father|پدر|Date)/ium', $text, $match)) {
        $name = trim($match[1]);
        // Remove numbers and clean up
        $name = preg_replace('/^\d+[\s\-]*/', '', $name);
        $name = preg_replace('/[\s\-]*\d+$/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        // Only accept if contains actual letters and not a field label
        if (!preg_match('/^(SURNAME|GIVEN|FULL|NUMBER|ID|DATE)/iu', $name) && strlen($name) > 3 && preg_match('/[A-Za-zآ-ی]+/', $name) && isValidName($name)) {
            $data['full_name'] = $name;
        }
    }
    // Pattern 3: First line that looks like a name (for Dari/Pashto text) - more aggressive for Afghan docs
    if (empty($data['full_name']) && preg_match('/^([A-Z][^\n\r\d:]{3,})(?:\n|\/|$|Father|پدر|پلار|Date|تاریخ)/mu', $text, $match)) {
        $name = trim($match[1]);
        // Avoid capturing keywords
        if (!preg_match('/^(ISLAMIC|REPUBLIC|NATIONAL|TAZKIRA|AFGHANISTAN|KABUL|افغانستان|اسلامی|جمهوری|شناسنامه|FULL|NAME|SURNAME|NUMBER|DATE|GENDER|FATHER)/iu', $name) && strlen($name) > 2 && preg_match('/[A-Za-zآ-ی]+/', $name) && isValidName($name)) {
            $data['full_name'] = $name;
        }
    }
    // Pattern 4: Afghan Tazkira specific - name might be alone on a line after FULL NAME label
    if (empty($data['full_name']) && preg_match('/FULL\s+NAME[:\s]+\n([^\n\r]+)/ium', $text, $match)) {
        $name = trim($match[1]);
        $name = preg_replace('/^\d+[\s\-]*/', '', $name);
        $name = preg_replace('/[\s\-]*\d+$/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        if (strlen($name) > 3 && preg_match('/[A-Za-zآ-ی]+/', $name) && isValidName($name)) {
            $data['full_name'] = $name;
        }
    }
    
    // Pattern 5: Last resort - extract any "FIELD: VALUE" where VALUE looks like a name
    if (empty($data['full_name']) && preg_match_all('/^([A-Z\s]+)[:\s]+([A-Za-z\s]+?)(?:\n|$)/um', $text, $matches)) {
        for ($i = 0; $i < count($matches[0]); $i++) {
            $field = strtoupper(trim($matches[1][$i]));
            $value = trim($matches[2][$i]);
            
            // Look for fields that indicate name
            if (preg_match('/(?:NAME|نام|نوم)/i', $field) 
                && !preg_match('/^(SURNAME|GIVEN|FULL|FIELD)/iu', $value)
                && strlen($value) > 3 
                && preg_match('/[A-Za-zآ-ی]+/', $value)) {
                // Clean up the value
                $value = preg_replace('/^\d+[\s\-]*/', '', $value);
                $value = preg_replace('/[\s\-]*\d+$/', '', $value);
                if (strlen($value) > 3 && isValidName($value)) {
                    $data['full_name'] = $value;
                    break;
                }
            }
        }
    }
    
    // Extract father's name (English, Dari, Pashto)
    // Afghan Tazkira format: "د پلار نوم / نام پدر:" on one line, name on next line
    if (preg_match('/(?:د\s+پلار\s+نوم\s*\/\s*نام\s+پدر|Father|Father\'s|نام\s+پدر|پلار|د\s+پلار\s+نوم|FATHER)[:\s]*\n?([^\n\r:]+?)(?:\n|$|د\s+نیکه|نام\s+پدرکلان|Mother|مادر|د\s+مور|Guardian|سرپرست)/iu', $text, $match)) {
        $fname = trim($match[1]);
        // Clean up and validate
        $fname = preg_replace('/^\d+[\s\-]*/', '', $fname);
        $fname = preg_replace('/[\s\-]*\d+$/', '', $fname);
        if (!preg_match('/^(FATHER|MOTHER|GUARDIAN|DATE|NUMBER|GENDER|د|نام|نوم)/iu', $fname) && strlen($fname) > 2) {
            $data['father_name'] = $fname;
        }
    }
    
    // Extract grandfather/paternal grandfather name (Afghan Tazkira specific)
    // Afghan Tazkira: "د نیکه نوم / نام پدرکلان:" on one line, name on next line
    // Priority 1: Grandfather field (د نیکه نوم / نام پدرکلان)
    if (preg_match('/(?:د\s+نیکه\s+نوم\s*\/\s*نام\s+پدرکلان|نام\s+پدرکلان|د\s+نیکه\s+نوم|Grandfather|Grand\s+Father)[:\s]*\n?([^\n\r]+?)(?:\n|$|Religion|دین|Ethnicity|قوم)/iu', $text, $match)) {
        $grandfather = trim($match[1]);
        $grandfather = preg_replace('/^\d+[\s\-]*/', '', $grandfather);
        $grandfather = preg_replace('/[\s\-]*\d+$/', '', $grandfather);
        if (!preg_match('/^(GRANDFATHER|MOTHER|GUARDIAN|DATE|NUMBER|GENDER|نام|نوم|د)/iu', $grandfather) && strlen($grandfather) > 2) {
            $data['guardian_name'] = $grandfather;
        }
    }
    // Priority 2: Guardian field (fallback for other document types)
    if (empty($data['guardian_name']) && preg_match('/(?:Guardian|سرپرست|د\s+سرپرستۍ\s+نوم)[:\s]*([^\n\r]+?)(?:\n|$|Date|تاریخ|Issued)/iu', $text, $match)) {
        $data['guardian_name'] = trim($match[1]);
    }
    // Priority 3: Mother field (fallback)
    elseif (empty($data['guardian_name']) && preg_match('/(?:Mother|مادر|د\s+مور)[:\s]*([^\n\r]+?)(?:\n|$|Date|تاریخ|Issued)/iu', $text, $match)) {
        $data['guardian_name'] = trim($match[1]);
    }
    // Priority 4: Parent field (fallback)
    elseif (empty($data['guardian_name']) && preg_match('/(?:Parent|والدین|د\s+والدین)[:\s]*([^\n\r]+?)(?:\n|$|Date|تاریخ|Issued)/iu', $text, $match)) {
        $data['guardian_name'] = trim($match[1]);
    }
    
    // Extract gender - looks for M/F or Male/Female or Dari/Pashto equivalents
    // Pattern 1: English labels
    if (preg_match('/(?:Gender|Sex)[:\s]+(M|F|Male|Female)/i', $text, $match)) {
        $gender = strtoupper(substr($match[1], 0, 1));
        $data['gender'] = ($gender === 'M') ? 'Male' : ($gender === 'F' ? 'Female' : $match[1]);
    }
    // Pattern 2: Dari/Pashto gender labels
    elseif (preg_match('/(?:جنسیت|د\s+جنس)[:\s]+(مرد|نارینه|زن|بنځې|ذکر|اناث)/iu', $text, $match)) {
        $genderText = strtolower(trim($match[1]));
        // Map Dari/Pashto to English
        if (preg_match('/^(مرد|نارینه|ذکر)$/u', $genderText)) {
            $data['gender'] = 'Male';
        } elseif (preg_match('/^(زن|بنځې|اناث)$/u', $genderText)) {
            $data['gender'] = 'Female';
        } else {
            $data['gender'] = $match[1];
        }
    }
    // Pattern 3: Standalone M/F or Dari letter م (Meem for male) / ف (Feh for female)
    elseif (preg_match('/\b(M|F|م|ف)\b(?:\s|$|\/)/iu', $text, $match)) {
        $gender = strtoupper($match[1]);
        if ($gender === 'M' || $gender === 'م') {
            $data['gender'] = 'Male';
        } elseif ($gender === 'F' || $gender === 'ف') {
            $data['gender'] = 'Female';
        } else {
            $data['gender'] = $match[1];
        }
    }
    
    // Calculate confidence score
    $filled = 0;
    $total = 5; // id_number, full_name, date_of_birth, father_name, gender
    if (!empty($data['id_number'])) $filled++;
    if (!empty($data['full_name'])) $filled++;
    if (!empty($data['date_of_birth'])) $filled++;
    if (!empty($data['father_name'])) $filled++;
    if (!empty($data['gender'])) $filled++;
    
    $data['extraction_confidence'] = ($filled / $total);
    
    return $data;
}

/**
 * Format date extracted from document to YYYY-MM-DD
 */
function formatPassportDate($day, $month, $year) {
    // Handle month as text
    if (!is_numeric($month)) {
        $months = [
            'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
            'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
            'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12'
        ];
        $monthKey = strtoupper(substr($month, 0, 3));
        $month = $months[$monthKey] ?? $month;
    }
    
    $day = str_pad($day, 2, '0', STR_PAD_LEFT);
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

/**
 * Validate if extracted text looks like an actual name
 * Rejects OCR garbage while accepting real names in multiple languages
 * 
 * Checks:
 * - Must have mostly letters (not random symbols/numbers)
 * - Must have normal word structure (not too many special chars)
 * - Rejects if contains too many consecutive symbols
 * - Accepts names with spaces, hyphens, apostrophes (common in names)
 */
function isValidName($name) {
    $name = trim($name);
    
    // Reject if empty
    if (empty($name) || strlen($name) < 3) {
        return false;
    }
    
    // Count character types
    $letterCount = preg_match_all('/[A-Za-zآ-یء-ي]/u', $name); // Letters (Latin, Dari, Arabic)
    $numberCount = preg_match_all('/\d/', $name);
    $symbolCount = preg_match_all('/[^A-Za-z0-9\s\-\'\،ء-يآ-ی]/u', $name); // Everything else
    
    // For UTF-8 strings with multi-byte characters, count actual characters not bytes
    $totalChars = mb_strlen($name, 'UTF-8');
    
    // Must be mostly letters (at least 60%)
    if ($letterCount < $totalChars * 0.60) {
        return false; // Too many numbers/symbols
    }
    
    // Reject if too many symbols (more than 2 symbols in name is suspicious)
    if ($symbolCount > 2) {
        return false;
    }
    
    // Reject if has too many consecutive special characters
    if (preg_match('/[^A-Za-zآ-یء-ي\s\-\']{2,}/u', $name)) {
        return false; // Multiple special chars in a row
    }
    
    // Reject if looks like random characters (e.g., "\i% L O v W SRR")
    // Real names don't have isolated single letters with spaces/symbols between them
    if (preg_match('/^[A-Z]\s*[%\\\\\/\!\@\#\$]\s*[A-Z]\s*[%\\\\\/\!\@\#\$]/i', $name)) {
        return false;
    }
    
    // Reject if has too many single isolated characters
    $singleLetters = preg_match_all('/\b[A-Za-z]\b/u', $name);
    if ($singleLetters > 2) {
        return false; // Too many isolated single letters
    }
    
    // Accept if passed all checks
    return true;
}

/**
 * Normalize Dari/Pashto text for better pattern matching
 * Handles common OCR variations and character variations
 * 
 * Returns normalized text
 */
function normalizeArabicScript($text) {
    // Handle common Unicode variations in Arabic/Dari/Pashto
    // Some OCR engines may convert یا to ي + alef
    // یا (U+06CC U+0627) -> ی (U+06CC)
    $text = str_replace('یا', 'ی', $text);
    
    // ئ can be written as ي + hamza or ی + hamza
    $text = str_replace('ئ', 'ی', $text);
    $text = str_replace('ء', '', $text); // Remove standalone hamza
    
    // Normalize different forms of alef
    $text = str_replace('ۀ', 'ه', $text); // Alef with hamza above
    $text = str_replace('ؤ', 'و', $text); // Waw with hamza
    
    // Remove diacritics that may confuse matching (Arabic diacritical marks U+064B to U+0652)
    $text = preg_replace('/[\x{064B}-\x{0652}]/u', '', $text);
    
    // Normalize whitespace (including zero-width spaces)
    $text = preg_replace('/\s+/u', ' ', $text); // Multiple spaces to single
    $text = preg_replace('/\x{200B}/u', '', $text); // Remove zero-width space
    $text = preg_replace('/\x{200C}/u', '', $text); // Remove zero-width non-joiner
    $text = preg_replace('/\x{200D}/u', '', $text); // Remove zero-width joiner
    
    return $text;
}

/**
 * Detect if the OCR text is from an Afghan identity card (Tazkira)
 * Looks for Afghan-specific keywords and markers in Dari, Pashto, or English
 * 
 * Returns true if document appears to be Afghan Tazkira
 */
function isAfghanIdentityCard($text) {
    // Normalize text for better matching
    $normalizedText = normalizeArabicScript($text);
    // Afghan Tazkira keywords in different languages:
    // Dari: تذکره تابعیت (Tazkira-ye Tabi'yyat - ID of nationality)
    // Pashto: د تابعیت تذکره (Da Tabia'yyat Tazkira)
    // English: Afghan, Tazkira, National ID
    
    $afghanMarkers = [
        // Dari keywords (try both normalized and original)
        '/تذکره\s+تابعیت/u',           // تذکره تابعیت (Tazkira-ye Tabi'yyat)
        '/شناسنامه\s+ملی/u',          // شناسنامه ملی (National ID)
        '/شماره\s+شناسنامه/u',        // شماره شناسنامه (ID Number)
        
        // Pashto keywords  
        '/د\s+تابعیت\s+تذکره/u',      // د تابعیت تذکره (ID of nationality)
        '/د\s+شناسنامې/u',            // د شناسنامې (of ID)
        '/ملی\s+شناسنامه/u',          // ملی شناسنامه (National ID)
        
        // English keywords
        '/Afghan.*ID|ID.*Afghan/i',
        '/Tazkira/i',
        '/Islamic Republic of Afghanistan/i',
        '/AFGHANISTAN/i',
    ];
    
    // Try matching on original text
    foreach ($afghanMarkers as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }
    
    // Also try on normalized text (handles OCR variations)
    foreach ($afghanMarkers as $pattern) {
        if (preg_match($pattern, $normalizedText)) {
            return true;
        }
    }
    
    // Additional check: if text contains both a 12-digit number and Afghan keywords
    if (preg_match('/\b\d{12}\b/i', $text) && 
        (preg_match('/Afghanistan|Afghan|AFGHAN/i', $text) || 
         preg_match('/تذکره|تابعیت|شناسنامه|د\s+شناسنامې/u', $text) ||
         preg_match('/تذکره|تابعیت|شناسنامه|د\s+شناسنامې/u', $normalizedText))) {
        return true;
    }
    
    return false;
}

/**
 * Find Python executable path
 * On Windows, python might not be in PATH for web servers
 * This function tries multiple approaches to find Python
 */
function findPythonExecutable() {
    static $pythonPath = null;
    
    if ($pythonPath !== null) {
        return $pythonPath;
    }
    
    // Try 1: Check environment variable PYTHON
    $envPython = getenv('PYTHON');
    if ($envPython && file_exists($envPython)) {
        $pythonPath = $envPython;
        return $pythonPath;
    }
    
    // Try 2: Try common Python locations on Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $commonPaths = [
            'C:\\Python314\\python.exe',
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            'C:\\Users\\' . getenv('USERNAME') . '\\AppData\\Local\\Programs\\Python\\Python314\\python.exe',
            'C:\\Users\\' . getenv('USERNAME') . '\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\' . getenv('USERNAME') . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
            'C:\\Users\\' . getenv('USERNAME') . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
            'C:\\Program Files\\Python314\\python.exe',
            'C:\\Program Files\\Python313\\python.exe',
            'C:\\Program Files\\Python312\\python.exe',
            'C:\\Program Files\\Python311\\python.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $pythonPath = $path;
                return $pythonPath;
            }
        }
    } else {
        // Unix/Linux - check known paths first (safe method, no shell_exec)
        $unixPaths = [
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/opt/homebrew/bin/python3',
            '/usr/bin/python',
            '/usr/local/bin/python',
        ];
        
        foreach ($unixPaths as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        // Check PATH environment (safer than shell_exec)
        $path_env = explode(PATH_SEPARATOR, getenv('PATH') ?? '');
        foreach ($path_env as $dir) {
            $python3_path = $dir . DIRECTORY_SEPARATOR . 'python3';
            if (file_exists($python3_path) && is_executable($python3_path)) {
                return $python3_path;
            }
            
            $python_path = $dir . DIRECTORY_SEPARATOR . 'python';
            if (file_exists($python_path) && is_executable($python_path)) {
                return $python_path;
            }
        }
    }
    
    return null;
}

/**
 * Extract text from image via PaddleOCR (server-side)
 * Uses free, open-source PaddleOCR - faster and more accurate than Tesseract
 * Install: pip install paddleocr
 * 
 * This function calls PaddleOCR via Python directly (more reliable than CLI)
 */
function extractTextViaPaddleOCR($imagePath) {
    try {
        // Check if image file exists
        if (!file_exists($imagePath)) {
            return '';
        }
        
        // Find Python executable
        $pythonExe = findPythonExecutable();
        if (!$pythonExe) {
            return '';
        }
        
        // Create temporary Python script to run PaddleOCR
        $pythonScript = sys_get_temp_dir() . '/paddle_ocr_' . uniqid() . '.py';
        $outputFile = sys_get_temp_dir() . '/paddle_ocr_output_' . uniqid() . '.txt';
        
        // Python script to extract text using PaddleOCR
        $pythonCode = <<<'PYTHON'
import sys
import os
import json

try:
    from paddleocr import PaddleOCR
    
    image_path = sys.argv[1]
    output_file = sys.argv[2]
    
    # Initialize PaddleOCR with English language
    # Disable model source check to avoid long waits
    os.environ['DISABLE_MODEL_SOURCE_CHECK'] = 'True'
    ocr = PaddleOCR(use_angle_cls=True, lang='en', show_log=False)
    
    # Perform OCR
    result = ocr.ocr(image_path, cls=True)
    
    # Extract text from results
    extracted_text = []
    
    if result and len(result) > 0:
        for line in result:
            if line:
                line_text = ""
                for word_info in line:
                    # PaddleOCR returns: [coordinates, (text, confidence)]
                    if isinstance(word_info, (list, tuple)) and len(word_info) >= 2:
                        text = word_info[1][0] if isinstance(word_info[1], (list, tuple)) else str(word_info[1])
                        if text:
                            line_text += text + " "
                
                if line_text.strip():
                    extracted_text.append(line_text.strip())
    
    # Write output to file - ensure file is created
    full_text = "\n".join(extracted_text)
    
    # Ensure directory exists
    os.makedirs(os.path.dirname(output_file), exist_ok=True)
    
    with open(output_file, 'w', encoding='utf-8') as f:
        f.write(full_text)
    
    # Verify file was written
    if os.path.exists(output_file) and os.path.getsize(output_file) > 0:
        print("SUCCESS")
    else:
        print("ERROR: Failed to write output file")
    
except ImportError as e:
    print(f"ERROR: PaddleOCR not installed - {str(e)}")
except Exception as e:
    print(f"ERROR: {type(e).__name__}: {str(e)}")
PYTHON;
        
        // Write Python script to file
        file_put_contents($pythonScript, $pythonCode);
        
        // Execute Python script using proc_open (safer than exec)
        // Build command as array to prevent shell injection
        $descriptorspec = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = proc_open(
            [$pythonExe, $pythonScript, $imagePath, $outputFile],
            $descriptorspec,
            $pipes,
            null,
            null
        );
        
        $output = [];
        $returnCode = -1;
        
        if (is_resource($process)) {
            fclose($pipes[0]); // Close stdin
            
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            
            $returnCode = proc_close($process);
            $output = array_filter(array_merge(
                explode("\n", $stdout),
                explode("\n", $stderr)
            ));
        }
        
        // Clean up Python script
        @unlink($pythonScript);
        
        // Check output messages
        $outputText = implode("\n", $output);
        
        // Check if execution was successful
        if (strpos($outputText, 'SUCCESS') !== false) {
            // Read extracted text from output file
            if (file_exists($outputFile)) {
                $text = file_get_contents($outputFile);
                @unlink($outputFile);
                
                if (!empty($text)) {
                    return trim($text);
                } else {
                    error_log('PaddleOCR: Output file was empty');
                }
            } else {
                error_log('PaddleOCR: Output file was not created: ' . $outputFile);
            }
        } else if (strpos($outputText, 'ERROR') !== false) {
            error_log('PaddleOCR Error: ' . $outputText);
        } else {
            error_log('PaddleOCR: Unexpected output - ' . $outputText);
        }
        
        // Clean up output file if it exists
        @unlink($outputFile);
        
        return '';
        
    } catch (Exception $e) {
        return '';
    }
}

?>
