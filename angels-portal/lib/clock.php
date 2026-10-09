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
  $db = new PDO('sqlite:' . DATA_DIR . '/clock.sqlite');
  $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
  return $db;
}
