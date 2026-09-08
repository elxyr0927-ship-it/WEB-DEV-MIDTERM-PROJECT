<?php
// Start session and require login + admin role
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();

// CSRF verification for all POST actions in Admin Dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired security token (CSRF). Please refresh and try again.';
    }
}

// Handle manual Mark as Paid (Admin action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid']) && empty($error)) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    if ($booking_id) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid', paid_at = NOW() WHERE id = :id");
            $stmt->execute(['id' => $booking_id]);

            $hist = $pdo->prepare("
                INSERT INTO status_history (booking_id, old_status, new_status, changed_by, notes) 
                VALUES (:b_id, 'pending', 'pending', :uid, 'Marked as paid manually by admin')
            ");
            $hist->execute(['b_id' => $booking_id, 'uid' => $_SESSION['user_id']]);

            $pdo->commit();
            $updateSuccess = "Booking #{$booking_id} successfully marked as PAID.";
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'MARK_PAID', "Manually marked Booking #{$booking_id} as paid.");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to mark as paid: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking ID.';
    }
}

// Handle update checkpoint & tracker notes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_tracker']) && empty($error)) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $checkpoint = trim($_POST['checkpoint'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $chkErr = validateStringLength($checkpoint, 'Current Checkpoint', 150);
    $noteErr = validateStringLength($notes, 'Tracker Notes', 500);

    if ($chkErr || $noteErr) {
        $error = $chkErr ?: $noteErr;
    } elseif ($booking_id) {
        try {
            $checkStmt = $pdo->prepare("SELECT status FROM bookings WHERE id = :id");
            $checkStmt->execute(['id' => $booking_id]);
            $currStatus = $checkStmt->fetchColumn();

            if ($currStatus === 'delivered') {
                $error = "Cannot modify tracker fields: This shipment has already been delivered and is locked.";
            } else {
                $pdo->beginTransaction();
                $uStmt = $pdo->prepare("
                    UPDATE bookings 
                    SET current_checkpoint = :cp, tracker_notes = :notes 
                    WHERE id = :id
                ");
                $uStmt->execute([
                    'cp' => !empty($checkpoint) ? $checkpoint : null,
                    'notes' => !empty($notes) ? $notes : null,
                    'id' => $booking_id
                ]);

                // Record checkpoint update in status_history
                $hStmt = $pdo->prepare("
                    INSERT INTO status_history (booking_id, old_status, new_status, changed_by, checkpoint, notes) 
                    VALUES (:b_id, :status, :status, :uid, :cp, :notes)
                ");
                $hStmt->execute([
                    'b_id' => $booking_id,
                    'status' => $currStatus,
                    'uid' => $_SESSION['user_id'],
                    'cp' => $checkpoint,
                    'notes' => $notes
                ]);

                $pdo->commit();
                $updateSuccess = "Tracking checkpoint & notes updated for Booking #{$booking_id}!";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_TRACKER', "Updated checkpoint for Booking #{$booking_id} to '{$checkpoint}'");
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to update tracker fields: ' . $e->getMessage();
        }
    }
}

// Handle status update with strict business validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && empty($error)) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed_statuses = ['pending', 'dispatched', 'delivered', 'cancelled'];
    
    $statusErr = validateInArray($new_status, $allowed_statuses, 'status');
    if ($booking_id && !$statusErr) {
        try {
            // Check current booking state
            $checkStmt = $pdo->prepare("SELECT status, payment_status, tracking_code FROM bookings WHERE id = :id");
            $checkStmt->execute(['id' => $booking_id]);
            $currentBooking = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentBooking) {
                $error = 'Booking not found.';
            } elseif ($currentBooking['status'] === 'delivered') {
                $error = 'Security rule: Delivered bookings are locked and cannot have their status modified.';
            } elseif (in_array($new_status, ['dispatched', 'delivered']) && ($currentBooking['payment_status'] ?? 'unpaid') !== 'paid') {
                $error = "Security rule: Cannot change status to '" . ucfirst($new_status) . "' because this booking is UNPAID. Payment must be confirmed or marked as paid first.";
            } elseif ($currentBooking['status'] !== 'pending' && $new_status === 'pending') {
                $error = 'Security rule: One-way status progression violation. Shipments in transit cannot be reverted to Pending.';
            } elseif ($new_status === $currentBooking['status']) {
                $error = "Booking #{$booking_id} is already set to '" . ucfirst($new_status) . "'. No changes made.";
            } else {
                // Execute update and status_history record atomically
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE bookings SET status = :status WHERE id = :id");
                $stmt->execute(['status' => $new_status, 'id' => $booking_id]);

                $hStmt = $pdo->prepare("
                    INSERT INTO status_history (booking_id, old_status, new_status, changed_by, notes) 
                    VALUES (:b_id, :old_status, :new_status, :uid, :notes)
                ");
                $hStmt->execute([
                    'b_id' => $booking_id,
                    'old_status' => $currentBooking['status'],
                    'new_status' => $new_status,
                    'uid' => $_SESSION['user_id'],
                    'notes' => "Status changed from {$currentBooking['status']} to {$new_status} by admin"
                ]);

                $pdo->commit();
                $updateSuccess = "Booking #{$booking_id} status updated to " . ucfirst($new_status) . "!";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_STATUS', "Changed Booking #{$booking_id} status from '{$currentBooking['status']}' to '{$new_status}'");
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Status update error: " . $e->getMessage());
            $error = 'Failed to update booking status. Please try again.';
        }
    } else {
        $error = $statusErr ?? 'Invalid booking ID or status selected.';
    }
}

// Handle deleting delivered booking
$deleteSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking']) && empty($error)) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    
    if ($booking_id) {
        try {
            // Verify booking is indeed 'delivered' before allowing deletion
            $checkStmt = $pdo->prepare("SELECT id, tracking_code, status FROM bookings WHERE id = :id");
            $checkStmt->execute(['id' => $booking_id]);
            $targetBooking = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($targetBooking && $targetBooking['status'] === 'delivered') {
                $delStmt = $pdo->prepare("DELETE FROM bookings WHERE id = :id AND status = 'delivered'");
                $delStmt->execute(['id' => $booking_id]);
                $code = $targetBooking['tracking_code'] ?? ('#' . $booking_id);
                $deleteSuccess = "Delivered parcel [{$code}] has been permanently archived/deleted.";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'ARCHIVE_PARCEL', "Permanently deleted delivered parcel {$code} (Booking #{$booking_id})");
            } else {
                $error = "Only parcels with 'Delivered' status can be deleted.";
            }
        } catch (PDOException $e) {
            $error = 'Failed to delete parcel: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking ID for deletion.';
    }
}

// Handle capacity update
$capacitySuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_capacity']) && empty($error)) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $new_capacity_raw = $_POST['new_capacity'] ?? '';
    $capErr = validateIntRange($new_capacity_raw, 'Capacity', 0, 100000);
    
    if ($service_id && !$capErr) {
        $new_capacity = (int)$new_capacity_raw;
        try {
            $stmt = $pdo->prepare("UPDATE services SET capacity = :cap WHERE id = :id");
            $stmt->execute(['cap' => $new_capacity, 'id' => $service_id]);
            $capacitySuccess = true;
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_CAPACITY', "Set Service #{$service_id} capacity slots to {$new_capacity}");
        } catch (PDOException $e) {
            $error = 'Failed to update capacity: ' . $e->getMessage();
        }
    } else {
        $error = $capErr ?? 'Invalid service capacity value.';
    }
}

