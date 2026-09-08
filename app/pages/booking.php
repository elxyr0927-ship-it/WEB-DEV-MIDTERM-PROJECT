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
require_once __DIR__ . '/../database/validation.php';

// Get any error messages from session (set by process_booking.php)
$errors = $_SESSION['booking_errors'] ?? [];
unset($_SESSION['booking_errors']); // Clear after reading

// Get success message if any
$success = $_SESSION['booking_success'] ?? false;
$lastTrackingCode = $_SESSION['last_tracking_code'] ?? '';
unset($_SESSION['booking_success'], $_SESSION['last_tracking_code']);

// Fetch available services (only active ones with capacity > 0)
$pdo = getConnection();
$stmt = $pdo->prepare("SELECT id, name, description, capacity, base_price, price_per_kg FROM services WHERE capacity > 0 AND is_active = 1 ORDER BY id");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-fill weight or service from query params if passed from calculator
$prefillWeight = isset($_GET['weight']) ? (float)$_GET['weight'] : (isset($_POST['weight']) ? (float)$_POST['weight'] : 1.0);
$prefillService = $_GET['service'] ?? '';
$prefillTier = $_GET['tier'] ?? '';
$prefillOriginRegion = $_GET['origin_region'] ?? ($_POST['pickup_region'] ?? 'Luzon');
$prefillDestRegion = $_GET['dest_region'] ?? ($_POST['delivery_region'] ?? 'Luzon');
$prefillPickup = $_GET['pickup'] ?? ($_POST['pickup_address'] ?? '');
$prefillDelivery = $_GET['delivery'] ?? ($_POST['delivery_address'] ?? '');

// Fetch dynamic regional distance rates from database
$regionalRates = getRegionalRates($pdo);

// Map tier or service name to service ID
$prefillServiceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : (isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0);
if (!$prefillServiceId && !empty($services)) {
    if ($prefillService === 'parcel' || $prefillTier === 'priority') {
        $prefillServiceId = $services[0]['id']; // Parcel Express
    } elseif ($prefillTier === 'sameday') {
        foreach ($services as $srv) {
            if (stripos($srv['name'], 'cold') !== false || stripos($srv['name'], 'same') !== false) {
                $prefillServiceId = $srv['id'];
                break;
            }
        }
    } elseif ($prefillTier === 'standard') {
        $prefillServiceId = $services[0]['id'];
    }
}

