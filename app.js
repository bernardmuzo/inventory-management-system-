// Global state
let currentUser = { first_name: 'Admin', last_name: 'User', email: 'admin@inventrack.com', phone: '+1 (555) 123-4567', created_at: new Date().toISOString() };
let currentReportData = [];
let productsPage = 1;
let currentLowStockThreshold = 10;
let currentCurrency = '$';
let editingSupplierId = null;
let editingProductId = null;

// Department & User Global State
let currentDepartmentId = null;
let currentUserId = null;
let usersCurrentPage = 1;
let usersTotalPages = 1;

// Helper Functions
function showToast(msg, type = 'info') {
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

// ============= DEPARTMENT MANAGEMENT FUNCTIONS =============

async function loadDepartments() {
    try {
        const departments = await apiCall('departments');
        displayDepartments(departments);
        await loadDepartmentDropdowns();
    } catch (error) {
        showToast('Error loading departments: ' + error.message, 'error');
    }
}

function displayDepartments(departments) {
    const tbody = document.getElementById("departmentsTbody");
    if (!tbody) return;
    
    if (departments.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center p-8 text-gray-500">No departments found. Click "Add Department" to create one.很少</td>';
        return;
    }
    
    tbody.innerHTML = departments.map(dept => `
        <tr class="border-b hover:bg-gray-50 transition">
            <td class="p-3 text-sm font-mono text-gray-600">${escapeHtml(dept.id)}</td>
            <td class="p-3"><span class="font-medium">${escapeHtml(dept.name)}</span></td>
            <td class="p-3 text-sm text-gray-600">${escapeHtml(dept.description || '-')}</td>
            <td class="p-3 text-sm">${escapeHtml(dept.manager_name || 'Not assigned')}</td>
            <td class="p-3 text-sm text-gray-500">${dept.created_at ? new Date(dept.created_at).toLocaleDateString() : '-'}</td>
            <td class="p-3">
                <button onclick="editDepartment('${dept.id}')" class="text-blue-600 hover:text-blue-800 mr-2 p-1.5 hover:bg-blue-50 rounded-lg">
                    <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteDepartment('${dept.id}')" class="text-red-600 hover:text-red-800 p-1.5 hover:bg-red-50 rounded-lg">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

async function loadDepartmentDropdowns() {
    try {
        const departments = await apiCall('departments');
        const deptSelect = document.getElementById('userDepartment');
        const managerSelect = document.getElementById('deptManager');
        
        if (deptSelect) {
            deptSelect.innerHTML = '<option value="">Select Department</option>' + 
                departments.map(d => `<option value="${d.id}">${escapeHtml(d.name)}</option>`).join('');
        }
        
        if (managerSelect) {
            try {
                const users = await apiCall('users?limit=100');
                managerSelect.innerHTML = '<option value="">Select Manager</option>' + 
                    (users.users || []).map(u => `<option value="${u.id}">${escapeHtml((u.first_name + ' ' + u.last_name).trim() || u.username)} (${u.username})</option>`).join('');
            } catch (e) {
                managerSelect.innerHTML = '<option value="">Select Manager</option>';
            }
        }
    } catch (error) {
        console.error('Error loading department dropdowns:', error);
    }
}

function openDepartmentModal(deptId = null) {
    currentDepartmentId = deptId;
    document.getElementById('deptName').value = '';
    document.getElementById('deptDesc').value = '';
    document.getElementById('deptManager').value = '';
    
    const modalTitle = document.querySelector("#departmentModal h3 span:first-child");
    if (deptId) {
        if (modalTitle) modalTitle.innerText = 'Edit Department';
        loadDepartmentData(deptId);
    } else {
        if (modalTitle) modalTitle.innerText = 'Add Department';
    }
    
    document.getElementById('departmentModal').classList.remove('hidden');
    loadDepartmentDropdowns();
}

async function loadDepartmentData(id) {
    try {
        const departments = await apiCall('departments');
        const dept = departments.find(d => d.id === id);
        if (dept) {
            document.getElementById('deptName').value = dept.name;
            document.getElementById('deptDesc').value = dept.description || '';
            document.getElementById('deptManager').value = dept.manager_id || '';
        }
    } catch (error) {
        showToast('Error loading department data', 'error');
    }
}

function closeDepartmentModal() {
    document.getElementById('departmentModal').classList.add('hidden');
    currentDepartmentId = null;
}

async function saveDepartment() {
    const name = document.getElementById('deptName').value.trim();
    if (!name) {
        showToast('Department name is required', 'error');
        return;
    }
    
    const data = {
        name: name,
        description: document.getElementById('deptDesc').value,
        manager_id: document.getElementById('deptManager').value || null
    };
    
    try {
        if (currentDepartmentId) {
            data.id = currentDepartmentId;
            await apiCall('departments', 'PUT', data);
            showToast('Department updated successfully', 'success');
        } else {
            await apiCall('departments', 'POST', data);
            showToast('Department created successfully', 'success');
        }
        closeDepartmentModal();
        await loadDepartments();
    } catch (error) {
        showToast('Error saving department: ' + error.message, 'error');
    }
}

async function editDepartment(id) {
    openDepartmentModal(id);
}

async function deleteDepartment(id) {
    if (confirm('Are you sure you want to delete this department? This action cannot be undone.')) {
        try {
            await apiCall('departments?id=' + id, 'DELETE');
            showToast('Department deleted successfully', 'success');
            await loadDepartments();
        } catch (error) {
            showToast('Error deleting department: ' + error.message, 'error');
        }
    }
}

// ============= USER MANAGEMENT FUNCTIONS =============

async function loadUsers(page = 1) {
    usersCurrentPage = page;
    const search = document.getElementById("userSearch")?.value || '';
    try {
        const result = await apiCall(`users?page=${page}&limit=10&search=${encodeURIComponent(search)}`);
        usersTotalPages = result.pages;
        displayUsers(result.users, result.page, result.pages);
    } catch (error) {
        showToast('Error loading users: ' + error.message, 'error');
    }
}

function displayUsers(users, currentPage, totalPages) {
    const tbody = document.getElementById("usersTbody");
    const paginationDiv = document.getElementById("usersPagination");
    
    if (!tbody) return;
    
    if (!users || users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center p-8 text-gray-500">No users found. Click "Add User" to create one.很少</tr>';
        if (paginationDiv) paginationDiv.innerHTML = '';
        return;
    }
    
    const roleColors = {
        'admin': 'bg-red-100 text-red-700',
        'manager': 'bg-blue-100 text-blue-700',
        'warehouse': 'bg-green-100 text-green-700',
        'accountant': 'bg-purple-100 text-purple-700',
        'viewer': 'bg-gray-100 text-gray-700'
    };
    
    tbody.innerHTML = users.map(user => {
        const statusBadge = user.is_active 
            ? '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-700">Active</span>'
            : '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-700">Inactive</span>';
        
        const roleColor = roleColors[user.role] || 'bg-gray-100 text-gray-700';
        const fullName = (user.first_name + ' ' + user.last_name).trim() || user.username;
        const initials = user.first_name ? user.first_name.charAt(0).toUpperCase() : user.username.charAt(0).toUpperCase();
        
        return `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="p-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-green-600 flex items-center justify-center text-white font-bold">
                            ${escapeHtml(initials)}
                        </div>
                        <div>
                            <p class="font-medium">${escapeHtml(fullName)}</p>
                            <p class="text-xs text-gray-500">@${escapeHtml(user.username)}</p>
                        </div>
                    </div>
                </td>
                <td class="p-3 text-sm">${escapeHtml(user.email)}</td>
                <td class="p-3">
                    ${user.department_name ? '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-700">' + escapeHtml(user.department_name) + '</span>' : '<span class="text-gray-400">-</span>'}
                </td>
                <td class="p-3">
                    <span class="capitalize px-2 py-1 text-xs rounded-full ${roleColor}">${escapeHtml(user.role)}</span>
                </td>
                <td class="p-3">${statusBadge}</td>
                <td class="p-3 text-sm text-gray-500">${user.last_login ? new Date(user.last_login).toLocaleDateString() : '-'}</td>
                <td class="p-3">
                    <button onclick="editUser(${user.id})" class="text-blue-600 hover:text-blue-800 mr-2 p-1.5 hover:bg-blue-50 rounded-lg">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${user.id !== 1 ? `<button onclick="deleteUser(${user.id})" class="text-red-600 hover:text-red-800 p-1.5 hover:bg-red-50 rounded-lg">
                        <i class="fas fa-trash"></i>
                    </button>` : '<span class="text-gray-400" title="Cannot delete admin"><i class="fas fa-lock"></i></span>'}
                </td>
            </tr>
        `;
    }).join('');
    
    if (paginationDiv && totalPages > 1) {
        let pagHtml = `
            <div class="flex justify-between items-center w-full">
                <button onclick="loadUsers(${currentPage - 1})" class="px-3 py-1 border rounded-lg ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <span class="text-sm text-gray-600">Page ${currentPage} of ${totalPages}</span>
                <button onclick="loadUsers(${currentPage + 1})" class="px-3 py-1 border rounded-lg ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-gray-50'}" ${currentPage === totalPages ? 'disabled' : ''}>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        `;
        paginationDiv.innerHTML = pagHtml;
    } else if (paginationDiv) {
        paginationDiv.innerHTML = '';
    }
}

function openUserModal(userId = null) {
    currentUserId = userId;
    document.getElementById('userUsername').value = '';
    document.getElementById('userEmail').value = '';
    document.getElementById('userFirstName').value = '';
    document.getElementById('userLastName').value = '';
    document.getElementById('userPhone').value = '';
    document.getElementById('userRole').value = 'warehouse';
    document.getElementById('userDepartment').value = '';
    document.getElementById('userPassword').value = '';
    document.getElementById('userConfirmPassword').value = '';
    document.getElementById('userActive').checked = true;
    document.getElementById('userUsername').disabled = false;
    
    const modalTitle = document.querySelector("#userModal h3 span:first-child");
    const passwordLabel = document.getElementById('passwordRequired');
    
    if (userId) {
        if (modalTitle) modalTitle.innerText = 'Edit User';
        if (passwordLabel) passwordLabel.style.display = 'none';
        loadUserData(userId);
    } else {
        if (modalTitle) modalTitle.innerText = 'Add User';
        if (passwordLabel) passwordLabel.style.display = 'inline';
    }
    
    document.getElementById('userModal').classList.remove('hidden');
    loadDepartmentDropdowns();
}

async function loadUserData(id) {
    try {
        const result = await apiCall(`users?limit=100`);
        const user = result.users.find(u => u.id === id);
        if (user) {
            document.getElementById('userUsername').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userFirstName').value = user.first_name || '';
            document.getElementById('userLastName').value = user.last_name || '';
            document.getElementById('userPhone').value = user.phone || '';
            document.getElementById('userRole').value = user.role;
            document.getElementById('userDepartment').value = user.department_id || '';
            document.getElementById('userActive').checked = user.is_active == 1;
            document.getElementById('userUsername').disabled = true;
        }
    } catch (error) {
        showToast('Error loading user data', 'error');
    }
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
    currentUserId = null;
    const usernameField = document.getElementById('userUsername');
    if (usernameField) usernameField.disabled = false;
}

async function saveUser() {
    const username = document.getElementById('userUsername').value.trim();
    const email = document.getElementById('userEmail').value.trim();
    const password = document.getElementById('userPassword').value;
    const confirmPassword = document.getElementById('userConfirmPassword').value;
    
    if (!username || !email) {
        showToast('Username and email are required', 'error');
        return;
    }
    
    if (!currentUserId && !password) {
        showToast('Password is required for new users', 'error');
        return;
    }
    
    if (password && password !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
    }
    
    const data = {
        username: username,
        email: email,
        first_name: document.getElementById('userFirstName').value,
        last_name: document.getElementById('userLastName').value,
        phone: document.getElementById('userPhone').value,
        role: document.getElementById('userRole').value,
        department_id: document.getElementById('userDepartment').value || null,
        is_active: document.getElementById('userActive').checked ? 1 : 0
    };
    
    if (password) {
        data.password = password;
    }
    
    try {
        if (currentUserId) {
            data.id = currentUserId;
            await apiCall('users', 'PUT', data);
            showToast('User updated successfully', 'success');
        } else {
            await apiCall('users', 'POST', data);
            showToast('User created successfully', 'success');
        }
        closeUserModal();
        await loadUsers(usersCurrentPage);
        await loadDepartmentDropdowns();
    } catch (error) {
        showToast('Error saving user: ' + error.message, 'error');
    }
}

async function editUser(id) {
    openUserModal(id);
}

async function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
        try {
            await apiCall(`users?id=${id}`, 'DELETE');
            showToast('User deleted successfully', 'success');
            await loadUsers(usersCurrentPage);
            await loadDepartmentDropdowns();
        } catch (error) {
            showToast('Error deleting user: ' + error.message, 'error');
        }
    }
}

// Dashboard Functions
async function loadDashboardStats() {
    try {
        const stats = await apiCall('dashboard/stats');
        const statsGrid = document.getElementById("statsGrid");
        if (statsGrid) {
            statsGrid.innerHTML = `
                <div class="stat-card p-5 shadow-sm border-l-4 border-green-500"><div class="text-3xl font-bold text-green-600">${stats.totalProducts}</div><div class="text-gray-500 text-sm">Total Products</div></div>
                <div class="stat-card p-5 shadow-sm border-l-4 border-green-500"><div class="text-3xl font-bold text-green-600">${stats.totalCategories}</div><div class="text-gray-500 text-sm">Categories</div></div>
                <div class="stat-card p-5 shadow-sm border-l-4 border-green-500"><div class="text-3xl font-bold text-green-600">${stats.totalSuppliers}</div><div class="text-gray-500 text-sm">Suppliers</div></div>
                <div class="stat-card p-5 shadow-sm border-l-4 border-red-500"><div class="text-3xl font-bold text-red-500">${stats.lowStock}</div><div class="text-gray-500 text-sm">Low Stock</div></div>
                <div class="stat-card p-5 shadow-sm border-l-4 border-green-500"><div class="text-3xl font-bold text-green-600">${currentCurrency}${(stats.totalValue || 0).toLocaleString()}</div><div class="text-gray-500 text-sm">Inventory Value</div></div>
            `;
        }
        
        const activity = await apiCall('dashboard/activity');
        const activityList = document.getElementById("activityList");
        if (activityList) {
            activityList.innerHTML = activity.length ? activity.map(m => `<div class="flex items-center gap-3 text-sm p-3 bg-gray-50 rounded-xl"><span class="w-8 h-8 rounded-full ${m.type === 'in' ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'} flex items-center justify-center"><i class="fas fa-${m.type === 'in' ? 'arrow-down' : 'arrow-up'} text-xs"></i></span><div class="flex-1"><p class="font-medium">${m.type === 'in' ? 'Stock In' : 'Stock Out'}</p><p class="text-xs text-gray-500">${m.quantity} units of ${m.product_name || 'Unknown'}</p></div><span class="text-xs text-gray-400">${new Date(m.created_at).toLocaleDateString()}</span></div>`).join('') : "<div class='text-center text-gray-400 py-4'>No activity yet</div>";
        }
        
        const alerts = await apiCall('dashboard/alerts');
        const alertList = document.getElementById("dashLowStockList");
        if (alertList) {
            alertList.innerHTML = alerts.length ? alerts.map(p => `<div class="bg-amber-50 p-3 rounded-xl flex justify-between items-center"><span class="font-medium">${escapeHtml(p.name)}</span><span class="text-sm font-bold text-amber-700">⚠️ ${p.quantity} units left</span></div>`).join('') : "<div class='text-green-600 text-center py-4'>All stock levels are healthy</div>";
        }
    } catch (error) { 
        console.error('Dashboard error:', error);
        showToast("Error loading dashboard: " + error.message, 'error');
    }
}

// Products Functions
async function loadProducts() {
    const search = document.getElementById("productSearch")?.value || '';
    const category = document.getElementById("productCatFilter")?.value || '';
    const status = document.getElementById("productStatusFilter")?.value || '';
    
    try {
        const params = new URLSearchParams({ page: productsPage, limit: 10, search: search, category: category, status: status });
        const result = await apiCall(`products?${params.toString()}`);
        
        if (!result || !result.products) {
            console.error("Invalid products response:", result);
            const tbody = document.getElementById("productTbody");
            if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-red-500">Error loading products很少</td>';
            return;
        }
        
        const tbody = document.getElementById("productTbody");
        const countSpan = document.getElementById("productCount");
        const paginationDiv = document.getElementById("productPagination");
        
        if (!tbody) return;
        
        if (result.products.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-gray-500">No products found. Click "New Product" to add one.很少</td>';
            if (countSpan) countSpan.innerText = '0 products';
            if (paginationDiv) paginationDiv.innerHTML = '';
            return;
        }
        
        tbody.innerHTML = result.products.map(p => `
            <tr class="border-b hover:bg-gray-50 transition">
                <td class="p-3 text-xs font-mono">${escapeHtml(p.id)}</td>
                <td class="p-3 font-medium">${escapeHtml(p.name)}</td>
                <td class="p-3">${escapeHtml(p.category_name || '-')}</td>
                <td class="p-3 font-bold">${p.quantity}</td>
                <td class="p-3">${currentCurrency}${parseFloat(p.price || 0).toFixed(2)}</td>
                <td class="p-3"><span class="status-badge status-${p.status === 'In Stock' ? 'instock' : p.status === 'Low Stock' ? 'lowstock' : 'outstock'}">${p.status}</span></td>
                <td class="p-3">
                    <button onclick="editProduct('${p.id}')" class="text-green-600 mr-2 p-1.5 hover:bg-green-50 rounded-lg"><i class="fas fa-edit"></i></button>
                    <button onclick="deleteProduct('${p.id}')" class="text-red-500 p-1.5 hover:bg-red-50 rounded-lg"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');
        
        if (countSpan) countSpan.innerText = `Showing ${result.products.length} of ${result.total} products`;
        
        let pagHtml = '';
        for (let i = 1; i <= result.pages; i++) {
            pagHtml += `<button onclick="changeProductPage(${i})" class="px-3 py-1 border rounded-lg text-sm ${i === productsPage ? 'btn-primary text-white' : 'border hover:bg-gray-50'}">${i}</button>`;
        }
        if (paginationDiv) paginationDiv.innerHTML = pagHtml;
        
    } catch (error) { 
        console.error('Products error:', error);
        const tbody = document.getElementById("productTbody");
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center p-4 text-red-500">Error loading products: ${error.message}很少</td>`;
        }
        showToast("Error loading products: " + error.message, 'error');
    }
}

