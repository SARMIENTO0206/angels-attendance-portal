<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/attendance.php';
$data = ['week' => '—', 'employees' => []];
$error = '';
try {
  $data = attendance_data($config, isset($_GET['refresh']));
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load Google Sheets data. Check setup, sharing, API access, and PHP error logs.';
}
$employees = $data['employees'];
$tot = count($employees);
$hours = 0;
$ot = 0;
foreach ($employees as $e) {
  $hours += (float) str_replace(',', '', $e['work']);
  $ot += (float) str_replace(',', '', $e['ot']);
}
$start = attendance_week_start((string) $data['week']);
$names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
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
$perDay = [];
$sum = ['present' => 0, 'absent' => 0, 'half' => 0, 'off' => 0];
for ($i = 0; $i < 7; $i++) {
  $perDay[$i] = ['present' => 0, 'absent' => 0, 'half' => 0, 'off' => 0];
  foreach ($employees as $e) {
    $s = $e['days'][$names[$i]]['status'] ?? '';
    $c = attendance_status_category((string) $s);
    $perDay[$i][$c]++;
    if (trim($s) !== '') {
      $sum[$c]++;
    }
  }
}
$daily = attendance_dashboard_summary($employees, $names, $start, new DateTimeImmutable('today'));
$tp = $daily['counts'];
$dailyLabel = $daily['date'] ? ($daily['is_today'] ? 'Today' : $daily['date']->format('M j')) : '';
$dailyNote = $daily['has_data']
  ? 'Out of ' . $tot . ' employees'
  : ($daily['date']
    ? 'No attendance data recorded for ' . $daily['date']->format('M j')
    : ($start
      ? 'No attendance data recorded for this week'
      : 'Tracker week date unavailable'));
$shifts = array_sum($sum);
$pct = function ($n) use ($shifts) {
  return $shifts ? round(($n / $shifts) * 100, 1) : 0;
};
$a = 0;
$cg = [];
$acc = 0;
foreach (
  ['present' => '#2fb36d', 'absent' => '#ef5b61', 'half' => '#9b7cf0', 'off' => '#b0b8c6']
  as $k => $col
) {
  $p = $shifts ? ($sum[$k] / $shifts) * 100 : 0;
  $cg[] = "$col {$acc}% " . ($acc + $p) . '%';
  $acc += $p;
}
$donut = $shifts ? 'conic-gradient(' . implode(',', $cg) . ')' : '#e6ecf4';
require __DIR__ . '/lib/layout.php';
page_start('Attendance', 'index.php');
?><header><div><span class="eyebrow">EMPLOYEE MANAGEMENT</span><h1>Attendance Overview</h1><p>Week of <?= h(
  $start ? $start->format('F d') . ' – ' . (clone $start)->modify('+6 day')->format('F d, Y') : $data['week'],
) ?> <span class="pill blue">Live data from Google Sheets</span></p></div><div class="actions"><span class="updated">Last updated: <?= h(
   date('M j, Y g:i A'),
 ) ?></span><a class="refresh light" href="index.php?refresh=1">↻ Refresh</a><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= h(
  csrf(),
) ?>"><button class="logout">Log out</button></form></div></header><?php if (
  $error
): ?><div class="error"><?= h(
  $error,
) ?></div><?php endif; ?><section class="stats six"><article><div class="stat-label">Total Employees</div><div class="stat-number"><?= $tot ?></div><small>Registered in tracker</small></article><article><div class="stat-label">Present<?= $dailyLabel ? ' ' . h(
  $dailyLabel,
) : '' ?></div><div class="stat-number c-green"><?= $tp[
  'present'
] ?></div><small><?= h($dailyNote) ?></small></article><article><div class="stat-label">Absent<?= $dailyLabel ? ' ' . h(
  $dailyLabel,
) : '' ?></div><div class="stat-number c-red"><?= $tp[
   'absent'
 ] ?></div><small><?= h($dailyNote) ?></small></article><article><div class="stat-label">Late / Half Day<?= $dailyLabel
  ? ' (' . h($dailyLabel) . ')'
  : '' ?></div><div class="stat-number c-purple"><?= $tp[
   'half'
 ] ?></div><small><?= h($dailyNote) ?></small></article><article><div class="stat-label">Regular Hours</div><div class="stat-number"><?= number_format(
   $hours,
   2,
 ) ?></div><small>Current payroll week</small></article><article><div class="stat-label">Overtime Hours</div><div class="stat-number c-orange"><?= number_format(
  $ot,
  2,
) ?></div><small>Current payroll week</small></article></section><section class="charts"><div class="panel pad"><h2>Attendance Summary (Mon – Sun)</h2><div class="bars"><?php for (
  $i = 0;
  $i < 7;
  $i++
):
  $max = max(1, $tot); ?><div class="bar"><div class="stack"><?php foreach (
  ['off' => '#b0b8c6', 'half' => '#9b7cf0', 'absent' => '#ef5b61', 'present' => '#2fb36d']
  as $k => $col
):
  if ($perDay[$i][$k]): ?><i style="height:<?= ($perDay[$i][$k] / $max) *
  100 ?>%;background:<?= $col ?>" title="<?= h(ucfirst($k) . ': ' . $perDay[$i][$k]) ?>"></i><?php endif;
endforeach; ?></div><span><?= $names[$i] ?></span><small><?= h($dates[$i]) ?></small></div><?php
endfor; ?></div><div class="legend"><span><i style="background:#2fb36d"></i>Present</span><span><i style="background:#ef5b61"></i>Absent</span><span><i style="background:#9b7cf0"></i>Half Day</span><span><i style="background:#b0b8c6"></i>Day Off</span></div></div><div class="panel pad"><h2>Attendance Distribution (This Week)</h2><div class="dist"><div class="donut" style="background:<?= $donut ?>"><div><b><?= $shifts ?></b><small>Total Shifts</small></div></div><ul><?php foreach (
  [
    'present' => ['Present', '#2fb36d'],
    'absent' => ['Absent', '#ef5b61'],
    'half' => ['Half Day', '#9b7cf0'],
    'off' => ['Day Off', '#b0b8c6'],
  ]
  as $k => $m
): ?><li><i style="background:<?= $m[1] ?>"></i><span><?= $m[0] ?></span><b><?= $sum[$k] ?> (<?= $pct(
   $sum[$k],
 ) ?>%)</b></li><?php endforeach; ?></ul></div></div></section><?php page_end();
