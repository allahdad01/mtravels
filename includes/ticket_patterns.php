<?php
/**
 * Complete Airline Ticket Extraction System
 * Handles Kam Air and standard IATA formats
 */

// ============================================
// LOOKUP TABLES
// ============================================

const AIRPORTS = [
    // Afghanistan
    'KBL' => 'Hamid Karzai International, Kabul',
    'HEA' => 'Herat International Airport',
    'MZR' => 'Mazar-i-Sharif International',
    'KDH' => 'Kandahar International',
    'JAM' => 'Jalalabad International',
    
    // UAE
    'DXB' => 'Dubai International',
    'AUH' => 'Abu Dhabi International',
    'SHJ' => 'Sharjah International',
    'RAK' => 'Ras Al Khaimah',
    'DWC' => 'Al Maktoum International, Dubai',
    'FUJ' => 'Fujairah',
    
    // Middle East
    'IST' => 'Istanbul International',
    'SAW' => 'Istanbul Sabiha Gökçen',
    'DOH' => 'Hamad International, Doha',
    'RUH' => 'King Khalid International, Riyadh',
    'JED' => 'King Abdulaziz, Jeddah',
    'BAH' => 'Bahrain International',
    'MCT' => 'Muscat International',
    'KWI' => 'Kuwait International',
    'BEY' => 'Beirut-Rafic Hariri',
    'AMM' => 'Queen Alia International, Amman',
    'BIA' => 'Baghdad International',
    
    // North Africa
    'CAI' => 'Cairo International',
    'ALX' => 'Borg El Arab, Alexandria',
    'HRG' => 'Hurghada International',
    'SHM' => 'Sharm El-Sheikh International',
    
    // Central Asia & Iran
    'IKA' => 'Imam Khomeini International, Tehran',
    'DME' => 'Moscow Domodedovo',
    'SVO' => 'Moscow Sheremetyevo',
    'VKO' => 'Moscow Vnukovo',
    'TAS' => 'Tashkent International',
    'BAK' => 'Heydar Aliyev, Baku',
    'GYD' => 'Ganja',
    'TBS' => 'Tbilisi International',
    'ALA' => 'Nursultan Nazarbayev, Almaty',
    'TSE' => 'Aktau International',
    
    // South Asia
    'DEL' => 'Indira Gandhi, Delhi',
    'BOM' => 'Mumbai International',
    'BLR' => 'Kempegowda, Bangalore',
    'MAA' => 'Chennai International',
    'HYD' => 'Rajiv Gandhi, Hyderabad',
    'COK' => 'Cochin International',
    'JAI' => 'Jaipur International',
    'AMD' => 'Sardar Vallabhbhai Patel, Ahmedabad',
    'PNQ' => 'Pune Airport',
    'KTM' => 'Tribhuvan International, Kathmandu',
    'KTY' => 'Kathmandu International',
    'DAC' => 'Hazrat Shahjalal, Dhaka',
    'CDA' => 'Shah Amanat, Chittagong',
    'KHI' => 'Jinnah International, Karachi',
    'LHE' => 'Allama Iqbal, Lahore',
    'ISB' => 'Benazir Bhutto, Islamabad',
    'PEW' => 'Peshawar International',
    'SKZ' => 'Sialkot International',
    'MUX' => 'Multan International',
    'HYD' => 'Hyderabad, Pakistan',
    
    // Southeast Asia
    'BKK' => 'Suvarnabhumi, Bangkok',
    'DMK' => 'Don Mueang, Bangkok',
    'CNX' => 'Chiang Mai International',
    'HAN' => 'Noi Bai, Hanoi',
    'SGN' => 'Tan Son Nhat, Ho Chi Minh',
    'SIN' => 'Singapore Changi',
    'KUL' => 'Kuala Lumpur International',
    'CGK' => 'Soekarno-Hatta, Jakarta',
    'MNL' => 'Ninoy Aquino, Manila',
    
    // East Asia
    'PEK' => 'Beijing Capital',
    'PVG' => 'Shanghai Pudong',
    'SHA' => 'Shanghai Hongqiao',
    'CTU' => 'Chengdu Shuangliu',
    'CAN' => 'Guangzhou Baiyun',
    'CKG' => 'Chongqing Jiangbei',
    'ICN' => 'Incheon, Seoul',
    'GMP' => 'Gimpo, Seoul',
    'NRT' => 'Narita, Tokyo',
    'HND' => 'Haneda, Tokyo',
    'KIX' => 'Kansai, Osaka',
    'HKG' => 'Hong Kong International',
    'TPE' => 'Taiwan Taoyuan',
    
    // Europe
    'LHR' => 'London Heathrow',
    'LGW' => 'London Gatwick',
    'STN' => 'London Stansted',
    'LTN' => 'London Luton',
    'CDG' => 'Paris Charles de Gaulle',
    'ORY' => 'Paris Orly',
    'FRA' => 'Frankfurt',
    'MUC' => 'Munich',
    'BER' => 'Berlin Brandenburg',
    'TXL' => 'Berlin Tegel',
    'DUS' => 'Düsseldorf',
    'COL' => 'Cologne Bonn',
    'HAM' => 'Hamburg',
    'AMS' => 'Amsterdam Schiphol',
    'BRU' => 'Brussels',
    'ZRH' => 'Zurich',
    'VIE' => 'Vienna',
    'PRG' => 'Prague',
    'WAW' => 'Warsaw Chopin',
    'BUD' => 'Budapest Ferenc Liszt',
    'BTS' => 'Bratislava',
    'ZGH' => 'Zagreb',
    'BEG' => 'Belgrade',
    'ATH' => 'Athens',
    'ROM' => 'Rome Fiumicino',
    'FCO' => 'Rome Fiumicino',
    'MIL' => 'Milan Malpensa',
    'MXP' => 'Milan Malpensa',
    'LIN' => 'Milan Linate',
    'MAD' => 'Madrid',
    'BCN' => 'Barcelona',
    'LIS' => 'Lisbon',
    'AGP' => 'Málaga',
    'SVX' => 'Ekaterinburg',
    'NOV' => 'Novosibirsk',
    'KZN' => 'Kazan',
    'ROV' => 'Rostov',
    'AER' => 'Sochi',
    'DXO' => 'Domodedovo',
    
    // North America
    'JFK' => 'New York JFK',
    'EWR' => 'Newark',
    'LGA' => 'LaGuardia, New York',
    'ORD' => 'Chicago O\'Hare',
    'MDW' => 'Chicago Midway',
    'LAX' => 'Los Angeles',
    'SFO' => 'San Francisco',
    'SEA' => 'Seattle-Tacoma',
    'DEN' => 'Denver',
    'DFW' => 'Dallas-Fort Worth',
    'ATL' => 'Atlanta',
    'MIA' => 'Miami',
    'BOS' => 'Boston',
    'IAD' => 'Washington Dulles',
    'DCA' => 'Washington Reagan',
    'YYZ' => 'Toronto Pearson',
    'YVR' => 'Vancouver',
    
    // Add more as needed
];

const AIRLINES = [
    'RQ' => 'Kam Air',
    'FG' => 'Ariana Afghan Airlines',
    'EK' => 'Emirates',
    'QR' => 'Qatar Airways',
    'TK' => 'Turkish Airlines',
    'PK' => 'Pakistan International Airlines',
    'AI' => 'Air India',
    'CA' => 'Air China',
    'BA' => 'British Airways',
    'AA' => 'American Airlines',
    'FZ' => 'Fly Dubai',
    // Add more as needed
];

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get airport name from IATA code
 */
function getAirportName($code) {
    $code = strtoupper(trim($code));
    return AIRPORTS[$code] ?? $code;
}

/**
 * Get airline name from IATA code
 */
function getAirlineName($code) {
    $code = strtoupper(trim($code));
    return AIRLINES[$code] ?? $code;
}

/**
 * Parse date in various formats
 */
function parseTicketDate($day, $month, $year = null) {
    // If month is numeric (DD/MM/YYYY format)
    if (is_numeric($month)) {
        $monthNum = (int)$month;
        $dayNum = (int)$day;
        $yearNum = (int)$year;
    } else {
        // Month is text (DD MMM YYYY format)
        $months = [
            'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6,
            'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12,
        ];
        
        $monthNum = $months[strtolower(substr($month, 0, 3))] ?? null;
        if (!$monthNum) return null;
        
        $dayNum = (int)$day;
        $yearNum = $year ? (int)$year : date('Y');
    }
    
    // Validate the date
    if (!checkdate($monthNum, $dayNum, $yearNum)) {
        return null;
    }
    
    return sprintf('%04d-%02d-%02d', $yearNum, $monthNum, $dayNum);
}

/**
 * Parse time
 */
function parseTicketTime($hour, $minute) {
    return sprintf('%02d:%02d', (int)$hour, (int)$minute);
}

/**
 * Calculate confidence score (0-1)
 */
function calculateConfidenceScore($data) {
    $score = 0;
    $weight = 0;
    
    // Critical fields (3x weight)
    $critical = ['pnr', 'passenger_name', 'airline', 'origin', 'destination', 'departure_date'];
    foreach ($critical as $field) {
        $weight += 3;
        if (!empty($data[$field])) {
            $score += 3;
        }
    }
    
    // Important fields (2x weight)
    $important = ['ticket_number', 'departure_time', 'flight_number'];
    foreach ($important as $field) {
        $weight += 2;
        if (!empty($data[$field])) {
            $score += 2;
        }
    }
    
    // Optional fields (1x weight)
    $optional = ['cabin_class', 'baggage_allowance', 'issue_date', 'arrival_time'];
    foreach ($optional as $field) {
        $weight += 1;
        if (!empty($data[$field])) {
            $score += 1;
        }
    }
    
    return $weight > 0 ? round(($score / $weight) * 100) / 100 : 0;
}

/**
 * Normalize passenger name
 */
function normalizePassengerName($name) {
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name);
    return $name;
}

// ============================================
// MAIN EXTRACTION FUNCTIONS
// ============================================

/**
 * Normalize ticket extraction result to standard grouped format
 */
function normalizeTicketResponse($result, $format = 'unknown') {
    // If already in grouped format with passengers array, return as-is
    if (isset($result['is_group_booking']) && isset($result['passengers']) && is_array($result['passengers'])) {
        return [
            'success' => true,
            'message' => 'Ticket extracted successfully',
            'data' => $result,
            'format_detected' => $result['format_detected'] ?? $format,
            'extracted_text_preview' => null, // Will be set by API
            'confidence' => null, // Will be calculated by API
        ];
    }
    
    // If it's a single passenger result, convert to grouped format
    if (!empty($result) && !isset($result['is_group_booking'])) {
        // Check if it has passenger data
        if (isset($result['passenger_name']) || isset($result['pnr']) || isset($result['ticket_number'])) {
            $grouped = [
                'is_group_booking' => false,
                'booking_reference' => $result['pnr'] ?? $result['booking_reference'] ?? null,
                'total_passengers' => 1,
                'passengers' => [$result],
                'flight_info' => extractCommonFlightInfo([$result]),
            ];
            
            return [
                'success' => true,
                'message' => 'Ticket extracted successfully',
                'data' => $grouped,
                'format_detected' => $result['format_detected'] ?? $format,
                'extracted_text_preview' => null,
                'confidence' => null,
            ];
        }
    }
    
    // Multi-segment or special format
    if (isset($result['is_multi_segment'])) {
        return [
            'success' => true,
            'message' => 'Ticket extracted successfully',
            'data' => $result,
            'format_detected' => $result['format_detected'] ?? $format,
            'extracted_text_preview' => null,
            'confidence' => null,
        ];
    }
    
    // Fallback: return empty grouped structure
    return [
        'success' => true,
        'message' => 'Ticket extracted successfully',
        'data' => [
            'is_group_booking' => false,
            'booking_reference' => null,
            'total_passengers' => 0,
            'passengers' => [],
            'flight_info' => [],
        ],
        'format_detected' => $format,
        'extracted_text_preview' => null,
        'confidence' => 0,
    ];
}

/**
 * Main extraction function with format detection
 */
function extractTicketData($text) {
    $text = cleanTicketText($text);
    
    // Detect format
    $format = detectTicketFormat($text);
    
    // Extract based on format
    $result = null;
    switch ($format) {
        case 'tolotripportal':
            $result = extractTolotripportalTicket($text);
            break;
        case 'salaamportal':
            $result = extractSalaamPortalTicket($text);
            break;
        case 'merajair':
            $result = extractMerajAirTicket($text);
            break;
        case 'haditaportal':
            $result = extractHaditaportalTicket($text);
            break;
        case 'flydubai':
            $result = extractFlydubaiTicket($text);
            break;
        case 'kamair':
            $result = extractKamAirTicket($text);
            break;
        case 'ariana':
            $result = extractArianaTicket($text);
            break;
        case 'airarabia':
            $result = extractAirArabiaTicket($text);
            break;
        case 'skyportal':
            $result = extractSkyportalTicket($text);
            break;
        case 'nsstportal':
            $result = extractNSSPortalTicket($text);
            break;
        default:
            $result = extractStandardTicket($text);
            break;
    }
    
    // Ensure result is in grouped format
    if (!isset($result['is_group_booking'])) {
        // Single passenger or needs conversion
        if (!empty($result) && is_array($result)) {
            if (isset($result['passenger_name']) || isset($result['pnr']) || isset($result['ticket_number'])) {
                // Single passenger object - convert to grouped format
                $result = [
                    'is_group_booking' => false,
                    'booking_reference' => $result['pnr'] ?? $result['booking_reference'] ?? null,
                    'total_passengers' => 1,
                    'passengers' => [$result],
                    'flight_info' => extractCommonFlightInfo([$result]),
                    'format_detected' => $result['format_detected'] ?? $format,
                ];
            } elseif (!isset($result['is_multi_segment']) && !isset($result['passengers'])) {
                // Unknown format - wrap as empty
                $result = [
                    'is_group_booking' => false,
                    'booking_reference' => null,
                    'total_passengers' => 0,
                    'passengers' => [],
                    'flight_info' => [],
                    'format_detected' => $format,
                ];
            }
        } else {
            // Null or invalid - return empty structure
            $result = [
                'is_group_booking' => false,
                'booking_reference' => null,
                'total_passengers' => 0,
                'passengers' => [],
                'flight_info' => [],
                'format_detected' => $format,
            ];
        }
    }
    
    // Ensure format_detected is present
    if (!isset($result['format_detected'])) {
        $result['format_detected'] = $format;
    }
    
    return $result;
}

/**
 * Detect ticket format
 */
function detectTicketFormat($text) {
    // Hadita Portal e-ticket indicators (check first - most specific)
    if (preg_match('/voucherID|Counterman\s+Software|haditatrip\.com/i', $text)) {
        return 'haditaportal';
    }
    
    // Air Arabia e-ticket indicators (check before salaamportal - more specific)
    if (preg_match('/AirRewards|Sky\s+Cafe|All\s+rights\s+reserved.*Air\s+Arabia|airarabia\.com|© \d{4}\s+Air\s+Arabia/i', $text)) {
        return 'airarabia';
    }
    
    // NSSTravel Portal e-ticket indicators (check before salaamportal - has overlapping patterns)
    // Unique NST markers: "Super PNR no", "Agent Reference", "Passenger Information"
    if (preg_match('/Super\s+PNR\s+no|Agent\s+Reference.*\(FZ\)|Passenger\s+Information.*Segment\s+Baggage/i', $text)) {
        return 'nsstportal';
    }
    
    // Salaam Portal e-ticket indicators (check before tolotripportal)
    // Unique patterns: "Traveler: MR", "E-Ticket Number:", specific "Airline PNR:" format
    if (preg_match('/Traveler:\s+MR\s+[A-Z]|E-Ticket\s+Number:\d|(?:Airline\s+PNR:\s*[A-Z0-9]+\s*$)(?!.*Agent\s+Reference)/im', $text)) {
        return 'salaamportal';
    }
    
    // Kam Air specific indicators (check BEFORE tolotripportal — kamair.com tickets contain
    // "Electronic Ticket PASSENGER ITINERARY RECEIPT" which would false-match tolotripportal)
    if (preg_match('/kamair\.com|ALMOQADAS_TRAVEL|RQ\s+KBL|CARRIER CODE\s+RQ/i', $text)) {
        return 'kamair';
    }
    
    // Tolotripportal e-ticket indicators (more specific pattern to avoid false matches)
    if (preg_match('/Order\s+no\s*:|Accounting\s+No|ELECTRONIC\s+TICKET\s+PASSENGER\s+ITINERARY\s+RECEIPT(?!.*voucherID|.*haditatrip)/is', $text)) {
        return 'tolotripportal';
    }
    
    // Meraj Air e-ticket indicators (check after Hadita)
    // Must have Local PNR or Flight Date + Meraj Air
    if (preg_match('/Local\s+PNR|Flight\s+Date.*Flight\s+time.*Meraj\s+Air/i', $text)) {
        return 'merajair';
    }
    
    // Flydubai e-ticket indicators
    if (preg_match('/flydubai\s+booking\s+reference|© flydubai|FZ\s+\d+\/FZ\s+\d+|Your\s+booking\s+is\s+confirmed/i', $text)) {
        return 'flydubai';
    }
    
    // Ariana Afghan Airlines indicators. Check before generic itinerary/receipt language.
    if (preg_match('/e-ticket.*BOOKING\s*#|FG-\d+\s+ECONOMY|Ariana\s+Afghan|fare\s+family\s+fare\s+basis/i', $text)) {
        return 'ariana';
    }
    
    // Skyportal e-ticket indicators (IATA receipt portal with traveller/flight details)
    if (preg_match('/Booking\s+Reference\s+number\s*:|GDS\s+PNR\s*:|Traveller\s+Details.*Flight\s+Details|warsaw\s+convention|article\s+3|conditions\s+of\s+carriage|iatatravelcentre/i', $text)) {
        return 'skyportal';
    }
    
    return 'standard';
}

/**
 * Extract Tolotripportal e-ticket format
 */
