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

// Get any error messages from session (set by process_booking.php)
$errors = $_SESSION['booking_errors'] ?? [];
unset($_SESSION['booking_errors']); // Clear after reading

// Get success message if any
$success = $_SESSION['booking_success'] ?? false;
unset($_SESSION['booking_success']);

// Fetch available services (only those with capacity > 0)
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT id, name, description, capacity FROM services WHERE capacity > 0 ORDER BY name");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set page title and active nav for header
$pageTitle = "Book Delivery | YOCOR Express";
$activeNav = "booking";

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-12 bg-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-xs uppercase tracking-widest">
                <i class="fa-solid fa-truck-pickup"></i> Dispatch Booking
            </div>
            <h1 class="text-3xl font-black text-brandNavy">Book a Shipment Pickup</h1>
            <p class="text-slate-600 text-sm">Schedule a direct courier pickup at your home, office, or warehouse.</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-6 text-sm">
                <i class="fa-solid fa-circle-check mr-2"></i> 
                Booking successful! Your shipment has been scheduled.
                <a href="customer_dashboard.php" class="font-bold underline hover:no-underline">View your orders</a>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-6 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- No Services Available -->
        <?php if (empty($services)): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-8 rounded-2xl text-center">
                <div class="text-4xl mb-3">😅</div>
                <h3 class="font-bold text-lg">No Services Available</h3>
                <p class="text-sm mt-2">All our services are currently fully booked. Please check back later.</p>
                <a href="customer_dashboard.php" class="inline-block mt-4 text-brandOrange font-bold hover:underline">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>

            <!-- Booking Form Card -->
            <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl border border-slate-200 relative">
                <form action="process_booking.php" method="POST" class="space-y-8">
                    
                    <!-- Step 1: Sender & Pickup -->
                    <div>
                        <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                            <span class="w-6 h-6 rounded-full bg-brandOrange text-white text-xs flex items-center justify-center font-bold">1</span>
                            Sender & Pickup Details
                        </h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1">Complete Pickup Address <span class="text-red-500">*</span></label>
                                <input type="text" name="pickup_address" required 
                                       placeholder="Street address, building name, unit number, city"
                                       value="<?= htmlspecialchars($_POST['pickup_address'] ?? '') ?>"
                                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Recipient & Destination -->
                    <div>
                        <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                            <span class="w-6 h-6 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">2</span>
                            Recipient & Delivery Details
                        </h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 mb-1">Complete Delivery Address <span class="text-red-500">*</span></label>
                                <input type="text" name="delivery_address" required 
                                       placeholder="Destination address, city, postal code"
                                       value="<?= htmlspecialchars($_POST['delivery_address'] ?? '') ?>"
                                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Package Specifications -->
                    <div>
                        <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
                            <span class="w-6 h-6 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">3</span>
                            Package Details & Service
                        </h3>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Select Service <span class="text-red-500">*</span></label>
                                <select name="service_id" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-sm">
                                    <option value="">-- Choose a service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service['id'] ?>" 
                                            <?= (isset($_POST['service_id']) && $_POST['service_id'] == $service['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($service['name']) ?> 
                                            (<?= $service['capacity'] ?> slots left)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-slate-500 mt-1">Only services with available capacity are shown.</p>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1">Estimated Weight (kg) <span class="text-red-500">*</span></label>
                                <input type="number" name="weight" required min="0.1" step="0.5" 
                                       value="<?= htmlspecialchars($_POST['weight'] ?? '1.0') ?>"
                                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 border-t border-slate-200">
                        <button type="submit" 
                                class="w-full bg-brandOrange hover:bg-orange-600 text-white font-extrabold py-4 px-6 rounded-xl transition-all shadow-xl brand-glow-orange text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                            <i class="fa-solid fa-box-open"></i>
                            Confirm & Schedule Pickup
                        </button>
                    </div>

                </form>
            </div>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>