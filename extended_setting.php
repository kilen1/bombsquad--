<?php 
session_start(); 
include __DIR__ . '../../Base/db.php';

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

// 🔹 Загружаем тарифы с дополнительными полями
try {
    $gameTariffsStmt = $pdo->prepare("
        SELECT id, name, game, price, duration_months, 
               COALESCE(ram, 'Не указано') as ram,
               COALESCE(cpu, 'Не указано') as cpu,
               COALESCE(storage, 'Не указано') as storage,
               COALESCE(bandwidth, 'Не указано') as bandwidth,
               COALESCE(description, '') as description,
               COALESCE(mod, '') as mod,
               COALESCE(confirmation_button_text, 'Подтвердить покупку') as confirmation_button_text,
               COALESCE(additional_features, '') as additional_features,
               COALESCE(features_list, '') as features_list
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

// 🔹 Функция для преобразования features_list в массив
function parseFeaturesList($features_list) {
    if (empty($features_list)) {
        return [];
    }
    
    // Попробуем распарсить JSON, если это не получится, разобьем по переносам строк
    $features = json_decode($features_list, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $features;
    } else {
        // Разбиваем по переносам строк или точкам с запятой
        $features = preg_split('/[\r\n;]+/', $features_list);
        $features = array_map('trim', $features);
        $features = array_filter($features);
        return $features;
    }
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
            max-width: 500px;
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
        
        .modal-content .tariff-description {
            text-align: left;
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .modal-content .tariff-features {
            text-align: left;
            margin: 15px 0;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
        }
        
        .modal-content .tariff-features ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .modal-content .tariff-features li {
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
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
            border: 3px solid var(--primary);
            margin-bottom: 10px;
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: white;
        }

        .profile-username {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .profile-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-light);
        }

        .stat-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .profile-actions {
            padding: 15px 20px;
        }

        .profile-actions button {
            width: 100%;
            margin-bottom: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .desktop-container {
                display: none;
            }
            
            .mobile-container {
                display: flex;
            }
            
            .desktop-content {
                margin-left: 0;
            }
        }

        @media (min-width: 769px) {
            .mobile-container {
                display: none;
            }
        }
        
        .tariff-mod {
            display: inline-block;
            background-color: rgba(0, 191, 165, 0.2);
            color: var(--secondary);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            margin-top: 5px;
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
                <li><a href="#" class="active" onclick="showPage('dashboard')"><i class="fas fa-home"></i> Панель</a></li>
                <li><a href="#" onclick="showPage('servers')"><i class="fas fa-server"></i> Мои серверы</a></li>
                <li><a href="#" onclick="showPage('tariffs')"><i class="fas fa-shopping-cart"></i> Тарифы</a></li>
                <li><a href="#" onclick="showPage('billing')"><i class="fas fa-credit-card"></i> Платежи</a></li>
                <li><a href="#" onclick="showPage('support')"><i class="fas fa-headset"></i> Поддержка</a></li>
            </ul>
        </div>
        
        <div class="desktop-content">
            <div class="desktop-header">
                <div class="balance">
                    <i class="fas fa-coins"></i>
                    <span id="balanceDisplay"><?php echo number_format($balance, 2); ?> монет</span>
                </div>
                <div class="user-menu">
                    <div class="user-avatar" onclick="toggleProfileModal()">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
            </div>
            
            <!-- Dashboard Page -->
            <div id="dashboard" class="page active">
                <div class="card">
                    <h3 class="card-title">Добро пожаловать, <?php echo htmlspecialchars($username); ?>!</h3>
                    <p>Управление вашими игровыми серверами в одном месте.</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <h4>Статистика</h4>
                            <p>Активных серверов: <strong><?php echo count($userServices); ?></strong></p>
                            <p>Баланс: <strong><?php echo number_format($balance, 2); ?> монет</strong></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <h4>Быстрые действия</h4>
                            <button class="btn btn-primary" onclick="showPage('tariffs')">Заказать сервер</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Servers Page -->
            <div id="servers" class="page">
                <div class="card">
                    <h3 class="card-title">Мои сервера</h3>
                    <?php if (empty($userServices)): ?>
                        <div class="empty-state">
                            <h3>Нет активных серверов</h3>
                            <p>Закажите первый сервер, чтобы начать</p>
                            <button class="btn btn-primary" onclick="showPage('tariffs')">Заказать сервер</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userServices as $service): ?>
                            <div class="server-item">
                                <div class="server-info">
                                    <h6 class="mb-1 server-name"><?php echo htmlspecialchars($service['name']); ?></h6>
                                    <small>Тариф: <?php echo htmlspecialchars($service['tariff_name']); ?></small><br>
                                    <small>Игра: <?php echo htmlspecialchars($service['game']); ?></small><br>
                                    <small>Окончание: <?php echo date('d.m.Y', strtotime($service['expires_at'])); ?></small>
                                </div>
                                <div class="server-actions">
                                    <?php if ($service['has_panel']): ?>
                                        <a href="/MasterBilling/HomePage/BillingPanel?id=<?php echo urlencode($service['service_uid']); ?>" class="btn btn-primary">Панель</a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary" disabled>Панель</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tariffs Page -->
            <div id="tariffs" class="page">
                <div class="card">
                    <h3 class="card-title">Доступные тарифы</h3>
                    <div class="row">
                        <?php foreach ($gameTariffs as $tariff): ?>
                            <div class="col-md-4">
                                <div class="pricing-card">
                                    <h3><?php echo htmlspecialchars($tariff['name']); ?></h3>
                                    <?php if (!empty($tariff['mod'])): ?>
                                        <div class="tariff-mod">Мод: <?php echo htmlspecialchars($tariff['mod']); ?></div>
                                    <?php endif; ?>
                                    <h5><?php echo number_format($tariff['price'], 2); ?> монет</h5>
                                    <ul>
                                        <li><i class="fas fa-microchip"></i> RAM: <?php echo htmlspecialchars($tariff['ram']); ?></li>
                                        <li><i class="fas fa-tachometer-alt"></i> CPU: <?php echo htmlspecialchars($tariff['cpu']); ?></li>
                                        <li><i class="fas fa-hdd"></i> Storage: <?php echo htmlspecialchars($tariff['storage']); ?></li>
                                        <li><i class="fas fa-bolt"></i> Bandwidth: <?php echo htmlspecialchars($tariff['bandwidth']); ?></li>
                                    </ul>
                                    <button class="btn btn-primary" onclick="showTariffModal(<?php echo $tariff['id']; ?>)">Выбрать тариф</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Billing Page -->
            <div id="billing" class="page">
                <div class="card">
                    <h3 class="card-title">История платежей</h3>
                    <p>Здесь будет отображаться история ваших платежей.</p>
                </div>
            </div>
            
            <!-- Support Page -->
            <div id="support" class="page">
                <div class="card">
                    <h3 class="card-title">Поддержка</h3>
                    <p>Если у вас возникли вопросы, свяжитесь с нашей командой поддержки.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tariff Modal -->
    <div id="tariffModal" class="modal-overlay">
        <div class="modal-content">
            <h4 id="modalTariffName">Название тарифа</h4>
            <div id="modalTariffDescription" class="tariff-description">
                <!-- Описание тарифа будет вставлено сюда -->
            </div>
            <div id="modalTariffFeatures" class="tariff-features">
                <!-- Список возможностей будет вставлен сюда -->
            </div>
            <p id="modalTariffPrice">Цена: 0 монет</p>
            <div class="d-flex justify-content-center">
                <button id="confirmPurchaseBtn" class="btn btn-primary-modal">Подтвердить покупку</button>
                <button class="btn btn-secondary-modal" onclick="closeTariffModal()">Отмена</button>
            </div>
        </div>
    </div>
    
    <!-- Profile Modal -->
    <div id="profileModal" class="modal-overlay">
        <div class="modal-content">
            <div class="profile-header">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($username); ?>&background=7c4dff&color=fff" class="profile-avatar" alt="Avatar">
                <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="profile-username">@<?php echo htmlspecialchars($username); ?></div>
            </div>
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-value"><?php echo count($userServices); ?></div>
                    <div class="stat-label">Серверов</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo number_format($balance, 2); ?></div>
                    <div class="stat-label">Монет</div>
                </div>
            </div>
            <div class="profile-actions">
                <button class="btn btn-secondary" onclick="location.reload()">Обновить</button>
                <button class="btn btn-secondary" onclick="logout()">Выйти</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Страницы
        function showPage(pageId) {
            // Скрываем все страницы
            document.querySelectorAll('.page').forEach(page => {
                page.classList.remove('active');
            });
            
            // Показываем выбранную страницу
            document.getElementById(pageId).classList.add('active');
            
            // Обновляем активный пункт меню
            document.querySelectorAll('.desktop-sidebar-menu a').forEach(link => {
                link.classList.remove('active');
            });
            
            event.target.classList.add('active');
        }
        
        // Модальные окна
        function toggleProfileModal() {
            const modal = document.getElementById('profileModal');
            modal.style.display = modal.style.display === 'flex' ? 'none' : 'flex';
        }
        
        function showTariffModal(tariffId) {
            // Находим тариф по ID
            const tariffs = <?php echo json_encode($gameTariffs); ?>;
            const tariff = tariffs.find(t => t.id == tariffId);
            
            if (tariff) {
                document.getElementById('modalTariffName').textContent = tariff.name;
                document.getElementById('modalTariffPrice').textContent = `Цена: ${tariff.price} монет`;
                
                // Устанавливаем описание
                const descElement = document.getElementById('modalTariffDescription');
                if (tariff.description) {
                    descElement.innerHTML = `<strong>Описание:</strong><br>${tariff.description}`;
                    descElement.style.display = 'block';
                } else {
                    descElement.style.display = 'none';
                }
                
                // Устанавливаем список возможностей
                const featuresElement = document.getElementById('modalTariffFeatures');
                if (tariff.features_list) {
                    // Попробуем распарсить JSON, если не получится - разобьем по переносам строк
                    let features = [];
                    try {
                        features = JSON.parse(tariff.features_list);
                        if (!Array.isArray(features)) throw new Error('Not an array');
                    } catch (e) {
                        features = tariff.features_list.split(/[\r\n;]+/).map(f => f.trim()).filter(f => f);
                    }
                    
                    if (features.length > 0) {
                        let featuresHtml = '<strong>Возможности:</strong><ul>';
                        features.forEach(feature => {
                            featuresHtml += `<li>${feature}</li>`;
                        });
                        featuresHtml += '</ul>';
                        featuresElement.innerHTML = featuresHtml;
                        featuresElement.style.display = 'block';
                    } else {
                        featuresElement.style.display = 'none';
                    }
                } else {
                    featuresElement.style.display = 'none';
                }
                
                // Устанавливаем текст кнопки подтверждения
                const confirmBtn = document.getElementById('confirmPurchaseBtn');
                confirmBtn.textContent = tariff.confirmation_button_text || 'Подтвердить покупку';
                confirmBtn.onclick = function() {
                    purchaseTariff(tariffId);
                };
                
                document.getElementById('tariffModal').style.display = 'flex';
            }
        }
        
        function closeTariffModal() {
            document.getElementById('tariffModal').style.display = 'none';
        }
        
        function purchaseTariff(tariffId) {
            // Отправляем запрос на покупку тарифа
            $.post('/order_game.php', {
                tariff_id: tariffId,
                server_name: 'Мой сервер',
                game: 'bombsquad'  // будет определено из тарифа на сервере
            }, function(response) {
                if (response.success) {
                    alert('Тариф успешно приобретен!');
                    closeTariffModal();
                    location.reload();
                } else {
                    alert('Ошибка: ' + response.message);
                }
            }).fail(function() {
                alert('Ошибка соединения с сервером');
            });
        }
        
        function logout() {
            if (confirm('Вы действительно хотите выйти?')) {
                window.location.href = '/MasterBilling/Logout';
            }
        }
        
        // Закрытие модальных окон при клике вне их области
        window.onclick = function(event) {
            const tariffModal = document.getElementById('tariffModal');
            const profileModal = document.getElementById('profileModal');
            
            if (event.target === tariffModal) {
                closeTariffModal();
            }
            if (event.target === profileModal) {
                toggleProfileModal();
            }
        }
        
        // Обработка закрытия модальных окон по клавише Esc
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTariffModal();
                document.getElementById('profileModal').style.display = 'none';
            }
        });
    </script>
</body>
</html>