<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/init.php';

$orderId = $_GET['order_id'] ?? '';

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$orders = readData('orders.json');
$order = null;

foreach ($orders as $item) {
    if ($item['id'] === $orderId) {
        $order = $item;
        break;
    }
}

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found']);
    exit;
}

echo json_encode($order);