<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $result = authenticateUser($pdo, $username, $password);
        
        if ($result['success']) {
            header('Location: index.php');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | InvenTrack Pro</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #15803d 0%, #166534 100%); min-height: 100vh; }
        .login-card { animation: fadeInUp 0.5s ease-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="flex items-center justify-center p-5">
    <div class="w-full max-w-md login-card">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white/20 rounded-2xl flex items-center justify-center mx-auto backdrop-blur-sm">
                <i class="fas fa-chart-line text-white text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mt-4">InvenTrack Pro</h1>
            <p class="text-white/80 text-sm mt-1">Enterprise Inventory Management System</p>
        </div>
        
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Welcome Back</h2>
                <p class="text-gray-500 text-sm mt-1">Please sign in to your account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-5">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Username</label>
                    <div class="relative">
                        <i class="fas fa-user absolute left-3 top-3 text-gray-400"></i>
                        <input type="text" name="username" required class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                    </div>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-3 top-3 text-gray-400"></i>
                        <input type="password" name="password" required class="w-full pl-10 pr-3 py-3 border border-gray-300 rounded-xl focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-200">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white py-3 rounded-xl font-semibold transition transform hover:scale-[1.02]">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </form>
            
            <div class="mt-6 pt-4 border-t text-center">
                <p class="text-sm text-gray-500">Demo Credentials:</p>
                <p class="text-xs text-gray-400 font-mono">Username: admin | Password: admin123</p>
            </div>
        </div>
    </div>
</body>
</html>