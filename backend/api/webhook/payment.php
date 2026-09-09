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

require_once __DIR__ . '/../../database/init.php';
require_once __DIR__ . '/../../providers/delivery.php';
require_once __DIR__ . '/../../providers/reservation_helper.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['event_id']) || !isset($data['order_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'event_id, order_id and status are required']);
        exit;
    }

    $eventId = $data['event_id'];
    $orderId = $data['order_id'];
    $status = $data['status'];

    // Начинаем транзакцию
    $db->beginTransaction();

    try {
        // Атомарно фиксируем событие. Повторный event_id просто не вставится.
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO webhook_events (event_id, order_id, status, amount, currency)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $eventId,
            $orderId,
            $status,
            $data['amount'] ?? null,
            $data['currency'] ?? 'RUB'
        ]);

        // Если вставки не произошло — это дубликат
        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            echo json_encode([
                'status' => 'already_processed',
                'duplicate' => true,
                'order_updated' => false
            ]);
            exit;
        }

        // Обновляем статус заказа
        $orderUpdated = false;

        if ($status === 'paid') {
            // Получаем заказ. Если его нет — не падаем, просто фиксируем событие.
            $stmt = $db->prepare("
                SELECT id, sku, status, reservation_id
                FROM orders
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            $orderData = $stmt->fetch();

            if ($orderData && $orderData['status'] === 'created') {
                // Проверяем бронь, если она есть
                if ($orderData['reservation_id']) {
                    $stmt = $db->prepare("
                        SELECT status, expires_at
                        FROM reservations
                        WHERE id = ?
                    ");
                    $stmt->execute([$orderData['reservation_id']]);
                    $reservation = $stmt->fetch();

                    if (!$reservation || $reservation['status'] !== 'active' || strtotime($reservation['expires_at']) < time()) {
                        // Бронь недействительна или истекла
                        $db->rollBack();

                        // Освобождаем просроченную бронь
                        if ($reservation && $reservation['status'] === 'active') {
                            releaseReservation($db, $orderData['reservation_id'], 'expired');
                        }

                        echo json_encode([
                            'status' => 'ok',
                            'order_updated' => false,
                            'reason' => 'reservation_invalid'
                        ]);
                        exit;
                    }
                }

                // Переводим заказ в paid
                $stmt = $db->prepare("
                    UPDATE orders
                    SET status = 'paid', updated_at = datetime('now')
                    WHERE id = ? AND status = 'created'
                ");
                $stmt->execute([$orderId]);

                $orderUpdated = $stmt->rowCount() > 0;
            }
        }

        // Фиксируем транзакцию
        $db->commit();

        // Если заказ перешёл в paid — запускаем выдачу
        if ($status === 'paid' && $orderUpdated) {
            $deliveryResult = deliverOrder($db, $orderId);
            error_log("Delivery result for $orderId: " . ($deliveryResult ? 'success' : 'failed'));
        }

        echo json_encode([
            'status' => 'ok',
            'order_updated' => $orderUpdated
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