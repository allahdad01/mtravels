<?php
$_SERVER['REQUEST_URI'] = '/api/umrah/rooming_list_excel.php?ticket_ids=b394,b395,b396,b397,b398,b399,b400,b401,b402,b403,b404,b405&language=en';
$_GET['ticket_ids'] = 'b394,b395,b396,b397,b398,b399,b400,b401,b402,b403,b404,b405';
$_GET['language'] = 'en';
session_id('opencode-e2e-test');
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['tenant_id'] = 2;
$_SESSION['branch_id'] = 2;
echo "MARK-START\n";
ob_start();
require __DIR__ . '/rooming_list_excel.php';
$bin = (string)ob_get_clean();
echo "MARK-CAPTURED len=" . strlen($bin) . "\n";
file_put_contents('C:\\Users\\ALLAHD~1\\AppData\\Local\\Temp\\opencode\\rooming_out.xlsx', $bin, LOCK_EX);
$z = new ZipArchive();
$r = $z->open('C:/Users/ALLAHD~1/AppData/Local/Temp/opencode/rooming_out.xlsx');
echo "zip_open=" . var_export($r, true) . "\n";
if ($r === true) {
    $x = $z->getFromName('xl/worksheets/sheet1.xml');
    $z->close();
    $t = preg_replace('/<[^>]+>/u', ' | ', (string)$x);
    $t = preg_replace('/\s+/u', ' ', $t);
    echo "SHEET-START\n" . $t . "\nSHEET-END\n";
}