<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/layout.php';
$data = ['week' => '—', 'employees' => []];
$error = '';
try {
  $data = attendance_data($config);
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load Google Sheets data. Check setup, sharing, API access, and PHP error logs.';
}
$employees = $data['employees'];
$names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
function cat($s)
{
  $s = strtoupper(trim($s));
  if (in_array($s, ['PRESENT', 'HOLIDAY', 'RDOT'], true)) {
    return 'present';
  }
  if ($s === 'ABSENT') {
    return 'absent';
  }
  if (in_array($s, ['LATE', 'HALF DAY'], true)) {
    return 'late';
  }
  return 'neutral';
}
$start = DateTime::createFromFormat('m/d/Y', trim((string) $data['week'])) ?: null;
$dates = [];
for ($i = 0; $i < 7; $i++) {
  if ($start) {
    $d = clone $start;
    $d->modify("+$i day");
    $dates[$i] = $d->format('M d');
  } else {
    $dates[$i] = '';
  }
}
page_start('Weekly Tracker', 'tracker.php');
?>
<header><div><span class="eyebrow">EMPLOYEE MANAGEMENT</span><h1>Weekly Attendance Tracker</h1><p>Week of <?= h(
  $start ? $start->format('F d') . ' – ' . (clone $start)->modify('+6 day')->format('F d, Y') : $data['week'],
) ?> <span class="pill blue">Live data from Google Sheets</span></p></div><div class="actions"><a class="refresh light" href="tracker.php">↻ Refresh</a></div></header>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<section class="panel" id="tracker"><div class="panel-head"><div><h2>Weekly Attendance Tracker</h2><p>View only — changes must be made in Google Sheets</p></div></div><div class="table-scroll"><table><thead><tr><th>#</th><th class="emp">Employee</th><th>Time In</th><th>Time Out</th><?php for (
  $i = 0;
  $i < 7;
  $i++
): ?><th><?= $names[$i] ?><br><span class="dt"><?= h(
  $dates[$i],
) ?></span></th><?php endfor; ?><th>Work hrs</th><th>OT hrs</th><th>Salary advance</th><th>Total pay</th></tr></thead><tbody><?php
foreach ($employees as $n => $e): ?><tr><td><?= $n + 1 ?></td><td class="employee"><?= h(
  $e['name'],
) ?></td><td><?= h($e['in'] ?: '-') ?></td><td><?= h($e['out'] ?: '-') ?></td><?php foreach ($names as $dn):

  $day = $e['days'][$dn];
  $s = strtoupper(trim($day['status']));
  ?><td><span class="status <?= cat($s) ?>"><?= h($s ?: '—') ?></span><?php if (
  $day['hours'] !== ''
): ?><div class="hours"><?= h($day['hours']) ?> hrs</div><?php endif; ?></td><?php
endforeach; ?><td><?= h($e['work']) ?></td><td><?= h($e['ot']) ?></td><td><?= h(
  $e['advance'] ?: '-',
) ?></td><td class="pay"><?= h($e['pay']) ?></td></tr><?php endforeach;
if (!$employees): ?><tr><td colspan="15">No employee records to display.</td></tr><?php endif;
?></tbody><?php if ($employees):

  $num = function ($v) {
    return (float) preg_replace('/[^0-9.\-]/', '', (string) $v);
  };
  $tw = $to = $tpay = $tadv = 0;
  foreach ($employees as $e) {
    $tw += $num($e['work']);
    $to += $num($e['ot']);
    $tpay += $num($e['pay']);
    $tadv += $num($e['advance']);
  }
  ?><tfoot><tr class="total"><td colspan="11">TOTAL</td><td><?= number_format(
  $tw,
  2,
) ?></td><td><?= number_format($to, 2) ?></td><td><?= $tadv
  ? '₱' . number_format($tadv, 2)
  : '-' ?></td><td class="pay">₱<?= number_format($tpay, 2) ?></td></tr></tfoot><?php
endif; ?></table></div></section>
<?php page_end();
