<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/init.php';

try {
    $orderId = $_GET['order_id'] ?? '';

    if (!$orderId) {
        http_response_code(400);
        echo json_encode(['error' => 'Order ID is required']);
        exit;
    }

    $stmt = $db->prepare("
        SELECT id, sku, status, amount, currency, delivery_code, created_at, updated_at
        FROM orders 
        WHERE id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    echo json_encode($order);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}