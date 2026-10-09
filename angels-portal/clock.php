<?php require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/clock.php';
require __DIR__ . '/lib/sheets.php';
$isAdmin = !empty($_SESSION['logged_in']);

function emp_photo($n)
{
  foreach (['jpg', 'png', 'webp'] as $x) {
    $f = __DIR__ . '/uploads/employees/' . emp_key($n) . '.' . $x;
    if (is_file($f)) {
      return 'uploads/employees/' . emp_key($n) . '.' . $x . '?v=' . filemtime($f);
    }
  }
  return null;
}
// Status ng employee ngayong araw: out, in, o break
function clock_state(PDO $db, string $key): array
{
  $start = strtotime('today');
  $st = $db->prepare('SELECT type, ts FROM clock_events WHERE emp_key = ? AND ts >= ? ORDER BY id');
  $st->execute([$key, $start]);
  $state = 'out';
  $since = null;
  $lastOut = null;
  foreach ($st as $r) {
    if ($r['type'] === 'in') {
      $state = 'in';
      $since = (int) $r['ts'];
    } elseif ($r['type'] === 'break_start') {
      $state = 'break';
    } elseif ($r['type'] === 'break_end') {
      $state = 'in';
    } elseif ($r['type'] === 'out') {
      $state = 'out';
      $lastOut = (int) $r['ts'];
      $since = null;
    }
  }
  return ['state' => $state, 'since' => $since, 'last_out' => $lastOut];
}
function json_out(array $d, int $code = 200): void
{
  global $db, $txOpen;
  if (!empty($txOpen)) {
    $txOpen = false;
    $db->exec('COMMIT');
  }
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode($d);
  exit();
}

if (isset($_GET['selfie'])) {
  require_login();
  $f = DATA_DIR . '/selfies/' . (int) $_GET['selfie'] . '.jpg';
  if (!is_file($f)) {
    http_response_code(404);
    exit();
  }
  header('Content-Type: image/jpeg');
  header('Cache-Control: private, max-age=86400');
  readfile($f);
  exit();
}

$labels = ['in' => 'Clock in', 'break_start' => 'Start break', 'break_end' => 'End break', 'out' => 'Clock out'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals(csrf(), (string) ($_POST['csrf'] ?? ''))) {
    json_out(['ok' => false, 'msg' => 'Invalid request token.'], 403);
  }
  $type = (string) ($_POST['type'] ?? '');
  $key = (string) ($_POST['emp'] ?? '');
  if ($type === 'set_pin') {
    if (!$isAdmin) {
      json_out(['ok' => false, 'msg' => 'Admin login required.'], 403);
    }
    $pin = (string) ($_POST['pin'] ?? '');
    if (!preg_match('/^\d{4,6}$/', $pin) || !preg_match('/^[a-f0-9]{16}$/', $key)) {
      json_out(['ok' => false, 'msg' => 'PIN must be 4 to 6 digits.'], 400);
    }
    $st = clock_db()->prepare(
      'INSERT INTO clock_pins (emp_key, pin_hash, fails, locked_until) VALUES (?, ?, 0, 0)
       ON CONFLICT(emp_key) DO UPDATE SET pin_hash = excluded.pin_hash, fails = 0, locked_until = 0'
    );
    $st->execute([$key, password_hash($pin, PASSWORD_DEFAULT)]);
    json_out(['ok' => true]);
  }
  if (!isset($labels[$type])) {
    json_out(['ok' => false, 'msg' => 'Invalid action.'], 400);
  }
  try {
    $name = null;
    foreach (attendance_data($config)['employees'] as $e) {
      if (emp_key($e['name']) === $key) {
        $name = $e['name'];
      }
    }
    if ($name === null) {
      json_out(['ok' => false, 'msg' => 'Employee not found.'], 404);
    }
    $img = null;
    if (!empty($_POST['selfie'])) {
      if (!preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#', (string) $_POST['selfie'], $m)) {
        json_out(['ok' => false, 'msg' => 'Invalid selfie.'], 400);
      }
      $img = base64_decode($m[1], true);
      if ($img === false || strlen($img) > 1024 * 1024 || @getimagesizefromstring($img) === false) {
        json_out(['ok' => false, 'msg' => 'Invalid selfie.'], 400);
      }
    }    // One write transaction per clock action so simultaneous requests are serialized safely.
    $db = clock_db();
    $db->exec('BEGIN IMMEDIATE');
    $txOpen = true;
    $ps = $db->prepare('SELECT pin_hash, fails, locked_until FROM clock_pins WHERE emp_key = ?');
    $ps->execute([$key]);
    $pr = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$pr) {
      json_out(['ok' => false, 'msg' => 'No PIN set yet. Ask the admin to set one.'], 403);
    }
    if ((int) $pr['locked_until'] > time()) {
      $mins = (int) ceil(((int) $pr['locked_until'] - time()) / 60);
      json_out(['ok' => false, 'msg' => "Too many wrong PIN attempts. Try again in $mins minute(s)."], 429);
    }
    if (!password_verify((string) ($_POST['pin'] ?? ''), $pr['pin_hash'])) {
      $fails = (int) $pr['fails'] + 1;
      $lock = $fails >= 5 ? time() + 300 : 0;
      $db->prepare('UPDATE clock_pins SET fails = ?, locked_until = ? WHERE emp_key = ?')->execute([
        $lock ? 0 : $fails,
        $lock,
        $key,
      ]);
      json_out(['ok' => false, 'msg' => 'Incorrect PIN.'], 403);
    }
    $db->prepare('UPDATE clock_pins SET fails = 0, locked_until = 0 WHERE emp_key = ?')->execute([$key]);
    $cur = clock_state($db, $key)['state'];
    $allowed = ['out' => ['in'], 'in' => ['break_start', 'out'], 'break' => ['break_end', 'out']];
    if (!in_array($type, $allowed[$cur], true)) {
      json_out(['ok' => false, 'msg' => 'This action is not allowed for the current status.'], 409);
    }

    $ins = $db->prepare('INSERT INTO clock_events (emp_key, name, type, ts, selfie) VALUES (?, ?, ?, ?, ?)');
    $ins->execute([$key, $name, $type, time(), $img !== null ? 1 : 0]);
    if ($img !== null) {
      file_put_contents(DATA_DIR . '/selfies/' . $db->lastInsertId() . '.jpg', $img);
    }
    json_out(['ok' => true]);
  } catch (Throwable $e) {
    error_log($e->getMessage());
    if (!empty($txOpen)) {
      $txOpen = false;
      $db->exec('ROLLBACK');
    }
    json_out(['ok' => false, 'msg' => 'Server error. Please try again.'], 500);
  }
}

