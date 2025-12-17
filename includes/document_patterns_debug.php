<?php
/**
 * Debug version of document extraction with detailed logging
 * Shows which patterns matched for each field
 */

require_once __DIR__ . '/mrz_parser.php';

function extractIDDataDebug($text) {
    $debug_log = [];
    
    // First, try MRZ parsing
    $mrzData = parseMRZLines($text);
    if ($mrzData['mrz_valid']) {
        $mrzData['extraction_confidence'] = 0.95;
        $mrzData['document_type'] = 'id';
        $debug_log[] = "✅ MRZ parsing successful";
        return array_merge($mrzData, ['debug_log' => $debug_log]);
    }
    
    $debug_log[] = "❌ MRZ parsing failed: " . ($mrzData['mrz_debug']['status'] ?? 'Unknown');
    
    // Check if this looks like an Afghan identity card
    $isAfghanID = isAfghanIdentityCard($text);
    if ($isAfghanID) {
        $debug_log[] = "✅ Detected as Afghan Tazkira";
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
        'mrz_debug' => $mrzData['mrz_debug'] ?? [],
    ];
    
    // === Extract ID Number ===
    if (preg_match('/(?:ID|National\s+ID|Tazkira|شناسنامه|شماره\s+شناسنامه|د\s+شناسنامې|شماره|تذکره\s+تابعیت|د\s+تابعیت\s+تذکره|تذکره)[:\s]+([A-Z0-9\-]+)/iu', $text, $match)) {
        $data['id_number'] = trim($match[1]);
        $debug_log[] = "✅ ID Number (Pattern 1 - Explicit label): " . $data['id_number'];
    } elseif (preg_match('/(?:Number|شماره|نمبر)[:\s]+([A-Z0-9\-]+)/iu', $text, $match)) {
        $data['id_number'] = trim($match[1]);
        $debug_log[] = "✅ ID Number (Pattern 2 - Number field): " . $data['id_number'];
    } elseif (preg_match('/\b(\d{12})\b/i', $text, $match)) {
        $data['id_number'] = trim($match[1]);
        $debug_log[] = "✅ ID Number (Pattern 3 - 12 digits): " . $data['id_number'];
    } elseif (preg_match('/\b(\d{4}\-\d{4}\-\d{5})\b/i', $text, $match)) {
        $data['id_number'] = trim($match[1]);
        $debug_log[] = "✅ ID Number (Pattern 4 - Dashed): " . $data['id_number'];
    } else {
        $debug_log[] = "❌ ID Number: No pattern matched";
    }
    
    // === Extract Date of Birth ===
    if (preg_match('/(?:Date\s+of\s+(?:Birth|birth)|تاریخ\s+تولد|د\s+زیزې\s+نیته|Birth)[:\s/]*([^\n\r]+?)(?:\n|$|Gender|Place|جنسیت)/iu', $text, $match)) {
        $dateStr = trim($match[1]);
        if (preg_match('/(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/', $dateStr, $dateMatch)) {
            $data['date_of_birth'] = formatPassportDate($dateMatch[1], $dateMatch[2], $dateMatch[3]);
            $debug_log[] = "✅ Date of Birth (Pattern 1 - Date of Birth label): " . $data['date_of_birth'];
        }
    }
    
    if (empty($data['date_of_birth']) && preg_match('/\b(?:DOB|تولد)[:\s/]*([^\n\r]+?)(?:\n|$)/iu', $text, $match)) {
        $dateStr = trim($match[1]);
        if (preg_match('/(\d{1,2})[\/\-\s]+([A-Za-z]+|\d{1,2})[\/\-\s]+(\d{4})/', $dateStr, $dateMatch)) {
            $data['date_of_birth'] = formatPassportDate($dateMatch[1], $dateMatch[2], $dateMatch[3]);
            $debug_log[] = "✅ Date of Birth (Pattern 2 - DOB label): " . $data['date_of_birth'];
        }
    }
    
    if (empty($data['date_of_birth']) && preg_match('/(\d{1,2})\s+(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC)\s+(\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
        $debug_log[] = "✅ Date of Birth (Pattern 3 - Standalone month): " . $data['date_of_birth'];
    }
    
    if (empty($data['date_of_birth']) && preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/i', $text, $match)) {
        $data['date_of_birth'] = formatPassportDate($match[1], $match[2], $match[3]);
        $debug_log[] = "✅ Date of Birth (Pattern 4 - Numeric): " . $data['date_of_birth'];
    }
    
    if (empty($data['date_of_birth'])) {
        $debug_log[] = "❌ Date of Birth: No pattern matched";
    }
    
    // === Extract Full Name ===
    if (preg_match('/(?:Full\s+Name|Name\s*\/?\s*SURNAME|Name\s|نام\s+و\s+تخلص|نام\s*[:\s]|نوم\s*[:\s])[:\s]*([^\n\r:]+?)(?:\n|$|Father|Father\'s|پدر|پلار|Date|Place|تاریخ|د|Gender|جنسیت)/iu', $text, $match)) {
        $name = trim($match[1]);
        $name = preg_replace('/^\d+[\s\-]*/', '', $name);
        $name = preg_replace('/[\s\-]*\d+$/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        
        if (!preg_match('/^(ISLAMIC|REPUBLIC|NATIONAL|TAZKIRA|AFGHANISTAN|KABUL|افغانستان|اسلامی|جمهوری|SURNAME|GIVEN|FULL|FATHER|MOTHER|GUARDIAN|ADDRESS|DOB)/iu', $name) 
            && strlen($name) > 3 
            && preg_match('/[A-Za-zآ-ی]+/', $name)
            && !preg_match('/^\d/', $name)
            && isValidName($name)) {
            $data['full_name'] = $name;
            $debug_log[] = "✅ Full Name (Pattern 1 - Name/SURNAME label): " . $data['full_name'];
        } else {
            $debug_log[] = "⚠️  Full Name (Pattern 1) - Matched but failed validation: '$name'";
        }
    }
    
    if (empty($data['full_name']) && preg_match('/(?:Surname|Given\s+Names|نام|تخلص)[:\s]*\n?([^\n\r:]+?)(?:\n|$|Father|پدر|Date)/ium', $text, $match)) {
        $name = trim($match[1]);
        $name = preg_replace('/^\d+[\s\-]*/', '', $name);
        $name = preg_replace('/[\s\-]*\d+$/', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        if (!preg_match('/^(SURNAME|GIVEN|FULL|NUMBER|ID|DATE)/iu', $name) && strlen($name) > 3 && preg_match('/[A-Za-zآ-ی]+/', $name) && isValidName($name)) {
            $data['full_name'] = $name;
            $debug_log[] = "✅ Full Name (Pattern 2 - Surname/Given Names): " . $data['full_name'];
        }
    }
    
    if (empty($data['full_name'])) {
        $debug_log[] = "❌ Full Name: No pattern matched";
    }
    
    // === Extract Father's Name ===
    if (preg_match('/(?:Father|Father\'s|پدر|نام\s+پدر|پلار|د\s+پلار\s+نوم|FATHER)[:\s]*([^\n\r:]+?)(?:\n|$|Mother|مادر|د\s+مور|Guardian|سرپرست)/iu', $text, $match)) {
        $fname = trim($match[1]);
        $fname = preg_replace('/^\d+[\s\-]*/', '', $fname);
        $fname = preg_replace('/[\s\-]*\d+$/', '', $fname);
        if (!preg_match('/^(FATHER|MOTHER|GUARDIAN|DATE|NUMBER|GENDER)/iu', $fname) && strlen($fname) > 2) {
            $data['father_name'] = $fname;
            $debug_log[] = "✅ Father's Name: " . $data['father_name'];
        } else {
            $debug_log[] = "⚠️  Father's Name - Matched but failed validation: '$fname'";
        }
    } else {
        $debug_log[] = "❌ Father's Name: No pattern matched";
    }
    
    // === Extract Gender ===
    if (preg_match('/(?:Gender|Sex)[:\s]+(M|F|Male|Female)/i', $text, $match)) {
        $gender = strtoupper(substr($match[1], 0, 1));
        $data['gender'] = ($gender === 'M') ? 'Male' : ($gender === 'F' ? 'Female' : $match[1]);
        $debug_log[] = "✅ Gender (Pattern 1 - English): " . $data['gender'];
    } elseif (preg_match('/(?:جنسیت|د\s+جنس)[:\s]+(مرد|نارینه|زن|بنځې|ذکر|اناث)/iu', $text, $match)) {
        $genderText = strtolower(trim($match[1]));
        if (preg_match('/^(مرد|نارینه|ذکر)$/u', $genderText)) {
            $data['gender'] = 'Male';
        } elseif (preg_match('/^(زن|بنځې|اناث)$/u', $genderText)) {
            $data['gender'] = 'Female';
        } else {
            $data['gender'] = $match[1];
        }
        $debug_log[] = "✅ Gender (Pattern 2 - Dari/Pashto): " . $data['gender'];
    } else {
        $debug_log[] = "❌ Gender: No pattern matched";
    }
    
    // Calculate confidence score
    $filled = 0;
    $total = 5;
    if (!empty($data['id_number'])) $filled++;
    if (!empty($data['full_name'])) $filled++;
    if (!empty($data['date_of_birth'])) $filled++;
    if (!empty($data['father_name'])) $filled++;
    if (!empty($data['gender'])) $filled++;
    
    $data['extraction_confidence'] = ($filled / $total);
    
    $debug_log[] = "\n📊 Confidence Score: " . round($data['extraction_confidence'] * 100) . "% ($filled/$total fields)";
    
    $data['debug_log'] = $debug_log;
    return $data;
}

// Include required functions
require_once __DIR__ . '/document_patterns.php';

?>
