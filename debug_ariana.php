<?php
require_once 'includes/ticket_patterns.php';

// Your actual extracted PDF text
$pdfText = <<<'TEXT'
e-ticket
BOOKING #
QYCJIV
Passengers
MRS GEETA MOHIBZADA
255 1019 951 835
MR MANSOUR KARIMEE
255 1019 951 836
Travel Itinerary
Thursday
18-DEC
FG-252ECONOMY 737
REPORTING TIME: Passengers must check-in 3:00 hrs before flight departure.
TICKET EXCHANGE: Ticket Fares may change without prior notice and passenger shall pay the Fare difference amount, which
may have been changed.
Ticket Details
ticket # / couponflight route date fare family fare basisprice status
MRS GEETA MOHIBZADA
255 1019 951 835 / 1FG-252HEA-KBL18 Dec 2025Basic (20kg)20kgsLBASIC20AFN 6,542 OK
MR MANSOUR KARIMEE
255 1019 951 836 / 1FG-252HEA-KBL18 Dec 2025Basic (20kg)20kgsLBASIC20AFN 6,542 OK
Total Ticket Value:AFN13,084
TEXT;

echo "=== DEBUG INFO ===\n\n";

// Test passenger name extraction
echo "1. Testing passenger name extraction:\n";
if (preg_match_all('/(MRS?|MS|MISS|DR|PROF|MR|MRS)\s+([A-Z\s]+?)(?=\s*\d{3}\s+\d{4}|\s+\d{15}|$)/i', $pdfText, $matches, PREG_SET_ORDER)) {
    echo "Found " . count($matches) . " passengers\n";
    foreach ($matches as $idx => $match) {
        echo "  $idx: {$match[1]} {$match[2]}\n";
    }
} else {
    echo "No passengers found\n";
}

echo "\n2. Testing ticket pattern extraction:\n";
$ticketPattern = '/(\d{3}\s+\d{4}\s+\d{3}\s+\d{3})\s*\/\s*(\d+)\s*(FG-\d+)\s*([A-Z]{3})-([A-Z]{3})\s*(\d{1,2}\s+[A-Za-z]{3}\s+\d{4})\s*([A-Za-z\s()0-9]+?)\s*(\d+kgs?)\s*([A-Z0-9]+?)\s*AFN\s+([\d,]+)\s+([A-Z]+)/i';
if (preg_match_all($ticketPattern, $pdfText, $matches, PREG_SET_ORDER)) {
    echo "Found " . count($matches) . " tickets\n";
    foreach ($matches as $idx => $match) {
        echo "  Ticket $idx: #{$match[1]} / Flight {$match[3]} Route {$match[4]}-{$match[5]}\n";
    }
} else {
    echo "No tickets found\n";
}

echo "\n3. Full extraction:\n";
$result = extractTicketData($pdfText);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
