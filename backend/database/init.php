<?php
// Инициализация базы данных SQLite
$dbPath = __DIR__ . '/database.sqlite';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Включаем поддержку иностранных ключей
    $db->exec('PRAGMA foreign_keys = ON');
    // Включаем WAL режим для лучшей конкурентности
    $db->exec('PRAGMA journal_mode = WAL');
    // Таймаут для блокировок
    $db->exec('PRAGMA busy_timeout = 5000');

    // Создание таблиц
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id TEXT PRIMARY KEY,
            sku TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'created',
            amount REAL NOT NULL,
            currency TEXT NOT NULL DEFAULT 'RUB',
            email TEXT,
            delivery_code TEXT,
            delivery_attempts INTEGER DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS webhook_events (
            event_id TEXT PRIMARY KEY,
            order_id TEXT NOT NULL,
            status TEXT NOT NULL,
            amount REAL,
            currency TEXT,
            processed_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (order_id) REFERENCES orders(id)
        );

        CREATE TABLE IF NOT EXISTS key_pool (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sku TEXT NOT NULL,
            key_code TEXT NOT NULL UNIQUE,
            is_used INTEGER DEFAULT 0,
            used_by_order_id TEXT,
            used_at TEXT,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (used_by_order_id) REFERENCES orders(id)
        );

        CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
        CREATE INDEX IF NOT EXISTS idx_key_pool_sku ON key_pool(sku);
        CREATE INDEX IF NOT EXISTS idx_key_pool_is_used ON key_pool(is_used);
        CREATE INDEX IF NOT EXISTS idx_webhook_events_order ON webhook_events(order_id);
    ");

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ]);
    exit;
}