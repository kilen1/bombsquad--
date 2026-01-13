<?php
// check_expiration.php - ручная проверка истечения сроков
session_start();
require_once __DIR__ . './db.php';

header('Content-Type: application/json');

// Проверка прав администратора
if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit();
}

function processExpiredServices($pdo) {
    $now = date('Y-m-d H:i:s');
    $result = [
        'expired' => [],
        'notifications' => [],
        'auto_renewed' => []
    ];
    
    // 1. Проверяем истекшие сервера
    $stmt = $pdo->prepare("
        SELECT s.*, u.username, u.coins, t.renewal_price, t.default_duration_days
        FROM services s
        JOIN users u ON s.user_id = u.id
        JOIN tariffs t ON s.tariff_id = t.id
        WHERE s.payment_status = 'active'
        AND s.expires_at < ?
        AND s.status IN ('active', 'suspended')
    ");
    $stmt->execute([$now]);
    $expired_services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expired_services as $service) {
        // Авто-продление если включено и есть деньги
        if ($service['auto_renew'] == 1 && $service['coins'] >= ($service['renewal_price'] ?? $service['price'])) {
            
            $pdo->beginTransaction();
            try {
                $renewal_amount = $service['renewal_price'] ?? $service['price'];
                $new_expires = date('Y-m-d H:i:s', strtotime("+{$service['default_duration_days']} days"));
                $new_balance = $service['coins'] - $renewal_amount;
                
                // Списываем средства
                $stmt = $pdo->prepare("UPDATE users SET coins = ? WHERE id = ?");
                $stmt->execute([$new_balance, $service['user_id']]);
                
                // Обновляем срок
                $stmt = $pdo->prepare("
                    UPDATE services 
                    SET expires_at = ?,
                        last_payment_date = NOW(),
                        updated_at = NOW()
                    WHERE service_id = ?
                ");
                $stmt->execute([$new_expires, $service['service_id']]);
                
                // Логируем
                $stmt = $pdo->prepare("
                    INSERT INTO service_payments (
                        service_id, user_id, amount, payment_type,
                        payment_date, expires_after, status, details
                    ) VALUES (?, ?, ?, 'auto_renewal',
                        NOW(), ?, 'completed', ?)
                ");
                $stmt->execute([
                    $service['service_id'],
                    $service['user_id'],
                    $renewal_amount,
                    $new_expires,
                    json_encode(['auto' => true])
                ]);
                
                $pdo->commit();
                
                $result['auto_renewed'][] = [
                    'service_id' => $service['service_id'],
                    'user' => $service['username'],
                    'amount' => $renewal_amount,
                    'new_expires' => $new_expires
                ];
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Ошибка авто-продления: " . $e->getMessage());
            }
            
        } else {
            // Меняем статус на истекший
            $stmt = $pdo->prepare("
                UPDATE services 
                SET payment_status = 'expired',
                    status = 'suspended',
                    updated_at = NOW()
                WHERE service_id = ?
            ");
            $stmt->execute([$service['service_id']]);
            
            $result['expired'][] = [
                'service_id' => $service['service_id'],
                'user' => $service['username'],
                'expired_at' => $service['expires_at']
            ];
        }
    }
    
    // 2. Отправляем уведомления о скором истечении
    $stmt = $pdo->prepare("
        SELECT s.*, u.username, u.email
        FROM services s
        JOIN users u ON s.user_id = u.id
        WHERE s.payment_status = 'active'
        AND s.status = 'active'
        AND s.expires_at BETWEEN ? AND DATE_ADD(?, INTERVAL 3 DAY)
    ");
    $stmt->execute([date('Y-m-d H:i:s', strtotime('+1 day')), date('Y-m-d H:i:s')]);
    $soon_expiring = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($soon_expiring as $service) {
        $days_left = ceil((strtotime($service['expires_at']) - time()) / (60 * 60 * 24));
        $result['notifications'][] = [
            'service_id' => $service['service_id'],
            'user' => $service['username'],
            'days_left' => $days_left,
            'expires_at' => $service['expires_at']
        ];
    }
    
    return $result;
}

// Запускаем обработку
try {
    $result = processExpiredServices($pdo);
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'results' => $result,
        'summary' => [
            'expired' => count($result['expired']),
            'auto_renewed' => count($result['auto_renewed']),
            'notifications' => count($result['notifications'])
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка обработки: ' . $e->getMessage()
    ]);
}
?>