<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';

echo "=== Тест параллельного бронирования ===\n\n";

$db->exec("DELETE FROM reservations WHERE sku = 'KEY-CS2-PRIME'");

$db->exec("UPDATE products SET stock = 1, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

echo "Stock установлен в 1 для KEY-CS2-PRIME\n";

// Функция отправки запроса бронирования
function sendReserveRequest() {
    $ch = curl_init('http://localhost:8000/backend/api/reserve.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sku' => 'KEY-CS2-PRIME']));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'response' => json_decode($response, true)];
}

// Отправляем 50 параллельных запросов
echo "Отправляем 50 параллельных запросов бронирования...\n";

$mh = curl_multi_init();
$channels = [];

for ($i = 0; $i < 50; $i++) {
    $ch = curl_init('http://localhost:8000/backend/api/reserve.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sku' => 'KEY-CS2-PRIME']));
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

$successCount = 0;
$outOfStockCount = 0;
$errorCount = 0;

foreach ($channels as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = curl_multi_getcontent($ch);
    $data = json_decode($response, true);

    if ($httpCode === 200 && isset($data['status']) && $data['status'] === 'ok') {
        $successCount++;
    } elseif ($httpCode === 409) {
        $outOfStockCount++;
    } else {
        $errorCount++;
    }

    curl_multi_remove_handle($mh, $ch);
}

curl_multi_close($mh);

echo "\nРезультаты:\n";
echo "- Успешных броней: $successCount\n";
echo "- Отказов (409): $outOfStockCount\n";
echo "- Ошибок: $errorCount\n";

// Проверяем stock
$stock = $db->query("SELECT stock FROM products WHERE sku = 'KEY-CS2-PRIME'")->fetchColumn();
echo "- Stock после теста: $stock\n";

// Проверяем активные брони
$activeReservations = $db->query("SELECT COUNT(*) FROM reservations WHERE sku = 'KEY-CS2-PRIME' AND status = 'active'")->fetchColumn();
echo "- Активных броней: $activeReservations\n";

// Проверка
$testPassed = true;
if ($successCount !== 1) {
    echo "\n❌ ОШИБКА: Успешных броней должно быть ровно 1, получено: $successCount\n";
    $testPassed = false;
}
if ($outOfStockCount !== 49) {
    echo "\n❌ ОШИБКА: Отказов должно быть 49, получено: $outOfStockCount\n";
    $testPassed = false;
}
if ((int)$stock !== 0) {
    echo "\n❌ ОШИБКА: Stock должен быть 0, получено: $stock\n";
    $testPassed = false;
}
if ((int)$activeReservations !== 1) {
    echo "\n❌ ОШИБКА: Активных броней должно быть 1, получено: $activeReservations\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: Только один покупатель получил последнюю единицу!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка
$db->exec("DELETE FROM reservations WHERE sku = 'KEY-CS2-PRIME'");
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

echo "\nТестовые данные очищены.\n";