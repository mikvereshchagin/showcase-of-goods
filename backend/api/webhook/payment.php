<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../../database/init.php';

$data = json_decode(file_get_contents('php://input'), true);

// Проверка на дубликат вебхука
$webhookEvents = readData('webhook_events.json');
foreach ($webhookEvents as $event) {
    if ($event['event_id'] === $data['event_id']) {
        echo json_encode(['status' => 'already_processed']);
        exit;
    }
}

// Сохраняем событие
$webhookEvents[] = [
    'event_id' => $data['event_id'],
    'order_id' => $data['order_id'],
    'status' => $data['status'],
    'amount' => $data['amount'] ?? null,
    'currency' => $data['currency'] ?? 'RUB',
    'processed_at' => date('Y-m-d H:i:s')
];
writeData('webhook_events.json', $webhookEvents);

// Обновляем статус заказа
if ($data['status'] === 'paid') {
    $orders = readData('orders.json');
    $orderUpdated = false;

    foreach ($orders as &$order) {
        if ($order['id'] === $data['order_id'] && $order['status'] === 'created') {
            $order['status'] = 'paid';
            $order['updated_at'] = date('Y-m-d H:i:s');
            $orderUpdated = true;
            break;
        }
    }

    if ($orderUpdated) {
        writeData('orders.json', $orders);

        // Запускаем выдачу
        require_once __DIR__ . '/../../providers/delivery.php';
        deliverOrder($data['order_id']);
    }
}

echo json_encode(['status' => 'ok']);