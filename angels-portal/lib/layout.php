<?php
function page_start($title, $active)
{
  ?><!doctype html>
  <html lang="en">
  <head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>
  <?= h(
  $title,
) ?> | Angel's Aluminum & Glass Services</title>
  <link rel="stylesheet" href="assets/style.css">
  </head>
<body><div class="shell"><aside class="sidebar"><img class="logo-side" src="assets/logo.png" alt="Angel's logo"><div class="brand">ANGEL'S</div><div class="side-sub">ALUMINUM & GLASS SERVICES</div><nav><?php
 $ic = [
   'index.php' =>
     '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
   'tracker.php' =>
     '<rect x="3" y="5" width="18" height="16" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="2" fill="none"/>',
   'employees.php' =>
     '<circle cx="9" cy="8" r="3.6"/><path d="M2 20c0-3.6 3-6 7-6s7 2.4 7 6z"/><circle cx="17.5" cy="9" r="2.6"/><path d="M18 14c2.7.2 4.5 2 4.5 5h-4.2c0-2-.6-3.6-1.8-4.6z"/>',
   'settings.php' =>
     '<circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 2l2 3 3.5-.5.8 3.4 3.2 1.6-1.4 3.3 1.4 3.3-3.2 1.6-.8 3.4L14 19l-2 3-2-3-3.5.5-.8-3.4L2.5 13.5 3.9 10.2 2.5 6.9l3.200-1.600.8-3.400L10 5z" fill="none" stroke="currentColor" stroke-width="1.6"/>',
 ];
 foreach (
   [
     'index.php' => 'Attendance Dashboard',
     'tracker.php' => 'Weekly Tracker',
     'employees.php' => 'Employees',
     'settings.php' => 'Settings',
   ]
   as $u => $l
 ): ?><a class="<?= $u === $active ? 'active' : '' ?>" href="<?= h(
  $u,
) ?>"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><?= $ic[$u] ?></svg><?= h(
  $l,
) ?></a><?php endforeach;?></nav><div class="side-bottom"><span class="dot"></span><div><b>Connected to Google Sheets</b><br>Live data • Read-only</div></div></aside><main class="main"><?php
}
function page_end()
{
  ?><footer>Angel's Aluminum & Glass Services • Data stays in Google Sheets • No payroll changes performed</footer></main></div></body></html><?php
}
