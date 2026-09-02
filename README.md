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
# Основной сервер (API + фронтенд)
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
│   ├── api/              # REST API эндпоинты
│   │   ├── create_order.php
│   │   ├── order_status.php
│   │   └── webhook/
│   │       └── payment.php
│   ├── database/         # SQLite база и миграции
│   │   ├── init.php      # Инициализация БД
│   │   ├── seed.php      # Заполнение тестовыми данными
│   │   └── database.sqlite # Файл базы данных
│   └── providers/        # Логика выдачи
│       └── delivery.php  # Выдача ключей
├── frontend/
│   ├── css/              # Стили
│   │   └── style.css
│   ├── js/               # JavaScript
│   │   └── main.js
│   ├── images/           # Изображения (опционально)
│   └── index.html        # Главная страница
├── tests/                # Тесты
│   ├── test_parallel_webhooks.php
│   ├── test_duplicate_webhook.php
│   ├── test_double_click.php
│   └── run_all_tests.sh
└── README.md
```

# 🔄 API Endpoints

**Создание заказа**

```
POST /backend/api/create_order.php
Content-Type: application/json

Body: 
{
  "sku": "STEAM-TOPUP-500",
  "amount": 500,
  "email": "user@example.com"
}

Response: 
{
  "order_id": "ord_xxx",
  "status": "created",
  "message": "Order created successfully"
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
  "amount": 500,
  "currency": "RUB",
  "created_at": "2025-01-01T12:00:00Z"
}

Response (успех):
{
  "status": "ok",
  "order_updated": true
}

Response (дубликат):
{
  "status": "already_processed",
  "duplicate": true
}
```

**Статус заказа**

```
GET /backend/api/order_status.php?order_id=ord_xxx

Response:
{
  "id": "ord_xxx",
  "sku": "STEAM-TOPUP-500",
  "status": "delivered",
  "amount": 500,
  "currency": "RUB",
  "delivery_code": "LFXC-TNCS-BPCD",
  "created_at": "2025-01-01 12:00:00",
  "updated_at": "2025-01-01 12:00:01"
}
```

# 🧪 Тестирование гонок

**Запуск всех тестов:**
```bash
./tests/run_all_tests.sh
```
**Или запуск отдельных тестов:**
```bash
# Тест на 50 параллельных вебхуков
php tests/test_parallel_webhooks.php

# Тест на повторный вебхук
php tests/test_duplicate_webhook.php

# Тест на двойной клик
php tests/test_double_click.php
```
**Что проверяют тесты:**
Параллельные вебхуки - 50 одновременных запросов, ключ должен выдаться 1 раз

Повторный вебхук - тот же event_id, должен вернуть "already_processed"

Двойной клик - два вебхука на один заказ, только 1 ключ

# 🔐 Механизм однократной выдачи

Однократная выдача гарантируется через:
1. Атомарные SQL-транзакции - ключ помечается как использованный в рамках одной транзакции с блокировкой записи 
2. Уникальные индексы - на ключи (key_code UNIQUE) и события вебхуков (event_id PRIMARY KEY)
3. Идемпотентные обработчики - повторные запросы с тем же event_id возвращают already_processed 
4. Статусная модель - заказ может перейти в финальное состояние только один раз 
5. Проверка статуса - выдача происходит только из статуса paid, повторная выдача невозможна

**Статусы заказов**

`created` - заказ создан, ожидает оплаты

`paid` - оплата подтверждена, запускается выдача

`delivering` - идет получение кода у поставщика

`delivered` - код выдан и привязан к заказу (финальный)

`payment_failed` - оплата не прошла (финальный)

`out_of_stock` - оплачено, но кода нет в наличии (восстановимый)

`delivery_failed`- оба поставщика не смогли выдать (восстановимый)

# 🔄 Жизненный цикл заказа

```
Основной путь:
created → paid → delivering → delivered

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

`orders` - заказы

`webhook_events` - события вебхуков (идемпотентность)

`key_pool` - пул ключей

# Безопасность

CORS заголовки для API

Защита от SQL-инъекций через PDO prepared statements

Атомарные операции выдачи ключей

Валидация входных данных
