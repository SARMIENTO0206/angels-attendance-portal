<?php require __DIR__ . '/lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit();
}
verify_csrf();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit();
