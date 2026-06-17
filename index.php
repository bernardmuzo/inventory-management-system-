<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Check if this is an API request
$isApiRequest = isset($_GET['route']);

if ($isApiRequest) {
    require_once __DIR__ . '/api/routes.php';
    exit;
}

// Get current user info
$currentUser = getCurrentUser($pdo);

// Load page templates from pages.php
ob_start();
require_once __DIR__ . '/templates/pages.php';
$pageTemplatesJson = json_encode($pageTemplates);
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>InvenTrack Pro | Enterprise Inventory Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800 transition-colors">

<!-- MAIN APP SHELL -->
<div id="appShell" class="min-h-screen">
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden transition-all" onclick="toggleSidebar()"></div>
    
    <!-- SIDEBAR NAVIGATION -->
    <aside id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-white shadow-2xl z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col border-r border-gray-100">
        <div class="p-5 border-b border-gray-100 flex items-center justify-between logo-gradient">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center shadow-md"><i class="fas fa-chart-line text-white text-lg"></i></div>
                <span class="font-bold text-xl text-white">InvenTrack<b class="text-green-200">Pro</b></span>
            </div>
            <button onclick="toggleSidebar()" class="lg:hidden text-white/80 hover:text-white"><i class="fas fa-times text-xl"></i></button>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-2 mb-3 px-3">Main</p>
            <a href="#" data-page="dashboard" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-tachometer-alt w-5 text-green-600"></i><span>Dashboard</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">Inventory</p>
            <a href="#" data-page="products" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-box w-5 text-green-600"></i><span>Products</span></a>
            <a href="#" data-page="categories" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-tags w-5 text-green-600"></i><span>Categories</span></a>
            <a href="#" data-page="suppliers" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-truck w-5 text-green-600"></i><span>Suppliers</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">Stock Operations</p>
            <a href="#" data-page="stockIn" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-arrow-down-to-bracket w-5 text-green-600"></i><span>Stock In</span></a>
            <a href="#" data-page="stockOut" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-arrow-up-from-bracket w-5 text-green-600"></i><span>Stock Out</span></a>
            <a href="#" data-page="stockLevels" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-chart-simple w-5 text-green-600"></i><span>Stock Levels</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">Administration</p>
            <a href="#" data-page="departments" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-building w-5 text-green-600"></i><span>Departments</span></a>
            <a href="#" data-page="users" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-users w-5 text-green-600"></i><span>Users</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">Insights</p>
            <a href="#" data-page="reports" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-chart-pie w-5 text-green-600"></i><span>Reports</span></a>
            <a href="#" data-page="notifications" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-bell w-5 text-green-600"></i><span>Notifications</span><span id="navBadge" class="ml-auto bg-red-500 text-white text-xs px-2 py-0.5 rounded-full hidden">0</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">Account</p>
            <a href="#" data-page="profile" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-user-circle w-5 text-green-600"></i><span>Profile</span></a>
            <a href="#" data-page="settings" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-gray-600 hover:bg-green-50 transition-all"><i class="fas fa-sliders-h w-5 text-green-600"></i><span>Settings</span></a>
            
            <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-4 mb-3 px-3">System</p>
            <a href="#" onclick="logout(); return false;" class="nav-item sidebar-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition-all"><i class="fas fa-sign-out-alt w-5 text-red-600"></i><span>Logout</span></a>
        </nav>
        <div class="p-4 border-t border-gray-100 bg-gray-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full logo-gradient flex items-center justify-center text-white font-bold text-sm shadow-md" id="sidebarAvatar">
                    <?php echo strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1) . substr($currentUser['last_name'] ?? 'D', 0, 1)); ?>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold" id="profileName"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></p>
                    <p class="text-xs text-gray-500" id="profileEmailDisplay"><?php echo htmlspecialchars($currentUser['email'] ?? 'user@inventrack.com'); ?></p>
                    <p class="text-xs text-green-600 capitalize"><?php echo htmlspecialchars($currentUser['role'] ?? 'user'); ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="lg:ml-72 min-h-screen transition-all">
        <header class="glass-effect sticky top-0 z-20 shadow-sm border-b border-gray-200 px-5 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="lg:hidden text-gray-600 text-xl"><i class="fas fa-bars"></i></button>
                <div class="flex items-center gap-2 text-gray-600 bg-white px-3 py-1.5 rounded-full text-sm shadow-sm"><i class="fas fa-chart-line text-green-600 text-xs"></i><span id="breadcrumbText" class="font-medium capitalize">Dashboard</span></div>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="toggleDarkMode()" class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center transition"><i class="fas fa-moon text-gray-600 dark:hidden"></i><i class="fas fa-sun hidden dark:inline text-yellow-500"></i></button>
                <div class="w-9 h-9 rounded-full logo-gradient flex items-center justify-center text-white font-semibold shadow-md">
                    <?php echo strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1)); ?>
                </div>
            </div>
        </header>

        <div class="p-5 md:p-6 max-w-7xl mx-auto" id="pageContainer">
            <div class="flex justify-center items-center h-64">
                <div class="text-center">
                    <div class="loading-spinner mx-auto"></div>
                    <p class="mt-4 text-gray-500">Loading InvenTrack Pro...</p>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- MODALS -->
