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
    $reservationId = $data['reservation_id'] ?? null;

    $db->beginTransaction();

    try {
        // Если есть бронь, проверяем её
        if ($reservationId) {
            $stmt = $db->prepare("
                SELECT sku, status, expires_at 
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
                // Бронь истекла, снимаем её
                $stmt = $db->prepare("
                    UPDATE reservations 
                    SET status = 'expired', released_at = datetime('now')
                    WHERE id = ?
                ");
                $stmt->execute([$reservationId]);

                $db->rollBack();
                http_response_code(409);
                echo json_encode(['error' => 'Бронь истекла']);
                exit;
            }

            // Проверяем, что бронь для этого товара
            if ($reservation['sku'] !== $data['sku']) {
                $db->rollBack();
                http_response_code(400);
                echo json_encode(['error' => 'Бронь не соответствует товару']);
                exit;
            }
        }

        // Создаем заказ
        $stmt = $db->prepare("
            INSERT INTO orders (id, sku, status, amount, currency, email, reservation_id)
            VALUES (?, ?, 'created', ?, 'RUB', ?, ?)
        ");

        $stmt->execute([
            $orderId,
            $data['sku'],
            (float)$data['amount'],
            $data['email'] ?? null,
            $reservationId
        ]);

        // Если есть бронь, привязываем её к заказу
        if ($reservationId) {
            $stmt = $db->prepare("
                UPDATE reservations 
                SET order_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$orderId, $reservationId]);
        }

        $db->commit();

        echo json_encode([
            'order_id' => $orderId,
            'status' => 'created',
            'reservation_id' => $reservationId,
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