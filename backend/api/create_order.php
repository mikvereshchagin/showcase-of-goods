<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/init.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['sku']) || !isset($data['amount'])) {
    http_response_code(400);
    echo json_encode(['error' => 'SKU and amount are required']);
    exit;
}

$orderId = 'ord_' . uniqid() . '_' . bin2hex(random_bytes(4));

// Для файлового хранилища
$orders = readData('orders.json');
$orders[] = [
    'id' => $orderId,
    'sku' => $data['sku'],
    'status' => 'created',
    'amount' => $data['amount'],
    'currency' => 'RUB',
    'email' => $data['email'] ?? null,
    'delivery_code' => null,
    'delivery_attempts' => 0,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];
writeData('orders.json', $orders);

echo json_encode([
    'order_id' => $orderId,
    'status' => 'created',
    'message' => 'Order created successfully'
]);