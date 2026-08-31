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

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['event_id']) || !isset($data['order_id']) || !isset($data['status'])) {
        http_response_code(400);
        echo json_encode(['error' => 'event_id, order_id and status are required']);
        exit;
    }

    // Проверка на дубликат вебхука
    $stmt = $db->prepare("SELECT event_id FROM webhook_events WHERE event_id = ?");
    $stmt->execute([$data['event_id']]);

    if ($stmt->fetch()) {
        echo json_encode(['status' => 'already_processed']);
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

    // Обновляем статус заказа
    if ($data['status'] === 'paid') {
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'paid', updated_at = datetime('now')
            WHERE id = ? AND status = 'created'
        ");
        $stmt->execute([$data['order_id']]);

        if ($stmt->rowCount() > 0) {
            // Запускаем выдачу
            require_once __DIR__ . '/../../providers/delivery.php';
            deliverOrder($db, $data['order_id']);
        }
    }

    echo json_encode(['status' => 'ok']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}