<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../backend/database/init.php';
require_once __DIR__ . '/../backend/providers/reservation_helper.php';

echo "=== Тест истечения брони ===\n\n";

// Устанавливаем stock = 5
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

// Создаём активную бронь
$reservationId = 'res_test_' . uniqid();
$expiresAt = date('Y-m-d H:i:s', time() + 300);

$stmt = $db->prepare("
    INSERT INTO reservations (id, sku, status, expires_at)
    VALUES (?, 'KEY-CS2-PRIME', 'active', ?)
");
$stmt->execute([$reservationId, $expiresAt]);

// Списываем stock вручную (как это делает reserve.php)
$db->exec("UPDATE products SET stock = stock - 1, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

$stockBefore = $db->query("SELECT stock FROM products WHERE sku = 'KEY-CS2-PRIME'")->fetchColumn();
echo "Stock до истечения: $stockBefore\n";

// Делаем бронь просроченной
$db->exec("UPDATE reservations SET expires_at = datetime('now', '-1 minute') WHERE id = '$reservationId'");

echo "Бронь помечена просроченной\n";

// Запускаем sweep
$expiredCount = expireOverdueReservations($db);
echo "Освобождено броней: $expiredCount\n";

// Проверяем статус брони
$status = $db->query("SELECT status FROM reservations WHERE id = '$reservationId'")->fetchColumn();
echo "Статус брони: $status\n";

// Проверяем stock
$stockAfter = $db->query("SELECT stock FROM products WHERE sku = 'KEY-CS2-PRIME'")->fetchColumn();
echo "Stock после истечения: $stockAfter\n";

// Повторный sweep — ничего не должен делать
$expiredCount2 = expireOverdueReservations($db);
echo "Повторный sweep: $expiredCount2\n";

// Проверки
$testPassed = true;

if ($expiredCount !== 1) {
    echo "\n❌ ОШИБКА: Освобождена должна быть 1 бронь, получено: $expiredCount\n";
    $testPassed = false;
}

if ($status !== 'expired') {
    echo "\n❌ ОШИБКА: Статус должен быть 'expired', получено: $status\n";
    $testPassed = false;
}

if ((int)$stockAfter !== (int)$stockBefore + 1) {
    echo "\n❌ ОШИБКА: Stock должен вернуться к " . ($stockBefore + 1) . ", получено: $stockAfter\n";
    $testPassed = false;
}

if ($expiredCount2 !== 0) {
    echo "\n❌ ОШИБКА: Повторный sweep должен вернуть 0, получено: $expiredCount2\n";
    $testPassed = false;
}

if ($testPassed) {
    echo "\n✅ ТЕСТ ПРОЙДЕН: Просроченная бронь вернула товар, повторный sweep идемпотентен!\n";
} else {
    echo "\n❌ ТЕСТ ПРОВАЛЕН!\n";
}

// Очистка
$db->exec("DELETE FROM reservations WHERE id = '$reservationId'");
$db->exec("UPDATE products SET stock = 5, updated_at = datetime('now') WHERE sku = 'KEY-CS2-PRIME'");

echo "\nТестовые данные очищены.\n";
