<?php
require_once __DIR__ . '/../config/database.php';

// Start session for user authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize database tables
function initDatabase($pdo) {
    $sql = "
    -- Departments table for user management
    CREATE TABLE IF NOT EXISTS departments (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        manager_id INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_department_name (name)
    );
    
    CREATE TABLE IF NOT EXISTS categories (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE,
        description TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS suppliers (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        contact_person VARCHAR(100),
        email VARCHAR(100) NOT NULL,
        phone VARCHAR(50),
        address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS products (
        id VARCHAR(20) PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        category_id VARCHAR(20),
        quantity INT DEFAULT 0,
        price DECIMAL(10,2) DEFAULT 0.00,
        supplier_id VARCHAR(20),
        status ENUM('In Stock', 'Low Stock', 'Out of Stock') DEFAULT 'In Stock',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
        INDEX idx_product_status (status),
        INDEX idx_product_quantity (quantity)
    );
    
    CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id VARCHAR(20),
        type ENUM('in', 'out') NOT NULL,
        quantity INT NOT NULL,
        supplier_name VARCHAR(100),
        reason VARCHAR(50),
        notes TEXT,
        reference_number VARCHAR(50),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX idx_movement_type (type),
        INDEX idx_created_at (created_at)
    );
    
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_is_read (is_read)
    );
    
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS company_info (
        id INT DEFAULT 1 PRIMARY KEY,
        company_name VARCHAR(200),
        tax_id VARCHAR(50),
        address TEXT,
        phone VARCHAR(50),
        email VARCHAR(100),
        logo VARCHAR(255),
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );
    
    -- Users table with department_id and expanded roles
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        email VARCHAR(100),
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        phone VARCHAR(50),
        avatar VARCHAR(255),
        department_id VARCHAR(20),
        role ENUM('admin', 'manager', 'warehouse', 'accountant', 'viewer', 'user') DEFAULT 'viewer',
        is_active BOOLEAN DEFAULT TRUE,
        last_login DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_department (department_id),
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
    );
    
    -- Login attempts table for security
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50),
        ip_address VARCHAR(45),
        attempt_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_username (username),
        INDEX idx_ip_address (ip_address)
    );
    
    -- Activity logs table
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(50),
        full_name VARCHAR(100),
        action VARCHAR(100) NOT NULL,
        action_type VARCHAR(50),
        entity_type VARCHAR(50),
        entity_id VARCHAR(50),
        old_values TEXT,
        new_values TEXT,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        request_method VARCHAR(10),
        request_url VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_action_type (action_type),
        INDEX idx_entity (entity_type, entity_id),
        INDEX idx_created_at (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );
    ";
    
    try {
        $pdo->exec($sql);
        
        // Insert default departments
        $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO departments (id, name, description) VALUES 
                ('DEPT-1', 'Management', 'Executive and management team'),
                ('DEPT-2', 'Warehouse', 'Inventory and stock management team'),
                ('DEPT-3', 'Sales', 'Sales and customer service team'),
                ('DEPT-4', 'Accounting', 'Finance and accounting team'),
                ('DEPT-5', 'IT', 'Information technology department')");
        }
        
        // Insert default categories
        $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO categories (id, name, description) VALUES 
                ('CAT-1', 'Electronics', 'Gadgets and electronic devices'),
                ('CAT-2', 'Furniture', 'Office and home furniture'),
                ('CAT-3', 'Clothing', 'Apparel and fashion items'),
                ('CAT-4', 'Books', 'Educational and recreational books')");
        }
        
        // Insert default suppliers
        $stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO suppliers (id, name, contact_person, email, phone, address) VALUES 
                ('SUP-1', 'Apple Inc.', 'Tim Cook', 'supply@apple.com', '+1 408 996 1010', 'Cupertino, CA, USA'),
                ('SUP-2', 'Sony Corp', 'John Smith', 'procurement@sony.com', '+81 3 6748 2111', 'Tokyo, Japan'),
                ('SUP-3', 'Dell Technologies', 'Michael Dell', 'suppliers@dell.com', '+1 800 289 3355', 'Round Rock, TX, USA'),
                ('SUP-4', 'Nike Inc.', 'John Donahoe', 'supply@nike.com', '+1 503 671 6453', 'Beaverton, OR, USA'),
                ('SUP-5', 'Penguin Books', 'Markus Dohle', 'orders@penguin.com', '+1 212 366 2000', 'New York, NY, USA')");
        }
        
        // Insert default products
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO products (id, name, category_id, quantity, price, supplier_id, status) VALUES 
                ('PRD-001', 'MacBook Pro 14\"', 'CAT-1', 12, 1999.00, 'SUP-1', 'In Stock'),
                ('PRD-002', 'Sony WH-1000XM5', 'CAT-1', 4, 349.00, 'SUP-2', 'Low Stock'),
                ('PRD-003', 'Office Executive Desk', 'CAT-2', 0, 499.00, 'SUP-3', 'Out of Stock'),
                ('PRD-004', 'Dell UltraSharp Monitor', 'CAT-1', 18, 429.00, 'SUP-3', 'In Stock'),
                ('PRD-005', 'Ergonomic Mesh Chair', 'CAT-2', 7, 1299.00, 'SUP-1', 'Low Stock'),
                ('PRD-006', 'Nike Air Max', 'CAT-3', 25, 129.99, 'SUP-4', 'In Stock'),
                ('PRD-007', 'The Great Gatsby', 'CAT-4', 50, 15.99, 'SUP-5', 'In Stock'),
                ('PRD-008', 'Mechanical Keyboard', 'CAT-1', 32, 89.99, 'SUP-3', 'In Stock')");
        }
        
        // Insert default system settings
        $stmt = $pdo->query("SELECT COUNT(*) FROM system_settings");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES 
                ('low_stock_threshold', '10'),
                ('currency', '$'),
                ('company_name', 'InvenTrack Solutions'),
                ('date_format', 'Y-m-d'),
                ('items_per_page', '10'),
                ('max_login_attempts', '5')");
        }
        
        // Insert default company info
        $stmt = $pdo->query("SELECT COUNT(*) FROM company_info");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO company_info (id, company_name, tax_id, address, phone, email) VALUES 
                (1, 'InvenTrack Solutions', 'INV-12345678', '123 Business Avenue, Tech Park, Suite 100, New York, NY 10001', '+1 (555) 123-4567', 'contact@inventrack.com')");
        }
        
        // Insert default users
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO users (username, password_hash, email, first_name, last_name, phone, department_id, role, is_active) VALUES 
                ('admin', '$hashedPassword', 'admin@inventrack.com', 'Admin', 'User', '+1 (555) 000-0001', 'DEPT-1', 'admin', TRUE),
                ('manager', '$hashedPassword', 'manager@inventrack.com', 'John', 'Manager', '+1 (555) 000-0002', 'DEPT-1', 'manager', TRUE),
                ('warehouse', '$hashedPassword', 'warehouse@inventrack.com', 'Sarah', 'Warehouse', '+1 (555) 000-0003', 'DEPT-2', 'warehouse', TRUE),
                ('accountant', '$hashedPassword', 'accountant@inventrack.com', 'Lisa', 'Accountant', '+1 (555) 000-0004', 'DEPT-4', 'accountant', TRUE),
                ('viewer', '$hashedPassword', 'viewer@inventrack.com', 'Mike', 'Viewer', '+1 (555) 000-0005', 'DEPT-3', 'viewer', TRUE)");
        }
        
        // Add sample stock movements
        $stmt = $pdo->query("SELECT COUNT(*) FROM stock_movements");
        if ($stmt->fetchColumn() == 0) {
            $pdo->exec("INSERT INTO stock_movements (product_id, type, quantity, supplier_name, reason, notes, reference_number) VALUES 
                ('PRD-001', 'in', 15, 'Apple Inc.', NULL, 'Initial stock order', 'PO-2024-001'),
                ('PRD-001', 'out', 3, NULL, 'Sale', 'Customer order #1001', 'SO-2024-001'),
                ('PRD-002', 'in', 10, 'Sony Corp', NULL, 'Initial stock order', 'PO-2024-002'),
                ('PRD-002', 'out', 6, NULL, 'Sale', 'Customer order #1002', 'SO-2024-002'),
                ('PRD-004', 'in', 20, 'Dell Technologies', NULL, 'Initial stock order', 'PO-2024-003'),
                ('PRD-004', 'out', 2, NULL, 'Sale', 'Customer order #1003', 'SO-2024-003'),
                ('PRD-005', 'in', 10, 'Apple Inc.', NULL, 'Initial stock order', 'PO-2024-004'),
                ('PRD-005', 'out', 3, NULL, 'Damaged', 'Damaged during shipping', 'ADJ-2024-001')");
        }
        
    } catch(PDOException $e) {
        error_log("Init error: " . $e->getMessage());
    }
}

