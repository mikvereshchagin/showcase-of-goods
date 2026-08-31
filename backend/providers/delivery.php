<?php
function deliverOrder($db, $orderId) {
    try {
        // Начинаем транзакцию
        $db->beginTransaction();

        // Обновляем статус на delivering
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'delivering', updated_at = datetime('now')
            WHERE id = ? AND status = 'paid'
        ");
        $stmt->execute([$orderId]);

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
            return false;
        }

        // Помечаем ключ как использованный
        $stmt = $db->prepare("
            UPDATE key_pool 
            SET is_used = 1, used_by_order_id = ?, used_at = datetime('now')
            WHERE id = ? AND is_used = 0
        ");
        $stmt->execute([$orderId, $key['id']]);

        if ($stmt->rowCount() === 0) {
            // Ключ уже был использован другим запросом
            $db->rollBack();
            return false;
        }

        // Обновляем заказ с выданным ключом
        $stmt = $db->prepare("
            UPDATE orders 
            SET status = 'delivered', 
                delivery_code = ?, 
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$key['key_code'], $orderId]);

        // Фиксируем транзакцию
        $db->commit();
        return true;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Delivery error: " . $e->getMessage());
        return false;
    }
}