<div id="productModal" class="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden"><div class="bg-white rounded-2xl w-full max-w-md p-6 shadow-2xl"><div class="flex justify-between items-center mb-4"><h3 class="text-xl font-bold"><i class="fas fa-box text-green-600 mr-2"></i>Add Product</h3><button onclick="closeProductModal()" class="text-gray-500"><i class="fas fa-times"></i></button></div><div class="space-y-4"><div><label class="block text-sm font-medium mb-1">Product Name *</label><input type="text" id="prodName" class="w-full border rounded-xl p-2.5"></div><div><label class="block text-sm font-medium mb-1">Category *</label><select id="prodCategory" class="w-full border rounded-xl p-2.5"></select></div><div class="grid grid-cols-2 gap-3"><div><label>Quantity</label><input type="number" id="prodQty" class="w-full border rounded-xl p-2.5" value="0"></div><div><label>Price</label><input type="number" id="prodPrice" class="w-full border rounded-xl p-2.5" value="0" step="0.01"></div></div><div><label>Supplier</label><select id="prodSupplier" class="w-full border rounded-xl p-2.5"><option value="">Select supplier</option></select></div></div><div class="flex justify-end gap-3 mt-6"><button onclick="closeProductModal()" class="px-4 py-2 border rounded-lg">Cancel</button><button onclick="saveProduct()" class="px-5 py-2 btn-primary text-white rounded-lg">Save</button></div></div></div>

<div id="categoryModal" class="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden"><div class="bg-white rounded-2xl w-full max-w-md p-6"><div class="flex justify-between items-center mb-4"><h3 class="text-xl font-bold"><i class="fas fa-tags text-green-600 mr-2"></i>Add Category</h3><button onclick="closeCategoryModal()" class="text-gray-500"><i class="fas fa-times"></i></button></div><div class="space-y-4"><div><label>Category Name *</label><input type="text" id="catName" class="w-full border rounded-xl p-2.5"></div><div><label>Description</label><textarea id="catDesc" class="w-full border rounded-xl p-2.5" rows="2"></textarea></div></div><div class="flex justify-end gap-3 mt-6"><button onclick="closeCategoryModal()" class="px-4 py-2 border rounded-lg">Cancel</button><button onclick="saveCategory()" class="px-5 py-2 btn-primary text-white rounded-lg">Save</button></div></div></div>

<div id="supplierModal" class="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden"><div class="bg-white rounded-2xl w-full max-w-md p-6"><div class="flex justify-between items-center mb-4"><h3 class="text-xl font-bold"><i class="fas fa-truck text-green-600 mr-2"></i>Add Supplier</h3><button onclick="closeSupplierModal()" class="text-gray-500"><i class="fas fa-times"></i></button></div><div class="space-y-4"><div><label>Company Name *</label><input type="text" id="supName" class="w-full border rounded-xl p-2.5"></div><div><label>Contact Person</label><input type="text" id="supContact" class="w-full border rounded-xl p-2.5"></div><div><label>Email *</label><input type="email" id="supEmail" class="w-full border rounded-xl p-2.5"></div><div><label>Phone</label><input type="tel" id="supPhone" class="w-full border rounded-xl p-2.5"></div><div><label>Address</label><textarea id="supAddress" class="w-full border rounded-xl p-2.5" rows="2"></textarea></div></div><div class="flex justify-end gap-3 mt-6"><button onclick="closeSupplierModal()" class="px-4 py-2 border rounded-lg">Cancel</button><button onclick="saveSupplier()" class="px-5 py-2 btn-primary text-white rounded-lg">Save</button></div></div></div>

<!-- DEPARTMENT MODAL -->
<div id="departmentModal" class="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl w-full max-w-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold"><i class="fas fa-building text-green-600 mr-2"></i><span id="deptModalTitle">Add Department</span></h3>
            <button onclick="closeDepartmentModal()" class="text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Department Name *</label>
                <input type="text" id="deptName" class="w-full border rounded-xl p-2.5" placeholder="e.g., Sales, Warehouse, IT">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea id="deptDesc" class="w-full border rounded-xl p-2.5" rows="2" placeholder="Department description..."></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Manager</label>
                <select id="deptManager" class="w-full border rounded-xl p-2.5">
                    <option value="">Select Manager</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeDepartmentModal()" class="px-4 py-2 border rounded-lg">Cancel</button>
            <button onclick="saveDepartment()" class="px-5 py-2 btn-primary text-white rounded-lg">Save</button>
        </div>
    </div>
</div>

