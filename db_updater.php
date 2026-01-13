<?php
// db_updater.php - скрипт для обновления базы данных
include __DIR__ . '../../Base/db.php';

function updateDatabase($pdo) {
    $updates = [];
    
    try {
        // 1. Проверяем наличие колонки has_panel
        $check = $pdo->query("SHOW COLUMNS FROM services LIKE 'has_panel'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE services ADD COLUMN has_panel TINYINT(1) DEFAULT 0");
            $updates[] = "Добавлена колонка has_panel";
        }
        
        // 2. Проверяем наличие колонки control_panel_id
        $check = $pdo->query("SHOW COLUMNS FROM services LIKE 'control_panel_id'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE services ADD COLUMN control_panel_id INT(11) DEFAULT 3");
            $updates[] = "Добавлена колонка control_panel_id";
        }
        
        // 3. Проверяем таблицу server_action_log
        $check = $pdo->query("SHOW TABLES LIKE 'server_action_log'");
        if ($check->rowCount() == 0) {
            $sql = "CREATE TABLE server_action_log (
                id INT(11) NOT NULL AUTO_INCREMENT,
                service_id VARCHAR(20) NOT NULL,
                action VARCHAR(50) NOT NULL,
                details TEXT,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                KEY idx_service_id (service_id),
                KEY idx_action (action),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            $pdo->exec($sql);
            $updates[] = "Создана таблица server_action_log";
        }
        
        // 4. Обновляем существующие Standard тарифы
        $pdo->exec("
            UPDATE services s
            JOIN tariffs t ON s.tariff_id = t.id
            SET s.has_panel = 1, 
                s.control_panel_id = 3,
                s.setup_status = 'ready'
            WHERE LOWER(TRIM(t.name)) = 'standard'
        ");
        
        $updated = $pdo->rowCount();
        if ($updated > 0) {
            $updates[] = "Обновлено $updated Standard серверов";
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
    $result = updateDatabase($pdo);
    print_r($result);
} else {
    // Web режим
    session_start();
    if (!isset($_SESSION['username'])) {
        die("Доступ запрещен");
    }
    
    $result = updateDatabase($pdo);
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>