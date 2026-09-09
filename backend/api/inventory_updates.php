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
require_once __DIR__ . '/../providers/reservation_helper.php';

try {
    $lastUpdate = isset($_GET['last_update']) ? (int)$_GET['last_update'] : 0;
    $timeout = 3; // Максимальное время ожидания в секундах
    $startTime = time();

    // Снимаем просроченные брони
    expireOverdueReservations($db);

    while (time() - $startTime < $timeout) {
        // Получаем максимальное время обновления товаров
        $stmt = $db->query("
            SELECT MAX(strftime('%s', updated_at)) as max_update
            FROM products
        ");
        $maxUpdate = (int)$stmt->fetchColumn();

        // Если есть обновления после last_update
        if ($maxUpdate > $lastUpdate) {
            // Получаем все товары
            $stmt = $db->query("
                SELECT 
                    p.sku,
                    p.name,
                    p.type,
                    p.price,
                    p.old_price,
                    p.currency,
                    p.stock,
                    p.image,
                    p.updated_at
                FROM products p
                ORDER BY p.sku
            ");

            $products = $stmt->fetchAll();

            echo json_encode([
                'status' => 'ok',
                'products' => $products,
                'timestamp' => time(),
                'has_updates' => true
            ]);
            exit;
        }

        // Ждем 1 секунду перед следующей проверкой
        sleep(1);
    }

    // Если обновлений не было, возвращаем пустой ответ
    echo json_encode([
        'status' => 'ok',
        'products' => [],
        'timestamp' => time(),
        'has_updates' => false
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}