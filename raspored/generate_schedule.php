<?php
define('BASE_URL', '/raspored');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'raspored');
if ($conn->connect_error) die('DB error: ' . $conn->connect_error);

$year  = isset($_POST['year']) ? (int)$_POST['year'] : (isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y'));
$month = isset($_POST['month']) ? (int)$_POST['month'] : (isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m'));
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$monthStr = str_pad((string)$month, 2, '0', STR_PAD_LEFT);

function countWorkdaysMonFri(int $year, int $month): int {
    $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $count = 0;
    for ($d=1; $d <= $days; $d++) {
        $ts = strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d));
        $w = (int)date('N', $ts);
        if ($w >= 1 && $w <= 5) $count++;
    }
    return $count;
}
function monthlyNormHours(float $weeklyHours, int $year, int $month): float {
    $workdays = countWorkdaysMonFri($year, $month);
    return ($weeklyHours / 5.0) * $workdays;
}

/** ✅ ne dupliraj */
if ($conn->query("DELETE FROM shifts WHERE shift_date LIKE '$year-$monthStr-%'") === false) {
    die('Delete error: ' . $conn->error);
}

$resEmp = $conn->query("SELECT id, can_night, weekly_hours FROM employees");
if (!$resEmp) die('Employees error: ' . $conn->error);
$employees = $resEmp->fetch_all(MYSQLI_ASSOC);

if (count($employees) === 0) {
    header("Location: " . BASE_URL . "/views/schedule.php?year=$year&month=$month&success=1");
    exit;
}

$shiftHours = 8.0;   
$nightRatio = 0.12;  
$quota = [];
$lastShift = [];
$consec = []; 

foreach ($employees as $e) {
    $id = (int)$e['id'];
    $canNight = (int)$e['can_night'] === 1;
    $weekly = (float)$e['weekly_hours'];

    $norm = monthlyNormHours($weekly, $year, $month);
    $requiredWorkDays = (int)ceil($norm / $shiftHours);
    if ($requiredWorkDays > $daysInMonth) $requiredWorkDays = $daysInMonth;
    if ($requiredWorkDays < 0) $requiredWorkDays = 0;

    $off = $daysInMonth - $requiredWorkDays;
    if ($off < 0) $off = 0;

    $night = $canNight ? (int)round($requiredWorkDays * $nightRatio) : 0;
    if ($night > $requiredWorkDays) $night = 0;

    $remain = $requiredWorkDays - $night;
    $morning = (int)floor($remain / 2);
    $evening = $remain - $morning;

    $quota[$id] = [
        'off' => $off,
        'nocna' => $night,
        'jutarnja' => $morning,
        'popodnevna' => $evening
    ];
}

function pickShift(array &$q, ?string $prev): ?string {
    $cands = [];
    foreach (['off','jutarnja','popodnevna','nocna'] as $t) {
        if (($q[$t] ?? 0) > 0) $cands[] = $t;
    }
    if (!$cands) return null;


    if ($prev === 'popodnevna') {
        $cands = array_values(array_filter($cands, fn($x) => $x !== 'jutarnja'));
        if (!$cands) return null;
    }


    usort($cands, fn($a,$b) => ($q[$b] ?? 0) <=> ($q[$a] ?? 0));
    $top = array_slice($cands, 0, min(2, count($cands)));
    return $top[array_rand($top)];
}

$stmt = $conn->prepare("INSERT INTO shifts (employee_id, shift_date, shift_type) VALUES (?, ?, ?)");
if (!$stmt) die('Prepare error: ' . $conn->error);

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

    foreach ($employees as $e) {
        $id = (int)$e['id'];

 
        $worked = $consec[$id] ?? 0;
        if ($worked >= 7) {

            if (($quota[$id]['off'] ?? 0) <= 0) {
                if (($quota[$id]['popodnevna'] ?? 0) > 0) { $quota[$id]['popodnevna']--; $quota[$id]['off'] = 1; }
                else if (($quota[$id]['jutarnja'] ?? 0) > 0) { $quota[$id]['jutarnja']--; $quota[$id]['off'] = 1; }
                else if (($quota[$id]['nocna'] ?? 0) > 0) { $quota[$id]['nocna']--; $quota[$id]['off'] = 1; }
            }

            if (($quota[$id]['off'] ?? 0) > 0) {
                $quota[$id]['off']--;
                $lastShift[$id] = 'off';
                $consec[$id] = 0;
                continue;
            }
        }

        $prev = $lastShift[$id] ?? null;
        $choice = pickShift($quota[$id], $prev);
        if ($choice === null) continue;

        if ($choice === 'off') {
            $quota[$id]['off']--;
            $lastShift[$id] = 'off';
            $consec[$id] = 0;  
            continue;
        }

        $stmt->bind_param('iss', $id, $date, $choice);
        if (!$stmt->execute()) die('Insert error: ' . $stmt->error);

        $quota[$id][$choice]--;
        $lastShift[$id] = $choice;
        $consec[$id] = ($consec[$id] ?? 0) + 1; 
    }
}

$fromAdd = isset($_GET['employee_added']) ? 1 : 0;
header("Location: " . BASE_URL . "/views/schedule.php?year=$year&month=$month&success=1&employee_added=$fromAdd");
exit;


