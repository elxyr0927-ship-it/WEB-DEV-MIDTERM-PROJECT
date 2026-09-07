<?php
// Start session and require login
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// If admin tries to access, redirect to admin dashboard
if ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';

$pdo = getConnection();

// Fetch user's bookings
$stmt = $pdo->prepare("
    SELECT b.*, s.name AS service_name 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    WHERE b.user_id = :user_id 
    ORDER BY b.created_at DESC
");
$stmt->execute(['user_id' => $_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
    
    <?php 
$pageTitle = "My Orders | YOCOR Express";
$activeNav = "customer_dashboard";
include __DIR__ . '/../includes/header.php'; 
?>

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] bg-slate-50">

    <!-- Simple Customer Sidebar -->
    <aside class="w-full md:w-64 bg-white border-r border-slate-200 p-5 md:py-8 flex-shrink-0">
        <div class="mb-6 pb-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-orange-50 text-brandOrange flex items-center justify-center font-bold text-lg shadow-sm">
                <i class="fa-solid fa-box"></i>
            </div>
            <div>
                <h3 class="font-black text-brandNavy text-sm">Customer Hub</h3>
                <p class="text-xs text-slate-500 font-medium">Hello, <?= htmlspecialchars($_SESSION['username']) ?></p>
            </div>
        </div>

        <nav class="space-y-1.5 text-xs font-bold">
            <a href="customer_dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl bg-brandOrange/10 text-brandOrange hover:bg-brandOrange/15 transition-colors">
                <i class="fa-solid fa-boxes-stacked text-sm w-4 text-center"></i>
                <span>My Shipments</span>
            </a>
            <a href="booking.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-truck-pickup text-slate-400 text-sm w-4 text-center"></i>
                <span>Book Pickup</span>
            </a>
            <a href="tracking.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-magnifying-glass-location text-slate-400 text-sm w-4 text-center"></i>
                <span>Track Parcel</span>
            </a>
            <a href="quote.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                <i class="fa-solid fa-calculator text-slate-400 text-sm w-4 text-center"></i>
                <span>Rate Calculator</span>
            </a>
            
            <div class="pt-4 border-t border-slate-100 mt-4">
                <a href="logout.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-red-600 hover:bg-red-50 transition-colors">
                    <i class="fa-solid fa-right-from-bracket text-sm w-4 text-center"></i>
                    <span>Sign Out</span>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Customer Main Orders Area -->
    <main class="flex-grow p-3 sm:p-5 md:p-6 max-w-6xl">
        
        <!-- Welcome Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading">
                    Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
                </h1>
                <p class="text-slate-600 text-xs mt-0.5">Review live delivery progress and tracking codes for all your shipments.</p>
            </div>
            <a href="booking.php" 
               class="bg-brandOrange hover:bg-orange-600 text-white font-bold px-4 py-2 rounded-lg transition-all shadow-sm brand-glow-orange inline-flex items-center gap-1.5 text-xs">
                <i class="fa-solid fa-plus text-[11px]"></i>
                <span>Book Shipment</span>
            </a>
        </div>

        <!-- Success Message (from booking) -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'booking_success'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span>Booking successful! Your shipment has been scheduled with our courier.</span>
            </div>
        <?php endif; ?>

        <!-- Customer Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 mb-5">
            <div class="bg-white rounded-xl p-3.5 border border-slate-200/90 shadow-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Orders</p>
                <p class="text-xl font-black text-brandNavy mt-0.5 font-heading"><?= count($bookings) ?></p>
            </div>
            <div class="bg-white rounded-xl p-3.5 border border-slate-200/90 shadow-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">In Progress</p>
                <p class="text-xl font-black text-brandOrange mt-0.5 font-heading">
                    <?= count(array_filter($bookings, function($b) { return in_array($b['status'], ['pending', 'dispatched']); })) ?>
                </p>
            </div>
            <div class="bg-white rounded-xl p-3.5 border border-slate-200/90 shadow-sm">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Delivered</p>
                <p class="text-xl font-black text-emerald-600 mt-0.5 font-heading">
                    <?= count(array_filter($bookings, function($b) { return $b['status'] === 'delivered'; })) ?>
                </p>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-slate-200 bg-slate-50/60 flex items-center justify-between">
                <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-brandOrange"></i>
                    <span>Order History & Waybills</span>
                </h2>
                <span class="text-[11px] text-slate-500 font-semibold"><?= count($bookings) ?> order(s)</span>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="p-8 text-center">
                    <div class="w-12 h-12 rounded-xl bg-orange-50 text-brandOrange flex items-center justify-center text-xl mx-auto mb-2.5">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-700">No Bookings Yet</h3>
                    <p class="text-slate-500 text-xs mt-1 max-w-sm mx-auto">You haven't scheduled any courier pickups yet. Start sending parcels now!</p>
                    <a href="booking.php" class="inline-flex items-center gap-1.5 mt-3.5 bg-brandOrange text-white font-bold px-4 py-2 rounded-lg hover:bg-orange-600 transition-all text-xs shadow-sm">
                        <i class="fa-solid fa-plus text-[10px]"></i> Schedule First Delivery
                    </a>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider">
                            <tr>
                                <th class="px-4 py-2.5">Booking ID</th>
                                <th class="px-4 py-2.5">Service</th>
                                <th class="px-4 py-2.5">Destination</th>
                                <th class="px-4 py-2.5">Weight</th>
                                <th class="px-4 py-2.5">Cost</th>
                                <th class="px-4 py-2.5">Status</th>
                                <th class="px-4 py-2.5">Date</th>
                                <th class="px-4 py-2.5 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 font-medium">
                            <?php foreach ($bookings as $booking): ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-2.5 font-mono font-bold text-brandNavy">
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[11px]">
                                            <?= htmlspecialchars($booking['tracking_code'] ?? ('#' . $booking['id'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-slate-800">
                                        <?= htmlspecialchars($booking['service_name']) ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-600 max-w-xs truncate text-[11px]">
                                        <?php if (!empty($booking['pickup_region']) && !empty($booking['delivery_region'])): ?>
                                            <div class="mb-1">
                                                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-orange-100/70 text-brandOrange border border-orange-200">
                                                    <i class="fa-solid fa-map-pin text-[9px]"></i>
                                                    <?= htmlspecialchars($booking['pickup_region']) ?> &rarr; <?= htmlspecialchars($booking['delivery_region']) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="truncate"><strong class="text-slate-700">To:</strong> <?= htmlspecialchars($booking['delivery_address']) ?></div>
                                        <div class="truncate text-slate-400 text-[10px]">From: <?= htmlspecialchars($booking['pickup_address']) ?></div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-700 font-semibold">
                                        <?= $booking['weight'] ?> kg
                                    </td>
                                    <td class="px-4 py-2.5 font-bold text-brandOrange">
                                        ₱<?= number_format((float)($booking['total_cost'] ?? 0), 2) ?>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <?php 
                                        $statusBadges = [
                                            'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'dispatched' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'delivered' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200'
                                        ];
                                        $badge = $statusBadges[$booking['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                        ?>
                                        <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wide border <?= $badge ?>">
                                            <?= htmlspecialchars($booking['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500 text-[10px]">
                                        <?= date('M d, Y', strtotime($booking['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" 
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-orange-50 hover:bg-brandOrange text-brandOrange hover:text-white font-bold text-[11px] transition-colors shadow-sm">
                                            <i class="fa-solid fa-location-crosshairs text-[10px]"></i>
                                            <span>Track</span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>