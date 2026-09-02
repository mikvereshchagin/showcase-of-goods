<?php
// Тест параллельных вебхуков
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';

echo "=== Тест параллельных вебхуков ===\n\n";

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
function sendWebhook($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => $response];
}

// Отправляем 50 параллельных вебхуков
echo "\nОтправляем 50 параллельных вебхуков...\n";

$mh = curl_multi_init();
$channels = [];

for ($i = 0; $i < 50; $i++) {
    $ch = curl_init('http://localhost:8000/backend/api/webhook/payment.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_multi_add_handle($mh, $ch);
    $channels[] = $ch;
}

// Выполняем все запросы параллельно
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

// Собираем результаты
$results = [];
foreach ($channels as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = curl_multi_getcontent($ch);
    $results[] = ['code' => $httpCode, 'response' => json_decode($response, true)];
    curl_multi_remove_handle($mh, $ch);
}

curl_multi_close($mh);

// Анализируем результаты
$successCount = 0;
$duplicateCount = 0;
$errorCount = 0;

foreach ($results as $result) {
    if ($result['code'] == 200 && isset($result['response']['status'])) {
        if ($result['response']['status'] === 'ok') {
            $successCount++;
        } elseif ($result['response']['status'] === 'already_processed') {
            $duplicateCount++;
        }
    } else {
        $errorCount++;
    }
}

echo "Результаты:\n";
echo "- Успешных обработок: $successCount\n";
echo "- Дубликатов: $duplicateCount\n";
echo "- Ошибок: $errorCount\n";

// Проверяем состояние заказа
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

// Проверяем количество использованных ключей
$stmt = $db->prepare("
    SELECT COUNT(*) as used_count
    FROM key_pool 
    WHERE used_by_order_id = ?
");
$stmt->execute([$orderId]);
$usedKeys = $stmt->fetch();

echo "- Использовано ключей для заказа: {$usedKeys['used_count']}\n";

// Проверяем события вебхуков
$stmt = $db->prepare("
    SELECT COUNT(*) as event_count
    FROM webhook_events 
    WHERE event_id = ?
");
$stmt->execute([$webhookData['event_id']]);
$events = $stmt->fetch();

echo "- Событий вебхука сохранено: {$events['event_count']}\n";

// Итоговая проверка
$testPassed = true;
if ($order['status'] !== 'delivered') {
    echo "\n❌ ОШИБКА: Заказ не доставлен!\n";
    $testPassed = false;
}
if ($usedKeys['used_count'] !== 1) {
    echo "\n❌ ОШИБКА: Использовано неверное количество ключей!\n";
    $testPassed = false;
}
if ($events['event_count'] !== 1) {
    echo "\n❌ ОШИБКА: Событие вебхука сохранено неверное количество раз!\n";
    $testPassed = false;
}
if ($order['delivery_attempts'] !== 1) {
    echo "\n❌ ОШИБКА: Неверное количество попыток выдачи!\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: Ключ выдан ровно один раз!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка тестовых данных
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