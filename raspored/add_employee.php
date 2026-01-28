<?php
define('BASE_URL', '/raspored');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'raspored');
if ($conn->connect_error) {
    die('DB error: ' . $conn->connect_error);
}

$name = trim($_POST['name'] ?? '');
$last = trim($_POST['last_name'] ?? '');
$birth = $_POST['birth_date'] ?? '';
$canNight = isset($_POST['can_night']) ? 1 : 0;

$weeklyHours = isset($_POST['weekly_hours']) ? (float)$_POST['weekly_hours'] : 40.0;
if ($weeklyHours <= 0) $weeklyHours = 40.0;

$year = isset($_POST['year']) ? (int)$_POST['year'] : (int)date('Y');
$month = isset($_POST['month']) ? (int)$_POST['month'] : (int)date('m');
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

if ($name === '' || $last === '' || $birth === '') {
    header("Location: " . BASE_URL . "/index.php?year=$year&month=$month&error=" . urlencode("Sva polja su obavezna."));
    exit;
}

$stmt = $conn->prepare("
  INSERT INTO employees (name, last_name, birth_date, can_night, weekly_hours)
  VALUES (?, ?, ?, ?, ?)
");
if (!$stmt) {
    header("Location: " . BASE_URL . "/index.php?year=$year&month=$month&error=" . urlencode("Greška u bazi (prepare)."));
    exit;
}

$stmt->bind_param('sssid', $name, $last, $birth, $canNight, $weeklyHours);

if (!$stmt->execute()) {
    header("Location: " . BASE_URL . "/index.php?year=$year&month=$month&error=" . urlencode("Zaposleni već postoji (ime+prezime+datum rođenja)."));
    exit;
}

header("Location: " . BASE_URL . "/generate_schedule.php?year=$year&month=$month&employee_added=1");
exit;
