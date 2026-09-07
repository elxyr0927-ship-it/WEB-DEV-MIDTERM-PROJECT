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
require_once 'app/database/config.php';
require_once 'app/database/validation.php';

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
            
            // Find user by username OR email
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM user WHERE username = :ident OR email = :ident");
            $stmt->execute(['ident' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verify password
            if ($user && password_verify($password, $user['password_hash'])) {
                // Login success - store session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                // Check for safe redirect destination (e.g. booking.php)
                $redirect = $_GET['redirect'] ?? ($_POST['redirect'] ?? '');
                if (!empty($redirect) && strpos($redirect, '.php') !== false && !strpos($redirect, '://')) {
                    header('Location: ' . $redirect);
                    exit;
                }

                // Default role-based redirect
                if ($user['role'] === 'admin') {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: customer_dashboard.php');
                }
                exit;
            } else {
                $errors[] = 'Invalid username/email or password.';
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
    <title>Sign In | YOCOR Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              brandNavy: '#1D3563',
              brandOrange: '#F37B23',
              brandLight: '#F8FAFC',
              brandDark: '#0B132B'
            },
            fontFamily: {
              sans: ['Roboto', 'sans-serif'],
              heading: ['Montserrat', 'sans-serif'],
            }
          }
        }
      }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gradient-to-br from-slate-100 via-slate-50 to-orange-50/40 min-h-screen flex flex-col justify-center items-center py-12 px-4 sm:px-6">

    <!-- Top Back to Home -->
    <div class="w-full max-w-md mb-6 flex items-center justify-between">
        <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-brandNavy transition-colors group">
            <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
            <span>Back to Home</span>
        </a>
        <span class="text-xs text-slate-400 font-medium">Secure Portal</span>
    </div>

    <!-- Main Card -->
    <div class="max-w-md w-full">
        <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/80 border border-slate-200/90 p-8 sm:p-10 relative overflow-hidden">
            <!-- Accent gradient line -->
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-brandNavy via-brandOrange to-orange-400"></div>
            
            <!-- Logo Header -->
            <div class="text-center mb-8">
                <a href="index.php" class="inline-flex items-center justify-center focus:outline-none mb-3 hover:opacity-95 transition-opacity">
                    <img src="public/Asset%205.svg" alt="YOCOR Express" class="h-10 sm:h-12 w-auto object-contain">
                </a>
                <h1 class="text-xl font-bold text-slate-800 font-heading">Sign In to Your Account</h1>
                <p class="text-slate-500 text-xs mt-1">Access your shipments, bookings, and tracking history</p>
            </div>

            <!-- Success Alert -->
            <?php if (isset($_GET['registered'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl mb-6 text-xs flex items-center gap-3">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
                    <div>
                        <p class="font-bold">Account created successfully!</p>
                        <p class="text-emerald-700">Please enter your credentials to log in.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-2xl mb-6 text-xs">
                    <div class="flex items-center gap-2 font-bold mb-1">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span>Unable to sign in</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" action="login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="space-y-4">
                <?php if (!empty($_GET['redirect'])): ?>
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_GET['redirect']) ?>">
                <?php endif; ?>
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1.5">
                        Username or Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-user text-sm"></i>
                        </div>
                        <input type="text" name="username" required autocomplete="username"
                               placeholder="Enter username or email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700">Password</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-lock text-sm"></i>
                        </div>
                        <input type="password" id="login-password" name="password" required autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full pl-10 pr-11 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                        <button type="button" onclick="togglePasswordVisibility('login-password', this)" 
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                aria-label="Toggle password visibility">
                            <i class="fa-regular fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full bg-brandNavy hover:bg-slate-900 text-white font-bold py-3.5 px-4 rounded-xl transition-all shadow-lg hover:shadow-xl brand-glow-navy flex items-center justify-center gap-2 text-sm transform hover:-translate-y-0.5">
                        <span>Sign In</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </div>
            </form>

            <!-- Register Footer -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    Don't have an account yet? 
                    <a href="register.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>" class="text-brandOrange font-bold hover:underline transition-colors ml-1">
                        Create an account
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Quick JavaScript for Show/Hide Password -->
    <script>
      function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      }
    </script>
</body>
</html>