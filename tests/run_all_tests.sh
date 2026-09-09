#!/bin/bash

echo "==================================="
echo "Запуск тестов GameMarket"
echo "==================================="

echo ""
echo "1. Тест параллельного бронирования"
echo "-----------------------------------"
php tests/test_parallel_reserve.php

echo ""
echo "2. Тест истечения брони"
echo "-----------------------------------"
php tests/test_reservation_expire.php

echo ""
echo "3. Тест дубликата intent_id"
echo "-----------------------------------"
php tests/test_duplicate_intent.php

echo ""
echo "4. Тест параллельных вебхуков"
echo "-----------------------------------"
php tests/test_payment_parallel.php

echo ""
echo "==================================="
echo "Все тесты завершены"
echo "==================================="