function changeProductPage(page) { 
    productsPage = page; 
    loadProducts(); 
}

async function openProductModal() {
    editingProductId = null;
    document.getElementById("prodName").value = '';
    document.getElementById("prodQty").value = '0';
    document.getElementById("prodPrice").value = '0';
    try {
        const categories = await apiCall('lookup/categories');
        const suppliers = await apiCall('lookup/suppliers');
        const catSelect = document.getElementById("prodCategory");
        const supSelect = document.getElementById("prodSupplier");
        if (catSelect) catSelect.innerHTML = categories.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        if (supSelect) supSelect.innerHTML = '<option value="">Select supplier</option>' + suppliers.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
        document.getElementById("productModal").classList.remove("hidden");
    } catch (error) {
        showToast("Error loading form data: " + error.message, 'error');
    }
}

function closeProductModal() { 
    document.getElementById("productModal").classList.add("hidden"); 
}

async function saveProduct() {
    const name = document.getElementById("prodName")?.value.trim();
    const category_id = document.getElementById("prodCategory")?.value;
    const quantity = parseInt(document.getElementById("prodQty")?.value) || 0;
    const price = parseFloat(document.getElementById("prodPrice")?.value) || 0;
    const supplier_id = document.getElementById("prodSupplier")?.value || null;
    
    if (!name || !category_id) { 
        showToast("Product name and category are required", 'error'); 
        return; 
    }
    
    const productData = { name, category_id, quantity, price, supplier_id };
    
    try {
        if (editingProductId) { 
            productData.id = editingProductId; 
            await apiCall('products', 'PUT', productData); 
            showToast("Product updated successfully", 'success'); 
        } else { 
            await apiCall('products', 'POST', productData); 
            showToast("Product added successfully", 'success'); 
        }
        
        closeProductModal();
        productsPage = 1;
        
        const searchInput = document.getElementById("productSearch");
        const categoryFilter = document.getElementById("productCatFilter");
        const statusFilter = document.getElementById("productStatusFilter");
        
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        
        setTimeout(async () => {
            await loadProducts();
            await loadDashboardStats();
            await loadStockLevels();
            await populateProductDropdowns();
        }, 500);
        
    } catch (error) {
        console.error('Save product error:', error);
        showToast("Error saving product: " + error.message, 'error');
    }
}

