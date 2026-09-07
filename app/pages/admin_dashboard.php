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

// Handle capacity update
// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['new_status'] ?? '');
    $allowed_statuses = ['pending', 'dispatched', 'delivered', 'cancelled'];
    
    if ($booking_id && in_array($new_status, $allowed_statuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE bookings SET status = :status WHERE id = :id");
            $stmt->execute(['status' => $new_status, 'id' => $booking_id]);
            $updateSuccess = "Booking #{$booking_id} status updated to " . ucfirst($new_status) . "!";
        } catch (PDOException $e) {
            $error = 'Failed to update status: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid booking ID or status selected.';
    }
}

// Handle deleting delivered booking
$deleteSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_capacity'])) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $new_capacity = filter_input(INPUT_POST, 'new_capacity', FILTER_VALIDATE_INT);
    
    // Validate
    if ($service_id && $new_capacity !== false && $new_capacity >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE services SET capacity = :cap WHERE id = :id");
            $stmt->execute(['cap' => $new_capacity, 'id' => $service_id]);
            $capacitySuccess = true;
        } catch (PDOException $e) {
            $error = 'Failed to update capacity: ' . $e->getMessage();
        }
    }
}

// Handle regional transit pricing updates
$rateSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_regional_rates'])) {
    $rates_input = $_POST['rates'] ?? [];
    try {
        $stmt = $pdo->prepare("UPDATE regional_rates SET fee = :fee WHERE route_key = :route_key");
        foreach ($rates_input as $key => $fee) {
            $feeFloat = filter_var($fee, FILTER_VALIDATE_FLOAT);
            if ($feeFloat !== false && $feeFloat >= 0) {
                $stmt->execute(['fee' => $feeFloat, 'route_key' => $key]);
            }
        }
        $rateSuccess = "Regional island transit fees successfully updated!";
    } catch (PDOException $e) {
        $error = 'Failed to update regional rates: ' . $e->getMessage();
    }
}

// Handle service base price and price per kg update
$servicePricingSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service_pricing'])) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $base_price = filter_input(INPUT_POST, 'base_price', FILTER_VALIDATE_FLOAT);
    $price_per_kg = filter_input(INPUT_POST, 'price_per_kg', FILTER_VALIDATE_FLOAT);
    
    if ($service_id && $base_price !== false && $base_price >= 0 && $price_per_kg !== false && $price_per_kg >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE services SET base_price = :base_price, price_per_kg = :price_per_kg WHERE id = :id");
            $stmt->execute([
                'base_price' => $base_price,
                'price_per_kg' => $price_per_kg,
                'id' => $service_id
            ]);
            $servicePricingSuccess = true;
        } catch (PDOException $e) {
            $error = 'Failed to update service pricing: ' . $e->getMessage();
        }
    } else {
        $error = 'Invalid service price values provided.';
    }
}

