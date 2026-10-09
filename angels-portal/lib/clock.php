<?php
function emp_key($n)
{
  return substr(sha1(strtolower(trim((string) $n))), 0, 16);
}
function clock_db(): PDO
{
  if (!is_dir(DATA_DIR . '/selfies')) {
    @mkdir(DATA_DIR . '/selfies', 0755, true);
  }
  $db = new PDO('sqlite:' . DATA_DIR . '/clock.sqlite', null, null, [PDO::ATTR_TIMEOUT => 15]);
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $db->exec('PRAGMA busy_timeout = 15000');
  // Schema setup only when needed, so concurrent requests don't fight over write locks.
  if ((int) $db->query('PRAGMA user_version')->fetchColumn() >= 2) {
    return $db;
  }
  $db->exec('BEGIN IMMEDIATE');
  if ((int) $db->query('PRAGMA user_version')->fetchColumn() >= 2) {
    $db->exec('COMMIT');
    return $db;
  }
  $db->exec(
    'CREATE TABLE IF NOT EXISTS clock_events (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      emp_key TEXT NOT NULL,
      name TEXT NOT NULL,
      type TEXT NOT NULL,
      ts INTEGER NOT NULL,
      selfie INTEGER NOT NULL DEFAULT 0
    )'
  );
  $db->exec('CREATE INDEX IF NOT EXISTS idx_clock_emp_ts ON clock_events (emp_key, ts)');
  $db->exec(
    'CREATE TABLE IF NOT EXISTS clock_pins (
      emp_key TEXT PRIMARY KEY,
      pin_hash TEXT NOT NULL,
      fails INTEGER NOT NULL DEFAULT 0,
      locked_until INTEGER NOT NULL DEFAULT 0
    )'
  );
  $cols = $db->query('PRAGMA table_info(clock_events)')->fetchAll(PDO::FETCH_COLUMN, 1);
  foreach (['edited_by', 'note'] as $c) {
    if (!in_array($c, $cols, true)) {
      $db->exec("ALTER TABLE clock_events ADD COLUMN $c TEXT");
    }
  }
  $db->exec('CREATE TABLE IF NOT EXISTS clock_settings (k TEXT PRIMARY KEY, v TEXT NOT NULL)');
  $db->exec('PRAGMA user_version = 2');
  $db->exec('COMMIT');
  return $db;
}
function clock_setting(PDO $db, string $k, string $default = ''): string
{
  $st = $db->prepare('SELECT v FROM clock_settings WHERE k = ?');
  $st->execute([$k]);
  $v = $st->fetchColumn();
  return $v === false ? $default : (string) $v;
}
function clock_set(PDO $db, string $k, string $v): void
{
  $db->prepare('INSERT INTO clock_settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v')->execute([$k, $v]);
}
function clock_delete_selfie(int $id): void
{
  $f = DATA_DIR . '/selfies/' . $id . '.jpg';
  if (is_file($f)) {
    @unlink($f);
  }
}
// Removes selfies older than $days (files only; the record stays). Returns count removed.
function clock_purge_selfies(PDO $db, int $days): int
{
  if ($days < 1) {
    return 0;
  }
  $st = $db->prepare('SELECT id FROM clock_events WHERE selfie = 1 AND ts < ?');
  $st->execute([time() - $days * 86400]);
  $ids = $st->fetchAll(PDO::FETCH_COLUMN);
  foreach ($ids as $id) {
    clock_delete_selfie((int) $id);
  }
  if ($ids) {
    $db->prepare('UPDATE clock_events SET selfie = 0 WHERE selfie = 1 AND ts < ?')->execute([time() - $days * 86400]);
  }
  return count($ids);
}
