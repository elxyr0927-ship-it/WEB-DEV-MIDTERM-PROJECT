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

$errors = [];

// Get and validate inputs
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$pickup = trim($_POST['pickup_address'] ?? '');
$delivery = trim($_POST['delivery_address'] ?? '');
$pickup_region = trim($_POST['pickup_region'] ?? 'Luzon');
$delivery_region = trim($_POST['delivery_region'] ?? 'Luzon');
$weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);

// Valid Philippine island regions
$valid_regions = ['Luzon', 'Visayas', 'Mindanao'];
if (!in_array($pickup_region, $valid_regions)) $pickup_region = 'Luzon';
if (!in_array($delivery_region, $valid_regions)) $delivery_region = 'Luzon';

// === VALIDATE ALL INPUTS ===
// Required fields (using instructor's function)
if ($err = validateRequired($pickup, 'Pickup address')) $errors[] = $err;
if ($err = validateRequired($delivery, 'Delivery address')) $errors[] = $err;

// Service ID must be valid
if (!$service_id || $service_id <= 0) {
    $errors[] = 'Please select a valid service.';
}

// Weight must be positive
if ($weight === false || $weight <= 0) {
    $errors[] = 'Weight must be a positive number.';
}

// Limit string lengths (never trust client side!)
if (strlen($pickup) > 255) $errors[] = 'Pickup address is too long (max 255 characters).';
if (strlen($delivery) > 255) $errors[] = 'Delivery address is too long (max 255 characters).';
if ($weight > 9999.99) $errors[] = 'Weight is too large (max 9999.99 kg).';

// === PROCESS IF NO ERRORS ===
if (empty($errors)) {
    $pdo = getConnection();
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Lock the service row to prevent race conditions
        // FOR UPDATE locks the row so other transactions wait
        $stmt = $pdo->prepare("SELECT id, name, capacity, base_price, price_per_kg FROM services WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $service_id]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check if service exists
        if (!$service) {
            throw new Exception('Service not found.');
        }
        
        // Check if capacity is available
        if ($service['capacity'] <= 0) {
            throw new Exception('Sorry, this service is currently fully booked. Please try another service.');
        }
        
        // Fetch dynamic regional distance rates from database
        $regionalRates = getRegionalRates($pdo);
        $routeKey = getRegionalRouteKey($pickup_region, $delivery_region);
        $distanceFee = (float)($regionalRates[$routeKey] ?? 0.00);

        // Fetch service pricing to compute or verify total cost
        $base_price = (float)($service['base_price'] ?? 100.00);
        $price_per_kg = (float)($service['price_per_kg'] ?? 40.00);
        $calculated_cost = $base_price + (max(0, $weight - 1) * $price_per_kg) + $distanceFee;

        // Retrieve submitted total cost, fallback to calculated cost
        $submitted_cost = filter_input(INPUT_POST, 'total_cost', FILTER_VALIDATE_FLOAT);
        $total_cost = ($submitted_cost !== false && $submitted_cost > 0) ? $submitted_cost : $calculated_cost;

        // Generate unique tracking code (e.g. YR-A7F3B9)
        $tracking_code = generateTrackingCode($pdo);

        // Insert booking with total_cost, pickup_region, and delivery_region
        $stmt = $pdo->prepare("
            INSERT INTO bookings (tracking_code, user_id, service_id, pickup_region, delivery_region, pickup_address, delivery_address, weight, status, total_cost) 
            VALUES (:tracking_code, :user_id, :service_id, :pickup_region, :delivery_region, :pickup, :delivery, :weight, 'pending', :total_cost)
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
        
        // Get the booking ID (optional, for reference)
        $booking_id = $pdo->lastInsertId();
        
        // Decrease capacity by 1
        $stmt = $pdo->prepare("UPDATE services SET capacity = capacity - 1 WHERE id = :id");
        $stmt->execute(['id' => $service_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Success! Store success message and tracking code in session and redirect
        $_SESSION['booking_success'] = true;
        $_SESSION['last_tracking_code'] = $tracking_code;
        header('Location: booking.php');
        exit;
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
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