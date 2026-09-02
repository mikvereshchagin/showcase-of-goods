#!/bin/bash

echo "==================================="
echo "Запуск тестов GameMarket"
echo "==================================="

echo ""
echo "1. Тест параллельных вебхуков"
echo "-----------------------------------"
php tests/test_parallel_webhooks.php

echo ""
echo "2. Тест повторного вебхука"
echo "-----------------------------------"
php tests/test_duplicate_webhook.php

echo ""
echo "3. Тест двойного клика"
echo "-----------------------------------"
php tests/test_double_click.php

echo ""
echo "==================================="
echo "Все тесты завершены"
echo "==================================="