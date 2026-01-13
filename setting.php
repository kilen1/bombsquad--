<?php 
session_start(); 
include __DIR__ . './db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /MasterBilling/Autorize");
    exit();
}

$inactive_time = 60000; 
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive_time)) {
    session_unset();
    session_destroy();
    header("Location: /MasterBilling/Autorize");
    exit();
}
$_SESSION['last_activity'] = time();

$username = $_SESSION['username'];

// 🔹 Получаем ID и баланс
try {
    $stmt = $pdo->prepare("SELECT id, coins FROM users WHERE username = ?");
    $stmt->execute([trim($username)]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("Пользователь не найден.");
    }

    $user_id = (int) $user['id']; // Приводим к int
    $balance = (float) $user['coins'];

} catch (Exception $e) {
    error_log("Ошибка загрузки пользователя: " . $e->getMessage());
    die("Ошибка загрузки данных.");
}

// 🔹 Загружаем сервера - РАБОЧИЙ ВАРИАНТ
try {
    $servicesStmt = $pdo->prepare("
        SELECT 
            s.id as service_id, 
            s.name, 
            s.game, 
            s.price, 
            s.created_at, 
            s.setup_status, 
            s.ip,
            s.control_panel,
            s.has_panel,
            t.name as tariff_name,
            s.expires_at,                    -- ДОБАВЛЕНО
            s.payment_status,                -- ДОБАВЛЕНО
            s.last_payment_date,             -- ДОБАВЛЕНО
            s.auto_renew,                    -- ДОБАВЛЕНО
            s.service_id as service_uid
        FROM services s
        LEFT JOIN tariffs t ON s.tariff_id = t.id
        WHERE CAST(s.user_id AS UNSIGNED) = ?  -- Приводим VARCHAR к числу для сравнения
        ORDER BY s.created_at DESC
    ");
    
    $servicesStmt->execute([$user_id]); 
    $userServices = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Загружено серверов с CAST для user_id=$user_id: " . count($userServices));

} catch (Exception $e) {
    error_log("Ошибка загрузки серверов: " . $e->getMessage());
    $userServices = [];
}

// 🔹 Загружаем тарифы
try {
    $gameTariffsStmt = $pdo->prepare("
        SELECT id, name, game, price, duration_months, 
               COALESCE(ram, 'Не указано') as ram,
               COALESCE(cpu, 'Не указано') as cpu,
               COALESCE(storage, 'Не указано') as storage,
               COALESCE(bandwidth, 'Не указано') as bandwidth
        FROM tariffs 
        WHERE game IN ('bombsquad', 'minecraft', 'rust')
        ORDER BY 
            CASE 
                WHEN name = 'Standard' THEN 1
                WHEN name = 'Free Basic' THEN 2
                ELSE 3
            END,
            price ASC
    ");
    $gameTariffsStmt->execute();
    $gameTariffs = $gameTariffsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Ошибка загрузки тарифов: " . $e->getMessage());
    $gameTariffs = [];

}
?>
<?php var_dump($_SESSION['username']); ?>
<!DOCTYPE html>
<html lang="ru">
<head>
   
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MasterBilling</title>
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #7c4dff;
            --primary-dark: #6a3de8;
            --primary-light: #9a6dff;
            --secondary: #00bfa5;
            --dark: #1a1f25;
            --darker: #12161a;
            --light: #f8f9fa;
            --gray: #6c757d;
            --success: #00c853;
            --warning: #ff9100;
            --danger: #ff1744;
            --border-radius: 16px;
            --shadow-sm: 0 4px 12px rgba(0, 0, 0, 0.15);
            --shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
            --shadow-lg: 0 12px 36px rgba(0, 0, 0, 0.25);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }

        body {
            background-color: var(--darker);
            color: var(--light);
            min-height: 100vh;
            overflow-x: hidden;
        }


        /* Desktop Layout */
        .desktop-container {
            display: flex;
            min-height: 100vh;
        }

        .desktop-sidebar {
            width: 260px;
            background: linear-gradient(180deg, var(--primary-dark), var(--darker));
            color: white;
            padding: 20px 0;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .desktop-sidebar-header {
            padding: 20px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .desktop-sidebar-logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(90deg, var(--primary-light), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .desktop-sidebar-menu {
            list-style: none;
            padding: 0 10px;
        }

        .desktop-sidebar-menu li {
            margin-bottom: 8px;
            border-radius: 8px;
            overflow: hidden;
        }

        .desktop-sidebar-menu a {
            text-decoration: none;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            padding: 12px 20px;
            transition: var(--transition);
            border-radius: 8px;
            font-weight: 500;
        }

        .desktop-sidebar-menu a:hover, 
        .desktop-sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .desktop-sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .desktop-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
        }

        .desktop-header {
            background-color: var(--dark);
            padding: 15px 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .desktop-header .balance {
            background: rgba(124, 77, 255, 0.15);
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 600;
            color: var(--primary-light);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .desktop-header .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .desktop-header .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            cursor: pointer;
        }

        /* Mobile Layout */
        .mobile-container {
            display: none;
            flex-direction: column;
            height: 100vh;
        }

        .mobile-header {
            background-color: var(--dark);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .mobile-header-title {
            font-size: 1.2rem;
            font-weight: 600;
            background: linear-gradient(90deg, var(--primary-light), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .mobile-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background-color: var(--dark);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            margin-top: 20px;
        }

        .mobile-tab-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--darker);
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .mobile-tab {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
            color: rgba(255, 255, 255, 0.6);
            transition: var(--transition);
            font-size: 0.8rem;
        }

        .mobile-tab.active {
            color: var(--primary-light);
        }

        .mobile-tab i {
            font-size: 1.5rem;
            margin-bottom: 4px;
        }

        /* Common Styles */
        .page {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .page.active {
            display: block;
        }

        .card {
            background-color: var(--dark);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .card-title {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--primary-light);
            font-weight: 600;
        }

        .server-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.05);
            margin-bottom: 12px;
            border-radius: 12px;
            transition: var(--transition);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .server-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
            border-color: rgba(122, 77, 255, 0.2);
        }
/* Исправление цвета названия сервера */
.server-info h6 {
    color: white !important;
    font-weight: 600 !important;
    margin-bottom: 0.5rem !important;
    font-size: 1.1rem !important;
}

/* Альтернативно - более конкретное правило */
.server-item .server-info h6.mb-1 {
    color: white !important;
}

/* Или добавьте класс к элементу в HTML: */
/* <h6 class="mb-1 server-name">...</h6> */
.server-name {
    color: white !important;
}
        .server-info {
            flex: 1;
        }

        .server-info span {
            font-weight: 600;
            color: white;
            font-size: 1.1rem;
        }

        .server-info small {
            color: rgba(255, 255, 255, 0.6);
            display: block;
            margin-top: 4px;
            font-size: 0.85rem;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.6);
        }

        .empty-state img {
            max-width: 200px;
            opacity: 0.8;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: white;
        }

        .empty-state p {
            font-size: 0.9rem;
        }

        .pricing-card {
            background-color: var(--dark);
            border-radius: var(--border-radius);
            padding: 20px;
            height: 100%;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: var(--transition);
        }

        .pricing-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow);
            border-color: rgba(124, 77, 255, 0.2);
        }

        .pricing-card h3 {
            color: var(--primary-light);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .pricing-card h5 {
            margin-bottom: 15px;
            color: white;
        }

        .pricing-card ul {
            margin-bottom: 20px;
        }

        .pricing-card ul li {
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
        }

        .pricing-card ul li i {
            margin-right: 8px;
            color: var(--primary-light);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-content {
            background-color: var(--dark);
            padding: 30px;
            border-radius: var(--border-radius);
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-content h4 {
            margin-bottom: 15px;
            color: white;
            font-weight: 600;
        }

        .modal-content p {
            margin-bottom: 20px;
            font-size: 14px;
            opacity: 0.8;
        }

        .btn-modal {
            padding: 10px 20px;
            border-radius: 8px;
            margin: 0 5px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary-modal {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary-modal:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary-modal {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-secondary-modal:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }

        /* Profile Modal */
        .modal.right .modal-dialog {
            position: fixed;
            top: 0;
            right: 0;
            margin: 0;
            width: 350px;
            height: 100%;
            max-width: 100%;
            transition: transform 0.3s ease-in-out;
            border-radius: 0;
        }

        .modal.right.show .modal-dialog {
            transform: translateX(0);
        }

        .modal.right.fade .modal-dialog {
            transform: translateX(100%);
        }

        .profile-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 10px;
            border: 3px solid var(--primary);
        }

        .profile-username {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
        }

        .profile-balance {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 10px 0;
            color: var(--primary-light);
            font-weight: 600;
        }

        .profile-menu {
            padding: 20px;
        }

        .profile-menu-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            transition: var(--transition);
            cursor: pointer;
        }

        .profile-menu-item:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .profile-menu-item i {
            margin-right: 12px;
            color: var(--primary-light);
            width: 20px;
            text-align: center;
        }

        /* Recharge Modal */
        .recharge-modal {
            max-width: 500px;
            width: 90%;
            border-radius: var(--border-radius);
            overflow: hidden;
            background: linear-gradient(135deg, var(--dark), var(--darker));
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .recharge-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .recharge-header h5 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-weight: 600;
        }

        .recharge-body {
            padding: 20px;
        }

        .payment-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .payment-tab {
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.7);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
        }

        .payment-tab.active {
            background: var(--primary);
            color: white;
        }

        .payment-tab i {
            margin-right: 8px;
        }

        .payment-section {
            display: none;
        }

        .payment-section.show {
            display: block;
        }

        .payment-form {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .form-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.85rem;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 12px;
            border-radius: 6px;
            width: 100%;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(124, 77, 255, 0.25);
        }

        .security-note {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-top: 15px;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .security-note i {
            margin-right: 8px;
            color: var(--warning);
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .desktop-container {
                display: none;
            }
            
            .mobile-container {
                display: flex;
            }
            
            .desktop-sidebar {
                display: none;
            }
            
            .desktop-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .desktop-header {
                margin-bottom: 15px;
            }
        }

        @media (min-width: 993px) {
            .mobile-container {
                display: none;
            }
        }

        /* Mobile-specific styles */
        @media (max-width: 992px) {
            .mobile-content {
                padding: 15px;
                margin-top: 15px;
                border-radius: 16px 16px 0 0;
            }
            
            .card {
                padding: 15px;
            }
            
            .card-title {
                font-size: 1.3rem;
            }
            
            .server-item {
                padding: 12px;
            }
            
            .server-info span {
                font-size: 1rem;
            }
            
            .pricing-card {
                padding: 15px;
            }
            
            .pricing-card h3 {
                font-size: 1.2rem;
            }
        }

        /* Loading spinner */
        .spinner-border {
            color: var(--primary);
        }

        /* Chart styles */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stat-card h5 {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .stat-card .display-6 {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }

        /* Mobile tab bar icons */
        .mobile-tab i {
            margin-bottom: 2px;
        }

        /* Button groups */
        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-group .btn {
            flex: 1;
        }





        /* Footer spacing for mobile */
        .mobile-footer-spacer {
            height: 70px;
        }
        
        
        .game-title {
    color: #ffffff; /* белый */
    font-weight: 600;
    font-size: 1.5rem;
}


.server-item .badge {
    font-size: 0.7rem;
    padding: 2px 6px;
    margin-left: 8px;
}


/* Добавьте в CSS секцию setting.php */
.expired-server {
    opacity: 0.85;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05), rgba(239, 68, 68, 0.02)) !important;
    border-left: 4px solid #ef4444 !important;
}

.server-item {
    transition: all 0.3s ease;
    border-radius: 12px;
    border-left: 4px solid #3498db;
    margin-bottom: 15px;
    padding: 15px;
}

.server-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
}

.expiration-info {
    background: rgba(255, 255, 255, 0.05);
    padding: 8px 12px;
    border-radius: 8px;
    border-left: 3px solid #3498db;
}

.status-info {
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 6px;
    display: inline-block;
}

.vds-info {
    padding: 4px 8px;
    background: rgba(52, 152, 219, 1);
    border-radius: 4px;
    display: inline-block;
}

.text-muted {
    color: var(--gray) !important; /* Используем переменную gray */
    /* Или можно явно указать цвет: */
    /* color: #6c757d !important; */
}















/* --- ИСПРАВЛЕНИЕ ШИРИНЫ МОДАЛЬНОГО ОКНА ПРОДЛЕНИЯ И ОБЩИЕ СТИЛИ --- */

/* Убедимся, что .modal-dialog и .modal-content имеют нужную ширину */
#extendModal .modal-dialog.modal-lg,
#extendModal .modal-dialog.modal-lg .modal-content {
    max-width: 70vw !important;
    width: 80vw !important;
    margin: 1.75rem auto !important;
}

/* Если выше не сработает, попробуйте этот более агрессивный вариант */
#extendModal .modal-dialog {
    max-width: 95vw !important;
    width: 80vw !important;
    margin: 1.75rem auto !important;
}

