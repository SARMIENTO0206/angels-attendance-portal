<?php
declare(strict_types=1);

$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'angels-portal-tests-' . bin2hex(random_bytes(8));
if (!mkdir($tempDir, 0700)) {
  throw new RuntimeException('Could not create temporary test directory.');
}
define('DATA_DIR', $tempDir);

require __DIR__ . '/../angels-portal/lib/attendance.php';
require __DIR__ . '/../angels-portal/lib/auth.php';

$passed = 0;
$check = static function (bool $condition, string $description) use (&$passed): void {
  if (!$condition) {
    throw new RuntimeException('FAILED: ' . $description);
  }
  $passed++;
  echo "ok - $description\n";
};
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$employee = static function (array $statuses): array {
  $days = [];
  foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $name) {
    $days[$name] = ['status' => $statuses[$name] ?? ''];
  }
  return ['days' => $days];
};

try {
  $week = attendance_week_start('10/05/2026');
  $check($week?->format('Y-m-d') === '2026-10-05', 'parses a valid tracker week');
  $check(attendance_week_start('02/30/2026') === null, 'rejects an invalid calendar date');
  $check(attendance_week_start('not set') === null, 'handles an unset tracker week');

  $today = new DateTimeImmutable('2026-10-09');
  $current = attendance_dashboard_summary(
    [$employee(['Fri' => 'PRESENT']), $employee(['Fri' => 'LATE'])],
    $days,
    $week,
    $today,
  );
  $check($current['index'] === 4 && $current['is_today'], 'uses the actual day for the current week');
  $check($current['counts']['present'] === 1 && $current['counts']['half'] === 1, 'counts current-day statuses');
  $check($current['has_data'], 'marks current-day attendance data as available');
  $emptyToday = attendance_dashboard_summary([$employee([])], $days, $week, $today);
  $check($emptyToday['index'] === 4 && !$emptyToday['has_data'], 'keeps today selected when no statuses are entered yet');

  $oldWeek = attendance_week_start('09/28/2026');
  $historical = attendance_dashboard_summary(
    [$employee(['Wed' => 'ABSENT']), $employee(['Fri' => 'HALF DAY'])],
    $days,
    $oldWeek,
    $today,
  );
  $check($historical['index'] === 4 && !$historical['is_today'], 'selects the latest recorded day in a past week');
  $check($historical['date']?->format('Y-m-d') === '2026-10-02', 'returns the historical day date');
  $check($historical['counts']['half'] === 1, 'counts statuses for the selected historical day');

  $noHistory = attendance_dashboard_summary([$employee([])], $days, $oldWeek, $today);
  $check($noHistory['index'] === null && !$noHistory['has_data'], 'does not invent a date without historical data');
  $future = attendance_dashboard_summary([$employee(['Mon' => 'PRESENT'])], $days, attendance_week_start('10/12/2026'), $today);
  $check($future['index'] === null, 'does not report a future tracker day as today');

  $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
  for ($i = 0; $i < 5; $i++) {
    $check(auth_attempt_allowed('login'), 'allows authentication attempt ' . ($i + 1));
  }
  $check(!auth_attempt_allowed('login'), 'blocks attempts beyond the configured limit');
  $_SERVER['REMOTE_ADDR'] = '192.0.2.11';
  $check(auth_attempt_allowed('login'), 'tracks attempts separately by client IP');
  $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
  $check(auth_attempt_allowed('recovery'), 'tracks password recovery separately from login');
  auth_clear_attempts('login');
  $check(auth_attempt_allowed('login'), 'clears attempts after successful authentication');

  echo "\n$passed checks passed.\n";
} finally {
  $rateLimitFile = $tempDir . DIRECTORY_SEPARATOR . 'auth-attempts.json';
  if (is_file($rateLimitFile) && !unlink($rateLimitFile)) {
    throw new RuntimeException('Could not remove temporary test data.');
  }
  if (!rmdir($tempDir)) {
    throw new RuntimeException('Could not remove temporary test directory.');
  }
}
