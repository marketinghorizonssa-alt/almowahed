<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const GOOGLE_SECRET_SHA256 = 'db4bafad4633ea9eb327cdfad091f1e8923d92907231ae3dcf56356c456bf64e';
const ALMOWAHID_RECEIVER = 'https://script.google.com/macros/s/AKfycbzpY2jJ9X1yXusKxUZo3z8YZC1y8KCuszJ9NrMW9HelE4Iki-I54ewbvPHthXbr54DpwA/exec';
const QUEUE_DIR = '/home/u414915683/.almowahid-leads';

function reply_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function clean($v, int $max=1500): string {
    $s=trim((string)($v??''));
    $s=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$s)??'';
    return mb_substr($s,0,$max,'UTF-8');
}
function ensure_dirs(): bool {
    foreach([QUEUE_DIR,QUEUE_DIR.'/pending',QUEUE_DIR.'/delivered'] as $d){
        if(!is_dir($d)&&!@mkdir($d,0700,true)) return false;
        @chmod($d,0700);
    }
    return true;
}
function queue_save(array $payload): ?string {
    if(!ensure_dirs()) return null;
    $key=hash('sha256',(string)$payload['submission_id']);
    $file=QUEUE_DIR.'/pending/'.$key.'.json';
    $record=['received_at'=>gmdate('c'),'attempts'=>0,'last_attempt_at'=>null,'last_error'=>null,'payload'=>$payload];
    $tmp=$file.'.tmp.'.bin2hex(random_bytes(4));
    $json=json_encode($record,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if(!is_string($json)||@file_put_contents($tmp,$json,LOCK_EX)===false) return null;
    @chmod($tmp,0600);
    if(!@rename($tmp,$file)){@unlink($tmp);return null;}
    @chmod($file,0600);
    return $file;
}
function receiver_post(array $payload): array {
    $ch=curl_init(ALMOWAHID_RECEIVER);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>http_build_query($payload,'','&',PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded; charset=UTF-8','Accept: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_FOLLOWLOCATION=>true,
        CURLOPT_MAXREDIRS=>5,
        CURLOPT_CONNECTTIMEOUT=>6,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_USERAGENT=>'ALMOWAHID-Google-Lead-Webhook/1.0'
    ]);
    $body=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    $j=is_string($body)?json_decode($body,true):null;
    return ['ok'=>$err===''&&$status>=200&&$status<300&&is_array($j)&&!empty($j['ok']),'status'=>$status,'body'=>is_array($j)?$j:null,'error'=>$err?:null];
}
function queue_delivered(string $pending,array $response): void {
    $r=json_decode((string)@file_get_contents($pending),true); if(!is_array($r))$r=[];
    $r['delivered_at']=gmdate('c');$r['receiver_response']=$response;
    $dest=QUEUE_DIR.'/delivered/'.basename($pending);
    $json=json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if(is_string($json)){@file_put_contents($dest,$json,LOCK_EX);@chmod($dest,0600);@unlink($pending);}
}
function queue_failed(string $pending,array $d): void {
    $r=json_decode((string)@file_get_contents($pending),true); if(!is_array($r))return;
    $r['attempts']=(int)($r['attempts']??0)+1;$r['last_attempt_at']=gmdate('c');
    $r['last_error']=['status'=>$d['status']??0,'error'=>$d['error']??null,'body'=>$d['body']??null];
    @file_put_contents($pending,json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);@chmod($pending,0600);
}

if(($_SERVER['REQUEST_METHOD']??'')!=='POST') reply_json(405,['ok'=>false,'error'=>'method_not_allowed']);
$raw=file_get_contents('php://input');
$data=json_decode($raw?:'{}',true);
if(!is_array($data)) reply_json(400,['ok'=>false,'error'=>'invalid_json']);

$googleKey=clean($data['google_key']??$data['Google_key']??'',200);
if($googleKey===''||!hash_equals(GOOGLE_SECRET_SHA256,hash('sha256',$googleKey))){
    reply_json(403,['ok'=>false,'error'=>'invalid_google_key']);
}

$leadId=clean($data['lead_id']??'',500);
if($leadId==='') reply_json(400,['ok'=>false,'error'=>'lead_id_required']);

$fields=[];
foreach(($data['user_column_data']??[]) as $col){
    if(!is_array($col)) continue;
    $id=strtoupper(clean($col['column_id']??'',100));
    $fields[$id]=clean($col['string_value']??'',1500);
}
$name=$fields['FULL_NAME']??trim(($fields['FIRST_NAME']??'').' '.($fields['LAST_NAME']??''));
$phone=$fields['PHONE_NUMBER']??'';
$email=$fields['EMAIL']??'';

$campaignId=clean($data['campaign_id']??'',100);
$formId=clean($data['form_id']??'',100);
$gclid=clean($data['gcl_id']??'',500);
$isTest=!empty($data['is_test']);

$forward=[
    'submission_id'=>'GADS-LF-'.$leadId,
    'form_id'=>'ALMOWAHID_GOOGLE_LEAD_FORM_'.($formId?:'UNKNOWN'),
    'full_name'=>$isTest?('TEST - '.($name?:'Google Lead')):$name,
    'phone'=>$phone,
    'email'=>$email,
    'service'=>$isTest?'Google Lead Form TEST':'طلب استقدام - Google Lead Form',
    'privacy_consent'=>'yes',
    'page_url'=>'https://almowahid.sa/ads/',
    'gclid'=>$gclid,
    'utm_source'=>'google',
    'utm_medium'=>'lead_form_asset',
    'utm_campaign'=>$campaignId,
    'campaign_id'=>$campaignId
];

$pending=queue_save($forward);
if($pending===null) reply_json(500,['ok'=>false,'error'=>'queue_unavailable']);

$d=receiver_post($forward);
if($d['ok']){
    queue_delivered($pending,$d['body']??[]);
    reply_json(200,['ok'=>true,'lead_id'=>$leadId,'sheet_lead_id'=>$d['body']['lead_id']??null,'test'=>$isTest]);
}
queue_failed($pending,$d);
// Lead is already durable on Hostinger; current retry worker will resend it.
reply_json(200,['ok'=>true,'queued'=>true,'lead_id'=>$leadId,'test'=>$isTest]);