async function editProduct(id) {
    editingProductId = id;
    try {
        const result = await apiCall('products');
        const product = result.products?.find(p => p.id === id);
        if (product) {
            document.getElementById("prodName").value = product.name;
            document.getElementById("prodCategory").value = product.category_id;
            document.getElementById("prodQty").value = product.quantity;
            document.getElementById("prodPrice").value = product.price;
            document.getElementById("prodSupplier").value = product.supplier_id || '';
            document.getElementById("productModal").classList.remove("hidden");
            const modalTitle = document.querySelector("#productModal h3");
            if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-edit text-green-600 mr-2"></i>Edit Product';
        }
    } catch (error) {
        showToast("Error loading product: " + error.message, 'error');
    }
}

async function deleteProduct(id) {
    if (confirm("Delete this product?")) {
        try {
            await apiCall(`products?id=${id}`, 'DELETE');
            showToast("Product deleted", 'success');
            await loadProducts();
            await refreshAll();
        } catch (error) {
            showToast("Error deleting product: " + error.message, 'error');
        }
    }
}

// Categories Functions
async function loadCategories() {
    try {
        const categories = await apiCall('categories');
        const tbody = document.getElementById("categoryTbody");
        if (tbody) {
            tbody.innerHTML = categories.map(c => `
                <tr class="border-b hover:bg-gray-50">
                    <td class="p-3">${escapeHtml(c.id)}</td>
                    <td class="p-3 font-medium">${escapeHtml(c.name)}</td>
                    <td class="p-3 text-gray-500">${escapeHtml(c.description || '-')}</td>
                    <td class="p-3 text-center">${c.product_count || 0}</td>
                    <td class="p-3 text-sm text-gray-500">${new Date(c.created_at).toLocaleDateString()}</td>
                    <td class="p-3"><button onclick="deleteCategory('${c.id}')" class="text-red-500 p-1.5 hover:bg-red-50 rounded-lg"><i class="fas fa-trash"></i></button></td>
                </tr>
            `).join('');
        }
    } catch (error) {
        console.error('Categories error:', error);
        showToast("Error loading categories: " + error.message, 'error');
    }
}

