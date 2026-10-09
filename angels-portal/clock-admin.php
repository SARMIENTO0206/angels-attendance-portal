<?php require __DIR__ . '/lib/bootstrap.php';
require_login();
require __DIR__ . '/lib/sheets.php';
require __DIR__ . '/lib/clock.php';
require __DIR__ . '/lib/layout.php';

$labels = ['in' => 'Clock in', 'break_start' => 'Start break', 'break_end' => 'End break', 'out' => 'Clock out'];
$rows = [];
$pinRows = [];
$error = '';
try {
  $db = clock_db();
  $rows = $db->query('SELECT * FROM clock_events ORDER BY id DESC LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
  $hasPin = $db->query('SELECT emp_key FROM clock_pins')->fetchAll(PDO::FETCH_COLUMN);
  foreach (attendance_data($config)['employees'] as $e) {
    $k = emp_key($e['name']);
    $pinRows[] = ['key' => $k, 'name' => $e['name'], 'has' => in_array($k, $hasPin, true)];
  }
} catch (Throwable $e) {
  error_log($e->getMessage());
  $error = 'Could not load clock data.';
}
page_start('Clock Admin', 'clock-admin.php');
?>
<h1>Clock Admin</h1>
<p class="muted">Manage employee PINs and review Time Clock records. Separate from the Google Sheet and payroll.</p>
<?php if ($error): ?><p class="alert"><?= h($error) ?></p><?php endif; ?>

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
<p class="muted">Latest 200 clock events. <a href="clock.php">Open Time Clock</a></p>
<div class="table-wrap">
  <table>
    <thead><tr><th>Date and time</th><th>Employee</th><th>Action</th><th>Selfie</th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h(date('M j, Y g:i A', (int) $r['ts'])) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($labels[$r['type']] ?? $r['type']) ?></td>
          <td>
            <?php if ($r['selfie']): ?>
              <a href="clock.php?selfie=<?= (int) $r['id'] ?>" class="selfie-open"><img class="clock-thumb" src="clock.php?selfie=<?= (int) $r['id'] ?>" alt="Selfie"></a>
            <?php else: ?>&mdash;<?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="4">No records yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
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
