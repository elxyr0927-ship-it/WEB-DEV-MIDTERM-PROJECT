<?php

require_once __DIR__ . '/helper.php';



function getConnection(): PDO
{
    // Load .env from project root
    static $envLoaded = false;
    if (!$envLoaded) {
        loadEnv(dirname(__DIR__, 2) . '/.env');
        $envLoaded = true;
    }
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3307';
    $db   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'yocor_express';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'root';
    $pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Database connection error. Please contact the administrator.");
    }
}

/**
 * Generate a unique tracking code formatted like YR-XXXXXX
 * Simple and beginner-friendly for college midterm defense
 */
function generateTrackingCode(PDO $pdo): string
{
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $maxAttempts = 50;

    for ($i = 0; $i < $maxAttempts; $i++) {
        // Generate random 6-character string
        $randomPart = '';
        for ($j = 0; $j < 6; $j++) {
            $randomPart .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $code = 'YR-' . $randomPart;

        // Check if code is already used in bookings table
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE tracking_code = :code");
        $stmt->execute(['code' => $code]);
        if ($stmt->rowCount() === 0) {
            return $code;
        }
    }

    // Fallback timestamp-based code in rare case of collisions
    return 'YR-' . strtoupper(substr(uniqid(), -6));
}

/**
 * Fetch dynamic regional distance rates configured by admin
 */
function getRegionalRates(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT route_key, fee FROM regional_rates");
    $rates = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rates[$row['route_key']] = (float)$row['fee'];
    }
    // Safe fallbacks if not populated
    return [
        'intra_island' => $rates['intra_island'] ?? 0.00,
        'inter_island' => $rates['inter_island'] ?? 60.00,
        'cross_island' => $rates['cross_island'] ?? 120.00
    ];
}

/**
 * Determine route fee key based on origin and destination regions
 */
function getRegionalRouteKey(string $origin, string $dest): string
{
    $o = strtolower(trim($origin));
    $d = strtolower(trim($dest));

    if ($o === $d) {
        return 'intra_island';
    }
    if (($o === 'luzon' && $d === 'mindanao') || ($o === 'mindanao' && $d === 'luzon')) {
        return 'cross_island';
    }
    return 'inter_island';
}

/**
 * Log admin operations (status changes, price updates, capacity adjustments, deletions)
 */
function logAdminAction(PDO $pdo, int $adminId, string $action, ?string $details = null): bool
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("
            INSERT INTO admin_logs (admin_id, action, details, ip_address)
            VALUES (:admin_id, :action, :details, :ip_address)
        ");
        return $stmt->execute([
            'admin_id'   => $adminId,
            'action'     => $action,
            'details'    => $details,
            'ip_address' => $ip
        ]);
    } catch (PDOException $e) {
        // Silently log or handle to not break core admin operation
        error_log("Failed to insert admin log: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch all payment methods (active only by default, or all for admin configuration)
 */
function getPaymentMethods(PDO $pdo, bool $activeOnly = true): array
{
    $sql = "SELECT * FROM payment_methods";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY id ASC";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch a single payment method by code
 */
function getPaymentMethod(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE method_code = :code");
    $stmt->execute(['code' => $code]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}

/**
 * Update payment method credentials and instructions (admin only)
 */
function updatePaymentMethod(PDO $pdo, string $code, array $data): bool
{
    $allowed = ['account_name', 'account_number', 'instructions', 'is_active'];
    $fields = [];
    $params = ['code' => $code];

    foreach ($data as $key => $val) {
        if (in_array($key, $allowed, true)) {
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $val;
        }
    }

    if (empty($fields)) {
        return false;
    }

    $sql = "UPDATE payment_methods SET " . implode(', ', $fields) . " WHERE method_code = :code";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Count unread customer support inquiries
 */
function getUnreadMessagesCount(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0 AND (deleted_at IS NULL)");
    return (int)$stmt->fetchColumn();
}
