<?php require __DIR__ . '/lib/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit();
}
verify_csrf();
$_SESSION = [];
session_destroy();
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
  'expires' => time() - 42000,
  'path' => $cookie['path'],
  'domain' => $cookie['domain'],
  'secure' => $cookie['secure'],
  'httponly' => $cookie['httponly'],
  'samesite' => $cookie['samesite'],
]);
header('Location: login.php');
exit();
