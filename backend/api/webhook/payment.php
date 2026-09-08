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

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['event_id']) || !isset($data['order_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'event_id, order_id and status are required']);
        exit;
    }

    // Начинаем транзакцию
    $db->beginTransaction();

    try {
        // Проверка на дубликат вебхука
        $stmt = $db->prepare("SELECT event_id FROM webhook_events WHERE event_id = ?");
        $stmt->execute([$data['event_id']]);

        if ($stmt->fetch()) {
            $db->rollBack();
            echo json_encode(['status' => 'already_processed', 'duplicate' => true]);
            exit;
        }

        // Сохраняем событие
        $stmt = $db->prepare("
            INSERT INTO webhook_events (event_id, order_id, status, amount, currency)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['event_id'],
            $data['order_id'],
            $data['status'],
            $data['amount'] ?? null,
            $data['currency'] ?? 'RUB'
        ]);

        // Обновляем статус заказа только если он created
        $orderUpdated = false;
        $orderData = null;

        if ($data['status'] === 'paid') {
            // Получаем информацию о заказе
            $stmt = $db->prepare("
                SELECT id, sku, status, reservation_id
                FROM orders 
                WHERE id = ?
            ");
            $stmt->execute([$data['order_id']]);
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
                        // Бронь недействительна
                        if ($reservation && $reservation['status'] === 'active') {
                            $stmt = $db->prepare("
                                UPDATE reservations 
                                SET status = 'expired', released_at = datetime('now')
                                WHERE id = ?
                            ");
                            $stmt->execute([$orderData['reservation_id']]);
                        }

                        $db->rollBack();
                        http_response_code(409);
                        echo json_encode(['error' => 'Бронь истекла или недействительна']);
                        exit;
                    }
                }

                // Обновляем статус заказа
                $stmt = $db->prepare("
                    UPDATE orders 
                    SET status = 'paid', updated_at = datetime('now')
                    WHERE id = ? AND status = 'created'
                ");
                $stmt->execute([$data['order_id']]);

                $orderUpdated = $stmt->rowCount() > 0;
            }
        }

        // Фиксируем транзакцию
        $db->commit();

        // Если заказ был обновлен, запускаем выдачу
        if ($data['status'] === 'paid' && $orderUpdated) {
            error_log("Starting delivery for order: " . $data['order_id']);
            $deliveryResult = deliverOrder($db, $data['order_id']);
            error_log("Delivery result: " . ($deliveryResult ? 'success' : 'failed'));
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