<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const ALMOWAHID_RECEIVER = 'https://script.google.com/macros/s/AKfycbzpY2jJ9X1yXusKxUZo3z8YZC1y8KCuszJ9NrMW9HelE4Iki-I54ewbvPHthXbr54DpwA/exec';
const ALMOWAHID_QUEUE_DIR = '/home/u414915683/.almowahid-leads';

function out_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function clean_value($value, int $max = 1500): string {
    $value = trim((string)($value ?? ''));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max, 'UTF-8');
}
function ensure_queue_dirs(): bool {
    foreach ([ALMOWAHID_QUEUE_DIR, ALMOWAHID_QUEUE_DIR.'/pending', ALMOWAHID_QUEUE_DIR.'/delivered'] as $dir) {
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) return false;
        @chmod($dir, 0700);
    }
    return true;
}
function queue_key(string $submissionId): string {
    return hash('sha256', $submissionId);
}
function queue_save(array $payload): ?string {
    if (!ensure_queue_dirs()) return null;
    $key = queue_key((string)$payload['submission_id']);
    $file = ALMOWAHID_QUEUE_DIR.'/pending/'.$key.'.json';
    $record = [
        'received_at' => gmdate('c'),
        'attempts' => 0,
        'last_attempt_at' => null,
        'last_error' => null,
        'payload' => $payload,
    ];
    $tmp = $file.'.tmp.'.bin2hex(random_bytes(4));
    $json = json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if (!is_string($json) || @file_put_contents($tmp, $json, LOCK_EX) === false) return null;
    @chmod($tmp, 0600);
    if (!@rename($tmp, $file)) { @unlink($tmp); return null; }
    @chmod($file, 0600);
    return $file;
}
function receiver_post(array $payload): array {
    $ch = curl_init(ALMOWAHID_RECEIVER);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload, '', '&', PHP_QUERY_RFC3986),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'Accept: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'ALMOWAHID-Lead-Bridge/2.0',
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    $decoded = is_string($body) ? json_decode($body, true) : null;
    $ok = $curlError === '' && $status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['ok']);
    return ['ok'=>$ok,'status'=>$status,'body'=>is_array($decoded)?$decoded:null,'error'=>$curlError ?: null];
}
function queue_mark_delivered(string $pendingFile, array $response): void {
    $record = json_decode((string)@file_get_contents($pendingFile), true);
    if (!is_array($record)) $record = [];
    $record['delivered_at'] = gmdate('c');
    $record['receiver_response'] = $response;
    $dest = ALMOWAHID_QUEUE_DIR.'/delivered/'.basename($pendingFile);
    $json = json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    if (is_string($json)) {
        @file_put_contents($dest.'.tmp', $json, LOCK_EX);
        @chmod($dest.'.tmp', 0600);
        if (@rename($dest.'.tmp', $dest)) @unlink($pendingFile);
    }
}
function queue_mark_failed(string $pendingFile, array $delivery): void {
    $record = json_decode((string)@file_get_contents($pendingFile), true);
    if (!is_array($record)) return;
    $record['attempts'] = (int)($record['attempts'] ?? 0) + 1;
    $record['last_attempt_at'] = gmdate('c');
    $record['last_error'] = [
        'status'=>$delivery['status'] ?? 0,
        'error'=>$delivery['error'] ?? null,
        'body'=>$delivery['body'] ?? null,
    ];
    @file_put_contents($pendingFile, json_encode($record, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($pendingFile, 0600);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    out_json(405, ['ok'=>false,'error'=>'method_not_allowed']);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) out_json(400, ['ok'=>false,'error'=>'invalid_json']);

$name = clean_value($data['full_name'] ?? '', 180);
$phone = clean_value($data['phone'] ?? '', 80);
$consent = strtolower(clean_value($data['privacy_consent'] ?? '', 20));
$pageUrl = clean_value($data['page_url'] ?? '', 1500);

if ($name === '') out_json(400, ['ok'=>false,'error'=>'name_required','message'=>'من فضلك أدخل الاسم.']);
if ($phone === '') out_json(400, ['ok'=>false,'error'=>'phone_required','message'=>'من فضلك أدخل رقم الجوال.']);
if (!in_array($consent, ['yes','1','true','on'], true)) out_json(400, ['ok'=>false,'error'=>'consent_required','message'=>'يلزم الموافقة على سياسة الخصوصية.']);
if ($pageUrl !== '' && !preg_match('~^https://(?:www\.)?almowahid\.sa(?:/|$)~i', $pageUrl)) {
    out_json(400, ['ok'=>false,'error'=>'invalid_source_page']);
}

$submissionId = clean_value($data['submission_id'] ?? '', 180);
if ($submissionId === '') {
    $submissionId = 'ALMOWAHID-SERVER-'.gmdate('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(4)));
}

$forward = [
    'submission_id' => $submissionId,
    'form_id' => 'ALMOWAHID_WEBSITE_FORM_V1',
    'full_name' => $name,
    'phone' => $phone,
    'service' => clean_value($data['service'] ?? '', 220),
    'nationality' => clean_value($data['nationality'] ?? '', 120),
    'message' => clean_value($data['message'] ?? '', 1500),
    'privacy_consent' => 'yes',
    'page_url' => $pageUrl,
    'gclid' => clean_value($data['gclid'] ?? '', 300),
    'gbraid' => clean_value($data['gbraid'] ?? '', 300),
    'wbraid' => clean_value($data['wbraid'] ?? '', 300),
    'utm_source' => clean_value($data['utm_source'] ?? '', 180),
    'utm_medium' => clean_value($data['utm_medium'] ?? '', 180),
    'utm_campaign' => clean_value($data['utm_campaign'] ?? '', 220),
    'utm_term' => clean_value($data['utm_term'] ?? '', 220),
    'utm_content' => clean_value($data['utm_content'] ?? '', 220),
    'campaign_id' => clean_value($data['campaign_id'] ?? '', 120),
    'adgroup_id' => clean_value($data['adgroup_id'] ?? '', 120),
    'creative_id' => clean_value($data['creative_id'] ?? '', 120),
];

$pendingFile = queue_save($forward);
if ($pendingFile === null) {
    out_json(500, ['ok'=>false,'error'=>'local_queue_unavailable','message'=>'تعذر حفظ الطلب الآن، حاول مرة أخرى.']);
}

$delivery = receiver_post($forward);
if ($delivery['ok']) {
    queue_mark_delivered($pendingFile, $delivery['body'] ?? []);
    $body = $delivery['body'] ?? [];
    out_json(200, [
        'ok'=>true,
        'queued'=>false,
        'lead_id'=>$body['lead_id'] ?? null,
        'submission_id'=>$body['submission_id'] ?? $submissionId,
        'duplicate'=>!empty($body['duplicate']),
    ]);
}

queue_mark_failed($pendingFile, $delivery);

// The lead is already safely stored on Hostinger. Treat it as accepted and retry automatically.
out_json(200, [
    'ok'=>true,
    'queued'=>true,
    'submission_id'=>$submissionId,
    'message'=>'تم استلام طلبك وسيتم مزامنته تلقائيًا.',
]);
