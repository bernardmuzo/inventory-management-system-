<?php
require_once __DIR__ . '/../config/database.php';

function sendResponse($success, $data = null, $error = null) {
    // Ensure no extra output before JSON
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'data' => $data, 'error' => $error]);
    exit;
}

function getRequestData() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

function updateProductStatus($pdo, $productId, $quantity) {
    $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
    $thresholdStmt->execute();
    $threshold = $thresholdStmt->fetchColumn() ?: 10;
    
    $status = ($quantity == 0) ? 'Out of Stock' : (($quantity <= $threshold) ? 'Low Stock' : 'In Stock');
    $updateStmt = $pdo->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->execute([$status, $productId]);
    return $status;
}

function checkLowStockAndNotify($pdo) {
    $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
    $thresholdStmt->execute();
    $threshold = $thresholdStmt->fetchColumn() ?: 10;
    
    $stmt = $pdo->prepare("SELECT id, name, quantity FROM products WHERE quantity <= ? AND quantity > 0");
    $stmt->execute([$threshold]);
    $lowStockProducts = $stmt->fetchAll();
    
    foreach ($lowStockProducts as $product) {
        $checkStmt = $pdo->prepare("SELECT id FROM notifications WHERE message LIKE ? AND is_read = 0");
        $checkStmt->execute(["Low stock alert: {$product['name']}%"]);
        if (!$checkStmt->fetch()) {
            $insertStmt = $pdo->prepare("INSERT INTO notifications (message, created_at) VALUES (?, NOW())");
            $insertStmt->execute(["⚠️ Low stock alert: {$product['name']} has only {$product['quantity']} units remaining"]);
            
            // Log this system alert
            logActivity($pdo, 'SYSTEM_ALERT', 'Low Stock', $product['id'], "Product {$product['name']} has only {$product['quantity']} units remaining");
        }
    }
}

// ============= PROFESSIONAL LOGGING FUNCTIONS =============

/**
 * Log user activity in the system
 */
function logActivity($pdo, $action, $entity_type = null, $entity_id = null, $details = null, $action_type = null) {
    try {
        // Determine action type if not provided
        if (!$action_type) {
            if (strpos($action, 'CREATE') !== false) {
                $action_type = 'CREATE';
            } elseif (strpos($action, 'UPDATE') !== false) {
                $action_type = 'UPDATE';
            } elseif (strpos($action, 'DELETE') !== false) {
                $action_type = 'DELETE';
            } elseif (strpos($action, 'LOGIN') !== false) {
                $action_type = 'LOGIN';
            } elseif (strpos($action, 'STOCK_IN') !== false) {
                $action_type = 'STOCK_IN';
            } elseif (strpos($action, 'STOCK_OUT') !== false) {
                $action_type = 'STOCK_OUT';
            } else {
                $action_type = 'VIEW';
            }
        }
        
        // Get current user information
        $user_id = null;
        $username = 'system';
        $full_name = 'System';
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $username = $_SESSION['username'] ?? 'unknown';
            $full_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
            $full_name = $full_name ?: $username;
        }
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $request_method = $_SERVER['REQUEST_METHOD'] ?? null;
        $request_url = $_SERVER['REQUEST_URI'] ?? null;
        
        // Insert log entry
        $stmt = $pdo->prepare("INSERT INTO activity_logs 
            (user_id, username, full_name, action, action_type, entity_type, entity_id, details, ip_address, user_agent, request_method, request_url, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $user_id, $username, $full_name, $action, $action_type, 
            $entity_type, $entity_id, $details, $ip_address, $user_agent, 
            $request_method, $request_url
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Get paginated activity logs with filters
 */
function getActivityLogs($pdo, $filters = [], $page = 1, $limit = 20) {
    $sql = "SELECT * FROM activity_logs WHERE 1=1";
    $params = [];
    
    if (!empty($filters['username'])) {
        $sql .= " AND username LIKE ?";
        $params[] = "%{$filters['username']}%";
    }
    if (!empty($filters['action_type'])) {
        $sql .= " AND action_type = ?";
        $params[] = $filters['action_type'];
    }
    if (!empty($filters['entity_type'])) {
        $sql .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
    }
    if (!empty($filters['date_from'])) {
        $sql .= " AND DATE(created_at) >= ?";
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $sql .= " AND DATE(created_at) <= ?";
        $params[] = $filters['date_to'];
    }
    
    // Count total
    $countSql = str_replace("SELECT *", "SELECT COUNT(*)", $sql);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    
    // Get paginated results
    $offset = ($page - 1) * $limit;
    $sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    return [
        'logs' => $logs,
        'total' => (int)$total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ];
}

/**
 * Get log statistics
 */
function getLogStatistics($pdo, $days = 7) {
    $stats = [];
    
    // Total logs
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs");
    $stats['total'] = (int)$stmt->fetchColumn();
    
    // Logs by action type
    $stmt = $pdo->query("SELECT action_type, COUNT(*) as count FROM activity_logs GROUP BY action_type ORDER BY count DESC");
    $stats['by_action_type'] = $stmt->fetchAll();
    
    // Logs by user
    $stmt = $pdo->query("SELECT username, full_name, COUNT(*) as count FROM activity_logs GROUP BY username ORDER BY count DESC LIMIT 10");
    $stats['by_user'] = $stmt->fetchAll();
    
    // Today's logs
    $stmt = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = (int)$stmt->fetchColumn();
    
    // Recent activity by day
    $stmt = $pdo->prepare("SELECT DATE(created_at) as date, COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY date DESC");
    $stmt->execute([$days]);
    $stats['recent_activity'] = $stmt->fetchAll();
    
    return $stats;
}

/**
 * Get unique action types for filter
 */
function getActionTypes($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT action_type FROM activity_logs WHERE action_type IS NOT NULL ORDER BY action_type");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get unique entity types for filter
 */
function getEntityTypes($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT entity_type FROM activity_logs WHERE entity_type IS NOT NULL ORDER BY entity_type");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Clean old logs
 */
function cleanOldLogs($pdo, $days = 90) {
    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
    return $stmt->rowCount();
}
?>