<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/layout.php';
$data = ['week' => '—', 'employees' => []];
$error = '';
try {
  $data = attendance_data($config, isset($_GET['refresh']));
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load Google Sheets data.';
}
$emps = $data['employees'];
$names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$upDir = __DIR__ . '/uploads/employees';
function emp_key($n)
{
  return substr(sha1(strtolower(trim((string) $n))), 0, 16);
}
function emp_photo($n)
{
  foreach (['jpg', 'png', 'webp'] as $x) {
    if (is_file(__DIR__ . '/uploads/employees/' . emp_key($n) . '.' . $x)) {
      return 'uploads/employees/' .
        emp_key($n) .
        '.' .
        $x .
        '?v=' .
        filemtime(__DIR__ . '/uploads/employees/' . emp_key($n) . '.' . $x);
    }
  }
  return null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['e']) && isset($emps[(int) $_GET['e']])) {
  verify_csrf();
  $pe = (int) $_GET['e'];
  $k = emp_key($emps[$pe]['name']);
  $msg = ['err', 'Hindi na-save ang picture.'];
  if (($_POST['action'] ?? '') === 'photo_remove') {
    foreach (['jpg', 'png', 'webp'] as $x) {
      @unlink("$upDir/$k.$x");
    }
    $msg = ['ok', 'Natanggal na ang picture.'];
  } elseif (($_POST['action'] ?? '') === 'photo') {
    $f = $_FILES['photo'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
      $msg = ['err', 'Walang na-upload na file.'];
    } elseif ($f['size'] > 3 * 1024 * 1024) {
      $msg = ['err', 'Masyadong malaki. Max 3MB.'];
    } else {
      $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
      $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
      if (!isset($map[$mime]) || @getimagesize($f['tmp_name']) === false) {
        $msg = ['err', 'JPG, PNG, o WEBP lang ang pwede.'];
      } else {
        if (!is_dir($upDir)) {
          @mkdir($upDir, 0755, true);
        }
        foreach ($map as $x) {
          @unlink("$upDir/$k.$x");
        }
        $msg = move_uploaded_file($f['tmp_name'], "$upDir/$k." . $map[$mime])
          ? ['ok', 'Na-save na ang picture.']
          : ['err', 'Hindi na-save. I-check ang permission ng uploads folder.'];
      }
    }
  }
  $_SESSION['flash'] = $msg;
  header('Location: employees.php?e=' . $pe);
  exit();
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$sel = isset($_GET['e']) ? (int) $_GET['e'] : -1;
$q = trim((string) ($_GET['q'] ?? ''));
function ecat($s)
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
    $dates[$i] = $d->format('m/d/Y');
  } else {
    $dates[$i] = '';
  }
}
page_start('Employees', 'employees.php');
$ico = [
  'users' =>
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.6 3-6 7-6s7 2.4 7 6z"/><circle cx="17.5" cy="9" r="2.6"/><path d="M18 14c2.7.2 4.5 2 4.5 5h-4.2c0-2-.6-3.6-1.8-4.6z"/></svg>',
  'search' =>
    '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#2f5f9b" stroke-width="2.4" stroke-linecap="round"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/></svg>',
  'eye' =>
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="#fff" stroke-width="2.2"><path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3" fill="#fff"/></svg>',
];
if ($sel >= 0 && isset($emps[$sel])):

  $e = $emps[$sel];
  $present = 0;
  foreach ($names as $i => $n) {
    if ($i < 6 && ecat($e['days'][$n]['status']) === 'present') {
      $present++;
    }
  }
  ?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'ok' ? 'okmsg' : 'error' ?>"><?= h(
  $flash[1],
) ?></div><?php endif; ?><div class="crumb"><a href="employees.php">Employees</a> &gt; <?= h(
  $e['name'],
) ?></div>
<header><div><h1><?= h(
  $e['name'],
) ?></h1><p>Employee Profile</p></div><div class="actions"><a class="refresh light" href="employees.php">← Back to Employees</a></div></header>
<section class="panel pad profile"><div class="who"><div class="avwrap"><div class="avatar"><?php if (
  $ph = emp_photo($e['name'])
): ?><img src="<?= h(
  $ph,
) ?>" alt=""><?php else: ?><svg viewBox="0 0 24 24" width="64" height="64" fill="#2469b6"><circle cx="12" cy="8" r="4.2"/><path d="M3.5 21c0-4.7 3.8-7.5 8.5-7.5s8.5 2.8 8.5 7.5z"/></svg><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="phform"><input type="hidden" name="csrf" value="<?= h(
  csrf(),
) ?>"><input type="hidden" name="action" value="photo"><label class="phbtn">Change Photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" hidden onchange="this.form.submit()"></label></form><?php if (
  $ph
): ?><form method="post" class="phform"><input type="hidden" name="csrf" value="<?= h(
  csrf(),
) ?>"><input type="hidden" name="action" value="photo_remove"><button class="phrm">Remove</button></form><?php endif; ?></div><div><h2><?= h(
  $e['name'],
) ?></h2><span class="status late" style="background:#e1ecfa;color:#2469b6">EMP-<?= str_pad(
  (string) ($sel + 1),
  3,
  '0',
  STR_PAD_LEFT,
) ?></span><br><br><span class="status present" style="background:#1fa55a;color:#fff;padding:6px 14px;border-radius:14px">Active</span></div></div>
<div class="kpis"><div class="k b"><span>Total Work Hours</span><b><?= h(
  $e['work'] ?: '0',
) ?></b><small>This week</small></div><div class="k o"><span>OT Hours</span><b><?= h(
  $e['ot'] ?: '0',
) ?></b><small>This week</small></div><div class="k g"><span>Total Pay</span><b><?= h(
  $e['pay'] ?: '-',
) ?></b><small>This week</small></div><div class="k n"><span>Days Present</span><b><?= $present ?> / 6</b><small>Mon – Sat</small></div></div></section>
<section class="panel pad"><h2>Weekly Attendance</h2><p>Week beginning: <?= h(
  $start ? $start->format('F j, Y') : $data['week'],
) ?></p><div class="table-scroll"><table class="grid"><thead><tr><?php foreach (
  $names
  as $i => $n
): ?><th><?= strtoupper($n) ?><br><span class="dt"><?= h(
  $dates[$i],
) ?></span></th><?php endforeach; ?></tr></thead><tbody><tr><?php foreach ($names as $n):

  $d = $e['days'][$n];
  $s = strtoupper(trim($d['status']));
  ?><td><span class="status <?= ecat($s) ?>"><?= h($s ?: '—') ?></span><div class="hours big"><?= $d[
  'hours'
] !== ''
  ? h($d['hours']) . ' hrs'
  : '-' ?></div></td><?php
