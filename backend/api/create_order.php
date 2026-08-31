<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/init.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['sku']) || !isset($data['amount'])) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU and amount are required']);
        exit;
    }

    $orderId = 'ord_' . uniqid() . '_' . bin2hex(random_bytes(4));

    $stmt = $db->prepare("
        INSERT INTO orders (id, sku, status, amount, currency, email)
        VALUES (?, ?, 'created', ?, 'RUB', ?)
    ");

    $stmt->execute([
        $orderId,
        $data['sku'],
        (float)$data['amount'],
        $data['email'] ?? null
    ]);

    echo json_encode([
        'order_id' => $orderId,
        'status' => 'created',
        'message' => 'Order created successfully'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}