// ============= LOGIN SYSTEM FUNCTIONS =============

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

function isAdmin() {
    return hasRole('admin');
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT id, username, email, first_name, last_name, phone, role, department_id, is_active, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function authenticateUser($pdo, $username, $password) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Check login attempts
    $attemptStmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    $attemptStmt->execute([$ip_address]);
    $attempts = $attemptStmt->fetchColumn();
    
    $maxAttempts = 5;
    $settingsStmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'max_login_attempts'");
    $maxAttemptsSetting = $settingsStmt->fetchColumn();
    if ($maxAttemptsSetting) {
        $maxAttempts = (int)$maxAttemptsSetting;
    }
    
    if ($attempts >= $maxAttempts) {
        return ['success' => false, 'error' => 'Too many login attempts. Please try again later.'];
    }
    
    // Get user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $logStmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
        $logStmt->execute([$username, $ip_address]);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        $logStmt = $pdo->prepare("INSERT INTO login_attempts (username, ip_address) VALUES (?, ?)");
        $logStmt->execute([$username, $ip_address]);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Clear login attempts on success
    $clearStmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $clearStmt->execute([$ip_address]);
    
    // Update last login time
    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->execute([$user['id']]);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['logged_in'] = true;
    
    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?error=access_denied');
        exit;
    }
}

initDatabase($pdo);
?>