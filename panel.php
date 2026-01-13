<?php
session_start();
include __DIR__ . './db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /MasterBilling/Autorize");
    exit();
}

$service_id = $_GET['id'] ?? '';
$username = $_SESSION['username'];

// Получаем информацию о сервисе (без поля color)
$stmt = $pdo->prepare("
    SELECT s.*, t.name as tariff_name, t.game, u.username, 
           v.ip as vds_ip, v.username as vds_username, v.password as vds_password, 
           cp.name as panel_name,
           s.has_panel
    FROM services s
    JOIN tariffs t ON s.tariff_id = t.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN vds v ON s.vds_id = v.vds_id
    LEFT JOIN control_panels cp ON s.control_panel_id = cp.id
    WHERE s.service_id = ? AND u.username = ?
");
$stmt->execute([$service_id, $username]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

// Проверка доступа
if (!$service) {
    die("<h2 style='color: red; text-align: center; margin-top: 50px;'>Доступ запрещен или сервер не найден!</h2>");
}

$isStandard = (strtolower(trim($service['tariff_name'])) === 'standard');



$game_icons = [
    'minecraft' => 'fas fa-cube',
    'bombsquad' => 'fas fa-bomb',
    'rust' => 'fas fa-shield-alt',
    'default' => 'fas fa-gamepad'
];

$game_icon = $game_icons[$service['game']] ?? $game_icons['default'];

// Цвета для разных типов панелей
$panel_colors = [
    'Standard' => '#6366f1',
    'Basic' => '#3b82f6',
    'Premium' => '#10b981'
];

$panel_color = $panel_colors[$service['panel_name']] ?? '#3498db';

// Альтернатива - генерируем цвет на основе имени панели
function generateColor($string) {
    $hash = md5($string);
    return '#' . substr($hash, 0, 6);
}

if (!$service['panel_name']) {
    $panel_color = generateColor($service['game'] . $service['tariff_name']);
}




?>

<?php if(!$service['vds_ip']): ?>
<div class="vds-notice">
    <div class="vds-notice-icon">
        <i class="fas fa-server"></i>
    </div>
    <div class="vds-notice-content">
        <h4>Требуется VDS сервер</h4>
        <p>Ваша игровая панель работает без физического сервера. Для работы сервера привяжите вашу vds.</p>
        <div class="vds-action-buttons">
            <button class="btn btn-primary" onclick="openVdsAssignmentModal('<?= $service_id ?>')">
                <i class="fas fa-link"></i> Привязать VDS
            </button>
            <button class="btn btn-outline" onclick="checkAutoAssignment()">
                <i class="fas fa-bolt"></i> Авто-выбор
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($service['name']) ?> | Панель управления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
    --primary-color: <?= $panel_color ?>;
    --primary-dark: <?= adjustBrightness($panel_color, -30) ?>;
    --primary-light: <?= adjustBrightness($panel_color, 30) ?>;
    --secondary: #6366f1;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --dark: #1e293b;
    --darker: #0f172a;
    --light: #f8fafc;
    --gray: #94a3b8;  /* Было: #64748b - слишком темный */
    --gray-light: #cbd5e1; /* Добавляем светлый серый */
    --border-radius: 16px;
    --shadow-sm: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Исправление видимости текста */
.text-muted {
    color: var(--gray-light) !important; /* Более светлый серый */
    opacity: 0.9 !important;
}

.text-light {
    color: rgba(255, 255, 255, 0.95) !important;
}

.text-dark {
    color: var(--darker) !important;
}

/* Улучшение контраста для карточек */
.card, .control-section, .stat-card {
    background: rgba(255, 255, 255, 0.08) !important; /* Было: 0.05 */
    border: 1px solid rgba(255, 255, 255, 0.12) !important; /* Более заметная граница */
}

/* Улучшение видимости заголовков */
h1, h2, h3, h4, h5, h6 {
    color: rgba(255, 255, 255, 0.95) !important;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

/* Улучшение видимости текста в боковой панели */
.sidebar {
    background: rgba(15, 23, 42, 0.98) !important; /* Меньше прозрачности */
}

.nav-link {
    color: rgba(255, 255, 255, 0.85) !important; /* Было: 0.7 */
}

.nav-link:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.15) !important; /* Более заметный ховер */
}

/* Улучшение видимости текста в модальных окнах */
.modal-content {
    background: rgba(30, 41, 59, 0.98) !important; /* Меньше прозрачности */
    color: rgba(255, 255, 255, 0.95) !important;
}

/* Улучшение видимости текста в уведомлениях */
.vds-notice-content p {
    color: rgba(255, 255, 255, 0.9) !important; /* Было: 0.7 */
}

/* Улучшение видимости текста в терминале */
.terminal-body {
    color: rgba(255, 255, 255, 0.9) !important;
    background: #0a0a0f !important;
}

.terminal-prompt {
    color: #4ade80 !important; /* Более яркий зеленый */
}

/* Улучшение видимости текста во вкладках */
.tab-btn {
    color: rgba(255, 255, 255, 0.8) !important; /* Было: var(--gray) */
}

.tab-btn:hover {
    color: white !important;
}

.tab-btn.active {
    color: var(--primary-color) !important;
    border-bottom: 3px solid var(--primary-color) !important; /* Более толстая линия */
}

/* Улучшение видимости текста в info-item */
.info-item .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
    font-size: 0.9rem !important;
    margin-bottom: 0.25rem !important;
}

