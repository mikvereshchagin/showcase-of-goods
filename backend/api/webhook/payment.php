<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../database/init.php';

$data = json_decode(file_get_contents('php://input'), true);

// Проверка на дубликат вебхука
$stmt = $db->prepare("SELECT * FROM webhook_events WHERE event_id = ?");
$stmt->execute([$data['event_id']]);
if ($stmt->fetch()) {
    echo json_encode(['status' => 'already_processed']);
    exit;
}

// Сохраняем событие
$stmt = $db->prepare("
    INSERT INTO webhook_events (event_id, order_id, status, processed_at)
    VALUES (?, ?, ?, datetime('now'))
");
$stmt->execute([$data['event_id'], $data['order_id'], $data['status']]);

// Обновляем статус заказа
if ($data['status'] === 'paid') {
    $stmt = $db->prepare("
        UPDATE orders SET status = 'paid', updated_at = datetime('now')
        WHERE id = ? AND status = 'created'
    ");
    $stmt->execute([$data['order_id']]);

    // Запускаем выдачу
    require_once __DIR__ . '/../../providers/delivery.php';
    deliverOrder($db, $data['order_id']);
}

echo json_encode(['status' => 'ok']);