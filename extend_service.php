<?php
session_start();
include __DIR__ . '../../Base/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

// Определяем действие: get_prices или продление
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? ($input['action'] ?? 'extend');

// Если запрос на получение цен
if ($action === 'get_prices') {
    getRenewalPrices();
    exit();
}

// Иначе выполняем продление
extendService();

// Функция для получения цен продления
function getRenewalPrices() {
    global $pdo;
    
    $service_id = $_GET['service_id'] ?? '';
    $username = $_SESSION['username'];

    if (empty($service_id)) {
        echo json_encode(['success' => false, 'message' => 'Не указан ID сервиса']);
        exit();
    }

    // Получаем информацию о сервисе и тарифе
    $stmt = $pdo->prepare("
        SELECT 
            s.*,
            t.*,
            t.price as tariff_price,
            t.renewal_price_month,
            t.renewal_price_week,
            t.renewal_price_day,
            t.renewal_enabled,
            t.min_renewal_days,
            t.max_renewal_days
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN tariffs t ON s.tariff_id = t.id
        WHERE s.service_id = ? AND u.username = ?
    ");
    $stmt->execute([$service_id, $username]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        exit();
    }

    // Формируем цены продления
    $prices = [];

    // Месяц (30 дней)
    if ($service['renewal_enabled']) {
        $month_price = $service['renewal_price_month'] ?? $service['tariff_price'] * 0.9;
        $prices[] = [
            'days' => 30,
            'amount' => (float)$month_price,
            'label' => '30 дней',
            'daily_price' => round($month_price / 30, 2),
            'discount' => $service['renewal_price_month'] ? round((1 - ($month_price / $service['tariff_price'])) * 100) : 10
        ];
        
        // Неделя (7 дней)
        $week_price = $service['renewal_price_week'] ?? $service['tariff_price'] * 0.3;
        $prices[] = [
            'days' => 7,
            'amount' => (float)$week_price,
            'label' => '7 дней',
            'daily_price' => round($week_price / 7, 2)
        ];
        
        // День
        $day_price = $service['renewal_price_day'] ?? $service['tariff_price'] * 0.05;
        $prices[] = [
            'days' => 1,
            'amount' => (float)$day_price,
            'label' => '1 день',
            'daily_price' => (float)$day_price
        ];
        
        // Пользовательский период
        $prices[] = [
            'days' => 'custom',
            'min_days' => $service['min_renewal_days'] ?? 1,
            'max_days' => $service['max_renewal_days'] ?? 365,
            'daily_price' => round($month_price / 30, 2)
        ];
    }

    echo json_encode([
        'success' => true,
        'service_id' => $service_id,
        'tariff_name' => $service['name'],
        'tariff_price' => $service['tariff_price'],
        'renewal_enabled' => (bool)$service['renewal_enabled'],
        'prices' => $prices,
        'current_expires' => $service['expires_at']
    ]);
}

// Функция для продления сервиса (остается прежней, но немного обновим)
function extendService() {
    global $pdo;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $service_id = $input['service_id'] ?? '';
    $days = (int)($input['days'] ?? 30);

    $username = $_SESSION['username'];

    // Проверяем доступ к серверу и получаем информацию о тарифе
    $stmt = $pdo->prepare("
        SELECT 
            s.*, 
            u.coins, 
            u.id as user_id,
            t.*,
            s.price as current_price,
            t.renewal_price_month,
            t.renewal_price_week,
            t.renewal_price_day,
            t.renewal_enabled,
            t.min_renewal_days,
            t.max_renewal_days
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN tariffs t ON s.tariff_id = t.id
        WHERE s.service_id = ? AND u.username = ?
    ");
    $stmt->execute([$service_id, $username]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        echo json_encode(['success' => false, 'message' => 'Сервер не найден']);
        exit();
    }

    // Проверяем, разрешено ли продление для этого тарифа
    if (!$service['renewal_enabled']) {
        echo json_encode(['success' => false, 'message' => 'Продление не доступно для этого тарифа']);
        exit();
    }

    // Проверяем диапазон дней
    $min_days = $service['min_renewal_days'] ?? 1;
    $max_days = $service['max_renewal_days'] ?? 365;

    if ($days < $min_days || $days > $max_days) {
        echo json_encode(['success' => false, 'message' => "Период должен быть от $min_days до $max_days дней"]);
        exit();
    }

    // Рассчитываем стоимость продления
    $amount = calculateRenewalPrice($service, $days);

    // Проверяем баланс
    if ($service['coins'] < $amount) {
        echo json_encode(['success' => false, 'message' => 'Недостаточно средств']);
        exit();
    }

    $pdo->beginTransaction();
    try {
        // Списываем средства
        $new_balance = $service['coins'] - $amount;
        $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
        $stmt->execute([$new_balance, $service['user_id']]);
        
        // Рассчитываем новую дату истечения
        $current_expires = $service['expires_at'] ?: date('Y-m-d H:i:s');
        $new_expires = date('Y-m-d H:i:s', strtotime("+{$days} days", strtotime($current_expires)));
        
        // Обновляем сервер
        $stmt = $pdo->prepare("
            UPDATE services 
            SET expires_at = ?,
                last_payment_date = NOW(),
                updated_at = NOW(),
                payment_status = 'active'
            WHERE service_id = ?
        ");
        $stmt->execute([$new_expires, $service_id]);
        
        // Записываем платеж
        $stmt = $pdo->prepare("
            INSERT INTO service_payments (
                service_id, user_id, amount, payment_type,
                period_days, payment_date, expires_before,
                expires_after, transaction_id, status, details
            ) VALUES (?, ?, ?, 'extension', ?, NOW(), ?, 
                ?, ?, 'completed', ?)
        ");
        $stmt->execute([
            $service_id,
            $service['user_id'],
            $amount,
            $days,
            $current_expires,
            $new_expires,
            'manual_extend_' . $service_id . '_' . time(),
            json_encode([
                'days_added' => $days,
                'tariff_id' => $service['tariff_id'],
                'tariff_name' => $service['name'],
                'price_per_day' => round($amount / $days, 2),
                'renewal_type' => getRenewalType($days)
            ])
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Сервер продлен на ' . $days . ' дней',
            'new_expires' => $new_expires,
            'new_balance' => $new_balance,
            'amount' => $amount,
            'days' => $days
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Ошибка продления сервера: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Ошибка продления']);
    }
}

// Функция расчета цены продления
function calculateRenewalPrice($service, $days) {
    // Если установлены фиксированные цены
    if ($days == 30 && isset($service['renewal_price_month'])) {
        return (float)$service['renewal_price_month'];
    }
    if ($days == 7 && isset($service['renewal_price_week'])) {
        return (float)$service['renewal_price_week'];
    }
    if ($days == 1 && isset($service['renewal_price_day'])) {
        return (float)$service['renewal_price_day'];
    }
    
    // Иначе рассчитываем пропорционально месячной цене
    $monthly_price = $service['renewal_price_month'] ?? $service['price'] * 0.9;
    return round(($monthly_price / 30) * $days, 2);
}

// Функция определения типа продления
function getRenewalType($days) {
    if ($days == 30) return 'month';
    if ($days == 7) return 'week';
    if ($days == 1) return 'day';
    if ($days > 30) return 'long_term';
    return 'custom';
}
?>