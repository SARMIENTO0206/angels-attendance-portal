<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/clock.php';
require __DIR__ . '/lib/layout.php';

$labels = ['in' => 'Clock in', 'break_start' => 'Start break', 'break_end' => 'End break', 'out' => 'Clock out'];
const CLOCK_PER_PAGE = 50;

function fmt_dur(int $s): string
{
  return intdiv($s, 3600) . 'h ' . str_pad((string) intdiv($s % 3600, 60), 2, '0', STR_PAD_LEFT) . 'm';
}

$rows = [];
$pinRows = [];
$summary = [];
$total = 0;
$error = '';
$employees = [];
try {
  $employees = attendance_data($config)['employees'];
} catch (Throwable $e) {
  error_log($e->getMessage());
}

$fEmp = preg_match('/^[a-f0-9]{16}$/', (string) ($_GET['emp'] ?? '')) ? $_GET['emp'] : '';
$validDate = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
$fFrom = $validDate($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-d', strtotime('-13 days'));
$fTo = $validDate($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $msg = ['err', 'Nothing done.'];
  try {
    $db = clock_db();
    $act = (string) ($_POST['action'] ?? '');
    $me = (string) ($_SESSION['username'] ?? 'admin');
    $when = strtotime((string) ($_POST['dt'] ?? ''));
    if ($act === 'delete') {
      $id = (int) ($_POST['id'] ?? 0);
      $db->prepare('DELETE FROM clock_events WHERE id = ?')->execute([$id]);
      clock_delete_selfie($id);
      $msg = ['ok', 'Record deleted.'];
    } elseif ($act === 'clear_all') {
      foreach ($db->query('SELECT id FROM clock_events')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        clock_delete_selfie((int) $id);
      }
      $db->exec('DELETE FROM clock_events');
      $msg = ['ok', 'All clock records and selfies deleted. PINs were kept.'];
    } elseif ($act === 'edit_time') {
      if ($when === false) {
        $msg = ['err', 'Invalid date and time.'];
      } else {
        $db->prepare('UPDATE clock_events SET ts = ?, edited_by = ?, note = ? WHERE id = ?')->execute([
          $when,
          $me,
          'Time edited',
          (int) ($_POST['id'] ?? 0),
        ]);
        $msg = ['ok', 'Time updated.'];
      }
    } elseif ($act === 'add') {
      $key = (string) ($_POST['emp'] ?? '');
      $type = (string) ($_POST['type'] ?? '');
      $name = null;
      foreach ($employees as $e) {
        if (emp_key($e['name']) === $key) {
          $name = $e['name'];
        }
      }
      if ($name === null || !isset($labels[$type]) || $when === false || $when > time() + 300) {
        $msg = ['err', 'Choose an employee, an action, and a valid date and time (not in the future).'];
      } else {
        $db->prepare('INSERT INTO clock_events (emp_key, name, type, ts, selfie, edited_by, note) VALUES (?, ?, ?, ?, 0, ?, ?)')->execute([
          $key,
          $name,
          $type,
          $when,
          $me,
          'Added manually',
        ]);
        $msg = ['ok', 'Record added.'];
      }
    } elseif ($act === 'settings') {
      clock_set($db, 'require_selfie', !empty($_POST['require_selfie']) ? '1' : '0');
      clock_set($db, 'retention_days', (string) max(0, min(3650, (int) ($_POST['retention_days'] ?? 0))));
      $msg = ['ok', 'Settings saved.'];
    } elseif ($act === 'purge') {
      $n = clock_purge_selfies($db, (int) clock_setting($db, 'retention_days', '0'));
      $msg = ['ok', "$n old selfie(s) removed."];
    }
  } catch (Throwable $e) {
    error_log($e->getMessage());
    $msg = ['err', 'Could not save. Please try again.'];
  }
  $_SESSION['flash'] = $msg;
  header('Location: clock-admin.php?' . http_build_query(['emp' => $fEmp, 'from' => $fFrom, 'to' => $fTo, 'page' => $page]));
  exit();
}
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

try {
  $db = clock_db();
  $retention = (int) clock_setting($db, 'retention_days', '0');
  clock_purge_selfies($db, $retention);
  $reqSelfie = clock_setting($db, 'require_selfie', '0') === '1';
  $hasPin = $db->query('SELECT emp_key FROM clock_pins')->fetchAll(PDO::FETCH_COLUMN);
  foreach ($employees as $e) {
    $k = emp_key($e['name']);
    $pinRows[] = ['key' => $k, 'name' => $e['name'], 'has' => in_array($k, $hasPin, true)];
  }
  $where = 'ts >= ? AND ts < ?';
  $args = [strtotime($fFrom), strtotime($fTo . ' +1 day')];
  if ($fEmp !== '') {
    $where .= ' AND emp_key = ?';
    $args[] = $fEmp;
  }
  $cnt = $db->prepare("SELECT COUNT(*) FROM clock_events WHERE $where");
  $cnt->execute($args);
  $total = (int) $cnt->fetchColumn();
  $pages = max(1, (int) ceil($total / CLOCK_PER_PAGE));
  $page = min($page, $pages);
  $q = $db->prepare("SELECT * FROM clock_events WHERE $where ORDER BY ts DESC, id DESC LIMIT " . CLOCK_PER_PAGE . ' OFFSET ' . ($page - 1) * CLOCK_PER_PAGE);
  $q->execute($args);
  $rows = $q->fetchAll(PDO::FETCH_ASSOC);

  $all = $db->prepare("SELECT * FROM clock_events WHERE $where ORDER BY ts, id");
  $all->execute($args);
  $days = [];
  foreach ($all as $r) {
    $d = date('Y-m-d', (int) $r['ts']);
    $s = &$days[$d . '|' . $r['emp_key']];
    $s ??= ['date' => $d, 'name' => $r['name'], 'in' => null, 'out' => null, 'work' => 0, 'brk' => 0, 'since' => null, 'bstart' => null];
    $ts = (int) $r['ts'];
    if ($r['type'] === 'in') {
      $s['in'] ??= $ts;
      $s['since'] = $ts;
    } elseif ($r['type'] === 'break_start' && $s['since'] !== null) {
      $s['work'] += $ts - $s['since'];
      $s['since'] = null;
      $s['bstart'] = $ts;
    } elseif ($r['type'] === 'break_end' && $s['bstart'] !== null) {
      $s['brk'] += $ts - $s['bstart'];
      $s['bstart'] = null;
      $s['since'] = $ts;
    } elseif ($r['type'] === 'out') {
      if ($s['since'] !== null) {
        $s['work'] += $ts - $s['since'];
      } elseif ($s['bstart'] !== null) {
        $s['brk'] += $ts - $s['bstart'];
      }
      $s['since'] = $s['bstart'] = null;
      $s['out'] = $ts;
    }
    unset($s);
  }
  $summary = array_reverse(array_values($days));
  usort($summary, fn($a, $b) => [$b['date'], $a['name']] <=> [$a['date'], $b['name']]);
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load clock data.';
  $pages = 1;
  $retention = 0;
  $reqSelfie = false;
}
$qs = fn(array $o = []) => http_build_query($o + ['emp' => $fEmp, 'from' => $fFrom, 'to' => $fTo]);
$dtVal = fn($ts) => date('Y-m-d\TH:i', (int) $ts);
page_start('Clock Admin', 'clock-admin.php');
?>
<h1>Clock Admin</h1>
<p class="muted">Manage employee PINs and review Time Clock records. Separate from the Google Sheet and payroll.</p>
<?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($flash): ?><div class="<?= $flash[0] === 'ok' ? 'okmsg' : 'error' ?>"><?= h($flash[1]) ?></div><?php endif; ?>

<h2>Settings</h2>
<form method="post" class="ca-form">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="settings">
  <label class="ca-check"><input type="checkbox" name="require_selfie" value="1" <?= $reqSelfie ? 'checked' : '' ?>> Require a selfie for every clock action</label>
  <label>Delete selfies older than (days, 0 = keep forever)
    <input type="number" name="retention_days" min="0" max="3650" value="<?= (int) $retention ?>" class="ca-num">
  </label>
  <button class="btn" type="submit">Save settings</button>
</form>
<form method="post" class="ca-form">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="purge">
  <button class="btn" type="submit">Delete old selfies now</button>
  <span class="muted">Only the photos are removed. The clock records stay.</span>
</form>

<h2>Employee PINs</h2>
<p class="muted">Each employee needs a PIN (4-6 digits) to clock in. Enter a new PIN to replace an existing one.</p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Employee</th><th>Status</th><th>New PIN</th></tr></thead>
    <tbody>
      <?php foreach ($pinRows as $p): ?>
        <tr>
          <td><?= h($p['name']) ?></td>
          <td><?= $p['has'] ? 'PIN set' : 'No PIN yet' ?></td>
          <td>
            <input class="pin-in" type="password" inputmode="numeric" maxlength="6" pattern="\d{4,6}" placeholder="PIN" data-k="<?= h($p['key']) ?>">
            <button class="btn pin-save" type="button">Save</button>
            <span class="pin-msg"></span>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<h2>Records</h2>
<form method="get" class="ca-form">
  <label>Employee
    <select name="emp">
      <option value="">All employees</option>
      <?php foreach ($pinRows as $p): ?><option value="<?= h($p['key']) ?>" <?= $p['key'] === $fEmp ? 'selected' : '' ?>><?= h($p['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>From <input type="date" name="from" value="<?= h($fFrom) ?>"></label>
  <label>To <input type="date" name="to" value="<?= h($fTo) ?>"></label>
  <button class="btn" type="submit">Filter</button>
  <a href="clock.php">Open Time Clock</a>
</form>

<h3>Daily summary</h3>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date</th><th>Employee</th><th>First in</th><th>Last out</th><th>Work time</th><th>Break</th></tr></thead>
    <tbody>
      <?php foreach ($summary as $s): ?>
        <tr>
          <td><?= h(date('M j, Y', strtotime($s['date']))) ?></td>
          <td><?= h($s['name']) ?></td>
          <td><?= $s['in'] ? h(date('g:i A', $s['in'])) : '&mdash;' ?></td>
          <td><?= $s['out'] ? h(date('g:i A', $s['out'])) : '<span class="badge warn">No clock out</span>' ?></td>
          <td><?= h(fmt_dur((int) $s['work'])) ?></td>
          <td><?= h(fmt_dur((int) $s['brk'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$summary): ?><tr><td colspan="6">No records in this range.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h3>Add a missed record</h3>
<form method="post" class="ca-form">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="add">
  <label>Employee
    <select name="emp" required>
      <option value="">Choose...</option>
      <?php foreach ($pinRows as $p): ?><option value="<?= h($p['key']) ?>"><?= h($p['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Action
    <select name="type" required>
      <?php foreach ($labels as $k => $l): ?><option value="<?= h($k) ?>"><?= h($l) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Date and time <input type="datetime-local" name="dt" value="<?= h($dtVal(time())) ?>" required></label>
  <button class="btn" type="submit">Add record</button>
</form>

<h3>All records <span class="muted">(<?= (int) $total ?>)</span></h3>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date and time</th><th>Employee</th><th>Action</th><th>Selfie</th><th>Fix</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h(date('M j, Y g:i A', (int) $r['ts'])) ?><?php if (!empty($r['edited_by'])): ?> <span class="badge"><?= h($r['note']) ?></span><?php endif; ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($labels[$r['type']] ?? $r['type']) ?></td>
          <td>
            <?php if ($r['selfie']): ?>
              <a href="clock.php?selfie=<?= (int) $r['id'] ?>" class="selfie-open"><img class="clock-thumb" src="clock.php?selfie=<?= (int) $r['id'] ?>" alt="Selfie"></a>
            <?php elseif (!empty($r['edited_by'])): ?>&mdash;
            <?php else: ?><span class="badge warn">No selfie</span><?php endif; ?>
          </td>
          <td class="ca-fix">
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="action" value="edit_time">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <input type="datetime-local" name="dt" value="<?= h($dtVal($r['ts'])) ?>" required>
              <button class="btn" type="submit">Save time</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this record?');">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn danger" type="submit">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5">No records in this range.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
<?php if (($pages ?? 1) > 1): ?>
  <p class="ca-pager">
    <?php if ($page > 1): ?><a href="?<?= h($qs(['page' => $page - 1])) ?>">&larr; Newer</a><?php endif; ?>
    Page <?= (int) $page ?> of <?= (int) $pages ?>
    <?php if ($page < $pages): ?><a href="?<?= h($qs(['page' => $page + 1])) ?>">Older &rarr;</a><?php endif; ?>
  </p>
<?php endif; ?>
<form method="post" class="ca-form" onsubmit="return confirm('Delete ALL clock records and selfies? This cannot be undone.');">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="clear_all">
  <button class="btn danger" type="submit">Delete all records (test data)</button>
</form>
<div class="selfie-modal" id="selfieModal" hidden>
  <button type="button" class="selfie-x" id="selfieX" aria-label="Close">&times;</button>
  <img id="selfieBig" alt="Selfie">
</div>
<script>
  const modal = document.getElementById('selfieModal');
  const closeModal = () => {
    modal.hidden = true;
    document.getElementById('selfieBig').removeAttribute('src');
  };
  document.querySelectorAll('.selfie-open').forEach((a) => {
    a.addEventListener('click', (e) => {
      e.preventDefault();
      document.getElementById('selfieBig').src = a.href;
      modal.hidden = false;
    });
  });
  document.getElementById('selfieX').addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeModal();
  });
  document.querySelectorAll('.pin-save').forEach((b) => {
    b.addEventListener('click', async () => {
      const td = b.parentElement;
      const inp = td.querySelector('.pin-in');
      const out = td.querySelector('.pin-msg');
      const r = await fetch('clock.php', {
        method: 'POST',
        body: new URLSearchParams({ csrf: <?= json_encode(csrf()) ?>, type: 'set_pin', emp: inp.dataset.k, pin: inp.value }),
      });
      const d = await r.json();
      out.textContent = d.ok ? ' Saved' : ' ' + (d.msg || 'Error');
      if (d.ok) {
        inp.value = '';
        td.previousElementSibling.textContent = 'PIN set';
      }
    });
  });
</script>
<?php page_end();
