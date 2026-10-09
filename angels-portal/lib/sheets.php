<?php
declare(strict_types=1);
function b64url(string $data):string{return rtrim(strtr(base64_encode($data),'+/','-_'),'=');}
function sheets_token(array $config):string{
 $path=$config['service_account_json']??'';
 $raw=getenv('GOOGLE_CREDENTIALS_JSON');
 if(!$raw){if(!$path||!is_file($path))throw new RuntimeException('Service account JSON not found.');$raw=file_get_contents($path);}
 $creds=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if(empty($creds['client_email'])||empty($creds['private_key']))throw new RuntimeException('Invalid service account JSON.');
 $now=time();$header=b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));
 $payload=b64url(json_encode(['iss'=>$creds['client_email'],'scope'=>'https://www.googleapis.com/auth/spreadsheets.readonly','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3500]));
 $message=$header.'.'.$payload;
 if(!openssl_sign($message,$signature,$creds['private_key'],OPENSSL_ALGO_SHA256))throw new RuntimeException('Could not sign authorization request.');
 $jwt=$message.'.'.b64url($signature);
 $ch=curl_init('https://oauth2.googleapis.com/token');
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
 $response=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=json_decode((string)$response,true);
 if($status!==200||empty($data['access_token']))throw new RuntimeException('Google authorization failed. Check credentials and server clock.');
 return $data['access_token'];
}
function sheets_read(array $config,string $range):array{
 $id=$config['spreadsheet_id']??'';
 if(!preg_match('/^[A-Za-z0-9_-]{15,}$/',$id))throw new RuntimeException('Invalid spreadsheet ID in settings.');
 $url='https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($id).'/values/'.rawurlencode($range).'?valueRenderOption=FORMATTED_VALUE';
 $ch=curl_init($url);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.sheets_token($config)]]);
 $response=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
 $data=json_decode((string)$response,true);
 if($status!==200||!is_array($data))throw new RuntimeException('Cannot read Google Sheet. Check sharing permissions, spreadsheet ID, and Sheets API.');
 return $data['values']??[];
}
function attendance_data(array $config):array{
 // Original tracker: B employee; E regular hours; F OT; I salary advance; J total pay; K:X statuses/hours.
 $rows=sheets_read($config,"'ATTENDANCE TRACKER'!B4:X200");
 $week=$rows[0][0]??'Not set';$employees=[];
 foreach($rows as $index=>$r){$sheetRow=$index+4;if($sheetRow<8)continue;
  $name=trim((string)($r[0]??''));if($name==='')continue;
  $days=[];$names=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  foreach($names as $d=>$label){$days[$label]=['status'=>(string)($r[9+$d*2]??''),'hours'=>(string)($r[10+$d*2]??'')];}
  $employees[]=['name'=>$name,'in'=>(string)($r[1]??''),'out'=>(string)($r[2]??''),'work'=>(string)($r[3]??''),'ot'=>(string)($r[4]??''),'advance'=>(string)($r[7]??''),'pay'=>(string)($r[8]??''),'days'=>$days];
 }
 return ['week'=>$week,'employees'=>$employees];
}
