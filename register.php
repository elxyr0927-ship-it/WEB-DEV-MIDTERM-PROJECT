<?php
 
require_once 'app/database/config.php';
require_once 'app/database/validation.php';

 $errors = [];
 $success = false;

 if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $password = ($_POST['password'] ?? '');
    $confirm = $_POST['confirm_password'] ?? '';
    
    if ($err = validateRequired($username, 'Username')) $errors[] = $err;
    if ($err = validateEmailFormat($email)) $errors[] = $err;
    if ($err = validateIntRange($age, 'Age', 1, 120)) $errors[] = $err;
    if ($err = validateRequired($password, 'Password')) $errors[] = $err;

    if (strlen($password) < 8 ) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Password do not match.';

    if (empty($errors)) {
        try {
            $pdo = getConnection();

            //check username
            $stmt = $pdo->prepare('SELECT id FROM user WHERE username = :username');
            $stmt = $pdo->execute(['username' => $username]);

            if ($stmt->fetch()){
             $errors [] = 'Username already taken. Please choose another.';
            }

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }

    }

    if (empty($errors)){
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $pdo = getConnection();
            $stmt = $pdo->prepare("
            INSERT INTO user(username,email,age,password_hash,role)
            VALUES(:username, :email, :age, :hash, 'customer') ");

            $stmt = $pdo->execute([
                'username' => $username,
                'email' => $email,
                'age' => $age,
                'hash' => $hashedPassword
            ]);

            header('Location: login.php?registered=1');
            exit;

        } catch (PDOException $e){
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
    <title>Register | YOCOR Express</title>
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
                }
            }
            }
        }
</script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-4">
        <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
            
            <!-- Logo / Brand -->
            <div class="text-center mb-8">
                <a href="index.php" class="text-2xl font-black text-brandNavy">
                    YOCOR <span class="text-brandOrange">Express</span>
                </a>
                <p class="text-slate-500 text-sm mt-1">Create your customer account</p>
            </div>

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

            <!-- Registration Form -->
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Username</label>
                    <input type="text" name="username" required 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                    <input type="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Age</label>
                    <input type="number" name="age" required min="1" max="120"
                           value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Password (min 8 characters)</label>
                    <input type="password" name="password" required
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">Confirm Password</label>
                    <input type="password" name="confirm_password" required
                           class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>

                <button type="submit" 
                        class="w-full bg-brandOrange hover:bg-orange-600 text-white font-bold py-3.5 rounded-xl transition-all shadow-lg brand-glow-orange">
                    Create Account
                </button>
            </form>

            <!-- Login Link -->
            <p class="text-center text-sm text-slate-600 mt-6">
                Already have an account? 
                <a href="login.php" class="text-brandOrange font-bold hover:underline">Login</a>
            </p>
        </div>
    </div>
</body>
</html>