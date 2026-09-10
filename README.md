# GameMarket - Магазин цифровых товаров

Тестовое задание для позиции Fullstack разработчика.

## 🚀 Быстрый старт

### Требования
- PHP 7.4+ или 8.x с расширениями:
    - pdo_sqlite
    - curl (для тестов)
- SQLite3
- Браузер (Chrome, Firefox, Safari)

### Установка

1. Клонируйте репозиторий:
```bash
git clone git@github.com:mikvereshchagin/showcase-of-goods.git
cd showcase-of-goods
```
2. Инициализируйте базу данных:
```bash
php backend/database/init.php
```
3. Заполните базу тестовыми данными:
```bash
php backend/database/seed.php
```
4. Запустите сервер:
```bash
php -S localhost:8000 -t .
```
5. Откройте фронтенд:
```bash
http://localhost:8000/frontend/index.html
```

# 📋 Структура проекта
```
showcase-of-goods/
├── backend/
│   ├── api/                  # REST API эндпоинты
│   │   ├── products.php      # Список товаров
│   │   ├── reserve.php       # Бронирование товара
│   │   ├── check_reservation.php  # Проверка брони
│   │   ├── release_reservation.php # Снятие брони
│   │   ├── inventory_updates.php   # Long polling обновлений
│   │   ├── create_order.php  # Создание заказа
│   │   ├── order_status.php  # Статус заказа
│   │   ├── webhook/
│   │   │   └── payment.php   # Вебхук оплаты
│   │   └── cron/
│   │       └── expire.php    # Снятие просроченных броней
│   ├── database/             # SQLite база и миграции
│   │   ├── init.php          # Инициализация БД
│   │   ├── seed.php          # Заполнение тестовыми данными
│   │   └── database.sqlite   # Файл базы данных
│   └── providers/            # Логика
│       ├── delivery.php      # Выдача ключей
│       └── reservation_helper.php # Управление бронью
├── frontend/
│   ├── css/                  # Стили
│   │   └── style.css
│   ├── js/                   # JavaScript
│   │   └── main.js
│   ├── images/               # Изображения
│   └── index.html            # Главная страница
├── tests/                    # Тесты
│   ├── test_parallel_reserve.php
│   ├── test_reservation_expire.php
│   ├── test_duplicate_intent.php
│   ├── test_payment_parallel.php
│   └── run_all_tests.sh
└── README.md
```

# 🔄 API Endpoints

**Список товаров**

```
GET /backend/api/products.php

Response:
{
  "status": "ok",
  "products": [
    {
      "sku": "KEY-CS2-PRIME",
      "name": "CS2 Prime Status ключ",
      "type": "key",
      "price": 1290,
      "old_price": 1500,
      "currency": "RUB",
      "stock": 5
    }
  ],
  "timestamp": 1700000000
}
```

**Бронирование товара**

```
POST /backend/api/reserve.php
Content-Type: application/json

Body:
{
  "sku": "KEY-CS2-PRIME"
}

Response (успех):
{
  "status": "ok",
  "reservation_id": "res_xxx",
  "expires_at": "2026-09-09 20:25:39",
  "product": {
    "sku": "KEY-CS2-PRIME",
    "name": "CS2 Prime Status ключ",
    "price": 1290,
    "available_stock": 4
  }
}

Response (нет в наличии):
HTTP 409
{
  "error": "out_of_stock",
  "message": "Товар только что раскупили"
}
```

**Проверка брони**

```
GET /backend/api/check_reservation.php?reservation_id=res_xxx

Response:
{
  "status": "ok",
  "reservation": {
    "id": "res_xxx",
    "status": "active",
    "is_active": true,
    "expires_at": "2026-09-09 20:25:39",
    "time_remaining": 280
  }
}
```

**Снятие брони**

```
POST /backend/api/release_reservation.php
Content-Type: application/json

Body:
{
  "reservation_id": "res_xxx"
}
```

**Создание заказа**

```
POST /backend/api/create_order.php
Content-Type: application/json

Body:
{
  "sku": "KEY-CS2-PRIME",
  "reservation_id": "res_xxx",
  "intent_id": "intent_xxx",
  "email": "user@example.com"
}

Response:
{
  "order_id": "ord_xxx",
  "status": "created",
  "reservation_id": "res_xxx",
  "amount": 1290,
  "currency": "RUB"
}
```

**Вебхук оплаты**

