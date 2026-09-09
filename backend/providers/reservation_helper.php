<?php
/**
 * Общие функции для работы с бронью.
 */

/**
 * Освобождает одну бронь и возвращает товар в продажу.
 *
 * @param PDO    $db
 * @param string $reservationId
 * @param string $toStatus 'released' (ручное снятие) или 'expired' (истекла)
 * @return bool true, если бронь была активной и освобождена
 */
function releaseReservation($db, $reservationId, $toStatus = 'released') {
    if (!in_array($toStatus, ['released', 'expired'], true)) {
        $toStatus = 'released';
    }

    $db->beginTransaction();

    try {
        // Блокируем конкретную бронь
        $stmt = $db->prepare("
            SELECT id, sku, status
            FROM reservations
            WHERE id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();

        if (!$reservation || $reservation['status'] !== 'active') {
            $db->rollBack();
            return false;
        }

        // Помечаем бронь
        $stmt = $db->prepare("
            UPDATE reservations
            SET status = ?,
                released_at = datetime('now')
            WHERE id = ?
              AND status = 'active'
        ");
        $stmt->execute([$toStatus, $reservationId]);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            return false;
        }

        // Возвращаем товар в продажу
        $stmt = $db->prepare("
            UPDATE products
            SET stock = stock + 1,
                updated_at = datetime('now')
            WHERE sku = ?
        ");
        $stmt->execute([$reservation['sku']]);

        $db->commit();
        return true;

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("releaseReservation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Снимает все просроченные активные брони.
 * Возвращает количество освобождённых броней.
 *
 * @param PDO $db
 * @return int
 */
function expireOverdueReservations($db) {
    $expiredCount = 0;

    try {
        // Находим все просроченные активные брони
        $stmt = $db->query("
            SELECT id
            FROM reservations
            WHERE status = 'active'
              AND expires_at <= datetime('now')
        ");
        $overdueIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($overdueIds as $reservationId) {
            if (releaseReservation($db, $reservationId, 'expired')) {
                $expiredCount++;
            }
        }
    } catch (Exception $e) {
        error_log("expireOverdueReservations error: " . $e->getMessage());
    }

    return $expiredCount;
}