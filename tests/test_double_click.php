<?php
// Тест двойного клика
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Тест двойного клика ===\n\n";

// Создаем заказ через API
$orderData = [
    'sku' => 'STEAM-TOPUP-500',
    'amount' => 500,
    'email' => 'test@test.com'
];

$ch = curl_init('http://localhost:8000/backend/api/create_order.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$orderResult = json_decode($response, true);
curl_close($ch);

$orderId = $orderResult['order_id'];
echo "Создан заказ: $orderId\n";

// Отправляем два вебхука параллельно (имитация двойного клика)
$webhookData1 = [
    'event_id' => 'evt_click_1_' . uniqid(),
    'order_id' => $orderId,
    'status' => 'paid',
    'amount' => 500,
    'currency' => 'RUB',
    'created_at' => date('Y-m-d H:i:s')
];

$webhookData2 = [
    'event_id' => 'evt_click_2_' . uniqid(),
    'order_id' => $orderId,
    'status' => 'paid',
    'amount' => 500,
    'currency' => 'RUB',
    'created_at' => date('Y-m-d H:i:s')
];

// Отправляем оба вебхука параллельно
$mh = curl_multi_init();
$channels = [];

foreach ([$webhookData1, $webhookData2] as $webhookData) {
    $ch = curl_init('http://localhost:8000/backend/api/webhook/payment.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_multi_add_handle($mh, $ch);
    $channels[] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

foreach ($channels as $ch) {
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);

// Проверяем результат
require_once __DIR__ . '/../backend/database/init.php';

$stmt = $db->prepare("
    SELECT status, delivery_code, delivery_attempts
    FROM orders 
    WHERE id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

echo "\nРезультат:\n";
echo "- Статус: {$order['status']}\n";
echo "- Ключ: {$order['delivery_code']}\n";
echo "- Попыток выдачи: {$order['delivery_attempts']}\n";

$stmt = $db->prepare("
    SELECT COUNT(*) as used_count
    FROM key_pool 
    WHERE used_by_order_id = ?
");
$stmt->execute([$orderId]);
$usedKeys = $stmt->fetch();
echo "- Использовано ключей: {$usedKeys['used_count']}\n";

if ($order['delivery_attempts'] === 1 && $usedKeys['used_count'] === 1) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: При двойном клике ключ выдан один раз!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН: Обнаружено задвоение!\n";
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
$stmt = $db->prepare("DELETE FROM webhook_events WHERE order_id = ?");
$stmt->execute([$orderId]);
$stmt = $db->prepare("UPDATE key_pool SET is_used = 0, used_by_order_id = NULL, used_at = NULL WHERE used_by_order_id = ?");
$stmt->execute([$orderId]);