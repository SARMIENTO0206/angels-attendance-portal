<?php require __DIR__.'/lib/bootstrap.php';
$msg=null;$done=false;
$_SESSION['fp_try']=$_SESSION['fp_try']??0;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $key=strtoupper(trim((string)($_POST['key']??'')));$new=(string)($_POST['new']??'');$cf=(string)($_POST['confirm']??'');
 $rh=(string)($config['recovery_hash']??'');
 if($_SESSION['fp_try']>=5)$msg='Masyadong maraming subok. Isara ang browser at subukan ulit mamaya.';
 elseif($rh===''||!password_verify($key,$rh)){$_SESSION['fp_try']++;$msg='Mali ang recovery key.';}
 elseif(strlen($new)<8)$msg='Dapat at least 8 characters ang bagong password.';
 elseif($new!==$cf)$msg='Hindi magkapareho ang bagong password at confirmation.';
 else{
  $f=DATA_DIR.'/local.json';
  $cur=is_file($f)?(json_decode((string)file_get_contents($f),true)?:[]):[];
  $cur['admin_password_hash']=password_hash($new,PASSWORD_DEFAULT);
  if(file_put_contents($f,json_encode($cur,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)!==false){$done=true;$_SESSION['fp_try']=0;}
  else $msg='Hindi na-save. I-check ang permission ng config folder.';
 }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Forgot Password | Angels Portal</title><link rel="stylesheet" href="assets/style.css"></head><body class="lg-body">
<main class="lg-card">
 <img class="lg-logo" src="assets/logo.png" alt="Angel's logo">
 <h1>Reset Password</h1>
 <p class="lg-sub">Ilagay ang recovery key para makapagpalit ng password</p>
 <?php if($done):?>
  <div class="okmsg">Napalitan na ang password. Pwede ka nang mag-sign in.</div>
  <a class="lg-btn" href="login.php" style="text-decoration:none">Back to Sign In</a>
 <?php else:?>
 <?php if($msg):?><div class="error"><?=h($msg)?></div><?php endif;?>
 <form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
  <label>Recovery Key</label>
  <div class="lg-field"><svg viewBox="0 0 24 24"><circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/></svg><input name="key" required autocomplete="off" placeholder="XXXX-XXXX-XXXX-XXXX"></div>
  <label>New Password</label>
  <div class="lg-field"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg><input type="password" name="new" required minlength="8" autocomplete="new-password" placeholder="At least 8 characters"><button type="button" class="lg-eye" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
  <label>Confirm New Password</label>
  <div class="lg-field"><svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/></svg><input type="password" name="confirm" required minlength="8" autocomplete="new-password" placeholder="Repeat password"><button type="button" class="lg-eye" aria-label="Show password"><svg viewBox="0 0 24 24"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button></div>
  <button class="lg-btn">Reset Password</button>
 </form>
 <p class="lg-sub" style="margin:16px 0 0"><a class="lg-link" href="login.php">← Back to Sign In</a></p>
 <?php endif;?>
</main><script>document.querySelectorAll(".lg-eye").forEach(function(b){b.onclick=function(){var p=b.parentNode.querySelector("input");p.type=p.type==="password"?"text":"password"}});</script></body></html>