function openCategoryModal() { 
    document.getElementById("catName").value = ''; 
    document.getElementById("catDesc").value = ''; 
    document.getElementById("categoryModal").classList.remove("hidden"); 
}

function closeCategoryModal() { 
    document.getElementById("categoryModal").classList.add("hidden"); 
}

async function saveCategory() {
    const name = document.getElementById("catName")?.value.trim();
    if (!name) { showToast("Category name required", 'error'); return; }
    try {
        await apiCall('categories', 'POST', { name, description: document.getElementById("catDesc")?.value });
        closeCategoryModal();
        await loadCategories();
        await refreshAll();
        showToast("Category added", 'success');
    } catch (error) {
        showToast("Error saving category: " + error.message, 'error');
    }
}

async function deleteCategory(id) {
    if (confirm("Delete category?")) {
        try {
            await apiCall(`categories?id=${id}`, 'DELETE');
            await loadCategories();
            await refreshAll();
            showToast("Category deleted", 'success');
        } catch (error) {
            showToast("Error deleting category: " + error.message, 'error');
        }
    }
}

// Suppliers Functions
async function loadSuppliers() {
    const search = document.getElementById("supplierSearch")?.value || '';
    const grid = document.getElementById("supplierGrid");
    const emptyDiv = document.getElementById("emptySuppliers");
    const countSpan = document.getElementById("supplierCount");
    
    try {
        const suppliers = await apiCall(`suppliers?search=${encodeURIComponent(search)}`);
        
        if (!suppliers || suppliers.length === 0) { 
            if (grid) grid.innerHTML = ''; 
            if (emptyDiv) emptyDiv.classList.remove('hidden'); 
            if (countSpan) countSpan.innerText = '0';
            return; 
        }
        
        if (emptyDiv) emptyDiv.classList.add('hidden');
        if (countSpan) countSpan.innerText = suppliers.length;
        
        if (grid) {
            grid.innerHTML = suppliers.map(s => `
                <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5 card-hover">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center">
                                <i class="fas fa-building text-green-600 text-xl"></i>
                            </div>
                            <h3 class="font-bold text-lg mt-2">${escapeHtml(s.name)}</h3>
                        </div>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-1 rounded-full">${escapeHtml(s.id)}</span>
                    </div>
                    <div class="mt-3 space-y-2 text-sm">
                        <div class="flex items-center gap-2"><i class="fas fa-user w-4 text-gray-400"></i><span>${escapeHtml(s.contact_person || 'No contact')}</span></div>
                        <div class="flex items-center gap-2"><i class="fas fa-envelope w-4 text-gray-400"></i><a href="mailto:${s.email}" class="text-green-600">${escapeHtml(s.email)}</a></div>
                        <div class="flex items-center gap-2"><i class="fas fa-phone w-4 text-gray-400"></i><span>${escapeHtml(s.phone || 'No phone')}</span></div>
                        <div class="flex items-start gap-2 text-xs text-gray-500"><i class="fas fa-location-dot w-4 mt-0.5"></i><span>${escapeHtml(s.address || 'No address')}</span></div>
                    </div>
                    <div class="mt-4 pt-3 border-t flex justify-end gap-2">
                        <button onclick="editSupplier('${s.id}')" class="px-3 py-1.5 text-green-600 hover:bg-green-50 rounded-lg"><i class="fas fa-edit"></i> Edit</button>
                        <button onclick="deleteSupplier('${s.id}')" class="px-3 py-1.5 text-red-500 hover:bg-red-50 rounded-lg"><i class="fas fa-trash"></i> Delete</button>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Suppliers error:', error);
        showToast("Error loading suppliers: " + error.message, 'error');
        if (grid) grid.innerHTML = '';
        if (emptyDiv) emptyDiv.classList.remove('hidden');
        if (countSpan) countSpan.innerText = '0';
    }
}

function openSupplierModal() { 
    editingSupplierId = null; 
    document.getElementById("supName").value = ''; 
    document.getElementById("supContact").value = ''; 
    document.getElementById("supEmail").value = ''; 
    document.getElementById("supPhone").value = ''; 
    document.getElementById("supAddress").value = ''; 
    document.getElementById("supplierModal").classList.remove("hidden"); 
}

function closeSupplierModal() { 
    document.getElementById("supplierModal").classList.add("hidden"); 
}

async function editSupplier(id) {
    try {
        const suppliers = await apiCall('suppliers');
        const supplier = suppliers.find(s => s.id === id);
        if (supplier) {
            editingSupplierId = id;
            document.getElementById("supName").value = supplier.name;
            document.getElementById("supContact").value = supplier.contact_person || '';
            document.getElementById("supEmail").value = supplier.email;
            document.getElementById("supPhone").value = supplier.phone || '';
            document.getElementById("supAddress").value = supplier.address || '';
            document.getElementById("supplierModal").classList.remove("hidden");
        }
    } catch (error) {
        showToast("Error loading supplier: " + error.message, 'error');
    }
}

async function saveSupplier() {
    const name = document.getElementById("supName")?.value.trim();
    const email = document.getElementById("supEmail")?.value.trim();
    
    if (!name || !email) { 
        showToast("Supplier name and email required", 'error'); 
        return; 
    }
    
    const supplierData = { 
        name: name, 
        contact_person: document.getElementById("supContact")?.value, 
        email: email, 
        phone: document.getElementById("supPhone")?.value, 
        address: document.getElementById("supAddress")?.value 
    };
    
    try {
        if (editingSupplierId) { 
            supplierData.id = editingSupplierId; 
            await apiCall('suppliers', 'PUT', supplierData); 
            showToast("Supplier updated", 'success'); 
        } else { 
            await apiCall('suppliers', 'POST', supplierData); 
            showToast("Supplier added", 'success'); 
        }
        closeSupplierModal();
        await loadSuppliers();
        await refreshAll();
    } catch (error) {
        showToast("Error saving supplier: " + error.message, 'error');
    }
}

async function deleteSupplier(id) {
    if (confirm("Delete this supplier?")) { 
        try {
            await apiCall(`suppliers?id=${id}`, 'DELETE'); 
            await loadSuppliers(); 
            await refreshAll(); 
            showToast("Supplier deleted", 'success');
        } catch (error) {
            showToast("Error deleting supplier: " + error.message, 'error');
        }
    }
}

// Stock Functions
async function loadStockMovements() {
    try {
        const stockIn = await apiCall('stock/movements?type=in');
        const stockOut = await apiCall('stock/movements?type=out');
        
        const stockInList = document.getElementById("stockInList");
        const stockOutList = document.getElementById("stockOutList");
        
        if (stockInList) {
            if (stockIn && stockIn.length > 0) {
                stockInList.innerHTML = stockIn.slice(0, 10).map(m => `
                    <div class="flex justify-between items-center p-4 bg-green-50 rounded-xl hover:shadow-md transition mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1"><i class="fas fa-plus-circle text-green-600"></i><span class="font-semibold text-gray-800">${escapeHtml(m.product_name || 'Unknown Product')}</span></div>
                            <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><i class="fas fa-box"></i> Quantity: ${m.quantity} units</span>
                                ${m.reference_number ? `<span class="flex items-center gap-1"><i class="fas fa-hashtag"></i> Ref: ${escapeHtml(m.reference_number)}</span>` : ''}
                                ${m.supplier_name ? `<span class="flex items-center gap-1"><i class="fas fa-building"></i> Supplier: ${escapeHtml(m.supplier_name)}</span>` : ''}
                            </div>
                        </div>
                        <div class="text-right"><span class="font-bold text-green-600 text-lg">+${m.quantity}</span><p class="text-xs text-gray-400">${new Date(m.created_at).toLocaleString()}</p></div>
                    </div>
                `).join('');
            } else {
                stockInList.innerHTML = '<div class="text-center text-gray-400 py-8"><i class="fas fa-inbox text-4xl mb-2"></i><p>No stock in records yet. Add your first stock receipt!</p></div>';
            }
        }
        
        if (stockOutList) {
            if (stockOut && stockOut.length > 0) {
                stockOutList.innerHTML = stockOut.slice(0, 10).map(m => `
                    <div class="flex justify-between items-center p-4 bg-red-50 rounded-xl hover:shadow-md transition mb-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1"><i class="fas fa-minus-circle text-red-600"></i><span class="font-semibold text-gray-800">${escapeHtml(m.product_name || 'Unknown Product')}</span></div>
                            <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                <span class="flex items-center gap-1"><i class="fas fa-box"></i> Quantity: ${m.quantity} units</span>
                                ${m.reference_number ? `<span class="flex items-center gap-1"><i class="fas fa-hashtag"></i> Ref: ${escapeHtml(m.reference_number)}</span>` : ''}
                                ${m.reason ? `<span class="flex items-center gap-1"><i class="fas fa-tag"></i> Reason: ${escapeHtml(m.reason)}</span>` : ''}
                            </div>
                        </div>
                        <div class="text-right"><span class="font-bold text-red-600 text-lg">-${m.quantity}</span><p class="text-xs text-gray-400">${new Date(m.created_at).toLocaleString()}</p></div>
                    </div>
                `).join('');
            } else {
                stockOutList.innerHTML = '<div class="text-center text-gray-400 py-8"><i class="fas fa-inbox text-4xl mb-2"></i><p>No stock out records yet. Dispatch some stock!</p></div>';
            }
        }
    } catch (error) { 
        console.error('Stock movements error:', error);
        showToast("Error loading stock movements: " + error.message, 'error');
    }
}

async function recordStockIn() {
    const product_id = document.getElementById("siProduct")?.value;
    const quantity = parseInt(document.getElementById("siQty")?.value);
    const supplier_name = document.getElementById("siSupplier")?.value;
    const reference_number = document.getElementById("siReference")?.value;
    const notes = document.getElementById("siNotes")?.value;
    
    if (!product_id) { showToast("Please select a product", 'error'); return; }
    if (!quantity || quantity <= 0) { showToast("Please enter a valid quantity", 'error'); return; }
    if (!supplier_name) { showToast("Please select a supplier", 'error'); return; }
    
    try {
        await apiCall('stock/in', 'POST', { product_id, quantity, supplier_name, notes, reference_number });
        showToast(`Successfully added ${quantity} units to stock!`, 'success');
        
        if (document.getElementById("siQty")) document.getElementById("siQty").value = '';
        if (document.getElementById("siNotes")) document.getElementById("siNotes").value = '';
        if (document.getElementById("siReference")) document.getElementById("siReference").value = '';
        
        await loadStockMovements();
        await loadDashboardStats();
        await loadStockLevels();
        await populateProductDropdowns();
        
    } catch (error) {
        console.error('Record stock in error:', error);
        showToast("Failed to add stock: " + error.message, 'error');
    }
}

async function recordStockOut() {
    const product_id = document.getElementById("soProduct")?.value;
    const quantity = parseInt(document.getElementById("soQty")?.value);
    const reason = document.getElementById("soReason")?.value;
    const reference_number = document.getElementById("soReference")?.value;
    const notes = document.getElementById("soNotes")?.value;
    
    if (!product_id) { showToast("Please select a product", 'error'); return; }
    if (!quantity || quantity <= 0) { showToast("Please enter a valid quantity", 'error'); return; }
    if (!reason) { showToast("Please select a dispatch reason", 'error'); return; }
    
    try {
        await apiCall('stock/out', 'POST', { product_id, quantity, reason, notes, reference_number });
        showToast(`Successfully removed ${quantity} units from stock!`, 'success');
        
        if (document.getElementById("soQty")) document.getElementById("soQty").value = '';
        if (document.getElementById("soNotes")) document.getElementById("soNotes").value = '';
        if (document.getElementById("soReference")) document.getElementById("soReference").value = '';
        
        await loadStockMovements();
        await loadDashboardStats();
        await loadStockLevels();
        await populateProductDropdowns();
        
    } catch (error) {
        console.error('Record stock out error:', error);
        showToast("Failed to remove stock: " + error.message, 'error');
    }
}

// Stock Levels Page
async function loadStockLevels() {
    try {
        const products = await apiCall('reports/inventory');
        const tbody = document.getElementById("stockLevelTbody");
        if (tbody) {
            tbody.innerHTML = products.map(p => { 
                let percent = Math.min((p.quantity / 100) * 100, 100); 
                let fillClass = percent < 30 ? "bg-red-500" : (percent < 70 ? "bg-amber-500" : "bg-green-500"); 
                return `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-3 font-medium">${escapeHtml(p.name)}</td>
                        <td class="p-3">${escapeHtml(p.category || '-')}</td>
                        <td class="p-3 font-bold">${p.quantity}</td>
                        <td class="p-3"><span class="status-badge status-${p.status === 'In Stock' ? 'instock' : p.status === 'Low Stock' ? 'lowstock' : 'outstock'}">${p.status}</span></td>
                        <td class="p-3"><div class="level-bar"><div class="level-fill ${fillClass}" style="width:${percent}%"></div></div></td>
                        <td class="p-3 text-xs text-gray-500">-</td>
                    </tr>
                `; 
            }).join('');
        }
    } catch (error) {
        console.error('Stock levels error:', error);
        showToast("Error loading stock levels: " + error.message, 'error');
    }
}

// Notifications
async function loadNotifications() {
    try {
        const notifications = await apiCall('notifications');
        const list = document.getElementById("notificationList");
        if (list) {
            list.innerHTML = notifications.map(n => `
                <div class="p-4 border-b flex justify-between items-center hover:bg-gray-50">
                    <div><i class="fas fa-bell text-amber-500 mr-2"></i><span>${escapeHtml(n.message)}</span><p class="text-xs text-gray-400 mt-1">${new Date(n.created_at).toLocaleString()}</p></div>
                    <button onclick="markNotificationRead(${n.id})" class="text-green-600 text-sm">Dismiss</button>
                </div>
            `).join('') || "<div class='text-center text-gray-400 py-8'>No notifications</div>";
            
            const unreadCount = notifications.filter(n => !n.is_read).length;
            const badge = document.getElementById("navBadge");
            if (unreadCount > 0 && badge) { 
                badge.classList.remove("hidden"); 
                badge.innerText = unreadCount; 
            } else if (badge) { 
                badge.classList.add("hidden"); 
            }
        }
    } catch (error) {
        console.error('Notifications error:', error);
    }
}

async function markNotificationRead(id) { 
    try {
        await apiCall(`notifications?id=${id}`, 'PUT'); 
        await loadNotifications(); 
    } catch (error) {
        console.error('Mark read error:', error);
    }
}

async function clearNotifications() { 
    try {
        await apiCall('notifications?id=all', 'DELETE'); 
        await loadNotifications(); 
        showToast("All notifications cleared", 'success');
    } catch (error) {
        console.error('Clear notifications error:', error);
    }
}

// Settings
async function loadSettings() {
    try {
        const settings = await apiCall('settings');
        currentLowStockThreshold = settings.low_stock_threshold || 10;
        currentCurrency = settings.currency || '$';
        
        const thresholdInput = document.getElementById("thresholdInput");
        const currencySelect = document.getElementById("currencySelect");
        
        if (thresholdInput) thresholdInput.value = currentLowStockThreshold;
        if (currencySelect) currencySelect.value = currentCurrency;
        
        const company = await apiCall('company');
        if (company) {
            const companyName = document.getElementById("companyName");
            const companyTaxId = document.getElementById("companyTaxId");
            const companyAddress = document.getElementById("companyAddress");
            const companyPhone = document.getElementById("companyPhone");
            const companyEmail = document.getElementById("companyEmail");
            
            if (companyName) companyName.value = company.company_name || '';
            if (companyTaxId) companyTaxId.value = company.tax_id || '';
            if (companyAddress) companyAddress.value = company.address || '';
            if (companyPhone) companyPhone.value = company.phone || '';
            if (companyEmail) companyEmail.value = company.email || '';
        }
    } catch (error) {
        console.error('Settings error:', error);
    }
}

async function saveSystemPrefs() {
    const threshold = parseInt(document.getElementById("thresholdInput")?.value);
    const currency = document.getElementById("currencySelect")?.value;
    try {
        await apiCall('settings', 'POST', { low_stock_threshold: threshold, currency: currency });
        currentLowStockThreshold = threshold; 
        currentCurrency = currency;
        showToast("Preferences saved", 'success');
        await refreshAll();
    } catch (error) {
        showToast("Error saving preferences: " + error.message, 'error');
    }
}

async function saveCompanyInfo() {
    const companyData = { 
        company_name: document.getElementById("companyName")?.value, 
        tax_id: document.getElementById("companyTaxId")?.value, 
        address: document.getElementById("companyAddress")?.value, 
        phone: document.getElementById("companyPhone")?.value, 
        email: document.getElementById("companyEmail")?.value 
    };
    try {
        await apiCall('company', 'POST', companyData);
        showToast("Company info saved", 'success');
    } catch (error) {
        showToast("Error saving company info: " + error.message, 'error');
    }
}

// Profile
async function loadProfile() {
    try {
        const profile = await apiCall('profile');
        if (profile) {
            const firstNameInput = document.getElementById("profileFirstName");
            const lastNameInput = document.getElementById("profileLastName");
            const emailInput = document.getElementById("profileEmail");
            const phoneInput = document.getElementById("profilePhone");
            const memberSinceSpan = document.getElementById("profileMemberSince");
            const fullNameSpan = document.getElementById("profileFullName");
            const emailSpan = document.getElementById("profileEmailText");
            const avatarDiv = document.getElementById("profileAvatar");
            const sidebarAvatar = document.getElementById("sidebarAvatar");
            const profileNameSpan = document.getElementById("profileName");
            const profileEmailDisplay = document.getElementById("profileEmailDisplay");
            const profileDeptSpan = document.getElementById("profileDepartment");
            const profileDeptInput = document.getElementById("profileDept");
            
            if (firstNameInput) firstNameInput.value = profile.first_name || '';
            if (lastNameInput) lastNameInput.value = profile.last_name || '';
            if (emailInput) emailInput.value = profile.email || '';
            if (phoneInput) phoneInput.value = profile.phone || '';
            if (memberSinceSpan) memberSinceSpan.innerText = profile.created_at ? new Date(profile.created_at).toLocaleDateString() : 'January 2025';
            
            const fullName = `${profile.first_name || 'Admin'} ${profile.last_name || 'User'}`;
            if (fullNameSpan) fullNameSpan.innerText = fullName;
            if (emailSpan) emailSpan.innerText = profile.email || 'admin@inventrack.com';
            if (profileNameSpan) profileNameSpan.innerText = fullName;
            if (profileEmailDisplay) profileEmailDisplay.innerText = profile.email || 'admin@inventrack.com';
            
            const initials = `${(profile.first_name?.[0] || 'A')}${(profile.last_name?.[0] || 'D')}`;
            if (avatarDiv) avatarDiv.innerText = initials;
            if (sidebarAvatar) sidebarAvatar.innerText = initials;
            
            if (profile.department_id) {
                try {
                    const departments = await apiCall('departments');
                    const userDept = departments.find(d => d.id === profile.department_id);
                    if (userDept && profileDeptSpan) profileDeptSpan.innerText = userDept.name;
                    if (profileDeptInput) profileDeptInput.value = userDept ? userDept.name : 'Not Assigned';
                } catch (e) {
                    console.error('Error loading user department:', e);
                }
            } else {
                if (profileDeptSpan) profileDeptSpan.innerText = 'Not Assigned';
                if (profileDeptInput) profileDeptInput.value = 'Not Assigned';
            }
            
            currentUser = profile;
        }
    } catch (error) {
        console.error('Profile error:', error);
    }
}

async function updateProfile() {
    const profileData = { 
        first_name: document.getElementById("profileFirstName")?.value, 
        last_name: document.getElementById("profileLastName")?.value, 
        email: document.getElementById("profileEmail")?.value, 
        phone: document.getElementById("profilePhone")?.value 
    };
    try {
        await apiCall('profile', 'POST', profileData);
        showToast("Profile updated", 'success');
        await loadProfile();
    } catch (error) {
        showToast("Error updating profile: " + error.message, 'error');
    }
}

async function changePassword() {
    const newPass = document.getElementById("newPass")?.value;
    const confirmPass = document.getElementById("confirmPass")?.value;
    if (newPass && newPass === confirmPass) { 
        try {
            await apiCall('profile', 'POST', { password: newPass }); 
            showToast("Password changed", 'success'); 
            if (document.getElementById("newPass")) document.getElementById("newPass").value = ''; 
            if (document.getElementById("confirmPass")) document.getElementById("confirmPass").value = ''; 
        } catch (error) {
            showToast("Error changing password: " + error.message, 'error');
        }
    } else if (newPass) { 
        showToast("Passwords don't match", 'error'); 
    }
}

// Reports
async function generateReport(type) {
    let data;
    try {
        if (type === 'inventory') { 
            data = await apiCall('reports/inventory'); 
            currentReportData = data.map(p => ({ ID: p.id, Name: p.name, Category: p.category || '-', Quantity: p.quantity, Price: `${currentCurrency}${parseFloat(p.price).toFixed(2)}`, Status: p.status })); 
        } else if (type === 'movement') { 
            data = await apiCall('reports/movements'); 
            currentReportData = data.map(m => ({ Type: m.type === 'in' ? 'Stock In' : 'Stock Out', Product: m.product_name, Quantity: m.quantity, Date: new Date(m.created_at).toLocaleString() })); 
        } else { 
            data = await apiCall('reports/lowstock'); 
            currentReportData = data.map(p => ({ ID: p.id, Name: p.name, Quantity: p.quantity, Status: p.status })); 
        }
        
        const reportOutput = document.getElementById("reportOutput");
        const reportContent = document.getElementById("reportContent");
        
        if (reportOutput) reportOutput.classList.remove("hidden");
        if (reportContent && currentReportData.length > 0) {
            reportContent.innerHTML = `
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>${Object.keys(currentReportData[0]).map(k => `<th class="p-2 text-left">${k}</th>`).join('')}</tr>
                        </thead>
                        <tbody>
                            ${currentReportData.map(r => `<tr class="border-b">${Object.values(r).map(v => `<td class="p-2">${escapeHtml(String(v))}</td>`).join('')}</tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
    } catch (error) {
        showToast("Error generating report: " + error.message, 'error');
    }
}

function downloadCSV() {
    if (!currentReportData.length) return;
    const headers = Object.keys(currentReportData[0]);
    const csv = [headers.join(","), ...currentReportData.map(r => headers.map(h => JSON.stringify(r[h] || '')).join(","))].join("\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
    a.download = "inventory_report.csv";
    a.click();
    showToast("CSV exported", 'success');
}

// Dropdown Populators
async function populateProductDropdowns() {
    try {
        const products = await apiCall('lookup/products');
        const siProduct = document.getElementById("siProduct");
        const soProduct = document.getElementById("soProduct");
        
        if (siProduct) {
            siProduct.innerHTML = '<option value="">-- Choose a product --</option>' + products.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (${p.quantity} in stock)</option>`).join('');
        }
        if (soProduct) {
            soProduct.innerHTML = '<option value="">-- Choose a product --</option>' + products.map(p => `<option value="${p.id}">${escapeHtml(p.name)} (${p.quantity} in stock)</option>`).join('');
        }
    } catch (error) {
        console.error('Error populating product dropdowns:', error);
    }
}

async function populateSupplierDropdowns() {
    try {
        const suppliers = await apiCall('lookup/suppliers');
        const siSupplier = document.getElementById("siSupplier");
        if (siSupplier) {
            siSupplier.innerHTML = '<option value="">-- Select supplier --</option>' + suppliers.map(s => `<option value="${escapeHtml(s.name)}">${escapeHtml(s.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error populating supplier dropdowns:', error);
    }
}

async function loadCategoryFilter() {
    try {
        const categories = await apiCall('lookup/categories');
        const filter = document.getElementById("productCatFilter");
        if (filter) {
            filter.innerHTML = '<option value="">All Categories</option>' + categories.map(c => `<option value="${c.name}">${escapeHtml(c.name)}</option>`).join('');
        }
    } catch (error) {
        console.error('Error loading category filter:', error);
    }
}

// Navigation & UI
function navigate(page, el) {
    document.querySelectorAll(".page").forEach(p => p.classList.remove("active-page"));
    const pageElement = document.getElementById(`page-${page}`);
    if (pageElement) pageElement.classList.add("active-page");
    document.querySelectorAll(".nav-item").forEach(n => n.classList.remove("nav-active"));
    if (el) el.classList.add("nav-active");
    
    const names = { dashboard: "Dashboard", products: "Products", categories: "Categories", suppliers: "Suppliers", stockIn: "Stock In", stockOut: "Stock Out", stockLevels: "Stock Levels", reports: "Reports", notifications: "Notifications", profile: "Profile", settings: "Settings", departments: "Departments", users: "Users" };
    const breadcrumb = document.getElementById("breadcrumbText");
    if (breadcrumb) breadcrumb.innerHTML = names[page] || page;
    
    loadPage(page);
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

async function refreshAll() { 
    const activePage = document.querySelector(".page.active-page")?.id?.replace('page-', ''); 
    if (activePage) await refreshPageData(activePage); 
}

// Page Loading
async function loadPage(page) {
    const container = document.getElementById("pageContainer");
    if (container && window.pageTemplates && window.pageTemplates[page]) {
        container.innerHTML = window.pageTemplates[page];
        await refreshPageData(page);
    }
}

async function refreshPageData(page) {
    switch(page) {
        case 'dashboard': 
            await loadDashboardStats(); 
            break;
        case 'products': 
            await loadProducts(); 
            await loadCategoryFilter(); 
            break;
        case 'categories': 
            await loadCategories(); 
            break;
        case 'suppliers': 
            await loadSuppliers(); 
            break;
        case 'stockIn': 
            await loadStockMovements(); 
            await populateProductDropdowns(); 
            await populateSupplierDropdowns(); 
            const siDate = document.getElementById("siDate");
            if (siDate) {
                const today = new Date().toISOString().split('T')[0];
                siDate.value = today;
            }
            break;
        case 'stockOut': 
            await loadStockMovements(); 
            await populateProductDropdowns(); 
            const soDate = document.getElementById("soDate");
            if (soDate) {
                const today = new Date().toISOString().split('T')[0];
                soDate.value = today;
            }
            break;
        case 'stockLevels': 
            await loadStockLevels(); 
            break;
        case 'notifications': 
            await loadNotifications(); 
            break;
        case 'profile': 
            await loadProfile(); 
            break;
        case 'settings': 
            await loadSettings(); 
            break;
        case 'departments':
            await loadDepartments();
            break;
        case 'users':
            await loadUsers();
            const userSearch = document.getElementById("userSearch");
            if (userSearch && !userSearch.hasListener) {
                userSearch.hasListener = true;
                userSearch.addEventListener('input', () => loadUsers(1));
            }
            break;
    }
}

// Initialize
async function init() {
    const container = document.getElementById("pageContainer");
    if (container) {
        const pages = ['dashboard', 'products', 'categories', 'suppliers', 'stockIn', 'stockOut', 'stockLevels', 'reports', 'notifications', 'profile', 'settings', 'departments', 'users'];
        container.innerHTML = pages.map(page => `<div id="page-${page}" class="page">${window.pageTemplates[page] || '<div class="p-8 text-center">Loading...</div>'}</div>`).join('');
        const dashboardPage = document.getElementById("page-dashboard");
        if (dashboardPage) dashboardPage.classList.add("active-page");
    }
    
    document.querySelectorAll(".nav-item").forEach(link => { 
        link.addEventListener("click", (e) => { 
            e.preventDefault(); 
            navigate(link.getAttribute("data-page"), link); 
        }); 
    });
    
    const supplierSearch = document.getElementById("supplierSearch");
    const productSearch = document.getElementById("productSearch");
    const productCatFilter = document.getElementById("productCatFilter");
    const productStatusFilter = document.getElementById("productStatusFilter");
    
    if (supplierSearch) supplierSearch.addEventListener("input", () => loadSuppliers());
    if (productSearch) productSearch.addEventListener("input", () => { productsPage = 1; loadProducts(); });
    if (productCatFilter) productCatFilter.addEventListener("change", () => { productsPage = 1; loadProducts(); });
    if (productStatusFilter) productStatusFilter.addEventListener("change", () => { productsPage = 1; loadProducts(); });
    
    await loadSettings();
    await loadProfile();
    await loadDashboardStats();
    await loadProducts();
    await loadCategories();
    await loadSuppliers();
    await loadStockLevels();
    await loadStockMovements();
    await loadNotifications();
    await populateProductDropdowns();
    await populateSupplierDropdowns();
    await loadCategoryFilter();
    
    if (localStorage.getItem("darkMode") === "true") document.body.classList.add("dark");
    const darkToggle = document.getElementById("darkModeToggle");
    if (darkToggle) darkToggle.checked = document.body.classList.contains("dark");
}

// Expose functions globally
window.toggleSidebar = toggleSidebar;
window.toggleDarkMode = toggleDarkMode;
window.openProductModal = openProductModal;
window.closeProductModal = closeProductModal;
window.saveProduct = saveProduct;
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.openCategoryModal = openCategoryModal;
window.closeCategoryModal = closeCategoryModal;
window.saveCategory = saveCategory;
window.deleteCategory = deleteCategory;
window.openSupplierModal = openSupplierModal;
window.closeSupplierModal = closeSupplierModal;
window.saveSupplier = saveSupplier;
window.editSupplier = editSupplier;
window.deleteSupplier = deleteSupplier;
window.recordStockIn = recordStockIn;
window.recordStockOut = recordStockOut;
window.generateReport = generateReport;
window.downloadCSV = downloadCSV;
window.clearNotifications = clearNotifications;
window.saveSystemPrefs = saveSystemPrefs;
window.saveCompanyInfo = saveCompanyInfo;
window.updateProfile = updateProfile;
window.changePassword = changePassword;
window.changeProductPage = changeProductPage;
window.markNotificationRead = markNotificationRead;

// Expose Department & User functions globally
window.loadDepartments = loadDepartments;
window.loadUsers = loadUsers;
window.openDepartmentModal = openDepartmentModal;
window.closeDepartmentModal = closeDepartmentModal;
window.saveDepartment = saveDepartment;
window.editDepartment = editDepartment;
window.deleteDepartment = deleteDepartment;
window.openUserModal = openUserModal;
window.closeUserModal = closeUserModal;
window.saveUser = saveUser;
window.editUser = editUser;
window.deleteUser = deleteUser;

// Expose additional helper functions
window.showToast = showToast;
window.escapeHtml = escapeHtml;