<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';

echo "=== Тест дубликата intent_id ===\n\n";

// Устанавливаем stock = 5
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

// Функция создания брони через API
function reserveProduct($sku) {
    $ch = curl_init('http://localhost:8000/backend/api/reserve.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['sku' => $sku]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Функция создания заказа
function createOrder($sku, $reservationId, $intentId) {
    $ch = curl_init('http://localhost:8000/backend/api/create_order.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'sku' => $sku,
        'reservation_id' => $reservationId,
        'intent_id' => $intentId,
        'email' => 'test@test.com'
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Создаём бронь
$reservation = reserveProduct('KEY-CS2-PRIME');
$reservationId = $reservation['reservation_id'];
echo "Бронь создана: $reservationId\n";

// Создаём заказ с intent_id
$intentId = 'intent_test_' . uniqid();
$firstOrder = createOrder('KEY-CS2-PRIME', $reservationId, $intentId);
echo "Первый заказ: {$firstOrder['order_id']}\n";

// Пытаемся создать заказ с тем же intent_id
$secondOrder = createOrder('KEY-CS2-PRIME', $reservationId, $intentId);
echo "Второй запрос с тем же intent_id\n";

// Проверяем
$orderCount = $db->query("SELECT COUNT(*) FROM orders WHERE intent_id = '$intentId'")->fetchColumn();
echo "Заказов с intent_id: $orderCount\n";

$testPassed = true;

if ($orderCount !== 1) {
    echo "\n❌ ОШИБКА: Должен быть 1 заказ с intent_id, получено: $orderCount\n";
    $testPassed = false;
}

if (!isset($secondOrder['already_created']) || $secondOrder['already_created'] !== true) {
    echo "\n❌ ОШИБКА: Второй запрос должен вернуть already_created: true\n";
    $testPassed = false;
}

if ($firstOrder['order_id'] !== $secondOrder['order_id']) {
    echo "\n❌ ОШИБКА: Должен вернуться тот же order_id\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: Повторный intent_id не создал дубль заказа!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка
$db->exec("UPDATE reservations SET order_id = NULL WHERE id = '$reservationId'");
$db->exec("DELETE FROM orders WHERE intent_id = '$intentId'");
$db->exec("DELETE FROM reservations WHERE id = '$reservationId'");
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

echo "\nТестовые данные очищены.\n";