<?php
session_start();
include __DIR__ . './db.php';

header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

$username = $_SESSION['username'];

// Получаем данные из запроса
$input = json_decode(file_get_contents('php://input'), true);
$service_id = $input['service_id'] ?? '';
$command = $input['command'] ?? '';

if (empty($service_id) || empty($command)) {
    echo json_encode(['success' => false, 'message' => 'Не указан ID сервиса или команда']);
    exit();
}

// Проверяем, принадлежит ли сервер пользователю
$stmt = $pdo->prepare("
    SELECT s.*, u.username 
    FROM services s
    JOIN users u ON s.user_id = u.id
    WHERE s.service_id = ? AND u.username = ?
");
$stmt->execute([$service_id, $username]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    echo json_encode(['success' => false, 'message' => 'Сервер не найден или доступ запрещен']);
    exit();
}

// Проверяем, доступна ли панель управления для этого сервера
if (!$service['has_panel'] && strtolower(trim($service['tariff_name'] ?? '')) !== 'standard') {
    echo json_encode(['success' => false, 'message' => 'Панель управления еще не готова']);
    exit();
}

// Обрабатываем команды
try {
    switch ($command) {
        case 'start':
            $result = startServer($pdo, $service_id, $service);
            break;
            
        case 'stop':
            $result = stopServer($pdo, $service_id, $service);
            break;
            
        case 'restart':
            $result = restartServer($pdo, $service_id, $service);
            break;
            
        case 'status':
            $result = getServerStatus($pdo, $service_id, $service);
            break;
            
        default:
            $result = [
                'success' => false,
                'message' => 'Неизвестная команда'
            ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Ошибка в panel_api: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Внутренняя ошибка сервера'
    ]);
}

// Функция для запуска сервера
function startServer($pdo, $service_id, $service) {
    // Здесь должна быть логика реального запуска сервера
    // Для демонстрации просто создаем лог
    
    $log_stmt = $pdo->prepare("
        INSERT INTO server_action_log (service_id, action, details, created_at)
        VALUES (?, 'start', ?, NOW())
    ");
    $log_stmt->execute([
        $service_id,
        json_encode([
            'user' => $_SESSION['username'],
            'game' => $service['game'],
            'vds_id' => $service['vds_id'] ?? 'none',
            'tariff' => $service['tariff_name'] ?? 'unknown'
        ])
    ]);
    
    return [
        'success' => true,
        'message' => 'Сервер запускается...',
        'command' => 'start',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Функция для остановки сервера
function stopServer($pdo, $service_id, $service) {
    $log_stmt = $pdo->prepare("
        INSERT INTO server_action_log (service_id, action, details, created_at)
        VALUES (?, 'stop', ?, NOW())
    ");
    $log_stmt->execute([
        $service_id,
        json_encode([
            'user' => $_SESSION['username'],
            'game' => $service['game']
        ])
    ]);
    
    return [
        'success' => true,
        'message' => 'Сервер останавливается...',
        'command' => 'stop',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Функция для перезапуска сервера
function restartServer($pdo, $service_id, $service) {
    $log_stmt = $pdo->prepare("
        INSERT INTO server_action_log (service_id, action, details, created_at)
        VALUES (?, 'restart', ?, NOW())
    ");
    $log_stmt->execute([
        $service_id,
        json_encode([
            'user' => $_SESSION['username'],
            'game' => $service['game']
        ])
    ]);
    
    return [
        'success' => true,
        'message' => 'Сервер перезапускается...',
        'command' => 'restart',
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

// Функция для получения статуса сервера
function getServerStatus($pdo, $service_id, $service) {
    // Для Standard тарифа или серверов без VDS возвращаем базовый статус
    $isStandard = (strtolower(trim($service['tariff_name'] ?? '')) === 'standard');
    
    if ($isStandard || !$service['vds_id']) {
        return [
            'success' => true,
            'status' => 'online',
            'uptime' => '24/7',
            'players' => 0,
            'cpu_usage' => rand(5, 25),
            'ram_usage' => rand(30, 60),
            'disk_usage' => rand(10, 40),
            'is_standard' => $isStandard
        ];
    }
    
    // Для серверов с VDS можно добавить реальную проверку статуса
    // Временная заглушка
    return [
        'success' => true,
        'status' => 'online',
        'uptime' => '12:34:56',
        'players' => rand(0, 10),
        'cpu_usage' => rand(10, 40),
        'ram_usage' => rand(20, 70),
        'disk_usage' => rand(15, 50)
    ];
}
?>