#extendModal .modal-content {
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important; /* Убираем лишние отступы, если они есть */
    /* Применяем стили из вашего предыдущего CSS для фона и т.д. */
    background: linear-gradient(135deg, rgba(26, 31, 37, 0.98) 0%, rgba(30, 40, 50, 0.98) 100%) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(124, 77, 255, 0.4) !important;
    border-radius: 24px !important;
    overflow: hidden !important;
    box-shadow:
        0 0 0 1px rgba(124, 77, 255, 0.1),
        0 30px 60px -12px rgba(0, 0, 0, 0.6),
        0 18px 36px -18px rgba(124, 77, 255, 0.3) !important;
}

/* Для мобильных устройств */
@media (max-width: 992px) { /* Изменил брейкпоинт на 992, чтобы раньше переключался на 1 колонку */
    #extendModal .modal-dialog.modal-lg,
    #extendModal .modal-dialog.modal-lg .modal-content {
        max-width: 98vw !important;
        width: 98vw !important;
        margin: 1rem auto !important;
    }

    /* На мобильных - 1 колонка, стек */
    #extendModal .main-content {
        grid-template-columns: 1fr !important; /* 1 колонка */
        gap: 20px !important; /* Уменьшаем отступ */
    }

    /* Скрываем правую панель на мобильных */
    #extendModal .summary-panel {
        display: none !important;
    }

    /* Перемещаем футер на первое место */
    #extendModal .modal-footer {
        order: -1; /* Помещаем футер перед основным контентом */
    }
}

/* Улучшаем сетку для широкой модалки */
#extendModal .main-content {
    display: grid;
    grid-template-columns: 2.5fr 1fr; /* Основной контент и сводка */
    gap: 40px;
    align-items: start;
    padding: 20px; /* Добавим внутренние отступы */
}

/* --- СТИЛИ ДЛЯ ЗАГОЛОВКА МОДАЛЬНОГО ОКНА --- */
#extendModal .modal-header {
    background: linear-gradient(90deg, rgba(124, 77, 255, 0.15), transparent) !important;
    border-bottom: 1px solid rgba(124, 77, 255, 0.2) !important;
    padding: 1.5rem 2rem !important;
    border-top-left-radius: 24px !important; /* Соответствие скруглению модалки */
    border-top-right-radius: 24px !important;
}

#extendModal .modal-title {
    font-weight: 600;
    color: #fff;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

#extendModal .modal-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c4dff, #6a3de8);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

/* --- СТИЛИ ДЛЯ ОСНОВНОГО ТЕЛА МОДАЛЬНОГО ОКНА --- */
#extendModal .modal-body {
    padding: 2rem 2rem 1rem !important; /* Убираем нижний отступ, так как он в футере */
}

/* --- СТИЛИ ДЛЯ ИНФОРМАЦИИ О СЕРВЕРЕ --- */
#extendModal .server-info-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    backdrop-filter: blur(10px);
}

#extendModal .section-title {
    font-weight: 600;
    color: #fff;
    font-size: 1.2rem;
    margin-bottom: 15px;
    position: relative;
    display: inline-block;
}

#extendModal .section-title::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 50px;
    height: 2px;
    background: linear-gradient(90deg, #7c4dff, transparent);
    border-radius: 2px;
}

#extendModal .info-item {
    margin-bottom: 12px;
}

#extendModal .info-item small {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.85rem;
    display: block;
    margin-bottom: 4px;
}

#extendModal .info-item div {
    color: #fff;
    font-weight: 500;
    font-size: 1rem;
}

/* --- СТИЛИ ДЛЯ ВЫБОРА ПЕРИОДА (ЦЕН) --- */
#extendModal .period-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); /* Более адаптивно */
    gap: 24px;
    margin-bottom: 30px;
}

/* --- СТИЛИ ДЛЯ КАРТОЧКИ ПЕРИОДА --- */
#extendModal .price-option-card {
    min-height: 320px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

#extendModal .price-option-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #7c4dff, #6a3de8);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s ease;
    z-index: 1;
}

#extendModal .price-option-card:hover::before {
    transform: scaleX(1);
}

#extendModal .price-option-card.selected {
    border-color: rgba(124, 77, 255, 0.6);
    box-shadow: 0 0 0 2px rgba(124, 77, 255, 0.3);
    transform: translateY(-4px);
}

#extendModal .price-option-card.selected::before {
    transform: scaleX(1);
}

#extendModal .price-option-card .price-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

#extendModal .price-period {
    font-size: 2.5rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 8px;
    line-height: 1;
}

#extendModal .price-period small {
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: normal;
}

#extendModal .price-amount {
    font-size: 1.8rem;
    font-weight: 600;
    color: #7c4dff;
    margin: 15px 0;
}

#extendModal .price-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

#extendModal .price-features li {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
}

#extendModal .price-features li i {
    color: #7c4dff;
    margin-right: 8px;
    font-size: 0.8rem;
}

/* --- СТИЛИ ДЛЯ КНОПКИ ВЫБОРА --- */
#extendModal .btn-select {
    position: absolute;
    bottom: -20px;
    left: 50%;
    transform: translateX(-50%);
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #7c4dff, #6a3de8);
    border: none;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(124, 77, 255, 0.4);
    z-index: 2;
    cursor: pointer;
}

#extendModal .price-option-card:hover .btn-select {
    opacity: 1;
    bottom: -15px;
    transform: translateX(-50%);
}

#extendModal .price-option-card.selected .btn-select {
    opacity: 1;
    bottom: -15px;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #6a3de8, #7c4dff);
    box-shadow: 0 0 0 0 rgba(124, 77, 255, 0.6);
    animation: pulse 1.5s infinite; /* Добавим анимацию для выбранной */
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(124, 77, 255, 0.6); }
    70% { box-shadow: 0 0 0 12px rgba(124, 77, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(124, 77, 255, 0); }
}

/* --- СТИЛИ ДЛЯ КАСТОМНОГО ВВОДА ПЕРИОДА --- */
#extendModal .custom-period-section {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px;
    margin-top: 20px;
    backdrop-filter: blur(10px);
}

#extendModal .custom-period-input {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(124, 77, 255, 0.3);
    color: white;
    padding: 12px 15px;
    border-radius: 10px;
    width: 100%;
    font-size: 1rem;
    transition: border-color 0.3s;
}

#extendModal .custom-period-input:focus {
    outline: none;
    border-color: #7c4dff;
    box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.2);
}

#extendModal .custom-price-preview {
    font-size: 1.4rem;
    font-weight: 600;
    color: #7c4dff;
    margin-top: 10px;
    text-align: center;
}

/* --- СТИЛИ ДЛЯ ПАНЕЛИ СВОДКИ (ПРАВАЯ ЧАСТЬ) --- */
#extendModal .summary-panel {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 24px;
    height: fit-content; /* Не растягивать на всю высоту */
    backdrop-filter: blur(10px);
    align-self: start; /* Прижать к верху */
}

#extendModal .summary-panel h6 {
    color: #fff;
    font-weight: 600;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

#extendModal .summary-panel h6 i {
    color: #7c4dff;
}

#extendModal .summary-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

#extendModal .summary-item:last-child {
    border-bottom: none;
}

#extendModal .summary-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

#extendModal .summary-value {
    color: #fff;
    font-weight: 500;
    font-size: 1rem;
}

/* --- СТИЛИ ДЛЯ ФУТЕРА МОДАЛЬНОГО ОКНА --- */
#extendModal .modal-footer {
    background: rgba(0, 0, 0, 0.15);
    border-top: 1px solid rgba(124, 77, 255, 0.2);
    padding: 1.5rem 2rem !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom-left-radius: 24px !important; /* Соответствие скруглению модалки */
    border-bottom-right-radius: 24px !important;
}

#extendModal .selected-summary {
    display: flex;
    align-items: center;
    gap: 15px;
}

#extendModal #selectedPeriod,
#extendModal #selectedAmount {
    color: white;
    font-weight: 600;
}

#extendModal #selectedPeriod {
    font-size: 1.1rem;
}

#extendModal #selectedAmount {
    font-size: 1.2rem;
    color: #7c4dff;
}

#extendModal .btn-close-modal {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    transition: all 0.3s;
}

#extendModal .btn-close-modal:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.25);
}

#extendModal .btn-gradient-success {
    background: linear-gradient(135deg, #00c853, #009624);
    border: none;
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
    box-shadow: 0 4px 10px rgba(0, 200, 83, 0.3);
}

#extendModal .btn-gradient-success:hover {
    background: linear-gradient(135deg, #00b247, #007e1e);
    box-shadow: 0 6px 15px rgba(0, 200, 83, 0.4);
    transform: translateY(-1px);
}

/* --- УБИРАЕМ ОТЛАДОЧНЫЕ СТИЛИ --- */
#extendModal .price-option-card {
    border: none; /* Убираем красную рамку */
}


/* --- ИСПРАВЛЕНИЕ КНОПКИ "РАССЧИТАТЬ И ПРОДЛИТЬ" --- */

/* Убедимся, что карточка с кастомным периодом видна */
#extendModal .custom-period-card {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 20px;
    margin-top: 20px;
    backdrop-filter: blur(10px);
    display: block !important; /* Гарантируем, что она отображается */
}

/* Стили для заголовка карточки */
#extendModal .custom-period-card .card-header {
    background: linear-gradient(90deg, rgba(124, 77, 255, 0.15), transparent) !important;
    border-bottom: 1px solid rgba(124, 77, 255, 0.2) !important;
    padding: 12px 16px !important;
    border-top-left-radius: 16px !important;
    border-top-right-radius: 16px !important;
}

/* Стили для формы внутри карточки */
#extendModal .custom-period-card .form-label {
    color: #fff;
    font-weight: 500;
    margin-bottom: 8px;
}

