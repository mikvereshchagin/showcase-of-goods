<?php
// Инициализация базы данных SQLite
$db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Создание таблиц
$db->exec("
    CREATE TABLE IF NOT EXISTS orders (
        id TEXT PRIMARY KEY,
        sku TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'created',
        amount REAL NOT NULL,
        currency TEXT NOT NULL DEFAULT 'RUB',
        created_at TEXT NOT NULL,
        updated_at TEXT NOT NULL,
        delivery_code TEXT,
        delivery_attempts INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS webhook_events (
        event_id TEXT PRIMARY KEY,
        order_id TEXT NOT NULL,
        status TEXT NOT NULL,
        processed_at TEXT NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id)
    );

    CREATE TABLE IF NOT EXISTS key_pool (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sku TEXT NOT NULL,
        key_code TEXT NOT NULL UNIQUE,
        is_used INTEGER DEFAULT 0,
        used_by_order_id TEXT,
        used_at TEXT,
        FOREIGN KEY (used_by_order_id) REFERENCES orders(id)
    );

    CREATE TABLE IF NOT EXISTS promocodes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL UNIQUE,
        type TEXT NOT NULL,
        value REAL NOT NULL,
        currency TEXT DEFAULT 'RUB',
        max_uses INTEGER NOT NULL,
        current_uses INTEGER DEFAULT 0
    );

    CREATE TABLE IF NOT EXISTS promo_usage (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        promo_id INTEGER NOT NULL,
        order_id TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY (promo_id) REFERENCES promocodes(id),
        FOREIGN KEY (order_id) REFERENCES orders(id),
        UNIQUE(promo_id, order_id)
    );
");