if (isset($_GET['log'])) {
  header('Location: clock-admin.php');
  exit();
}

$emps = [];
$error = '';
try {
  $emps = attendance_data($config)['employees'];
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load Google Sheets data.';
}
$list = [];
try {
  $db = clock_db();
  foreach ($emps as $e) {
    $k = emp_key($e['name']);
    $list[] = ['key' => $k, 'name' => $e['name'], 'photo' => emp_photo($e['name'])] + clock_state($db, $k);
  }
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not open the clock database.';
}
usort($list, fn($a, $b) => strcasecmp($a['name'], $b['name']));
$sel = null;
foreach ($list as $e) {
  if ($e['key'] === (string) ($_GET['e'] ?? '')) {
    $sel = $e;
  }
}
$company = "Angel's Glass & Aluminum Services";
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Time Clock | <?= h($company) ?></title>
  <link rel="stylesheet" href="assets/clock.css">
</head>
<body class="clock-body">
<header class="clock-top">
  <?php if ($sel): ?>
    <a class="clock-icon" href="clock.php" aria-label="Back">&larr;</a>
  <?php elseif ($isAdmin): ?>
    <a class="clock-icon" href="index.php" aria-label="Back to modules" title="Back to modules">&larr;</a>
  <?php else: ?>
    <span class="clock-icon"></span>
  <?php endif; ?>
  <div class="clock-title"><?= h($company) ?></div>
  <?php if ($isAdmin): ?>
    <a class="clock-icon" href="clock-admin.php" aria-label="Clock Admin">&#9776;</a>
  <?php else: ?>
    <span class="clock-icon"></span>
  <?php endif; ?>
</header>
<?php if ($error): ?><p class="clock-error"><?= h($error) ?></p><?php endif; ?>

<?php if (!$sel): ?>
  <main class="clock-main">
    <input id="q" class="clock-search" type="search" placeholder="Search for an employee" autocomplete="off">
    <div class="clock-tabs" id="tabs">
      <button class="on" data-f="all">All</button>
      <button data-f="in">In</button>
      <button data-f="break">Break</button>
      <button data-f="out">Out</button>
    </div>
    <div class="clock-count"><span id="cnt"><?= count($list) ?></span> members</div>
    <ul class="clock-list" id="list">
      <?php foreach ($list as $e): ?>
        <li data-name="<?= h(strtolower($e['name'])) ?>" data-state="<?= h($e['state']) ?>">
          <a href="clock.php?e=<?= h($e['key']) ?>">
            <span class="clock-av s-<?= h($e['state']) ?>">
              <?php if ($e['photo']): ?><img src="<?= h($e['photo']) ?>" alt=""><?php else: ?><?= h(mb_strtoupper(mb_substr($e['name'], 0, 1))) ?><?php endif; ?>
            </span>
            <span class="clock-name"><?= h($e['name']) ?></span>
            <span class="clock-meta">
              <?php if ($e['state'] === 'in'): ?>In &bull; <?= h(date('g:i a', $e['since'])) ?>
              <?php elseif ($e['state'] === 'break'): ?>Break
              <?php elseif ($e['last_out']): ?>Out &bull; <?= h(date('g:i a', $e['last_out'])) ?><?php endif; ?>
            </span>
            <span class="clock-chev">&rsaquo;</span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </main>
  <script>
    const items = [...document.querySelectorAll('#list li')];
    let filter = 'all';
    function apply() {
      const q = document.getElementById('q').value.trim().toLowerCase();
      let n = 0;
      items.forEach((li) => {
        const show = (filter === 'all' || li.dataset.state === filter) && li.dataset.name.includes(q);
        li.hidden = !show;
        if (show) n++;
      });
      document.getElementById('cnt').textContent = n;
    }
    document.getElementById('q').addEventListener('input', apply);
    document.getElementById('tabs').addEventListener('click', (ev) => {
      if (ev.target.dataset.f) {
        filter = ev.target.dataset.f;
        document.querySelectorAll('#tabs button').forEach((b) => b.classList.toggle('on', b === ev.target));
        apply();
      }
    });
  </script>