#extendModal .custom-period-card .custom-period-input {
    background: rgba(0, 0, 0, 0.2);
    border: 1px solid rgba(124, 77, 255, 0.3);
    color: white;
    padding: 12px 15px;
    border-radius: 10px;
    width: 100%;
    font-size: 1rem;
    transition: border-color 0.3s;
}

#extendModal .custom-period-card .custom-period-input:focus {
    outline: none;
    border-color: #7c4dff;
    box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.2);
}

/* Стили для превью цены */
#extendModal .custom-price-preview {
    font-size: 1.4rem;
    font-weight: 600;
    color: #7c4dff;
    margin-top: 10px;
    text-align: center;
}

/* СТИЛИ ДЛЯ КНОПКИ "РАССЧИТАТЬ И ПРОДЛИТЬ" */
#extendModal .btn-gradient-primary {
    background: linear-gradient(135deg, #7c4dff, #6a3de8) !important;
    border: none !important;
    color: white !important;
    padding: 12px 24px !important;
    border-radius: 8px !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    box-shadow: 0 4px 10px rgba(124, 77, 255, 0.3) !important;
    cursor: pointer !important;
    display: inline-block !important;
    width: 100% !important; /* На всю ширину */
    text-align: center !important;
}

#extendModal .btn-gradient-primary:hover {
    background: linear-gradient(135deg, #6a3de8, #7c4dff) !important;
    box-shadow: 0 6px 15px rgba(124, 77, 255, 0.4) !important;
    transform: translateY(-1px) !important;
}

/* Убираем возможный overflow:hidden */
#extendModal .modal-body {
    overflow-y: auto !important; /* Разрешаем прокрутку, если контент большой */
    max-height: calc(100vh - 200px) !important; /* Ограничиваем высоту, чтобы не вылезать за экран */
}



    </style>
