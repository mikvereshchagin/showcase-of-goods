<?php
// Тест повторного вебхука
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';

echo "=== Тест повторного вебхука ===\n\n";

// Создаем тестовый заказ
$orderId = 'ord_test_' . uniqid();
$stmt = $db->prepare("
    INSERT INTO orders (id, sku, status, amount, currency)
    VALUES (?, 'STEAM-TOPUP-500', 'created', 500, 'RUB')
");
$stmt->execute([$orderId]);
echo "Создан заказ: $orderId\n";

// Подготавливаем вебхук данные
$webhookData = [
    'event_id' => 'evt_test_' . uniqid(),
    'order_id' => $orderId,
    'status' => 'paid',
    'amount' => 500,
    'currency' => 'RUB',
    'created_at' => date('Y-m-d H:i:s')
];

$jsonData = json_encode($webhookData);

// Функция для отправки вебхука
function sendWebhookRequest($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => json_decode($response, true)];
}

// Отправляем первый вебхук
echo "\nОтправляем первый вебхук...\n";
$firstResult = sendWebhookRequest('http://localhost:8000/backend/api/webhook/payment.php', $jsonData);
echo "Первый ответ: " . json_encode($firstResult['response']) . "\n";

// Небольшая пауза
sleep(1);

// Отправляем повторный вебхук с тем же event_id
echo "\nОтправляем повторный вебхук с тем же event_id...\n";
$secondResult = sendWebhookRequest('http://localhost:8000/backend/api/webhook/payment.php', $jsonData);
echo "Второй ответ: " . json_encode($secondResult['response']) . "\n";

// Проверяем состояние
$stmt = $db->prepare("
    SELECT status, delivery_code, delivery_attempts
    FROM orders 
    WHERE id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

echo "\nСостояние заказа:\n";
echo "- Статус: {$order['status']}\n";
echo "- Ключ: {$order['delivery_code']}\n";
echo "- Попыток выдачи: {$order['delivery_attempts']}\n";

// Проверяем количество событий
$stmt = $db->prepare("
    SELECT COUNT(*) as event_count
    FROM webhook_events 
    WHERE event_id = ?
");
$stmt->execute([$webhookData['event_id']]);
$events = $stmt->fetch();
echo "- Событий вебхука: {$events['event_count']}\n";

// Проверяем количество ключей
$stmt = $db->prepare("
    SELECT COUNT(*) as used_count
    FROM key_pool 
    WHERE used_by_order_id = ?
");
$stmt->execute([$orderId]);
$usedKeys = $stmt->fetch();
echo "- Использовано ключей: {$usedKeys['used_count']}\n";

// Проверка результатов
$testPassed = true;
if ($secondResult['response']['status'] !== 'already_processed') {
    echo "\n❌ ОШИБКА: Повторный вебхук не распознан как дубликат!\n";
    $testPassed = false;
}
if ($order['delivery_attempts'] !== 1) {
    echo "\n❌ ОШИБКА: Ключ выдан более одного раза!\n";
    $testPassed = false;
}
if ($events['event_count'] !== 1) {
    echo "\n❌ ОШИБКА: Событие сохранено более одного раза!\n";
    $testPassed = false;
}
if ($usedKeys['used_count'] !== 1) {
    echo "\n❌ ОШИБКА: Использовано более одного ключа!\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: Повторный вебхук обработан идемпотентно!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка
// Сначала удаляем связанные записи
$stmt = $db->prepare("DELETE FROM webhook_events WHERE order_id = ?");
$stmt->execute([$orderId]);

$stmt = $db->prepare("UPDATE key_pool SET is_used = 0, used_by_order_id = NULL, used_at = NULL WHERE used_by_order_id = ?");
$stmt->execute([$orderId]);

// Потом удаляем заказ
$stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$stmt = $db->prepare("DELETE FROM webhook_events WHERE event_id = ?");
$stmt->execute([$webhookData['event_id']]);
$stmt = $db->prepare("UPDATE key_pool SET is_used = 0, used_by_order_id = NULL, used_at = NULL WHERE used_by_order_id = ?");
$stmt->execute([$orderId]);

echo "\nТестовые данные очищены.\n";