```
POST /backend/api/webhook/payment.php
Content-Type: application/json

Body:
{
  "event_id": "evt_unique",
  "order_id": "ord_xxx",
  "status": "paid",
  "currency": "RUB"
}

Response (успех):
{
  "status": "ok",
  "order_updated": true
}

Response (дубликат):
{
  "status": "already_processed",
  "duplicate": true,
  "order_updated": false
}
```

**Статус заказа**

```
GET /backend/api/order_status.php?order_id=ord_xxx

Response:
{
"id": "ord_xxx",
"sku": "KEY-CS2-PRIME",
"status": "delivered",
"amount": 1290,
"currency": "RUB",
"delivery_code": "LFXC-TNCS-BPCD",
"created_at": "2026-09-09 20:30:00",
"updated_at": "2026-09-09 20:30:01"
}
```

**Снятие просроченных броней**

```
GET /backend/api/cron/expire.php

Response:
{
  "status": "ok",
  "expired_count": 1
}
```

**Long polling обновлений**

```
GET /backend/api/inventory_updates.php?last_update=1700000000

Response (есть обновления):
{
"status": "ok",
"products": [
{
"sku": "KEY-CS2-PRIME",
"name": "CS2 Prime Status ключ",
"type": "key",
"price": 1290,
"old_price": 1500,
"currency": "RUB",
"stock": 5
}
],
"timestamp": 1700000005,
"has_updates": true
}

Response (нет обновлений):
{
"status": "ok",
"products": [],
"timestamp": 1700000005,
"has_updates": false
}
```

# 🧪 Тестирование

**Запуск всех тестов:**
```bash
./tests/run_all_tests.sh
```
**Или запуск отдельных тестов:**
```bash
# Тест параллельного бронирования (50 запросов на 1 единицу)
php tests/test_parallel_reserve.php

# Тест истечения брони
php tests/test_reservation_expire.php

# Тест дубликата intent_id
php tests/test_duplicate_intent.php

# Тест параллельных вебхуков (50 запросов на 1 заказ)
php tests/test_payment_parallel.php
```
**Что проверяют тесты:**

Параллельное бронирование — 50 одновременных запросов на товар с stock=1, только 1 победит

Истечение брони — просроченная бронь возвращает товар в продажу

Дубликат intent_id — повторный запрос создания заказа не создаёт дубль

Параллельные вебхуки — 50 одинаковых вебхуков дают ровно 1 ключ

# 🔐 Механизм защиты от гонок

**Атомарное бронирование**

Бронь = UPDATE products SET stock = stock - 1 WHERE sku = ? AND stock >= 1.
Кто первый выполнил UPDATE — тот и победил. Параллельные запросы блокируются на уровне SQLite.

**Идемпотентность**

webhook_events.event_id — PRIMARY KEY, повторный вебхук возвращает already_processed

orders.intent_id — UNIQUE, повторное создание заказа возвращает существующий

Кнопки блокируются на время запроса (защита от двойного клика)

**Бронь с таймером**

Бронь активна 5 минут

По истечении — товар возвращается в продажу

Sweep-механизм: cron/expire.php + ленивый вызов при бронировании

**Восстановление после F5**

order_id сохраняется в localStorage

При загрузке страницы статус заказа восстанавливается

**Статусы заказов**

`created` - заказ создан, ожидает оплаты

`paid` - оплата подтверждена, запускается выдача

`delivering` - идет получение кода у поставщика

`delivered` - код выдан и привязан к заказу (финальный)

`payment_failed` - оплата не прошла (финальный)

`out_of_stock` - оплачено, но кода нет в наличии (восстановимый)

`delivery_failed` - оба поставщика не смогли выдать (восстановимый)

# 🔄 Жизненный цикл заказа

```
Основной путь:
бронь → created → paid → delivering → delivered

Ветки сбоев:
created → payment_failed
paid → delivering → out_of_stock → (после пополнения) → delivered
paid → delivering → delivery_failed → (ручная выдача) → delivered
```

# 🛠 Технические детали

# База данных

SQLite (файл `backend/database/database.sqlite`)

WAL режим для лучшей конкурентности

Таймаут блокировок: 5 секунд

# Таблицы

`products` - товары (цена, остаток)

`orders` - заказы

`reservations` - брони

`webhook_events` - события вебхуков (идемпотентность)

`key_pool` - пул ключей

# Безопасность

CORS заголовки для API

Защита от SQL-инъекций через PDO prepared statements

Серверная цена (клиент не может подменить сумму)

Валидация входных данных