// Set page title and active nav for header
$pageTitle = "Book Delivery | YOCOR Express";
$activeNav = "booking";

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-8 bg-slate-50">
    <div class="max-w-3xl mx-auto px-4 sm:px-6">
        
        <!-- Header -->
        <div class="text-center max-w-2xl mx-auto mb-6 space-y-1">
            <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-orange-100 text-brandOrange font-bold text-[11px] uppercase tracking-widest">
                <i class="fa-solid fa-truck-pickup text-xs"></i> Dispatch Booking
            </div>
            <h1 class="text-2xl font-black text-brandNavy">Book a Shipment Pickup</h1>
            <p class="text-slate-600 text-xs">Schedule a direct courier pickup at your home, office, or warehouse.</p>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl mb-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-500 text-white flex items-center justify-center font-bold text-sm flex-shrink-0 shadow">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-emerald-950">Booking Confirmed!</h3>
                        <p class="text-[11px] text-emerald-700 mt-0.5">Your pickup has been successfully scheduled.</p>
                        <?php if (!empty($lastTrackingCode)): ?>
                            <div class="mt-1.5 inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono font-bold text-[11px]">
                                <span>Booking ID:</span>
                                <span class="tracking-wider"><?= htmlspecialchars($lastTrackingCode) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 pt-1 sm:pt-0">
                    <?php if (!empty($lastTrackingCode)): ?>
                        <a href="tracking.php?id=<?= urlencode($lastTrackingCode) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold rounded-lg transition-colors shadow-sm">
                            <i class="fa-solid fa-satellite-dish text-[10px]"></i> Track Parcel
                        </a>
                    <?php endif; ?>
                    <a href="customer_dashboard.php" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white hover:bg-emerald-100 border border-emerald-300 text-emerald-900 text-[11px] font-bold rounded-lg transition-colors">
                        My Dashboard
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-3.5 py-2.5 rounded-xl mb-4 text-xs">
                <ul class="list-disc list-inside space-y-0.5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- No Services Available -->
        <?php if (empty($services)): ?>
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-5 py-6 rounded-xl text-center">
                <div class="text-3xl mb-2">😅</div>
                <h3 class="font-bold text-sm">No Services Available</h3>
                <p class="text-xs mt-1">All our services are currently fully booked. Please check back later.</p>
                <a href="customer_dashboard.php" class="inline-block mt-3 text-brandOrange font-bold text-xs hover:underline">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Dashboard
                </a>
            </div>
        <?php else: ?>

            <!-- Booking Form Card -->
            <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-sm border border-slate-200 relative">
                <form action="process_booking.php" method="POST" class="space-y-5">
                    <?= csrfField() ?>
                    
                    <!-- Step 1: Sender & Pickup -->
                    <div>
                        <h3 class="text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2.5 flex items-center gap-2 border-b border-slate-100 pb-1.5">
                            <span class="w-5 h-5 rounded-full bg-brandOrange text-white text-[10px] flex items-center justify-center font-bold">1</span>
                            Sender & Pickup Details
                        </h3>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Pickup Island / Region <span class="text-red-500">*</span></label>
                                <select name="pickup_region" id="pickup_region" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs font-bold text-brandNavy">
                                    <option value="Luzon" <?= ($prefillOriginRegion === 'Luzon') ? 'selected' : '' ?>>Luzon (NCR / Provincial)</option>
                                    <option value="Visayas" <?= ($prefillOriginRegion === 'Visayas') ? 'selected' : '' ?>>Visayas (Cebu / Iloilo)</option>
                                    <option value="Mindanao" <?= ($prefillOriginRegion === 'Mindanao') ? 'selected' : '' ?>>Mindanao (Davao / CDO)</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Detailed Pickup Address <span class="text-red-500">*</span></label>
                                <input type="text" name="pickup_address" required 
                                       placeholder="Street address, building name, unit number, city"
                                       value="<?= htmlspecialchars($prefillPickup) ?>"
                                       class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Recipient & Destination -->
                    <div>
                        <h3 class="text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2.5 flex items-center gap-2 border-b border-slate-100 pb-1.5">
                            <span class="w-5 h-5 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">2</span>
                            Recipient & Delivery Details
                        </h3>
                        <div class="grid sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Delivery Island / Region <span class="text-red-500">*</span></label>
                                <select name="delivery_region" id="delivery_region" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs font-bold text-brandNavy">
                                    <option value="Luzon" <?= ($prefillDestRegion === 'Luzon') ? 'selected' : '' ?>>Luzon (NCR / Provincial)</option>
                                    <option value="Visayas" <?= ($prefillDestRegion === 'Visayas') ? 'selected' : '' ?>>Visayas (Cebu / Iloilo)</option>
                                    <option value="Mindanao" <?= ($prefillDestRegion === 'Mindanao') ? 'selected' : '' ?>>Mindanao (Davao / CDO)</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Detailed Delivery Address <span class="text-red-500">*</span></label>
                                <input type="text" name="delivery_address" required 
                                       placeholder="Destination address, city, postal code"
                                       value="<?= htmlspecialchars($prefillDelivery) ?>"
                                       class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs">
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Package Specifications -->
                    <div>
                        <h3 class="text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2.5 flex items-center gap-2 border-b border-slate-100 pb-1.5">
                            <span class="w-5 h-5 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">3</span>
                            Package Details & Service
                        </h3>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Select Service <span class="text-red-500">*</span></label>
                                <select name="service_id" id="service_id" required class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs">
                                    <option value="">-- Choose a service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service['id'] ?>" 
                                            <?= ($prefillServiceId == $service['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($service['name']) ?> 
                                            (₱<?= number_format($service['base_price'], 2) ?> base + ₱<?= number_format($service['price_per_kg'], 2) ?>/kg)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-[10px] text-slate-500 mt-1">Available slots remaining shown per service.</p>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 mb-1">Estimated Weight (kg) <span class="text-red-500">*</span></label>
                                <input type="number" name="weight" id="weight" required min="0.1" step="0.1" 
                                       value="<?= htmlspecialchars($prefillWeight) ?>"
                                       class="w-full px-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs">
                            </div>
                        </div>

                        <!-- Live Cost Estimate Box -->
                        <div class="mt-4 p-3.5 rounded-xl bg-gradient-to-r from-orange-50 via-slate-50 to-orange-50 border border-orange-200/80 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-brandOrange text-white flex items-center justify-center text-sm font-bold shadow-sm">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                <div>
                                    <p class="font-extrabold text-brandNavy text-xs">Estimated Delivery Cost</p>
                                    <p class="text-[10px] text-slate-500" id="cost-breakdown-text">Select a service to view estimated price.</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-bold text-slate-400 uppercase block">Total to Pay</span>
                                <span id="live-cost-estimate" class="text-lg sm:text-xl font-black text-brandOrange">₱0.00 <span class="text-[10px] font-normal text-slate-500">PHP</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3 border-t border-slate-200">
                        <button type="submit" 
                                class="w-full bg-brandOrange hover:bg-orange-600 text-white font-bold py-2.5 px-5 rounded-lg transition-all shadow-md brand-glow-orange text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                            <i class="fa-solid fa-box-open"></i>
                            Confirm & Schedule Pickup
                        </button>
                    </div>

                </form>
            </div>

            <!-- Client-Side Live Price Calculation -->
            <script>
                const serviceRates = {
                    <?php foreach ($services as $s): ?>
                        <?= $s['id'] ?>: { base: <?= (float)$s['base_price'] ?>, perKg: <?= (float)$s['price_per_kg'] ?>, name: '<?= addslashes($s['name']) ?>' },
                    <?php endforeach; ?>
                };

                const regionalRates = <?= json_encode($regionalRates) ?>;

                const pickupRegionSelect = document.getElementById('pickup_region');
                const deliveryRegionSelect = document.getElementById('delivery_region');
                const serviceSelect = document.getElementById('service_id');
                const weightInput = document.getElementById('weight');
                const costDisplay = document.getElementById('live-cost-estimate');
                const breakdownText = document.getElementById('cost-breakdown-text');
                const totalCostInput = document.getElementById('total_cost');

                function getRegionalFee(origin, dest) {
                    const o = (origin || '').toLowerCase();
                    const d = (dest || '').toLowerCase();
                    if (o === d) return { fee: regionalRates.intra_island ?? 0, type: 'Same-Island Transit' };
                    if ((o === 'luzon' && d === 'mindanao') || (o === 'mindanao' && d === 'luzon')) {
                        return { fee: regionalRates.cross_island ?? 120, type: 'Cross-Country Transit (Air/Sea)' };
                    }
                    return { fee: regionalRates.inter_island ?? 60, type: 'Inter-Island Transit' };
                }

                function calculateBookingCost() {
                    const sId = serviceSelect ? serviceSelect.value : '';
                    const weight = parseFloat(weightInput ? weightInput.value : 1.0) || 1.0;
                    const oReg = pickupRegionSelect ? pickupRegionSelect.value : 'Luzon';
                    const dReg = deliveryRegionSelect ? deliveryRegionSelect.value : 'Luzon';

                    if (sId && serviceRates[sId]) {
                        const { base, perKg, name } = serviceRates[sId];
                        const extraWeight = Math.max(0, weight - 1);
                        const weightFee = base + (extraWeight * perKg);
                        const { fee: distanceFee, type: routeLabel } = getRegionalFee(oReg, dReg);
                        const total = weightFee + distanceFee;
                        
                        costDisplay.innerHTML = `₱${total.toFixed(2)} <span class="text-[10px] font-normal text-slate-500">PHP</span>`;
                        totalCostInput.value = total.toFixed(2);

                        let breakdown = `₱${base.toFixed(2)} base fee + (₱${perKg.toFixed(2)} × ${extraWeight.toFixed(1)}kg extra)`;
                        if (distanceFee > 0) {
                            breakdown += ` + ₱${distanceFee.toFixed(2)} ${routeLabel}`;
                        } else {
                            breakdown += ` + ₱0.00 (Local ${oReg} route)`;
                        }
                        breakdownText.textContent = breakdown;
                    } else {
                        costDisplay.innerHTML = `₱0.00 <span class="text-[10px] font-normal text-slate-500">PHP</span>`;
                        totalCostInput.value = "0.00";
                        breakdownText.textContent = 'Select a service to view estimated price.';
                    }
                }

                if (serviceSelect && weightInput) {
                    serviceSelect.addEventListener('change', calculateBookingCost);
                    weightInput.addEventListener('input', calculateBookingCost);
                    if (pickupRegionSelect) pickupRegionSelect.addEventListener('change', calculateBookingCost);
                    if (deliveryRegionSelect) deliveryRegionSelect.addEventListener('change', calculateBookingCost);
                    calculateBookingCost(); // Run on page load
                }
            </script>

        <?php endif; ?>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>