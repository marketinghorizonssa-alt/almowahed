<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '{}', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json'], JSON_UNESCAPED_UNICODE);
    exit;
}

function clean_value($value, int $max = 1500): string {
    $value = trim((string)($value ?? ''));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return mb_substr($value, 0, $max, 'UTF-8');
}

$name = clean_value($data['full_name'] ?? '', 180);
$phone = clean_value($data['phone'] ?? '', 80);
$consent = strtolower(clean_value($data['privacy_consent'] ?? '', 20));

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'name_required', 'message' => 'من فضلك أدخل الاسم.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'phone_required', 'message' => 'من فضلك أدخل رقم الجوال.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($consent, ['yes','1','true','on'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'consent_required', 'message' => 'يلزم الموافقة على سياسة الخصوصية.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$forward = [
    'submission_id' => clean_value($data['submission_id'] ?? '', 180),
    'form_id' => 'ALMOWAHID_WEBSITE_FORM_V1',
    'full_name' => $name,
    'phone' => $phone,
    'service' => clean_value($data['service'] ?? '', 220),
    'nationality' => clean_value($data['nationality'] ?? '', 120),
    'message' => clean_value($data['message'] ?? '', 1500),
    'privacy_consent' => 'yes',
    'page_url' => clean_value($data['page_url'] ?? '', 1500),
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

$endpoint = 'https://script.google.com/macros/s/AKfycbzpY2jJ9X1yXusKxUZo3z8YZC1y8KCuszJ9NrMW9HelE4Iki-I54ewbvPHthXbr54DpwA/exec';

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($forward, '', '&', PHP_QUERY_RFC3986),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_CONNECTTIMEOUT => 6,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'ALMOWAHID-Lead-Bridge/1.0',
]);

$body = curl_exec($ch);
$curlError = curl_error($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($body === false || $curlError !== '') {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'receiver_unreachable', 'message' => 'تعذر إرسال الطلب الآن، حاول مرة أخرى.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = json_decode((string)$body, true);
if (!is_array($result)) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'invalid_receiver_response', 'message' => 'تعذر تأكيد استلام الطلب.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($result['ok'])) {
    $error = (string)($result['error'] ?? 'receiver_rejected');
    $message = $error === 'invalid_phone'
        ? 'الرقم لم يتم قبوله من مستقبل البيانات الحالي.'
        : 'تعذر إرسال الطلب الآن، حاول مرة أخرى.';
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $error, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode([
    'ok' => true,
    'lead_id' => $result['lead_id'] ?? null,
    'submission_id' => $result['submission_id'] ?? ($forward['submission_id'] ?: null),
    'duplicate' => !empty($result['duplicate']),
], JSON_UNESCAPED_UNICODE);
