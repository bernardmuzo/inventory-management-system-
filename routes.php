<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/init.php';

// Set headers for JSON responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : '';

switch($route) {
    case 'dashboard/stats':
        if ($method === 'GET') {
            try {
                $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                $totalSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
                $threshold = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'")->fetchColumn();
                $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= $threshold AND quantity > 0")->fetchColumn();
                $outOfStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
                $totalValue = $pdo->query("SELECT SUM(quantity * price) FROM products")->fetchColumn();
                
                sendResponse(true, [
                    'totalProducts' => (int)$totalProducts,
                    'totalCategories' => (int)$totalCategories,
                    'totalSuppliers' => (int)$totalSuppliers,
                    'lowStock' => (int)$lowStock,
                    'outOfStock' => (int)$outOfStock,
                    'totalValue' => (float)$totalValue
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'dashboard/activity':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT 10");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'dashboard/alerts':
        if ($method === 'GET') {
            try {
                $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
                $thresholdStmt->execute();
                $threshold = $thresholdStmt->fetchColumn() ?: 10;
                $stmt = $pdo->prepare("SELECT id, name, quantity FROM products WHERE quantity <= ? AND quantity > 0 ORDER BY quantity ASC LIMIT 10");
                $stmt->execute([$threshold]);
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'products':
        if ($method === 'GET') {
            try {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                $category = isset($_GET['category']) ? $_GET['category'] : '';
                $status = isset($_GET['status']) ? $_GET['status'] : '';
                
                $sql = "SELECT p.*, c.name as category_name, c.id as category_id, s.name as supplier_name, s.id as supplier_id
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        LEFT JOIN suppliers s ON p.supplier_id = s.id 
                        WHERE (p.name LIKE ? OR p.id LIKE ?)";
                $params = [$search, $search];
                
                if ($category) {
                    $sql .= " AND c.name = ?";
                    $params[] = $category;
                }
                if ($status) {
                    $sql .= " AND p.status = ?";
                    $params[] = $status;
                }
                
                $countSql = str_replace("p.*, c.name as category_name, c.id as category_id, s.name as supplier_name, s.id as supplier_id", "COUNT(*)", $sql);
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                $offset = ($page - 1) * $limit;
                $sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                sendResponse(true, [
                    'products' => $products,
                    'total' => (int)$total,
                    'page' => $page,
                    'pages' => ceil($total / $limit)
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'PRD-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO products (id, name, category_id, quantity, price, supplier_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $status = $data['quantity'] == 0 ? 'Out of Stock' : ($data['quantity'] <= 10 ? 'Low Stock' : 'In Stock');
                $stmt->execute([$id, $data['name'], $data['category_id'], $data['quantity'], $data['price'], $data['supplier_id'] ?: null, $status]);
                
                checkLowStockAndNotify($pdo);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, quantity = ?, price = ?, supplier_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$data['name'], $data['category_id'], $data['quantity'], $data['price'], $data['supplier_id'] ?: null, $data['id']]);
                updateProductStatus($pdo, $data['id'], $data['quantity']);
                checkLowStockAndNotify($pdo);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Product ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'categories':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY c.name ASC");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'CAT-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO categories (id, name, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['description']]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Category ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'suppliers':
        if ($method === 'GET') {
            try {
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                $stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count FROM suppliers s WHERE s.name LIKE ? OR s.email LIKE ? OR s.contact_person LIKE ? ORDER BY s.name");
                $stmt->execute([$search, $search, $search]);
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'SUP-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO suppliers (id, name, contact_person, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['contact_person'], $data['email'], $data['phone'], $data['address']]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['contact_person'], $data['email'], $data['phone'], $data['address'], $data['id']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Supplier ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/in':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $pdo->beginTransaction();
                
                $checkStmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
                $checkStmt->execute([$data['product_id']]);
                $currentQty = $checkStmt->fetchColumn();
                $newQuantity = $currentQty + $data['quantity'];
                
                $stmt = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newQuantity, $data['product_id']]);
                
                updateProductStatus($pdo, $data['product_id'], $newQuantity);
                
                $movementStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, supplier_name, notes, reference_number, created_at) VALUES (?, 'in', ?, ?, ?, ?, NOW())");
                $movementStmt->execute([$data['product_id'], $data['quantity'], $data['supplier_name'], $data['notes'] ?? null, $data['reference_number'] ?? null]);
                
                $pdo->commit();
                checkLowStockAndNotify($pdo);
                sendResponse(true);
            } catch(Exception $e) {
                $pdo->rollBack();
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/out':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $pdo->beginTransaction();
                
                $checkStmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
                $checkStmt->execute([$data['product_id']]);
                $currentQty = $checkStmt->fetchColumn();
                
                if ($currentQty >= $data['quantity']) {
                    $newQuantity = $currentQty - $data['quantity'];
                    
                    $stmt = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newQuantity, $data['product_id']]);
                    
                    updateProductStatus($pdo, $data['product_id'], $newQuantity);
                    
                    $movementStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, reason, notes, reference_number, created_at) VALUES (?, 'out', ?, ?, ?, ?, NOW())");
                    $movementStmt->execute([$data['product_id'], $data['quantity'], $data['reason'], $data['notes'] ?? null, $data['reference_number'] ?? null]);
                    
                    $pdo->commit();
                    checkLowStockAndNotify($pdo);
                    sendResponse(true);
                } else {
                    $pdo->rollBack();
                    sendResponse(false, null, 'Insufficient stock');
                }
            } catch(Exception $e) {
                $pdo->rollBack();
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/movements':
        if ($method === 'GET') {
            try {
                $type = isset($_GET['type']) ? $_GET['type'] : '';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $sql = "SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id";
                if ($type) {
                    $sql .= " WHERE sm.type = '$type'";
                }
                $sql .= " ORDER BY sm.created_at DESC LIMIT $limit";
                $result = $pdo->query($sql)->fetchAll();
                sendResponse(true, $result);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'notifications':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id === 'all') {
                    $pdo->exec("DELETE FROM notifications");
                    sendResponse(true);
                } elseif ($id) {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'settings':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = [];
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                sendResponse(true, $settings);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                foreach ($data as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'company':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT * FROM company_info WHERE id = 1");
                sendResponse(true, $stmt->fetch());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE company_info SET company_name = ?, tax_id = ?, address = ?, phone = ?, email = ? WHERE id = 1");
                $stmt->execute([$data['company_name'], $data['tax_id'], $data['address'], $data['phone'], $data['email']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'profile':
        if ($method === 'GET') {
            try {
                $username = isset($_GET['username']) ? $_GET['username'] : 'admin';
                $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, role, created_at FROM users WHERE username = ?");
                $stmt->execute([$username]);
                sendResponse(true, $stmt->fetch());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE username = ?");
                $stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['phone'], 'admin']);
                if (!empty($data['password'])) {
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                    $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), 'admin']);
                }
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'lookup/products':
        try {
            $stmt = $pdo->query("SELECT id, name, quantity FROM products ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'lookup/categories':
        try {
            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'lookup/suppliers':
        try {
            $stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/inventory':
        try {
            $stmt = $pdo->query("SELECT p.id, p.name, c.name as category, p.quantity, p.price, p.status, s.name as supplier FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/movements':
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT $limit");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/lowstock':
        try {
            $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
            $thresholdStmt->execute();
            $threshold = $thresholdStmt->fetchColumn() ?: 10;
            $stmt = $pdo->prepare("SELECT id, name, quantity, status FROM products WHERE quantity <= ? AND quantity > 0 ORDER BY quantity ASC");
            $stmt->execute([$threshold]);
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;

    // ============= USER MANAGEMENT ROUTES =============
    case 'departments':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as manager_name 
                                      FROM departments d 
                                      LEFT JOIN users u ON d.manager_id = u.id 
                                      ORDER BY d.name ASC");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'DEPT-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO departments (id, name, description, manager_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['description'], $data['manager_id'] ?: null]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, manager_id = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['description'], $data['manager_id'] ?: null, $data['id']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Department ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    case 'users':
        if ($method === 'GET') {
            try {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                
                $sql = "SELECT u.*, d.name as department_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id 
                        WHERE u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
                        ORDER BY u.created_at DESC";
                $params = [$search, $search, $search, $search];
                
                $countSql = str_replace("u.*, d.name as department_name", "COUNT(*)", $sql);
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                $offset = ($page - 1) * $limit;
                $sql .= " LIMIT $limit OFFSET $offset";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll();
                
                foreach ($users as &$user) {
                    unset($user['password_hash']);
                }
                
                sendResponse(true, [
                    'users' => $users,
                    'total' => (int)$total,
                    'page' => $page,
                    'pages' => ceil($total / $limit)
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, first_name, last_name, phone, department_id, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $data['username'],
                    $hashedPassword,
                    $data['email'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['phone'] ?: null,
                    $data['department_id'] ?: null,
                    $data['role'],
                    $data['is_active'] ?? true
                ]);
                sendResponse(true, ['id' => $pdo->lastInsertId()]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, department_id = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                    $data['phone'] ?: null,
                    $data['department_id'] ?: null,
                    $data['role'],
                    $data['is_active'] ?? true,
                    $data['id']
                ]);
                
                if (!empty($data['password'])) {
                    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $data['id']]);
                }
                
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id && $id != 1) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Cannot delete admin user');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    case 'user/roles':
        if ($method === 'GET') {
            try {
                $roles = [
                    ['value' => 'admin', 'label' => 'Administrator', 'permissions' => ['full_access'], 'color' => 'red'],
                    ['value' => 'manager', 'label' => 'Manager', 'permissions' => ['manage_products', 'manage_stock', 'view_reports'], 'color' => 'blue'],
                    ['value' => 'warehouse', 'label' => 'Warehouse Staff', 'permissions' => ['manage_stock', 'view_products'], 'color' => 'green'],
                    ['value' => 'accountant', 'label' => 'Accountant', 'permissions' => ['view_reports', 'view_products'], 'color' => 'purple'],
                    ['value' => 'viewer', 'label' => 'Viewer', 'permissions' => ['view_products'], 'color' => 'gray']
                ];
                sendResponse(true, $roles);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    // ============= AUTHENTICATION ROUTES =============
    case 'auth/login':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $result = authenticateUser($pdo, $data['username'], $data['password']);
                sendResponse($result['success'], $result['user'] ?? null, $result['error'] ?? null);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
    
    case 'auth/logout':
        if ($method === 'POST') {
            try {
                if (isset($_SESSION['user_id'])) {
                    logActivity($pdo, 'USER_LOGOUT', 'User', $_SESSION['user_id'], "User logged out");
                }
                logoutUser();
                sendResponse(true, ['message' => 'Logged out successfully']);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
    
    case 'auth/check':
        if ($method === 'GET') {
            try {
                $user = null;
                if (isLoggedIn()) {
                    $user = getCurrentUser($pdo);
                    if ($user) {
                        unset($user['password_hash']);
                    }
                }
                sendResponse(true, [
                    'logged_in' => isLoggedIn(),
                    'user' => $user
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    default:
        sendResponse(false, null, 'Invalid endpoint: ' . $route);
}
?><?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/init.php';

// Set headers for JSON responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];
$route = isset($_GET['route']) ? $_GET['route'] : '';

switch($route) {
    case 'dashboard/stats':
        if ($method === 'GET') {
            try {
                $totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
                $totalSuppliers = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();
                $threshold = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'")->fetchColumn();
                $lowStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity <= $threshold AND quantity > 0")->fetchColumn();
                $outOfStock = $pdo->query("SELECT COUNT(*) FROM products WHERE quantity = 0")->fetchColumn();
                $totalValue = $pdo->query("SELECT SUM(quantity * price) FROM products")->fetchColumn();
                
                sendResponse(true, [
                    'totalProducts' => (int)$totalProducts,
                    'totalCategories' => (int)$totalCategories,
                    'totalSuppliers' => (int)$totalSuppliers,
                    'lowStock' => (int)$lowStock,
                    'outOfStock' => (int)$outOfStock,
                    'totalValue' => (float)$totalValue
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'dashboard/activity':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT 10");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'dashboard/alerts':
        if ($method === 'GET') {
            try {
                $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
                $thresholdStmt->execute();
                $threshold = $thresholdStmt->fetchColumn() ?: 10;
                $stmt = $pdo->prepare("SELECT id, name, quantity FROM products WHERE quantity <= ? AND quantity > 0 ORDER BY quantity ASC LIMIT 10");
                $stmt->execute([$threshold]);
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'products':
        if ($method === 'GET') {
            try {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                $category = isset($_GET['category']) ? $_GET['category'] : '';
                $status = isset($_GET['status']) ? $_GET['status'] : '';
                
                $sql = "SELECT p.*, c.name as category_name, c.id as category_id, s.name as supplier_name, s.id as supplier_id
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.id 
                        LEFT JOIN suppliers s ON p.supplier_id = s.id 
                        WHERE (p.name LIKE ? OR p.id LIKE ?)";
                $params = [$search, $search];
                
                if ($category) {
                    $sql .= " AND c.name = ?";
                    $params[] = $category;
                }
                if ($status) {
                    $sql .= " AND p.status = ?";
                    $params[] = $status;
                }
                
                $countSql = str_replace("p.*, c.name as category_name, c.id as category_id, s.name as supplier_name, s.id as supplier_id", "COUNT(*)", $sql);
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                $offset = ($page - 1) * $limit;
                $sql .= " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $products = $stmt->fetchAll();
                
                sendResponse(true, [
                    'products' => $products,
                    'total' => (int)$total,
                    'page' => $page,
                    'pages' => ceil($total / $limit)
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'PRD-' . str_pad(rand(100, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO products (id, name, category_id, quantity, price, supplier_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $status = $data['quantity'] == 0 ? 'Out of Stock' : ($data['quantity'] <= 10 ? 'Low Stock' : 'In Stock');
                $stmt->execute([$id, $data['name'], $data['category_id'], $data['quantity'], $data['price'], $data['supplier_id'] ?: null, $status]);
                
                checkLowStockAndNotify($pdo);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, quantity = ?, price = ?, supplier_id = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$data['name'], $data['category_id'], $data['quantity'], $data['price'], $data['supplier_id'] ?: null, $data['id']]);
                updateProductStatus($pdo, $data['id'], $data['quantity']);
                checkLowStockAndNotify($pdo);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Product ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'categories':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY c.name ASC");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'CAT-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO categories (id, name, description, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['description']]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Category ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'suppliers':
        if ($method === 'GET') {
            try {
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                $stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM products WHERE supplier_id = s.id) as product_count FROM suppliers s WHERE s.name LIKE ? OR s.email LIKE ? OR s.contact_person LIKE ? ORDER BY s.name");
                $stmt->execute([$search, $search, $search]);
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'SUP-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO suppliers (id, name, contact_person, email, phone, address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['contact_person'], $data['email'], $data['phone'], $data['address']]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, address = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['contact_person'], $data['email'], $data['phone'], $data['address'], $data['id']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Supplier ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/in':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $pdo->beginTransaction();
                
                $checkStmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
                $checkStmt->execute([$data['product_id']]);
                $currentQty = $checkStmt->fetchColumn();
                $newQuantity = $currentQty + $data['quantity'];
                
                $stmt = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newQuantity, $data['product_id']]);
                
                updateProductStatus($pdo, $data['product_id'], $newQuantity);
                
                $movementStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, supplier_name, notes, reference_number, created_at) VALUES (?, 'in', ?, ?, ?, ?, NOW())");
                $movementStmt->execute([$data['product_id'], $data['quantity'], $data['supplier_name'], $data['notes'] ?? null, $data['reference_number'] ?? null]);
                
                $pdo->commit();
                checkLowStockAndNotify($pdo);
                sendResponse(true);
            } catch(Exception $e) {
                $pdo->rollBack();
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/out':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $pdo->beginTransaction();
                
                $checkStmt = $pdo->prepare("SELECT quantity FROM products WHERE id = ?");
                $checkStmt->execute([$data['product_id']]);
                $currentQty = $checkStmt->fetchColumn();
                
                if ($currentQty >= $data['quantity']) {
                    $newQuantity = $currentQty - $data['quantity'];
                    
                    $stmt = $pdo->prepare("UPDATE products SET quantity = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$newQuantity, $data['product_id']]);
                    
                    updateProductStatus($pdo, $data['product_id'], $newQuantity);
                    
                    $movementStmt = $pdo->prepare("INSERT INTO stock_movements (product_id, type, quantity, reason, notes, reference_number, created_at) VALUES (?, 'out', ?, ?, ?, ?, NOW())");
                    $movementStmt->execute([$data['product_id'], $data['quantity'], $data['reason'], $data['notes'] ?? null, $data['reference_number'] ?? null]);
                    
                    $pdo->commit();
                    checkLowStockAndNotify($pdo);
                    sendResponse(true);
                } else {
                    $pdo->rollBack();
                    sendResponse(false, null, 'Insufficient stock');
                }
            } catch(Exception $e) {
                $pdo->rollBack();
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'stock/movements':
        if ($method === 'GET') {
            try {
                $type = isset($_GET['type']) ? $_GET['type'] : '';
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $sql = "SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id";
                if ($type) {
                    $sql .= " WHERE sm.type = '$type'";
                }
                $sql .= " ORDER BY sm.created_at DESC LIMIT $limit";
                $result = $pdo->query($sql)->fetchAll();
                sendResponse(true, $result);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'notifications':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id === 'all') {
                    $pdo->exec("DELETE FROM notifications");
                    sendResponse(true);
                } elseif ($id) {
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'settings':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
                $settings = [];
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                sendResponse(true, $settings);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                foreach ($data as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'company':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT * FROM company_info WHERE id = 1");
                sendResponse(true, $stmt->fetch());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE company_info SET company_name = ?, tax_id = ?, address = ?, phone = ?, email = ? WHERE id = 1");
                $stmt->execute([$data['company_name'], $data['tax_id'], $data['address'], $data['phone'], $data['email']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'profile':
        if ($method === 'GET') {
            try {
                $username = isset($_GET['username']) ? $_GET['username'] : 'admin';
                $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, role, created_at FROM users WHERE username = ?");
                $stmt->execute([$username]);
                sendResponse(true, $stmt->fetch());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE username = ?");
                $stmt->execute([$data['first_name'], $data['last_name'], $data['email'], $data['phone'], 'admin']);
                if (!empty($data['password'])) {
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
                    $stmt->execute([password_hash($data['password'], PASSWORD_DEFAULT), 'admin']);
                }
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    case 'lookup/products':
        try {
            $stmt = $pdo->query("SELECT id, name, quantity FROM products ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'lookup/categories':
        try {
            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'lookup/suppliers':
        try {
            $stmt = $pdo->query("SELECT id, name FROM suppliers ORDER BY name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/inventory':
        try {
            $stmt = $pdo->query("SELECT p.id, p.name, c.name as category, p.quantity, p.price, p.status, s.name as supplier FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.name");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/movements':
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
            $stmt = $pdo->query("SELECT sm.*, p.name as product_name FROM stock_movements sm LEFT JOIN products p ON sm.product_id = p.id ORDER BY sm.created_at DESC LIMIT $limit");
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;
        
    case 'reports/lowstock':
        try {
            $thresholdStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
            $thresholdStmt->execute();
            $threshold = $thresholdStmt->fetchColumn() ?: 10;
            $stmt = $pdo->prepare("SELECT id, name, quantity, status FROM products WHERE quantity <= ? AND quantity > 0 ORDER BY quantity ASC");
            $stmt->execute([$threshold]);
            sendResponse(true, $stmt->fetchAll());
        } catch (Exception $e) {
            sendResponse(false, null, $e->getMessage());
        }
        break;

    // ============= USER MANAGEMENT ROUTES =============
    case 'departments':
        if ($method === 'GET') {
            try {
                $stmt = $pdo->query("SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as manager_name 
                                      FROM departments d 
                                      LEFT JOIN users u ON d.manager_id = u.id 
                                      ORDER BY d.name ASC");
                sendResponse(true, $stmt->fetchAll());
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $id = 'DEPT-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO departments (id, name, description, manager_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$id, $data['name'], $data['description'], $data['manager_id'] ?: null]);
                sendResponse(true, ['id' => $id]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE departments SET name = ?, description = ?, manager_id = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['description'], $data['manager_id'] ?: null, $data['id']]);
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id) {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Department ID required');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    case 'users':
        if ($method === 'GET') {
            try {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
                
                $sql = "SELECT u.*, d.name as department_name 
                        FROM users u 
                        LEFT JOIN departments d ON u.department_id = d.id 
                        WHERE u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?
                        ORDER BY u.created_at DESC";
                $params = [$search, $search, $search, $search];
                
                $countSql = str_replace("u.*, d.name as department_name", "COUNT(*)", $sql);
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();
                
                $offset = ($page - 1) * $limit;
                $sql .= " LIMIT $limit OFFSET $offset";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll();
                
                foreach ($users as &$user) {
                    unset($user['password_hash']);
                }
                
                sendResponse(true, [
                    'users' => $users,
                    'total' => (int)$total,
                    'page' => $page,
                    'pages' => ceil($total / $limit)
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'POST') {
            try {
                $data = getRequestData();
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, first_name, last_name, phone, department_id, role, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([
                    $data['username'],
                    $hashedPassword,
                    $data['email'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['phone'] ?: null,
                    $data['department_id'] ?: null,
                    $data['role'],
                    $data['is_active'] ?? true
                ]);
                sendResponse(true, ['id' => $pdo->lastInsertId()]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'PUT') {
            try {
                $data = getRequestData();
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, department_id = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                    $data['phone'] ?: null,
                    $data['department_id'] ?: null,
                    $data['role'],
                    $data['is_active'] ?? true,
                    $data['id']
                ]);
                
                if (!empty($data['password'])) {
                    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hashedPassword, $data['id']]);
                }
                
                sendResponse(true);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        } elseif ($method === 'DELETE') {
            try {
                $id = isset($_GET['id']) ? $_GET['id'] : '';
                if ($id && $id != 1) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$id]);
                    sendResponse(true);
                } else {
                    sendResponse(false, null, 'Cannot delete admin user');
                }
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    case 'user/roles':
        if ($method === 'GET') {
            try {
                $roles = [
                    ['value' => 'admin', 'label' => 'Administrator', 'permissions' => ['full_access'], 'color' => 'red'],
                    ['value' => 'manager', 'label' => 'Manager', 'permissions' => ['manage_products', 'manage_stock', 'view_reports'], 'color' => 'blue'],
                    ['value' => 'warehouse', 'label' => 'Warehouse Staff', 'permissions' => ['manage_stock', 'view_products'], 'color' => 'green'],
                    ['value' => 'accountant', 'label' => 'Accountant', 'permissions' => ['view_reports', 'view_products'], 'color' => 'purple'],
                    ['value' => 'viewer', 'label' => 'Viewer', 'permissions' => ['view_products'], 'color' => 'gray']
                ];
                sendResponse(true, $roles);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;

    // ============= AUTHENTICATION ROUTES =============
    case 'auth/login':
        if ($method === 'POST') {
            try {
                $data = getRequestData();
                $result = authenticateUser($pdo, $data['username'], $data['password']);
                sendResponse($result['success'], $result['user'] ?? null, $result['error'] ?? null);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
    
    case 'auth/logout':
        if ($method === 'POST') {
            try {
                if (isset($_SESSION['user_id'])) {
                    logActivity($pdo, 'USER_LOGOUT', 'User', $_SESSION['user_id'], "User logged out");
                }
                logoutUser();
                sendResponse(true, ['message' => 'Logged out successfully']);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
    
    case 'auth/check':
        if ($method === 'GET') {
            try {
                $user = null;
                if (isLoggedIn()) {
                    $user = getCurrentUser($pdo);
                    if ($user) {
                        unset($user['password_hash']);
                    }
                }
                sendResponse(true, [
                    'logged_in' => isLoggedIn(),
                    'user' => $user
                ]);
            } catch (Exception $e) {
                sendResponse(false, null, $e->getMessage());
            }
        }
        break;
        
    default:
        sendResponse(false, null, 'Invalid endpoint: ' . $route);
}
?>