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

    if (!isset($data['reservation_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Reservation ID is required']);
        exit;
    }

    $reservationId = $data['reservation_id'];

    // Используем общую функцию: она и бронь снимет, и stock вернёт
    $released = releaseReservation($db, $reservationId, 'released');

    if ($released) {
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