function extractTolotripportalTicket($text) {
    $passengers = [];
    
    // Extract issue date - pattern: "Issue Date : 21/09/2025 11:02"
    $issueDate = null;
    if (preg_match('/Issue\s+Date\s*:\s*(\d{2})\/(\d{2})\/(\d{4})/i', $text, $match)) {
        $issueDate = parseTicketDate($match[1], $match[2], $match[3]);
    }
    
    // Extract issued by - pattern: "Issued By : AL-MUQADAS TRAVEL & TOURIST"
    $issuedBy = null;
    if (preg_match('/Issued\s+By\s*:\s*([^\n]+)/i', $text, $match)) {
        $issuedBy = trim($match[1]);
    }
    
    // Extract order/ticket number - pattern: "Order no : 2352278179196"
    $ticketNumber = null;
    if (preg_match('/Order\s+no\s*:\s*(\d+)/i', $text, $match)) {
        $ticketNumber = $match[1];
    }
    
    // Extract passenger name - pattern: "Passenger Name : AZMATULLAH OMAR"
    $passengerName = null;
    if (preg_match('/Passenger\s+Name\s*:\s*([^\n]+)/i', $text, $match)) {
        $passengerName = normalizePassengerName($match[1]);
    }
    
    // Extract PNR - pattern: "PNR : 5PLA09XN7P"
    $pnr = null;
    if (preg_match('/PNR\s*:\s*([A-Z0-9]+)/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // Extract airline - pattern: "Turkish Airlines"
    $airline = null;
    $airlineCode = null;
    if (preg_match('/^(Turkish|Lufthansa|Emirates|Qatar|British|Fly\s+Dubai|Kam\s+Air|Ariana|Air\s+Arabia|Meraj)\s+([A-Z][a-zA-Z\s]+)$/m', $text, $match)) {
        $airline = trim($match[1] . ' ' . $match[2]);
    }
    
    // Extract airline code - pattern: "TK1591" (airline code before flight number)
    if (preg_match('/([A-Z]{2})(\d{3,5})/i', $text, $match)) {
        $airlineCode = $match[1];
        if (!$airline) {
            $airline = getAirlineName($airlineCode);
        }
    }
    
    // Extract flight number - pattern: "TK1591"
    $flightNumber = null;
    if (preg_match('/([A-Z]{2})(\d{3,5})/i', $text, $match)) {
        $flightNumber = $match[1] . $match[2];
    }
    
    // Extract cabin class - pattern: "E (Economy)"
    $cabinClass = null;
    if (preg_match('/\(([^)]+)\)\s*\d{2}\/\d{2}\/\d{4}/i', $text, $match)) {
        $cabinClass = trim($match[1]);
    }
    
    // Extract baggage - pattern: "0 K" (0 means free baggage type K)
    $baggage = null;
    if (preg_match('/(\d+)\s+([A-Z])\s+[A-Z0-9]+\s*$/m', $text, $match)) {
        $baggage = $match[1] . ' ' . $match[2];
    }
    
    // Extract route and times - pattern: "Istanbul Arpt (IST)" and "Frankfurt Intl (FRA)"
    $origin = null;
    $destination = null;
    $originCity = null;
    $destinationCity = null;
    $departureDate = null;
    $departureTime = null;
    $arrivalDate = null;
    $arrivalTime = null;
    
    // Extract from/to airports - pattern: "Istanbul Arpt (IST),Istanbul" to "Frankfurt Intl (FRA),Frankfurt"
    if (preg_match('/([A-Z][A-Za-z\s]+)\s*\(([A-Z]{3})\),[^\n]*?\n\s*([A-Z][A-Za-z\s]+)\s*\(([A-Z]{3})\)/i', $text, $match)) {
        $origin = strtoupper($match[2]);
        $originCity = getAirportName($origin);
        $destination = strtoupper($match[4]);
        $destinationCity = getAirportName($destination);
    }
    if ((!$origin || !$destination) && preg_match('/Airline\s+From\/To.*?\(([A-Z]{3})\),[^\n]*?\(([A-Z]{3})\),/is', $text, $match)) {
        $origin = strtoupper($match[1]);
        $originCity = getAirportName($origin);
        $destination = strtoupper($match[2]);
        $destinationCity = getAirportName($destination);
    }
    
    // Extract departure and arrival times - pattern: "04/10/2025 11:30" and "04/10/2025 13:40"
    if (preg_match('/([A-Z]{2})(\d{3,5})\s+[A-Z]\s+\(([A-Za-z\s]+)\)\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/i', $text, $match)) {
        // Departure date/time
        $departureDate = parseTicketDate($match[4], $match[5], $match[6]);
        $departureTime = sprintf('%02d:%02d', $match[7], $match[8]);
        
        // Arrival date/time
        $arrivalDate = parseTicketDate($match[9], $match[10], $match[11]);
        $arrivalTime = sprintf('%02d:%02d', $match[12], $match[13]);
    }
    if (!$departureDate && preg_match('/\b([A-Z]{2}\d{3,5})\s+[A-Z]\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})\s+(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/i', $text, $match)) {
        $departureDate = parseTicketDate($match[2], $match[3], $match[4]);
        $departureTime = sprintf('%02d:%02d', $match[5], $match[6]);
        $arrivalDate = parseTicketDate($match[7], $match[8], $match[9]);
        $arrivalTime = sprintf('%02d:%02d', $match[10], $match[11]);
    }
    
    // Build passenger record
    if (!empty($ticketNumber) || !empty($flightNumber)) {
        $passenger = [];
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $pnr;
        $passenger['ticket_number'] = $ticketNumber;
        
        // Flight details
        $passenger['airline'] = $airline;
        $passenger['airline_code'] = $airlineCode;
        $passenger['flight_number'] = $flightNumber;
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        
        // Route
        $passenger['origin'] = $origin;
        $passenger['origin_city'] = $originCity;
        $passenger['destination'] = $destination;
        $passenger['destination_city'] = $destinationCity;
        
        // Times
        $passenger['departure_date'] = $departureDate;
        $passenger['departure_time'] = $departureTime;
        $passenger['arrival_date'] = $arrivalDate;
        $passenger['arrival_time'] = $arrivalTime;
        
        // Additional info
        $passenger['baggage_allowance'] = $baggage;
        $passenger['issue_date'] = $issueDate;
        $passenger['issued_by'] = $issuedBy;
        $passenger['ticket_status'] = 'Confirmed';
        $passenger['is_confirmed'] = true;
        $passenger['trip_type'] = 'One Way';
        
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'tolotripportal';
        
        if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Always return grouped format for consistency
    if (!empty($passengers)) {
        return [
            'is_group_booking' => count($passengers) > 1,
            'booking_reference' => $passengers[0]['pnr'] ?? null,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
        ];
    }
    
    // No passengers found - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => null,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
    ];
}

/**
 * Extract Salaam Portal e-ticket format
 */
function extractSalaamPortalTicket($text) {
    $passengers = [];
    
    // Extract E-Ticket Number as booking reference - pattern: "E-Ticket Number:7J56OV"
    $bookingRef = null;
    if (preg_match('/E-Ticket\s+Number:\s*([A-Z0-9]+)/i', $text, $match)) {
        $bookingRef = $match[1];
    }
    
    // Extract PNR - pattern: "Airline PNR:7J56OV"
    $pnr = null;
    if (preg_match('/Airline\s+PNR:\s*([A-Z0-9]+)/i', $text, $match)) {
        $pnr = $match[1];
    } elseif (preg_match('/GDS\s+PNR\s*:\s*([A-Z0-9]+)/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // Extract booking date - pattern: "Date Of issue:09 Nov 2025"
    $bookingDate = null;
    if (preg_match('/Date\s+Of\s+issue:\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i', $text, $match)) {
        $bookingDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
    } elseif (preg_match('/Booking\s+Date\s*:\s*\w+,\s+(\w+)\s+(\d{1,2}),\s+(\d{4})/i', $text, $match)) {
        $bookingDate = parseTicketDate($match[2], substr($match[1], 0, 3), $match[3]);
    }
    
    // Extract trip type - pattern: "Trip Type : OneWay"
    $tripType = null;
    if (preg_match('/Trip\s+Type\s*:\s*([A-Za-z\s]+?)(?:\n|$)/i', $text, $match)) {
        $tripType = trim($match[1]);
    }
    
    // Extract route from format like "NQZ To DXB on 10 Dec 2025"
    $origin = null;
    $destination = null;
    $originCity = null;
    $destinationCity = null;
    if (preg_match('/([A-Z]{3})\s+To\s+([A-Z]{3})\s+on/i', $text, $match)) {
        $origin = strtoupper($match[1]);
        $destination = strtoupper($match[2]);
        $originCity = getAirportName($origin);
        $destinationCity = getAirportName($destination);
    } elseif (preg_match('/Trip\s+Name\s*:\s*([A-Za-z\s]+?)\(([A-Z]{3})\)\s+to\s+([A-Za-z\s]+?)\(([A-Z]{3})\)/i', $text, $match)) {
        $origin = strtoupper($match[2]);
        $originCity = trim($match[1]);
        $destination = strtoupper($match[4]);
        $destinationCity = trim($match[3]);
    }
    
    // Extract agency/issued by - pattern: "Staff Name :Abdul Qadar Sabawoon" or "Office" section
    $issuedBy = null;
    if (preg_match('/Staff\s+Name\s*:\s*([^\n]+)/i', $text, $match)) {
        $issuedBy = trim($match[1]);
    } elseif (preg_match('/Office\s+.*?\n\s*([^\n]+?)\n.*?Kabul/i', $text, $match)) {
        $issuedBy = trim($match[1]);
    }
    
    // Extract status - pattern: "Status : confirmed"
    $status = null;
    if (preg_match('/Status\s*:\s*(\w+)/i', $text, $match)) {
        $status = $match[1];
    }
    
    // Extract currency - pattern: "USD" at top
    $currency = null;
    if (preg_match('/^(USD|AFN|EUR|GBP)/m', $text, $match)) {
        $currency = $match[1];
    }
    
    // Extract pricing
    $baseFare = null;
    $taxes = null;
    $totalFare = null;
    
    if (preg_match('/Grand\s+Total\s*:\s*(\d+(?:\.\d{2})?)/i', $text, $match)) {
        $totalFare = $match[1];
    }
    if (preg_match('/Other\s+Taxes:\s*(\d+(?:\.\d{2})?)/i', $text, $match)) {
        $taxes = $match[1];
    }
    
    // Extract airline - look for airline name pattern in flight details
    // Pattern: "AirlineName (CODE)" on same line, avoiding multi-line matches
    $airline = null;
    $airlineCode = null;
    
    // First try: Look for airline in format "NAME (CODE)" at start of line (flight details section)
    if (preg_match('/^\s*([A-Z][A-Za-z\s]{2,25}?)\s+\(([A-Z]{2})\)\s+[-–]?\s*(Economy|Business|First|Premium)/m', $text, $match)) {
        $airline = trim($match[1]);
        $airlineCode = $match[2];
    }
    
    // Fallback: Look for known airline patterns like "FLY DUBAI (FZ)"
    if (!$airline && preg_match('/(FLY\s+DUBAI|EMIRATES|QATAR\s+AIRWAYS|TURKISH\s+AIRLINES|KAM\s+AIR|ARIANA|AIR\s+ARABIA)\s*\(([A-Z]{2})\)/i', $text, $match)) {
        $airline = strtoupper($match[1]);
        $airlineCode = $match[2];
        // Normalize the airline name
        $airline = ucwords(strtolower($airline));
    }
    
    // Extract flight details from Flight Details section
    $flightNumber = null;
    $departureTime = null;
    $departureDate = null;
    $departureTerminal = null;
    $arrivalTime = null;
    $arrivalDate = null;
    $arrivalTerminal = null;
    $cabinClass = null;
    $duration = null;
    
    // Extract flight number from format like "FLY DUBAI (FZ) 1304"
    if (preg_match('/\(([A-Z]{2})\)\s+(\d{3,4})/i', $text, $match)) {
        $flightNumber = $match[2];
    } elseif (preg_match('/Flight\s+No.*?(\d{3,4})/i', $text, $match)) {
        $flightNumber = $match[1];
    }
    
    // Extract departure details from "Depart:" section
    // Pattern: Depart: CITY\nDate: DD MMM YYYY\nTime: HH:MM
    if (preg_match('/Depart:.*?Date:\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4}).*?Time:\s*(\d{2}):(\d{2})/is', $text, $match)) {
        $departureDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
        $departureTime = sprintf('%02d:%02d', $match[4], $match[5]);
        
        // Extract departure terminal if available
        $depSection = substr($text, strpos($text, 'Depart:'), 300);
        if (preg_match('/Terminal:\s*([^\n]+)/i', $depSection, $termMatch)) {
            $departureTerminal = trim($termMatch[1]);
        }
    }
    
    // Extract arrival details from "Arrive:" section
    // Pattern: Arrive: CITY\nDate: DD MMM YYYY\nTime: HH:MM
    if (preg_match('/Arrive:.*?Date:\s*(\d{1,2})\s+([A-Za-z]+)\s+(\d{4}).*?Time:\s*(\d{2}):(\d{2})/is', $text, $match)) {
        $arrivalDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
        $arrivalTime = sprintf('%02d:%02d', $match[4], $match[5]);
        
        // Extract arrival terminal if available
        $arrSection = substr($text, strpos($text, 'Arrive:'), 300);
        if (preg_match('/Terminal:\s*([^\n]+)/i', $arrSection, $termMatch)) {
            $arrivalTerminal = trim($termMatch[1]);
        }
    }
    
    // Extract duration - pattern: "3h10m"
    if (preg_match('/Duration.*?(\d+)h(\d+)m/i', $text, $match)) {
        $duration = $match[1] . 'h' . $match[2] . 'm';
    }
    
    // Extract cabin class - pattern: "Economy (H)"
    if (preg_match('/Economy\s*\([A-Z]\)|\(([A-Z])\).*?Economy/i', $text, $match)) {
        $cabinClass = 'Economy';
    } elseif (preg_match('/(Business|First|Premium)/i', $text, $match)) {
        $cabinClass = $match[1];
    }
    
    // Extract passenger info - pattern: "Traveler: MR IBRAHIM NAZARI"
    $passengerExtracted = false;
    
    // Try first to extract from simple format "Traveler: MR NAME"
    if (preg_match('/Traveler:\s+(MR|MS|MRS|DR)\s+([A-Z\s]+)/i', $text, $match)) {
        $passenger = [];
        
        // Passenger info
        $passengerName = normalizePassengerName(trim($match[2]));
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $pnr;
        $passenger['ticket_number'] = $bookingRef;
        $passenger['passenger_type'] = 'ADT'; // Default to Adult
        
        // Flight details - extract airline from format like "FLY DUBAI (FZ)"
        if (preg_match('/([A-Za-z\s]+?)\s*\(([A-Z]{2})\)(?:\s+(\d{3,4}))?/i', $text, $airlineMatch)) {
            $airline = trim($airlineMatch[1]);
            $airlineCode = $airlineMatch[2];
            if (!empty($airlineMatch[3])) {
                $flightNumber = $airlineMatch[3];
            }
        }
        
        $passenger['airline'] = $airline;
        $passenger['airline_code'] = $airlineCode;
        $passenger['flight_number'] = $flightNumber;
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        
        // Route
        $passenger['origin'] = $origin;
        $passenger['origin_city'] = $originCity;
        $passenger['destination'] = $destination;
        $passenger['destination_city'] = $destinationCity;
        
        // Times
        $passenger['departure_date'] = $departureDate;
        $passenger['departure_time'] = $departureTime;
        $passenger['departure_terminal'] = $departureTerminal;
        $passenger['arrival_date'] = $arrivalDate;
        $passenger['arrival_time'] = $arrivalTime;
        $passenger['arrival_terminal'] = $arrivalTerminal;
        
        // Baggage - look for pattern like "0 KG"
        if (preg_match('/Baggage[:\s]+(\d+\s*KG)/i', $text, $bagMatch)) {
            $passenger['baggage_allowance'] = trim($bagMatch[1]);
        }
        
        // Additional info
        $passenger['issue_date'] = $bookingDate;
        $passenger['issued_by'] = $issuedBy;
        $passenger['ticket_status'] = ucfirst(strtolower($status ?? 'Confirmed'));
        $passenger['is_confirmed'] = strtolower($status ?? 'confirmed') === 'confirmed';
        $passenger['currency'] = $currency ?? 'USD';
        $passenger['price'] = $totalFare;
        $passenger['trip_type'] = $tripType ?? 'One Way';
        $passenger['duration'] = $duration;
        
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'salaamportal';
        
        $passengers[] = $passenger;
        $passengerExtracted = true;
    }
    
    // Alternative extraction if main method fails - look for passenger rows with simpler approach
    if (!$passengerExtracted && preg_match('/Traveller\s+Details/i', $text)) {
        // Get text after "Traveller Details" header, specifically after the column headers
        // Extract everything after the "Pax Name" header line until "Flight Details" or end
        if (preg_match('/Traveller\s+Details\s+.*?(?:Pax\s+Name.*?(?:Status|Bag)\s*\n)(.*?)(?:Flight\s+Details|$)/is', $text, $section)) {
            $travellersSection = trim($section[1]);
            // Look for lines with passenger name pattern (all caps with spaces) followed by ADT/CHD/INF
            // This should match passenger data lines only, not header lines
            if (preg_match_all('/^([A-Z][A-Z\s]+?)\s+(ADT|CHD|INF)\s+(.+)$/m', $travellersSection, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $passengerName = normalizePassengerName(trim($match[1]));
                    $passengerType = $match[2];
                    $dataLine = $match[3]; // Rest of the line with route, ticket, etc.
                    
                    // Parse the data line to extract route, ticket number, PNR, baggage
                    // Typical format: "KBL : DXB    3842407616589 18PE7P 50KGconfirmed"
                    preg_match('/([A-Z]{3}\s*:\s*[A-Z]{3})\s+(\d{10,15})\s+([A-Z0-9]+)\s+(\d+KG)/', $dataLine, $dataParts);
                    
                    if (!empty($dataParts)) {
                        $passenger = [];
                        $passenger['passenger_name'] = $passengerName;
                        $passenger['passenger_type'] = $passengerType;
                        $passenger['pnr'] = $pnr ?? $dataParts[3];
                        $passenger['ticket_number'] = $dataParts[2];
                        
                        // Flight details
                        $passenger['airline'] = $airline;
                        $passenger['airline_code'] = $airlineCode;
                        $passenger['flight_number'] = $flightNumber;
                        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
                        
                        // Route
                        $passenger['origin'] = $origin;
                        $passenger['origin_city'] = $originCity;
                        $passenger['destination'] = $destination;
                        $passenger['destination_city'] = $destinationCity;
                        
                        // Times
                        $passenger['departure_date'] = $departureDate;
                        $passenger['departure_time'] = $departureTime;
                        $passenger['departure_terminal'] = $departureTerminal;
                        $passenger['arrival_date'] = $arrivalDate;
                        $passenger['arrival_time'] = $arrivalTime;
                        $passenger['arrival_terminal'] = $arrivalTerminal;
                        
                        // Baggage
                        if (preg_match('/(\d+)KG/i', $dataParts[4], $bagMatch)) {
                            $passenger['baggage_allowance'] = $bagMatch[0];
                        }
                        
                        // Additional info
                        $passenger['issue_date'] = $bookingDate;
                        $passenger['issued_by'] = $issuedBy;
                        $passenger['ticket_status'] = ucfirst(strtolower($status ?? 'Confirmed'));
                        $passenger['is_confirmed'] = strtolower($status ?? 'confirmed') === 'confirmed';
                        $passenger['currency'] = $currency ?? 'USD';
                        $passenger['price'] = $totalFare;
                        $passenger['trip_type'] = $tripType ?? 'One Way';
                        $passenger['duration'] = $duration;
                        
                        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
                        $passenger['format_detected'] = 'salaamportal';
                        
                        $passengers[] = $passenger;
                        $passengerExtracted = true;
                    }
                }
            }
        }
    }
    
    // Fallback: if main extraction didn't find passengers
    if (!$passengerExtracted) {
        // Try to extract passenger name and ticket from traveller details header or basic fields
        $passengerName = null;
        $ticketNumber = null;
        
        // Try to find passenger name - look for line with all caps name followed by ADT/CHD/INF
        // Must find a line that starts with a name (uppercase letters and spaces) followed by passenger type
        if (preg_match('/\n\s*([A-Z][A-Z\s]{5,}?)\s+(ADT|CHD|INF)\s+[A-Z]{3}\s*:\s*[A-Z]{3}/i', $text, $match)) {
            $passengerName = normalizePassengerName(trim($match[1]));
        } elseif (preg_match('/Traveler:\s*(.+?)(?:\n|$)/i', $text, $match)) {
            $passengerName = normalizePassengerName($match[1]);
        }
        
        // Try to find ticket number
        if (preg_match('/Ticket\s*(?:Number)?\s*:\s*(\d{10,15})|E-Ticket\s+Number:\s*([A-Z0-9]+)/i', $text, $match)) {
            $ticketNumber = $match[1] ?? $match[2];
        }
        
        // Create a passenger record if we have essential info
        if ((!empty($ticketNumber) || !empty($flightNumber) || !empty($pnr)) && (!empty($origin) && !empty($destination))) {
            $passenger = [];
            $passenger['passenger_name'] = $passengerName;
            $passenger['pnr'] = $pnr ?? $bookingRef;
            $passenger['ticket_number'] = $ticketNumber;
            
            $passenger['airline'] = $airline;
            $passenger['airline_code'] = $airlineCode;
            $passenger['flight_number'] = $flightNumber;
            $passenger['cabin_class'] = $cabinClass ?? 'Economy';
            
            $passenger['origin'] = $origin;
            $passenger['origin_city'] = $originCity;
            $passenger['destination'] = $destination;
            $passenger['destination_city'] = $destinationCity;
            
            $passenger['departure_date'] = $departureDate;
            $passenger['departure_time'] = $departureTime;
            $passenger['departure_terminal'] = $departureTerminal;
            $passenger['arrival_date'] = $arrivalDate;
            $passenger['arrival_time'] = $arrivalTime;
            $passenger['arrival_terminal'] = $arrivalTerminal;
            
            $passenger['issue_date'] = $bookingDate;
            $passenger['issued_by'] = $issuedBy;
            $passenger['ticket_status'] = ucfirst(strtolower($status ?? 'Confirmed'));
            $passenger['is_confirmed'] = strtolower($status ?? 'confirmed') === 'confirmed';
            $passenger['currency'] = $currency ?? 'USD';
            $passenger['price'] = $totalFare;
            $passenger['trip_type'] = $tripType ?? 'One Way';
            $passenger['duration'] = $duration;
            
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'salaamportal';
            
            $passengers[] = $passenger;
        }
    }
    
    // Always return grouped format for consistency
    if (!empty($passengers)) {
        return [
            'is_group_booking' => count($passengers) > 1,
            'booking_reference' => $pnr ?? $bookingRef,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'salaamportal',
        ];
    }
    
    // No passengers found - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => $pnr ?? $bookingRef,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'salaamportal',
    ];
}

/**
 * Extract Meraj Air e-ticket format
 */
function extractMerajAirTicket($text) {
    $passengers = [];
    
    // Extract flight date - pattern: "Sunday     07 December 2025"
    $departureDate = null;
    if (preg_match('/(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $text, $match)) {
        $departureDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
    }
    
    // Extract flight time - pattern: "07:00" (can be on its own line or embedded)
    $departureTime = null;
    if (preg_match('/\b(\d{2}):(\d{2})\b/', $text, $match)) {
        $departureTime = sprintf('%02d:%02d', $match[1], $match[2]);
    }
    
    // Extract flight number - pattern: "4848 Meraj Air"
    $flightNumber = null;
    if (preg_match('/(\d{4,5})\s+Meraj\s+Air/i', $text, $match)) {
        $flightNumber = $match[1];
    }
    
    // Extract PNR - pattern: "Local PNR\nT45KU3"
    $pnr = null;
    if (preg_match('/Local\s+PNR\s*\n?\s*([A-Z0-9]+)/i', $text, $match)) {
        $pnr = trim($match[1]);
    }
    
    // Extract ticket number - pattern: "*0000001007013*" or plain "0000001007013"
    $ticketNumber = null;
    if (preg_match('/\*(\d{10,15})\*/i', $text, $match)) {
        $ticketNumber = $match[1];
    } elseif (preg_match('/Ticket\s+number\s*(\d{10,15})/i', $text, $match)) {
        $ticketNumber = $match[1];
    } elseif (preg_match('/(\d{13})\s*\n\s*-\s*/', $text, $match)) {
        $ticketNumber = $match[1];
    }
    
    // Extract issue date - pattern: "2025-12-02 11:21"
    $issueDate = null;
    if (preg_match('/Issue\s+date\s*\n?\s*(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})/i', $text, $match)) {
        $issueDate = parseTicketDate($match[3], $match[2], $match[1]);
    }
    
    // Extract passport number first - pattern: "425125616"
    $passportNumber = null;
    if (preg_match('/(\d{9,10})([A-Z]|SAIF|SAI)/', $text, $match)) {
        $passportNumber = $match[1];
    } elseif (preg_match('/Passport\s+No.*?(\d{9,10})/i', $text, $match)) {
        $passportNumber = $match[1];
    }
    
    // Extract passenger name - pattern: "425125616SAIFOOR _ AHMADZAI/MR - Adult"
    // Look specifically after passport number
    $passengerName = null;
    if (preg_match('/\d{9,10}\s*([A-Z][A-Z\s_\-]+?)\/\s*MR/i', $text, $match)) {
        $passengerName = normalizePassengerName($match[1]);
    } elseif (preg_match('/Passenger\s+Name\s*\t\s*([^\n]+?)\/MR/i', $text, $match)) {
        $passengerName = normalizePassengerName($match[1]);
    }
    
    // Extract route - pattern: "IKA-KBL Y01" or "KabulTehran Imam Khomeini"
    $origin = null;
    $destination = null;
    $originCity = null;
    $destinationCity = null;
    
    if (preg_match('/([A-Z]{3})-([A-Z]{3})\s+[YJ]\d{2}/i', $text, $match)) {
        $origin = strtoupper($match[1]);
        $destination = strtoupper($match[2]);
        $originCity = getAirportName($origin);
        $destinationCity = getAirportName($destination);
    }
    
    // Fallback: look for airport names in text
    if (!$origin && preg_match('/Imam\s+Khomeini|Tehran/i', $text)) {
        $origin = 'IKA';
        $originCity = getAirportName('IKA');
    }
    if (!$destination && preg_match('/Kabul/i', $text)) {
        $destination = 'KBL';
        $destinationCity = getAirportName('KBL');
    }
    
    // Extract cabin class - pattern: "Economy"
    $cabinClass = null;
    if (preg_match('/(Economy|Business|First|Premium)/i', $text, $match)) {
        $cabinClass = trim($match[1]);
    }
    
    // Extract checked baggage - pattern: "1Bag(s)25Kg in total" (can be on separate lines)
    $checkedBaggage = null;
    if (preg_match('/Checked\s+Baggage[\s\S]*?(\d+)\s*Bag\s*\(s\)\s*(\d+)\s*Kg/i', $text, $match)) {
        $checkedBaggage = $match[2] . ' KG';
    }
    
    // Extract hand baggage - pattern: "1Bag(s)5Kg in total" (can be on separate lines)
    $handBaggage = null;
    if (preg_match('/Hand\s+Baggage[\s\S]*?(\d+)\s*Bag\s*\(s\)\s*(\d+)\s*Kg/i', $text, $match)) {
        $handBaggage = $match[2] . ' KG';
    }
    
    // Extract payment amount - pattern: "Payment Amount 130" or "Payment Amount\n130"
    $paymentAmount = null;
    if (preg_match('/Payment\s+Amount[\s\S]*?(\d+)(?:\s|$)/i', $text, $match)) {
        $paymentAmount = $match[1];
    }
    
    // Calculate arrival date and time (add 2 hours 30 minutes to departure)
    $arrivalDate = $departureDate;
    $arrivalTime = null;
    if ($departureTime) {
        // Extract departure hour and minute
        preg_match('/(\d{2}):(\d{2})/', $departureTime, $timeMatch);
        if (!empty($timeMatch)) {
            $depHour = (int)$timeMatch[1];
            $depMin = (int)$timeMatch[2];
            
            // Add 2 hours 30 minutes
            $totalMin = ($depHour * 60) + $depMin + 150;
            $arrivalHour = intdiv($totalMin, 60) % 24;
            $arrivalMin = $totalMin % 60;
            $arrivalTime = sprintf('%02d:%02d', $arrivalHour, $arrivalMin);
        }
    }
    
    // Build passenger record
    if (!empty($flightNumber) || !empty($ticketNumber)) {
        $passenger = [];
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $pnr;
        $passenger['ticket_number'] = $ticketNumber;
        $passenger['passport_number'] = $passportNumber;
        
        // Flight details
        $passenger['airline'] = 'Meraj Air';
        $passenger['airline_code'] = 'MJ'; // Meraj Air code
        $passenger['flight_number'] = $flightNumber;
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        
        // Route
        $passenger['origin'] = $origin;
        $passenger['origin_city'] = $originCity;
        $passenger['destination'] = $destination;
        $passenger['destination_city'] = $destinationCity;
        
        // Times
        $passenger['departure_date'] = $departureDate;
        $passenger['departure_time'] = $departureTime;
        $passenger['arrival_date'] = $arrivalDate;
        $passenger['arrival_time'] = $arrivalTime;
        
        // Baggage info
        $passenger['baggage_allowance'] = $checkedBaggage;
        $passenger['hand_baggage'] = $handBaggage;
        
        // Additional info
        $passenger['ticket_status'] = 'Confirmed';
        $passenger['is_confirmed'] = true;
        $passenger['issue_date'] = $issueDate;
        $passenger['payment_amount'] = $paymentAmount;
        $passenger['trip_type'] = 'One Way';
        
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'merajair';
        
        if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Always return grouped format for consistency
    if (!empty($passengers)) {
        return [
            'is_group_booking' => count($passengers) > 1,
            'booking_reference' => $passengers[0]['pnr'] ?? null,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
        ];
    }
    
    // No passengers found - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => null,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
    ];
}

/**
 * Extract Hadita Portal e-ticket format
 */
function extractHaditaportalTicket($text) {
    $passengers = [];
    
    // Extract voucherID
    $voucherId = null;
    if (preg_match('/voucherID\s*:\s*(\d+)/i', $text, $match)) {
        $voucherId = $match[1];
    }
    
    // Extract issuing date and time
    $issuingDate = null;
    $issuingTime = null;
    if (preg_match('/ISSUING\s+DATE\s+(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})/i', $text, $match)) {
        $issuingDate = parseTicketDate($match[3], $match[2], $match[1]);
        $issuingTime = sprintf('%02d:%02d:%02d', $match[4], $match[5], $match[6]);
    }
    
    // Extract issuing airline - pattern: "ISSUING AIRLINEMeraj Air" (no newline)
    $airline = null;
    if (preg_match('/ISSUING\s+AIRLINE\s*([A-Za-z\s]+?)(?:\n|TICKET)/i', $text, $match)) {
        $airline = trim($match[1]);
    }
    
    // Extract ticket number - pattern: "TICKET NUMBER2443702" or "TICKET NUMBER 2443702"
    $ticketNumber = null;
    if (preg_match('/TICKET\s+NUMBER\s*(\d+)/i', $text, $match)) {
        $ticketNumber = $match[1];
    }
    
    // Extract passport number - pattern: "PASSPORT NUMBERP06907018" or "PASSPORT NUMBER P06907018"
    $passportNumber = null;
    if (preg_match('/PASSPORT\s+NUMBER\s*([A-Z0-9]+)/i', $text, $match)) {
        $passportNumber = trim($match[1]);
    }
    
    // Extract PNR
    $pnr = null;
    if (preg_match('/TICKET\s+PNR\s+([A-Z0-9]+)/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // Extract agency/buy sign
    $agency = null;
    if (preg_match('/BUY\s+SIGN\s+([^\n]+)/i', $text, $match)) {
        $agency = trim($match[1]);
    }
    
    // Extract passenger name - pattern: "ADT - MR - SAFI / WALI AGHA"
    // Also handles: "ADT - MR - NAME" format
    $passengerName = null;
    if (preg_match('/ADT\s*-\s*(?:MR|MRS|MS|MISS)\s*-\s*([^\n]+)/i', $text, $match)) {
        $passengerName = normalizePassengerName($match[1]);
    } elseif (preg_match('/^ADT\s*-\s*([^\n]+)/im', $text, $match)) {
        // Alternative: just "ADT - NAME" format
        $passengerName = normalizePassengerName($match[1]);
    }
    
    // Extract flight information from structured section
    // Format: "Imam Khomeini Intl4848ECONOMY15OCT 05:45 15OCT 10:1530 1piece KGCONFIRMED"
    // No spaces between flight number and cabin class, all concatenated
    
    $flightNumber = null;
    $cabinClass = null;
    $departureDate = null;
    $departureTime = null;
    $arrivalDate = null;
    $arrivalTime = null;
    $baggageAllowance = null;
    $ticketStatus = null;
    $origin = null;
    $destination = null;
    $originCity = null;
    $destinationCity = null;
    
    // Extract flight number and cabin class - pattern: "4848ECONOMY"
    // Flight number is 4-5 digits directly followed by cabin class
    if (preg_match('/(\d{4,5})(ECONOMY|BUSINESS|FIRST|PREMIUM)/i', $text, $flightMatch)) {
        $flightNumber = $flightMatch[1];
        $cabinClass = trim($flightMatch[2]);
    }
    
    // Extract all date/time patterns: "15OCT 05:45"
    $allDateTimes = [];
    if (preg_match_all('/(\d{2})([A-Z]{3})\s+(\d{2}):(\d{2})/i', $text, $matches, PREG_SET_ORDER)) {
        $allDateTimes = $matches;
    }
    
    // Use first date/time as departure
    if (!empty($allDateTimes[0])) {
        $dateMatch = $allDateTimes[0];
        $day = $dateMatch[1];
        $month = substr($dateMatch[2], 0, 3);
        $year = date('Y'); // Use current year
        if ($issuingDate) {
            // Use year from issuing date
            preg_match('/(\d{4})/', $issuingDate, $yearMatch);
            if (!empty($yearMatch)) $year = $yearMatch[1];
        }
        $departureDate = parseTicketDate($day, $month, $year);
        $departureTime = sprintf('%02d:%02d', $dateMatch[3], $dateMatch[4]);
    }
    
    // Use second date/time as arrival
    if (count($allDateTimes) >= 2) {
        $arrMatch = $allDateTimes[1];
        $day = $arrMatch[1];
        $month = substr($arrMatch[2], 0, 3);
        $year = date('Y');
        if ($issuingDate) {
            preg_match('/(\d{4})/', $issuingDate, $yearMatch);
            if (!empty($yearMatch)) $year = $yearMatch[1];
        }
        $arrivalDate = parseTicketDate($day, $month, $year);
        $arrivalTime = sprintf('%02d:%02d', $arrMatch[3], $arrMatch[4]);
    }
    
    // Extract baggage - pattern: "30 1piece KG" or "30 piece KG" 
    // Look for number followed by "piece" and "KG"
    if (preg_match('/(\d+)\s+\d*\s*piece\s+([A-Z]{2})/i', $text, $bagMatch)) {
        $baggageAllowance = $bagMatch[1] . $bagMatch[2];
    } elseif (preg_match('/(\d+)KG/i', $text, $bagMatch)) {
        $baggageAllowance = $bagMatch[0];
    }
    
    // Extract status - pattern: "CONFIRMED", "PENDING", "CANCELLED"
    if (preg_match('/(CONFIRMED|PENDING|CANCELLED)/i', $text, $statusMatch)) {
        $ticketStatus = ucfirst(strtolower($statusMatch[1]));
    }
    
    // Extract route from FLIGHT INFORMATION section
    // Look for airport names/codes in the flight info line
    // Pattern: "Imam Khomeini Intl4848..." and destination on next line "KABUL"
    if (preg_match('/FROM\/TO[^\n]*\n\s*([A-Za-z\s]+?)(\d{4,5})/i', $text, $originMatch)) {
        $originName = trim($originMatch[1]);
        
        // Map known airport names to codes
        if (stripos($originName, 'Imam Khomeini') !== false) {
            $origin = 'IKA';
            $originCity = 'Imam Khomeini International';
        } elseif (stripos($originName, 'Kabul') !== false) {
            $origin = 'KBL';
            $originCity = getAirportName('KBL');
        } else {
            // Try to extract city name
            $originCity = $originName;
        }
    }
    
    // Destination is usually on the next line after the flight info
    if (preg_match('/(?:ECONOMY|BUSINESS|FIRST|PREMIUM)[^\n]*\n\s*([A-Z][A-Za-z\s]+)/i', $text, $destMatch)) {
        $destName = trim($destMatch[1]);
        
        if (stripos($destName, 'KABUL') !== false || stripos($destName, 'Kabul') !== false) {
            $destination = 'KBL';
            $destinationCity = getAirportName('KBL');
        } elseif (stripos($destName, 'Imam Khomeini') !== false) {
            $destination = 'IKA';
            $destinationCity = 'Imam Khomeini International';
        } else {
            $destinationCity = $destName;
        }
    }
    
    // If we have basic info, build passenger record
    if (!empty($flightNumber)) {
        $passenger = [];
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $pnr;
        $passenger['ticket_number'] = $ticketNumber;
        $passenger['passport_number'] = $passportNumber;
        $passenger['voucher_id'] = $voucherId;
        
        // Flight details
        $passenger['airline'] = $airline;
        $passenger['flight_number'] = $flightNumber;
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        
        // Route
        $passenger['origin'] = $origin;
        $passenger['origin_city'] = $originCity;
        $passenger['destination'] = $destination;
        $passenger['destination_city'] = $destinationCity;
        
        // Times
        $passenger['departure_date'] = $departureDate;
        $passenger['departure_time'] = $departureTime;
        $passenger['arrival_date'] = $arrivalDate;
        $passenger['arrival_time'] = $arrivalTime;
        
        // Additional info
        $passenger['baggage_allowance'] = $baggageAllowance;
        $passenger['ticket_status'] = $ticketStatus;
        $passenger['is_confirmed'] = strtolower($ticketStatus) === 'confirmed';
        $passenger['issued_by'] = $agency;
        $passenger['issue_date'] = $issuingDate;
        $passenger['trip_type'] = 'One Way';
        
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'haditaportal';
        
        if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Always return grouped format for consistency
    if (!empty($passengers)) {
        return [
            'is_group_booking' => count($passengers) > 1,
            'booking_reference' => $passengers[0]['pnr'] ?? null,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
        ];
    }
    
    // No passengers found - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => null,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
    ];
}

/**
 * Extract flydubai e-ticket format
 */
function extractFlydubaiTicket($text) {
    $passengers = [];
    
    // Extract booking reference (PNR) - look for specific pattern
    $bookingRef = null;
    if (preg_match('/^\s*([A-Z0-9]{6})\s*\n\s*flydubai\s+booking\s+reference/im', $text, $match)) {
        $bookingRef = $match[1];
    } elseif (preg_match('/Passenger\s+details\s*\n\s*([A-Z0-9]{6})\s*\n/i', $text, $match)) {
        $bookingRef = $match[1];
    }
    
    // Extract passenger name - look for pattern like "Mr. Mumtaz Udin Mukhtar Udin" or "Mr Mumtaz Udin"
    $passengerName = null;
    // Try to match at the beginning: title + multiple words (with possible periods and various cases)
    if (preg_match('/©\s+flydubai[^\n]*\n\s*((?:Mr|Mrs|Ms|Miss|Dr)\.?\s+[A-Za-z\s]+?)(?:\n|Primary)/i', $text, $match)) {
        $passengerName = trim($match[1]);
    } elseif (preg_match('/^((?:Mr|Mrs|Ms|Miss|Dr)\.?\s+[A-Z][a-zA-Z\s]+?)\s*\n/m', $text, $match)) {
        $passengerName = trim($match[1]);
    } elseif (preg_match('/(Mr\.?\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+)\s+Primary/i', $text, $match)) {
        $passengerName = $match[1];
    }
    
    // Extract flight segments - look for patterns like "FZ 992/FZ 307"
    $flights = [];
    if (preg_match_all('/FZ\s+(\d+)\s*\/\s*FZ\s+(\d+)/i', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $flights[] = 'FZ' . $match[1];
            $flights[] = 'FZ' . $match[2];
        }
    } elseif (preg_match_all('/FZ\s+(\d+)/i', $text, $matches)) {
        foreach ($matches[1] as $num) {
            $flights[] = 'FZ' . $num;
        }
    }
    
    // Extract segments with routes and times
    // Look for valid airport codes only
    $validAirports = array_keys(AIRPORTS);
    $segments = [];
    
    // Extract route pairs and times separately
    // First, find all airport code pairs in the text (e.g., "LED ... DXB" and "DXB ... KBL")
    $routes = [];
    if (preg_match_all('/(LED|KBL|DXB|RUH|AUH|DOH|IST|BOM|DEL|HEA|LHR|JFK)\s+[^\n]*\n[^\n]*?(LED|KBL|DXB|RUH|AUH|DOH|IST|BOM|DEL|HEA|LHR|JFK)/i', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $origin = strtoupper($match[1]);
            $dest = strtoupper($match[2]);
            if ($origin !== $dest && in_array($origin, $validAirports) && in_array($dest, $validAirports)) {
                $routes[] = ['origin' => $origin, 'destination' => $dest];
            }
        }
    }
    
    // Now find times near these routes
    // Extract all times from the text
    $times = [];
    preg_match_all('/(\d{2}):(\d{2})/', $text, $timeMatches);
    if (!empty($timeMatches[0])) {
        foreach ($timeMatches[0] as $t) {
            $times[] = $t;
        }
    }
    
    // Match times to routes
    $timeIndex = 0;
    foreach ($routes as $idx => $route) {
        if ($timeIndex + 1 < count($times)) {
            $segments[] = [
                'origin' => $route['origin'],
                'dep_time' => $times[$timeIndex],
                'destination' => $route['destination'],
                'arr_time' => $times[$timeIndex + 1],
            ];
            $timeIndex += 2;
        }
    }
    
    // Extract date information
    $departureDate = null;
    if (preg_match('/(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4}),\s+\w+/i', $text, $match)) {
        $departureDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
    }
    
    // Extract cabin class
    $cabinClass = null;
    if (preg_match('/(Economy\s+Flex|Business|First|Premium\s+Economy)/i', $text, $match)) {
        $cabinClass = trim($match[1]);
    }
    
    // Extract baggage information
    $handBaggage = null;
    $checkedBaggage = null;
    if (preg_match('/(\d+)\s*kg\s+hand\s+baggage/i', $text, $match)) {
        $handBaggage = $match[1] . ' kg';
    }
    if (preg_match('/(\d+)\s*kg\s+checked\s+baggage/i', $text, $match)) {
        $checkedBaggage = $match[1] . ' kg';
    }
    
    // Extract payment and booking info
    $paymentRef = null;
    if (preg_match('/Payment\s+reference\s+(\d+)/i', $text, $match)) {
        $paymentRef = $match[1];
    }
    
    $bookedDate = null;
    if (preg_match('/Booked\s+on\s+(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $text, $match)) {
        $bookedDate = parseTicketDate($match[1], substr($match[2], 0, 3), $match[3]);
    }
    
    // Extract invoice/agency info
    $bookedVia = null;
    if (preg_match('/Invoice:\s*\n\s*([^\n]+)/i', $text, $match)) {
        $bookedVia = trim($match[1]);
    }
    
    // Extract stopover information
    $stopover = null;
    if (preg_match('/Stopover\s+in\s+([A-Z]+)\s+\(([A-Z]{3})\)\s*\|\s*([\d\w\s]+)/i', $text, $match)) {
        $stopover = ['city' => $match[1], 'code' => $match[2], 'duration' => trim($match[3])];
    }
    
    // Build one passenger record. Connection segments are itinerary details, not passengers.
    $passenger = [];
    $passenger['passenger_name'] = $passengerName;
    $passenger['pnr'] = $bookingRef;
    $passenger['payment_reference'] = $paymentRef;
    $passenger['booked_date'] = $bookedDate;
    $passenger['airline'] = 'Fly Dubai';
    $passenger['airline_code'] = 'FZ';
    $passenger['flight_number'] = !empty($flights) ? implode(', ', array_unique($flights)) : null;
    $passenger['cabin_class'] = $cabinClass ?? 'Economy';
    $passenger['hand_baggage'] = $handBaggage;
    $passenger['checked_baggage'] = $checkedBaggage;
    $passenger['meal_service'] = 'Standard meal (included)';
    $passenger['seat_assignment'] = 'Unassigned (included)';
    $passenger['in_flight_entertainment'] = 'In-flight entertainment (included)';
    $passenger['issued_by'] = $bookedVia;
    $passenger['ticket_status'] = 'Confirmed';
    $passenger['is_confirmed'] = true;
    $passenger['trip_type'] = count($segments) > 1 ? 'Multi Segment' : 'One Way';
    
    if (!empty($segments)) {
        foreach ($segments as $idx => &$segment) {
            if (isset($flights[$idx])) {
                $segment['flight_number'] = $flights[$idx];
            }
        }
        unset($segment);
        
        $firstSegment = $segments[0];
        $lastSegment = $segments[count($segments) - 1];
        $passenger['origin'] = $firstSegment['origin'];
        $passenger['origin_city'] = getAirportName($firstSegment['origin']);
        $passenger['destination'] = $lastSegment['destination'];
        $passenger['destination_city'] = getAirportName($lastSegment['destination']);
        $passenger['departure_date'] = $departureDate;
        $passenger['departure_time'] = $firstSegment['dep_time'];
        $passenger['arrival_time'] = $lastSegment['arr_time'];
        $passenger['segments'] = $segments;
    }
    
    if ($stopover) {
        $passenger['stopover'] = $stopover;
    }
    
    $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
    $passenger['format_detected'] = 'flydubai';
    
    if (!empty($passenger) && isset($passenger['pnr'])) {
        $passengers[] = $passenger;
    }
    
    // Single segment - return in grouped format
    if (count($passengers) === 1) {
        return [
            'is_group_booking' => false,
            'booking_reference' => $bookingRef,
            'total_passengers' => 1,
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'flydubai',
        ];
    }
    
    // No passengers - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => $bookingRef,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'flydubai',
    ];
}

/**
 * Extract departure/arrival date+time from a two-column Kam Air ticket block.
 * Handles reversed linearization (ARR before DEP in raw text).
 */
function extractKamAirDateTime(string $ticketBlock): array
{
    $result = [
        'departure_date' => null,
        'departure_time' => null,
        'arrival_date'   => null,
        'arrival_time'   => null,
    ];

    // ── Step 1: find where each label starts and ends ──────────────────
    $depLabelMatch = [];
    $arrLabelMatch = [];
    preg_match('/DEP[.\s]*TIME/i', $ticketBlock, $depLabelMatch, PREG_OFFSET_CAPTURE);
    preg_match('/ARR[.\s]*TIME/i', $ticketBlock, $arrLabelMatch, PREG_OFFSET_CAPTURE);
    


    $depStart = isset($depLabelMatch[0])
        ? $depLabelMatch[0][1] + strlen($depLabelMatch[0][0])
        : -1;
    $arrStart = isset($arrLabelMatch[0])
        ? $arrLabelMatch[0][1] + strlen($arrLabelMatch[0][0])
        : -1;

    // ── Step 2: slice into non-overlapping windows ─────────────────────
    $depWindow = '';
    $arrWindow = '';

    if ($depStart >= 0 && $arrStart >= 0) {
        $depHeaderPos = $depLabelMatch[0][1];
        $arrHeaderPos = $arrLabelMatch[0][1];

        if ($depHeaderPos < $arrHeaderPos) {
            $depWindow = substr($ticketBlock, $depStart, $arrHeaderPos - $depStart);
            $arrWindow = substr($ticketBlock, $arrStart);
        } else {
            $arrWindow = substr($ticketBlock, $arrStart, $depHeaderPos - $arrStart);
            $depWindow = substr($ticketBlock, $depStart);
        }
    } elseif ($depStart >= 0) {
        $depWindow = substr($ticketBlock, $depStart);
    } elseif ($arrStart >= 0) {
        $arrWindow = substr($ticketBlock, $arrStart);
    }

    // ── Step 3: extract first valid time and date from each window ──────
    $firstTime = static function (string $s): ?string {
        if (preg_match('/\b(\d{2}):(\d{2})\b/', $s, $m) && (int)$m[1] <= 23 && (int)$m[2] <= 59) {
            return $m[1] . ':' . $m[2];
        }
        return null;
    };

    $firstDate = static function (string $s): ?string {
        if (preg_match('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $s, $m)) {
            return sprintf('%s-%s-%s', $m[3], $m[2], $m[1]);
        }
        return null;
    };

    $result['departure_time'] = $firstTime($depWindow);
    $result['departure_date'] = $firstDate($depWindow);
    $result['arrival_time']   = $firstTime($arrWindow);
    $result['arrival_date']   = $firstDate($arrWindow);

    // ── Step 4: same-day cross-fill if one window had no date ──────────
    if (!$result['departure_date'] && $result['arrival_date']) {
        $result['departure_date'] = $result['arrival_date'];
    }
    if (!$result['arrival_date'] && $result['departure_date']) {
        $result['arrival_date'] = $result['departure_date'];
    }

    // ── Step 5: last-resort positional fallback ─────────────────────────
    if (!$result['departure_time'] || !$result['arrival_time']) {
        $allTimes = [];
        if (preg_match_all('/\b(\d{2}):(\d{2})\b/', $ticketBlock, $tm, PREG_SET_ORDER)) {
            foreach ($tm as $t) {
                if ((int)$t[1] <= 23 && (int)$t[2] <= 59) {
                    $allTimes[] = $t[1] . ':' . $t[2];
                }
            }
        }

        $allDates = [];
        if (preg_match_all('/\b(\d{2})\/(\d{2})\/(\d{4})\b/', $ticketBlock, $dm, PREG_SET_ORDER)) {
            foreach ($dm as $d) {
                $allDates[] = sprintf('%s-%s-%s', $d[3], $d[2], $d[1]);
            }
        }

        if (!$result['departure_time']) $result['departure_time'] = $allTimes[0] ?? null;
        if (!$result['arrival_time'])   $result['arrival_time']   = $allTimes[1] ?? null;
        if (!$result['departure_date']) $result['departure_date'] = $allDates[0] ?? null;
        if (!$result['arrival_date'])   $result['arrival_date']   = $allDates[1] ?? $allDates[0] ?? null;
    }

    return $result;
}

/**
 * Extract Kam Air specific format
 */
function extractKamAirTicket($text) {
    // Split into individual passenger tickets - more flexible pattern
    $tickets = preg_split('/(?=\d{13}.*?TICKET NUMBER)/', $text);
    
    // If split didn't work well, try alternative split
    if (count($tickets) < 2) {
        $tickets = preg_split('/(?=PASSENGER NAME)/', $text);
    }
    
    // If still only one, treat whole text as single ticket
    if (count($tickets) < 2) {
        $tickets = [$text];
    }
    
    $passengers = [];
    
    foreach ($tickets as $ticketBlock) {
        if (empty(trim($ticketBlock))) continue;
        
        $passenger = [];
        
        // Ticket Number - flexible: 13 digits before or after label
        if (preg_match('/(\d{13})\s*TICKET\s+NUMBER/i', $ticketBlock, $match)) {
            $passenger['ticket_number'] = $match[1];
        } elseif (preg_match('/TICKET\s+NUMBER\s+(\d{13})/i', $ticketBlock, $match)) {
            $passenger['ticket_number'] = $match[1];
        } elseif (preg_match('/(\d{13})/', $ticketBlock, $match)) {
            $passenger['ticket_number'] = $match[1];
        }
        
        // Booking Reference (PNR) - handle both formats
        if (preg_match('/([A-Z0-9]{6})\s*BOOKING\s+REFERENCE/i', $ticketBlock, $match)) {
            $passenger['pnr'] = $match[1];
        } elseif (preg_match('/BOOKING\s+REFERENCE\s+([A-Z0-9]{6})/i', $ticketBlock, $match)) {
            $passenger['pnr'] = $match[1];
        } elseif (preg_match('/^([A-Z0-9]{6})/', $ticketBlock, $match) && preg_match('/\d/', $match[1])) {
            // Require at least one digit — avoids matching words like FLIGHT
            $passenger['pnr'] = $match[1];
        } elseif (preg_match('/^\s*([A-Z0-9]{6})\s*$/m', $ticketBlock, $match) && preg_match('/\d/', $match[1])) {
            // On its own line: 6-char alphanumeric containing a digit
            $passenger['pnr'] = strtoupper($match[1]);
        } elseif (preg_match('/\b([A-Z0-9]{6})\b/', $ticketBlock, $match) && preg_match('/\d/', $match[1]) && !preg_match('/^\d{2}[A-Z]{3}\d{2}$/', $match[1])) {
            // Anywhere in text: 6-char alphanumeric containing a digit (but not a date like 04JUN85)
            $passenger['pnr'] = strtoupper($match[1]);
        }
        
        // Passenger Name - more flexible pattern
        // Formats: LASTNAME / FIRSTNAME (DDMMMYY) or FIRSTNAME / LASTNAME (DDMMMYY)
        if (preg_match('/([A-Z]+)\s*\/\s*([A-Z]+)\s*\((\d{2}[A-Z]{3}\d{2})\)/i', $ticketBlock, $match)) {
            $passenger['passenger_name'] = trim($match[1]) . ' / ' . trim($match[2]);
            $passenger['last_name'] = trim($match[1]);
            $passenger['first_name'] = trim($match[2]);
            $passenger['date_of_birth'] = parseDateOfBirth($match[3]);
        } elseif (preg_match('/PASSENGER\s+NAME\s+([A-Z]+)\s*\/\s*([A-Z]+)\s*\((\d{2}[A-Z]{3}\d{2})\)/i', $ticketBlock, $match)) {
            $passenger['passenger_name'] = trim($match[1]) . ' / ' . trim($match[2]);
            $passenger['last_name'] = trim($match[1]);
            $passenger['first_name'] = trim($match[2]);
            $passenger['date_of_birth'] = parseDateOfBirth($match[3]);
        } elseif (preg_match('/BOOKING\s+REFERENCE\s+([A-Z][A-Z\s]+?)\s*\/\s*([A-Z][A-Z\s]+?)\s*PASSENGER\s+NAME/i', $ticketBlock, $match)) {
            $passenger['last_name'] = normalizePassengerName($match[1]);
            $passenger['first_name'] = normalizePassengerName($match[2]);
            $passenger['passenger_name'] = normalizePassengerName($match[1] . ' / ' . $match[2]);
        } elseif (preg_match('/([A-Z][A-Z\s]+?)\s*\/\s*([A-Z][A-Z\s]+?)\s*PASSENGER\s+NAME/i', $ticketBlock, $match)) {
            $passenger['last_name'] = normalizePassengerName($match[1]);
            $passenger['first_name'] = normalizePassengerName($match[2]);
            $passenger['passenger_name'] = normalizePassengerName($match[1] . ' / ' . $match[2]);
        }
        
        // Carrier Code — fallback chain
        // 1) Direct label match (label may be separated from code by jumbled text)
        if (preg_match('/CARRIER CODE\s+([A-Z]{2})/', $ticketBlock, $match)) {
            $passenger['airline_code'] = $match[1];
            $passenger['airline'] = getAirlineName($match[1]);
        } elseif (preg_match('/FLIGHT NO\.\s*([A-Z]{2})\d+/', $ticketBlock, $match)) {
            // 2) Extract from flight-number prefix: "FLIGHT NO. RQ901"
            $passenger['airline_code'] = $match[1];
            $passenger['airline'] = getAirlineName($match[1]);
        } elseif (preg_match('/FLIGHT NO\..*?([A-Z]{2})\s*\d+/', $ticketBlock, $match)) {
            // 3) Code separated from label by newlines/jumbled text
            $passenger['airline_code'] = $match[1];
            $passenger['airline'] = getAirlineName($match[1]);
        }
        // 4) Default to RQ when this is a Kam Air ticket
        if (empty($passenger['airline_code'])) {
            $passenger['airline_code'] = 'RQ';
            $passenger['airline'] = getAirlineName('RQ');
        }
        
        // Flight Number (handles multi-line: FLIGHT NO.\nRQ\n9097)
        if (preg_match('/FLIGHT NO\.\s*([A-Z]{0,2})\s*(\d+)/', $ticketBlock, $match)) {
            $airlineCode = $passenger['airline_code'] ?? '';
            $flightNum = $match[2];
            $passenger['flight_number'] = $airlineCode . $flightNum;
        } elseif (preg_match('/FLIGHT NO\..*?[A-Z]{0,2}\s*(\d+)/is', $ticketBlock, $match)) {
            $airlineCode = $passenger['airline_code'] ?? '';
            $flightNum = $match[1];
            $passenger['flight_number'] = $airlineCode . $flightNum;
        }
        
        // Route - Extract origin and destination
        // Kam Air PDFs have airport codes near DEP/ARR sections
        if (preg_match('/DEP[.\s]*TIME.*?\(([A-Z]{3})\)/is', $ticketBlock, $match)) {
            $passenger['origin'] = $match[1];
            $passenger['origin_city'] = getAirportName($match[1]);
        }
        if (preg_match('/ARR[.\s]*TIME.*?\(([A-Z]{3})\)/is', $ticketBlock, $match)) {
            $passenger['destination'] = $match[1];
            $passenger['destination_city'] = getAirportName($match[1]);
        }
        // Fallback: look for (XXX)...to...(YYY) pattern
        if (empty($passenger['origin']) && empty($passenger['destination'])) {
            if (preg_match('/\(([A-Z]{3})\).*?to.*?\(([A-Z]{3})\)/is', $ticketBlock, $match)) {
                $passenger['origin'] = $match[1];
                $passenger['origin_city'] = getAirportName($match[1]);
                $passenger['destination'] = $match[2];
                $passenger['destination_city'] = getAirportName($match[2]);
            } elseif (preg_match('/\(([A-Z]{3})\).*?\(([A-Z]{3})\)/is', $ticketBlock, $match)) {
                $passenger['origin'] = $match[1];
                $passenger['origin_city'] = getAirportName($match[1]);
                $passenger['destination'] = $match[2];
                $passenger['destination_city'] = getAirportName($match[2]);
            }
        }
        // If only one side found, try the other via fallback
        if (empty($passenger['origin']) && !empty($passenger['destination'])) {
            if (preg_match('/\(([A-Z]{3})\)/', $ticketBlock, $match)) {
                $passenger['origin'] = $match[1];
                $passenger['origin_city'] = getAirportName($match[1]);
            }
        }
        if (empty($passenger['destination']) && !empty($passenger['origin'])) {
            // Find the LAST parenthesized airport code
            if (preg_match_all('/\(([A-Z]{3})\)/', $ticketBlock, $matches)) {
                $last = end($matches[1]);
                if ($last !== $passenger['origin']) {
                    $passenger['destination'] = $last;
                    $passenger['destination_city'] = getAirportName($last);
                }
            }
        }
        
        // Departure + Arrival Details — window-sliced extraction
        // Kam Air PDF is a two-column layout; linearized text may have ARR before DEP
        $kmDt = extractKamAirDateTime($ticketBlock);
        $passenger['departure_date'] = $kmDt['departure_date'];
        $passenger['departure_time'] = $kmDt['departure_time'];
        $passenger['arrival_date']   = $kmDt['arrival_date'];
        $passenger['arrival_time']   = $kmDt['arrival_time'];
        
        // Reservation Class
        if (preg_match('/REZ[.\s]*CLASS\s*([A-Z0-9]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['reservation_class'] = $match[1];
                $passenger['cabin_class'] = mapReservationClass($match[1]);
            }
        } elseif (preg_match('/([A-Z])\s*REZ[.\s]*CLASS/i', $ticketBlock, $match)) {
            $passenger['reservation_class'] = $match[1];
            $passenger['cabin_class'] = mapReservationClass($match[1]);
        }
        
        // Ticket Status
        if (preg_match('/TICKET\s+STATUS\s*([A-Z]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['ticket_status'] = $match[1];
                $passenger['is_confirmed'] = ($match[1] === 'OK');
            }
        } elseif (preg_match('/TKT\s+ST[.\s]*([A-Z]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['ticket_status'] = $match[1];
                $passenger['is_confirmed'] = ($match[1] === 'OK');
            }
        } elseif (preg_match('/([A-Z]+)\s+(?:TICKET\s+STATUS|TKT\s+ST)/i', $ticketBlock, $match)) {
            $passenger['ticket_status'] = $match[1];
            $passenger['is_confirmed'] = ($match[1] === 'OK');
        }
        
        // Baggage Allowance - accept both "KG" and "PC" units
        if (preg_match('/BAG\s+(\d+)\s*kg/i', $ticketBlock, $match)) {
            $passenger['baggage_allowance'] = $match[1] . ' kg';
        } elseif (preg_match('/BAG\s+(\d+)\s*PC/i', $ticketBlock, $match)) {
            $passenger['baggage_allowance'] = $match[1] . ' PC';
        }
        
        // Terminals
        if (preg_match('/DEP\s+TERMINAL\s*([A-Z0-9]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['departure_terminal'] = $match[1];
            }
        }
        if (preg_match('/ARR\s+TERMINAL\s*([A-Z0-9]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['arrival_terminal'] = $match[1];
            }
        }
        
        // Issue Date - look for date pattern
        if (preg_match('/(\d{1,2}[A-Z]{3}\d{4})\s+\d{8}/', $ticketBlock, $match)) {
            $passenger['issue_date'] = parseIssueDateFormat($match[1]);
        } elseif (preg_match('/(\d{1,2}[A-Z]{3}\d{4})/', $ticketBlock, $match)) {
            $passenger['issue_date'] = parseIssueDateFormat($match[1]);
        }
        
        // Issued By
        if (preg_match('/ISSUED\s+BY\s+([^\n]+?)(?:TICKET|$)/is', $ticketBlock, $match)) {
            $name = trim($match[1]);
            if (!empty($name) && strlen($name) < 50) {
                $passenger['issued_by'] = $name;
            }
        }
        
        // Fare Basis
        if (preg_match('/FARE\s+BASIS\s+([A-Z0-9]+)/i', $ticketBlock, $match)) {
            $passenger['fare_basis'] = $match[1];
        } elseif (preg_match('/([A-Z0-9]+)\s+FARE\s+BASIS/i', $ticketBlock, $match)) {
            $passenger['fare_basis'] = $match[1];
        }
        
        // Validity dates
        if (preg_match('/NVB\s+([A-Z0-9]+)/i', $ticketBlock, $match)) {
            $passenger['not_valid_before'] = $match[1];
        } elseif (preg_match('/([0-9A-Z]+)\s+NVB/i', $ticketBlock, $match)) {
            $passenger['not_valid_before'] = $match[1];
        }
        
        if (preg_match('/NVA\s+([A-Z0-9]+)/i', $ticketBlock, $match)) {
            $passenger['not_valid_after'] = $match[1];
        } elseif (preg_match('/([0-9A-Z]+)\s+NVA/i', $ticketBlock, $match)) {
            $passenger['not_valid_after'] = $match[1];
        }
        
        // Seat
        if (preg_match('/SEAT\s+([A-Z0-9]+)/i', $ticketBlock, $match)) {
            $passenger['seat_number'] = $match[1];
        } elseif (preg_match('/SEAT\s+NAME\s*([A-Z0-9]+)?/i', $ticketBlock, $match)) {
            if (isset($match[1]) && !empty($match[1])) {
                $passenger['seat_number'] = $match[1];
            }
        }
        
        // Add metadata
        $passenger['trip_type'] = 'One Way';
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'kamair';
        
        if (!empty($passenger) && (isset($passenger['ticket_number']) || !empty($passenger['pnr']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Merge passengers sharing same PNR (flight info in Block[0], personal info in Block[1])
    $merged = [];
    foreach ($passengers as $p) {
        $pnr = !empty($p['pnr']) ? $p['pnr'] : null;
        if ($pnr && isset($merged[$pnr])) {
            foreach ($p as $k => $v) {
                if (($v !== null && $v !== '') && (empty($merged[$pnr][$k]) || $merged[$pnr][$k] === null)) {
                    $merged[$pnr][$k] = $v;
                }
            }
        } elseif ($pnr) {
            $merged[$pnr] = $p;
        } else {
            $merged[] = $p;
        }
    }
    $passengers = array_values($merged);
    
    // Deduplicate by ticket_number (Kam Air PDF has stub + receipt = 2 copies per passenger)
    $seen = [];
    $deduped = [];
    foreach ($passengers as $idx => $p) {
        $key = $p['ticket_number'] ?? $p['pnr'] ?? $p['passenger_name'] ?? ('row_' . $idx);
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $deduped[] = $p;
        }
    }
    $passengers = $deduped;
    
    // If no passenger extracted via normal method but we have ticket data, create one
    if (empty($passengers)) {
        $passenger = [];
        
        // Try fallback: first 13-digit number is ticket number
        if (preg_match('/\b(\d{13})\b/', $text, $match)) {
            $passenger['ticket_number'] = $match[1];
        }
        
        // Try fallback: first 6-char alphanumeric is PNR
        if (preg_match('/^([A-Z0-9]{6})/', trim($text), $match)) {
            $passenger['pnr'] = $match[1];
        } elseif (preg_match('/\b([A-Z0-9]{6})\b/', $text, $match)) {
            $passenger['pnr'] = $match[1];
        }
        
        if (!empty($passenger) && isset($passenger['ticket_number'])) {
            $passenger['trip_type'] = 'One Way';
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'kamair';
            $passengers[] = $passenger;
        }
    }
    
    // If multiple passengers, group them
    if (count($passengers) > 1) {
        return [
            'is_group_booking' => true,
            'booking_reference' => $passengers[0]['pnr'] ?? null,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
        ];
    }
    
    return count($passengers) === 1 ? $passengers[0] : ['passengers' => $passengers];
}

/**
 * Extract Ariana flight info from the itinerary section
 */
function extractArianaFlightInfo($text) {
    $info = [];
    
    // Pattern 1: Departure time with terminal - "KBL 10:30 terminal a"
    if (preg_match('/([A-Z]{3})\s+(\d{1,2}):(\d{2})\s+terminal[:\s]*([a-z])/i', $text, $match)) {
        $info['departure_airport'] = $match[1];
        $info['departure_time'] = sprintf('%02d:%02d', $match[2], $match[3]);
        $info['departure_terminal'] = strtoupper($match[4]);
    }
    
    // Pattern 2: Departure time without terminal - "KBL 10:30"
    if (!isset($info['departure_time']) && preg_match('/([A-Z]{3})\s+(\d{1,2}):(\d{2})(?:\s+terminal[:\s]*([a-z]))?/i', $text, $match)) {
        if (isset(AIRPORTS[$match[1]])) {
            $info['departure_airport'] = $match[1];
            $info['departure_time'] = sprintf('%02d:%02d', $match[2], $match[3]);
            if (!empty($match[4])) {
                $info['departure_terminal'] = strtoupper($match[4]);
            }
        }
    }
    
    // Pattern 3: Time on/or after the date line in itinerary - "07-JUN 10:30" or "07-JUN\n10:30"
    if (!isset($info['departure_time']) && preg_match('/(\d{1,2})-([A-Z]{3})\s*\R?\s*(\d{1,2}):(\d{2})/i', $text, $match)) {
        $info['departure_time'] = sprintf('%02d:%02d', $match[3], $match[4]);
    }
    
    // Pattern 4: Time right after flight number line - "FG-261ECONOMY737" then next line or "FG-261 10:30"
    if (!isset($info['departure_time']) && preg_match('/FG-\d+[A-Z]*\d*\s*\R?\s*(\d{1,2}):(\d{2})/i', $text, $match)) {
        $info['departure_time'] = sprintf('%02d:%02d', $match[1], $match[2]);
    }
    
    // Arrival time and terminal (same patterns)
    if (preg_match('/([A-Z]{3})\s+(\d{1,2}):(\d{2})\s+terminal[:\s]*([a-z])/i', $text, $match)) {
        $originAirport = $info['departure_airport'] ?? null;
        if ($originAirport !== $match[1]) {
            $info['arrival_airport'] = $match[1];
            $info['arrival_time'] = sprintf('%02d:%02d', $match[2], $match[3]);
            $info['arrival_terminal'] = strtoupper($match[4]);
        }
    }
    if (!isset($info['arrival_time']) && preg_match('/([A-Z]{3})\s+(\d{1,2}):(\d{2})(?:\s+terminal[:\s]*([a-z]))?/i', $text, $match)) {
        $originAirport = $info['departure_airport'] ?? null;
        if ($originAirport !== $match[1] && isset(AIRPORTS[$match[1]])) {
            $info['arrival_airport'] = $match[1];
            $info['arrival_time'] = sprintf('%02d:%02d', $match[2], $match[3]);
            if (!empty($match[4])) {
                $info['arrival_terminal'] = strtoupper($match[4]);
            }
        }
    }
    
    // Aircraft type
    if (preg_match('/FG-\d+[A-Z]+(\d{3})/', $text, $match)) {
        $info['aircraft_type'] = $match[1];
    }
    
    return $info;
}

/**
 * Extract Ariana Afghan Airlines format
 */
function extractArianaTicket($text) {
    $passengers = [];
    
    // Extract booking reference (PNR)
    $pnr = null;
    if (preg_match('/BOOKING\s*#\s*([A-Z0-9]{6})/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // Extract global flight info (shared across passengers)
    $flightInfo = extractArianaFlightInfo($text);
    
    // Extract ticket details with passenger names from table
    // Pattern: PASSENGER NAME\nTICKET DETAILS on separate lines
    // Handles formats like: 
    // MRS GEETA MOHIBZADA
    // 255 1019 951 835 / 1FG-252HEA-KBL18 Dec 2025Basic (20kg)20kgsLBASIC20AFN 6,542 OK
    $blockPattern = '/(?:^|\n)\s*((?:MRS?|MS|MISS|DR|PROF|MR)\s+[A-Z\s]+?)\n\s*(\d{3}\s+\d{4}\s+\d{3}\s+\d{3})\s*\/\s*(\d+)\s*(FG-\d+)\s*([A-Z]{3})-([A-Z]{3})\s*(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s*([A-Za-z\s()0-9]+?)\s*(\d+kgs?)\s*([A-Z0-9]+?)\s*AFN\s+([\d,]+)\s+([A-Z]+)/mi';
    
    if (preg_match_all($blockPattern, $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $passenger = [];
            
            // Passenger name
            $fullName = trim($match[1]);
            if (preg_match('/(MRS?|MS|MISS|DR|PROF|MR)\s+(.+)/i', $fullName, $nameMatch)) {
                $passenger['title'] = $nameMatch[1];
                $passenger['passenger_name'] = $fullName;
                $passenger['name'] = trim($nameMatch[2]);
            }
            
            // Basic info
            $passenger['pnr'] = $pnr;
            $passenger['ticket_number'] = str_replace(' ', '', $match[2]);
            $passenger['coupon'] = $match[3];
            
            // Flight details
            $flightNumber = $match[4];
            $passenger['flight_number'] = $flightNumber;
            $passenger['airline_code'] = 'FG';
            $passenger['airline'] = getAirlineName('FG');
            
            // Route
            $passenger['origin'] = $match[5];
            $passenger['origin_city'] = getAirportName($match[5]);
            $passenger['destination'] = $match[6];
            $passenger['destination_city'] = getAirportName($match[6]);
            
            // Date parsing: "18 Dec 2025"
            $dateStr = $match[7];
            if (preg_match('/(\d{1,2})\s+([A-Za-z]{3})\s+(\d{4})/', $dateStr, $dateMatch)) {
                $passenger['departure_date'] = parseTicketDate($dateMatch[1], $dateMatch[2], $dateMatch[3]);
            }
            
            // Fare family and baggage
            $fareFamily = trim($match[8]);
            $passenger['fare_family'] = $fareFamily;
            
            // Extract baggage from fare family if present
            if (preg_match('/\((\d+kg)\)/', $fareFamily, $bagMatch)) {
                $passenger['baggage_allowance'] = $bagMatch[1];
            } else {
                $passenger['baggage_allowance'] = $match[9];
            }
            
            // Fare basis and pricing
            $passenger['fare_basis'] = $match[10];
            $passenger['price'] = $match[11];
            $passenger['currency'] = 'AFN';
            
            // Status
            $passenger['ticket_status'] = $match[12];
            $passenger['is_confirmed'] = ($match[12] === 'OK');
            
            // Cabin class - extract from text if present
            if (preg_match('/' . preg_quote($flightNumber, '/') . '\s+([A-Z]+)/i', $text, $classMatch)) {
                $passenger['cabin_class'] = ucfirst(strtolower($classMatch[1]));
            } else {
                $passenger['cabin_class'] = 'Economy'; // Default for Ariana
            }
            
            // Aircraft type - extract if present
            if (preg_match('/' . preg_quote($flightNumber, '/') . '\s+[A-Z]+\s+(\d{3})/i', $text, $aircraftMatch)) {
                $passenger['aircraft_type'] = $aircraftMatch[1];
            }
            
            // Merge global flight info
            if (!empty($flightInfo)) {
                if (isset($flightInfo['departure_time']) && !isset($passenger['departure_time'])) {
                    $passenger['departure_time'] = $flightInfo['departure_time'];
                }
                if (isset($flightInfo['arrival_time']) && !isset($passenger['arrival_time'])) {
                    $passenger['arrival_time'] = $flightInfo['arrival_time'];
                }
                if (isset($flightInfo['departure_terminal']) && !isset($passenger['departure_terminal'])) {
                    $passenger['departure_terminal'] = $flightInfo['departure_terminal'];
                }
                if (isset($flightInfo['arrival_terminal']) && !isset($passenger['arrival_terminal'])) {
                    $passenger['arrival_terminal'] = $flightInfo['arrival_terminal'];
                }
            }
            
            // Trip type
            $passenger['trip_type'] = 'One Way';
            
            // Metadata
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'ariana';
            
            $passengers[] = $passenger;
        }
    }
    
    // Second pattern: handle single-line format where name and ticket number are on same line
    // e.g. "MR KHALID LARAWAY 255 1020 164 189"
    if (empty($passengers) && preg_match_all('/(?:^|\n)\s*((?:MRS?|MS|MISS|DR|PROF|MR)\s+[A-Z\s]+?)\s+(\d{3}\s+\d{4}\s+\d{3}\s+\d{3})(?:\s*$|\s*\n)/mi', $text, $nameMatches, PREG_SET_ORDER)) {
        // Extract origin/destination from route pattern
        $origin = null;
        $destination = null;
        if (preg_match('/([A-Z]{3})-([A-Z]{3})/', $text, $routeMatch)) {
            $origin = $routeMatch[1];
            $destination = $routeMatch[2];
        }
        
        // Extract flight number from "FG-261" pattern
        $flightNumber = null;
        if (preg_match('/(FG-\d+)/', $text, $fnMatch)) {
            $flightNumber = $fnMatch[1];
        }
        
        // Extract cabin class and aircraft from itinerary line like "FG-261ECONOMY737"
        $cabinClass = 'Economy';
        $aircraftType = null;
        if (preg_match('/FG-\d+([A-Z]+)(\d{3})/', $text, $detailMatch)) {
            $cabinClass = ucfirst(strtolower($detailMatch[1]));
            $aircraftType = $detailMatch[2];
        }
        
        // Extract departure date from Travel Itinerary section
        $departureDate = null;
        if (preg_match('/(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+(\d{1,2})-([A-Z]{3})/i', $text, $dateMatch)) {
            $departureDate = parseTicketDate($dateMatch[1], $dateMatch[2], date('Y'));
        }
        
        // Extract departure time from flight info (may be null if not on ticket)
        $departureTime = isset($flightInfo['departure_time']) ? $flightInfo['departure_time'] : null;
        $arrivalTime = isset($flightInfo['arrival_time']) ? $flightInfo['arrival_time'] : null;
        
        foreach ($nameMatches as $nm) {
            $passenger = [];
            
            // Passenger name
            $fullName = trim($nm[1]);
            if (preg_match('/(MRS?|MS|MISS|DR|PROF|MR)\s+(.+)/i', $fullName, $nameMatch)) {
                $passenger['title'] = $nameMatch[1];
                $passenger['passenger_name'] = $fullName;
                $passenger['name'] = trim($nameMatch[2]);
            }
            
            // Basic info
            $passenger['pnr'] = $pnr;
            $passenger['ticket_number'] = str_replace(' ', '', $nm[2]);
            
            // Flight details
            $passenger['flight_number'] = $flightNumber;
            $passenger['airline_code'] = 'FG';
            $passenger['airline'] = getAirlineName('FG');
            $passenger['cabin_class'] = $cabinClass;
            $passenger['aircraft_type'] = $aircraftType;
            
            // Route
            $passenger['origin'] = $origin;
            $passenger['origin_city'] = $origin ? getAirportName($origin) : null;
            $passenger['destination'] = $destination;
            $passenger['destination_city'] = $destination ? getAirportName($destination) : null;
            
            // Times
            $passenger['departure_date'] = $departureDate;
            $passenger['departure_time'] = $departureTime;
            $passenger['arrival_time'] = $arrivalTime;
            
            // Additional info
            $passenger['ticket_status'] = 'Confirmed';
            $passenger['is_confirmed'] = true;
            $passenger['trip_type'] = 'One Way';
            
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'ariana';
            
            $passengers[] = $passenger;
        }
    }
    
    // If no passengers extracted, try alternative parsing
    if (empty($passengers)) {
        // Fallback extraction logic
        $passenger = [];
        $passenger['pnr'] = $pnr;
        
        if (preg_match('/Passengers\s+((?:MR|MRS|MS|MISS|DR)\s+[A-Z][A-Z\s]+?)\s+(\d{3}\s+\d{4}\s+\d{3}\s+\d{3})/i', $text, $match)) {
            $passenger['passenger_name'] = normalizePassengerName($match[1]);
            $passenger['ticket_number'] = str_replace(' ', '', $match[2]);
        }
        
        // Try to extract at least basic flight info
        if (preg_match('/(FG-\d+)/', $text, $match)) {
            $passenger['flight_number'] = $match[1];
            $passenger['airline_code'] = 'FG';
            $passenger['airline'] = getAirlineName('FG');
        }
        
        if (preg_match('/FG-\d+\s*(ECONOMY|BUSINESS|FIRST)/i', $text, $match)) {
            $passenger['cabin_class'] = ucfirst(strtolower($match[1]));
        }
        
        if (preg_match('/([A-Z]{3})-([A-Z]{3})/', $text, $match)) {
            $passenger['origin'] = $match[1];
            $passenger['origin_city'] = getAirportName($match[1]);
            $passenger['destination'] = $match[2];
            $passenger['destination_city'] = getAirportName($match[2]);
        }
        
        if (preg_match('/(?:Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\s+(\d{1,2})-([A-Z]{3})/i', $text, $match)) {
            $passenger['departure_date'] = parseTicketDate($match[1], $match[2], date('Y'));
        }
        
        // Extract departure time from flightInfo in fallback too
        if (isset($flightInfo['departure_time'])) {
            $passenger['departure_time'] = $flightInfo['departure_time'];
        }
        if (isset($flightInfo['arrival_time'])) {
            $passenger['arrival_time'] = $flightInfo['arrival_time'];
        }
        
        if (empty($passenger['passenger_name']) && !empty($passengerNames)) {
            $passenger['passenger_name'] = $passengerNames[0]['full_name'];
        }
        
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'ariana';
        
        if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['flight_number']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Extract total fare if present
    $totalFare = null;
    if (preg_match('/Total\s+Ticket\s+Value:\s+AFN\s+([\d,]+)/i', $text, $match)) {
        $totalFare = $match[1];
    }
    
    // If multiple passengers, group them
    if (count($passengers) > 1) {
        return [
            'is_group_booking' => true,
            'booking_reference' => $pnr,
            'total_passengers' => count($passengers),
            'total_fare' => $totalFare,
            'currency' => 'AFN',
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
        ];
    }
    
    return count($passengers) === 1 ? $passengers[0] : ['passengers' => $passengers, 'pnr' => $pnr];
}


/**
 * Extract Air Arabia e-ticket format
 * Handles multiple passengers in single document
 */
function extractAirArabiaTicket($text) {
    $passengers = [];
    
    // Extract Booking Reference/Reservation Number - pattern: "Reservation Number\n68WUB8"
    $reservationNumber = null;
    if (preg_match('/Reservation\s+Number\s*\n\s*([A-Z0-9]+)/i', $text, $match)) {
        $reservationNumber = $match[1];
    }
    
    // Extract booking date - pattern: "Booking Date 24 Oct 2025"
    $bookingDate = null;
    if (preg_match('/Booking\s+Date\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/i', $text, $match)) {
        $bookingDate = parseTicketDate($match[1], $match[2], $match[3]);
    }
    
    // Extract PIN - pattern: "PIN 060833311208"
    $pin = null;
    if (preg_match('/PIN\s+(\d+)/i', $text, $match)) {
        $pin = $match[1];
    }
    
    // Extract contact email - pattern: "Contact Email sales4@marktravel.af"
    $contactEmail = null;
    if (preg_match('/Contact\s+Email\s+([^\n\s]+)/i', $text, $match)) {
        $contactEmail = $match[1];
    }
    
    // Extract contact mobile - pattern: "Contact Mobile 93-728-121212"
    $contactMobile = null;
    if (preg_match('/Contact\s+Mobile\s+([^\n]+)/i', $text, $match)) {
        $contactMobile = trim($match[1]);
    }
    
    // Extract all dates from entire text BEFORE splitting into passenger blocks
    // Pattern: "27 Oct 2025 23:25"
    $allExtractedDates = [];
    $dateTimePatterns = [
        '/(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})\s+(\d{2}):(\d{2})/',  // 27 Oct 2025 23:25 (standard)
        '/(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})[\s\t]+(\d{2}):(\d{2})/',  // with flexible whitespace
        '/(\d{1,2})-([A-Za-z]{3,9})-(\d{4})\s+(\d{2}):(\d{2})/',  // 27-Oct-2025 23:25
        '/(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})/',  // 27.10.2025 23:25
    ];
    
    foreach ($dateTimePatterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (count($match) >= 5) {
                    $allExtractedDates[] = $match;
                }
            }
            // Stop after first successful pattern
            if (!empty($allExtractedDates)) break;
        }
    }
    
    // Split document into passenger sections - pattern: "Mr/Ms/Mrs/Ms followed by E-ticket number"
    // Each passenger has: Title + Name, E-ticket, and Travel Itinerary
    $passengerBlocks = preg_split('/(?=(?:Mr|Mrs|Ms|Miss)\s+[A-Z][a-zA-Z\s]+\nE-ticket)/i', $text);
    
    $dateIndex = 0;  // Track which dates we've assigned
    
    foreach ($passengerBlocks as $block) {
        if (empty(trim($block))) continue;
        
        $passenger = [];
        
        // Extract passenger name - pattern: "Mr Shamal Said Kamal\nE-ticket"
        if (preg_match('/^((?:Mr|Mrs|Ms|Miss)\s+[A-Za-z\s]+)\s*\n/i', $block, $match)) {
            $passenger['passenger_name'] = normalizePassengerName($match[1]);
            $passenger['title'] = preg_match('/^(Mr|Mrs|Ms|Miss)/i', $match[1], $titleMatch) ? $titleMatch[1] : '';
        }
        
        // Extract e-ticket number - pattern: "E-ticket number 5142378751219"
        if (preg_match('/E-ticket\s+number\s+(\d{11})/i', $block, $match)) {
            $passenger['ticket_number'] = $match[1];
        }
        
        // Extract first and last name
        if (preg_match('/First\s+Name\s+(\w+)/i', $block, $match)) {
            $passenger['first_name'] = $match[1];
        }
        if (preg_match('/Last\s+Name\s+([A-Za-z\s]+?)(?:\n|$)/i', $block, $match)) {
            $passenger['last_name'] = trim($match[1]);
        }
        
        // Extract flight number - pattern: "G9806"
        if (preg_match('/\b([A-Z]\d{3,4})\b/i', $block, $match)) {
            $passenger['flight_number'] = $match[1];
            $passenger['airline_code'] = 'G9'; // Air Arabia code
            $passenger['airline'] = 'Air Arabia';
        }
        
        // Extract cabin class - pattern: "Economy"
        if (preg_match('/(Economy|Business|First|Premium)/', $block, $match)) {
            $passenger['cabin_class'] = $match[1];
        }
        
        // Extract route - look for "DME SHJ" pattern after "Economy" cabin class
        if (preg_match('/(Economy|Business|First|Premium)\s+([A-Z]{3})\s+([A-Z]{3})/i', $block, $routeMatch)) {
            $passenger['origin'] = $routeMatch[2];
            $passenger['origin_city'] = getAirportName($routeMatch[2]);
            $passenger['destination'] = $routeMatch[3];
            $passenger['destination_city'] = getAirportName($routeMatch[3]);
        } elseif (preg_match('/([A-Z]{3})\s+([A-Z]{3})(?:\s+|$)/i', $block, $routeMatch)) {
            // Fallback: look for two 3-letter codes
            $origin = $routeMatch[1];
            $dest = $routeMatch[2];
            $validAirports = array_keys(AIRPORTS);
            if (in_array($origin, $validAirports) && in_array($dest, $validAirports)) {
                $passenger['origin'] = $origin;
                $passenger['origin_city'] = getAirportName($origin);
                $passenger['destination'] = $dest;
                $passenger['destination_city'] = getAirportName($dest);
            }
        }
        
        // Use pre-extracted dates from text level
        // Each passenger gets 2 dates (departure and arrival)
        if ($dateIndex < count($allExtractedDates)) {
            $m = $allExtractedDates[$dateIndex];
            $passenger['departure_date'] = parseTicketDate($m[1], $m[2], $m[3]);
            $passenger['departure_time'] = $m[4] . ':' . $m[5];
            $dateIndex++;
        }
        
        if ($dateIndex < count($allExtractedDates)) {
            $m = $allExtractedDates[$dateIndex];
            $passenger['arrival_date'] = parseTicketDate($m[1], $m[2], $m[3]);
            $passenger['arrival_time'] = $m[4] . ':' . $m[5];
            $dateIndex++;
        }
        
        // Extract baggage - pattern: "Checked Baggage	No Bag" or "15kg"
        if (preg_match('/Checked\s+Baggage\s+([^\n]+)/i', $block, $match)) {
            $passenger['baggage_allowance'] = trim($match[1]);
        }
        
        // Extract duration - pattern: "5h 40m"
        if (preg_match('/(\d+h\s+\d+m)/i', $block, $match)) {
            $passenger['duration'] = $match[1];
        }
        
        // Extract terminal information - pattern: "Terminal 2", "Terminal 3"
        if (preg_match('/Terminal\s+(\d+|[A-Z])/i', $block, $match)) {
            $passenger['departure_terminal'] = $match[1];
        }
        
        // Set common fields
        $passenger['pnr'] = $reservationNumber;
        $passenger['reservation_number'] = $reservationNumber;
        $passenger['booking_date'] = $bookingDate;
        $passenger['pin'] = $pin;
        $passenger['contact_email'] = $contactEmail;
        $passenger['contact_mobile'] = $contactMobile;
        $passenger['ticket_status'] = 'Confirmed';
        $passenger['is_confirmed'] = true;
        $passenger['trip_type'] = 'One Way';
        
        // Calculate confidence and set format
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'airarabia';
        
        // Add to passengers if we have essential data
        if (!empty($passenger) && (isset($passenger['ticket_number']) || isset($passenger['passenger_name']))) {
            $passengers[] = $passenger;
        }
    }
    
    // Return in grouped format
    if (count($passengers) > 1) {
        return [
            'is_group_booking' => true,
            'booking_reference' => $reservationNumber,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'airarabia',
        ];
    }
    
    if (count($passengers) === 1) {
        return [
            'is_group_booking' => false,
            'booking_reference' => $reservationNumber,
            'total_passengers' => 1,
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'airarabia',
        ];
    }
    
    // No passengers found
    return [
        'is_group_booking' => false,
        'booking_reference' => $reservationNumber,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'airarabia',
    ];
}

/**
 * Extract Skyportal e-ticket format
 * Handles IATA tickets with Warsaw Convention terms
 */
function extractSkyportalTicket($text) {
    $passengers = [];
    $passengerName = null;
    $ticketNumber = null;
    $times = [];
    $flightNumbers = [];
    
    // Extract Booking Reference - pattern: "Booking Reference number :FB2011KQP5QV"
    $bookingRef = null;
    if (preg_match('/Booking\s+Reference\s+number\s*:\s*([A-Z0-9]+)(?:\s|$|B)/i', $text, $match)) {
        $bookingRef = $match[1];
    }
    
    // Extract GDS PNR - pattern: "GDS PNR :18PE7P"
    $pnr = null;
    if (preg_match('/GDS\s+PNR\s*:\s*([A-Z0-9]+)/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // Fallback to standard PNR extraction if GDS PNR not found
    if (!$pnr && preg_match('/(?:PNR|Booking\s+Reference|Record\s+Locator)[:\s#]*([A-Z0-9]{6})\b/i', $text, $match)) {
        $pnr = $match[1];
    }
    
    // First, try to extract passenger info from traveller details table
    $tablePassengers = [];
    if (preg_match('/Traveller\s+Details.*?(?=Flight\s+Details|$)/is', $text, $tableMatch)) {
        $tableSection = $tableMatch[0];
        // Extract rows - more flexible pattern to handle spacing and formatting
        // Pattern: MR NAME ADT KBL : DXB TICKETNUM PNR BAGGAGE STATUS
        if (preg_match_all('/(?:MR|MRS|MS)\s+([A-Z\s]+?)\s+(?:ADT|CHD|INF)\s+([A-Z]{3}\s*:\s*[A-Z]{3})\s+(\d{13})\s+([A-Z0-9]+)\s+(\d+KG|[\d.]+KG)\s*(\w+)?/i', $tableSection, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $passenger = [];
                $passenger['passenger_name'] = normalizePassengerName(trim($match[1]));
                $passenger['pnr'] = trim($match[4]);
                $passenger['ticket_number'] = $match[3];
                
                // Parse sector
                $sector = preg_split('/\s*:\s*/', trim($match[2]));
                if (count($sector) == 2) {
                    $passenger['origin'] = trim($sector[0]);
                    $passenger['origin_city'] = getAirportName(trim($sector[0]));
                    $passenger['destination'] = trim($sector[1]);
                    $passenger['destination_city'] = getAirportName(trim($sector[1]));
                }
                
                // Baggage
                $passenger['baggage_allowance'] = $match[5];
                $passenger['ticket_status'] = $match[6] ? ucfirst(strtolower($match[6])) : 'Confirmed';
                $passenger['is_confirmed'] = !$match[6] || strtolower($match[6]) === 'confirmed';
                
                $tablePassengers[] = $passenger;
            }
        }
    }
    
    // If we found passengers in table, use those
    if (!empty($tablePassengers)) {
        $passengers = $tablePassengers;
    }
    
    // Extract flight details section
    $flightData = [];
    if (preg_match('/Flight\s+Details.*?(?=Fare\s+Details|$)/is', $text, $flightMatch)) {
        $flightSection = $flightMatch[0];
        
        // Extract airline and flight number - pattern: "Kam Air(RQ) - Economy (H)\n901" or on multiple lines
        if (preg_match('/([A-Za-z][A-Za-z ]+)\(([A-Z]{2})\)\s*-\s*([A-Za-z]+(?:\s+[A-Za-z]+)?)\s*\(([A-Z])\)\s*[\n\s]+(\d+)/i', $flightSection, $match)) {
            $flightData['airline_name'] = trim($match[1]);
            $flightData['airline_code'] = $match[2];
            $flightData['cabin_class'] = $match[3];
            $flightData['reservation_class'] = $match[4];
            $flightData['flight_number'] = $match[2] . $match[5];
            $flightNumbers[] = ['full' => $flightData['flight_number']];
        }
        
        // Fallback: try to extract airline from various formats
        if (!isset($flightData['airline_code'])) {
            if (preg_match('/([A-Za-z][A-Za-z ]+)\(([A-Z]{2})\)/i', $flightSection, $match)) {
                $flightData['airline_name'] = trim($match[1]);
                $flightData['airline_code'] = $match[2];
            }
        }
        
        // Extract departure details - pattern: "17:15, 21 Nov 2025, Khwaja Rawash, Kabul(KBL), Terminal I"
        if (preg_match('/Departure.*?\n\s*(\d{2}):(\d{2}),\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4}),.*?\(([A-Z]{3})\),.*?Terminal\s+([^\n,]+)/is', $flightSection, $match)) {
            $flightData['departure_time'] = $match[1] . ':' . $match[2];
            $flightData['departure_date'] = parseTicketDate($match[3], $match[4], $match[5]);
            $flightData['origin'] = $match[6];
            $flightData['departure_terminal'] = $match[7];
        }
        
        // Extract arrival details - pattern: "19:55, 21 Nov 2025, Dubai International, Dubai(DXB), Terminal 1"
        if (preg_match('/Arrivals.*?\n\s*(\d{2}):(\d{2}),\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4}),.*?\(([A-Z]{3})\),.*?Terminal\s+(\d+|[IVX]+)/is', $flightSection, $match)) {
            $flightData['arrival_time'] = $match[1] . ':' . $match[2];
            $flightData['arrival_date'] = parseTicketDate($match[3], $match[4], $match[5]);
            $flightData['destination'] = $match[6];
            $flightData['arrival_terminal'] = $match[7];
        }
        
        if (preg_match_all('/\b(\d{2}):(\d{2}),\s+\d{1,2}\s+[A-Za-z]+\s+\d{4}/', $flightSection, $timeMatches, PREG_SET_ORDER)) {
            foreach ($timeMatches as $timeMatch) {
                $times[] = $timeMatch[1] . ':' . $timeMatch[2];
            }
        }
    }
    
    if (!$passengerName && preg_match('/(?:MR|MRS|MS)\s+([A-Z][A-Z\s]+?)\s+ADT\s+[A-Z]{3}\s*:\s*[A-Z]{3}/i', $text, $match)) {
        $passengerName = normalizePassengerName($match[1]);
    }
    if (!$ticketNumber && preg_match('/\b(\d{13})\s+[A-Z0-9]{6}\s+\d+\s*KG/i', $text, $match)) {
        $ticketNumber = $match[1];
    }
    
    // Extract trip details - pattern: "Trip Type : OneWay	Trip Name : Kabul(KBL) to Dubai(DXB)"
    $tripType = null;
    if (preg_match('/Trip\s+Type\s*:\s*(\w+)/i', $text, $match)) {
        $tripType = $match[1] === 'OneWay' ? 'One Way' : $match[1];
    }
    
    // Extract status - pattern: "Status : confirmed"
    $status = null;
    if (preg_match('/Status\s*:\s*(\w+)/i', $text, $match)) {
        $status = $match[1];
    }
    
    // Extract flight segments - look for route pairs (e.g., KBL-DXB, DXB-IST) - only if not already extracted
    $validAirports = array_keys(AIRPORTS);
    $routes = [];
    if (empty($passengers)) {
        if (preg_match_all('/([A-Z]{3})\s*[-–]\s*([A-Z]{3})/i', $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $origin = strtoupper($match[1]);
                $dest = strtoupper($match[2]);
                if ((in_array($origin, $validAirports) || in_array($dest, $validAirports)) && $origin !== $dest) {
                    $routes[] = ['origin' => $origin, 'destination' => $dest];
                }
            }
        }
        
        // Remove duplicate routes
        $seenRoutes = [];
        $uniqueRoutes = [];
        foreach ($routes as $route) {
            $key = $route['origin'] . '-' . $route['destination'];
            if (!isset($seenRoutes[$key])) {
                $seenRoutes[$key] = true;
                $uniqueRoutes[] = $route;
            }
        }
        $routes = $uniqueRoutes;
    }
    
    // Get airline information from extracted flight data
    $airlineCode = $flightData['airline_code'] ?? null;
    $airlineName = $flightData['airline_name'] ?? null;
    $cabinClass = $flightData['cabin_class'] ?? null;
    $departureDate = $flightData['departure_date'] ?? null;
    $departureTime = $flightData['departure_time'] ?? null;
    $arrivalTime = $flightData['arrival_time'] ?? null;
    $departureTerminal = $flightData['departure_terminal'] ?? null;
    $arrivalTerminal = $flightData['arrival_terminal'] ?? null;
    $flightNumber = $flightData['flight_number'] ?? null;
    
    // Extract booking date - pattern: "Booking Date : Thursday, November 20, 2025"
    $bookingDate = null;
    if (preg_match('/Booking\s+Date\s*:\s*\w+,\s+([A-Za-z]+)\s+(\d{1,2}),\s+(\d{4})/i', $text, $match)) {
        $bookingDate = parseTicketDate($match[2], $match[1], $match[3]);
    }
    
    // Extract issue date (usually same as booking date for e-tickets)
    $issueDate = $bookingDate;
    
    // Extract agency/issuer information - pattern from address section
    $issuedBy = null;
    if (preg_match('/Electronic\s+Ticket\s+Receipt.*?\n\s*([^\n]+)(?:\n|,)/is', $text, $match)) {
        $issuedBy = trim($match[1]);
    } elseif (preg_match('/Office\s+#\d+,\s+([^\n]+)/i', $text, $match)) {
        $issuedBy = trim($match[1]);
    }
    
    // Build passenger records for each segment (only if not already extracted from table)
    if (empty($passengers)) {
        if (!empty($routes)) {
            $timeIndex = 0;
            foreach ($routes as $idx => $route) {
                $passenger = [];
                $passenger['passenger_name'] = $passengerName;
                $passenger['pnr'] = $pnr;
                $passenger['ticket_number'] = $ticketNumber;
                
                // Flight details
                $passenger['origin'] = $route['origin'];
                $passenger['origin_city'] = getAirportName($route['origin']);
                $passenger['destination'] = $route['destination'];
                $passenger['destination_city'] = getAirportName($route['destination']);
                
                // Date and time information
                $passenger['departure_date'] = $departureDate;
                if ($timeIndex + 1 < count($times)) {
                    $passenger['departure_time'] = $times[$timeIndex];
                    $passenger['arrival_time'] = $times[$timeIndex + 1];
                    $timeIndex += 2;
                } elseif ($timeIndex < count($times)) {
                    $passenger['departure_time'] = $times[$timeIndex];
                    $timeIndex++;
                }
                
                // Flight number
                $passenger['airline'] = $airlineName ?? 'Unknown Airline';
                $passenger['airline_code'] = $airlineCode ?? 'XX';
                if ($idx < count($flightNumbers)) {
                    $passenger['flight_number'] = $flightNumbers[$idx]['full'];
                }
                
                // Class and service information
                $passenger['cabin_class'] = $cabinClass ?? 'Economy';
                $passenger['ticket_status'] = 'Confirmed';
                $passenger['is_confirmed'] = true;
                $passenger['trip_type'] = count($routes) > 1 ? 'Round Trip' : 'One Way';
                
                // Booking information
                $passenger['issue_date'] = $issueDate;
                $passenger['issued_by'] = $issuedBy;
                $passenger['terms'] = 'Subject to Warsaw Convention conditions of carriage';
                
                // Metadata
                $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
                $passenger['format_detected'] = 'skyportal';
                
                if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
                    $passengers[] = $passenger;
                }
            }
        } else {
            // Fallback: create single record with basic info
            $passenger = [];
            $passenger['passenger_name'] = $passengerName;
            $passenger['pnr'] = $pnr;
            $passenger['ticket_number'] = $ticketNumber;
            $passenger['airline'] = $airlineName ?? 'Unknown Airline';
            $passenger['airline_code'] = $airlineCode ?? 'XX';
            $passenger['cabin_class'] = $cabinClass ?? 'Economy';
            $passenger['issue_date'] = $issueDate;
            $passenger['issued_by'] = $issuedBy;
            $passenger['terms'] = 'Subject to Warsaw Convention conditions of carriage';
            $passenger['ticket_status'] = 'Confirmed';
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'skyportal';
            
            if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
                $passengers[] = $passenger;
            }
        }
    } else {
        // Enhance already-extracted passengers with additional data from text
        foreach ($passengers as &$passenger) {
            // Add flight data from Flight Details section
            if (!isset($passenger['airline']) && $airlineName) {
                $passenger['airline'] = $airlineName;
            }
            if (!isset($passenger['airline_code']) && $airlineCode) {
                $passenger['airline_code'] = $airlineCode;
            }
            if (!isset($passenger['flight_number']) && $flightNumber) {
                $passenger['flight_number'] = $flightNumber;
            }
            if (!isset($passenger['origin']) && isset($flightData['origin'])) {
                $passenger['origin'] = $flightData['origin'];
                $passenger['origin_city'] = getAirportName($flightData['origin']);
            }
            if (!isset($passenger['destination']) && isset($flightData['destination'])) {
                $passenger['destination'] = $flightData['destination'];
                $passenger['destination_city'] = getAirportName($flightData['destination']);
            }
            if (!isset($passenger['cabin_class']) && $cabinClass) {
                $passenger['cabin_class'] = $cabinClass;
            }
            if (!isset($passenger['departure_date']) && $departureDate) {
                $passenger['departure_date'] = $departureDate;
            }
            if (!isset($passenger['departure_time']) && $departureTime) {
                $passenger['departure_time'] = $departureTime;
            }
            if (!isset($passenger['arrival_time']) && $arrivalTime) {
                $passenger['arrival_time'] = $arrivalTime;
            }
            if (!isset($passenger['departure_terminal']) && $departureTerminal) {
                $passenger['departure_terminal'] = $departureTerminal;
            }
            if (!isset($passenger['arrival_terminal']) && $arrivalTerminal) {
                $passenger['arrival_terminal'] = $arrivalTerminal;
            }
            if (!isset($passenger['issue_date']) && $issueDate) {
                $passenger['issue_date'] = $issueDate;
            }
            if (!isset($passenger['issued_by']) && $issuedBy) {
                $passenger['issued_by'] = $issuedBy;
            }
            if (!isset($passenger['terms'])) {
                $passenger['terms'] = 'Subject to Warsaw Convention conditions of carriage';
            }
            
            // Set trip type and status
            $passenger['trip_type'] = $tripType ?? (count($passengers) > 1 ? 'Round Trip' : 'One Way');
            if (!isset($passenger['ticket_status']) && $status) {
                $passenger['ticket_status'] = ucfirst(strtolower($status));
                $passenger['is_confirmed'] = strtolower($status) === 'confirmed';
            }
            
            $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
            $passenger['format_detected'] = 'skyportal';
        }
    }
    
    // Always return structured format matching Kam Air/Ariana structure
    // This ensures consistency across all ticket formats
    
    // If multiple segments or passengers, group them
    if (count($passengers) > 1) {
        return [
            'is_group_booking' => true,
            'booking_reference' => $pnr ?? $bookingRef,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'skyportal',
        ];
    }
    
    // Single passenger - still return in grouped format for consistency
    if (count($passengers) === 1) {
        return [
            'is_group_booking' => false,
            'booking_reference' => $pnr ?? $bookingRef,
            'total_passengers' => 1,
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'skyportal',
        ];
    }
    
    // No passengers found - return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => $pnr ?? $bookingRef,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'skyportal',
    ];
}

/**
 * Extract NSSTravel Portal e-ticket format
 */
function extractNSSPortalTicket($text) {
    $passengers = [];
    
    // Extract Super PNR number (main booking reference)
    $superPnr = null;
    if (preg_match('/Super\s+PNR\s+no[.\s]*(\d+)/i', $text, $match)) {
        $superPnr = $match[1];
    } elseif (preg_match('/PNR\s*:\s*([A-Z0-9]{4,6})/i', $text, $match)) {
        // Fallback: look for any PNR pattern
        $superPnr = $match[1];
    }
    
    // Extract individual airline PNRs and passenger info
    // Look for patterns like: Airline PNR: QEAW4E
    $pnrMatches = [];
    if (preg_match_all('/(?:Airline\s+PNR|PNR)\s*:\s*([A-Z0-9]+)/i', $text, $matches)) {
        $pnrMatches = $matches[1];
    }
    
    // Extract flight segments - look for KBL/DXB/RUH patterns with times
    // Pattern: DXB 09:15 12:25 or KBL 09:15 12:25
    $flightBlocks = [];
    if (preg_match_all('/([A-Z]{3})\s+(\d{2}):(\d{2})\s+(\d{2}):(\d{2})/i', $text, $matches, PREG_SET_ORDER)) {
        // Extract dates - look for "23 December 2025" patterns
        $dates = [];
        if (preg_match_all('/(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $text, $dateMatches, PREG_SET_ORDER)) {
            foreach ($dateMatches as $dateMatch) {
                $dates[] = [
                    'day' => $dateMatch[1],
                    'month' => substr($dateMatch[2], 0, 3),
                    'year' => $dateMatch[3],
                ];
            }
        }
        
        // Build flight blocks
        foreach ($matches as $idx => $match) {
            $flightBlocks[] = [
                'airport' => $match[1],
                'dep_hour' => $match[2],
                'dep_min' => $match[3],
                'arr_hour' => $match[4],
                'arr_min' => $match[5],
                'date' => $dates[0] ?? null, // Use first date for now
            ];
        }
    }
    
    $segmentDetails = [];
    $airlineNames = [];
    $airlineCodes = [];
    $flightNumbersFromSegments = [];
    $monthPattern = 'January|February|March|April|May|June|July|August|September|October|November|December';
    $airlinePattern = 'Emirates|Air\s+China|Fly\s+Dubai|Kam\s+Air|Ariana\s+Afghan\s+Airlines|Qatar\s+Airways|Turkish\s+Airlines';
    if (preg_match_all('/(' . $airlinePattern . ')\s+([A-Z0-9]{2})\s+(\d{2,4}).{0,120}?(\d{1,2})\s+(' . $monthPattern . ')\s+(\d{4})\s+([A-Z]{3})\s+([A-Z]{3})\s+(\d{2}):(\d{2})\s+(\d{2}):(\d{2})/is', $text, $segmentMatches, PREG_SET_ORDER)) {
        foreach ($segmentMatches as $segmentMatch) {
            $airlineName = normalizePassengerName($segmentMatch[1]);
            $airlineCode = strtoupper($segmentMatch[2]);
            $flightNumber = $airlineCode . $segmentMatch[3];
            $date = parseTicketDate($segmentMatch[4], substr($segmentMatch[5], 0, 3), $segmentMatch[6]);
            $origin = strtoupper($segmentMatch[7]);
            $destination = strtoupper($segmentMatch[8]);
            
            $segmentDetails[] = [
                'airline' => $airlineName,
                'airline_code' => $airlineCode,
                'flight_number' => $flightNumber,
                'origin' => $origin,
                'destination' => $destination,
                'departure_date' => $date,
                'departure_time' => sprintf('%02d:%02d', $segmentMatch[9], $segmentMatch[10]),
                'arrival_time' => sprintf('%02d:%02d', $segmentMatch[11], $segmentMatch[12]),
            ];
            $airlineNames[] = $airlineName;
            $airlineCodes[] = $airlineCode;
            $flightNumbersFromSegments[] = $flightNumber;
        }
    }
    
    // Extract passenger name from greeting, name section, or email
    $passengerName = null;
    if (preg_match('/(?:Hi|Dear|Hello)\s+([A-Z][A-Za-z\s]+?)(?:\s+(?:thank\s+you|,))/i', $text, $match)) {
        $passengerName = trim($match[1]);
    } elseif (preg_match('/Passenger\s+Name.*?:\s*([A-Z][A-Za-z\s]+?)(?:\n|$)/i', $text, $match)) {
        $passengerName = trim($match[1]);
    }
    
    // Extract cabin class information
    $cabinClass = null;
    if (preg_match('/Economy\s+Lite/i', $text)) {
        $cabinClass = 'Economy Lite';
    } elseif (preg_match('/(Business|First|Premium\s+Economy|Economy)/i', $text, $match)) {
        $cabinClass = trim($match[1]);
    }
    
    // Extract contact details (travel agency)
    $agencyName = null;
    if (preg_match('/Company\s+Name\s*\n?\s*:\s*([^\n]+)/i', $text, $match)) {
        $agencyName = trim($match[1]);
    }
    
    // Extract ticket numbers from electronic ticket section
    $ticketNumbers = [];
    if (preg_match_all('/\b(\d{13})\b/i', $text, $matches)) {
        $ticketNumbers = array_unique($matches[1]);
    }
    
    // Extract route information - look for specific airport codes
    $validAirports = array_keys(AIRPORTS);
    // Blacklist of invalid codes that might appear in text
    $invalidCodes = ['DES', 'ORG', 'GIN', 'NAM', 'DAT', 'TYP', 'CAB', 'STA', 'CLS'];
    $routes = [];
    if (preg_match_all('/([A-Z]{3})\s*[\-–]\s*([A-Z]{3})/i', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $origin = strtoupper($match[1]);
            $dest = strtoupper($match[2]);
            // Only add if both are valid airports or at least known codes
            if ($origin !== $dest && 
                strlen($origin) === 3 && 
                strlen($dest) === 3 &&
                !in_array($origin, $invalidCodes) &&
                !in_array($dest, $invalidCodes) &&
                (in_array($origin, $validAirports) || in_array($dest, $validAirports))) {
                $routes[] = ['origin' => $origin, 'destination' => $dest];
            }
        }
    }
    
    // Remove duplicate routes
    $seenRoutes = [];
    $uniqueRoutes = [];
    foreach ($routes as $route) {
        $key = $route['origin'] . '-' . $route['destination'];
        if (!isset($seenRoutes[$key])) {
            $seenRoutes[$key] = true;
            $uniqueRoutes[] = $route;
        }
    }
    $routes = $uniqueRoutes;
    if (empty($routes) && !empty($segmentDetails)) {
        foreach ($segmentDetails as $segment) {
            $routes[] = [
                'origin' => $segment['origin'],
                'destination' => $segment['destination'],
            ];
        }
    }
    
    // Build single passenger record for multi-segment or single segment
    if (!empty($routes)) {
        $passenger = [];
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $pnrMatches[0] ?? $superPnr;
        $passenger['super_pnr'] = $superPnr;
        
        // Combine all ticket numbers
        $passenger['ticket_number'] = !empty($ticketNumbers) ? implode(', ', $ticketNumbers) : null;
        
        // For multi-segment: origin = first route, destination = last route
        if (count($routes) > 1) {
            $passenger['origin'] = $routes[0]['origin'];
            $passenger['origin_city'] = getAirportName($routes[0]['origin']);
            $passenger['destination'] = $routes[count($routes) - 1]['destination'];
            $passenger['destination_city'] = getAirportName($routes[count($routes) - 1]['destination']);
            $passenger['trip_type'] = 'Multi Segment';
            // Store all segments for reference
            $passenger['segments'] = $routes;
        } else {
            // Single segment: use the only route
            $passenger['origin'] = $routes[0]['origin'];
            $passenger['origin_city'] = getAirportName($routes[0]['origin']);
            $passenger['destination'] = $routes[0]['destination'];
            $passenger['destination_city'] = getAirportName($routes[0]['destination']);
            $passenger['trip_type'] = 'One Way';
        }
        
        // Parse departure date and time from first flight block
        if (!empty($flightBlocks) && isset($flightBlocks[0])) {
            $flight = $flightBlocks[0];
            if ($flight['date']) {
                $passenger['departure_date'] = parseTicketDate($flight['date']['day'], $flight['date']['month'], $flight['date']['year']);
            }
            $passenger['departure_time'] = sprintf('%02d:%02d', (int)$flight['dep_hour'], (int)$flight['dep_min']);
        }
        
        // Parse arrival time from last flight block
        if (!empty($flightBlocks) && isset($flightBlocks[count($flightBlocks) - 1])) {
            $lastFlight = $flightBlocks[count($flightBlocks) - 1];
            $passenger['arrival_time'] = sprintf('%02d:%02d', (int)$lastFlight['arr_hour'], (int)$lastFlight['arr_min']);
            // For multi-segment, arrival date = last segment date
            if ($lastFlight['date']) {
                $passenger['arrival_date'] = parseTicketDate($lastFlight['date']['day'], $lastFlight['date']['month'], $lastFlight['date']['year']);
            }
        }
        
        // Airline information
        $passenger['airline'] = !empty($airlineNames) ? implode(', ', array_unique($airlineNames)) : 'Unknown Airline';
        $passenger['airline_code'] = !empty($airlineCodes) ? implode(', ', array_unique($airlineCodes)) : null;
        
        // Extract all flight numbers
        $flightNumbers = $flightNumbersFromSegments;
        if (empty($flightNumbers) && preg_match_all('/([A-Z0-9]{2})\s+(\d{2,4})/i', $text, $flightMatches, PREG_SET_ORDER)) {
            foreach ($flightMatches as $flightMatch) {
                $flightNumbers[] = strtoupper($flightMatch[1]) . $flightMatch[2];
            }
        }
        $passenger['flight_number'] = !empty($flightNumbers) ? implode(', ', $flightNumbers) : null;
        if (!empty($segmentDetails)) {
            $passenger['segments'] = $segmentDetails;
            $passenger['departure_date'] = $segmentDetails[0]['departure_date'];
            $passenger['departure_time'] = $segmentDetails[0]['departure_time'];
            $passenger['arrival_time'] = $segmentDetails[count($segmentDetails) - 1]['arrival_time'];
        }
        
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        $passenger['ticket_status'] = 'Confirm';
        $passenger['is_confirmed'] = true;
        $passenger['baggage_allowance'] = 'None';
        $passenger['meal_service'] = 'Standard meal';
        
        // Agency and booking info
        $passenger['issued_by'] = $agencyName;
        
        // Metadata
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'nsstportal';
        
        if (!empty($passenger) && (isset($passenger['pnr']) || isset($passenger['ticket_number']))) {
            $passengers[] = $passenger;
        }
    } else {
        // Fallback: create single record with basic info
        $passenger = [];
        $passenger['passenger_name'] = $passengerName;
        $passenger['pnr'] = $superPnr;
        $passenger['airline'] = !empty($airlineNames) ? implode(', ', array_unique($airlineNames)) : 'Unknown Airline';
        $passenger['airline_code'] = !empty($airlineCodes) ? implode(', ', array_unique($airlineCodes)) : null;
        $passenger['cabin_class'] = $cabinClass ?? 'Economy';
        $passenger['issued_by'] = $agencyName;
        $passenger['ticket_status'] = 'Confirm';
        $passenger['extraction_confidence'] = calculateConfidenceScore($passenger);
        $passenger['format_detected'] = 'nsstportal';
        
        if (!empty($passenger) && isset($passenger['pnr'])) {
            $passengers[] = $passenger;
        }
    }
    
    // Always return single passenger, never multiple
    if (count($passengers) >= 1) {
        return [
            'is_group_booking' => false,
            'booking_reference' => $superPnr ?? $pnrMatches[0] ?? 'Unknown',
            'total_passengers' => 1,
            'passengers' => [$passengers[0]], // Always single passenger
            'flight_info' => extractCommonFlightInfo([$passengers[0]]),
            'agency' => $agencyName,
            'format_detected' => 'nsstportal',
        ];
    }
    
    // No passengers found - create minimal valid structure
    return [
        'is_group_booking' => false,
        'booking_reference' => $superPnr ?? $pnrMatches[0] ?? 'Unknown',
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'nsstportal',
    ];
}

/**
 * Extract standard IATA format tickets (fallback)
 */
function extractStandardTicket($text) {
    $passengers = [];
    $data = [];
    
    // Basic PNR extraction
    if (preg_match('/(?:Booking|PNR|Record Locator|Confirmation|Reference)[:\s#]+([A-Z0-9]{6})\b/i', $text, $match)) {
        $data['pnr'] = $match[1];
    }
    
    // Ticket number
    if (preg_match('/Ticket\s*(?:Number|#)[:\s]*(\d{13})/i', $text, $match)) {
        $data['ticket_number'] = $match[1];
    }
    
    // Passenger name
    if (preg_match('/(?:Passenger|Name)[:\s]*([A-Za-z\s]+)/i', $text, $match)) {
        $data['passenger_name'] = normalizePassengerName($match[1]);
    }
    
    // Origin/Destination
    if (preg_match('/([A-Z]{3})\s*-\s*([A-Z]{3})/', $text, $match)) {
        $data['origin'] = $match[1];
        $data['destination'] = $match[2];
        $data['origin_city'] = getAirportName($match[1]);
        $data['destination_city'] = getAirportName($match[2]);
    }
    
    $data['extraction_confidence'] = calculateConfidenceScore($data);
    $data['format_detected'] = 'standard';
    
    // Only add if we have some meaningful data
    if (!empty($data['pnr']) || !empty($data['ticket_number']) || !empty($data['passenger_name'])) {
        $passengers[] = $data;
    }
    
    // Return in grouped format
    if (!empty($passengers)) {
        return [
            'is_group_booking' => count($passengers) > 1,
            'booking_reference' => $passengers[0]['pnr'] ?? null,
            'total_passengers' => count($passengers),
            'passengers' => $passengers,
            'flight_info' => extractCommonFlightInfo($passengers),
            'format_detected' => 'standard',
        ];
    }
    
    // Return empty structure
    return [
        'is_group_booking' => false,
        'booking_reference' => null,
        'total_passengers' => 0,
        'passengers' => [],
        'flight_info' => [],
        'format_detected' => 'standard',
    ];
}

/**
 * Parse date of birth format: 02AUG96 → 1996-08-02
 */
function parseDateOfBirth($dob) {
    if (preg_match('/(\d{2})([A-Z]{3})(\d{2})/', $dob, $match)) {
        $day = $match[1];
        $month = $match[2];
        $year = $match[3];
        
        // Convert 2-digit year (96 → 1996)
        $year = (int)$year;
        $year = $year > 25 ? 1900 + $year : 2000 + $year;
        
        return parseTicketDate($day, $month, $year);
    }
    return null;
}

/**
 * Parse issue date format: 9DEC2025 → 2025-12-09
 */
function parseIssueDateFormat($dateStr) {
    if (preg_match('/(\d{1,2})([A-Z]{3})(\d{4})/', $dateStr, $match)) {
        return parseTicketDate($match[1], $match[2], $match[3]);
    }
    return null;
}

/**
 * Map reservation class code to cabin class
 */
function mapReservationClass($code) {
    $mapping = [
        'F' => 'First Class',
        'J' => 'Business Class',
        'C' => 'Business Class',
        'Y' => 'Economy Class',
        'W' => 'Premium Economy',
        'S' => 'Economy Class',
        'B' => 'Economy Class',
        'M' => 'Economy Class',
        'H' => 'Economy Class',
        'Q' => 'Economy Class',
        'K' => 'Economy Class',
        'L' => 'Economy Class',
    ];
    
    return $mapping[strtoupper($code)] ?? 'Economy Class';
}

/**
 * Extract common flight info from multiple passengers
 */
function extractCommonFlightInfo($passengers) {
    if (empty($passengers)) return [];
    
    $first = $passengers[0];
    return [
        'airline' => $first['airline'] ?? null,
        'airline_code' => $first['airline_code'] ?? null,
        'flight_number' => $first['flight_number'] ?? null,
        'origin' => $first['origin'] ?? null,
        'origin_city' => $first['origin_city'] ?? null,
        'destination' => $first['destination'] ?? null,
        'destination_city' => $first['destination_city'] ?? null,
        'departure_date' => $first['departure_date'] ?? null,
        'departure_time' => $first['departure_time'] ?? null,
        'arrival_date' => $first['arrival_date'] ?? null,
        'arrival_time' => $first['arrival_time'] ?? null,
    ];
}

/**
 * Clean ticket text
 */
function cleanTicketText($text) {
    // Remove control characters but preserve spaces and newlines
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text);
    
    // Remove excessive spaces but preserve newlines and at least one space between words
    // This prevents the aggressive space removal that was breaking the traveller details regex
    // Only collapse 3+ consecutive spaces to 2 spaces (preserves some spacing for data alignment)
    $text = preg_replace('/[ \t]{3,}/', '  ', $text);
    
    return trim($text);
}

// ============================================
// DISPLAY FUNCTIONS
// ============================================

function displayResults($result) {
    echo "=== TICKET EXTRACTION RESULTS ===\n\n";
    
    // Handle multi-segment bookings
    if (isset($result['is_multi_segment']) && $result['is_multi_segment']) {
        echo "MULTI-SEGMENT BOOKING DETECTED\n";
        echo "Total Segments: " . $result['total_segments'] . "\n";
        echo "Booking Reference: " . $result['booking_reference'] . "\n";
        
        // Show passenger info if available from first segment
        if (!empty($result['passengers']) && isset($result['passengers'][0]['passenger_name'])) {
            echo "Passenger Name: " . $result['passengers'][0]['passenger_name'] . "\n";
        }
        
        if (isset($result['airline'])) {
            echo "Airline: " . $result['airline'] . "\n";
        }
        if (isset($result['cabin_class'])) {
            echo "Cabin Class: " . $result['cabin_class'] . "\n";
        }
        if (isset($result['agency'])) {
            echo "Agency: " . $result['agency'] . "\n";
        }
        if (isset($result['payment_reference'])) {
            echo "Payment Reference: " . $result['payment_reference'] . "\n";
        }
        
        echo "\n=== FLIGHT SEGMENTS ===\n";
        foreach ($result['passengers'] as $idx => $passenger) {
            echo "\n--- Segment " . ($idx + 1) . " ---\n";
            displayPassenger($passenger);
        }
    } elseif (isset($result['is_group_booking']) && $result['is_group_booking']) {
        echo "GROUP BOOKING DETECTED\n";
        echo "Total Passengers: " . $result['total_passengers'] . "\n";
        echo "Booking Reference: " . $result['booking_reference'] . "\n\n";
        
        echo "=== FLIGHT INFORMATION ===\n";
        foreach ($result['flight_info'] as $key => $value) {
            if ($value) {
                echo str_pad(ucwords(str_replace('_', ' ', $key)), 25) . ": $value\n";
            }
        }
        
        echo "\n=== PASSENGERS ===\n";
        foreach ($result['passengers'] as $idx => $passenger) {
            echo "\n--- Passenger " . ($idx + 1) . " ---\n";
            displayPassenger($passenger);
        }
    } else {
        displayPassenger($result);
    }
}

function displayPassenger($data) {
    $fields = [
        'ticket_number' => 'Ticket Number',
        'pnr' => 'PNR/Booking Reference',
        'passenger_name' => 'Passenger Name',
        'first_name' => 'First Name',
        'last_name' => 'Last Name',
        'date_of_birth' => 'Date of Birth',
        'airline' => 'Airline',
        'airline_code' => 'Airline Code',
        'flight_number' => 'Flight Number',
        'origin' => 'Origin',
        'origin_city' => 'Origin Airport',
        'destination' => 'Destination',
        'destination_city' => 'Destination Airport',
        'departure_date' => 'Departure Date',
        'departure_time' => 'Departure Time',
        'arrival_date' => 'Arrival Date',
        'arrival_time' => 'Arrival Time',
        'departure_terminal' => 'Departure Terminal',
        'arrival_terminal' => 'Arrival Terminal',
        'cabin_class' => 'Cabin Class',
        'reservation_class' => 'Reservation Class',
        'ticket_status' => 'Status',
        'is_confirmed' => 'Confirmed',
        'baggage_allowance' => 'Baggage',
        'seat_number' => 'Seat',
        'issue_date' => 'Issue Date',
        'issued_by' => 'Issued By',
        'fare_basis' => 'Fare Basis',
        'extraction_confidence' => 'Confidence Score',
    ];
    
    foreach ($fields as $key => $label) {
        if (isset($data[$key]) && $data[$key] !== null && $data[$key] !== '') {
            $value = $data[$key];
            if ($key === 'extraction_confidence') {
                $value = round($value * 100) . '%';
            }
            if ($key === 'is_confirmed') {
                $value = $value ? 'Yes' : 'No';
            }
            echo str_pad($label, 25) . ": $value\n";
        }
    }
}


?>
