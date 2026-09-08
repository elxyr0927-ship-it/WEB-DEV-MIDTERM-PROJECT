<?php
 
require_once 'app/database/config.php';
require_once 'app/database/validation.php';

 $errors = [];
 $success = false;

 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid or expired security token (CSRF). Please refresh and try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = ($_POST['password'] ?? '');
    $confirm = $_POST['confirm_password'] ?? '';
    
    if ($err = validateRequired($username, 'Username')) $errors[] = $err;
    if ($err = validateEmailFormat($email)) $errors[] = $err;
    if (!empty($age)) {
        if ($err = validateIntRange($age, 'Age', 1, 120)) $errors[] = $err;
    }
    if (!empty($phone) && ($err = validatePhoneNumber($phone, 'Phone number'))) {
        $errors[] = $err;
    }
    if ($err = validateRequired($password, 'Password')) $errors[] = $err;

    if ($err = validateStringLength($username, 'Username', 50, 3)) $errors[] = $err;
    if ($err = validateStringLength($email, 'Email address', 100)) $errors[] = $err;
    if ($err = validateStringLength($password, 'Password', 128, 8)) $errors[] = $err;
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        try {
            $pdo = getConnection();

            // Check if username or email already taken
            $stmt = $pdo->prepare('SELECT id, username, email FROM user WHERE username = :username OR email = :email LIMIT 1');
            $stmt->execute(['username' => $username, 'email' => $email]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if (strtolower($existing['username']) === strtolower($username)) {
                    $errors[] = 'Username already taken. Please choose another.';
                }
                if (strtolower($existing['email']) === strtolower($email)) {
                    $errors[] = 'Email address is already registered. Please sign in or use another.';
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
                INSERT INTO user (username, email, age, phone, password_hash, role)
                VALUES (:username, :email, :age, :phone, :hash, 'customer')
            ");

            $stmt->execute([
                'username' => $username,
                'email'    => $email,
                'age'      => !empty($age) ? (int)$age : null,
                'phone'    => !empty($phone) ? $phone : null,
                'hash'     => $hashedPassword
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Auto-login new user directly
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'customer';
            $_SESSION['welcome_new_user'] = true;

            header('Location: customer_dashboard.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account | YOCOR Express</title>
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
<body class="bg-gradient-to-br from-slate-100 via-slate-50 to-orange-50/40 min-h-screen flex flex-col justify-center items-center py-10 px-4 sm:px-6">

    <!-- Top Back to Home -->
    <div class="w-full max-w-lg mb-6 flex items-center justify-between">
        <a href="index.php" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-brandNavy transition-colors group">
            <i class="fa-solid fa-arrow-left transition-transform group-hover:-translate-x-1"></i>
            <span>Back to Home</span>
        </a>
        <span class="text-xs text-slate-400 font-medium">Customer Registration</span>
    </div>

    <!-- Main Card -->
    <div class="max-w-lg w-full">
        <div class="bg-white rounded-3xl shadow-2xl shadow-slate-200/80 border border-slate-200/90 p-8 sm:p-10 relative overflow-hidden">
            <!-- Accent top bar -->
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-brandOrange via-amber-500 to-brandNavy"></div>

            <!-- Logo / Brand -->
            <div class="text-center mb-8">
                <a href="index.php" class="inline-flex items-center justify-center focus:outline-none mb-3 hover:opacity-95 transition-opacity">
                    <img src="public/Asset%205.svg" alt="YOCOR Express" class="h-10 sm:h-12 w-auto object-contain">
                </a>
                <h1 class="text-xl font-bold text-slate-800 font-heading">Create Customer Account</h1>
                <p class="text-slate-500 text-xs mt-1">Start shipping packages and managing global deliveries</p>
            </div>

            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3.5 rounded-2xl mb-6 text-xs">
                    <div class="flex items-center gap-2 font-bold mb-1">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span>Please fix the following:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Registration Form -->
            <form method="POST" action="" class="space-y-4">
                <?= csrfField() ?>
                <div class="grid sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Username <span class="text-brandOrange">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-user text-sm"></i>
                            </div>
                            <input type="text" name="username" required autocomplete="username"
                                   placeholder="johndoe"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                   class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Age <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <input type="number" name="age" min="1" max="120"
                               placeholder="25"
                               value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"
                               class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 text-center font-bold">
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Email Address <span class="text-brandOrange">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                            <input type="email" name="email" required autocomplete="email"
                                   placeholder="john@example.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Mobile Phone <span class="text-slate-400 font-normal">(Optional)</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-phone text-sm"></i>
                            </div>
                            <input type="tel" name="phone" autocomplete="tel"
                                   placeholder="09171234567"
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                                   class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                        </div>
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Password <span class="text-slate-400 font-normal">(min 8)</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-lock text-sm"></i>
                            </div>
                            <input type="password" id="reg-password" name="password" required autocomplete="new-password"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                            <button type="button" onclick="togglePasswordVisibility('reg-password', this)" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                <i class="fa-regular fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-lock-check text-sm"></i>
                            </div>
                            <input type="password" id="reg-confirm" name="confirm_password" required autocomplete="new-password"
                                   placeholder="••••••••"
                                   class="w-full pl-10 pr-10 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange/40 focus:border-brandOrange outline-none transition-all text-sm text-slate-800 placeholder-slate-400">
                            <button type="button" onclick="togglePasswordVisibility('reg-confirm', this)" 
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none"
                                    aria-label="Toggle password visibility">
                                <i class="fa-regular fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" 
                            class="w-full bg-brandOrange hover:bg-orange-600 text-white font-bold py-3.5 px-4 rounded-xl transition-all shadow-lg hover:shadow-xl brand-glow-orange flex items-center justify-center gap-2 text-sm transform hover:-translate-y-0.5">
                        <i class="fa-solid fa-user-plus text-xs"></i>
                        <span>Create Account</span>
                    </button>
                </div>
            </form>

            <!-- Login Link -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center">
                <p class="text-xs text-slate-600">
                    Already have an account? 
                    <a href="login.php" class="text-brandOrange font-bold hover:underline transition-colors ml-1">
                        Sign In
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