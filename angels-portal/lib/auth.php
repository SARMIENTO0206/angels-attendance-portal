<?php
declare(strict_types=1);

function auth_attempt_allowed(string $scope, int $limit = 5, int $windowSeconds = 900): bool
{
  if ($limit < 1 || $windowSeconds < 1 || !defined('DATA_DIR')) {
    throw new InvalidArgumentException('Invalid authentication rate-limit configuration.');
  }

  $path = DATA_DIR . '/auth-attempts.json';
  $file = @fopen($path, 'c+');
  if ($file === false) {
    throw new RuntimeException('Could not open authentication rate-limit storage.');
  }

  try {
    if (!flock($file, LOCK_EX)) {
      throw new RuntimeException('Could not lock authentication rate-limit storage.');
    }

    rewind($file);
    $raw = stream_get_contents($file);
    if ($raw === false) {
      throw new RuntimeException('Could not read authentication rate-limit storage.');
    }
    $entries = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($entries)) {
      throw new RuntimeException('Authentication rate-limit storage is invalid.');
    }

    $now = time();
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = hash('sha256', $scope . "\0" . $ip);
    $attempts = $entries[$key] ?? [];
    if (!is_array($attempts)) {
      throw new RuntimeException('Authentication rate-limit entry is invalid.');
    }
    $attempts = array_values(
      array_filter($attempts, static fn($at): bool => is_int($at) && $at > $now - $windowSeconds),
    );

    $allowed = count($attempts) < $limit;
    if ($allowed) {
      $attempts[] = $now;
    }
    if ($attempts) {
      $entries[$key] = $attempts;
    } else {
      unset($entries[$key]);
    }

    foreach ($entries as $entryKey => $entryAttempts) {
      if (!is_array($entryAttempts)) {
        throw new RuntimeException('Authentication rate-limit entry is invalid.');
      }
      $entries[$entryKey] = array_values(
        array_filter($entryAttempts, static fn($at): bool => is_int($at) && $at > $now - $windowSeconds),
      );
      if (!$entries[$entryKey]) {
        unset($entries[$entryKey]);
      }
    }

    $json = json_encode($entries, JSON_THROW_ON_ERROR);
    rewind($file);
    if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
      throw new RuntimeException('Could not write authentication rate-limit storage.');
    }
    flock($file, LOCK_UN);
    return $allowed;
  } finally {
    fclose($file);
  }
}

function auth_clear_attempts(string $scope): void
{
  if (!defined('DATA_DIR')) {
    throw new RuntimeException('Authentication rate-limit storage is not configured.');
  }

  $path = DATA_DIR . '/auth-attempts.json';
  $file = @fopen($path, 'c+');
  if ($file === false) {
    throw new RuntimeException('Could not open authentication rate-limit storage.');
  }

  try {
    if (!flock($file, LOCK_EX)) {
      throw new RuntimeException('Could not lock authentication rate-limit storage.');
    }
    rewind($file);
    $raw = stream_get_contents($file);
    if ($raw === false) {
      throw new RuntimeException('Could not read authentication rate-limit storage.');
    }
    $entries = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($entries)) {
      throw new RuntimeException('Authentication rate-limit storage is invalid.');
    }

    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    unset($entries[hash('sha256', $scope . "\0" . $ip)]);
    $json = json_encode($entries, JSON_THROW_ON_ERROR);
    rewind($file);
    if (!ftruncate($file, 0) || fwrite($file, $json) !== strlen($json) || !fflush($file)) {
      throw new RuntimeException('Could not write authentication rate-limit storage.');
    }
    flock($file, LOCK_UN);
  } finally {
    fclose($file);
  }
}
