<?php
session_start();
// Исправляем путь к базе данных
require_once __DIR__ . './db.php';

if (!isset($_SESSION['username'])) {
    die(json_encode(['success' => false, 'message' => 'Не авторизован']));
}

$username = $_SESSION['username'];
$tariff_id = $_POST['tariff_id'] ?? $_GET['tariff_id'] ?? null;
$server_name = $_POST['server_name'] ?? 'Мой сервер';
$slots = $_POST['slots'] ?? 10;
$game = $_POST['game'] ?? 'bombsquad';
$control_panel = $_POST['control_panel'] ?? 3;

// Проверяем подключение к БД
if (!$pdo) {
    die(json_encode(['success' => false, 'message' => 'Ошибка подключения к базе данных']));
}

// Получаем информацию о пользователе - ИСПРАВЛЕНО!
try {
    $stmt = $pdo->prepare("SELECT id, coins FROM users WHERE username = ?");
    if (!$stmt) {
        die(json_encode(['success' => false, 'message' => 'Ошибка подготовки запроса']));
    }
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка получения пользователя: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Ошибка базы данных']));
}

if (!$user) {
    die(json_encode(['success' => false, 'message' => 'Пользователь не найден']));
}

// Получаем информацию о тарифе с новыми полями
try {
    $stmt = $pdo->prepare("SELECT *, 
                           COALESCE(description, '') as description,
                           COALESCE(mod, '') as mod,
                           COALESCE(confirmation_button_text, 'Подтвердить покупку') as confirmation_button_text,
                           COALESCE(additional_features, '') as additional_features,
                           COALESCE(features_list, '') as features_list
                           FROM tariffs WHERE id = ?");
    $stmt->execute([$tariff_id]);
    $tariff = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Ошибка получения тарифа: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Ошибка загрузки тарифа']));
}

if (!$tariff) {
    die(json_encode(['success' => false, 'message' => 'Тариф не найден']));
}

// Проверяем баланс
if ($user['coins'] < $tariff['price']) {
    die(json_encode(['success' => false, 'message' => 'Недостаточно монет. Нужно: ' . $tariff['price'] . ', есть: ' . $user['coins']]));
}

// Генерируем уникальный ID сервиса
$service_id = 'srv_' . uniqid();

// Начинаем транзакцию
$pdo->beginTransaction();

try {
    // Списываем монеты
    $new_balance = $user['coins'] - $tariff['price'];
    $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
    $stmt->execute([$new_balance, $user['id']]);
    
    // Определяем, является ли тариф Standard
    $isStandard = (strtolower(trim($tariff['name'])) === 'standard');
    
    // Получаем длительность и дату истечения
    $duration_days = $_POST['duration_days'] ?? $tariff['default_duration_days'] ?? 30;
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$duration_days} days"));
    
    // Создаем запись сервиса
    $stmt = $pdo->prepare("
        INSERT INTO services (
            user_id, service_id, tariff_id, name, price, 
            status, created_at, game, setup_status, 
            control_panel_id, has_panel, expires_at, 
            duration_days, last_payment_date, payment_status,
            auto_renew
        ) VALUES (?, ?, ?, ?, ?, 'active', NOW(), ?, 
            'ready', ?, ?, ?, ?, NOW(), 'active', 0)
    ");
    
    // Для Standard тарифа всегда has_panel = 1
    $has_panel = $isStandard ? 1 : ($control_panel ? 1 : 0);
    
    $stmt->execute([
        $user['id'],
        $service_id,
        $tariff_id,
        $server_name,
        $tariff['price'],
        $game,
        $control_panel,
        $has_panel,
        $expires_at,
        $duration_days
    ]);
    
    // Создаем запись в логе
    $stmt = $pdo->prepare("
        INSERT INTO server_creation_log (service_id, action, created_at) 
        VALUES (?, ?, NOW())
    ");
    $log_action = $isStandard ? 'server_created_with_panel' : 'server_created';
    $stmt->execute([$service_id, $log_action]);
    
    // После успешного создания добавляем запись в историю платежей:
    $stmt = $pdo->prepare("
        INSERT INTO service_payments (
            service_id, user_id, amount, payment_type, 
            period_days, payment_date, expires_before,
            expires_after, transaction_id, status, details
        ) VALUES (?, ?, ?, 'initial', ?, NOW(), NOW(),
            ?, ?, 'completed', ?)
    ");
    
    $stmt->execute([
        $service_id,
        $user['id'],
        $tariff['price'],
        $duration_days,
        $expires_at,
        'order_' . $service_id . '_' . time(),
        json_encode([
            'tariff_name' => $tariff['name'],
            'tariff_description' => $tariff['description'],
            'tariff_mod' => $tariff['mod'],
            'game' => $game,
            'server_name' => $server_name,
            'is_standard' => $isStandard
        ])
    ]);
    
    $pdo->commit();
    
    // Формируем ответ
    $response = [
        'success' => true,
        'message' => 'Сервер успешно заказан!',
        'service_id' => $service_id,
        'new_balance' => $new_balance,
        'has_panel' => $has_panel,
        'expires_at' => $expires_at,
        'expires_timestamp' => strtotime($expires_at),
        // Добавляем информацию о тарифе для использования в интерфейсе
        'tariff_info' => [
            'name' => $tariff['name'],
            'description' => $tariff['description'],
            'mod' => $tariff['mod'],
            'confirmation_button_text' => $tariff['confirmation_button_text'],
            'additional_features' => $tariff['additional_features'],
            'features_list' => $tariff['features_list']
        ]
    ];
    
    // Добавляем информацию о панели управления
    if ($isStandard) {
        $response['message'] = 'Сервер Standard успешно заказан! Панель управления доступна сразу.';
        $response['panel_url'] = "/MasterBilling/HomePage/BillingPanel?id=" . urlencode($service_id);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Ошибка при заказе сервера: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка при создании сервера: ' . $e->getMessage(),
        'debug' => $e->getTraceAsString()
    ]);
}
?>