endforeach; ?></tr></tbody></table></div></section>
<section class="panel pad"><h2>Daily Time Records</h2><div class="table-scroll"><table><thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Work Hours</th></tr></thead><tbody><?php foreach (
  $names
  as $i => $n
):

  $d = $e['days'][$n];
  $s = strtoupper(trim($d['status']));
  ?><tr><td><?= h($dates[$i]) ?></td><td><?= $n ?></td><td><span class="status <?= ecat($s) ?>"><?= h(
  $s ?: '—',
) ?></span></td><td><?= $d['hours'] !== '' ? h($d['hours']) : '0.00' ?></td></tr><?php
endforeach; ?></tbody></table></div><p class="note">Time In / Time Out at daily OT ay hindi naka-track sa Google Sheet, kaya hindi ipinapakita.</p></section>
<?php
else:
   ?>
<header><div><span class="eyebrow">EMPLOYEE MANAGEMENT</span><h1>Employee Directory</h1><p>Manage and view employee information</p></div><div class="actions"><span class="pill blue cnt"><?= $ico[
  'users'
] ?> <?= count($emps) ?> Employees</span></div></header>
<?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
<section class="panel dirp"><form method="get" class="search sbox" onsubmit="return false"><?= $ico[
  'search'
] ?><input id="q" name="q" value="<?= h(
  $q,
) ?>" placeholder="Search employee name..." autocomplete="off"></form><div class="table-scroll"><table class="dir"><thead><tr><th>#</th><th>Employee Name</th><th>Status</th><th>Action</th></tr></thead><tbody id="rows"><?php
$n = 0;
foreach ($emps as $i => $e) {

  if ($q !== '' && stripos($e['name'], $q) === false) {
    continue;
  }
  $n++;
  ?><tr data-n="<?= h(strtolower($e['name'])) ?>"><td><?= $n ?></td><td class="employee"><?= h(
  $e['name'],
) ?></td><td><span class="status present act"><i></i>ACTIVE</span></td><td><a class="viewbtn" href="employees.php?e=<?= $i ?>"><?= $ico[
  'eye'
] ?> View</a></td></tr><?php
}
?></tbody></table><p class="note" id="none" style="padding:0 18px;<?= $n
  ? 'display:none'
  : '' ?>">No employees found.</p></div></section>
<script>document.getElementById('q').addEventListener('input',function(){var v=this.value.toLowerCase().trim(),c=0;document.querySelectorAll('#rows tr').forEach(function(r){var m=r.dataset.n.indexOf(v)>-1;r.style.display=m?'':'none';if(m){c++;r.cells[0].textContent=c;}});document.getElementById('none').style.display=c?'none':'';});</script>
<p class="note">Ang Active status ay default label lang ng portal, hindi galing sa Google Sheets.</p>
<?php
endif;
page_end();
