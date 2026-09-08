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

    if (!isset($data['sku'])) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        exit;
    }

    $sku = $data['sku'];
    $reservationId = 'res_' . uniqid() . '_' . bin2hex(random_bytes(4));
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    // Начинаем транзакцию
    $db->beginTransaction();

    try {
        // Проверяем наличие товара (без FOR UPDATE)
        $stmt = $db->prepare("
            SELECT stock, price, name 
            FROM products 
            WHERE sku = ?
        ");
        $stmt->execute([$sku]);
        $product = $stmt->fetch();

        if (!$product) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }

        // Проверяем, есть ли активные брони для этого товара
        $stmt = $db->prepare("
            SELECT COUNT(*) as active_reservations
            FROM reservations
            WHERE sku = ? AND status = 'active' AND expires_at > datetime('now')
        ");
        $stmt->execute([$sku]);
        $activeReservations = (int)$stmt->fetchColumn();

        // Доступное количество = stock - активные брони
        $availableStock = $product['stock'] - $activeReservations;

        if ($availableStock <= 0) {
            $db->rollBack();
            http_response_code(409);
            echo json_encode([
                'error' => 'out_of_stock',
                'message' => 'Товар только что раскупили'
            ]);
            exit;
        }

        // Создаем бронь
        $stmt = $db->prepare("
            INSERT INTO reservations (id, sku, status, expires_at)
            VALUES (?, ?, 'active', ?)
        ");
        $stmt->execute([$reservationId, $sku, $expiresAt]);

        $db->commit();

        echo json_encode([
            'status' => 'ok',
            'reservation_id' => $reservationId,
            'expires_at' => $expiresAt,
            'product' => [
                'sku' => $sku,
                'name' => $product['name'],
                'price' => $product['price'],
                'available_stock' => $availableStock
            ],
            'message' => 'Товар забронирован на 5 минут'
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}