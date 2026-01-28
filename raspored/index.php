<?php
define('BASE_URL', '/raspored');

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

$success = isset($_GET['success']) ? (int)$_GET['success'] : 0;
$error = isset($_GET['error']) ? $_GET['error'] : '';

function countWorkdaysMonFri(int $year, int $month): int {
    $days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $count = 0;
    for ($d=1; $d <= $days; $d++) {
        $ts = strtotime(sprintf('%04d-%02d-%02d', $year, $month, $d));
        $w = (int)date('N', $ts); // 1=Mon..7=Sun
        if ($w >= 1 && $w <= 5) $count++;
    }
    return $count;
}

$workdays = countWorkdaysMonFri($year, $month);
?>
<!DOCTYPE html>
<html lang="hr">
<head>
  <meta charset="UTF-8">
  <title>Dodaj uposlenika</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/style.css">
  <style>
    .alert { padding: 10px 12px; border-radius: 8px; margin: 0 0 12px; font-weight: 700; }
    .ok { background: #e9f7ef; border: 1px solid #b7e2c3; }
    .bad { background: #fdecec; border: 1px solid #f5b5b5; }
    .hint { margin: 10px 0 0; font-size: 14px; color: #444; }
    .actions { margin-top: 12px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }
    .linkbtn { text-decoration:none; padding:10px 12px; border-radius:8px; border:1px solid #d7d7d7; background:#fafafa; font-weight:700; color:#111; }
    .linkbtn:hover { background:#f0f0f0; }
    .small { font-size: 13px; color:#555; margin-top:6px; }
  </style>
</head>
<body>

<div class="container">
  <h1>Dodaj uposlenika</h1>

  <?php if ($success === 1): ?>
    <div class="alert ok">✅ Uposlenik je dodan i plan je regenerisan.</div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div class="alert bad">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="small">
    Mjesec: <b><?= str_pad((string)$month,2,'0',STR_PAD_LEFT) ?>/<?= (int)$year ?></b> • Radnih dana (pon–pet): <b><?= $workdays ?></b>
  </div>

  <form method="post" action="<?= BASE_URL ?>/add_employee.php" class="employee-form">

    <div class="field">
      <label>Ime</label>
      <input type="text" name="name" required>
    </div>

    <div class="field">
      <label>Prezime</label>
      <input type="text" name="last_name" required>
    </div>

    <div class="field full">
      <label>Datum rođenja</label>
      <input type="date" name="birth_date" required>
    </div>

    <div class="field full">
      <label>Sati sedmično (npr. 40)</label>
      <input type="number" name="weekly_hours" step="0.5" min="1" max="60" value="40" required>
      <div class="hint">
        Norma: (weekly_hours / 5) × broj radnih dana (pon–pet) u mjesecu. Smjena = 8h.
      </div>
    </div>

    <label class="checkbox">
      <input type="checkbox" name="can_night" value="1">
      Može raditi noćnu smjenu
    </label>

    <input type="hidden" name="year" value="<?= (int)$year ?>">
    <input type="hidden" name="month" value="<?= (int)$month ?>">

    <button type="submit">➕ Dodaj i generiši plan</button>
  </form>

  <div class="actions">
    <a class="linkbtn" href="<?= BASE_URL ?>/views/schedule.php?year=<?= (int)$year ?>&month=<?= (int)$month ?>">
      📅 Otvori raspored (<?= str_pad((string)$month,2,'0',STR_PAD_LEFT) ?>/<?= (int)$year ?>)
    </a>
  </div>
</div>

</body>
</html>
