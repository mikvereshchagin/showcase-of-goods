<?php
require_once __DIR__ . '/init.php';

try {
    // Полный список ключей из ТЗ
    $keys = [
        "LFXC-TNCS-BPCD", "P3EI-W8UO-9B4K", "FEL3-GUXN-TCCH", "YPLV-QK2Z-IUS5",
        "0K9E-P1FR-BY1U", "5LZV-UQ48-RXCZ", "X93K-NYAQ-GEC1", "EIO5-CQT5-35KO",
        "M58F-GIIR-VJAP", "NU8Y-SWYB-6252", "OODW-CCHF-MBAF", "DNA5-WFJM-NE49",
        "QRDD-MJ3F-A8TF", "TAT9-5ZJN-G1T2", "LI39-4330-ISMB", "BKJY-8Q79-8NHI",
        "HHW6-4RX2-DX62", "1RG2-L28O-O80G", "EF63-F39X-MTEA", "8XS7-P53H-JKIV",
        "JPE6-MQV6-P7ST", "SAPG-A2GR-0ULS", "T2DU-IJ1S-U16P", "WSSY-QTR7-Z57J",
        "U74E-EPCI-CY26", "FZXF-58H8-OR93", "FPSM-HLZA-TPAL", "WSC9-28DJ-B2JE",
        "P63J-F7UZ-DCYP", "C7W2-D4C5-QMT7", "JESI-DFBH-LK1K", "SGMA-JA0T-GR7D",
        "3PR4-OSY9-M3ZW", "OMBE-C0JF-D45Y", "KIKQ-FQJ8-9TI8", "LMAN-RSHS-AJDO",
        "BAKI-VT1X-Z5OL", "9F0X-B46W-03FS", "S423-V6YY-IBEM", "D4UW-WYRA-20ST",
        "XC0J-CJ0H-09RN", "RY1W-XCFJ-0KUA", "CJYY-YKSQ-QE6H", "97AQ-38QJ-H8HU",
        "FS8E-3S5Z-I6RA", "ARQK-FML4-A14E", "7Z6K-NO9V-MPJB", "D4K7-IJSG-N853",
        "W67T-ZB0Q-1XKB", "7EQM-K09J-XKUO"
    ];

    echo "Заполняем пул ключей...\n";
    $insertedKeys = 0;

    $stmt = $db->prepare("INSERT OR IGNORE INTO key_pool (sku, key_code) VALUES (?, ?)");

    foreach ($keys as $key) {
        $stmt->execute(['STEAM-TOPUP-500', $key]);
        if ($stmt->rowCount() > 0) {
            $insertedKeys++;
        }
    }
    echo "Добавлено ключей: $insertedKeys\n";

    // Заполнение промокодов
    echo "\nЗаполняем промокоды...\n";
    $promos = [
        ['WELCOME10', 'percent', 10, 'RUB', 100],
        ['GG500', 'amount', 500, 'RUB', 20],
        ['LIMIT3', 'percent', 25, 'RUB', 3],
        ['ONCEONLY', 'percent', 50, 'RUB', 1]
    ];

    $insertedPromos = 0;
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO promocodes (code, type, value, currency, max_uses) 
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($promos as $promo) {
        $stmt->execute($promo);
        if ($stmt->rowCount() > 0) {
            $insertedPromos++;
        }
    }
    echo "Добавлено промокодов: $insertedPromos\n";

    // Проверка
    echo "\n=== Проверка базы данных ===\n";
    $keyCount = $db->query("SELECT COUNT(*) FROM key_pool")->fetchColumn();
    $promoCount = $db->query("SELECT COUNT(*) FROM promocodes")->fetchColumn();
    $orderCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    echo "Всего ключей в пуле: $keyCount\n";
    echo "Всего промокодов: $promoCount\n";
    echo "Всего заказов: $orderCount\n";

    echo "\nБаза данных успешно заполнена!\n";

} catch (PDOException $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}