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
require_once __DIR__ . '/../providers/reservation_helper.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['sku'])) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU is required']);
        exit;
    }

    // Снимаем просроченные брони перед резервированием
    expireOverdueReservations($db);

    $sku = $data['sku'];
    $reservationId = 'res_' . uniqid() . '_' . bin2hex(random_bytes(4));
    $expiresAt = date('Y-m-d H:i:s', time() + 300);

    // Начинаем транзакцию
    $db->beginTransaction();

    try {
        // Атомарно списываем 1 единицу остатка
        $stmt = $db->prepare("
            UPDATE products
            SET stock = stock - 1,
                updated_at = datetime('now')
            WHERE sku = ?
              AND stock >= 1
        ");
        $stmt->execute([$sku]);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();

            $check = $db->prepare("SELECT stock FROM products WHERE sku = ?");
            $check->execute([$sku]);
            $product = $check->fetch();

            if (!$product) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
            } else {
                http_response_code(409);
                echo json_encode([
                    'error' => 'out_of_stock',
                    'message' => 'Товар только что раскупили'
                ]);
            }
            exit;
        }

        // Получаем актуальную цену и имя
        $stmt = $db->prepare("SELECT price, name FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        $product = $stmt->fetch();

        // Создаем бронь
        $stmt = $db->prepare("
            INSERT INTO reservations (id, sku, status, expires_at)
            VALUES (?, ?, 'active', ?)
        ");
        $stmt->execute([$reservationId, $sku, $expiresAt]);

        // Получаем новый остаток
        $stmt = $db->prepare("SELECT stock FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        $newStock = (int)$stmt->fetchColumn();

        $db->commit();

        echo json_encode([
            'status' => 'ok',
            'reservation_id' => $reservationId,
            'expires_at' => $expiresAt,
            'product' => [
                'sku' => $sku,
                'name' => $product['name'],
                'price' => $product['price'],
                'available_stock' => $newStock
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