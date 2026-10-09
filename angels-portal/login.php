<?php require __DIR__ . '/lib/bootstrap.php';
if (!empty($_SESSION['logged_in'])) {
  header('Location: index.php');
  exit();
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  verify_csrf();
  $username = (string) ($_POST['username'] ?? '');
  $pw = (string) ($_POST['password'] ?? '');
  if (
    hash_equals((string) $config['admin_username'], $username) &&
    password_verify($pw, (string) $config['admin_password_hash'])
  ) {
    session_regenerate_id(true);
    $_SESSION['logged_in'] = true;
    header('Location: index.php');
    exit();
  }
  $error = 'Invalid username or password.';
}
?><!doctype html>
  <html lang="en">
  <head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login | Angels Portal</title>
  <link rel="stylesheet" href="assets/style.css">
  </head>
<body class="lg-body">
<section class="lg-hero">
 <div class="lg-line"></div>
 <h2>ANGEL’S</h2>
 <h3>ALUMINUM &amp; GLASS SERVICES</h3>
 <p>Attendance &amp; Payroll Portal</p>
 <ul class="lg-feats">
  <li><svg viewBox="0 0 24 24"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg><span>Secure<br>Access</span></li>
  <li><svg viewBox="0 0 24 24"><path d="M4 20V10M10 20V4M16 20v-8M21 20H3"/></svg><span>Attendance<br>Tracking</span></li>
  <li><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><circle cx="17" cy="9" r="2.4"/><path d="M17 14c2.5 0 4 2 4 5"/></svg><span>Employee<br>Management</span></li>
 </ul>
</section>
<main class="lg-card">
 <img class="lg-logo" src="assets/logo.png" alt="Angel's logo">
 <h1>Admin Sign In</h1>
 <p class="lg-sub">Access your attendance and payroll dashboard</p>
 <?php if ($error): ?><div class="error"><?= h($error) ?></div><?php endif; ?>
 <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <label>Username</label>
  <div class="lg-field"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg><input name="username" required autocomplete="username" placeholder="Username"></div>
  <label>Password</label>
  <div class="lg-field"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg><input id="pw" type="password" name="password" required autocomplete="current-password" placeholder="Password"><button type="button" class="lg-eye" id="eye" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
  <div class="lg-forgot"><a class="lg-link" href="forgot.php">Forgot password?</a></div>
  <button class="lg-btn">Sign In <svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M14 4h5a2 2 0 012 2v12a2 2 0 01-2 2h-5"/></svg></button>
 </form>
</main>
<script>document.getElementById('eye').onclick=function(){var p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'};</script>
</body></html>