// Handle regional transit pricing updates
$rateSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_regional_rates']) && empty($error)) {
    $rates_input = $_POST['rates'] ?? [];
    try {
        $stmt = $pdo->prepare("UPDATE regional_rates SET fee = :fee WHERE route_key = :route_key");
        $loggedChanges = [];
        $skippedRoutes = [];

        foreach ($rates_input as $key => $fee) {
            $feeFloat = filter_var($fee, FILTER_VALIDATE_FLOAT);
            if ($feeFloat !== false && $feeFloat >= 0 && $feeFloat <= 100000) {
                $stmt->execute(['fee' => $feeFloat, 'route_key' => $key]);
                $loggedChanges[] = "{$key}: ₱" . number_format($feeFloat, 2);
            } else {
                $skippedRoutes[] = htmlspecialchars($key);
            }
        }
        
        if (!empty($loggedChanges)) {
            $rateSuccess = "Regional island transit fees successfully updated (" . count($loggedChanges) . " routes).";
            if (!empty($skippedRoutes)) {
                $rateSuccess .= " Note: " . implode(', ', $skippedRoutes) . " skipped due to invalid numeric fee.";
            }
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_REGIONAL_RATES', "Updated transit fees (" . implode(', ', $loggedChanges) . ")");
        } else {
            $error = "No valid rate changes provided. Please enter positive numbers.";
        }
    } catch (PDOException $e) {
        error_log("Regional rates update error: " . $e->getMessage());
        $error = 'Failed to update regional rates. Please try again.';
    }
}

// Handle payment methods update
$paymentMethodSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_methods']) && empty($error)) {
    try {
        $allMethods = getPaymentMethods($pdo, false);
        $methodErrors = [];

        // Validate lengths
        foreach ($allMethods as $pm) {
            $code = $pm['method_code'];
            $accName = trim($_POST['account_name_' . $code] ?? '');
            $accNum = trim($_POST['account_number_' . $code] ?? '');
            $instr = trim($_POST['instructions_' . $code] ?? '');

            if (!empty($accName)) {
                $methodErrors[] = validateStringLength($accName, "Account Name ($code)", 100);
            }
            if (!empty($accNum)) {
                $methodErrors[] = validateStringLength($accNum, "Account Number ($code)", 50);
            }
            if (!empty($instr)) {
                $methodErrors[] = validateStringLength($instr, "Instructions ($code)", 2000);
            }
        }

        $cleanMethodErrors = array_filter($methodErrors);
        if (!empty($cleanMethodErrors)) {
            $error = reset($cleanMethodErrors);
        } else {
            $updatedMethodsCount = 0;
            foreach ($allMethods as $pm) {
                $code = $pm['method_code'];
                $data = [
                    'account_name'   => sanitizeString($_POST['account_name_' . $code] ?? ''),
                    'account_number' => sanitizeString($_POST['account_number_' . $code] ?? ''),
                    'instructions'   => sanitizeString($_POST['instructions_' . $code] ?? ''),
                    'is_active'      => isset($_POST['is_active_' . $code]) ? 1 : 0
                ];
                if (updatePaymentMethod($pdo, $code, $data)) {
                    $updatedMethodsCount++;
                }
            }
            $paymentMethodSuccess = "Payment methods successfully updated ($updatedMethodsCount methods configured).";
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_PAYMENT_METHODS', "Updated payment methods settings");
        }
    } catch (Exception $e) {
        error_log("Payment methods update error: " . $e->getMessage());
        $error = 'Failed to update payment methods: ' . $e->getMessage();
    }
}

// Handle service base price and price per kg update
$servicePricingSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_pricing']) && empty($error)) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $base_price_raw = $_POST['base_price'] ?? '';
    $price_per_kg_raw = $_POST['price_per_kg'] ?? '';
    
    $baseErr = validatePositiveNumber($base_price_raw, 'Base price', 1000000);
    $kgErr = validatePositiveNumber($price_per_kg_raw, 'Price per kg', 1000000);
    
    if ($service_id && !$baseErr && !$kgErr) {
        $base_price = (float)$base_price_raw;
        $price_per_kg = (float)$price_per_kg_raw;
        try {
            $stmt = $pdo->prepare("UPDATE services SET base_price = :base_price, price_per_kg = :price_per_kg WHERE id = :id");
            $stmt->execute([
                'base_price' => $base_price,
                'price_per_kg' => $price_per_kg,
                'id' => $service_id
            ]);
            $servicePricingSuccess = true;
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_SERVICE_PRICING', "Updated Service #{$service_id} base price to ₱{$base_price} and per-kg to ₱{$price_per_kg}");
        } catch (PDOException $e) {
            $error = 'Failed to update service pricing: ' . $e->getMessage();
        }
    } else {
        $error = $baseErr ?: ($kgErr ?: 'Invalid service price values provided.');
    }
}

// Handle marking contact message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_message_read']) && empty($error)) {
    $msgId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    if ($msgId) {
        try {
            $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id");
            $stmt->execute(['id' => $msgId]);
            $updateSuccess = "Inquiry #{$msgId} marked as read.";
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'READ_MESSAGE', "Marked contact message #{$msgId} as read");
        } catch (PDOException $e) {
            $error = 'Failed to update message status: ' . $e->getMessage();
        }
    }
}

// Status & Search filters for bookings table
$statusFilter = trim($_GET['status'] ?? 'all');
$validFilters = ['all', 'pending', 'dispatched', 'delivered', 'unpaid', 'under_review'];
if ($fErr = validateInArray($statusFilter, $validFilters, 'filter')) {
    $statusFilter = 'all';
}

$searchQuery = trim($_GET['q'] ?? '');
$whereParts = [];
$params = [];

