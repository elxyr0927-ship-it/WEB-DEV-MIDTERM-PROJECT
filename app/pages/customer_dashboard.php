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
    // Use header.php but override activeNav
    $pageTitle = "My Orders | YOCOR Express";
    $activeNav = "booking"; // highlight booking in nav
    include __DIR__ . '/../includes/header.php'; 
    ?>

    <main class="flex-grow py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Welcome Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-brandNavy">
                        Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </h1>
                    <p class="text-slate-600 text-sm">Here are your recent shipments and deliveries.</p>
                </div>
                <a href="booking.php" 
                   class="bg-brandOrange hover:bg-orange-600 text-white font-bold px-6 py-3 rounded-xl transition-all shadow-lg brand-glow-orange inline-flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-plus"></i> Book New Delivery
                </a>
            </div>

            <!-- Success Message (from booking) -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'booking_success'): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    <i class="fa-solid fa-circle-check mr-2"></i> 
                    Booking successful! Your shipment has been scheduled.
                </div>
            <?php endif; ?>

            <!-- Bookings Table -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                <?php if (empty($bookings)): ?>
                    <div class="p-12 text-center">
                        <div class="text-6xl text-slate-300 mb-4">
                            <i class="fa-regular fa-receipt"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-600">No Bookings Yet</h3>
                        <p class="text-slate-500 text-sm mt-2">You haven't made any bookings. Start shipping with YOCOR Express!</p>
                        <a href="booking.php" class="inline-block mt-4 bg-brandOrange text-white font-bold px-6 py-3 rounded-xl hover:bg-orange-600 transition-all">
                            Book Your First Delivery
                        </a>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Booking ID</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Service</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Pickup</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Delivery</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Weight</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach ($bookings as $booking): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 font-mono text-xs font-bold text-brandNavy">
                                            #<?= $booking['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-slate-800">
                                            <?= htmlspecialchars($booking['service_name']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 text-xs">
                                            <?= htmlspecialchars($booking['pickup_address']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 text-xs">
                                            <?= htmlspecialchars($booking['delivery_address']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600 text-xs">
                                            <?= $booking['weight'] ?> kg
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            $statusColors = [
                                                'pending' => 'bg-yellow-100 text-yellow-800',
                                                'dispatched' => 'bg-blue-100 text-blue-800',
                                                'delivered' => 'bg-green-100 text-green-800',
                                                'cancelled' => 'bg-red-100 text-red-800'
                                            ];
                                            $color = $statusColors[$booking['status']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase <?= $color ?>">
                                                <?= htmlspecialchars($booking['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-slate-500 text-xs">
                                            <?= date('M d, Y', strtotime($booking['created_at'])) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>