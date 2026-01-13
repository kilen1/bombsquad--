<?php
// extend_tariff_system.php - скрипт для расширения системы тарифов
include __DIR__ . '../../Base/db.php';

function extendTariffSystem($pdo) {
    $updates = [];
    
    try {
        // 1. Проверяем наличие колонки description
        $check = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'description'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tariffs ADD COLUMN description TEXT");
            $updates[] = "Добавлена колонка description";
        }
        
        // 2. Проверяем наличие колонки mod
        $check = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'mod'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tariffs ADD COLUMN mod VARCHAR(255)");
            $updates[] = "Добавлена колонка mod";
        }
        
        // 3. Проверяем наличие колонки confirmation_button_text
        $check = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'confirmation_button_text'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tariffs ADD COLUMN confirmation_button_text VARCHAR(255) DEFAULT 'Подтвердить покупку'");
            $updates[] = "Добавлена колонка confirmation_button_text";
        }
        
        // 4. Проверяем наличие колонки additional_features
        $check = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'additional_features'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tariffs ADD COLUMN additional_features TEXT");
            $updates[] = "Добавлена колонка additional_features";
        }
        
        // 5. Проверяем наличие колонки features_list
        $check = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'features_list'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE tariffs ADD COLUMN features_list TEXT");
            $updates[] = "Добавлена колонка features_list";
        }
        
        return [
            'success' => true,
            'updates' => $updates
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Запуск обновления
if (php_sapi_name() === 'cli') {
    // CLI режим
    $result = extendTariffSystem($pdo);
    print_r($result);
} else {
    // Web режим
    session_start();
    if (!isset($_SESSION['username'])) {
        die("Доступ запрещен");
    }
    
    $result = extendTariffSystem($pdo);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>