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

    if (!isset($data['sku']) || !isset($data['reservation_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'SKU and reservation_id are required']);
        exit;
    }

    $sku = $data['sku'];
    $reservationId = $data['reservation_id'];
    $intentId = $data['intent_id'] ?? null;

    // Снимаем просроченные брони
    expireOverdueReservations($db);

    $db->beginTransaction();

    try {
        // Проверяем бронь
        $stmt = $db->prepare("
            SELECT id, sku, status, expires_at, order_id
            FROM reservations
            WHERE id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation || $reservation['status'] !== 'active') {
            $db->rollBack();
            http_response_code(409);
            echo json_encode(['error' => 'Бронь недействительна или истекла']);
            exit;
        }

        if (strtotime($reservation['expires_at']) < time()) {
            $db->rollBack();
            releaseReservation($db, $reservationId, 'expired');
            http_response_code(409);
            echo json_encode(['error' => 'Бронь истекла']);
            exit;
        }

        if ($reservation['sku'] !== $sku) {
            $db->rollBack();
            http_response_code(400);
            echo json_encode(['error' => 'Бронь не соответствует товару']);
            exit;
        }

        // Если у брони уже есть заказ — возвращаем его (идемпотентность)
        if (!empty($reservation['order_id'])) {
            $db->rollBack();

            $stmt = $db->prepare("SELECT id, status, amount, currency FROM orders WHERE id = ?");
            $stmt->execute([$reservation['order_id']]);
            $existingOrder = $stmt->fetch();

            if ($existingOrder) {
                echo json_encode([
                    'order_id' => $existingOrder['id'],
                    'status' => $existingOrder['status'],
                    'amount' => $existingOrder['amount'],
                    'currency' => $existingOrder['currency'],
                    'reservation_id' => $reservationId,
                    'already_created' => true
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Reservation linked to missing order']);
            }
            exit;
        }

        // Проверяем intent_id: повторный запрос с тем же intent_id
        if ($intentId) {
            $stmt = $db->prepare("SELECT id, status, amount, currency FROM orders WHERE intent_id = ?");
            $stmt->execute([$intentId]);
            $existingOrder = $stmt->fetch();

            if ($existingOrder) {
                $db->rollBack();
                echo json_encode([
                    'order_id' => $existingOrder['id'],
                    'status' => $existingOrder['status'],
                    'amount' => $existingOrder['amount'],
                    'currency' => $existingOrder['currency'],
                    'reservation_id' => $reservationId,
                    'already_created' => true
                ]);
                exit;
            }
        }

        // Берём авторитетную цену с сервера
        $stmt = $db->prepare("SELECT price, currency FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        $product = $stmt->fetch();

        if (!$product) {
            $db->rollBack();
            http_response_code(404);
            echo json_encode(['error' => 'Product not found']);
            exit;
        }

        $orderId = 'ord_' . uniqid() . '_' . bin2hex(random_bytes(4));

        // Создаем заказ
        $stmt = $db->prepare("
            INSERT INTO orders (id, sku, status, amount, currency, email, reservation_id, intent_id)
            VALUES (?, ?, 'created', ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $orderId,
            $sku,
            (float)$product['price'],
            $product['currency'],
            $data['email'] ?? null,
            $reservationId,
            $intentId
        ]);

        // Привязываем бронь к заказу
        $stmt = $db->prepare("
            UPDATE reservations
            SET order_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$orderId, $reservationId]);

        $db->commit();

        echo json_encode([
            'order_id' => $orderId,
            'status' => 'created',
            'reservation_id' => $reservationId,
            'amount' => (float)$product['price'],
            'currency' => $product['currency'],
            'message' => 'Order created successfully'
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