<?php
// Конфигурация приложения
return [
    'db' => [
        'path' => __DIR__ . '/database/database.sqlite'
    ],
    'providers' => [
        'primary' => [
            'url' => 'http://localhost:8081',
            'error_rate' => 0.2, // 20% ошибок
            'timeout_rate' => 0.1, // 10% таймаутов
            'timeout_seconds' => 3
        ],
        'fallback' => [
            'url' => 'http://localhost:8082',
            'error_rate' => 0.3,
            'timeout_rate' => 0.15,
            'timeout_seconds' => 2
        ]
    ]
];