<?php
declare(strict_types=1);

function attendance_week_start(string $value): ?DateTimeImmutable
{
  $date = DateTimeImmutable::createFromFormat('!m/d/Y', trim($value));
  if (!$date || DateTimeImmutable::getLastErrors() !== false) {
    return null;
  }
  return $date;
}

function attendance_status_category(string $status): string
{
  $status = strtoupper(trim($status));
  if ($status === '') {
    return 'off';
  }
  if (in_array($status, ['PRESENT', 'HOLIDAY', 'RDOT'], true)) {
    return 'present';
  }
  if ($status === 'ABSENT') {
    return 'absent';
  }
  if (in_array($status, ['LATE', 'HALF DAY'], true)) {
    return 'half';
  }
  return 'off';
}

function attendance_dashboard_summary(
  array $employees,
  array $dayNames,
  ?DateTimeImmutable $weekStart,
  DateTimeImmutable $today,
): array {
  $zeroCounts = ['present' => 0, 'absent' => 0, 'half' => 0, 'off' => 0];
  $empty = ['index' => null, 'date' => null, 'is_today' => false, 'has_data' => false, 'counts' => $zeroCounts];
  if (!$weekStart) {
    return $empty;
  }

  $diff = (int) $weekStart->diff($today)->format('%r%a');
  if ($diff < 0) {
    return $empty;
  }

  $index = $diff < count($dayNames) ? $diff : null;
  if ($index === null) {
    for ($i = count($dayNames) - 1; $i >= 0; $i--) {
      foreach ($employees as $employee) {
        $status = trim((string) ($employee['days'][$dayNames[$i]]['status'] ?? ''));
        if ($status !== '') {
          $index = $i;
          break 2;
        }
      }
    }
  }
  if ($index === null) {
    return $empty;
  }

  $counts = $zeroCounts;
  $hasData = false;
  foreach ($employees as $employee) {
    $status = trim((string) ($employee['days'][$dayNames[$index]]['status'] ?? ''));
    if ($status !== '') {
      $hasData = true;
    }
    $counts[attendance_status_category($status)]++;
  }

  return [
    'index' => $index,
    'date' => $weekStart->modify("+$index days"),
    'is_today' => $diff === $index,
    'has_data' => $hasData,
    'counts' => $counts,
  ];
}