</head>
<body>
    <!-- Desktop Layout -->
    <div class="desktop-container">
        <div class="desktop-sidebar">
            <div class="desktop-sidebar-header">
                <div class="desktop-sidebar-logo">MasterBilling</div>
            </div>
            <ul class="desktop-sidebar-menu">
                <li><a href="#moi-servera" class="active"><i class="fas fa-server"></i> Мои сервера</a></li>
                <li><a href="#bazy-dannyh-mysql"><i class="fas fa-database"></i> Базы данных</a></li>
                <li><a href="#virtualnye-servera"><i class="fas fa-gamepad"></i> Игровые сервера</a></li>
                <li><a href="#virtualnye-servera1"><i class="fas fa-cloud"></i> Виртуальные сервера</a></li>
                <li><a href="#bezopasnost"><i class="fas fa-shield-alt"></i> Безопасность</a></li>
                <li><a href="#nagruzka"><i class="fas fa-tachometer-alt"></i> Нагрузка</a></li>
                <li><a href="#vydelennyj-sajt"><i class="fas fa-globe"></i> Выделенный сайт</a></li>
                <li><a href="#podderzhka"><i class="fas fa-headset"></i> Поддержка</a></li>
                <li><a href="#dokumentaciya"><i class="fas fa-book"></i> Документация</a></li>
                <li><a href="#kontakty"><i class="fas fa-address-book"></i> Контакты</a></li>
                <li><a href="#" onclick="openBombsquadModal(event)"><i class="fas fa-bomb"></i> New Bombsquad</a></li>
            </ul>
        </div>

        <div class="desktop-content">
            <div class="desktop-header">
                <div class="balance">
                    <i class="fas fa-coins"></i>
                   <span id="balanceAmount"><?= htmlspecialchars(number_format($balance, 2, ',', ' ')) ?> ₽</span>
                </div>
                <div class="user-menu">
                    <img src="/bill/assets/default_avatar.png" alt="Аватар" class="user-avatar" data-bs-toggle="modal" data-bs-target="#profileModal">
                </div>
            </div>

            <div id="moi-servera" class="page active">
                <div class="card">
                    <h2 class="card-title">Мои сервера</h2>
                    <div id="servicesList" class="server-list">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2">Загружаем ваши сервера...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="bazy-dannyh-mysql" class="page">
                <div class="card">
                    <h2 class="card-title">Базы данных MySQL</h2>
                    <p style="color: white;">Управление базами данных MySQL.</p>
                </div>
            </div>

            <div id="virtualnye-servera" class="page">
                <div class="card">
                    <h2 class="card-title">Игровые сервера</h2>
                    <div class="row g-4" id="gameTariffList">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2" >Загружаем тарифы...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="virtualnye-servera1" class="page">
                <div class="card">
                    <h2 class="card-title">Виртуальные сервера</h2>
                    <p style="color: white;">Выберите тариф ниже, чтобы заказать новый VDS</p>
                    <div id="vdsTariffList" class="row g-4">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2">Загружаем тарифы...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="bezopasnost" class="page">
                <div class="card">
                    <h2 class="card-title">Безопасность</h2>
                    <p style="color: white;">Настройки безопасности вашего аккаунта.</p>
                </div>
            </div>

            <div id="nagruzka" class="page">
                <div class="card">
                    <h2 class="card-title">Нагрузка сервера</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <h5>Загрузка CPU</h5>
                            <div class="display-6" id="cpuLoad">--%</div>
                        </div>
                        <div class="stat-card">
                            <h5>Использование RAM</h5>
                            <div class="display-6" id="ramLoad">--%</div>
                        </div>
                        <div class="stat-card">
                            <h5>Диск (Storage)</h5>
                            <div class="display-6" id="diskLoad">--%</div>
                        </div>
                        <div class="stat-card">
                            <h5>Сеть (Bandwidth)</h5>
                            <div class="display-6" id="networkLoad">--%</div>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="loadChart"></canvas>
                    </div>
                </div>
            </div>

            <div id="vydelennyj-sajt" class="page">
                <div class="card">
                    <h2 class="card-title">Выделенный сайт</h2>
                    <p style="color: white;">Выберите тариф ниже, чтобы заказать новый хостинг!</p>
                    <div class="row g-4" id="siteTariffList">
                        <div class="col-12 text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Загрузка...</span>
                            </div>
                            <p class="mt-2">Загружаем тарифы...</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="podderzhka" class="page">
                <div class="card">
                    <h2 class="card-title">Поддержка</h2>
                    <p style="color: white;">Служба поддержки клиентов.</p>
                </div>
            </div>

            <div id="dokumentaciya" class="page">
                <div class="card">
                    <h2 class="card-title">Документация</h2>
                    <p style="color: white;">Техническая документация и руководства.</p>
                </div>
            </div>

            <div id="kontakty" class="page">
                <div class="card">
                    <h2 class="card-title">Контакты</h2>
                    <p style="color: white;">Контактная информация и способы связи.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Layout -->
    <div class="mobile-container">
        <div class="mobile-header">
            <div class="mobile-header-title">MasterBilling</div>
            <div class="mobile-balance">
                <i class="fas fa-coins"></i>
                <span id="mobileBalance"><?= htmlspecialchars(number_format($balance, 2, ',', ' ')) ?> ₽</span>
            </div>
        </div>

        <div class="mobile-content">
            <div id="mobile-moi-servera" class="page active">
                <h2 class="card-title">Мои сервера</h2>
                <div id="mobile-servicesList" class="server-list">
                    <div class="col-12 text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загружаем ваши сервера...</p>
                    </div>
                </div>
            </div>

            <div id="mobile-game-servers" class="page">
                <h2 class="card-title">Игровые сервера</h2>
                <div class="row g-3" id="mobile-gameTariffList">
                    <div class="col-12 text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загружаем тарифы...</p>
                    </div>
                </div>
            </div>

            <div id="mobile-vds-servers" class="page">
                <h2 class="card-title">VDS Сервера</h2>
                <div id="mobile-vdsTariffList" class="row g-3">
                    <div class="col-12 text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загружаем тарифы...</p>
                    </div>
                </div>
            </div>

            <div id="mobile-profile" class="page">
                <div class="text-center mb-4">
                    <img src="/bill/assets/default_avatar.png" alt="Аватар" class="profile-avatar" id="mobileAvatar">
                    <h4 id="mobile-username"><?= $username ?></h4>
                    <div class="profile-balance">
                        <i class="fas fa-coins"></i>
                        <span id="mobile-profile-balance"><?= htmlspecialchars(number_format($balance, 2, ',', ' ')) ?> ₽</span>
                    </div>
                </div>
                <div class="card">
                    <div class="profile-menu">
                        <div class="profile-menu-item" data-bs-toggle="modal" data-bs-target="#newRechargeModal">
                            <i class="fas fa-wallet"></i>
                            Пополнить баланс
                        </div>
                        <div class="profile-menu-item">
                            <i class="fas fa-user-circle"></i>
                            Профиль
                        </div>
                        <div class="profile-menu-item">
                            <i class="fas fa-cog"></i>
                            Настройки
                        </div>
                        <div class="profile-menu-item" onclick="openLogoutModal(event)">
                            <i class="fas fa-sign-out-alt"></i>
                            Выход
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mobile-tab-bar">
            <div class="mobile-tab active" data-target="mobile-moi-servera">
                <i class="fas fa-server"></i>
                <span>Сервера</span>
            </div>
            <div class="mobile-tab" data-target="mobile-game-servers">
                <i class="fas fa-gamepad"></i>
                <span>Игры</span>
            </div>
            <div class="mobile-tab" data-target="mobile-vds-servers">
                <i class="fas fa-cloud"></i>
                <span>VDS</span>
            </div>
            <div class="mobile-tab" data-target="mobile-profile">
                <i class="fas fa-user"></i>
                <span>Профиль</span>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logoutConfirmModal" class="modal-overlay">
        <div class="modal-content">
            <h4>Вы уверены?</h4>
            <p>Вы действительно хотите выйти из аккаунта?</p>
            <div class="btn-group">
                <button onclick="confirmLogout()" class="btn-modal btn-primary-modal">Да, выйти</button>
                <button onclick="closeLogoutModal()" class="btn-modal btn-secondary-modal">Отмена</button>
            </div>
        </div>
    </div>

    <!-- Inactivity Modal -->
    <div id="inactivityModal" class="modal-overlay">
        <div class="modal-content">
            <h4>Вы были неактивны</h4>
            <p>Для безопасности мы вышли из вашего аккаунта.</p>
            <p>Пожалуйста, войдите снова, чтобы продолжить использовать MasterBilling.</p>
            <button onclick="redirectToLogin()" class="btn-modal btn-primary-modal">Войти снова</button>
        </div>
    </div>

    <!-- Bombsquad Confirmation Modal -->
    <div id="bombsquadModal" class="modal-overlay">
        <div class="modal-content">
            <h4>Переход в BombSquad</h4>
            <p>Вы точно хотите перейти в пространство BombSquad?</p>
            <div class="btn-group">
                <button onclick="confirmBombsquad()" class="btn-modal btn-primary-modal">Да, перейти</button>
                <button onclick="closeBombsquadModal()" class="btn-modal btn-secondary-modal">Отмена</button>
            </div>
        </div>
    </div>

    <!-- Profile Modal -->
    <div class="modal right fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-sm modal-dialog-scrollable">
            <div class="modal-content bg-dark text-white border-0">
                <div class="profile-header">
                    <img src="/bill/assets/default_avatar.png" alt="Аватар" class="profile-avatar" id="modalAvatar">
                    <div class="profile-username" id="modalUsername">Загрузка...</div>
                    <div class="profile-balance">
                        <i class="fas fa-coins"></i>
                        <span id="modalBalance"><?= htmlspecialchars(number_format($balance, 2, ',', ' ')) ?> ₽</span>
                    </div>
                </div>
                <div class="profile-menu">
                    <div class="profile-menu-item" data-bs-toggle="modal" data-bs-target="#newRechargeModal">
                        <i class="fas fa-wallet"></i>
                        Пополнить баланс
                    </div>
                    <div class="profile-menu-item">
                        <i class="fas fa-user-circle"></i>
                        Профиль
                    </div>
                    <div class="profile-menu-item">
                        <i class="fas fa-cog"></i>
                        Настройки
                    </div>
                    <div class="profile-menu-item">
                        <i class="fas fa-credit-card"></i>
                        Управление картами
                    </div>
                    <div class="profile-menu-item" onclick="openLogoutModal(event)">
                        <i class="fas fa-sign-out-alt"></i>
                        Выход
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recharge Modal -->
    <div class="modal fade" id="newRechargeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content recharge-modal">
                <div class="recharge-header">
                    <h5 class="modal-title">
                        <i class="fas fa-wallet text-warning"></i>
                        Пополнить баланс
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="recharge-body">
                    <div class="payment-tabs">
                        <button class="payment-tab active" data-bs-toggle="collapse" data-bs-target="#yookassaSection">ЮKassa</button>
                        <button class="payment-tab" data-bs-toggle="collapse" data-bs-target="#cryptoSection">Криптовалюта</button>
                    </div>
                    
                    <div class="payment-section collapse show" id="yookassaSection">
                        <div class="payment-form">
                            <label class="form-label">Сумма пополнения (мин. 10 ₽)</label>
                            <input type="number" class="form-control mb-3" id="yookassaAmount" value="10" min="10" step="1">
                            <button class="btn btn-success w-100" onclick="submitYookassa()">
                                <i class="fas fa-paper-plane me-1"></i> Перейти к оплате
                            </button>
                        </div>
                    </div>
                    
                    <div class="payment-section collapse" id="cryptoSection">
                        <div class="text-center">
                            <h6 class="mb-3"><i class="fas fa-lock text-warning me-1"></i> Оплата криптовалютой</h6>
                            <div class="alert alert-info">
                                Криптовалютные платежи временно недоступны
                            </div>
                        </div>
                    </div>
                    
                    <div class="security-note">
                        <i class="fas fa-shield-alt"></i>
                        Все платежи защищены. Данные не передаются третьим лицам.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    <?php if (!empty($userServices)): ?>
    const userServices = <?= json_encode($userServices, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
<?php else: ?>
    const userServices = [];
<?php endif; ?>
</script>
    <script>
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Load user data first
            loadUserData();
            
            // Load services
            loadServices();
            
            // Load game tariffs
            loadGameTariffs();
            
            // Setup navigation
            setupNavigation();
            
            // Setup mobile tabs
            setupMobileTabs();
            
            // Initialize chart
            initChart();
            
            // Setup inactivity detection
            setupInactivityDetection();
        });

        function formatCurrency(amount) {
            return new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + 'р';
        }

        function plural(n, forms) {
            n = n % 100;
            if (n >= 11 && n <= 19) return forms[0];
            n = n % 10;
            if (n === 1) return forms[1];
            if (n >= 2 && n <= 4) return forms[2];
            return forms[0];
        }

        function loadUserData() {
    // Все данные уже переданы через PHP
    console.log("Данные пользователя загружены из PHP");
}

        function loadServices() {
    const container = document.getElementById('servicesList');
    const mobileContainer = document.getElementById('mobile-servicesList');
    
    // Показываем загрузку
    container.innerHTML = `
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-2 text-muted">Загружаем ваши сервера...</p>
        </div>
    `;
    
    if (mobileContainer) {
        mobileContainer.innerHTML = `
            <div class="col-12 text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
                <p class="mt-2 text-muted">Загружаем ваши сервера...</p>
            </div>
        `;
    }

    // Используем AJAX запрос
    fetch('/bill/get_user_services.php', {
        headers: {
            'Cache-Control': 'no-cache',
            'Pragma': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Ответ от сервера:', data); // Отладка
        
        if (data.success && data.services && data.services.length > 0) {
            let desktopHTML = '';
            let mobileHTML = '';
            
            data.services.forEach(service => {
                // Отладка: посмотрим, что приходит
                console.log('Сервис:', service.id, 'expires_at:', service.expires_at);
                
                const gameName = service.game || 'Неизвестно';
                const serviceName = service.name || 'Без названия';
                const serviceId = service.service_id || service.id;
                const price = service.price || 0;
                const date = service.created_at ? 
                    new Date(service.created_at).toLocaleDateString('ru-RU', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    }) : 'Нет даты';
                
                const isStandard = service.tariff_name ? 
                    service.tariff_name.trim().toLowerCase() === 'standard' : false;
                
                const hasPanel = service.has_panel == 1 || isStandard || service.setup_status === 'ready';
                
                // Получаем информацию о сроке действия
                const expirationInfo = formatExpirationInfo(service);
                
                // Настройка кнопки
                let buttonText = '';
                let buttonClass = '';
                let buttonUrl = '';
                let buttonDisabled = false;
                
                if (expirationInfo.isExpired) {
                    // Сервер истек
                    buttonText = '<i class="fas fa-redo me-1"></i>Продлить';
                    buttonClass = 'btn-success';
                    buttonUrl = 'javascript:void(0);';
                    buttonDisabled = false;
                    
                    // Добавляем обработчик для кнопки "Продлить"
                    const extendHandler = `extendServerFromList('${serviceId}')`;
                    buttonUrl = 'javascript:' + extendHandler;
                    
                } else if (hasPanel) {
                    // Панель доступна
                    buttonText = '<i class="fas fa-cog me-1"></i>Панель';
                    buttonClass = 'btn-primary';
                    buttonUrl = `/MasterBilling/HomePage/BillingPanel?id=${encodeURIComponent(serviceId)}`;
                } else if (service.setup_status === 'pending') {
                    // В процессе создания
                    buttonText = '<i class="fas fa-spinner fa-spin me-1"></i>Ожидайте';
                    buttonClass = 'btn-secondary';
                    buttonUrl = 'javascript:void(0);';
                    buttonDisabled = true;
                } else {
                    // Нужна настройка
                    buttonText = '<i class="fas fa-wrench me-1"></i>Настроить';
                    buttonClass = 'btn-outline-light';
                    buttonUrl = `/bill/server_settings/?id=${encodeURIComponent(serviceId)}`;
                }
                
                // Десктопная версия
                desktopHTML += `
                <div class="server-item ${expirationInfo.isExpired ? 'expired-server' : ''}">
                    <div class="server-info">
                        <div class="d-flex align-items-center mb-2">
                            <span class="me-2">${gameName}</span>
                            ${isStandard ? '<span class="badge bg-primary" style="font-size: 0.7rem;">STANDARD</span>' : ''}
                            ${expirationInfo.badge}
                        </div>
                        <h6 class="mb-1">${serviceName}</h6>
                        <div class="small text-muted mb-2">
                            ID: ${serviceId} | ${formatCurrency(price)} | Создан: ${date}
                        </div>
                        
                        <!-- Блок срока действия -->
                        <div class="expiration-info mb-2">
                            ${expirationInfo.html}
                        </div>
                        
                        <!-- Статус панели -->
                        <div class="status-info mb-2">
                            ${hasPanel ? 
                                '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Панель доступна</span>' : 
                                service.setup_status === 'pending' ?
                                '<span class="text-warning"><i class="fas fa-sync-alt fa-spin me-1"></i>Подготовка панели...</span>' :
                                '<span class="text-info"><i class="fas fa-cog me-1"></i>Требуется настройка</span>'
                            }
                        </div>
                        
                        ${service.vds_ip ? `
                        <div class="vds-info small">
                            <i class="fas fa-server me-1"></i>VDS: ${service.vds_ip}
                        </div>
                        ` : ''}
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <a href="${buttonUrl}" 
                           class="btn ${buttonClass} ${buttonDisabled ? 'disabled' : ''}" 
                           ${buttonDisabled ? 'aria-disabled="true"' : ''}>
                            ${buttonText}
                        </a>
                        ${expirationInfo.isExpired ? '' : `
                            <button class="btn btn-sm btn-outline-warning" onclick="extendServerFromList('${serviceId}')">
                                <i class="fas fa-redo me-1"></i>Продлить
                            </button>
                        `}
                    </div>
                </div>`;
                
                // Мобильная версия
                mobileHTML += `
                <div class="server-item ${expirationInfo.isExpired ? 'expired-server' : ''}">
                    <div class="server-info">
                        <div class="d-flex align-items-center mb-1">
                            <strong>${gameName}</strong>
                            ${isStandard ? '<span class="badge bg-primary ms-1" style="font-size: 0.6rem;">STD</span>' : ''}
                            ${expirationInfo.badge}
                        </div>
                        <div class="small mb-1">${serviceName}</div>
                        <div class="expiration-info small mb-2">
                            ${expirationInfo.html}
                        </div>
                    </div>
                    <div class="d-flex gap-1">
                        <a href="${buttonUrl}" 
                           class="btn ${buttonClass} btn-sm ${buttonDisabled ? 'disabled' : ''}" 
                           style="min-width: 80px;">
                            ${buttonText.replace(/<i[^>]*>/, '').replace(/<\/i>/, '')}
                        </a>
                        ${expirationInfo.isExpired ? '' : `
                            <button class="btn btn-sm btn-outline-warning btn-sm" onclick="extendServerFromList('${serviceId}')">
                                <i class="fas fa-redo"></i>
                            </button>
                        `}
                    </div>
                </div>`;
            });
            
            container.innerHTML = desktopHTML;
            if (mobileContainer) mobileContainer.innerHTML = mobileHTML;
            
        } else {
            // Нет серверов
            const emptyState = `
                <div class="empty-state text-center py-5">
                    <div class="mb-4">
                        <i class="fas fa-server fa-4x text-muted mb-3"></i>
                    </div>
                    <h4 class="mb-3">У вас пока нет серверов</h4>
                    <p class="text-muted mb-4">Закажите первый игровой сервер, чтобы он появился здесь</p>
                    <a href="#virtualnye-servera" class="btn btn-primary" onclick="showPage('virtualnye-servera')">
                        <i class="fas fa-plus me-2"></i>Заказать сервер
                    </a>
                </div>
            `;
            container.innerHTML = emptyState;
            if (mobileContainer) mobileContainer.innerHTML = emptyState;
        }
    })
    .catch(error => {
        console.error('Ошибка загрузки серверов:', error);
        
        const errorHTML = `
            <div class="empty-state text-center py-5">
                <div class="mb-4">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                </div>
                <h4 class="mb-3">Ошибка загрузки</h4>
                <p class="text-muted mb-3">Не удалось загрузить список серверов</p>
                <p class="small text-muted mb-4">Пожалуйста, попробуйте позже или обратитесь в поддержку</p>
                <button onclick="loadServices()" class="btn btn-outline-light">
                    <i class="fas fa-redo me-2"></i>Повторить попытку
                </button>
            </div>
        `;
        
        container.innerHTML = errorHTML;
        if (mobileContainer) mobileContainer.innerHTML = errorHTML;
    });
}

