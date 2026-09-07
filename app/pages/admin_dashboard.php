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
$updateSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_capacity'])) {
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $new_capacity = filter_input(INPUT_POST, 'new_capacity', FILTER_VALIDATE_INT);
    
    // Validate
    if ($service_id && $new_capacity !== false && $new_capacity >= 0) {
        try {
            $stmt = $pdo->prepare("UPDATE services SET capacity = :cap WHERE id = :id");
            $stmt->execute(['cap' => $new_capacity, 'id' => $service_id]);
            $updateSuccess = true;
        } catch (PDOException $e) {
            $error = 'Failed to update capacity: ' . $e->getMessage();
        }
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

// Fetch all services for capacity management
$services = $pdo->query("SELECT * FROM services ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>


    
    <?php 
    $pageTitle = "Admin Dashboard | YOCOR Express";
    $activeNav = "admin";
    include __DIR__ . '/../includes/header.php'; 
    ?>

    <main class="flex-grow py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <!-- Admin Header -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-black text-brandNavy">
                        Admin Dashboard
                    </h1>
                    <p class="text-slate-600 text-sm">
                        <i class="fa-regular fa-user text-brandOrange"></i> 
                        Logged in as <?= htmlspecialchars($_SESSION['username']) ?>
                    </p>
                </div>
                <div class="flex gap-3">
                    <span class="bg-brandNavy text-white px-4 py-2 rounded-xl text-sm font-bold">
                        <i class="fa-regular fa-calendar"></i> <?= date('F d, Y') ?>
                    </span>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($updateSuccess): ?>
                <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    <i class="fa-solid fa-circle-check mr-2"></i> 
                    Service capacity updated successfully!
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                    <i class="fa-solid fa-circle-exclamation mr-2"></i> 
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-brandOrange/10 text-brandOrange flex items-center justify-center text-2xl">
                            <i class="fa-regular fa-receipt"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Bookings</p>
                            <p class="text-2xl font-black text-brandNavy"><?= count($bookings) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                            <i class="fa-regular fa-circle-check"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Delivered</p>
                            <p class="text-2xl font-black text-brandNavy">
                                <?php 
                                $delivered = array_filter($bookings, function($b) { return $b['status'] === 'delivered'; });
                                echo count($delivered);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-yellow-50 text-yellow-600 flex items-center justify-center text-2xl">
                            <i class="fa-regular fa-clock"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Pending</p>
                            <p class="text-2xl font-black text-brandNavy">
                                <?php 
                                $pending = array_filter($bookings, function($b) { return $b['status'] === 'pending'; });
                                echo count($pending);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Bookings -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="font-bold text-brandNavy text-lg">
                        <i class="fa-regular fa-receipt text-brandOrange mr-2"></i>
                        All Bookings
                    </h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50/50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Pickup</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Delivery</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Weight</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (empty($bookings)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-slate-500">
                                        No bookings yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr class="hover:bg-slate-50 transition-colors">
                                        <td class="px-6 py-4 font-mono text-xs font-bold text-brandNavy">
                                            #<?= $booking['id'] ?>
                                        </td>
                                        <td class="px-6 py-4 font-bold text-slate-800">
                                            <?= htmlspecialchars($booking['username']) ?>
                                        </td>
                                        <td class="px-6 py-4 text-slate-600">
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Manage Service Capacity -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50">
                    <h2 class="font-bold text-brandNavy text-lg">
                        <i class="fa-regular fa-cubes text-brandOrange mr-2"></i>
                        Manage Service Capacities
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Update available slots for each service. Customers can only book if capacity > 0.</p>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($services as $service): ?>
                            <div class="border border-slate-200 rounded-xl p-6 hover:shadow-lg transition-shadow">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-bold text-brandNavy"><?= htmlspecialchars($service['name']) ?></h3>
                                    <?php if ($service['capacity'] > 0): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                                            In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">
                                            Out of Stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <p class="text-sm text-slate-600 mb-4">
                                    <?= htmlspecialchars($service['description']) ?>
                                </p>
                                
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-xs text-slate-500">Current Capacity</p>
                                        <p class="text-2xl font-black text-brandNavy"><?= $service['capacity'] ?></p>
                                    </div>
                                    
                                    <form method="POST" class="flex items-center gap-2">
                                        <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                        <input type="number" name="new_capacity" min="0" 
                                               value="<?= $service['capacity'] ?>"
                                               class="w-20 px-3 py-2 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-bold text-center">
                                        <button type="submit" name="update_capacity"
                                                class="bg-brandNavy hover:bg-slate-900 text-white font-bold px-4 py-2 rounded-xl transition-all text-xs">
                                            Update
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>