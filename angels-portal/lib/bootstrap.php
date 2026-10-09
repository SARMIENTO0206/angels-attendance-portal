<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
session_name('angels_portal');
session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','samesite'=>'Lax']);
session_start();
$settingsFile=dirname(__DIR__).'/config/settings.php';
if(!is_file($settingsFile)){http_response_code(503);exit('Setup required: copy config/settings.example.php to config/settings.php and configure it.');}
$config=require $settingsFile;
$localFile=dirname(__DIR__).'/config/local.json';
if(is_file($localFile)){$local=json_decode((string)file_get_contents($localFile),true);if(is_array($local))$config=array_replace($config,$local);}
date_default_timezone_set($config['timezone']??'Asia/Manila');
function h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function require_login():void{if(empty($_SESSION['logged_in'])){header('Location: login.php');exit;}}
function csrf():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function verify_csrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??''))){http_response_code(403);exit('Invalid request token');}}