// Добавьте эту функцию для продления из списка
function extendServerFromList(serviceId) {
    // Получаем цены из базы данных
    fetch(`/bill/extend_service.php?action=get_prices&service_id=${serviceId}`)
        .then(response => response.json())
        .then(data => {
            if (!data.success || !data.renewal_enabled) {
                showNotification('Продление не доступно для этого сервера', 'error');
                return;
            }
            
            // Создаем модальное окно с ценами из базы
            let priceOptions = '';
            let hasCustom = false;
            let dailyPrice = 0;
            
            // В функции extendServerFromList обновите создание карточек:
data.prices.forEach(price => {
    if (price.days === 'custom') {
        hasCustom = true;
        dailyPrice = price.daily_price;
    } else {
        const isPopular = price.days === 30;
        const discountBadge = price.discount ? 
            `<div class="discount-badge">-${price.discount}%</div>` : '';
        
        const icon = price.days === 1 ? 'fa-calendar-day' : 
                    price.days === 7 ? 'fa-calendar-week' : 
                    'fa-calendar-alt';
        
        priceOptions += `
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="price-option-card ${isPopular ? 'popular' : ''}" 
                     onclick="selectPriceOption('${serviceId}', ${price.days}, ${price.amount}, ${price.daily_price})">
                    ${discountBadge}
                    <div class="price-header">
                        <i class="fas ${icon}"></i>
                        <h6>${price.label}</h6>
                    </div>
                    <div class="price-body">
                        <div class="price-main">
                            <span class="price-amount">${price.amount}₽</span>
                            <small class="price-per-day">${price.daily_price}₽/день</small>
                        </div>
                        <div class="price-features">
                            <small><i class="fas fa-check text-success me-2"></i>Автоматическое продление</small>
                            <small><i class="fas fa-bolt text-warning me-2"></i>Мгновенная активация</small>
                        </div>
                        <button class="btn-select">
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
});
            
            let customOption = '';
            if (hasCustom) {
                const customPrice = data.prices.find(p => p.days === 'custom');
                customOption = `
                    <div class="custom-period-card mt-4">
                        <div class="card-header bg-gradient-secondary">
                            <h6 class="mb-0"><i class="fas fa-calculator me-2"></i>Свой период</h6>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Количество дней (от ${customPrice.min_days} до ${customPrice.max_days}):</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
                                            <input type="number" id="customDays" class="form-control" 
                                                   value="30" min="${customPrice.min_days}" max="${customPrice.max_days}">
                                            <span class="input-group-text">дней</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="price-preview text-center">
                                        <div class="estimated-price-label">Примерная цена</div>
                                        <div class="estimated-price" id="customPricePreview">${(dailyPrice * 30).toFixed(2)}₽</div>
                                        <small class="text-muted">${dailyPrice}₽ × <span id="daysPreview">30</span> дней</small>
                                    </div>
                                </div>
                            </div>
                            <div class="d-grid gap-2 mt-3">
                                <button class="btn btn-gradient-primary" onclick="extendCustomPeriod('${serviceId}', ${dailyPrice})">
                                    <i class="fas fa-calculator me-2"></i>Рассчитать и продлить
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            // Форматируем дату истечения
            let expiresInfo = '';
            if (data.current_expires) {
                const expiresDate = new Date(data.current_expires);
                const now = new Date();
                const diffTime = expiresDate.getTime() - now.getTime();
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                expiresInfo = `
                    <div class="expiry-info">
                        <div class="row">
                            <div class="col-6">
                                <div class="info-item">
                                    <small class="text-muted">Текущий срок</small>
                                    <div>${expiresDate.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })}</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-item">
                                    <small class="text-muted">Осталось дней</small>
                                    <div class="${diffDays <= 7 ? 'text-warning' : 'text-success'}">
                                        <strong>${diffDays > 0 ? diffDays : 'Истек'}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            const modalHtml = `
    <div class="modal fade" id="extendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"> <!-- вот здесь должен быть modal-lg -->
        <div class="modal-content">
                            <div class="modal-header bg-gradient-primary text-white">
                                <div class="d-flex align-items-center">
                                    <div class="modal-icon">
                                        <i class="fas fa-redo"></i>
                                    </div>
                                    <div class="ms-3">
                                        <h5 class="modal-title mb-1">Продлить сервер</h5>
                                        <small class="opacity-75">Тариф: <strong>${data.tariff_name}</strong></small>
                                    </div>
                                </div>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-lg-8">
                                        ${expiresInfo}
                                        
                                        <div class="section-title mt-4">
                                            <h6><i class="fas fa-clock me-2"></i>Выберите период продления</h6>
                                        </div>
                                        
                                        <div class="row g-3">
                                            ${priceOptions}
                                        </div>
                                        
                                        ${customOption}
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="info-sidebar">
                                            <div class="info-card">
                                                <h6><i class="fas fa-info-circle me-2"></i>Информация</h6>
                                                <ul class="info-list">
                                                    <li><i class="fas fa-check text-success me-2"></i>Продление происходит моментально</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Сервер не отключается</li>
                                                    <li><i class="fas fa-check text-success me-2"></i>Доступ сохраняется</li>
                                                    <li><i class="fas fa-wallet text-info me-2"></i>Списание с баланса</li>
                                                </ul>
                                            </div>
                                            
                                            <div class="balance-card">
                                                <small class="text-muted">Ваш баланс</small>
                                                <div class="balance-amount">
                                                    <i class="fas fa-coins text-warning"></i>
                                                    <span id="currentBalance">${document.getElementById('balanceAmount')?.textContent || '0.00₽'}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="tips-card">
                                                <small class="text-muted"><i class="fas fa-lightbulb me-1"></i>Совет</small>
                                                <p class="small mb-0">Чем больше дней вы выбираете, тем дешевле получается каждый день!</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Отмена
                                </button>
                                <div class="ms-auto">
                                    <small class="text-muted me-3">Выбранный период: <span id="selectedPeriod">Не выбран</span></small>
                                    <span id="selectedAmount" class="fw-bold">0₽</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Удаляем старую модалку если есть
            const oldModal = document.getElementById('extendModal');
            if (oldModal) oldModal.remove();
            
            // Добавляем новую
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Обновляем обработчик для расчета цены
            if (hasCustom) {
                setTimeout(() => {
                    const customDaysInput = document.getElementById('customDays');
                    const daysPreview = document.getElementById('daysPreview');
                    
                    if (customDaysInput) {
                        customDaysInput.addEventListener('input', function() {
                            const days = parseInt(this.value) || 30;
                            const preview = document.getElementById('customPricePreview');
                            if (preview) {
                                preview.textContent = (dailyPrice * days).toFixed(2) + '₽';
                            }
                            if (daysPreview) {
                                daysPreview.textContent = days;
                            }
                        });
                    }
                }, 100);
            }
            
            const modal = new bootstrap.Modal(document.getElementById('extendModal'));
            modal.show();
            
            // Добавляем анимацию при показе
            setTimeout(() => {
                const modalElement = document.getElementById('extendModal');
                if (modalElement) {
                    modalElement.classList.add('show');
                }
            }, 10);
        })
        .catch(error => {
            console.error('Ошибка получения цен:', error);
            showNotification('Ошибка загрузки цен продления', 'error');
        });
}

// Функция для выбора опции цены
function selectPriceOption(serviceId, days, amount, dailyPrice) {
    // Обновляем информацию в футере модалки
    const selectedPeriod = document.getElementById('selectedPeriod');
    const selectedAmount = document.getElementById('selectedAmount');
    
    if (selectedPeriod) {
        selectedPeriod.textContent = `${days} дней`;
    }
    if (selectedAmount) {
        selectedAmount.textContent = `${amount}₽`;
    }
    
    // Подсветка выбранной опции
    document.querySelectorAll('.price-option-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Показываем кнопку подтверждения
    const footer = document.querySelector('.modal-footer');
    const confirmButton = footer.querySelector('.btn-primary');
    
    if (!confirmButton) {
        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'btn btn-gradient-success ms-3';
        confirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>Подтвердить';
        confirmBtn.onclick = () => extendServerPeriod(serviceId, days, amount);
        footer.appendChild(confirmBtn);
    }
}

// Обновленная функция для продления пользовательского периода
function extendCustomPeriod(serviceId, dailyPrice) {
    const customDaysInput = document.getElementById('customDays');
    if (!customDaysInput) return;
    
    const days = parseInt(customDaysInput.value);
    if (isNaN(days) || days < 1) {
        showNotification('Введите корректное количество дней', 'error');
        return;
    }
    
    const amount = dailyPrice * days;
    
    // Обновляем информацию в футере
    const selectedPeriod = document.getElementById('selectedPeriod');
    const selectedAmount = document.getElementById('selectedAmount');
    
    if (selectedPeriod) selectedPeriod.textContent = `${days} дней`;
    if (selectedAmount) selectedAmount.textContent = `${amount.toFixed(2)}₽`;
    
    // Подсвечиваем кастомную опцию
    document.querySelectorAll('.price-option-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Добавляем кнопку подтверждения
    const footer = document.querySelector('.modal-footer');
    const existingBtn = footer.querySelector('.btn-gradient-success');
    
    if (existingBtn) {
        existingBtn.onclick = () => extendServerPeriod(serviceId, days, amount);
    } else {
        const confirmBtn = document.createElement('button');
        confirmBtn.className = 'btn btn-gradient-success ms-3';
        confirmBtn.innerHTML = '<i class="fas fa-check me-2"></i>Подтвердить';
        confirmBtn.onclick = () => extendServerPeriod(serviceId, days, amount);
        footer.appendChild(confirmBtn);
    }
}

// Обновим функцию updateBalance для обновления при продлении
function updateBalance(newBalance) {
    // Обновляем баланс во всех местах
    const balanceElements = [
        document.getElementById('balanceAmount'),
        document.getElementById('mobileBalance'),
        document.getElementById('mobile-profile-balance'),
        document.getElementById('modalBalance')
    ];
    
    balanceElements.forEach(element => {
        if (element) {
            element.textContent = new Intl.NumberFormat('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(newBalance) + ' ₽';
        }
    });
}

// Функция для показа уведомлений
function showNotification(message, type = 'info') {
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center border-0 ${type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-primary'}" 
             role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body text-white">
                    ${type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : 
                      type === 'error' ? '<i class="fas fa-exclamation-circle me-2"></i>' : 
                      '<i class="fas fa-info-circle me-2"></i>'}
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const container = document.getElementById('toast-container');
    container.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastEl = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
    toast.show();
    
    toastEl.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}

        function loadGameTariffs() {
            const container = document.getElementById('gameTariffList');
            const mobileContainer = document.getElementById('mobile-gameTariffList');
            
            // Используем данные из PHP
            const gameTariffs = <?php echo json_encode($gameTariffs, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
            
            if (!gameTariffs || gameTariffs.length === 0) {
                container.innerHTML = `<div class="col-12 text-center"><p class="text-muted">Нет доступных тарифов.</p></div>`;
                if (mobileContainer) {
                    mobileContainer.innerHTML = `<div class="col-12 text-center"><p class="text-muted">Нет доступных тарифов.</p></div>`;
                }
                return;
            }

            let desktopHTML = '';
            let mobileHTML = '';
            const games = { 'bombsquad': 'BombSquad', 'minecraft': 'Minecraft', 'rust': 'Rust' };

            Object.entries(games).forEach(([gameKey, gameName]) => {
                const tariffs = gameTariffs.filter(t => t.game === gameKey);
                if (tariffs.length > 0) {
                    desktopHTML += `<h4 class="mb-3 game-title">${gameName}</h4>`;
                    mobileHTML += `<h5 class="mt-4 mb-3 game-title">${gameName}</h5>`;

                    tariffs.forEach(tariff => {
                        const isStandard = tariff.name.trim().toLowerCase() === 'standard';
                        
                        // Десктопная версия
                        let desktopCard = `
                        <div class="col-md-6">
                            <div class="pricing-card ${isStandard ? 'standard-tariff' : ''}">
                                ${isStandard ? '<div class="text-center mb-3"><span class="badge bg-primary" style="font-size: 0.8rem;">ПОПУЛЯРНЫЙ</span></div>' : ''}
                                <h3 ${isStandard ? 'style="color: #3498db;"' : ''}>${tariff.name} ${isStandard ? '<i class="fas fa-crown text-warning"></i>' : ''}</h3>
                                <h5>Цена: <strong ${isStandard ? 'style="color: #3498db;"' : ''}>${formatCurrency(tariff.price)}</strong></h5>
                                <ul class="list-unstyled">
                                    ${isStandard ? '<li><i class="fas fa-star text-warning"></i> Премиум-панель</li>' : ''}
                                    <li><i class="fas fa-memory"></i> ${tariff.ram || 'Не указано'}</li>
                                    <li><i class="fas fa-microchip"></i> ${tariff.cpu || 'Не указано'}</li>
                                    <li><i class="fas fa-hdd"></i> ${tariff.storage || 'Не указано'}</li>
                                    <li><i class="fas fa-broadcast-tower"></i> ${tariff.bandwidth || 'Не указано'}</li>
                                    ${isStandard ? '<li><i class="fas fa-shield-alt text-success"></i> Авто-бэкапы</li>' : ''}
                                </ul>
                                ${isStandard ? 
                                    `<button class="btn btn-primary w-100" 
                                            style="background: linear-gradient(135deg, #3498db, #2980b9); border: none;"
                                            onclick="orderStandardTariff(${tariff.id}, '${tariff.name}', ${tariff.price}, '${tariff.game}')">
                                        <i class="fas fa-bolt"></i> Заказать сейчас
                                    </button>` : 
                                    `<button class="btn btn-outline-light w-100" 
                                            onclick="openGameServerConfigModal(${tariff.id}, '${tariff.name}', ${tariff.price}, '${tariff.game}')">
                                        Настроить и заказать
                                    </button>`
                                }
                            </div>
                        </div>`;
                        desktopHTML += desktopCard;

                        // Мобильная версия
                        let mobileCard = `
                        <div class="col-12">
                            <div class="card ${isStandard ? 'bg-primary bg-opacity-10 border-primary' : 'bg-dark'}">
                                <div class="card-body">
                                    <h5 class="card-title ${isStandard ? 'text-primary' : ''}">${tariff.name} ${isStandard ? '<i class="fas fa-crown text-warning"></i>' : ''}</h5>
                                    <p class="card-text ${isStandard ? 'text-primary' : 'text-success'}">${formatCurrency(tariff.price)}/${tariff.duration_months} мес</p>
                                    ${isStandard ? '<p class="text-warning small"><i class="fas fa-star"></i> Премиум-панель</p>' : ''}
                                    <ul class="list-unstyled small">
                                        <li><i class="fas fa-memory me-2"></i>${tariff.ram || 'Не указано'}</li>
                                        <li><i class="fas fa-microchip me-2"></i>${tariff.cpu || 'Не указано'}</li>
                                        <li><i class="fas fa-hdd me-2"></i>${tariff.storage || 'Не указано'}</li>
                                    </ul>
                                    ${isStandard ? 
                                        `<button class="btn btn-primary btn-sm w-100"
                                                onclick="orderStandardTariff(${tariff.id}, '${tariff.name}', ${tariff.price}, '${tariff.game}')">
                                            <i class="fas fa-bolt me-1"></i> Заказать
                                        </button>` : 
                                        `<button class="btn btn-outline-light btn-sm w-100"
                                                onclick="openGameServerConfigModal(${tariff.id}, '${tariff.name}', ${tariff.price}, '${tariff.game}')">
                                            Настроить
                                        </button>`
                                    }
                                </div>
                            </div>
                        </div>`;
                        mobileHTML += mobileCard;
                    });
                }
            });

            if (desktopHTML === '') {
                desktopHTML = `<div class="col-12 text-center"><p class="text-muted">Нет доступных тарифов.</p></div>`;
                mobileHTML = `<div class="col-12 text-center"><p class="text-muted">Нет доступных тарифов.</p></div>`;
            }

            container.innerHTML = desktopHTML;
            if (mobileContainer) mobileContainer.innerHTML = mobileHTML;
        }



function openGameServerConfigModal(tariffId, tariffName, price, game) {
    // Устанавливаем базовые данные
    document.getElementById('tariffId').value = tariffId;
    document.getElementById('gameKey').value = game;
    document.getElementById('serverName').value = `Мой ${tariffName}`;
    document.getElementById('totalPrice').textContent = `Цена: ${formatCurrency(price)}`;

    // Очищаем динамические поля
    const dynamicOptions = document.getElementById('dynamicOptions');
    dynamicOptions.innerHTML = '';

    // Устанавливаем заголовок
    const gameNames = { 'bombsquad': 'BombSquad', 'minecraft': 'Minecraft', 'rust': 'Rust' };
    document.getElementById('modalGameTitle').textContent = `Настройка: ${gameNames[game] || game} (${tariffName})`;

    // Проверяем, это ли тариф Standard
    const isStandard = tariffName.trim().toLowerCase() === 'standard';
    
    if (isStandard) {
        // Для тарифа Standard оставляем ТОЛЬКО название сервера
        dynamicOptions.innerHTML = `
            <div class="mb-3">
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Тариф Standard включает премиум-панель управления с полным функционалом
                </div>
            </div>
        `;
        
        // Убираем выбор слотов для Standard
        document.getElementById('slots').style.display = 'none';
        document.querySelector('label[for="slots"]').style.display = 'none';
        
    } else {
        // Для всех остальных тарифов — стандартное поведение
        
        // Показываем выбор слотов
        document.getElementById('slots').style.display = 'block';
        document.querySelector('label[for="slots"]').style.display = 'block';
        
        if (game === 'minecraft') {
            dynamicOptions.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Режим игры</label>
                    <select class="form-control" name="game_mode">
                        <option value="survival">Выживание</option>
                        <option value="creative">Творчество</option>
                        <option value="adventure">Приключение</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Сложность</label>
                    <select class="form-control" name="difficulty">
                        <option value="peaceful">Мирная</option>
                        <option value="easy">Лёгкая</option>
                        <option value="normal">Нормальная</option>
                        <option value="hard">Сложная</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Модификации</label>
                    <select class="form-control" name="mods">
                        <option value="vanilla">Vanilla (без модов)</option>
                        <option value="spigot">Spigot</option>
                        <option value="paper">Paper</option>
                        <option value="forge">Forge (моды)</option>
                        <option value="fabric">Fabric</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Версия Minecraft</label>
                    <select class="form-control" name="version">
                        <option value="1.20.4">1.20.4</option>
                        <option value="1.19.4">1.19.4</option>
                        <option value="1.18.2">1.18.2</option>
                        <option value="1.16.5">1.16.5</option>
                    </select>
                </div>
            `;
        } else if (game === 'rust') {
            dynamicOptions.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Тип сервера</label>
                    <select class="form-control" name="server_type">
                        <option value="vanilla">Vanilla</option>
                        <option value="modded">С модами (Oxide)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Режим PVP</label>
                    <select class="form-control" name="pvp_mode">
                        <option value="always">Всегда</option>
                        <option value="limited">Ограниченный</option>
                        <option value="never">Никогда</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Размер карты</label>
                    <select class="form-control" name="map_size">
                        <option value="3000">3000</option>
                        <option value="4000">4000</option>
                        <option value="5000">5000</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Seed (семя карты)</label>
                    <input type="number" class="form-control" name="seed" placeholder="Оставить пустым для случайного">
                </div>
            `;
        } else if (game === 'bombsquad') {
            dynamicOptions.innerHTML = `
                <div class="mb-3">
                    <label class="form-label">Режим игры</label>
                    <select class="form-control" name="game_mode">
                        <option value="ffa">Free-for-All</option>
                        <option value="teams">Команды</option>
                        <option value="cooperative">Кооператив</option>
                        <option value="race">Гонка</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Карты</label>
                    <select class="form-control" name="map">
                        <option value="doodle">Doodle Domain</option>
                        <option value="rampage">Rampage</option>
                        <option value="football">Football Stadium</option>
                        <option value="tower">Collapsing Tower</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Длительность матча (мин)</label>
                    <input type="number" class="form-control" name="match_duration" value="5" min="1" max="20">
                </div>
                <div class="mb-3">
                    <label class="form-label">
                        <input type="checkbox" name="pro_mode"> Про-режим (без подсказок)
                    </label>
                </div>
            `;
        }
    }

    // Показываем модальное окно
    document.getElementById('gameServerConfigModal').style.display = 'flex';
}



function closeGameServerModal() {
    document.getElementById('gameServerConfigModal').style.display = 'none';
}

const formData = new FormData(this);
// Все поля (включая game_mode, mods, difficulty и т.д.) попадут в URL
const tariffId = formData.get('tariff_id');
const serverName = encodeURIComponent(formData.get('server_name'));
const slots = formData.get('slots');
const difficulty = formData.get('difficulty') || '';
const gameMode = formData.get('game_mode') || '';
const mods = formData.get('mods') || '';
const game = formData.get('game');

const url = `/bill/order.php?tariff_id=${tariffId}&server_name=${serverName}&slots=${slots}&difficulty=${difficulty}&game_mode=${gameMode}&mods=${mods}&game=${game}`;





        function loadVdsTariffs() {
            const container = document.getElementById('vdsTariffList');
            const mobileContainer = document.getElementById('mobile-vdsTariffList');
            
            if (!container) return;
            
            container.innerHTML = `
                <div class="col-12 text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-2">Загружаем тарифы...</p>
                </div>
            `;
            
            if (mobileContainer) {
                mobileContainer.innerHTML = `
                    <div class="col-12 text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Загрузка...</span>
                        </div>
                        <p class="mt-2">Загружаем тарифы...</p>
                    </div>
                `;
            }
            
            fetch('/bill/get_vds_tariffs.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.tariffs && data.tariffs.length > 0) {
                        let desktopHTML = '';
                        let mobileHTML = '';
                        
                        data.tariffs.forEach(tariff => {
                            desktopHTML += `
                            <div class="col-md-4">
                                <div class="pricing-card">
                                    <h3>${tariff.name}</h3>
                                    <h5>Цена: <strong>${formatCurrency(tariff.price)}</strong></h5>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-memory"></i> ${tariff.ram}</li>
                                        <li><i class="fas fa-microchip"></i> ${tariff.cpu}</li>
                                        <li><i class="fas fa-hdd"></i> ${tariff.storage}</li>
                                        <li><i class="fas fa-broadcast-tower"></i> ${tariff.bandwidth}</li>
                                        <li><i class="fas fa-laptop"></i> ${tariff.os}</li>
                                    </ul>
                                    <a href="/bill/order_vds.php?tariff_id=${tariff.id}" class="btn btn-outline-light w-100">Заказать</a>
                                </div>
                            </div>`;
                            
                            mobileHTML += `
                            <div class="col-12">
                                <div class="card bg-dark">
                                    <div class="card-body">
                                        <h5 class="card-title">${tariff.name}</h5>
                                        <p class="card-text text-success">₽${tariff.price}/${tariff.duration_months} мес</p>
                                        <ul class="list-unstyled small">
                                            <li><i class="fas fa-memory me-2"></i>${tariff.ram}</li>
                                            <li><i class="fas fa-microchip me-2"></i>${tariff.cpu}</li>
                                            <li><i class="fas fa-hdd me-2"></i>${tariff.storage}</li>
                                            <li><i class="fas fa-broadcast-tower me-2"></i>${tariff.bandwidth}</li>
                                        </ul>
                                        <a href="/bill/order_vds.php?tariff_id=${tariff.id}" class="btn btn-outline-light btn-sm w-100">Заказать</a>
                                    </div>
                                </div>
                            </div>`;
                        });
                        
                        container.innerHTML = desktopHTML;
                        if (mobileContainer) mobileContainer.innerHTML = mobileHTML;
                    } else {
                        container.innerHTML = `
                            <div class="col-12 text-center">
                                <p class="text-muted">Нет доступных тарифов.</p>
                            </div>
                        `;
                        if (mobileContainer) {
                            mobileContainer.innerHTML = `
                                <div class="col-12 text-center">
                                    <p class="text-muted">Нет доступных тарифов.</p>
                                </div>
                            `;
                        }
                    }
                })
                .catch(err => {
                    console.error('Ошибка загрузки VDS тарифов:', err);
                    container.innerHTML = `
                        <div class="col-12 text-center">
                            <p class="text-danger">Не удалось загрузить тарифы.</p>
                        </div>
                    `;
                    if (mobileContainer) {
                        mobileContainer.innerHTML = `
                            <div class="col-12 text-center">
                                <p class="text-danger">Не удалось загрузить тарифы.</p>
                            </div>
                        `;
                    }
                });
        }

        function loadSiteTariffs() {
            const container = document.getElementById('siteTariffList');
            if (!container) return;
            
            container.innerHTML = `
                <div class="col-12 text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-2">Загружаем тарифы...</p>
                </div>
            `;
            
            fetch('/bill/get_site_tariffs.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.tariffs && data.tariffs.length > 0) {
                        let html = '';
                        
                        data.tariffs.forEach(tariff => {
                            let priceHtml = `<strong>₽${parseFloat(tariff.price).toFixed(2)}</strong>`;
                            
                            if (tariff.is_on_sale && tariff.sale_price) {
                                priceHtml = `
                                    <del class="text-muted">₽${parseFloat(tariff.price).toFixed(2)}</del>
                                    <strong class="text-success fs-5"> ₽${parseFloat(tariff.sale_price).toFixed(2)}</strong>
                                    <small class="d-block text-warning">Акция!</small>
                                `;
                            }
                            
                            html += `
                            <div class="col-md-4">
                                <div class="pricing-card">
                                    <h3>${tariff.name}</h3>
                                    <h5>Цена: ${priceHtml}</h5>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-globe"></i> ${tariff.sites_count} сайт${plural(tariff.sites_count, ['ов', '', 'а'])}</li>
                                        <li><i class="fas fa-hdd"></i> ${tariff.disk_space_gb} ГБ</li>
                                        <li><i class="fas fa-broadcast-tower"></i> ${tariff.bandwidth_gb} ГБ</li>
                                        <li><i class="fas fa-check ${tariff.phpmyadmin ? '' : 'text-danger'}"></i> phpMyAdmin</li>
                                        <li><i class="fas fa-shield-alt ${tariff.ssl ? '' : 'text-danger'}"></i> SSL</li>
                                        <li><i class="fas fa-rotate ${tariff.backups ? '' : 'text-danger'}"></i> Бэкапы</li>
                                    </ul>
                                    <a href="/bill/order_site.php?tariff_id=${tariff.id}" class="btn btn-outline-light w-100">Заказать</a>
                                </div>
                            </div>`;
                        });
                        
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = `
                            <div class="col-12 text-center">
                                <p class="text-muted">Нет доступных тарифов.</p>
                            </div>
                        `;
                    }
                })
                .catch(err => {
                    console.error('Ошибка загрузки тарифов хостинга:', err);
                    container.innerHTML = `
                        <div class="col-12 text-center">
                            <p class="text-danger">Не удалось загрузить тарифы.</p>
                        </div>
                    `;
                });
        }
        
        
        function orderStandardTariff(tariffId, tariffName, price, game) {
            // Показываем загрузку
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Оформляем...';
            button.disabled = true;
            
            // Генерируем название сервера по умолчанию
            const serverName = `Мой ${tariffName} сервер`;
            
            // Отправляем POST запрос
            const formData = new FormData();
            formData.append('tariff_id', tariffId);
            formData.append('server_name', serverName);
            formData.append('game', game);
            formData.append('control_panel', 3);
            formData.append('slots', 10); // По умолчанию 10 слотов
            
            fetch('/bill/order_game.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Ответ от сервера:', data);
                
                if (data.success) {
                    // Успешный заказ
                    button.innerHTML = '<i class="fas fa-check"></i> Заказано!';
                    button.style.background = 'linear-gradient(135deg, #2ecc71, #27ae60)';
                    button.style.border = 'none';
                    button.style.boxShadow = '0 4px 15px rgba(46, 204, 113, 0.4)';
                    
                    // Обновляем баланс
                    if (data.new_balance !== undefined) {
                        updateBalance(data.new_balance);
                    }
                    
                    // Показываем уведомление
                    showSuccessNotification(data, serverName);
                    
                    // Обновляем список серверов через 2 секунды
                    setTimeout(() => {
                        loadServices();
                    }, 2000);
                    
                    // Скрываем кнопку через 5 секунд
                    setTimeout(() => {
                        button.style.opacity = '0.7';
                        button.style.cursor = 'default';
                        button.setAttribute('disabled', true);
                    }, 5000);
                    
                } else {
                    // Ошибка заказа
                    button.innerHTML = originalText;
                    button.disabled = false;
                    showErrorNotification(data.message || 'Ошибка заказа');
                }
            })
            .catch(error => {
                // Ошибка сети или парсинга JSON
                button.innerHTML = originalText;
                button.disabled = false;
                console.error('Ошибка заказа:', error);
                showErrorNotification('Ошибка соединения. Проверьте консоль для подробностей.');
            });
        }

        function updateBalance(newBalance) {
            // Обновляем баланс во всех местах
            const balanceElements = [
                document.getElementById('balanceAmount'),
                document.getElementById('mobileBalance'),
                document.getElementById('mobile-profile-balance'),
                document.getElementById('modalBalance')
            ];
            
            balanceElements.forEach(element => {
                if (element) {
                    element.textContent = new Intl.NumberFormat('ru-RU', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }).format(newBalance) + ' ₽';
                }
            });
        }

function showSuccessNotification(data, serverName) {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #1a1f25, #2c3e50);
                    color: white;
                    padding: 20px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                    z-index: 10000;
                    max-width: 400px;
                    animation: slideInRight 0.3s ease;
                    border-left: 4px solid #2ecc71;
                    font-family: 'Segoe UI', sans-serif;
                ">
                    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 15px;">
                        <div style="
                            background: linear-gradient(135deg, #2ecc71, #27ae60);
                            width: 50px;
                            height: 50px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            font-size: 24px;
                        ">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 18px;">Сервер заказан!</div>
                            <div style="font-size: 14px; opacity: 0.9; margin-top: 3px;">${serverName}</div>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="
                            background: none;
                            border: none;
                            color: rgba(255,255,255,0.5);
                            font-size: 20px;
                            cursor: pointer;
                            margin-left: auto;
                            transition: color 0.2s;
                        ">
                            ×
                        </button>
                    </div>
                    
                    <div style="background: rgba(255,255,255,0.1); border-radius: 10px; padding: 15px; margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="opacity: 0.8;">ID сервера:</span>
                            <span style="font-family: monospace; font-weight: 600;">${data.service_id}</span>
                        </div>
                        ${data.vds_id ? `
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="opacity: 0.8;">VDS:</span>
                            <span style="font-weight: 600;">${data.vds_id}</span>
                        </div>
                        ` : ''}
                        ${data.vds_ip ? `
                        <div style="display: flex; justify-content: space-between;">
                            <span style="opacity: 0.8;">IP:</span>
                            <span style="font-weight: 600;">${data.vds_ip}</span>
                        </div>
                        ` : ''}
                    </div>
                    
                    ${data.has_panel ? `
                    <div style="background: rgba(46, 204, 113, 0.1); border-radius: 10px; padding: 12px; border: 1px solid rgba(46, 204, 113, 0.2);">
                        <div style="display: flex; align-items: center; gap: 10px; color: #2ecc71;">
                            <i class="fas fa-check-circle"></i>
                            <span style="font-size: 14px;">Панель управления доступна сразу!</span>
                        </div>
                        ${data.panel_url ? `<a href="${data.panel_url}" style="display: inline-block; margin-top: 8px; color: #3498db; text-decoration: none; font-size: 13px;">Перейти в панель управления →</a>` : ''}
                    </div>
                    ` : `
                    <div style="background: rgba(52, 152, 219, 0.1); border-radius: 10px; padding: 12px; border: 1px solid rgba(52, 152, 219, 0.2);">
                        <div style="display: flex; align-items: center; gap: 10px; color: #3498db;">
                            <i class="fas fa-sync-alt fa-spin"></i>
                            <span style="font-size: 14px;">Сервер создается. Панель управления будет доступна через 3-5 минут</span>
                        </div>
                    </div>
                    `}
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Автоматически скрываем через 8 секунд
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 8000);
        }

function showErrorNotification(message) {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #e74c3c, #c0392b);
                    color: white;
                    padding: 20px;
                    border-radius: 15px;
                    box-shadow: 0 10px 30px rgba(231, 76, 60, 0.3);
                    z-index: 10000;
                    max-width: 400px;
                    animation: slideInRight 0.3s ease;
                    border-left: 4px solid #e74c3c;
                ">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 24px;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 18px;">Ошибка</div>
                            <div style="font-size: 14px; opacity: 0.9; margin-top: 5px;">${message}</div>
                        </div>
                        <button onclick="this.parentElement.parentElement.remove()" style="
                            background: none;
                            border: none;
                            color: rgba(255,255,255,0.5);
                            font-size: 20px;
                            cursor: pointer;
                            transition: color 0.2s;
                        ">
                            ×
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        
        
        
        

        function setupNavigation() {
            const menuLinks = document.querySelectorAll('.desktop-sidebar-menu a');
            const pages = document.querySelectorAll('.page');
            
            menuLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active state
                    menuLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show page
                    const targetId = this.getAttribute('href').substring(1);
                    showPage(targetId);
                });
            });
        }

        function showPage(pageId) {
            const pages = document.querySelectorAll('.page');
            pages.forEach(page => {
                const isTarget = page.id === pageId;
                page.classList.toggle('active', isTarget);
            });
            
            // Special handling for pages that need data loading
            if (pageId === 'vydelennyj-sajt') {
                loadSiteTariffs();
            } else if (pageId === 'virtualnye-servera1') {
                loadVdsTariffs();
            } else if (pageId === 'nagruzka') {
                updateLoadData();
                startLoadUpdates();
            } else {
                stopLoadUpdates();
            }
        }

        function setupMobileTabs() {
            const tabs = document.querySelectorAll('.mobile-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    
                    // Update active state
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Show page
                    const pages = document.querySelectorAll('.mobile-container .page');
                    pages.forEach(page => {
                        const isTarget = page.id === targetId;
                        page.classList.toggle('active', isTarget);
                    });
                });
            });
        }

        // Modal functions
        function openLogoutModal(e) {
            if (e) e.preventDefault();
            document.getElementById('logoutConfirmModal').style.display = 'flex';
        }

        function closeLogoutModal() {
            document.getElementById('logoutConfirmModal').style.display = 'none';
        }

        function confirmLogout() {
            fetch('/bill/logout.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = '/MasterBilling/Autorize';
                    } else {
                        alert('Ошибка при выходе');
                    }
                })
                .catch(err => {
                    console.error('Ошибка:', err);
                    alert('Не удалось выйти');
                });
        }

        function openBombsquadModal(e) {
            e.preventDefault();
            document.getElementById('bombsquadModal').style.display = 'flex';
        }

        function closeBombsquadModal() {
            document.getElementById('bombsquadModal').style.display = 'none';
        }

        function confirmBombsquad() {
            window.open('http://mastercore.tech/Bombsquad/index.php', '_blank');
            closeBombsquadModal();
        }

        function redirectToLogin() {
            window.location.href = '/MasterBilling/Autorize';
        }

        // Profile functions
        function changePassword() {
            const newPassword = document.getElementById('newPassword').value;
            if (!newPassword || newPassword.length < 6) {
                alert("Пароль должен быть не менее 6 символов");
                return;
            }
            fetch('/bill/change_password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password: newPassword })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Пароль успешно изменён!");
                    document.getElementById('newPassword').value = '';
                } else {
                    alert("Ошибка: " + data.message);
                }
            });
        }

        document.getElementById('avatarInput')?.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('avatar', file);
            fetch('/bill/upload_avatar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('modalAvatar').src = data.avatarUrl + '?t=' + Date.now();
                    document.getElementById('mobileAvatar').src = data.avatarUrl + '?t=' + Date.now();
                    const navbarAvatar = document.querySelector('.user-avatar');
                    if (navbarAvatar) {
                        navbarAvatar.src = data.avatarUrl + '?t=' + Date.now();
                    }
                    alert("Аватар успешно загружен!");
                } else {
                    alert("Ошибка: " + data.message);
                }
            });
        });

        function addCard() {
            alert("Функция добавления карты временно недоступна.");
        }

        // Recharge functions
        function submitYookassa() {
            const amount = document.getElementById('yookassaAmount').value;
            if (!amount || isNaN(amount) || amount < 10) {
                alert("Сумма должна быть не менее 10 ₽");
                return;
            }
            const modal = bootstrap.Modal.getInstance(document.getElementById('newRechargeModal'));
            modal.hide();
            window.location.href = '/PayS/create_yookassa_payment.php?sum=' + amount;
        }

        // Load monitoring
        let cpuData = Array(12).fill(0);
        let ramData = Array(12).fill(0);
        let diskData = Array(12).fill(0);
        let networkData = Array(12).fill(0);
        let loadChart = null;
        let loadInterval = null;

        function initChart() {
            const ctx = document.getElementById('loadChart');
            if (!ctx) return;
            
            loadChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: Array(12).fill('').map((_, i) => {
                        const date = new Date(Date.now() - (11 - i) * 5000);
                        return date.getHours() + ':' + date.getMinutes().toString().padStart(2, '0');
                    }),
                    datasets: [
                        {
                            label: 'CPU',
                            data: cpuData,
                            borderColor: '#7c4dff',
                            backgroundColor: 'rgba(124, 77, 255, 0.1)',
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'RAM',
                            data: ramData,
                            borderColor: '#00bfa5',
                            backgroundColor: 'rgba(0, 191, 165, 0.1)',
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'Disk',
                            data: diskData,
                            borderColor: '#ff9100',
                            backgroundColor: 'rgba(255, 145, 0, 0.1)',
                            tension: 0.4,
                            borderWidth: 2
                        },
                        {
                            label: 'Network',
                            data: networkData,
                            borderColor: '#00c853',
                            backgroundColor: 'rgba(0, 200, 83, 0.1)',
                            tension: 0.4,
                            borderWidth: 2
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: 'rgba(255, 255, 255, 0.8)'
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)'
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        },
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                callback: function(value) { return value + '%'; }
                            },
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            }
                        }
                    }
                }
            });
        }

        function updateLoadData() {
            fetch('/bill/get_load_data.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Ошибка HTTP: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('cpuLoad').textContent = data.cpu + '%';
                        document.getElementById('ramLoad').textContent = data.ram + '%';
                        document.getElementById('diskLoad').textContent = data.disk + '%';
                        document.getElementById('networkLoad').textContent = data.network + '%';
                        
                        // Update chart data
                        cpuData.shift(); cpuData.push(data.cpu);
                        ramData.shift(); ramData.push(data.ram);
                        diskData.shift(); diskData.push(data.disk);
                        networkData.shift(); networkData.push(data.network);
                        
                        // Update labels
                        const labels = loadChart.data.labels;
                        labels.shift();
                        const now = new Date();
                        labels.push(now.getHours() + ':' + now.getMinutes().toString().padStart(2, '0'));
                        
                        loadChart.update();
                    }
                })
                .catch(err => {
                    console.error("Ошибка получения данных о нагрузке:", err);
                });
        }

        function startLoadUpdates() {
            stopLoadUpdates();
            updateLoadData();
            loadInterval = setInterval(updateLoadData, 5000);
        }

        function stopLoadUpdates() {
            if (loadInterval) {
                clearInterval(loadInterval);
                loadInterval = null;
            }
        }

        // Inactivity detection
        function setupInactivityDetection() {
            let inactivityTimer;
            let modalShown = false;
            
            function resetTimer() {
                clearTimeout(inactivityTimer);
                modalShown = false;
                document.getElementById('inactivityModal').style.display = 'none';
                
                inactivityTimer = setTimeout(() => {
                    if (!modalShown) {
                        document.getElementById('inactivityModal').style.display = 'flex';
                        modalShown = true;
                        setTimeout(logout, 5000);
                    }
                }, 600000); // 10 minutes
            }
            
            function logout() {
                fetch('/bill/logout.php')
                    .then(() => {
                        window.location.href = '/MasterBilling/Autorize';
                    })
                    .catch(err => {
                        console.error("Ошибка при выходе:", err);
                        window.location.href = '/MasterBilling/Autorize';
                    });
            }
            
            // Reset timer on user activity
            document.addEventListener('mousemove', resetTimer);
            document.addEventListener('keypress', resetTimer);
            document.addEventListener('click', resetTimer);
            document.addEventListener('scroll', resetTimer);
            
            // Initialize timer
            resetTimer();
        }
        
        
        
        
