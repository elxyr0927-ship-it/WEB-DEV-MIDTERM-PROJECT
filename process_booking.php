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
$weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);

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
        $stmt = $pdo->prepare("SELECT id, name, capacity FROM services WHERE id = :id FOR UPDATE");
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
        
        // Insert booking
        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, service_id, pickup_address, delivery_address, weight, status) 
            VALUES (:user_id, :service_id, :pickup, :delivery, :weight, 'pending')
        ");
        $stmt->execute([
            'user_id' => $_SESSION['user_id'],
            'service_id' => $service_id,
            'pickup' => $pickup,
            'delivery' => $delivery,
            'weight' => $weight
        ]);
        
        // Get the booking ID (optional, for reference)
        $booking_id = $pdo->lastInsertId();
        
        // Decrease capacity by 1
        $stmt = $pdo->prepare("UPDATE services SET capacity = capacity - 1 WHERE id = :id");
        $stmt->execute(['id' => $service_id]);
        
        // Commit transaction
        $pdo->commit();
        
        // Success! Store success message in session and redirect
        $_SESSION['booking_success'] = true;
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