<?php else: ?>
  <main class="clock-main clock-detail">
    <div class="clock-cam">
      <video id="cam" autoplay playsinline muted></video>
      <?php if ($sel['photo']): ?><img id="fallback" src="<?= h($sel['photo']) ?>" alt="" hidden><?php endif; ?>
    </div>
    <p class="clock-hint" id="hint">Say cheese!</p>
    <section class="clock-sheet">
      <h2><?= h($sel['name']) ?></h2>
      <div class="clock-pill">
        <?php if ($sel['state'] === 'out'): ?>
          <small><?= $sel['last_out'] ? 'LAST OUT' : 'NOT CLOCKED IN' ?></small>
          <b><?= $sel['last_out'] ? h(date('g:i a', $sel['last_out'])) . ' today' : '&mdash;' ?></b>
        <?php else: ?>
          <small><?= $sel['state'] === 'break' ? 'ON BREAK' : 'CLOCKED IN AT ' . h(strtoupper(date('g:i a', $sel['since']))) ?></small>
          <b id="timer" data-since="<?= (int) $sel['since'] ?>">0:00:00</b>
        <?php endif; ?>
      </div>
      <input id="pin" class="clock-pin" type="password" inputmode="numeric" maxlength="6" autocomplete="off" placeholder="Enter PIN">
      <div class="clock-actions">
        <?php if ($sel['state'] === 'out'): ?>
          <button class="cb green wide" data-t="in">&#9654; Clock in</button>
        <?php elseif ($sel['state'] === 'in'): ?>
          <button class="cb orange" data-t="break_start">&#9749; Start break</button>
          <button class="cb red" data-t="out">&#9632; Clock out</button>
        <?php else: ?>
          <button class="cb green" data-t="break_end">&#9654; End break</button>
          <button class="cb red" data-t="out">&#9632; Clock out</button>
        <?php endif; ?>
      </div>
      <p class="clock-msg" id="msg" role="status"></p>
    </section>
    <canvas id="snap" hidden></canvas>
  </main>
  <script>
    const video = document.getElementById('cam');
    const msg = document.getElementById('msg');
    let camOk = false;
    navigator.mediaDevices
      ?.getUserMedia({ video: { facingMode: 'user' }, audio: false })
      .then((s) => {
        video.srcObject = s;
        camOk = true;
      })
      .catch(() => {
        video.hidden = true;
        document.getElementById('hint').textContent = 'Camera not available. You can still clock without a selfie.';
        const fb = document.getElementById('fallback');
        if (fb) fb.hidden = false;
      });
    const timer = document.getElementById('timer');
    if (timer) {
      const since = Number(timer.dataset.since);
      const tick = () => {
        const s = Math.max(0, Math.floor(Date.now() / 1000) - since);
        timer.textContent =
          Math.floor(s / 3600) + ':' + String(Math.floor((s % 3600) / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
      };
      tick();
      setInterval(tick, 1000);
    }
    function snapshot() {
      if (!camOk || !video.videoWidth) return '';
      const c = document.getElementById('snap');
      const w = 480;
      c.width = w;
      c.height = Math.round((video.videoHeight / video.videoWidth) * w);
      c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
      return c.toDataURL('image/jpeg', 0.7);
    }
    document.querySelectorAll('.cb').forEach((btn) => {
      btn.addEventListener('click', async () => {
        const pin = document.getElementById('pin').value.trim();
        if (!/^\d{4,6}$/.test(pin)) {
          msg.className = 'clock-msg bad';
          msg.textContent = 'Enter your 4 to 6 digit PIN.';
          return;
        }
        const all = document.querySelectorAll('.cb');
        all.forEach((b) => (b.disabled = true));
        msg.className = 'clock-msg';
        msg.textContent = 'Saving...';
        const body = new URLSearchParams({
          csrf: <?= json_encode(csrf()) ?>,
          emp: <?= json_encode($sel['key']) ?>,
          type: btn.dataset.t,
          pin,
          selfie: snapshot(),
        });
        try {
          const r = await fetch('clock.php', { method: 'POST', body });
          const d = await r.json();
          if (d.ok) {
            location.href = 'clock.php';
            return;
          }
          msg.className = 'clock-msg bad';
          msg.textContent = d.msg || 'Could not save.';
        } catch (e) {
          msg.className = 'clock-msg bad';
          msg.textContent = 'No connection. Please try again.';
        }
        all.forEach((b) => (b.disabled = false));
      });
    });
  </script>
<?php endif; ?>
</body>
</html>