if ($statusFilter === 'unpaid') {
    $whereParts[] = "b.payment_status = 'unpaid'";
} elseif ($statusFilter === 'under_review') {
    $whereParts[] = "b.payment_status = 'under_review'";
} elseif ($statusFilter !== 'all') {
    $whereParts[] = "b.status = :status";
    $params['status'] = $statusFilter;
}

if (!empty($searchQuery)) {
    $whereParts[] = "(b.tracking_code LIKE :search OR u.username LIKE :search OR b.delivery_address LIKE :search OR CAST(b.id AS CHAR) = :exact_id)";
    $params['search'] = '%' . $searchQuery . '%';
    $params['exact_id'] = $searchQuery;
}

$whereClause = !empty($whereParts) ? "WHERE " . implode(" AND ", $whereParts) : "";

// Fetch bookings based on filter
$stmt = $pdo->prepare("
    SELECT b.*, u.username, s.name AS service_name 
    FROM bookings b 
    JOIN user u ON b.user_id = u.id 
    JOIN services s ON b.service_id = s.id 
    {$whereClause}
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch quick counters for KPI widgets
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$unpaidCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'unpaid'")->fetchColumn();
$underReviewCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'under_review'")->fetchColumn();
$readyToDispatchCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending' AND payment_status = 'paid'")->fetchColumn();
$deliveredTodayCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")->fetchColumn();

// Fetch all services for capacity and rate management
$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all regional island route rates
$regionalRatesList = $pdo->query("SELECT * FROM regional_rates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all payment methods for admin settings
$adminPaymentMethods = getPaymentMethods($pdo, false);

// Fetch recent contact inquiries
$contactMessages = $pdo->query("
    SELECT * FROM contact_messages 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
$unreadMessagesCount = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();

// Fetch recent admin audit logs
$adminLogs = $pdo->query("
    SELECT l.*, u.username AS admin_username 
    FROM admin_logs l 
    LEFT JOIN user u ON l.admin_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Dashboard | YOCOR Express";
$activeNav = "admin";
include __DIR__ . '/../includes/header.php'; 
?>

<style>
    .admin-sidebar {
        transition: width 0.2s ease-in-out;
    }
    .admin-sidebar details > summary::-webkit-details-marker {
        display: none;
    }
    .admin-sidebar details[open] .sidebar-chevron {
        transform: rotate(90deg);
    }
    @media (min-width: 768px) {
        .admin-sidebar.collapsed {
            width: 4.5rem !important;
        }
        .admin-sidebar.collapsed .admin-sidebar-text,
        .admin-sidebar.collapsed nav span,
        .admin-sidebar.collapsed nav .nav-badge,
        .admin-sidebar.collapsed nav .sidebar-heading,
        .admin-sidebar.collapsed nav .sidebar-chevron,
        .admin-sidebar.collapsed nav .nested-nav {
            display: none !important;
        }
        .admin-sidebar.collapsed nav a,
        .admin-sidebar.collapsed nav summary {
            justify-content: center !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .admin-sidebar.collapsed #sidebarToggleIcon {
            transform: rotate(180deg);
        }
    }
    @media (max-width: 767px) {
        .admin-sidebar.mobile-hidden {
            display: none !important;
        }
    }
</style>

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] w-full bg-slate-50">

    <!-- Responsive Collapsible Admin Sidebar -->
    <aside id="adminSidebar" class="admin-sidebar w-full md:w-64 bg-white border-r border-slate-200 p-4 sm:p-5 md:py-6 flex-shrink-0 md:sticky md:top-16 md:h-[calc(100vh-4rem)] md:overflow-y-auto">
        <div class="mb-5 pb-4 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-brandNavy text-white flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
                    <i class="fa-solid fa-gauge-high text-brandOrange"></i>
                </div>
                <div class="admin-sidebar-text whitespace-nowrap overflow-hidden">
                    <h3 class="font-black text-brandNavy text-sm">Admin Control</h3>
                    <p class="text-[11px] text-slate-500 font-medium truncate">User: <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
            <!-- Desktop Sidebar Collapse Toggle Button -->
            <button id="sidebarToggle" type="button" class="hidden md:flex p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-brandNavy transition-colors flex-shrink-0" title="Toggle Sidebar Width">
                <i id="sidebarToggleIcon" class="fa-solid fa-chevron-left text-xs transition-transform"></i>
            </button>
        </div>

        <nav class="space-y-4 text-xs font-bold">
            <!-- 1. DASHBOARD -->
            <div class="space-y-1">
                <a href="#overview" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-brandNavy/5 text-brandNavy hover:bg-brandNavy/10 transition-colors" title="Overview & Stats">
                    <i class="fa-solid fa-chart-pie text-brandOrange text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Overview & Stats</span>
                </a>
            </div>

            <!-- 2. OPERATIONS -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Operations</div>
                <a href="#all-bookings" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Customer Shipments">
                    <i class="fa-solid fa-boxes-packing text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Customer Shipments</span>
                </a>
                <a href="tracking.php" target="_blank" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Live Tracker (Opens page)">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-magnifying-glass-location text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                        <span class="truncate">Live Tracker</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-300"></i>
                </a>
            </div>

            <!-- 3. FLEET & PRICING (COLLAPSIBLE SERVICE CATALOG) -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Fleet & Rates</div>
                <details class="group" open>
                    <summary class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 cursor-pointer list-none select-none transition-colors">
                        <div class="flex items-center gap-3 truncate">
                            <i class="fa-solid fa-layer-group text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                            <span class="truncate">Service Catalog</span>
                        </div>
                        <i class="sidebar-chevron fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform duration-200"></i>
                    </summary>
                    <div class="nested-nav pl-7 pr-2 pt-1 pb-1 space-y-1 border-l-2 border-slate-100 ml-4.5 mt-1">
                        <a href="admin_services.php" class="flex items-center justify-between py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]" title="Manage Tiers & Fleet">
                            <span class="truncate">Services & Fleet</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-300"></i>
                        </a>
                        <a href="#capacities" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Daily Capacities</span>
                        </a>
                        <a href="#regional-rates" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Transit Rates</span>
                        </a>
                        <a href="#payment-settings" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Payment Methods</span>
                        </a>
                    </div>
                </details>
            </div>

            <!-- 4. SUPPORT & SECURITY -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Support & Logs</div>
                <a href="admin_inquiries.php" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Customer Inquiries & Reports">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-regular fa-envelope text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                        <span class="truncate">Support Inquiries</span>
                    </div>
                    <?php if ($unreadMessagesCount > 0): ?>
                        <span class="nav-badge px-1.5 py-0.5 rounded-full bg-brandOrange text-white font-black text-[9px] leading-none">
                            <?= $unreadMessagesCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="#audit-logs" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Security Audit Logs">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Audit Trail</span>
                </a>
            </div>

            <!-- 5. SYSTEM -->
            <div class="pt-3 border-t border-slate-100 mt-3 space-y-1">
                <a href="logout.php" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-red-600 hover:bg-red-50 transition-colors" title="Sign Out">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-right-from-bracket text-sm w-4 text-center flex-shrink-0"></i>
                        <span class="truncate">Sign Out</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-red-300"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Admin Main Content Area -->
    <main class="flex-1 min-w-0 p-3 sm:p-5 md:p-6 lg:p-8">
        
        <!-- Header -->
        <div id="overview" class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
            <div class="flex items-center gap-3">
                <!-- Mobile Sidebar Toggle -->
                <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-xl bg-white border border-slate-200 text-brandNavy shadow-sm hover:bg-slate-50 transition-colors" title="Toggle Navigation Menu">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading">
                        Admin Operations Center
                    </h1>
                    <p class="text-slate-600 text-xs mt-0.5">Review live dispatches, update parcel statuses, and adjust fleet availability.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="bg-white border border-slate-200 shadow-sm text-brandNavy px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar text-brandOrange"></i> <?= date('F d, Y') ?>
                </span>
            </div>
        </div>

        <!-- Feedback Alerts -->
        <?php if (!empty($updateSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span><?= htmlspecialchars(is_string($updateSuccess) ? $updateSuccess : 'Update successful!') ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($deleteSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-trash-can text-emerald-600 text-sm"></i> 
                <span><?= htmlspecialchars($deleteSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($capacitySuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span>Service capacity successfully updated!</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($rateSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span><?= htmlspecialchars($rateSuccess) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($servicePricingSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span>Service pricing (base & per kg rates) successfully updated!</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($paymentMethodSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span><?= htmlspecialchars($paymentMethodSuccess) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- High-Density Operations KPI Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <a href="admin_dashboard.php?status=all#all-bookings" class="bg-white rounded-xl shadow-sm border <?= $statusFilter === 'all' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> p-3.5 hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 text-brandNavy flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Waybills</p>
                        <p class="text-xl font-black text-brandNavy font-heading"><?= $totalCount ?></p>
                    </div>
                </div>
            </a>

            <a href="admin_dashboard.php?status=under_review#all-bookings" class="bg-white rounded-xl shadow-sm border <?= $statusFilter === 'under_review' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> p-3.5 hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Under Review</p>
                        <p class="text-xl font-black text-blue-600 font-heading"><?= $underReviewCount ?></p>
                    </div>
                </div>
            </a>

            <a href="admin_dashboard.php?status=pending#all-bookings" class="bg-white rounded-xl shadow-sm border <?= $statusFilter === 'pending' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> p-3.5 hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Ready to Dispatch</p>
                        <p class="text-xl font-black text-amber-600 font-heading"><?= $readyToDispatchCount ?></p>
                    </div>
                </div>
            </a>

            <a href="admin_dashboard.php?status=delivered#all-bookings" class="bg-white rounded-xl shadow-sm border <?= $statusFilter === 'delivered' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> p-3.5 hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Delivered Today</p>
                        <p class="text-xl font-black text-emerald-600 font-heading"><?= $deliveredTodayCount ?></p>
                    </div>
                </div>
            </a>
        </div>

        <!-- All Bookings Table with Quick Status Form -->
        <div id="all-bookings" class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <!-- Section Header & Controls -->
            <div class="p-4 sm:p-5 border-b border-slate-100 bg-white">
                <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-orange-50 text-brandOrange flex items-center justify-center text-sm font-bold shadow-xs">
                                <i class="fa-solid fa-boxes-packing"></i>
                            </div>
                            <h2 class="font-extrabold text-brandNavy text-base font-heading">
                                Customer Shipments &amp; Dispatch Controller
                            </h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                <?= count($bookings) ?> Showing
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Monitor shipments in real-time, update courier checkpoints, verify customer payments, and approve dispatch transitions.</p>
                    </div>

                    <!-- Search Bar -->
                    <form method="GET" action="#all-bookings" class="w-full lg:w-72 flex items-center gap-2">
                        <?php if ($statusFilter !== 'all'): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                        <?php endif; ?>
                        <div class="relative w-full">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" 
                                   placeholder="Search Waybill, Customer, City..." 
                                   class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                        </div>
                        <?php if (!empty($searchQuery)): ?>
                            <a href="admin_dashboard.php?status=<?= urlencode($statusFilter) ?>#all-bookings" 
                               class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs" title="Clear Search">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Interactive Filter Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 mt-4 pt-4 border-t border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1 flex items-center gap-1">
                        <i class="fa-solid fa-sliders text-[10px]"></i> Filter:
                    </span>
                    <a href="admin_dashboard.php?status=all<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'all' ? 'bg-brandNavy text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        All (<?= $totalCount ?>)
                    </a>
                    <a href="admin_dashboard.php?status=under_review<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'under_review' ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                        <i class="fa-solid fa-hourglass-half text-[10px] mr-1"></i> Under Review (<?= $underReviewCount ?>)
                    </a>
                    <a href="admin_dashboard.php?status=unpaid<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'unpaid' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' ?>">
                        <i class="fa-solid fa-clock text-[10px] mr-1"></i> Unpaid (<?= $unpaidCount ?>)
                    </a>
                    <a href="admin_dashboard.php?status=pending<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'pending' ? 'bg-slate-700 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        <i class="fa-solid fa-boxes-packing text-[10px] mr-1"></i> Pending
                    </a>
                    <a href="admin_dashboard.php?status=dispatched<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'dispatched' ? 'bg-purple-600 text-white shadow-xs' : 'bg-purple-50 text-purple-700 hover:bg-purple-100' ?>">
                        <i class="fa-solid fa-truck text-[10px] mr-1"></i> In Transit
                    </a>
                    <a href="admin_dashboard.php?status=delivered<?= !empty($searchQuery) ? '&q=' . urlencode($searchQuery) : '' ?>#all-bookings" 
                       class="px-3 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'delivered' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                        <i class="fa-solid fa-circle-check text-[10px] mr-1"></i> Delivered
                    </a>
                </div>
            </div>
            
            <!-- Bookings High Density Data Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/80 text-slate-500 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider select-none">
                        <tr>
                            <th class="px-4 py-3">Tracking &amp; Service</th>
                            <th class="px-4 py-3">Customer &amp; Route</th>
                            <th class="px-4 py-3">Weight &amp; Cost</th>
                            <th class="px-4 py-3">Payment</th>
                            <th class="px-4 py-3 min-w-[220px]">Live Checkpoint &amp; Rider Note</th>
                            <th class="px-4 py-3 min-w-[190px]">Status Control</th>
                            <th class="px-4 py-3 text-right">Quick Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium bg-white">
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                    <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl mx-auto mb-2">
                                        <i class="fa-regular fa-folder-open"></i>
                                    </div>
                                    <p class="font-bold text-slate-700 text-sm">No shipments found</p>
                                    <p class="text-slate-400 text-xs mt-0.5">There are no orders matching your current filter or search keyword.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <?php 
                                $isDelivered = ($booking['status'] === 'delivered');
                                $isDispatched = ($booking['status'] === 'dispatched');
                                $isPending = ($booking['status'] === 'pending');
                                $isCancelled = ($booking['status'] === 'cancelled');
                                $isPaid = (($booking['payment_status'] ?? 'unpaid') === 'paid');
                                ?>
                                <tr class="hover:bg-slate-50/90 transition-colors <?= $isDelivered ? 'bg-slate-50/30' : '' ?>">
                                    <!-- Tracking & Service -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-mono font-bold text-brandNavy text-xs flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-brandNavy font-mono">
                                                <?= htmlspecialchars($booking['tracking_code'] ?? ('#' . $booking['id'])) ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-[11px] text-slate-500 mt-1">
                                            <span class="inline-block w-2 h-2 rounded-full <?= $isDelivered ? 'bg-emerald-500' : ($isDispatched ? 'bg-purple-500 animate-pulse' : ($isCancelled ? 'bg-rose-500' : 'bg-amber-500')) ?>"></span>
                                            <span class="font-semibold text-slate-700"><?= htmlspecialchars($booking['service_name']) ?></span>
                                            <span class="text-slate-300">•</span>
                                            <span class="text-[10px] text-slate-400">#<?= $booking['id'] ?></span>
                                        </div>
                                    </td>

                                    <!-- Customer & Route -->
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                                            <i class="fa-regular fa-user text-slate-400 text-[11px]"></i>
                                            <span><?= htmlspecialchars($booking['username']) ?></span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 mt-1 max-w-[220px]">
                                            <?php if (!empty($booking['pickup_region']) && !empty($booking['delivery_region'])): ?>
                                                <div class="mb-0.5">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-1.5 py-0.2 rounded bg-orange-50 text-brandOrange border border-orange-200">
                                                        <?= htmlspecialchars($booking['pickup_region']) ?> &rarr; <?= htmlspecialchars($booking['delivery_region']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <p class="truncate text-slate-500" title="<?= htmlspecialchars($booking['delivery_address']) ?>">
                                                <?= htmlspecialchars($booking['delivery_address']) ?>
                                            </p>
                                        </div>
                                    </td>

                                    <!-- Weight & Cost -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="font-extrabold text-brandNavy text-xs">
                                            ₱<?= number_format((float)($booking['total_cost'] ?? 0), 2) ?>
                                        </div>
                                        <div class="text-[10px] font-semibold text-slate-400 mt-0.5">
                                            <?= $booking['weight'] ?> kg cargo
                                        </div>
                                    </td>

                                    <!-- Payment Status & Quick Mark as Paid -->
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($isPaid): ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                                    <i class="fa-solid fa-circle-check text-[9px] text-emerald-600"></i> Paid &amp; Cleared
                                                </span>
                                                <div class="text-[10px] text-slate-500 font-mono">
                                                    <?= !empty($booking['paid_at']) ? date('M d, H:i', strtotime($booking['paid_at'])) : 'Verified' ?>
                                                    <?php if (!empty($booking['payment_method'])): ?>
                                                        <span class="text-slate-400">· <?= strtoupper(htmlspecialchars($booking['payment_method'])) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (!empty($booking['payment_ref'])): ?>
                                                    <div class="text-[9px] font-mono text-slate-500 truncate max-w-[140px]" title="Ref: <?= htmlspecialchars($booking['payment_ref']) ?>">
                                                        Ref: <?= htmlspecialchars($booking['payment_ref']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (($booking['payment_status'] ?? 'unpaid') === 'under_review'): ?>
                                            <div class="space-y-1.5">
                                                <div class="flex items-center gap-1">
                                                    <span class="inline-flex items-center gap-1 text-[10px] font-extrabold text-blue-800 bg-blue-50 px-2 py-0.5 rounded-full border border-blue-200">
                                                        <i class="fa-solid fa-hourglass-half text-[9px] text-blue-600 animate-spin"></i> Under Review
                                                    </span>
                                                    <?php if (!empty($booking['payment_method'])): ?>
                                                        <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 uppercase font-mono">
                                                            <?= htmlspecialchars($booking['payment_method']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Payment Proof / Reference Details for Admin Verification -->
                                                <div class="text-[10px] text-slate-700 bg-blue-50/60 p-2 rounded-lg border border-blue-200/80 space-y-0.5 max-w-[180px]">
                                                    <?php if (!empty($booking['payment_ref'])): ?>
                                                        <div>
                                                            <span class="text-slate-500 font-semibold text-[9px] block">Unique Reference:</span>
                                                            <span class="font-mono font-black text-brandNavy select-all text-[11px] block truncate" title="<?= htmlspecialchars($booking['payment_ref']) ?>">
                                                                <?= htmlspecialchars($booking['payment_ref']) ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-slate-400 italic text-[10px]">No reference code provided</span>
                                                    <?php endif; ?>

                                                    <?php if (!empty($booking['payment_account_name']) || !empty($booking['payment_account_number'])): ?>
                                                        <div class="text-[9px] text-slate-500 pt-0.5 border-t border-blue-100 truncate">
                                                            <?= htmlspecialchars($booking['payment_account_name'] ?? '') ?>
                                                            <?= !empty($booking['payment_account_number']) ? '(' . htmlspecialchars($booking['payment_account_number']) . ')' : '' ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <form method="POST" action="" class="block pt-0.5" onsubmit="return confirm('Confirm that payment with Ref <?= htmlspecialchars($booking['payment_ref'] ?? '') ?> has been validated and received?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <button type="submit" name="mark_paid" class="inline-flex items-center gap-1.5 px-2 py-1 rounded bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-[10px] transition-colors shadow-xs">
                                                        <i class="fa-solid fa-circle-check text-[9px]"></i>
                                                        <span>Confirm &amp; Clear Paid</span>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="space-y-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                                    <i class="fa-solid fa-clock text-[9px] text-amber-600"></i> Unpaid
                                                </span>
                                                <form method="POST" action="" class="block" onsubmit="return confirm('Mark Booking #<?= $booking['id'] ?> as PAID?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <button type="submit" name="mark_paid" class="text-[10px] font-bold text-brandOrange hover:text-orange-700 hover:underline flex items-center gap-1">
                                                        <i class="fa-solid fa-hand-holding-dollar text-[9px]"></i> Mark Paid
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Editable Checkpoint & Tracker Notes Form -->
                                    <td class="px-4 py-3">
                                        <?php if ($isDelivered): ?>
                                            <div class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-200/70">
                                                <p class="font-bold text-slate-700 flex items-center gap-1.5">
                                                    <i class="fa-solid fa-lock text-slate-400 text-[10px]"></i>
                                                    <span><?= htmlspecialchars($booking['current_checkpoint'] ?? 'Delivered to Recipient') ?></span>
                                                </p>
                                                <?php if (!empty($booking['tracker_notes'])): ?>
                                                    <p class="text-[10px] text-slate-500 italic mt-0.5 truncate max-w-[220px]"><?= htmlspecialchars($booking['tracker_notes']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="" class="space-y-1.5">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <div class="flex items-center gap-1.5">
                                                    <div class="relative w-full">
                                                        <input type="text" name="checkpoint" value="<?= htmlspecialchars($booking['current_checkpoint'] ?? '') ?>" 
                                                               placeholder="Hub location..." 
                                                               class="w-full pl-2 pr-2 py-1 text-[11px] rounded-lg border border-slate-200 bg-slate-50 focus:bg-white focus:ring-1 focus:ring-brandOrange outline-none transition-all">
                                                    </div>
                                                    <button type="submit" name="update_tracker" class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-brandNavy hover:text-white text-slate-700 font-bold text-[10px] transition-colors border border-slate-200 flex-shrink-0" title="Save Checkpoint">
                                                        <i class="fa-solid fa-floppy-disk"></i>
                                                    </button>
                                                </div>
                                                <input type="text" name="notes" value="<?= htmlspecialchars($booking['tracker_notes'] ?? '') ?>" 
                                                       placeholder="Rider / status note..." 
                                                       class="w-full px-2 py-1 text-[10px] rounded-lg border border-slate-200 bg-slate-50/50 focus:bg-white focus:ring-1 focus:ring-brandOrange outline-none text-slate-600 transition-all">
                                            </form>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Interactive Status Update Form -->
                                    <td class="px-4 py-3">
                                        <?php if ($isDelivered): ?>
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-800 text-[11px] font-extrabold border border-emerald-200">
                                                <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                                                <span>Delivered (Locked)</span>
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" action="" class="flex items-center gap-1.5">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                <select name="new_status" class="px-2.5 py-1.5 text-[11px] font-bold rounded-lg border border-slate-200 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all shadow-2xs">
                                                    <?php if ($booking['status'] === 'pending'): ?>
                                                        <option value="pending" selected>⏳ Pending</option>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($isPaid): ?>
                                                        <option value="dispatched" <?= $booking['status'] === 'dispatched' ? 'selected' : '' ?>>🚚 Dispatched</option>
                                                        <option value="delivered" <?= $booking['status'] === 'delivered' ? 'selected' : '' ?>>✅ Delivered</option>
                                                    <?php else: ?>
                                                        <option disabled value="" class="text-slate-400">🔒 Paid Only: Dispatch</option>
                                                    <?php endif; ?>

                                                    <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_status" 
                                                        class="bg-brandNavy hover:bg-slate-900 text-white font-bold px-3 py-1.5 rounded-lg text-[11px] transition-all shadow-xs flex-shrink-0"
                                                        title="Apply Status Transition">
                                                    Update
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions Column -->
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <div class="inline-flex items-center gap-1.5">
                                            <a href="receipt.php?id=<?= (int)$booking['id'] ?>" 
                                               class="inline-flex items-center gap-1 text-[11px] text-slate-700 hover:text-brandNavy font-bold px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 transition-colors"
                                               target="_blank" title="View Waybill Receipt">
                                                <i class="fa-solid fa-receipt text-[10px] text-brandOrange"></i>
                                                <span>Receipt</span>
                                            </a>

                                            <a href="admin_booking_timeline.php?id=<?= (int)$booking['id'] ?>" 
                                               class="inline-flex items-center gap-1 text-[11px] text-blue-700 hover:text-blue-900 font-bold px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 border border-blue-200 transition-colors"
                                               target="_blank" title="Manage Live Checkpoints & Audit Timeline">
                                                <i class="fa-solid fa-timeline text-[10px] text-blue-600"></i>
                                                <span>Checkpoints</span>
                                            </a>

                                            <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" 
                                               class="inline-flex items-center gap-1 text-[11px] text-brandOrange hover:text-orange-700 font-bold px-2.5 py-1 rounded-lg bg-orange-50 hover:bg-orange-100 transition-colors"
                                               target="_blank" title="Live Customer Tracker">
                                                <span>Track</span>
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                            </a>

                                            <?php if ($isDelivered): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Permanently delete delivered parcel #<?= htmlspecialchars($booking['tracking_code'] ?? $booking['id']) ?>?');" class="inline">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <button type="submit" name="delete_booking" 
                                                            class="inline-flex items-center gap-1 text-[11px] text-rose-600 hover:text-rose-800 font-bold px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 transition-colors"
                                                            title="Archive Delivered Parcel">
                                                        <i class="fa-regular fa-trash-can text-[10px]"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Manage Service Fleet & Pricing Section -->
        <div id="capacities" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-solid fa-cubes text-brandOrange"></i>
                        <span>Manage Service Fleet, Capacities & Base Rates</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">Update daily booking slots, starting base price, and additional rate per kilogram.</p>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($services as $service): ?>
                        <div class="border border-slate-200 rounded-xl p-4 hover:shadow-md transition-shadow bg-slate-50/40 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="font-bold text-brandNavy text-xs"><?= htmlspecialchars($service['name']) ?></h3>
                                    <?php if ($service['capacity'] > 0): ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                            <?= $service['capacity'] ?> Slots
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                            Full
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-[11px] text-slate-600 mb-3 line-clamp-2">
                                    <?= htmlspecialchars($service['description']) ?>
                                </p>
                            </div>
                            
                            <div class="space-y-3 pt-3 border-t border-slate-200/80">
                                <!-- Capacity Editor -->
                                <div class="flex items-center justify-between gap-2">
                                    <div>
                                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Capacity</p>
                                    </div>
                                    <form method="POST" class="flex items-center gap-1">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                        <input type="number" name="new_capacity" min="0" 
                                               value="<?= $service['capacity'] ?>"
                                               class="w-16 px-2 py-1 rounded-md bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-bold text-center">
                                        <button type="submit" name="update_capacity"
                                                class="bg-brandNavy hover:bg-slate-900 text-white font-bold px-2 py-1 rounded-md transition-all text-[11px] shadow-sm">
                                            Set
                                        </button>
                                    </form>
                                </div>

                                <!-- Service Pricing Editor -->
                                <form method="POST" class="pt-2 border-t border-slate-100 flex flex-col gap-2">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Base Price (₱)</label>
                                            <input type="number" step="0.01" min="0" name="base_price" 
                                                   value="<?= htmlspecialchars($service['base_price'] ?? 100) ?>" 
                                                   class="w-full px-2 py-1 rounded-md bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-bold">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-slate-500 mb-0.5">Per Kg (₱)</label>
                                            <input type="number" step="0.01" min="0" name="price_per_kg" 
                                                   value="<?= htmlspecialchars($service['price_per_kg'] ?? 40) ?>" 
                                                   class="w-full px-2 py-1 rounded-md bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-bold">
                                        </div>
                                    </div>
                                    <button type="submit" name="update_service_pricing"
                                            class="w-full mt-1 bg-slate-100 hover:bg-brandOrange hover:text-white text-slate-700 font-bold py-1 px-2 rounded-md text-[11px] transition-all border border-slate-200">
                                        <i class="fa-solid fa-floppy-disk text-[10px] mr-1"></i> Update Pricing
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Manage Island Transit Rates Section -->
        <div id="regional-rates" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1.5">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-solid fa-earth-asia text-brandOrange"></i>
                        <span>Philippine Island Group Transit Pricing</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">
                        Adjust dynamic distance-based fees applied when shipping within or across Philippine island groups (Luzon, Visayas, Mindanao).
                    </p>
                </div>
                <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    <i class="fa-solid fa-bolt text-emerald-500"></i> Live Dynamic Rates
                </span>
            </div>

            <div class="p-5">
                <form method="POST" action="#regional-rates" class="space-y-4">
                    <?= csrfField() ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php foreach ($regionalRatesList as $rate): ?>
                            <?php 
                            $badgeColor = 'bg-blue-50 text-blue-800 border-blue-200';
                            $icon = 'fa-map-location';
                            if ($rate['route_key'] === 'intra_island') {
                                $badgeColor = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                                $icon = 'fa-city';
                            } elseif ($rate['route_key'] === 'cross_island') {
                                $badgeColor = 'bg-purple-50 text-purple-800 border-purple-200';
                                $icon = 'fa-plane-departure';
                            }
                            ?>
                            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/40 flex flex-col justify-between hover:border-slate-300 transition-colors">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider border <?= $badgeColor ?>">
                                            <i class="fa-solid <?= $icon ?> mr-1"></i> <?= htmlspecialchars($rate['route_key']) ?>
                                        </span>
                                    </div>
                                    <h4 class="font-bold text-brandNavy text-xs mb-1"><?= htmlspecialchars($rate['route_name']) ?></h4>
                                    <p class="text-[11px] text-slate-500 mb-3">
                                        <?php if ($rate['route_key'] === 'intra_island'): ?>
                                            Origins & destinations located within the same island group (e.g., Luzon to Luzon, Visayas to Visayas, Mindanao to Mindanao).
                                        <?php elseif ($rate['route_key'] === 'inter_island'): ?>
                                            Direct sea/air routes connecting adjacent islands (e.g., Luzon to Visayas, or Visayas to Mindanao).
                                        <?php else: ?>
                                            Long-distance transit between non-adjacent island territories (e.g., Luzon to Mindanao, or Mindanao to Luzon).
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="pt-3 border-t border-slate-200/80">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                        Transit Route Fee (₱)
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">₱</span>
                                        <input type="number" step="0.01" min="0" 
                                               name="rates[<?= htmlspecialchars($rate['route_key']) ?>]" 
                                               value="<?= number_format((float)$rate['fee'], 2, '.', '') ?>" 
                                               class="w-full pl-7 pr-3 py-1.5 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-black text-brandNavy">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
                        <p class="text-[11px] text-slate-500">
                            <i class="fa-solid fa-circle-info text-brandOrange mr-1"></i>
                            Changes take effect immediately on the customer shipping calculator and new booking checkouts.
                        </p>
                        <button type="submit" name="update_regional_rates"
                                class="w-full sm:w-auto bg-brandOrange hover:bg-orange-600 text-white font-bold px-5 py-2 rounded-lg text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save All Island Rates</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Method Account Numbers & Instructions Configuration Section (Admin Only) -->
        <div id="payment-settings" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-4 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-solid fa-wallet text-brandOrange"></i>
                        <span>Customer Payment Methods & Accounts Configuration</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">Change official GCash, Maya, and Card payment numbers, receiver account names, and checkout instructions dynamically.</p>
                </div>
                <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                    <i class="fa-solid fa-shield-halved text-[9px]"></i> Admin Protected
                </span>
            </div>

            <div class="p-5">
                <form method="POST" action="#payment-settings" class="space-y-5">
                    <?= csrfField() ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <?php foreach ($adminPaymentMethods as $pm): 
                            $code = $pm['method_code'];
                            $badgeColor = $pm['is_active'] ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-slate-100 text-slate-500 border-slate-200';
                            $icon = 'fa-credit-card';
                            if ($code === 'gcash') $icon = 'fa-mobile-screen-button';
                            elseif ($code === 'maya') $icon = 'fa-wallet';
                            elseif ($code === 'cod') $icon = 'fa-hand-holding-dollar';
                        ?>
                            <div class="border border-slate-200 rounded-xl p-4 bg-slate-50/40 hover:border-slate-300 transition-colors flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200/80">
                                        <div class="flex items-center gap-2">
                                            <span class="w-7 h-7 rounded-lg bg-brandNavy text-white flex items-center justify-center text-xs">
                                                <i class="fa-solid <?= $icon ?>"></i>
                                            </span>
                                            <div>
                                                <h4 class="font-bold text-brandNavy text-xs leading-none"><?= htmlspecialchars($pm['method_name']) ?></h4>
                                                <span class="font-mono text-[10px] text-slate-400 uppercase"><?= htmlspecialchars($code) ?></span>
                                            </div>
                                        </div>
                                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-bold text-slate-700">
                                            <input type="checkbox" name="is_active_<?= htmlspecialchars($code) ?>" value="1" <?= !empty($pm['is_active']) ? 'checked' : '' ?> class="rounded border-slate-300 text-brandOrange focus:ring-brandOrange">
                                            <span class="text-[11px] <?= !empty($pm['is_active']) ? 'text-emerald-700 font-bold' : 'text-slate-400' ?>">
                                                <?= !empty($pm['is_active']) ? 'Enabled' : 'Disabled' ?>
                                            </span>
                                        </label>
                                    </div>

                                    <?php if ($code !== 'cod'): ?>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                    Account Number / Mobile
                                                </label>
                                                <input type="text" name="account_number_<?= htmlspecialchars($code) ?>" 
                                                       value="<?= htmlspecialchars($pm['account_number'] ?? '') ?>" 
                                                       placeholder="e.g. 0917-882-9090"
                                                       class="w-full px-3 py-1.5 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-mono font-bold text-brandNavy">
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                                    Account Name / Business
                                                </label>
                                                <input type="text" name="account_name_<?= htmlspecialchars($code) ?>" 
                                                       value="<?= htmlspecialchars($pm['account_name'] ?? '') ?>" 
                                                       placeholder="e.g. YOCOR Express Logistics"
                                                       class="w-full px-3 py-1.5 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-semibold text-slate-800">
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">
                                            Customer Instructions Note
                                        </label>
                                        <textarea name="instructions_<?= htmlspecialchars($code) ?>" rows="2"
                                                  class="w-full px-3 py-1.5 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs text-slate-700 resize-none"><?= htmlspecialchars($pm['instructions'] ?? '') ?></textarea>
                                        <p class="text-[9px] text-slate-400 mt-0.5">Use <code class="bg-slate-200/80 px-1 py-0.5 rounded">{amount}</code> for dynamic order total, <code class="bg-slate-200/80 px-1 py-0.5 rounded">{account_number}</code> for account no., and <code class="bg-slate-200/80 px-1 py-0.5 rounded">{account_name}</code> for merchant name.</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3 border-t border-slate-100">
                        <p class="text-[11px] text-slate-500">
                            <i class="fa-solid fa-circle-info text-brandOrange mr-1"></i>
                            Changes take effect immediately on the Checkout / Settlement page for all users.
                        </p>
                        <button type="submit" name="update_payment_methods"
                                class="w-full sm:w-auto bg-brandOrange hover:bg-orange-600 text-white font-bold px-5 py-2 rounded-lg text-xs shadow-md transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Save Payment Method Changes</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Customer Support Inquiries Section -->
        <div id="contact-inquiries" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-regular fa-envelope text-brandOrange"></i>
                        <span>Customer Support & Inquiries Inbox</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">Messages sent by customers via the public contact portal.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-semibold text-slate-500 mr-1">
                        Showing <?= count($contactMessages) ?> recent inquiry(ies)
                    </span>
                    <a href="admin_inquiries.php" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-brandNavy hover:bg-slate-900 text-white font-bold text-xs transition-colors shadow-sm">
                        <span>Open Inquiries Portal</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">From</th>
                            <th class="px-4 py-2.5">Subject</th>
                            <th class="px-4 py-2.5">Message Content</th>
                            <th class="px-4 py-2.5 text-right">Status / Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($contactMessages)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                                    <i class="fa-regular fa-folder-open text-2xl mb-1 text-slate-300"></i>
                                    <p class="text-xs">No customer inquiries received yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contactMessages as $msg): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors <?= empty($msg['is_read']) ? 'bg-orange-50/20' : '' ?>">
                                    <td class="px-4 py-2.5 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                                        <?= date('M d, Y · h:i A', strtotime($msg['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-brandNavy whitespace-nowrap">
                                        <div><?= htmlspecialchars($msg['name']) ?></div>
                                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" class="text-[10px] text-slate-400 font-normal hover:text-brandOrange underline">
                                            <?= htmlspecialchars($msg['email']) ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 font-semibold text-slate-800 text-[11px] whitespace-nowrap">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 text-[11px] max-w-md break-words">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                        <?php if (empty($msg['is_read'])): ?>
                                            <form method="POST" action="" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                <button type="submit" name="mark_message_read" 
                                                        class="px-2.5 py-1 rounded-md bg-brandNavy hover:bg-slate-900 text-white font-bold text-[10px] shadow-sm transition-colors">
                                                    <i class="fa-solid fa-check text-[9px] mr-1"></i> Mark Read
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                                                <i class="fa-solid fa-check-double text-[9px]"></i> Read
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Admin Audit / Activity Log Section -->
        <div id="audit-logs" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1.5">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-solid fa-clock-rotate-left text-brandOrange"></i>
                        <span>Admin Audit & Activity Log</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">
                        Immutable security audit trail recording operational actions, status transitions, and pricing configurations.
                    </p>
                </div>
                <span class="text-[11px] font-semibold text-slate-500">Showing last <?= count($adminLogs) ?> event(s)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5">Timestamp</th>
                            <th class="px-4 py-2.5">Admin</th>
                            <th class="px-4 py-2.5">Action</th>
                            <th class="px-4 py-2.5">Details</th>
                            <th class="px-4 py-2.5">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($adminLogs)): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-slate-400">
                                    <i class="fa-solid fa-clipboard-check text-2xl mb-1 text-slate-300"></i>
                                    <p class="text-xs">No admin actions recorded yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($adminLogs as $log): ?>
                                <?php
                                $badgeStyle = 'bg-slate-100 text-slate-700 border-slate-200';
                                if (strpos($log['action'], 'STATUS') !== false) {
                                    $badgeStyle = 'bg-blue-50 text-blue-700 border-blue-200';
                                } elseif (strpos($log['action'], 'PRICING') !== false || strpos($log['action'], 'RATES') !== false) {
                                    $badgeStyle = 'bg-amber-50 text-amber-800 border-amber-200';
                                } elseif (strpos($log['action'], 'ARCHIVE') !== false) {
                                    $badgeStyle = 'bg-rose-50 text-rose-700 border-rose-200';
                                } elseif (strpos($log['action'], 'CAPACITY') !== false) {
                                    $badgeStyle = 'bg-emerald-50 text-emerald-800 border-emerald-200';
                                }
                                ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-2.5 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                                        <?= date('M d, Y · h:i A', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-brandNavy whitespace-nowrap">
                                        <i class="fa-regular fa-user text-slate-400 mr-1 text-[10px]"></i>
                                        <?= htmlspecialchars($log['admin_username'] ?? ('ID #' . $log['admin_id'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-mono font-bold border <?= $badgeStyle ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 text-[11px]">
                                        <?= htmlspecialchars($log['details'] ?? '—') ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                                        <?= htmlspecialchars($log['ip_address'] ?? '127.0.0.1') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Admin Sidebar Toggle Logic -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('adminSidebar');
        const desktopToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');

        // Restore saved desktop state from localStorage
        if (window.innerWidth >= 768) {
            const isCollapsed = localStorage.getItem('yocor_admin_sidebar_collapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }

        // Desktop toggle handler
        if (desktopToggle && sidebar) {
            desktopToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const isNowCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('yocor_admin_sidebar_collapsed', isNowCollapsed);
            });
        }

        // Mobile toggle handler (hide/show on smaller screens)
        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-hidden');
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>