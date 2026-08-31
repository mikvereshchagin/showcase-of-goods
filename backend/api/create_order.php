<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database/init.php';

$data = json_decode(file_get_contents('php://input'), true);

$orderId = 'ord_' . uniqid();
$stmt = $db->prepare("
    INSERT INTO orders (id, sku, status, amount, currency, created_at, updated_at)
    VALUES (?, ?, 'created', ?, 'RUB', datetime('now'), datetime('now'))
");
$stmt->execute([$orderId, $data['sku'], $data['amount']]);

echo json_encode([
    'order_id' => $orderId,
    'status' => 'created'
]);