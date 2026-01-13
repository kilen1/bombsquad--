<?php
session_start();
include __DIR__ . '../../Base/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

// Получаем действие из GET или POST или JSON тела
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Если действие не найдено в GET/POST, пробуем прочитать JSON тело
if (empty($action)) {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
}

$username = $_SESSION['username'];

// Основной роутер действий
switch ($action) {
    case 'get_available_vds':
        getAvailableVds();
        break;
        
    case 'check_vds_status':
        checkVdsStatus();
        break;
        
    case 'assign_vds':
        assignVds();
        break;
        
    case 'get_assignment_status':
        getAssignmentStatus();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие: ' . $action]);
        break;
}

// Функция 1: Получение списка доступных VDS
function getAvailableVds() {
    global $pdo, $username;
    
    $service_id = $_GET['service_id'] ?? '';
    
    // Проверяем доступ пользователя к серверу
    $stmt = $pdo->prepare("
        SELECT s.*, t.name as tariff_name
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN tariffs t ON s.tariff_id = t.id
        WHERE s.service_id = ? AND u.username = ?
    ");
    $stmt->execute([$service_id, $username]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
        exit();
    }
    
    // Проверяем, что сервер еще не имеет VDS
    if ($service['vds_id']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Сервер уже привязан к VDS',
            'current_vds' => $service['vds_id']
        ]);
        exit();
    }
    
    // Получаем список доступных VDS с информацией о занятости
    $stmt = $pdo->prepare("
    SELECT 
        v.vds_id,
        v.ip,
        v.username,
        v.status,
        v.created_at,
        v.slots_used,
        v.max_slots,
        (v.max_slots - COALESCE(v.slots_used, 0)) as free_slots,
        COUNT(s.id) as active_services,
        
        -- Проверка занятости по сокетам
        CASE 
            WHEN v.slots_used IS NULL THEN 'empty'
            WHEN v.slots_used < v.max_slots THEN 'has_free_slots'
            ELSE 'full'
        END as slot_status,
        
        -- Информация о загрузке
        ROUND((COALESCE(v.slots_used, 0) * 100.0 / v.max_slots), 1) as load_percentage,
        
        -- Тип VDS (для фильтрации)
        CASE 
            WHEN v.ip LIKE '185.%' THEN 'premium'
            WHEN v.ip LIKE '192.%' THEN 'standard'
            ELSE 'other'
        END as vds_type,
        
        -- Геолокация (из IP)
        CASE 
            WHEN v.ip LIKE '185.%' THEN 'Германия'
            WHEN v.ip LIKE '192.%' THEN 'Россия'
            ELSE 'Неизвестно'
        END as location
        
    FROM vds v
    LEFT JOIN services s ON v.vds_id = s.vds_id AND s.status = 'active'
    WHERE v.status = 'active' 
        AND v.is_slot = 1
        AND (v.max_slots - COALESCE(v.slots_used, 0)) > 0  -- ИЗМЕНИТЬ: > 0 вместо >= 0
        AND v.vds_id NOT IN (
            -- Исключаем VDS, которые уже обрабатываются
            SELECT vds_id FROM server_vds_assignments 
            WHERE status = 'pending' 
            AND TIMESTAMPDIFF(MINUTE, assigned_at, NOW()) < 10
        )
    GROUP BY v.id
    ORDER BY 
        free_slots DESC,
        load_percentage ASC,
        v.created_at DESC
");
    
    $stmt->execute();
    $available_vds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Для каждой VDS проверяем доступность через тестовые соединения
    foreach ($available_vds as &$vds) {
        // Проверяем основные порты
        $vds['port_checks'] = checkVdsPorts($vds['ip']);
        
        // Определяем общий статус
        $vds['overall_status'] = 'good';
        if ($vds['port_checks']['ssh'] === false) $vds['overall_status'] = 'warning';
        if ($vds['port_checks']['http'] === false && $vds['port_checks']['https'] === false) {
            $vds['overall_status'] = 'critical';
        }
        
        // Добавляем иконку статуса
        $vds['status_icon'] = getStatusIcon($vds['overall_status']);
        
        // Генерируем рекомендацию
        $vds['recommendation'] = getRecommendation($vds);
    }
    
    echo json_encode([
        'success' => true,
        'service_id' => $service_id,
        'service_info' => [
            'name' => $service['name'],
            'game' => $service['game'],
            'tariff' => $service['tariff_name']
        ],
        'available_vds' => $available_vds,
        'count' => count($available_vds),
        'filters' => [
            'by_location' => array_unique(array_column($available_vds, 'location')),
            'by_type' => array_unique(array_column($available_vds, 'vds_type')),
            'by_status' => ['good', 'warning', 'critical']
        ]
    ]);
}

