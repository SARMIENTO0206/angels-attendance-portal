<?php require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/sheets.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
  if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) {
    throw new RuntimeException('Application data directory is not writable.');
  }
  $data = attendance_data($config);
  if (!isset($data['employees']) || !is_array($data['employees'])) {
    throw new RuntimeException('Attendance data response is invalid.');
  }
  echo json_encode(['status' => 'ok'], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
  error_log('[health] ' . $e->getMessage());
  http_response_code(503);
  echo json_encode(['status' => 'error'], JSON_THROW_ON_ERROR);
}
