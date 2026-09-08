<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../database/init.php';

try {
    $reservationId = $_GET['reservation_id'] ?? '';

    if (!$reservationId) {
        http_response_code(400);
        echo json_encode(['error' => 'Reservation ID is required']);
        exit;
    }

    // Получаем информацию о брони
    $stmt = $db->prepare("
        SELECT 
            r.id,
            r.sku,
            r.status,
            r.expires_at,
            r.created_at,
            p.name,
            p.price,
            p.currency
        FROM reservations r
        JOIN products p ON r.sku = p.sku
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();

    if (!$reservation) {
        http_response_code(404);
        echo json_encode(['error' => 'Reservation not found']);
        exit;
    }

    $isExpired = strtotime($reservation['expires_at']) < time();
    $isActive = $reservation['status'] === 'active' && !$isExpired;

    if ($isExpired && $reservation['status'] === 'active') {
        $stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'expired', released_at = datetime('now')
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$reservationId]);
        $reservation['status'] = 'expired';
    }

    echo json_encode([
        'status' => 'ok',
        'reservation' => [
            'id' => $reservation['id'],
            'sku' => $reservation['sku'],
            'status' => $reservation['status'],
            'is_active' => $isActive,
            'expires_at' => $reservation['expires_at'],
            'time_remaining' => max(0, strtotime($reservation['expires_at']) - time()),
            'product' => [
                'name' => $reservation['name'],
                'price' => $reservation['price'],
                'currency' => $reservation['currency']
            ]
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}