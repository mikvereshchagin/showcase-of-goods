<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';

echo "=== Тест параллельных вебхуков оплаты ===\n\n";

// Устанавливаем stock = 5
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'STEAM-TOPUP-500'");

// Создаём тестовый заказ напрямую
$orderId = 'ord_test_' . uniqid();
$stmt = $db->prepare("
    INSERT INTO orders (id, sku, status, amount, currency, email)
    VALUES (?, 'STEAM-TOPUP-500', 'created', 500, 'RUB', 'test@test.com')
");
$stmt->execute([$orderId]);
echo "Создан заказ: $orderId\n";

// Подготавливаем вебхук данные
$eventId = 'evt_test_' . uniqid();
$webhookData = json_encode([
    'event_id' => $eventId,
    'order_id' => $orderId,
    'status' => 'paid',
    'amount' => 500,
    'currency' => 'RUB',
    'created_at' => date('Y-m-d H:i:s')
]);

// Отправляем 50 параллельных вебхуков
echo "Отправляем 50 параллельных вебхуков...\n";

$mh = curl_multi_init();
$channels = [];

for ($i = 0; $i < 50; $i++) {
    $ch = curl_init('http://localhost:8000/backend/api/webhook/payment.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $webhookData);
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

$okCount = 0;
$alreadyProcessedCount = 0;
$errorCount = 0;

foreach ($channels as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = curl_multi_getcontent($ch);
    $data = json_decode($response, true);

    if ($httpCode === 200) {
        if (isset($data['status']) && $data['status'] === 'ok' && $data['order_updated'] === true) {
            $okCount++;
        } elseif (isset($data['status']) && $data['status'] === 'already_processed') {
            $alreadyProcessedCount++;
        } elseif (isset($data['status']) && $data['status'] === 'ok' && $data['order_updated'] === false) {
            // Тоже корректный ответ — событие зафиксировано, но заказ уже обработан
            $alreadyProcessedCount++;
        } else {
            $errorCount++;
        }
    } else {
        $errorCount++;
    }

    curl_multi_remove_handle($mh, $ch);
}

curl_multi_close($mh);

echo "\nРезультаты:\n";
echo "- Успешных обработок (order_updated=true): $okCount\n";
echo "- Дубликатов/повторных: $alreadyProcessedCount\n";
echo "- Ошибок: $errorCount\n";

// Проверяем состояние заказа
$stmt = $db->prepare("SELECT status, delivery_code, delivery_attempts FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

echo "\nСостояние заказа:\n";
echo "- Статус: {$order['status']}\n";
echo "- Ключ: {$order['delivery_code']}\n";
echo "- Попыток выдачи: {$order['delivery_attempts']}\n";

// Проверяем количество событий
$eventCount = $db->query("SELECT COUNT(*) FROM webhook_events WHERE event_id = '$eventId'")->fetchColumn();
echo "- Событий вебхука: $eventCount\n";

// Проверяем количество использованных ключей
$keyCount = $db->query("SELECT COUNT(*) FROM key_pool WHERE used_by_order_id = '$orderId'")->fetchColumn();
echo "- Использовано ключей: $keyCount\n";

// Проверки
$testPassed = true;

if ($okCount !== 1) {
    echo "\n❌ ОШИБКА: Успешных обработок должно быть 1, получено: $okCount\n";
    $testPassed = false;
}

if ($errorCount !== 0) {
    echo "\n❌ ОШИБКА: Ошибок быть не должно, получено: $errorCount\n";
    $testPassed = false;
}

if ($order['status'] !== 'delivered') {
    echo "\n❌ ОШИБКА: Заказ должен быть delivered, получено: {$order['status']}\n";
    $testPassed = false;
}

if ((int)$order['delivery_attempts'] !== 1) {
    echo "\n❌ ОШИБКА: Попыток выдачи должно быть 1, получено: {$order['delivery_attempts']}\n";
    $testPassed = false;
}

if ((int)$eventCount !== 1) {
    echo "\n❌ ОШИБКА: Событий должно быть 1, получено: $eventCount\n";
    $testPassed = false;
}

if ((int)$keyCount !== 1) {
    echo "\n❌ ОШИБКА: Ключей должно быть использовано 1, получено: $keyCount\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: 50 параллельных вебхуков дали ровно один ключ!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка
$db->exec("UPDATE key_pool SET is_used = 0, used_by_order_id = NULL, used_at = NULL WHERE used_by_order_id = '$orderId'");
$db->exec("DELETE FROM webhook_events WHERE event_id = '$eventId'");
$db->exec("DELETE FROM orders WHERE id = '$orderId'");
$db->exec("UPDATE products SET stock = 10, updated_at = datetime('now') WHERE sku = 'STEAM-TOPUP-500'");

echo "\nТестовые данные очищены.\n";