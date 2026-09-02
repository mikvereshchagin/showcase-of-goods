<?php
function deliverOrder($db, $orderId) {
    try {
        // Проверяем текущий статус заказа
        $stmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            error_log("Order not found: $orderId");
            return false;
        }

        error_log("Current order status: {$order['status']}");

        // Если заказ не в статусе paid, не выдаем
        if ($order['status'] !== 'paid') {
            error_log("Order not in paid status: {$order['status']}");
            return false;
        }

        // Начинаем транзакцию
        $db->beginTransaction();

        // Обновляем статус на delivering
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'delivering', updated_at = datetime('now')
            WHERE id = ? AND status = 'paid'
        ");
        $stmt->execute([$orderId]);

        if ($stmt->rowCount() === 0) {
            // Другой процесс уже обновил статус
            $db->rollBack();
            error_log("Failed to update status to delivering");
            return false;
        }

        // Ищем свободный ключ
        $stmt = $db->prepare("
            SELECT id, key_code 
            FROM key_pool 
            WHERE is_used = 0 
            LIMIT 1
        ");
        $stmt->execute();
        $key = $stmt->fetch();

        if (!$key) {
            // Нет ключей - out_of_stock
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'out_of_stock', updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            $db->commit();
            error_log("No keys available");
            return false;
        }

        // Помечаем ключ как использованный
        $stmt = $db->prepare("
            UPDATE key_pool 
            SET is_used = 1, 
                used_by_order_id = ?, 
                used_at = datetime('now')
            WHERE id = ? AND is_used = 0
        ");
        $stmt->execute([$orderId, $key['id']]);

        if ($stmt->rowCount() === 0) {
            // Ключ уже был использован
            $db->rollBack();
            error_log("Key already used");
            return false;
        }

        // Обновляем заказ с выданным ключом
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'delivered', 
                delivery_code = ?, 
                delivery_attempts = delivery_attempts + 1,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$key['key_code'], $orderId]);

        // Фиксируем транзакцию
        $db->commit();

        error_log("Key delivered: {$key['key_code']}");
        return true;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Delivery error: " . $e->getMessage());
        return false;
    }
}