function formatExpirationInfo(service) {
    if (!service.expires_at) {
        return {
            html: '<small class="text-light"><i class="fas fa-infinity me-1"></i>Бессрочный доступ</small>',
            badge: '',
            isExpired: false,
            daysLeft: 999
        };
    }
    
    const expiresDate = new Date(service.expires_at);
    const now = new Date();
    const diffTime = expiresDate.getTime() - now.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    // Если дата истечения в прошлом
    if (diffTime <= 0 || service.payment_status === 'expired') {
        return {
            html: `
                <small class="text-danger d-block mb-1 fw-medium">
                    <i class="fas fa-ban me-1"></i>Срок истек
                </small>
                <small class="text-light opacity-80">
                    ${expiresDate.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' })} 
                    в ${expiresDate.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })}
                </small>
            `,
            badge: '<span class="badge bg-danger ms-1" style="font-size: 0.7rem;">ИСТЕК</span>',
            isExpired: true,
            daysLeft: 0
        };
    }
    
    // Активный сервер
    let textClass = 'text-light'; // Белый текст по умолчанию
    let badgeClass = '';
    let badgeText = '';
    let icon = 'fa-clock';
    
    if (diffDays <= 1) {
        textClass = 'text-danger'; // Красный
        icon = 'fa-exclamation-triangle';
        badgeClass = 'bg-danger';
        badgeText = `${diffDays}Д`;
    } else if (diffDays <= 3) {
        textClass = 'text-warning'; // Оранжевый
        icon = 'fa-exclamation-circle';
        badgeClass = 'bg-warning';
        badgeText = `${diffDays}Д`;
    } else if (diffDays <= 7) {
        textClass = 'text-info'; // Синий
        icon = 'fa-calendar';
        badgeClass = 'bg-info';
        badgeText = `${diffDays}Д`;
    } else if (diffDays <= 30) {
        textClass = 'text-success'; // Зеленый
        icon = 'fa-calendar-check';
        badgeClass = 'bg-success';
    }
    
    // Функция для правильного склонения
    function getDayWord(days) {
        if (days % 10 === 1 && days % 100 !== 11) return 'день';
        if (days % 10 >= 2 && days % 10 <= 4 && (days % 100 < 10 || days % 100 >= 20)) return 'дня';
        return 'дней';
    }
    
    // Форматируем дату
    const formattedDate = expiresDate.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
    
    const formattedTime = expiresDate.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit'
    });
    
    return {
        html: `
            <div class="mb-1">
                <small class="${textClass} fw-medium">
                    <i class="fas ${icon} me-1"></i>
                    Осталось: <strong>${diffDays}</strong> ${getDayWord(diffDays)}
                </small>
            </div>
            <small class="text-light opacity-90">
                <i class="far fa-calendar me-1"></i>
                До ${formattedDate} в ${formattedTime}
            </small>
        `,
        badge: badgeClass ? `<span class="badge ${badgeClass} ms-1" style="font-size: 0.7rem;">${badgeText}</span>` : '',
        isExpired: false,
        daysLeft: diffDays
    };
}
    </script>
    <pre>
