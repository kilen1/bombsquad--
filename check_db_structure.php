<?php
include __DIR__ . '../../Base/db.php';

echo "=== Структура таблицы tariffs ===\n";
try {
    $stmt = $pdo->query("DESCRIBE tariffs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo sprintf("%-15s %-15s %-10s %-10s %-10s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'], 
            $column['Extra']
        );
    }
} catch (Exception $e) {
    echo "Ошибка при получении структуры таблицы tariffs: " . $e->getMessage() . "\n";
}

echo "\n=== Структура таблицы services ===\n";
try {
    $stmt = $pdo->query("DESCRIBE services");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo sprintf("%-20s %-20s %-10s %-10s %-15s %s\n", 
            $column['Field'], 
            $column['Type'], 
            $column['Null'], 
            $column['Key'], 
            $column['Default'], 
            $column['Extra']
        );
    }
} catch (Exception $e) {
    echo "Ошибка при получении структуры таблицы services: " . $e->getMessage() . "\n";
}

echo "\n=== Пример данных из таблицы tariffs ===\n";
try {
    $stmt = $pdo->query("SELECT * FROM tariffs LIMIT 5");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as $row) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Ошибка при получении данных из таблицы tariffs: " . $e->getMessage() . "\n";
}
?>