<?php
define('BASE_URL', '/raspored');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli('localhost', 'root', '', 'raspored');
if ($conn->connect_error) die('DB error: ' . $conn->connect_error);

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($month < 1) $month = 1;
if ($month > 12) $month = 12;

$success = isset($_GET['success']) ? (int)$_GET['success'] : 0;
$employeeAdded = isset($_GET['employee_added']) ? (int)$_GET['employee_added'] : 0;

$days = cal_days_in_month(CAL_GREGORIAN, $month, $year);
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

$prevYear = $year; $prevMonth = $month - 1;
if ($prevMonth === 0) { $prevMonth = 12; $prevYear--; }
$nextYear = $year; $nextMonth = $month + 1;
if ($nextMonth === 13) { $nextMonth = 1; $nextYear++; }
$todayYear = (int)date('Y');
$todayMonth = (int)date('m');

$yearOptions = [];
for ($y = $todayYear - 2; $y <= $todayYear + 2; $y++) $yearOptions[] = $y;

$employeesRes = $conn->query("SELECT id, name, last_name, weekly_hours FROM employees ORDER BY last_name, name");
if (!$employeesRes) die('SQL error (employees): ' . $conn->error);
$employees = $employeesRes->fetch_all(MYSQLI_ASSOC);

$grid = [];
$shiftsRes = $conn->query("SELECT employee_id, shift_date, shift_type FROM shifts WHERE shift_date LIKE '$year-$monthStr-%'");
if (!$shiftsRes) die('SQL error (shifts): ' . $conn->error);
while ($row = $shiftsRes->fetch_assoc()) {
    $d = (int)date('j', strtotime($row['shift_date']));
    $grid[(int)$row['employee_id']][$d] = $row['shift_type'];
}

