<?php
// Заглушка поставщика A (основной)
header('Content-Type: application/json');

// Логируем запрос
$logFile = __DIR__ . '/provider_a.log';
$requestData = file_get_contents('php://input');
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Request: $requestData\n", FILE_APPEND);

// Имитация задержки
$delay = mt_rand(0, 2);
sleep($delay);

// Имитация ошибок (10% шанс)
if (mt_rand(1, 100) <= 10) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'reason' => 'provider_error',
        'message' => 'Internal provider error'
    ]);
    exit;
}

// Имитация таймаута (5% шанс)
if (mt_rand(1, 100) <= 5) {
    sleep(5); // Долгий сон - имитация таймаута
    // Ничего не отвечаем
    exit;
}

// Успешный ответ
$request = json_decode($requestData, true);
echo json_encode([
    'status' => 'ok',
    'request_id' => $request['request_id'] ?? 'unknown',
    'code' => 'TEST-' . strtoupper(substr(md5(uniqid()), 0, 12))
]);