<?php
/**
 * VDS Assignment Handler
 * Handles VDS assignment with user folder creation and server build download
 */

session_start();
include __DIR__ . './db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Не авторизован']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$username = $_SESSION['username'];

// Main router
switch ($action) {
    case 'handle_vds_assignment':
        handleVdsAssignment();
        break;
        
    case 'download_build':
        downloadBuild();
        break;
        
    case 'set_permissions':
        setPermissions();
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Неизвестное действие: ' . $action]);
        break;
}

/**
 * Handle VDS assignment - create user folder and download build
 */
function handleVdsAssignment() {
    global $pdo, $username;
    
    $service_id = $_POST['service_id'] ?? '';
    $vds_id = $_POST['vds_id'] ?? '';
    
    // Verify user has access to this service
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as user_id
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
    
    try {
        $pdo->beginTransaction();
        
        // Update service to assign VDS
        $stmt = $pdo->prepare("
            UPDATE services 
            SET vds_id = ?, 
                setup_status = 'assigned',
                updated_at = NOW()
            WHERE service_id = ?
        ");
        $stmt->execute([$vds_id, $service_id]);
        
        // Get VDS details
        $stmt = $pdo->prepare("SELECT * FROM vds WHERE vds_id = ?");
        $stmt->execute([$vds_id]);
        $vds = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$vds) {
            throw new Exception('VDS не найдена');
        }
        
        // Create user folder on VDS
        $user_folder_path = createUserFolder($username, $vds);
        
        // Update service with user folder info
        $stmt = $pdo->prepare("
            UPDATE services 
            SET user_folder = ?, 
                updated_at = NOW()
            WHERE service_id = ?
        ");
        $stmt->execute([$user_folder_path, $service_id]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'VDS успешно назначена, пользовательская папка создана',
            'user_folder' => $user_folder_path,
            'vds_ip' => $vds['ip']
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Ошибка при назначении VDS: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при назначении VDS: ' . $e->getMessage()
        ]);
    }
}

/**
 * Create user folder on VDS
 */
function createUserFolder($username, $vds) {
    // Create local directory structure for the user
    $base_path = "/workspace/vds_users/" . $vds['vds_id'];
    $user_path = $base_path . "/" . $username;
    
    // Create directory if it doesn't exist
    if (!file_exists($user_path)) {
        mkdir($user_path, 0755, true);
    }
    
    return $user_path;
}

/**
 * Download server build from URL
 */
function downloadBuild() {
    global $pdo, $username;
    
    $service_id = $_POST['service_id'] ?? '';
    $build_url = $_POST['build_url'] ?? 'www/mastercore.tech/Ssyd/builds/bombsquad17.zip';
    
    // Verify user has access to this service
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as user_id
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
    
    // Ensure the build URL has proper protocol
    if (!preg_match('/^https?:\/\//', $build_url)) {
        $build_url = 'https://' . $build_url;
    }
    
    $user_folder = $service['user_folder'] ?? '';
    if (empty($user_folder)) {
        echo json_encode(['success' => false, 'message' => 'Папка пользователя не найдена']);
        exit();
    }
    
    // Download the build file
    $build_file = $user_folder . '/server_build.zip';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $build_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
    
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || $http_code !== 200) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ошибка при загрузке сборки: ' . $error . ' HTTP: ' . $http_code
        ]);
        exit();
    }
    
    // Save the downloaded content to file
    $result = file_put_contents($build_file, $content);
    
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Не удалось сохранить файл сборки']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Сборка сервера успешно загружена',
        'file_path' => $build_file,
        'file_size' => filesize($build_file)
    ]);
}

/**
 * Set permissions for server files
 */
function setPermissions() {
    global $pdo, $username;
    
    $service_id = $_POST['service_id'] ?? '';
    
    // Verify user has access to this service
    $stmt = $pdo->prepare("
        SELECT s.*, u.id as user_id
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
    
    $user_folder = $service['user_folder'] ?? '';
    if (empty($user_folder)) {
        echo json_encode(['success' => false, 'message' => 'Папка пользователя не найдена']);
        exit();
    }
    
    // Extract the zip file first if it exists
    $build_file = $user_folder . '/server_build.zip';
    if (file_exists($build_file)) {
        $zip = new ZipArchive();
        $res = $zip->open($build_file);
        if ($res === TRUE) {
            $zip->extractTo($user_folder);
            $zip->close();
            
            // Remove the zip file after extraction
            unlink($build_file);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Не удалось распаковать архив сборки'
            ]);
            exit();
        }
    }
    
    // Set permissions 777 for all files in the user folder
    $result = setRecursivePermissions($user_folder, 0777);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Права доступа успешно установлены',
            'folder_path' => $user_folder
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка при установке прав доступа'
        ]);
    }
}

/**
 * Recursively set permissions for all files and directories
 */
function setRecursivePermissions($dir, $permissions) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    // First set permissions for all files
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            if (!chmod($item->getPathname(), $permissions)) {
                return false;
            }
        } else {
            if (!chmod($item->getPathname(), $permissions)) {
                return false;
            }
        }
    }
    
    // Then set permissions for the main directory
    return chmod($dir, $permissions);
}
?>