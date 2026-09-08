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

    if (!isset($data['reservation_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Reservation ID is required']);
        exit;
    }

    $reservationId = $data['reservation_id'];

    // Снимаем бронь
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'released', released_at = datetime('now')
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$reservationId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Бронь снята'
        ]);
    } else {
        echo json_encode([
            'status' => 'already_released',
            'message' => 'Бронь уже снята или истекла'
        ]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}