<!-- USER MODAL -->
<div id="userModal" class="modal-overlay fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold"><i class="fas fa-user-plus text-green-600 mr-2"></i><span id="userModalTitle">Add User</span></h3>
            <button onclick="closeUserModal()" class="text-gray-500"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Username *</label>
                    <input type="text" id="userUsername" class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Email *</label>
                    <input type="email" id="userEmail" class="w-full border rounded-xl p-2.5">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">First Name</label>
                    <input type="text" id="userFirstName" class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Last Name</label>
                    <input type="text" id="userLastName" class="w-full border rounded-xl p-2.5">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Phone</label>
                    <input type="tel" id="userPhone" class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Role *</label>
                    <select id="userRole" class="w-full border rounded-xl p-2.5">
                        <option value="warehouse">Warehouse Staff</option>
                        <option value="manager">Manager</option>
                        <option value="accountant">Accountant</option>
                        <option value="viewer">Viewer</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Department</label>
                <select id="userDepartment" class="w-full border rounded-xl p-2.5">
                    <option value="">Select Department</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Password <span id="passwordRequired" class="text-red-500 text-xs">*</span></label>
                    <input type="password" id="userPassword" class="w-full border rounded-xl p-2.5">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Confirm Password</label>
                    <input type="password" id="userConfirmPassword" class="w-full border rounded-xl p-2.5">
                </div>
            </div>
            <div>
                <label class="flex items-center gap-2">
                    <input type="checkbox" id="userActive" checked class="w-4 h-4"> 
                    <span>Active Account</span>
                </label>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeUserModal()" class="px-4 py-2 border rounded-lg">Cancel</button>
            <button onclick="saveUser()" class="px-5 py-2 btn-primary text-white rounded-lg">Save User</button>
        </div>
    </div>
</div>

<div id="toastContainer" class="fixed bottom-5 right-5 z-50 space-y-2"></div>

<script>
    // Pass page templates from PHP to JavaScript
    window.pageTemplates = <?php echo $pageTemplatesJson; ?>;
    
    // Get current user info from PHP
    window.currentUser = {
        id: <?php echo $currentUser['id'] ?? 0; ?>,
        username: '<?php echo addslashes($currentUser['username'] ?? ''); ?>',
        role: '<?php echo $currentUser['role'] ?? 'user'; ?>',
        first_name: '<?php echo addslashes($currentUser['first_name'] ?? ''); ?>',
        last_name: '<?php echo addslashes($currentUser['last_name'] ?? ''); ?>',
        email: '<?php echo addslashes($currentUser['email'] ?? ''); ?>'
    };
    
    // API call function
    async function apiCall(endpoint, method = 'GET', data = null) {
        let url = window.location.pathname + '?route=' + encodeURIComponent(endpoint);
        
        if (method === 'GET' && endpoint.includes('?')) {
            url = window.location.pathname + '?route=' + endpoint.split('?')[0] + '&' + endpoint.split('?')[1];
        }
        
        const options = { 
            method: method, 
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            } 
        };
        if (data && (method === 'POST' || method === 'PUT')) {
            options.body = JSON.stringify(data);
        }
        
        try {
            const response = await fetch(url, options);
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('JSON Parse Error:', text);
                throw new Error('Invalid JSON response from server');
            }
            if (!result.success) throw new Error(result.error || 'Request failed');
            return result.data;
        } catch (error) {
            console.error('API Error:', error, 'Endpoint:', endpoint, 'URL:', url);
            throw error;
        }
    }
    
    // Logout function
    async function logout() {
        try {
            await apiCall('auth/logout', 'POST');
            window.location.href = 'login.php';
        } catch (error) {
            window.location.href = 'login.php';
        }
    }
    
    // Helper functions
    function showToast(msg, type = 'success') {
        let container = document.getElementById("toastContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "toastContainer";
            container.className = "fixed bottom-5 right-5 z-50 space-y-2";
            document.body.appendChild(container);
        }
        let toast = document.createElement("div");
        const colors = { success: 'bg-green-600', error: 'bg-red-500', info: 'bg-blue-500' };
        toast.className = `toast-slide ${colors[type] || colors.info} text-white px-4 py-2.5 rounded-xl shadow-lg flex items-center gap-2 text-sm`;
        let icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
        toast.innerHTML = `<i class="fas ${icon}"></i> ${msg}`;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
    
    function escapeHtml(str) { 
        if (!str) return ''; 
        return str.replace(/[&<>]/g, function(m) { 
            if (m === '&') return '&amp;'; 
            if (m === '<') return '&lt;'; 
            if (m === '>') return '&gt;'; 
            return m; 
        }); 
    }
    
    function toggleSidebar() { 
        const sidebar = document.getElementById("sidebar");
        const overlay = document.getElementById("sidebarOverlay");
        if (sidebar) sidebar.classList.toggle("-translate-x-full");
        if (overlay) overlay.classList.toggle("hidden");
    }
    
    function toggleDarkMode() { 
        document.body.classList.toggle("dark"); 
        localStorage.setItem("darkMode", document.body.classList.contains("dark")); 
    }
    
    // Make functions globally available
    window.apiCall = apiCall;
    window.logout = logout;
    window.showToast = showToast;
    window.escapeHtml = escapeHtml;
    window.toggleSidebar = toggleSidebar;
    window.toggleDarkMode = toggleDarkMode;
</script>
<script src="assets/js/app.js"></script>
<script>
    // Start the application
    init();
</script>
</body>
</html>