// Fetch all bookings with user and service info
$bookings = $pdo->query("
    SELECT b.*, u.username, s.name AS service_name 
    FROM bookings b 
    JOIN user u ON b.user_id = u.id 
    JOIN services s ON b.service_id = s.id 
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all services for capacity and rate management
$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all regional island route rates
$regionalRatesList = $pdo->query("SELECT * FROM regional_rates ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Admin Dashboard | YOCOR Express";
$activeNav = "admin";
include __DIR__ . '/../includes/header.php'; 
?>

<style>
    .admin-sidebar {
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    @media (min-width: 768px) {
        .admin-sidebar.collapsed {
            width: 4.75rem !important;
        }
        .admin-sidebar.collapsed .admin-sidebar-text,
        .admin-sidebar.collapsed nav span {
            display: none !important;
        }
        .admin-sidebar.collapsed nav a {
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

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] bg-slate-50">

    <!-- Responsive Collapsible Admin Sidebar -->
    <aside id="adminSidebar" class="admin-sidebar w-full md:w-64 bg-white border-r border-slate-200 p-4 sm:p-5 md:py-8 flex-shrink-0">
        <div class="mb-5 pb-4 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-brandNavy text-white flex items-center justify-center font-bold text-lg shadow flex-shrink-0">
                    <i class="fa-solid fa-gauge-high text-brandOrange"></i>
                </div>
                <div class="admin-sidebar-text whitespace-nowrap overflow-hidden">
                    <h3 class="font-black text-brandNavy text-sm">Admin Control</h3>
                    <p class="text-[11px] text-slate-500 font-medium truncate">User: <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
            <!-- Desktop Sidebar Collapse Toggle Button -->
            <button id="sidebarToggle" class="hidden md:flex p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-brandNavy transition-colors flex-shrink-0" title="Toggle Sidebar Width">
                <i id="sidebarToggleIcon" class="fa-solid fa-chevron-left text-xs transition-transform"></i>
            </button>
        </div>

        <nav class="space-y-1.5 text-xs font-bold">
            <a href="#overview" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-brandNavy/5 text-brandNavy hover:bg-brandNavy/10 transition-colors" title="Overview & Stats">
                <i class="fa-solid fa-chart-pie text-brandOrange text-sm w-4 text-center flex-shrink-0"></i>
                <span class="truncate">Overview & Stats</span>
            </a>
            <a href="#all-bookings" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Manage Bookings">
                <i class="fa-solid fa-boxes-packing text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                <span class="truncate">Manage Bookings</span>
            </a>
            <a href="#capacities" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Service Capacities">
                <i class="fa-solid fa-cubes text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                <span class="truncate">Service Capacities</span>
            </a>
            <a href="#regional-rates" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Island Transit Rates">
                <i class="fa-solid fa-coins text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                <span class="truncate">Island Transit Rates</span>
            </a>
            <a href="tracking.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Live Tracker">
                <i class="fa-solid fa-magnifying-glass-location text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                <span class="truncate">Live Tracker</span>
            </a>
            
            <div class="pt-4 border-t border-slate-100 mt-4">
                <a href="logout.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition-colors" title="Sign Out">
                    <i class="fa-solid fa-right-from-bracket text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Sign Out</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Admin Main Content Area -->
    <main class="flex-grow p-3 sm:p-5 md:p-6 max-w-7xl">
        
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
        
        <?php if (isset($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i> 
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200/90 p-4 hover:border-slate-300 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-orange-50 text-brandOrange flex items-center justify-center text-lg">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Waybills</p>
                        <p class="text-xl font-black text-brandNavy font-heading"><?= count($bookings) ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200/90 p-4 hover:border-slate-300 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-truck-ramp-box"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Available Capacity</p>
                        <p class="text-xl font-black text-emerald-600 font-heading">
                            <?php 
                            $totalCap = array_sum(array_column($services, 'capacity'));
                            echo $totalCap;
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200/90 p-4 hover:border-slate-300 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Pending Dispatches</p>
                        <p class="text-xl font-black text-amber-600 font-heading">
                            <?php 
                            $pending = array_filter($bookings, function($b) { return $b['status'] === 'pending'; });
                            echo count($pending);
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Bookings Table with Quick Status Form -->
        <div id="all-bookings" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-1.5">
                <div>
                    <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                        <i class="fa-solid fa-table-list text-brandOrange"></i>
                        <span>Customer Shipments & Status Controller</span>
                    </h2>
                    <p class="text-[11px] text-slate-500">Update parcel progress directly using the inline dropdown.</p>
                </div>
                <span class="text-[11px] font-semibold text-slate-500">Showing <?= count($bookings) ?> record(s)</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider">
                        <tr>
                            <th class="px-4 py-2.5">ID</th>
                            <th class="px-4 py-2.5">Customer</th>
                            <th class="px-4 py-2.5">Service</th>
                            <th class="px-4 py-2.5">Addresses</th>
                            <th class="px-4 py-2.5">Weight</th>
                            <th class="px-4 py-2.5">Cost</th>
                            <th class="px-4 py-2.5 min-w-[170px]">Update Status</th>
                            <th class="px-4 py-2.5">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-slate-500">
                                    <div class="text-2xl mb-1 text-slate-300"><i class="fa-regular fa-box-open"></i></div>
                                    No customer bookings yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-2.5 font-mono font-bold text-brandNavy">
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[11px]">
                                            <?= htmlspecialchars($booking['tracking_code'] ?? ('#' . $booking['id'])) ?>
                                        </span>
                                        <span class="block text-[9px] text-slate-400 font-normal">#<?= $booking['id'] ?></span>
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-slate-800">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fa-regular fa-user text-slate-400 text-xs"></i>
                                            <?= htmlspecialchars($booking['username']) ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="font-bold text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded text-[11px]">
                                            <?= htmlspecialchars($booking['service_name']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate space-y-0.5">
                                        <?php if (!empty($booking['pickup_region']) && !empty($booking['delivery_region'])): ?>
                                            <div class="mb-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-orange-100/70 text-brandOrange border border-orange-200">
                                                    <i class="fa-solid fa-map-pin text-[9px]"></i>
                                                    <?= htmlspecialchars($booking['pickup_region']) ?> &rarr; <?= htmlspecialchars($booking['delivery_region']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="truncate text-[11px]"><strong class="text-slate-700">From:</strong> <?= htmlspecialchars($booking['pickup_address']) ?></div>
                                        <div class="truncate text-[11px]"><strong class="text-slate-700">To:</strong> <?= htmlspecialchars($booking['delivery_address']) ?></div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 font-semibold">
                                        <?= $booking['weight'] ?> kg
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-brandOrange">
                                        ₱<?= number_format((float)($booking['total_cost'] ?? 0), 2) ?>
                                    </td>
                                    <!-- Interactive Status Update Form -->
                                    <td class="px-4 py-2.5">
                                        <form method="POST" action="" class="flex items-center gap-1">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            <select name="new_status" class="px-2 py-1 text-[11px] font-bold rounded-md border border-slate-300 bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                                                <option value="pending" <?= $booking['status'] === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                                <option value="dispatched" <?= $booking['status'] === 'dispatched' ? 'selected' : '' ?>>🚚 Dispatched</option>
                                                <option value="delivered" <?= $booking['status'] === 'delivered' ? 'selected' : '' ?>>✅ Delivered</option>
                                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" 
                                                    class="bg-brandNavy hover:bg-slate-900 text-white font-bold px-2 py-1 rounded-md text-[11px] transition-colors shadow-sm"
                                                    title="Save Status">
                                                Save
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="flex items-center gap-2">
                                            <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" 
                                               class="inline-flex items-center gap-1 text-[11px] text-brandOrange hover:text-orange-700 font-bold hover:underline"
                                               target="_blank" title="Live Tracking">
                                                <span>Track</span>
                                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                            </a>

                                            <?php if ($booking['status'] === 'delivered'): ?>
                                                <span class="text-slate-300">|</span>
                                                <form method="POST" action="" onsubmit="return confirm('Are you sure you want to permanently delete this delivered parcel record (#<?= htmlspecialchars($booking['tracking_code'] ?? $booking['id']) ?>)? This cannot be undone.');" class="inline">
                                                    <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                                    <button type="submit" name="delete_booking" 
                                                            class="inline-flex items-center gap-1 text-[11px] text-rose-600 hover:text-rose-800 font-bold hover:underline"
                                                            title="Delete Delivered Parcel">
                                                        <i class="fa-regular fa-trash-can text-[10px]"></i>
                                                        <span>Delete</span>
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