<?php
function deliverOrder($db, $orderId) {
    // Обновляем статус
    $stmt = $db->prepare("
        UPDATE orders SET status = 'delivering', updated_at = datetime('now')
        WHERE id = ? AND status = 'paid'
    ");
    $stmt->execute([$orderId]);

    // Атомарная выдача ключа
    $db->beginTransaction();
    try {
        $stmt = $db->prepare("
            UPDATE key_pool 
            SET is_used = 1, used_by_order_id = ?, used_at = datetime('now')
            WHERE id = (
                SELECT id FROM key_pool 
                WHERE is_used = 0 
                LIMIT 1
            )
            RETURNING key_code
        ");
        $stmt->execute([$orderId]);
        $key = $stmt->fetchColumn();

        if ($key) {
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'delivered', delivery_code = ?, updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$key, $orderId]);
        } else {
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'out_of_stock', updated_at = datetime('now')
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}