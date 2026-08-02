$lines = Get-Content 'C:\xampp\htdocs\mtravels\admin\date_change.php' -Encoding UTF8
$start = 655
$end = 810
for ($n = $start; $n -le $end -and $n -le $lines.Count; $n++) {
    '{0,5}: {1}' -f $n, $lines[$n - 1]
}
