<?php
// Start session first
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If admin tries to book, redirect
if ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// Include database and validation
require_once __DIR__ . '/app/database/config.php';
require_once __DIR__ . '/app/database/validation.php';

// Verify CSRF token
if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['booking_errors'] = ['Invalid or expired security token (CSRF). Please refresh and try again.'];
    header('Location: booking.php');
    exit;
}

// Get and sanitize inputs
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$pickup = trim($_POST['pickup_address'] ?? '');
$delivery = trim($_POST['delivery_address'] ?? '');
$pickup_region = trim($_POST['pickup_region'] ?? 'Luzon');
$delivery_region = trim($_POST['delivery_region'] ?? 'Luzon');
$weight_input = $_POST['weight'] ?? '';

// Valid Philippine island regions
$valid_regions = ['Luzon', 'Visayas', 'Mindanao'];
if ($err = validateInArray($pickup_region, $valid_regions, 'pickup region')) {
    $pickup_region = 'Luzon';
}
if ($err = validateInArray($delivery_region, $valid_regions, 'delivery region')) {
    $delivery_region = 'Luzon';
}

// === VALIDATE ALL INPUTS UNIFORMLY VIA VALIDATION.PHP ===
if ($err = validateRequired($pickup, 'Pickup address')) $errors[] = $err;
if ($err = validateRequired($delivery, 'Delivery address')) $errors[] = $err;
if ($err = validateStringLength($pickup, 'Pickup address', 255)) $errors[] = $err;
if ($err = validateStringLength($delivery, 'Delivery address', 255)) $errors[] = $err;

if ($err = validateIntRange((string)$service_id, 'Selected service', 1, 100000)) {
    $errors[] = 'Please select a valid service.';
}

if ($err = validatePositiveNumber($weight_input, 'Package weight', 9999.99)) {
    $errors[] = $err;
} else {
    $weight = (float)$weight_input;
}

// === PROCESS IF NO ERRORS ===
if (empty($errors)) {
    $pdo = getConnection();
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Lock the service row to prevent race conditions and ensure service is active
        $stmt = $pdo->prepare("SELECT id, name, capacity, base_price, price_per_kg, is_active FROM services WHERE id = :id AND is_active = 1 FOR UPDATE");
        $stmt->execute(['id' => $service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if service exists and is active
        if (!$service) {
            throw new Exception('Selected service is currently unavailable or inactive.');
        }
        
        // Check if capacity is available
        if ($service['capacity'] <= 0) {
            throw new Exception('Sorry, this service is currently fully booked. Please try another service.');
        }
        
        // Fetch dynamic regional distance rates from database
        $regionalRates = getRegionalRates($pdo);
        $routeKey = getRegionalRouteKey($pickup_region, $delivery_region);
        $distanceFee = (float)($regionalRates[$routeKey] ?? 0.00);

        // Securely compute total cost server-side (prevent client-side parameter tampering)
        $base_price = (float)($service['base_price'] ?? 100.00);
        $price_per_kg = (float)($service['price_per_kg'] ?? 40.00);
        $total_cost = $base_price + (max(0, $weight - 1) * $price_per_kg) + $distanceFee;

        // Generate unique tracking code (e.g. YR-A7F3B9)
        $tracking_code = generateTrackingCode($pdo);

        // Insert booking with total_cost, pickup_region, delivery_region, and payment_status='unpaid'
        $stmt = $pdo->prepare("
            INSERT INTO bookings (tracking_code, user_id, service_id, pickup_region, delivery_region, pickup_address, delivery_address, weight, status, payment_status, total_cost) 
            VALUES (:tracking_code, :user_id, :service_id, :pickup_region, :delivery_region, :pickup, :delivery, :weight, 'pending', 'unpaid', :total_cost)
        ");
        $stmt->execute([
            'tracking_code' => $tracking_code,
            'user_id' => $_SESSION['user_id'],
            'service_id' => $service_id,
            'pickup_region' => $pickup_region,
            'delivery_region' => $delivery_region,
            'pickup' => $pickup,
            'delivery' => $delivery,
            'weight' => $weight,
            'total_cost' => $total_cost
        ]);
        
        // Get the booking ID
        $booking_id = $pdo->lastInsertId();
        
        // Decrease capacity by 1
        $stmt = $pdo->prepare("UPDATE services SET capacity = capacity - 1 WHERE id = :id");
        $stmt->execute(['id' => $service_id]);

        // Insert initial status history record
        $histStmt = $pdo->prepare("
            INSERT INTO status_history (booking_id, old_status, new_status, changed_by, notes) 
            VALUES (:b_id, NULL, 'pending', :user_id, 'Booking created by customer')
        ");
        $histStmt->execute([
            'b_id' => $booking_id,
            'user_id' => $_SESSION['user_id']
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        // Success! Redirect directly to the payment page
        $_SESSION['booking_success'] = true;
        $_SESSION['last_tracking_code'] = $tracking_code;
        header('Location: payment.php?id=' . $booking_id);
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Booking error: " . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}

// If we have errors, store them in session and redirect back
if (!empty($errors)) {
    $_SESSION['booking_errors'] = $errors;
    header('Location: booking.php');
    exit;
}
?>