.info-item .h5 {
    color: white !important;
    font-weight: 600 !important;
    font-size: 1.25rem !important;
}

/* Улучшение видимости текста в карточках статистики */
.stat-info p {
    color: rgba(255, 255, 255, 0.8) !important; /* Было: var(--gray) */
    font-size: 0.9rem !important;
    font-weight: 500 !important;
}

/* Улучшение видимости текста в кнопках */
.btn-outline {
    color: rgba(255, 255, 255, 0.9) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important; /* Более заметная граница */
}

.btn-outline:hover {
    color: white !important;
    border-color: var(--primary-color) !important;
    background: rgba(99, 102, 241, 0.2) !important;
}

/* Улучшение видимости текста в полях ввода */
.form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.3) !important;
    color: white !important;
}

.form-control::placeholder {
    color: rgba(255, 255, 255, 0.5) !important;
}

/* Улучшение видимости текста в alert */
.alert {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.alert-info {
    background: rgba(59, 130, 246, 0.15) !important;
    border-color: rgba(59, 130, 246, 0.3) !important;
    color: #93c5fd !important;
}

.alert-warning {
    background: rgba(245, 158, 11, 0.15) !important;
    border-color: rgba(245, 158, 11, 0.3) !important;
    color: #fcd34d !important;
}

.alert-success {
    background: rgba(16, 185, 129, 0.15) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #86efac !important;
}

/* Добавьте в CSS */
.vds-notice {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
    border: 1px solid rgba(245, 158, 11, 0.2);
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 20px;
    
    /* Сдвиг вправо */
    margin-left: 280px; /* ширина боковой панели */
    margin-right: 20px; /* отступ справа */
    width: calc(100% - 300px); /* 100% - боковая панель + отступы */
}

@media (max-width: 1024px) {
    .vds-notice {
        margin-left: 80px; /* ширина компактной боковой панели */
        width: calc(100% - 100px);
    }
}

@media (max-width: 768px) {
    .vds-notice {
        margin-left: 20px;
        margin-right: 20px;
        width: calc(100% - 40px);
    }
}





        /* Остальные стили остаются такими же, как в предыдущем ответе */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, var(--darker), var(--dark));
            color: var(--light);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow-x: hidden;
        }

        /* Лейаут */
        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Боковая панель */
        .sidebar {
            width: 280px;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
            padding: 24px 0;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 24px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .sidebar-logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }

        .sidebar-logo-text h3 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-logo-text small {
            font-size: 0.75rem;
            color: var(--gray);
            display: block;
        }

        .server-info-sidebar {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 16px;
            margin: 0 16px 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .server-name-sidebar {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 4px;
            color: white;
        }

        .server-game-sidebar {
            font-size: 0.85rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-section {
            margin-bottom: 24px;
        }

        .nav-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray);
            padding: 0 24px 8px;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
        }

        .nav-item {
            margin: 2px 16px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 500;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Основной контент */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 24px;
            min-height: 100vh;
        }

        /* Шапка */
        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .header-left h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: white;
        }

        .header-left .server-id {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .server-status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 500;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        /* Карточки статистики */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius);
            padding: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary));
        }

        .stat-card.cpu::before { background: linear-gradient(90deg, #3b82f6, #8b5cf6); }
        .stat-card.ram::before { background: linear-gradient(90deg, #10b981, #3b82f6); }
        .stat-card.storage::before { background: linear-gradient(90deg, #f59e0b, #f97316); }
        .stat-card.network::before { background: linear-gradient(90deg, #8b5cf6, #ec4899); }

        .stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 16px;
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .stat-info p {
            color: var(--gray);
            margin: 4px 0 0;
            font-size: 0.875rem;
        }

        .stat-progress {
            margin-top: 16px;
        }

        .progress {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            overflow: hidden;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary));
            border-radius: 3px;
        }

        /* Секция управления */
        .control-section {
            background: rgba(255, 255, 255, 0.05);
            border-radius: var(--border-radius);
            padding: 32px;
            margin-bottom: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin: 0;
        }

        .section-subtitle {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .control-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
        }

        .control-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 24px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            color: white;
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .control-btn:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        .control-btn.start:hover { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
        .control-btn.stop:hover { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .control-btn.restart:hover { border-color: #f59e0b; background: rgba(245, 158, 11, 0.1); }

        .control-icon {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .control-btn.start .control-icon { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .control-btn.stop .control-icon { background: rgba(239, 68, 68, 0.2); color: #ef4444; }
        .control-btn.restart .control-icon { background: rgba(245, 158, 11, 0.2); color: #f59e0b; }

        .control-btn span {
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Вкладки */
        .tabs-container {
            margin-top: 32px;
        }

        .tabs-header {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 24px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .tab-btn {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: var(--gray);
            font-weight: 500;
            cursor: pointer;
            border-radius: 8px 8px 0 0;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.05);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        /* Консоль */
        .terminal-container {
            background: #0a0a0f;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .terminal-header {
            background: #1a1a23;
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .terminal-title {
            font-weight: 600;
            color: white;
        }

        .terminal-controls {
            display: flex;
            gap: 8px;
        }

        .terminal-control-btn {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
        }

        .terminal-control-btn.close { background: #ff5f57; }
        .terminal-control-btn.minimize { background: #ffbd2e; }
        .terminal-control-btn.maximize { background: #28ca42; }

        .terminal-body {
            padding: 24px;
            height: 400px;
            overflow-y: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.6;
        }

        .terminal-line {
            margin-bottom: 4px;
            display: flex;
            gap: 8px;
        }

        .terminal-prompt {
            color: #10b981;
            font-weight: 600;
        }

        .terminal-input {
            background: transparent;
            border: none;
            color: white;
            font-family: inherit;
            font-size: inherit;
            width: 100%;
            outline: none;
            padding: 8px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 16px;
        }

        /* VDS уведомление */
        .vds-notice {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.2);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .vds-notice-icon {
            width: 64px;
            height: 64px;
            background: rgba(245, 158, 11, 0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #f59e0b;
        }

        .vds-notice-content h4 {
            color: white;
            margin-bottom: 8px;
            font-size: 1.25rem;
        }

        .vds-notice-content p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 16px;
            font-size: 0.875rem;
        }

        .vds-action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
        }

        /* Анимации */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Адаптивность */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
                padding: 20px 0;
            }
            
            .sidebar-header {
                padding: 0 16px 20px;
            }
            
            .sidebar-logo-text,
            .server-info-sidebar,
            .nav-title,
            .nav-link span {
                display: none;
            }
            
            .nav-link {
                justify-content: center;
                padding: 16px;
            }
            
            .main-content {
                margin-left: 80px;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .control-buttons-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .vds-notice {
                flex-direction: column;
                text-align: center;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .control-buttons-grid {
                grid-template-columns: 1fr;
            }
            
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }

        /* Пользовательский скролл */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- Боковая панель -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <div class="sidebar-logo-icon">
                        <i class="<?= $game_icon ?>"></i>
                    </div>
                    <div class="sidebar-logo-text">
                        <h3>GamePanel</h3>
                        <small><?= $isStandard ? 'Premium Edition' : ($service['panel_name'] ?? 'Basic Edition') ?></small>
                    </div>
                </div>
            </div>

            <div class="server-info-sidebar">
                <div class="server-name-sidebar"><?= htmlspecialchars($service['name']) ?></div>
                <div class="server-game-sidebar">
                    <i class="<?= $game_icon ?>"></i>
                    <?= htmlspecialchars(ucfirst($service['game'])) ?>
                </div>
            </div>

            <nav class="nav-section">
                <div class="nav-title">Основное</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#overview" class="nav-link active" data-tab="overview">
                            <i class="fas fa-tachometer-alt"></i>
                            <span>Обзор</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#console" class="nav-link" data-tab="console">
                            <i class="fas fa-terminal"></i>
                            <span>Консоль</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#files" class="nav-link" data-tab="files">
                            <i class="fas fa-folder"></i>
                            <span>Файлы</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">Управление</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#settings" class="nav-link" data-tab="settings">
                            <i class="fas fa-cog"></i>
                            <span>Настройки</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#backups" class="nav-link" data-tab="backups">
                            <i class="fas fa-save"></i>
                            <span>Бэкапы</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#players" class="nav-link" data-tab="players">
                            <i class="fas fa-users"></i>
                            <span>Игроки</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <nav class="nav-section">
                <div class="nav-title">Другое</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="#statistics" class="nav-link" data-tab="statistics">
                            <i class="fas fa-chart-bar"></i>
                            <span>Статистика</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="/bill/" class="nav-link">
                            <i class="fas fa-arrow-left"></i>
                            <span>В биллинг</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Основной контент -->
        <main class="main-content">
            <!-- Шапка -->
            <header class="main-header">
                <div class="header-left">
                    <h1><?= htmlspecialchars($service['name']) ?></h1>
                    <div class="server-id">ID: <?= htmlspecialchars($service['service_id']) ?></div>
                </div>
                <div class="header-right">
                    <?php if($isStandard): ?>
                        <span class="badge bg-primary px-3 py-2" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary)) !important;">
                            <i class="fas fa-crown me-2"></i>STANDARD
                        </span>
                    <?php endif; ?>
                    <div class="server-status-badge">
                        <span class="status-dot"></span>
                        Панель управления активна
                    </div>
                </div>
            </header>

            <!-- Уведомление о VDS -->
            <?php if(!$service['vds_ip'] && !$isStandard): ?>
            <div class="vds-notice">
                <div class="vds-notice-icon">
                    <i class="fas fa-server"></i>
                </div>
                <div class="vds-notice-content">
                    <h4>Требуется VDS сервер</h4>
                    <p>Ваш игровой сервер не привязан к физическому серверу. Привяжите VDS для полноценной работы.</p>
                    <div class="vds-action-buttons">
                        <button class="btn btn-primary" onclick="openVdsAssignmentModal('<?= $service_id ?>')">
                            <i class="fas fa-link"></i> Привязать VDS
                        </button>
                        <button class="btn btn-outline" onclick="checkAutoAssignment()">
                            <i class="fas fa-bolt"></i> Авто-выбор
                        </button>
                    </div>
                </div>
            </div>
            <?php elseif(!$service['vds_ip'] && $isStandard): ?>
            <div class="vds-notice" style="background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(99, 102, 241, 0.05)); border-color: rgba(99, 102, 241, 0.2);">
                <div class="vds-notice-icon" style="background: rgba(99, 102, 241, 0.2); color: var(--primary-color);">
                    <i class="fas fa-rocket"></i>
                </div>
                <div class="vds-notice-content">
                    <h4>Standard Edition</h4>
                    <p>Ваш сервер работает на выделенных ресурсах без привязки к VDS. Полный функционал панели доступен сразу.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Карточки статистики -->
            <div class="stats-grid">
                <div class="stat-card cpu">
                    <div class="stat-icon">
                        <i class="fas fa-microchip"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="cpu-usage">15%</h3>
                        <p>Использование CPU</p>
                    </div>
                    <div class="stat-progress">
                        <div class="progress">
                            <div class="progress-bar" style="width: 15%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card ram">
                    <div class="stat-icon">
                        <i class="fas fa-memory"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="ram-usage">45%</h3>
                        <p>Использование RAM</p>
                    </div>
                    <div class="stat-progress">
                        <div class="progress">
                            <div class="progress-bar" style="width: 45%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card storage">
                    <div class="stat-icon">
                        <i class="fas fa-hdd"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="storage-usage">2.5/10 GB</h3>
                        <p>Хранилище</p>
                    </div>
                    <div class="stat-progress">
                        <div class="progress">
                            <div class="progress-bar" style="width: 25%"></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card network">
                    <div class="stat-icon">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="network-usage">12 GB</h3>
                        <p>Использовано трафика</p>
                    </div>
                    <div class="stat-progress">
                        <div class="progress">
                            <div class="progress-bar" style="width: 12%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Секция управления -->
            <section class="control-section">
                <div class="section-header">
                    <div>
                        <h2>Управление сервером</h2>
                        <div class="section-subtitle">Управляйте состоянием вашего игрового сервера</div>
                    </div>
                </div>

                <div class="control-buttons-grid">
                    <button class="control-btn start" onclick="sendCommand('start')">
                        <div class="control-icon">
                            <i class="fas fa-play"></i>
                        </div>
                        <span>Запустить</span>
                    </button>

                    <button class="control-btn stop" onclick="sendCommand('stop')">
                        <div class="control-icon">
                            <i class="fas fa-stop"></i>
                        </div>
                        <span>Остановить</span>
                    </button>






                    <button class="control-btn restart" onclick="sendCommand('restart')">
                        <div class="control-icon">
                            <i class="fas fa-redo"></i>
                        </div>
                        <span>Перезапустить</span>
                    </button>

                    <a href="#console" class="control-btn" onclick="showTab('console')">
                        <div class="control-icon">
                            <i class="fas fa-terminal"></i>
                        </div>
                        <span>Консоль</span>
                    </a>
                </div>
            </section>







            <!-- Вкладки -->
            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-btn active" data-tab="overview">Обзор</button>
                    <button class="tab-btn" data-tab="console">Консоль</button>
                    <button class="tab-btn" data-tab="files">Файлы</button>
                    <button class="tab-btn" data-tab="settings">Настройки</button>
                    <button class="tab-btn" data-tab="backups">Бэкапы</button>
                    <button class="tab-btn" data-tab="players">Игроки</button>
                    <button class="tab-btn" data-tab="statistics">Статистика</button>
                </div>

                <!-- Вкладка Обзор -->
                <div id="overview" class="tab-content active">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="control-section">
                                <h3 class="mb-4">Информация о сервере</h3>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <div class="text-muted small mb-1">Тариф</div>
                                            <div class="h5"><?= htmlspecialchars($service['tariff_name']) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <div class="text-muted small mb-1">Игра</div>
                                            <div class="h5">
                                                <i class="<?= $game_icon ?> me-2"></i>
                                                <?= htmlspecialchars(ucfirst($service['game'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if($service['vds_ip']): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <div class="text-muted small mb-1">IP Адрес</div>
                                            <div class="h5">
                                                <code><?= htmlspecialchars($service['vds_ip']) ?></code>
                                                <button class="btn btn-sm btn-outline ms-2" onclick="copyToClipboard('<?= htmlspecialchars($service['vds_ip']) ?>')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="info-item">
                                            <div class="text-muted small mb-1">Создан</div>
                                            <div class="h5"><?= date('d.m.Y H:i', strtotime($service['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="control-section">
                                <h3 class="mb-4">Быстрые действия</h3>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline" onclick="sendCommand('status')">
                                        <i class="fas fa-info-circle me-2"></i>Проверить статус
                                    </button>
                                    <button class="btn btn-outline" onclick="showTab('backups')">
                                        <i class="fas fa-save me-2"></i>Создать бэкап
                                    </button>
                                    <button class="btn btn-outline" onclick="showTab('settings')">
                                        <i class="fas fa-cog me-2"></i>Настройки сервера
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Вкладка Консоль -->
                <div id="console" class="tab-content">
                    <div class="terminal-container">
                        <div class="terminal-header">
                            <div class="terminal-title">Консоль сервера</div>
                            <div class="terminal-controls">
                                <button class="terminal-control-btn close"></button>
                                <button class="terminal-control-btn minimize"></button>
                                <button class="terminal-control-btn maximize"></button>
                            </div>
                        </div>
                        <div class="terminal-body" id="terminalOutput">
                            <div class="terminal-line">
                                <span class="terminal-prompt">[<?= date('H:i:s') ?>]</span>
                                <span>Добро пожаловать в панель управления</span>
                            </div>
                            <div class="terminal-line">
                                <span class="terminal-prompt">[<?= date('H:i:s') ?>]</span>
                                <span>Сервер <?= htmlspecialchars(ucfirst($service['game'])) ?> готов к работе</span>
                            </div>
                            <div class="terminal-line">
                                <span class="terminal-prompt">[<?= date('H:i:s') ?>]</span>
                                <span>Используйте поле ввода для отправки команд</span>
                            </div>
                        </div>
                        <input type="text" class="terminal-input" id="commandInput" 
                               placeholder="Введите команду и нажмите Enter..." 
                               onkeypress="if(event.key === 'Enter') sendCustomCommand()">
                    </div>
                </div>

                <!-- Остальные вкладки -->
                <div id="files" class="tab-content">
                    <div class="control-section">
                        <h3>Файловый менеджер</h3>
                        <p class="text-muted">Управление файлами сервера будет доступно после подключения VDS</p>
                    </div>
                </div>

                <div id="settings" class="tab-content">
                    <div class="control-section">
                        <h3>Настройки сервера</h3>
                        <p class="text-muted">Настройки будут доступны после подключения VDS</p>
                    </div>
                </div>
<div class="col-md-6 mb-3">
    <div class="info-item">
        <div class="text-muted small mb-1">Срок действия</div>
        <div class="h5">
            <?php
            if ($service['expires_at']) {
                $expires = new DateTime($service['expires_at']);
                $now = new DateTime();
                $interval = $now->diff($expires);
                
                if ($expires > $now) {
                    echo '<span style="color: #10b981;">';
                    echo $interval->days . ' дней ';
                    echo $interval->h . ' часов';
                    echo '</span><br>';
                    echo '<small>до ' . $expires->format('d.m.Y H:i') . '</small>';
                } else {
                    echo '<span style="color: #ef4444;">Истек ' . $expires->format('d.m.Y') . '</span>';
                }
            } else {
                echo '<span style="color: #6b7280;">Бессрочно</span>';
            }
            ?>
        </div>
    </div>
</div>


<div class="col-md-6 mb-3">
    <div class="info-item">
        <div class="text-muted small mb-1">Продление</div>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-success" onclick="extendServer(30)">
                +30 дней
            </button>
            <button class="btn btn-sm btn-outline-warning" onclick="toggleAutoRenew()">
                <?= $service['auto_renew'] ? 'Авто-продление' : 'Авто-продление' ?>
            </button>
        </div>
    </div>
</div>

        


                <div id="backups" class="tab-content">
                    <div class="control-section">
                        <h3>Бэкапы</h3>
                        <p class="text-muted">Функция бэкапов будет доступна после подключения VDS</p>
                    </div>
                </div>

                <div id="players" class="tab-content">
                    <div class="control-section">
                        <h3>Игроки онлайн</h3>
                        <p class="text-muted">Список игроков будет доступен после запуска сервера</p>
                    </div>
                </div>

                <div id="statistics" class="tab-content">
                    <div class="control-section">
                        <h3>Статистика</h3>
                        <p class="text-muted">Детальная статистика будет доступна после подключения VDS</p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Модальные окна будут добавляться через JS -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            initNavigation();
            initTabs();
            updateStats(); // Первоначальное обновление статистики
            startStatsRefresh(); // Запуск автообновления
        });

        // Навигация
        function initNavigation() {
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Обновляем активное состояние в навигации
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Переключаем вкладку
                    const tabId = this.getAttribute('data-tab');
                    if (tabId) {
                        showTab(tabId);
                    }
                });
            });
        }

        // Вкладки
        function initTabs() {
            const tabBtns = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    // Обновляем активные кнопки
                    tabBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Показываем выбранную вкладку
                    const tabId = this.getAttribute('data-tab');
                    showTab(tabId);
                });
            });
        }

        function showTab(tabId) {
            // Скрываем все вкладки
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Показываем выбранную вкладку
            const tab = document.getElementById(tabId);
            if (tab) {
                tab.classList.add('active');
            }
        }

        // Управление сервером
        function sendCommand(command) {
            showNotification(`Отправка команды: ${command}`, 'info');
            
            fetch('/bill/panel_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    service_id: '<?= $service["service_id"] ?>',
                    command: command
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addTerminalLine(`Команда "${command}" выполнена: ${data.message}`);
                    showNotification(data.message, 'success');
                } else {
                    addTerminalLine(`Ошибка: ${data.message}`, 'error');
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                addTerminalLine('Ошибка соединения', 'error');
                showNotification('Ошибка соединения с сервером', 'error');
            });
        }

        function sendCustomCommand() {
            const input = document.getElementById('commandInput');
            const command = input.value.trim();
            
            if (!command) return;
            
            addTerminalLine(`> ${command}`, 'command');
            input.value = '';
            
            // Симуляция ответа от сервера
            setTimeout(() => {
                const responses = [
                    'Команда выполнена успешно',
                    'Ошибка выполнения команды',
                    'Сервер недоступен',
                    'Неизвестная команда'
                ];
                const response = responses[Math.floor(Math.random() * responses.length)];
                addTerminalLine(response);
            }, 500);
        }

        function addTerminalLine(text, type = 'info') {
            const terminal = document.getElementById('terminalOutput');
            const line = document.createElement('div');
            line.className = 'terminal-line';
            
            const prompt = document.createElement('span');
            prompt.className = 'terminal-prompt';
            prompt.textContent = `[${new Date().toLocaleTimeString('ru-RU')}]`;
            
            const content = document.createElement('span');
            content.textContent = text;
            
            if (type === 'error') {
                content.style.color = '#ef4444';
            } else if (type === 'command') {
                content.style.color = '#10b981';
            }
            
            line.appendChild(prompt);
            line.appendChild(content);
            terminal.appendChild(line);
            
            // Автоскролл
            terminal.scrollTop = terminal.scrollHeight;
        }

        // Статистика
        function updateStats() {
            // Обновляем значения (в реальном приложении здесь будет AJAX запрос)
            document.getElementById('cpu-usage').textContent = 
                Math.floor(Math.random() * 30) + 5 + '%';
            document.getElementById('ram-usage').textContent = 
                Math.floor(Math.random() * 50) + 20 + '%';
            
            const storage = (2.5 + Math.random() * 0.5).toFixed(1);
            document.getElementById('storage-usage').textContent = 
                `${storage}/10 GB`;
            
            const network = 12 + Math.floor(Math.random() * 3);
            document.getElementById('network-usage').textContent = 
                `${network} GB`;
        }

        function startStatsRefresh() {
            setInterval(updateStats, 2000); // Каждые 10 секунд
        }

        // VDS управление
        function openVdsAssignmentModal(serviceId) {
            showNotification('Загрузка доступных VDS серверов...', 'info');
            
            fetch(`/bill/vds_assign_manager.php?action=get_available_vds&service_id=${serviceId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showVdsSelectionModal(data);
                    } else {
                        showNotification(data.message || 'Ошибка загрузки VDS', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Ошибка соединения', 'error');
                });
        }

        function showVdsSelectionModal(data) {
            // Создаем модальное окно с карточками VDS
            const modalHtml = `
                <div class="modal fade" id="vdsSelectionModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content" style="background: var(--darker); color: white; border: 1px solid rgba(255,255,255,0.1);">
                            <div class="modal-header border-0">
                                <h5 class="modal-title">
                                    <i class="fas fa-server me-2"></i>Выберите VDS сервер
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info bg-dark border-info mb-4">
                                    <i class="fas fa-info-circle"></i>
                                    Сервер: <strong>${data.service_info.name}</strong> 
                                    (${data.service_info.game})
                                </div>
                                
                                <div class="row g-3" id="vdsListContainer">
                                    ${data.available_vds.map(vds => `
                                        <div class="col-md-6">
                                            <div class="card bg-dark border-${vds.overall_status === 'good' ? 'success' : vds.overall_status === 'warning' ? 'warning' : 'danger'} h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <div>
                                                            <h6 class="card-title mb-1">${vds.ip}</h6>
                                                            <small class="text-muted">${vds.location}</small>
                                                        </div>
                                                        <span class="badge bg-primary">${vds.free_slots} слотов</span>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <div class="d-flex justify-content-between small mb-1">
                                                            <span>Загрузка:</span>
                                                            <span>${vds.load_percentage}%</span>
                                                        </div>
                                                        <div class="progress" style="height: 4px;">
                                                            <div class="progress-bar bg-${vds.load_percentage < 50 ? 'success' : vds.load_percentage < 80 ? 'warning' : 'danger'}" 
                                                                 style="width: ${vds.load_percentage}%"></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row g-2 mb-3">
                                                        <div class="col-6">
                                                            <small class="d-block">
                                                                <i class="fas fa-plug ${vds.port_checks.ssh ? 'text-success' : 'text-danger'}"></i>
                                                                SSH: ${vds.port_checks.ssh ? '✓' : '✗'}
                                                            </small>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="d-block">
                                                                <i class="fas fa-globe ${vds.port_checks.http ? 'text-success' : 'text-danger'}"></i>
                                                                HTTP: ${vds.port_checks.http ? '✓' : '✗'}
                                                            </small>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="selectVds('${vds.vds_id}', '${data.service_id}')">
                                                            <i class="fas fa-check me-1"></i> Выбрать
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-info"
                                                                onclick="checkVdsStatus('${vds.vds_id}', '${data.service_id}')">
                                                            <i class="fas fa-search me-1"></i> Проверить
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Добавляем модальное окно в DOM
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('vdsSelectionModal'));
            modal.show();
            
            // Удаляем модальное окно при закрытии
            modal._element.addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function selectVds(vdsId, serviceId) {
            window.selectedVdsId = vdsId;
            window.selectedServiceId = serviceId;
            
            fetch('/bill/vds_assign_manager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'assign_vds',
                    vds_id: vdsId,
                    service_id: serviceId,
                    confirm: false
                })
            })
            .then(response => response.json())
            .then(data => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('vdsSelectionModal'));
                if (modal) modal.hide();
                
                if (data.success) {
                    if (data.requires_confirmation) {
                        showConfirmationModal(data.confirmation_data);
                    } else {
                        startAssignment(data);
                    }
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Ошибка соединения', 'error');
            });
        }

        function showConfirmationModal(data) {
            const confirmHtml = `
                <div class="modal fade" id="confirmVdsModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark text-white border-0">
                            <div class="modal-header border-0">
                                <h5 class="modal-title text-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Подтверждение
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-warning bg-dark border-warning mb-3">
                                    <i class="fas fa-info-circle"></i>
                                    Вы собираетесь привязать сервер <strong>${data.service.name}</strong> к VDS <strong>${data.vds.ip}</strong>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="mb-2"><i class="fas fa-clock me-2"></i>Время настройки: <strong>${data.estimated_time}</strong></p>
                                    <p class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>${data.warning}</p>
                                </div>
                                
                                <div class="text-center">
                                    <button class="btn btn-success px-4" onclick="confirmAssignment()">
                                        <i class="fas fa-check me-2"></i>Да, привязать
                                    </button>
                                    <button class="btn btn-secondary ms-2 px-4" data-bs-dismiss="modal">Отмена</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', confirmHtml);
            const modal = new bootstrap.Modal(document.getElementById('confirmVdsModal'));
            modal.show();
            
            modal._element.addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }

        function confirmAssignment() {
            const modal = bootstrap.Modal.getInstance(document.getElementById('confirmVdsModal'));
            if (modal) modal.hide();
            
            fetch('/bill/vds_assign_manager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'assign_vds',
                    vds_id: window.selectedVdsId,
                    service_id: window.selectedServiceId,
                    confirm: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('VDS успешно назначена. Настройка началась.', 'success');
                    showAssignmentProgress(data);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                showNotification('Ошибка соединения', 'error');
            });
        }

        function checkAutoAssignment() {
            showNotification('Поиск оптимальной VDS...', 'info');
            
            // В реальном приложении здесь будет вызов API для авто-выбора
            setTimeout(() => {
                showNotification('Функция авто-выбора в разработке', 'info');
            }, 1500);
        }

        function showAssignmentProgress(data) {
            // Обновляем интерфейс для отображения прогресса
            const vdsNotice = document.querySelector('.vds-notice');
            if (vdsNotice) {
                vdsNotice.innerHTML = `
                    <div class="vds-notice-icon" style="background: rgba(99, 102, 241, 0.2); color: var(--primary-color);">
                        <i class="fas fa-sync-alt fa-spin"></i>
                    </div>
                    <div class="vds-notice-content">
                        <h4>Настройка VDS</h4>
                        <p>Идет привязка сервера к VDS ${data.assignment.vds_ip}. Это займет 5-10 минут.</p>
                        <div class="progress mb-3" style="height: 6px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 30%"></div>
                        </div>
                        <button class="btn btn-outline btn-sm" onclick="checkAssignmentStatus('${data.assignment.id}')">
                            <i class="fas fa-sync me-1"></i>Обновить статус
                        </button>
                    </div>
                `;
            }
        }

        // Вспомогательные функции
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Скопировано в буфер обмена', 'success');
            }).catch(() => {
                showNotification('Ошибка копирования', 'error');
            });
        }

        function showNotification(message, type = 'info') {
            // Создаем уведомление
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
            
            // Добавляем контейнер для тостов, если его нет
            if (!document.getElementById('toast-container')) {
                const container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                container.style.zIndex = '9999';
                document.body.appendChild(container);
            }
            
            // Добавляем тост
            const container = document.getElementById('toast-container');
            container.insertAdjacentHTML('beforeend', toastHtml);
            
            // Показываем тост
            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
            
            // Удаляем после скрытия
            toastEl.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
        
        
        
        
        
        // Добавьте в panel.php в секцию <script>
function extendServer(days) {
    const serviceId = '<?= $service["service_id"] ?>';
    const pricePerDay = <?= $service['price'] / 30 ?>; // Примерная цена за день
    
    fetch('/bill/extend_service.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            service_id: serviceId,
            days: days,
            amount: pricePerDay * days
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Сервер успешно продлен на ' + days + ' дней', 'success');
            setTimeout(() => location.reload(), 2000);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function toggleAutoRenew() {
    const serviceId = '<?= $service["service_id"] ?>';
    
    fetch('/bill/toggle_auto_renew.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ service_id: serviceId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Авто-продление ' + (data.auto_renew ? 'включено' : 'отключено'), 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });
}

// Таймер обратного отсчета
function updateExpirationTimer() {
    const expiresElement = document.querySelector('.expiration-timer');
    if (!expiresElement) return;
    
    const expiresAt = expiresElement.dataset.expires;
    if (!expiresAt) return;
    
    const now = new Date();
    const expires = new Date(expiresAt);
    const diff = expires - now;
    
    if (diff <= 0) {
        expiresElement.innerHTML = '<span style="color: #ef4444;">Срок истек!</span>';
        return;
    }
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    expiresElement.innerHTML = `
        <span style="color: ${days < 3 ? '#f59e0b' : '#10b981'}">
            ${days}д ${hours}ч ${minutes}м
        </span>
    `;
}

// Обновляем таймер каждую минуту
setInterval(updateExpirationTimer, 60000);
updateExpirationTimer();
        
        
        
        
        
    </script>
</body>
</html>

<?php
function adjustBrightness($hex, $steps) {
    $steps = max(-255, min(255, $steps));
    $hex = str_replace('#', '', $hex);
    
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . 
               str_repeat(substr($hex, 1, 1), 2) . 
               str_repeat(substr($hex, 2, 1), 2);
    }
    
    $color_parts = str_split($hex, 2);
    $return = '#';
    
    foreach ($color_parts as $color) {
        $color = hexdec($color);
        $color = max(0, min(255, $color + $steps));
        $return .= str_pad(dechex($color), 2, '0', STR_PAD_LEFT);
    }
    
    return $return;
}
?>