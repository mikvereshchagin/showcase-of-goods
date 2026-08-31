<?php
function deliverOrder($orderId) {
    $orders = readData('orders.json');
    $keyPool = readData('key_pool.json');

    // Находим заказ
    $orderIndex = -1;
    foreach ($orders as $index => $order) {
        if ($order['id'] === $orderId) {
            $orderIndex = $index;
            break;
        }
    }

    if ($orderIndex === -1) {
        return false;
    }

    // Обновляем статус на delivering
    $orders[$orderIndex]['status'] = 'delivering';
    $orders[$orderIndex]['updated_at'] = date('Y-m-d H:i:s');
    writeData('orders.json', $orders);

    // Ищем свободный ключ
    $keyIndex = -1;
    foreach ($keyPool as $index => $key) {
        if (!$key['is_used']) {
            $keyIndex = $index;
            break;
        }
    }

    if ($keyIndex === -1) {
        // Нет ключей - out_of_stock
        $orders = readData('orders.json');
        $orders[$orderIndex]['status'] = 'out_of_stock';
        $orders[$orderIndex]['updated_at'] = date('Y-m-d H:i:s');
        writeData('orders.json', $orders);
        return false;
    }

    // Выдаем ключ (атомарная операция)
    $keyPool[$keyIndex]['is_used'] = true;
    $keyPool[$keyIndex]['used_by_order_id'] = $orderId;
    $keyPool[$keyIndex]['used_at'] = date('Y-m-d H:i:s');
    writeData('key_pool.json', $keyPool);

    // Обновляем заказ
    $orders = readData('orders.json');
    $orders[$orderIndex]['status'] = 'delivered';
    $orders[$orderIndex]['delivery_code'] = $keyPool[$keyIndex]['key_code'];
    $orders[$orderIndex]['updated_at'] = date('Y-m-d H:i:s');
    writeData('orders.json', $orders);

    return true;
}