<?= htmlspecialchars(json_encode($userServices, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
</pre>

<!-- Модальное окно: Настройка игрового сервера (динамическое) -->
<div id="gameServerConfigModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px; border-radius: var(--border-radius); overflow: hidden;">
        <div style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); padding: 20px; color: white; text-align: center;">
            <h4 style="margin: 0;"><i class="fas fa-cogs"></i> <span id="modalGameTitle">Настройка сервера</span></h4>
        </div>
        <div style="padding: 25px; background-color: var(--dark); color: white;">
            <form id="gameServerConfigForm">
                <!-- Скрытые поля -->
                <input type="hidden" id="tariffId" name="tariff_id">
                <input type="hidden" id="gameKey" name="game">

                <!-- Название сервера -->
                <div class="mb-3">
                    <label class="form-label">Название сервера</label>
                    <input type="text" class="form-control" id="serverName" name="server_name" placeholder="Например: Мой сервер" required>
                </div>

                <!-- Количество слотов -->
                <div class="mb-3">
                    <label class="form-label">Количество игроков</label>
                    <select class="form-control" id="slots" name="slots" required>
                        <option value="10">10 слотов</option>
                        <option value="20">20 слотов</option>
                        <option value="30">30 слотов</option>
                        <option value="50">50 слотов</option>
                    </select>
                </div>

                <!-- Контейнер для динамических полей -->
                <div id="dynamicOptions"></div>

                <!-- Итоговая цена -->
                <div class="alert alert-info" style="font-size: 0.9rem;">
                    <i class="fas fa-info-circle"></i>
                    <span id="totalPrice">Цена: 0 ₽</span><br>
                    <small>Оплата будет произведена после подтверждения.</small>
                </div>

                <!-- Кнопки -->
                <div class="btn-group" style="width: 100%;">
                    <button type="button" class="btn-modal btn-secondary-modal" onclick="closeGameServerModal()">Отмена</button>
                    <button type="submit" class="btn-modal btn-primary-modal">Заказать</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>