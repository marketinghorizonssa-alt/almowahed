<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
const ENDPOINT='https://script.google.com/macros/s/AKfycbzpY2jJ9X1yXusKxUZo3z8YZC1y8KCuszJ9NrMW9HelE4Iki-I54ewbvPHthXbr54DpwA/exec';
const BASE='/home/u414915683/.almowahid-leads';
function post_receiver(array $p): array {
    $ch=curl_init(ENDPOINT);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($p,'','&',PHP_QUERY_RFC3986),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded; charset=UTF-8','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>5,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>15,CURLOPT_USERAGENT=>'ALMOWAHID-Lead-Retry/1.0']);
    $body=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $j=is_string($body)?json_decode($body,true):null;$ok=$err===''&&$status>=200&&$status<300&&is_array($j)&&!empty($j['ok']);
    return ['ok'=>$ok,'status'=>$status,'body'=>is_array($j)?$j:null,'error'=>$err?:null];
}
if(!is_dir(BASE.'/pending')){echo "ALMOWAHID_RETRY_OK processed=0 delivered=0 pending=0\n";exit;}
$lock=@fopen(BASE.'/retry.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){echo "ALMOWAHID_RETRY_BUSY\n";exit;}
$files=array_slice(glob(BASE.'/pending/*.json')?:[],0,50);$processed=0;$delivered=0;
foreach($files as $file){$r=json_decode((string)@file_get_contents($file),true);if(!is_array($r)||!is_array($r['payload']??null))continue;$processed++;$d=post_receiver($r['payload']);
if($d['ok']){$r['delivered_at']=gmdate('c');$r['receiver_response']=$d['body'];$dest=BASE.'/delivered/'.basename($file);@file_put_contents($dest,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);@chmod($dest,0600);@unlink($file);$delivered++;}
else{$r['attempts']=(int)($r['attempts']??0)+1;$r['last_attempt_at']=gmdate('c');$r['last_error']=['status'=>$d['status'],'error'=>$d['error'],'body'=>$d['body']];@file_put_contents($file,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);@chmod($file,0600);}}
$pending=count(glob(BASE.'/pending/*.json')?:[]);flock($lock,LOCK_UN);fclose($lock);echo "ALMOWAHID_RETRY_OK processed=$processed delivered=$delivered pending=$pending\n";