// Функция 2: Проверка статуса конкретной VDS
function checkVdsStatus() {
    global $pdo, $username;
    
    $vds_id = $_POST['vds_id'] ?? '';
    $service_id = $_POST['service_id'] ?? '';
    
    // Проверяем доступ пользователя
    $stmt = $pdo->prepare("
        SELECT s.* FROM services s
        JOIN users u ON s.user_id = u.id
        WHERE s.service_id = ? AND u.username = ?
    ");
    $stmt->execute([$service_id, $username]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
        exit();
    }
    
    // Получаем информацию о VDS
    $stmt = $pdo->prepare("
        SELECT 
            v.*,
            (SELECT COUNT(*) FROM services WHERE vds_id = v.vds_id AND service_id != ?) as used_by_others,
            v.max_slots,
            (v.max_slots - COALESCE(v.slots_used, 0)) as free_slots
        FROM vds v
        WHERE v.vds_id = ? AND v.status = 'active'
    ");
    $stmt->execute([$service_id, $vds_id]);
    $vds = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$vds) {
        echo json_encode(['success' => false, 'message' => 'VDS не найдена или неактивна']);
        exit();
    }
    
    // Проверяем свободные слоты
    if ($vds['free_slots'] <= 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'На этой VDS нет свободных слотов',
            'slots_info' => [
                'used' => $vds['slots_used'],
                'max' => $vds['max_slots'],
                'free' => $vds['free_slots']
            ]
        ]);
        exit();
    }
    
    // Выполняем расширенную проверку VDS
    $detailed_check = performDetailedVdsCheck($vds['ip']);
    
    echo json_encode([
        'success' => true,
        'vds' => [
            'id' => $vds['vds_id'],
            'ip' => $vds['ip'],
            'status' => $vds['status'],
            'slots' => [
                'total' => $vds['max_slots'],
                'used' => $vds['slots_used'],
                'free' => $vds['free_slots'],
                'used_by_others' => $vds['used_by_others']
            ],
            'detailed_check' => $detailed_check,
            'recommendation_score' => calculateRecommendationScore($detailed_check, $vds)
        ],
        'service_info' => [
            'id' => $service_id,
            'name' => $service['name'],
            'game' => $service['game']
        ]
    ]);
}