function shiftCode(?string $type): string {
    if ($type === 'jutarnja') return 'J';
    if ($type === 'popodnevna') return 'P';
    if ($type === 'nocna') return 'N';
    return '-';
}
function shiftLabel(?string $type): string {
    if ($type === 'jutarnja') return 'Jutarnja';
    if ($type === 'popodnevna') return 'Popodnevna';
    if ($type === 'nocna') return 'Noćna';
    return 'Slobodno';
}
?>
<!doctype html>
<html lang="hr">
<head>
  <meta charset="utf-8">
  <title>Raspored smjena</title>
  <style>
    *{box-sizing:border-box}
    body{margin:0;font-family:Arial,sans-serif;background:#f4f6f8;padding:20px}
    .wrap{max-width:1250px;margin:0 auto;background:#fff;padding:16px;border-radius:10px;box-shadow:0 10px 25px rgba(0,0,0,.10)}
    .topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px}
    .title{margin:0;font-size:20px}
    .controls{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
    a.btn,button.btn{text-decoration:none;padding:10px 12px;border-radius:8px;border:1px solid #d7d7d7;background:#fafafa;color:#111;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:8px}
    a.btn:hover,button.btn:hover{background:#f0f0f0}
    form.month-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    select{padding:10px 10px;border-radius:8px;border:1px solid #d7d7d7;background:#fff;font-weight:700}

    .scroll-x{overflow-x:auto;border:1px solid #e6e6e6;border-radius:10px}
    table.calendar{border-collapse:collapse;width:max-content;min-width:100%;font-size:13px}
    .calendar th,.calendar td{border:1px solid #ddd;padding:6px;text-align:center;white-space:nowrap}
    .calendar th{background:#fafafa;font-weight:800;position:sticky;top:0;z-index:3}
    .calendar th.emp,.calendar td.emp{position:sticky;left:0;background:#fff;z-index:4;text-align:left;min-width:260px;font-weight:800}
    .emp small{display:block;font-weight:600;color:#555;margin-top:4px}

    .jutarnja{background:#d4f1f9}
    .popodnevna{background:#ffe8b3}
    .nocna{background:#d6c7ff}
    .free{background:#f4f4f4;color:#888}

    .legend{margin-top:10px;font-size:14px}
    .badge{display:inline-block;padding:4px 8px;border-radius:8px;border:1px solid #ddd;margin-right:8px;margin-bottom:6px}

    td[data-tip]{position:relative}
    td[data-tip]:hover::after{content:attr(data-tip);position:absolute;left:50%;transform:translateX(-50%);bottom:110%;background:rgba(0,0,0,.85);color:#fff;padding:6px 8px;border-radius:8px;font-size:12px;white-space:nowrap;z-index:999;pointer-events:none}
    td[data-tip]:hover::before{content:"";position:absolute;left:50%;transform:translateX(-50%);bottom:102%;border:6px solid transparent;border-top-color:rgba(0,0,0,.85);z-index:999;pointer-events:none}

    .alert{padding:10px 12px;border-radius:8px;margin:0 0 12px;font-weight:800}
    .ok{background:#e9f7ef;border:1px solid #b7e2c3}

    /* ✅ inline edit */
    td.editable { cursor: pointer; }
    td.editable select {
      width: 100%;
      padding: 4px;
      border-radius: 6px;
    }
  </style>
</head>
<body>

<div class="wrap">

  <?php if ($success === 1): ?>
    <div class="alert ok">
      ✅ Plan je generisan za <?= htmlspecialchars($monthStr.'/'.$year) ?><?= $employeeAdded ? ' (uključen i novi uposlenik)' : '' ?>.
    </div>
  <?php endif; ?>

  <div class="topbar">
    <h2 class="title">Raspored za <?= htmlspecialchars($monthStr.'/'.$year) ?></h2>

    <div class="controls">
      <a class="btn" href="<?= BASE_URL ?>/views/schedule.php?year=<?= $prevYear ?>&month=<?= $prevMonth ?>">« Prethodni</a>
      <a class="btn" href="<?= BASE_URL ?>/views/schedule.php?year=<?= $nextYear ?>&month=<?= $nextMonth ?>">Sljedeći »</a>
      <a class="btn" href="<?= BASE_URL ?>/views/schedule.php?year=<?= $todayYear ?>&month=<?= $todayMonth ?>">📅 Danas</a>

      <form class="month-form" method="get" action="<?= BASE_URL ?>/views/schedule.php">
        <select name="month">
          <?php for ($m=1; $m<=12; $m++):
            $mStr = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
          ?>
            <option value="<?= $m ?>" <?= ($m === $month ? 'selected' : '') ?>><?= $mStr ?></option>
          <?php endfor; ?>
        </select>

        <select name="year">
          <?php foreach ($yearOptions as $y): ?>
            <option value="<?= $y ?>" <?= ($y === $year ? 'selected' : '') ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>

        <button class="btn" type="submit">Prikaži</button>
      </form>

      <form method="post" action="<?= BASE_URL ?>/generate_schedule.php" style="display:inline;">
        <input type="hidden" name="year" value="<?= (int)$year ?>">
        <input type="hidden" name="month" value="<?= (int)$month ?>">
        <button class="btn" type="submit">⚙️ Generiši ovaj mjesec</button>
      </form>

      <a class="btn" href="<?= BASE_URL ?>/index.php?year=<?= (int)$year ?>&month=<?= (int)$month ?>">➕ Dodaj uposlenika</a>
    </div>
  </div>

  <div class="scroll-x">
    <table class="calendar">
      <tr>
        <th class="emp">Zaposleni</th>
        <?php for ($d=1; $d<=$days; $d++): ?>
          <th><?= $d ?></th>
        <?php endfor; ?>
      </tr>

      <?php foreach ($employees as $e): ?>
        <?php
          $weekly = (float)$e['weekly_hours'];
          $norm = monthlyNormHours($weekly, $year, $month);
          $requiredWorkDays = (int)ceil($norm / 8.0);
          if ($requiredWorkDays > $days) $requiredWorkDays = $days;
          $offDays = $days - $requiredWorkDays;
          if ($offDays < 0) $offDays = 0;
        ?>
        <tr>
          <td class="emp">
            <?= htmlspecialchars($e['last_name'].' '.$e['name']) ?>
            <small><?= $weekly ?>h/w • norma <?= round($norm, 1) ?>h • slobodno ~ <?= (int)$offDays ?> dana • max 7 dana rada zaredom</small>
          </td>

          <?php for ($d=1; $d<=$days; $d++):
            $type = $grid[$e['id']][$d] ?? null;
            $cls = $type ?? 'free';
            $txt = shiftCode($type);

            $dateIso = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $tip = shiftLabel($type) . ' • ' . $dateIso . ' (klik za promjenu)';
          ?>
            <td
              class="<?= $cls ?> editable"
              data-employee="<?= (int)$e['id'] ?>"
              data-date="<?= htmlspecialchars($dateIso) ?>"
              data-value="<?= htmlspecialchars($type ?? '') ?>"
              data-tip="<?= htmlspecialchars($tip) ?>"
            ><?= $txt ?></td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="legend">
    <span class="badge jutarnja">J = jutarnja</span>
    <span class="badge popodnevna">P = popodnevna</span>
    <span class="badge nocna">N = noćna</span>
    <span class="badge free">- = slobodno</span>
    <span class="badge">Klik na ćeliju = ručna promjena</span>
  </div>

</div>

<script>
(function () {
  const BASE_URL = "<?= BASE_URL ?>";

  function code(v){
    if(v === "jutarnja") return "J";
    if(v === "popodnevna") return "P";
    if(v === "nocna") return "N";
    return "-";
  }
  function apply(td, v){
    td.classList.remove("jutarnja","popodnevna","nocna","free");
    td.classList.add(v ? v : "free");
    td.textContent = code(v);
    td.dataset.value = v || "";
  }

  function makeSelect(current){
    const sel = document.createElement("select");
    const opts = [
      {v:"", t:"Slobodno (-)"},
      {v:"jutarnja", t:"Jutarnja (J)"},
      {v:"popodnevna", t:"Popodnevna (P)"},
      {v:"nocna", t:"Noćna (N)"}
    ];
    for (const o of opts){
      const op = document.createElement("option");
      op.value = o.v;
      op.textContent = o.t;
      if(o.v === (current || "")) op.selected = true;
      sel.appendChild(op);
    }
    return sel;
  }

  async function save(employeeId, dateIso, shiftType){
    const body = new URLSearchParams();
    body.set("employee_id", employeeId);
    body.set("shift_date", dateIso);
    body.set("shift_type", shiftType);

    const res = await fetch(`${BASE_URL}/update_shift.php`, {
      method: "POST",
      headers: {"Content-Type":"application/x-www-form-urlencoded"},
      body: body.toString()
    });

    const data = await res.json();
    if(!data.ok) throw new Error(data.error || "Save failed");
    return data.shift_type;
  }

  document.addEventListener("click", (e) => {
    const td = e.target.closest("td.editable");
    if(!td) return;
    if(td.querySelector("select")) return;

    const employeeId = td.dataset.employee;
    const dateIso = td.dataset.date;
    const originalValue = td.dataset.value || "";

    const sel = makeSelect(originalValue);
    td.textContent = "";
    td.appendChild(sel);
    sel.focus();

    const cancel = () => { td.innerHTML = ""; apply(td, originalValue); };

    sel.addEventListener("keydown", (ev) => {
      if(ev.key === "Escape") cancel();
      if(ev.key === "Enter") sel.dispatchEvent(new Event("change"));
    });

    sel.addEventListener("blur", cancel);

    sel.addEventListener("change", async () => {
      const newVal = sel.value;
      td.innerHTML = "";
      try {
        const saved = await save(employeeId, dateIso, newVal);
        apply(td, saved);
      } catch(err){
        apply(td, originalValue);
        alert("Greška pri snimanju: " + err.message);
      }
    });
  });
})();
</script>

</body>
</html>
