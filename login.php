<?php
// Start session first (needed to check if already logged in)
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: customer_dashboard.php');
    }
    exit;
}

// Include database and validation
require_once 'database/config.php';
require_once 'database/validation.php';

$errors = [];

// Handle login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if ($err = validateRequired($username, 'Username')) $errors[] = $err;
    if ($err = validateRequired($password, 'Password')) $errors[] = $err;

    if (empty($errors)) {
        try {
            $pdo = getConnection();
            
            // Find user by username
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM user WHERE username = :username");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login success - store session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: customer_dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | YOCOR Express</title>
    <link rel="stylesheet" href="https://cdn.tailwindcss.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            
            <!-- Logo -->
            <div class="text-center mb-8">
                <a href="index.php" class="text-2xl font-black text-brandNavy">
                    YOCOR <span class="text-brandOrange">Express</span>
                </a>
                <p class="text-slate-500 text-sm mt-1">Sign in to your account</p>
            </div>

            <!-- Success message from registration -->
            <?php if (isset($_GET['registered'])): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    Registration successful! Please log in.
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <button type="submit" 
                        class="w-full bg-brandNavy hover:bg-slate-900 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg brand-glow-navy">
                    <i class="fa-solid fa-right-to-bracket mr-2"></i> Sign In
                </button>
            </form>

            <!-- Register Link -->
            <p class="text-center text-sm text-slate-600 mt-6">
                Don't have an account? 
                <a href="register.php" class="text-brandOrange font-bold hover:underline">Register</a>
            </p>
        </div>
    </div>
</body>
</html>