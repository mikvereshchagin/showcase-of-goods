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
    // Получаем все товары с актуальными ценами и остатками
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
        'timestamp' => time()
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}