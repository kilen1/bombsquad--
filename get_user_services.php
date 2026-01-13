<?php
// get_user_services.php
session_start();
include __DIR__ . './db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

$username = $_SESSION['username'];

try {
    // Получаем ID пользователя
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
        exit();
    }
    
    $userId = $user['id'];
    
    // Получаем все сервисы пользователя
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.service_id,
            s.name,
            s.game,
            s.price,
            s.created_at,
            s.setup_status,
            s.vds_id,
            s.has_panel,
            s.control_panel_id,
            s.expires_at,
            s.payment_status,
            s.last_payment_date,
            s.auto_renew,
            t.name as tariff_name,
            v.ip as vds_ip,
            v.username as vds_username
        FROM services s
        LEFT JOIN tariffs t ON s.tariff_id = t.id
        LEFT JOIN vds v ON s.vds_id = v.vds_id
        WHERE s.user_id = ? 
        ORDER BY 
            CASE 
                WHEN s.payment_status = 'expired' THEN 3
                WHEN s.expires_at IS NULL THEN 0
                WHEN s.expires_at < NOW() THEN 2
                WHEN s.expires_at < DATE_ADD(NOW(), INTERVAL 7 DAY) THEN 1
                ELSE 0
            END,
            s.expires_at ASC,
            s.created_at DESC
    ");
    
    $stmt->execute([$userId]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'services' => $services,
        'count' => count($services)
    ]);
    
} catch (Exception $e) {
    error_log("Ошибка загрузки сервисов: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка загрузки данных'
    ]);
}
?>