// Функция 3: Присвоение VDS серверу
function assignVds() {
    global $pdo, $username;
    
    // Пытаемся получить данные из JSON тела
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Используем данные из JSON или из POST
    $vds_id = $input['vds_id'] ?? $_POST['vds_id'] ?? '';
    $service_id = $input['service_id'] ?? $_POST['service_id'] ?? '';
    $confirm = $input['confirm'] ?? $_POST['confirm'] ?? false;
    
    // Проверяем доступ пользователя
    $stmt = $pdo->prepare("
        SELECT s.*, u.username 
        FROM services s
        JOIN users u ON s.user_id = u.id
        WHERE s.service_id = ? AND u.username = ?
    ");
    $stmt->execute([$service_id, $username]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
        exit();
    }
    
    // Проверяем, что сервер еще не имеет VDS
    if ($service['vds_id']) {
        echo json_encode(['success' => false, 'message' => 'Сервер уже привязан к VDS']);
        exit();
    }
    
    // Если нужно подтверждение (проверка перед присвоением)
    if (!$confirm) {
        // Просто возвращаем информацию для подтверждения
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   (SELECT COUNT(*) FROM services WHERE vds_id = v.vds_id) as current_usage,
                   v.max_slots
            FROM vds v
            WHERE v.vds_id = ? AND v.status = 'active'
        ");
        $stmt->execute([$vds_id]);
        $vds = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vds) {
            echo json_encode(['success' => false, 'message' => 'VDS не найдена']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'requires_confirmation' => true,
            'confirmation_data' => [
                'vds' => [
                    'id' => $vds['vds_id'],
                    'ip' => $vds['ip'],
                    'current_usage' => $vds['current_usage'],
                    'max_slots' => $vds['max_slots'],
                    'free_slots' => $vds['max_slots'] - $vds['current_usage']
                ],
                'service' => [
                    'id' => $service_id,
                    'name' => $service['name']
                ],
                'estimated_time' => '5-10 минут',
                'warning' => 'После присвоения VDS сервер будет перезапущен'
            ]
        ]);
        exit();
    }
    
    // Если подтверждение получено - выполняем присвоение
    $pdo->beginTransaction();
    
    try {
        // 1. Проверяем, что VDS все еще доступна
        $stmt = $pdo->prepare("
            SELECT v.*, 
                   (SELECT COUNT(*) FROM services WHERE vds_id = v.vds_id) as current_usage,
                   v.max_slots
            FROM vds v
            WHERE v.vds_id = ? AND v.status = 'active'
            FOR UPDATE
        ");
        $stmt->execute([$vds_id]);
        $vds = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vds) {
            throw new Exception('VDS не найдена или неактивна');
        }
        
        if ($vds['current_usage'] >= $vds['max_slots']) {
            throw new Exception('На VDS закончились свободные слоты');
        }
        
        // 2. Создаем запись о назначении
        $stmt = $pdo->prepare("
            INSERT INTO server_vds_assignments 
            (service_id, vds_id, assigned_at, status, user_id)
            VALUES (?, ?, NOW(), 'pending', 
                    (SELECT id FROM users WHERE username = ?))
        ");
        $stmt->execute([$service_id, $vds_id, $username]);
        $assignment_id = $pdo->lastInsertId();
        
        // 3. Обновляем сервис
$stmt = $pdo->prepare("
    UPDATE services 
    SET vds_id = ?, 
        setup_status = 'ready',  
        has_panel = 1,           
        updated_at = NOW()
    WHERE service_id = ?
");
$stmt->execute([$vds_id, $service_id]);
        
        // 4. Обновляем счетчик слотов VDS
$new_slots_used = $vds['slots_used'] + 1;
$stmt = $pdo->prepare("
    UPDATE vds 
    SET slots_used = ?, 
        updated_at = NOW()
    WHERE vds_id = ?
");
        $stmt->execute([$new_slots_used, $vds_id]);
        
        // 5. Создаем задание для фоновой обработки
        $stmt = $pdo->prepare("
            INSERT INTO vds_assignment_queue 
            (assignment_id, service_id, vds_id, action, status, created_at)
            VALUES (?, ?, ?, 'configure_server', 'pending', NOW())
        ");
        $stmt->execute([$assignment_id, $service_id, $vds_id]);
        
        // 6. Логируем действие
        $stmt = $pdo->prepare("
            INSERT INTO vds_assignment_logs 
            (assignment_id, action, details, created_at)
            VALUES (?, 'assigned', ?, NOW())
        ");
        $stmt->execute([
            $assignment_id,
            json_encode([
                'user' => $username,
                'service_id' => $service_id,
                'vds_id' => $vds_id,
                'ip' => $vds['ip'],
                'new_slots_used' => $new_slots_used
            ])
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'VDS успешно назначена. Настройка сервера начнется в течение 1-2 минут.',
            'assignment' => [
                'id' => $assignment_id,
                'vds_id' => $vds_id,
                'vds_ip' => $vds['ip'],
                'status' => 'pending',
                'estimated_completion' => date('H:i:s', strtotime('+5 minutes'))
            ],
            'next_steps' => [
                'check_status_url' => '/bill/vds_assign_manager.php?action=get_assignment_status&assignment_id=' . $assignment_id,
                'refresh_interval' => 30 // секунд
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Ошибка присвоения VDS: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при присвоении VDS: ' . $e->getMessage(),
            'error_details' => $e->getMessage()
        ]);
    }
}

// Функция 4: Проверка статуса назначения
function getAssignmentStatus() {
    global $pdo, $username;
    
    $assignment_id = $_GET['assignment_id'] ?? '';
    
    // Проверяем доступ пользователя к этому назначению
    $stmt = $pdo->prepare("
        SELECT a.*, s.name as service_name, v.ip as vds_ip
        FROM server_vds_assignments a
        JOIN services s ON a.service_id = s.service_id
        JOIN users u ON s.user_id = u.id
        LEFT JOIN vds v ON a.vds_id = v.vds_id
        WHERE a.id = ? AND u.username = ?
    ");
    $stmt->execute([$assignment_id, $username]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Назначение не найдено']);
        exit();
    }
    
    // Получаем лог прогресса
    $stmt = $pdo->prepare("
        SELECT * FROM vds_assignment_logs 
        WHERE assignment_id = ? 
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$assignment_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем очередь обработки
    $stmt = $pdo->prepare("
        SELECT * FROM vds_assignment_queue 
        WHERE assignment_id = ? 
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$assignment_id]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Определяем процент выполнения на основе статуса
    $progress_percent = 0;
    $progress_steps = [];
    
    switch ($assignment['status']) {
        case 'pending':
            $progress_percent = 10;
            $progress_steps = ['Подготовка данных', 'Ожидание обработки'];
            break;
        case 'processing':
            $progress_percent = 40;
            $progress_steps = ['Подготовка VDS', 'Настройка окружения'];
            break;
        case 'configuring':
            $progress_percent = 70;
            $progress_steps = ['Установка игрового сервера', 'Настройка панели'];
            break;
        case 'completed':
            $progress_percent = 100;
            $progress_steps = ['Завершено', 'Сервер готов'];
            break;
        case 'failed':
            $progress_percent = 0;
            $progress_steps = ['Ошибка', 'Требуется повторная попытка'];
            break;
    }
    
    echo json_encode([
        'success' => true,
        'assignment' => $assignment,
        'progress' => [
            'percent' => $progress_percent,
            'status' => $assignment['status'],
            'steps' => $progress_steps,
            'estimated_time_left' => calculateTimeLeft($assignment['assigned_at'], $progress_percent)
        ],
        'logs' => $logs,
        'queue_status' => $queue,
        'actions' => getAvailableActions($assignment['status'])
    ]);
}

// Вспомогательные функции
function checkVdsPorts($ip) {
    $ports = [
        'ssh' => 22,
        'http' => 80,
        'https' => 443,
        'game_control' => 8080,
        'database' => 3306
    ];
    
    $results = [];
    foreach ($ports as $name => $port) {
        $fp = @fsockopen($ip, $port, $errno, $errstr, 2);
        $results[$name] = ($fp !== false);
        if ($fp) fclose($fp);
    }
    
    return $results;
}

function performDetailedVdsCheck($ip) {
    $checks = [];
    
    // 1. Проверка портов
    $checks['ports'] = checkVdsPorts($ip);
    
    // 2. Проверка пинга
    $checks['ping'] = checkPing($ip);
    
    // 3. Проверка DNS
    $checks['dns'] = checkDns($ip);
    
    // 4. Проверка времени ответа
    $checks['response_time'] = measureResponseTime($ip);
    
    return $checks;
}

function checkPing($ip) {
    $command = PHP_OS === 'WINNT' ? "ping -n 1 $ip" : "ping -c 1 -W 2 $ip";
    exec($command, $output, $result);
    return $result === 0;
}

function checkDns($ip) {
    $hostname = gethostbyaddr($ip);
    return $hostname !== $ip;
}

function measureResponseTime($ip) {
    $start = microtime(true);
    $fp = @fsockopen($ip, 80, $errno, $errstr, 2);
    $time = microtime(true) - $start;
    if ($fp) fclose($fp);
    return round($time * 1000, 2); // в миллисекундах
}

function calculateRecommendationScore($checks, $vds) {
    $score = 0;
    
    // Порты дают 50% оценки
    if ($checks['ports']['ssh']) $score += 20;
    if ($checks['ports']['http'] || $checks['ports']['https']) $score += 20;
    if ($checks['ports']['game_control']) $score += 10;
    
    // Пинг и DNS дают 30% оценки
    if ($checks['ping']) $score += 15;
    if ($checks['dns']) $score += 15;
    
    // Время ответа дает 20% оценки
    if ($checks['response_time'] < 50) $score += 20;
    elseif ($checks['response_time'] < 100) $score += 15;
    elseif ($checks['response_time'] < 200) $score += 10;
    else $score += 5;
    
    return min(100, $score);
}

function getStatusIcon($status) {
    $icons = [
        'good' => '✅',
        'warning' => '⚠️',
        'critical' => '🔴'
    ];
    return $icons[$status] ?? '❓';
}

function getRecommendation($vds) {
    if ($vds['load_percentage'] < 30) {
        return "Отличный выбор! Низкая нагрузка.";
    } elseif ($vds['load_percentage'] < 60) {
        return "Хороший выбор. Умеренная нагрузка.";
    } else {
        return "Внимание! Высокая нагрузка. Рассмотрите другие варианты.";
    }
}

function calculateTimeLeft($startTime, $progress) {
    $elapsed = time() - strtotime($startTime);
    if ($progress <= 0) return '~5 минут';
    
    $estimatedTotal = ($elapsed / $progress) * 100;
    $remaining = max(1, round(($estimatedTotal - $elapsed) / 60)); // в минутах
    
    return "~{$remaining} мин";
}

function getAvailableActions($status) {
    $actions = [];
    
    switch ($status) {
        case 'pending':
        case 'processing':
            $actions[] = ['name' => 'refresh', 'label' => 'Обновить статус'];
            $actions[] = ['name' => 'cancel', 'label' => 'Отменить', 'confirm' => true];
            break;
        case 'failed':
            $actions[] = ['name' => 'retry', 'label' => 'Повторить попытку'];
            $actions[] = ['name' => 'choose_another', 'label' => 'Выбрать другую VDS'];
            break;
        case 'completed':
            $actions[] = ['name' => 'open_panel', 'label' => 'Открыть панель'];
            $actions[] = ['name' => 'view_details', 'label' => 'Подробности'];
            break;
    }
    
    return $actions;
}

// Создание необходимых таблиц (добавить в db_updater.php)
function createAssignmentTables($pdo) {
    $tables = [
        "CREATE TABLE IF NOT EXISTS server_vds_assignments (
            id INT(11) NOT NULL AUTO_INCREMENT,
            service_id VARCHAR(20) NOT NULL,
            vds_id VARCHAR(50) NOT NULL,
            assigned_at DATETIME NOT NULL,
            completed_at DATETIME NULL,
            status ENUM('pending', 'processing', 'configuring', 'completed', 'failed') DEFAULT 'pending',
            user_id INT(11) NOT NULL,
            error_message TEXT,
            PRIMARY KEY (id),
            UNIQUE KEY unique_service_assignment (service_id),
            KEY idx_vds_id (vds_id),
            KEY idx_status (status),
            KEY idx_user_id (user_id),
            KEY idx_assigned_at (assigned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS vds_assignment_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            assignment_id INT(11) NOT NULL,
            action VARCHAR(50) NOT NULL,
            details TEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_assignment_id (assignment_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
        
        "CREATE TABLE IF NOT EXISTS vds_assignment_queue (
            id INT(11) NOT NULL AUTO_INCREMENT,
            assignment_id INT(11) NOT NULL,
            service_id VARCHAR(20) NOT NULL,
            vds_id VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            attempts INT(11) DEFAULT 0,
            last_attempt DATETIME NULL,
            created_at DATETIME NOT NULL,
            processed_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_assignment_id (assignment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];
    
    foreach ($tables as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Ошибка создания таблицы: " . $e->getMessage());
        }
    }
}

// Если файл запущен напрямую для создания таблиц
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === 'install') {
    createAssignmentTables($pdo);
    echo "Таблицы для системы присвоения VDS созданы!\n";
}
?>