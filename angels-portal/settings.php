<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/layout.php';
$localFile = DATA_DIR . '/local.json';
$save = function (array $new) use ($localFile) {
  $cur = is_file($localFile) ? (json_decode((string) file_get_contents($localFile), true) ?: []) : [];
  $cur = array_merge($cur, $new);
  return file_put_contents(
    $localFile,
    json_encode($cur, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    LOCK_EX,
  ) !== false;
};
$clip = function ($v, $n) {
  return mb_substr(trim(strip_tags((string) $v)), 0, $n);
};
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $a = (string) ($_POST['action'] ?? '');
  $msg = ['err', 'Hindi na-save.'];
  if ($a === 'company') {
    $ok = $save([
      'company_name' => $clip($_POST['company_name'] ?? '', 80) ?: "Angel's Glass & Aluminum Services",
      'site_title' => $clip($_POST['site_title'] ?? '', 80) ?: "Angel's Attendance Portal",
    ]);
    $msg = [
      $ok ? 'ok' : 'err',
      $ok ? 'Na-save ang Company Information.' : 'Hindi na-save. I-check ang permission ng config folder.',
    ];
  } elseif ($a === 'schedule') {
    $ok = $save([
      'work_days' => $clip($_POST['work_days'] ?? '', 40),
      'work_hours' => $clip($_POST['work_hours'] ?? '', 40),
      'rest_day' => $clip($_POST['rest_day'] ?? '', 40),
    ]);
    $msg = [$ok ? 'ok' : 'err', $ok ? 'Na-save ang Work Schedule.' : 'Hindi na-save.'];
  } elseif ($a === 'password') {
    $cur = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $cf = (string) ($_POST['confirm'] ?? '');
    if (!password_verify($cur, (string) $config['admin_password_hash'])) {
      $msg = ['err', 'Mali ang kasalukuyang password.'];
    } elseif (strlen($new) < 8) {
      $msg = ['err', 'Dapat at least 8 characters ang bagong password.'];
    } elseif ($new !== $cf) {
      $msg = ['err', 'Hindi magkapareho ang bagong password at confirmation.'];
    } else {
      $ok = $save(['admin_password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
      if ($ok) {
        session_regenerate_id(true);
      }
      $msg = [$ok ? 'ok' : 'err', $ok ? 'Napalitan na ang password.' : 'Hindi na-save.'];
    }
  } elseif ($a === 'test') {
    try {
      $d = attendance_data($config, true);
      $msg = ['ok', 'Connected: ' . count($d['employees']) . ' employees, week ' . $d['week']];
    } catch (Throwable $e) {
      error_log($e->getMessage());
      $msg = ['err', 'Connection failed. Check sharing at API access.'];
    }
  }
  $_SESSION['flash'] = $msg;
  header('Location: settings.php');
  exit();
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$ok = false;
try {
  attendance_data($config);
  $ok = true;
} catch (Throwable $e) {
  error_log($e->getMessage());
}
$id = (string) ($config['spreadsheet_id'] ?? '');
$mask = strlen($id) > 10 ? substr($id, 0, 5) . str_repeat('•', 11) . substr($id, -4) : 'Not set';
$cn = $config['company_name'] ?? "Angel's Glass & Aluminum Services";
$st = $config['site_title'] ?? "Angel's Attendance Portal";
$wd = $config['work_days'] ?? 'Monday – Saturday';
$wh = $config['work_hours'] ?? '8:00 AM – 5:00 PM';
$rd = $config['rest_day'] ?? 'Sunday';
$tok = h(csrf());
$i = [
  'edit' =>
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h4L19 9l-4-4L4 16z"/></svg>',
  'sheet' =>
    '<svg viewBox="0 0 24 24" width="26" height="26"><rect x="4" y="2" width="16" height="20" rx="2" fill="#1fa55a"/><path d="M8 9h8v9H8zM8 13h8M12 9v9" stroke="#fff" stroke-width="1.4" fill="none"/></svg>',
  'clock' =>
    '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="#2f7fd6" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  'shield' =>
    '<svg viewBox="0 0 24 24" width="26" height="26"><path d="M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z" fill="#7b61c9"/></svg>',
  'lock' =>
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg>',
  'sync' =>
    '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 11a8 8 0 00-14-4L4 9M4 4v5h5M4 13a8 8 0 0014 4l2-2M20 20v-5h-5"/></svg>',
];
page_start('Settings', 'settings.php');
?>
<header><div><span class="eyebrow">SETTINGS</span><h1>System Settings</h1><p>Manage company information, Google Sheets connection, and system preferences.</p></div></header>
<?php if ($flash): ?><div class="<?= $flash[0] === 'ok' ? 'okmsg' : 'error' ?>"><?= h(
  $flash[1],
) ?></div><?php endif; ?>

<section class="panel set"><div class="set-row"><img class="co-logo" src="assets/logo.png" alt=""><div class="grow"><h2>Company Information</h2><p><?= h(
  $cn,
) ?></p><div class="kv"><span>Company Name</span><b><?= h($cn) ?></b><span>Website Title</span><b><?= h(
  $st,
) ?></b></div></div><button type="button" class="soft" data-t="f-company"><?= $i[
  'edit'
] ?> Edit</button></div>
<form id="f-company" method="post" class="ef" hidden><input type="hidden" name="csrf" value="<?= $tok ?>"><input type="hidden" name="action" value="company"><label>Company Name<input name="company_name" value="<?= h(
  $cn,
) ?>" maxlength="80" required></label><label>Website Title<input name="site_title" value="<?= h(
  $st,
) ?>" maxlength="80" required></label><button>Save</button></form></section>

<section class="panel set"><div class="set-row"><span class="ib g"><?= $i[
  'sheet'
] ?></span><div class="grow"><h2>Google Sheets Connection</h2><p><?= $ok
  ? 'Connected and reading data from your existing Google Sheet.'
  : 'Not connected. Check sharing and API access.' ?></p></div><span class="pill <?= $ok
  ? ''
  : 'bad' ?>">● <?= $ok ? 'Connected' : 'Not connected' ?></span></div>
<div class="set-cols"><div><small>Spreadsheet ID</small><div class="chip mono"><span><?= h(
  $mask,
) ?></span></div></div><div><small>Tracker Tab</small><div class="chip mono">ATTENDANCE TRACKER</div></div><div><small>Access Type</small><div class="chip">Read-only</div></div><form method="post"><input type="hidden" name="csrf" value="<?= $tok ?>"><input type="hidden" name="action" value="test"><button class="soft"><?= $i[
  'sync'
] ?> Test Connection</button></form></div></section>

<section class="panel set"><div class="set-row"><span class="ib b"><?= $i[
  'clock'
] ?></span><div class="grow"><h2>Work Schedule</h2><p>Set the default work schedule for attendance display.</p></div><button type="button" class="soft" data-t="f-sched"><?= $i[
  'edit'
] ?> Edit</button></div>
<div class="set-cols three"><div><small>Work Days</small><b><?= h(
  $wd,
) ?></b></div><div><small>Work Hours</small><b><?= h($wh) ?></b></div><div><small>Rest Day</small><b><?= h(
  $rd,
) ?></b></div></div>
<form id="f-sched" method="post" class="ef" hidden><input type="hidden" name="csrf" value="<?= $tok ?>"><input type="hidden" name="action" value="schedule"><label>Work Days<input name="work_days" value="<?= h(
  $wd,
) ?>" maxlength="40"></label><label>Work Hours<input name="work_hours" value="<?= h(
  $wh,
) ?>" maxlength="40"></label><label>Rest Day<input name="rest_day" value="<?= h(
  $rd,
) ?>" maxlength="40"></label><button>Save</button></form>
<p class="note" style="padding:0 20px 14px">Display lang ito. Hindi nito binabago ang computation sa Google Sheet.</p></section>

<section class="panel set"><div class="set-row"><span class="ib p"><?= $i[
  'shield'
] ?></span><div class="grow"><h2>Admin &amp; Security</h2><p>Manage your account and security settings.</p></div><button type="button" class="soft" data-t="f-pass"><?= $i[
  'lock'
] ?> Change Password</button></div>
<div class="set-cols three"><div><small>Admin Username</small><b><?= h(
  $config['admin_username'] ?? 'admin',
) ?></b></div><div><small>Session Timeout</small><b>Ends on log out</b></div></div>
<form id="f-pass" method="post" class="ef" hidden autocomplete="off"><input type="hidden" name="csrf" value="<?= $tok ?>"><input type="hidden" name="action" value="password"><label>Current Password<span class="pw-wrap"><input type="password" name="current" required autocomplete="current-password"><button type="button" class="pw-eye" aria-label="Show password"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label><label>New Password (min 8)<span class="pw-wrap"><input type="password" name="new" minlength="8" required autocomplete="new-password"><button type="button" class="pw-eye" aria-label="Show password"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label><label>Confirm New Password<span class="pw-wrap"><input type="password" name="confirm" minlength="8" required autocomplete="new-password"><button type="button" class="pw-eye" aria-label="Show password"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label><button>Update Password</button></form></section>
<p class="note">Salary rates, OT multipliers at payroll rules ay nasa Google Sheet at read-only sa website para hindi masira ang payroll computations.</p>
<script>document.querySelectorAll('[data-t]').forEach(function(b){b.onclick=function(){var f=document.getElementById(b.dataset.t);f.hidden=!f.hidden;};});document.querySelectorAll('.pw-eye').forEach(function(b){b.onclick=function(){var p=b.parentNode.querySelector('input');p.type=p.type==='password'?'text':'password';};});</script>
<?php page_end();
