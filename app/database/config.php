<?php


function getConnection(): PDO
{
    $host = '127.0.0.1';
    $port = '3307';
    $db   = 'yocor_express';
    $user = 'root';
    $pass = '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user,
            $pass
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
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

