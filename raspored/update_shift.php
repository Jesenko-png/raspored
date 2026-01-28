<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');

$conn = new mysqli('localhost', 'root', '', 'raspored');
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'DB connect error']);
    exit;
}

$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$date = $_POST['shift_date'] ?? '';
$type = $_POST['shift_type'] ?? ''; // '' = slobodno

$allowed = ['jutarnja','popodnevna','nocna',''];
if ($employeeId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !in_array($type, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad input']);
    exit;
}

if ($type === '') {
    $stmt = $conn->prepare("DELETE FROM shifts WHERE employee_id = ? AND shift_date = ?");
    $stmt->bind_param('is', $employeeId, $date);
    $stmt->execute();
    echo json_encode(['ok' => true, 'shift_type' => '']);
    exit;
}

$stmt = $conn->prepare("
  INSERT INTO shifts (employee_id, shift_date, shift_type)
  VALUES (?, ?, ?)
  ON DUPLICATE KEY UPDATE shift_type = VALUES(shift_type)
");
$stmt->bind_param('iss', $employeeId, $date, $type);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $stmt->error]);
    exit;
}

echo json_encode(['ok' => true, 'shift_type' => $type]);

