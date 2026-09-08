<?php
// Start session safely and require admin role
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();
$error = '';
$success = '';

// Handle CSRF check on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired security token (CSRF). Please refresh and try again.';
    }
}

// 1. Handle Add New Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service']) && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $basePriceRaw = $_POST['base_price'] ?? '';
    $pricePerKgRaw = $_POST['price_per_kg'] ?? '';
    $capacityRaw = $_POST['capacity'] ?? '';

    $errs = [
        validateRequired($name, 'Service Name'),
        validateStringLength($name, 'Service Name', 100),
        validateRequired($desc, 'Description'),
        validatePositiveNumber($basePriceRaw, 'Base Price', 100000),
        validatePositiveNumber($pricePerKgRaw, 'Price per Kg', 100000),
        validateIntRange($capacityRaw, 'Initial Daily Capacity', 0, 100000)
    ];
    $cleanErrs = array_filter($errs);

    if (!empty($cleanErrs)) {
        $error = reset($cleanErrs);
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO services (name, description, base_price, price_per_kg, capacity, is_active) 
                VALUES (:name, :desc, :base, :kg, :cap, 1)
            ");
            $stmt->execute([
                'name' => $name,
                'desc' => $desc,
                'base' => (float)$basePriceRaw,
                'kg'   => (float)$pricePerKgRaw,
                'cap'  => (int)$capacityRaw
            ]);
            $success = "New courier service '{$name}' created successfully!";
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'CREATE_SERVICE', "Added new service '{$name}'");
        } catch (PDOException $e) {
            $error = 'Database error creating service: ' . $e->getMessage();
        }
    }
}

// 2. Handle Edit Existing Service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_service']) && empty($error)) {
    $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $basePriceRaw = $_POST['base_price'] ?? '';
    $pricePerKgRaw = $_POST['price_per_kg'] ?? '';
    $capacityRaw = $_POST['capacity'] ?? '';
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    $errs = [
        validateRequired($name, 'Service Name'),
        validateStringLength($name, 'Service Name', 100),
        validateRequired($desc, 'Description'),
        validatePositiveNumber($basePriceRaw, 'Base Price', 100000),
        validatePositiveNumber($pricePerKgRaw, 'Price per Kg', 100000),
        validateIntRange($capacityRaw, 'Daily Capacity', 0, 100000)
    ];
    $cleanErrs = array_filter($errs);

    if (!$serviceId) {
        $error = 'Invalid service ID for update.';
    } elseif (!empty($cleanErrs)) {
        $error = reset($cleanErrs);
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE services 
                SET name = :name, description = :desc, base_price = :base, price_per_kg = :kg, capacity = :cap, is_active = :active 
                WHERE id = :id
            ");
            $stmt->execute([
                'name'   => $name,
                'desc'   => $desc,
                'base'   => (float)$basePriceRaw,
                'kg'     => (float)$pricePerKgRaw,
                'cap'    => (int)$capacityRaw,
                'active' => $isActive,
                'id'     => $serviceId
            ]);
            $success = "Service #{$serviceId} ({$name}) updated successfully!";
            logAdminAction($pdo, (int)$_SESSION['user_id'], 'EDIT_SERVICE', "Updated service #{$serviceId} ({$name})");
        } catch (PDOException $e) {
            $error = 'Database error updating service: ' . $e->getMessage();
        }
    }
}

// 3. Handle Soft-Delete / Toggle Active State
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active']) && empty($error)) {
    $serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $targetActive = (int)($_POST['target_active'] ?? 0);

    if ($serviceId) {
        // If deactivating, check if there are pending shipments currently assigned
        if ($targetActive === 0) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_id = :id AND status IN ('pending', 'dispatched')");
            $checkStmt->execute(['id' => $serviceId]);
            $activeBookingsCount = (int)$checkStmt->fetchColumn();

            if ($activeBookingsCount > 0) {
                $error = "Cannot deactivate service: There are {$activeBookingsCount} active shipments currently in progress using this service.";
            }
        }

        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("UPDATE services SET is_active = :act WHERE id = :id");
                $stmt->execute(['act' => $targetActive, 'id' => $serviceId]);
                $actionName = $targetActive ? 'reactivated' : 'deactivated';
                $success = "Service #{$serviceId} has been {$actionName}.";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'TOGGLE_SERVICE', "Set service #{$serviceId} is_active to {$targetActive}");
            } catch (PDOException $e) {
                $error = 'Database error toggling service: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all services with total bookings count
$services = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id) AS total_bookings,
           (SELECT COUNT(*) FROM bookings b WHERE b.service_id = s.id AND b.status IN ('pending', 'dispatched')) AS active_bookings
    FROM services s 
    ORDER BY s.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Service Management & Fleet Catalog | YOCOR Express";
$activeNav = "admin";
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] w-full bg-slate-50">

    <!-- Admin Sidebar -->
    <aside class="w-full md:w-64 bg-white border-r border-slate-200 p-4 sm:p-5 md:py-6 flex-shrink-0 md:sticky md:top-16 md:h-[calc(100vh-4rem)] md:overflow-y-auto">
        <div class="mb-5 pb-4 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-brandNavy text-white flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
                    <i class="fa-solid fa-layer-group text-brandOrange"></i>
                </div>
                <div>
                    <h3 class="font-black text-brandNavy text-sm">Fleet Manager</h3>
                    <p class="text-[11px] text-slate-500 font-medium truncate">User: <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
        </div>

        <nav class="space-y-4 text-xs font-bold">
            <!-- 1. DASHBOARD -->
            <div class="space-y-1">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Back to Dashboard">
                    <i class="fa-solid fa-chart-pie text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Dashboard Overview</span>
                </a>
            </div>

            <!-- 2. OPERATIONS -->
            <div class="space-y-1">
                <div class="px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Operations</div>
                <a href="admin_dashboard.php#all-bookings" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Customer Shipments">
                    <i class="fa-solid fa-boxes-packing text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Customer Shipments</span>
                </a>
                <a href="tracking.php" target="_blank" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Live Tracker">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-magnifying-glass-location text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                        <span class="truncate">Live Tracker</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-300"></i>
                </a>
            </div>

            <!-- 3. FLEET & PRICING (COLLAPSIBLE SERVICE CATALOG ACTIVE) -->
            <div class="space-y-1">
                <div class="px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Fleet & Rates</div>
                <details class="group" open>
                    <summary class="flex items-center justify-between px-3.5 py-2 rounded-xl bg-brandNavy/5 text-brandNavy font-bold cursor-pointer list-none select-none transition-colors">
                        <div class="flex items-center gap-3 truncate">
                            <i class="fa-solid fa-layer-group text-brandOrange text-sm w-4 text-center flex-shrink-0"></i>
                            <span class="truncate">Service Catalog</span>
                        </div>
                        <i class="fa-solid fa-chevron-down text-[10px] text-brandNavy"></i>
                    </summary>
                    <div class="pl-7 pr-2 pt-1 pb-1 space-y-1 border-l-2 border-slate-200 ml-4.5 mt-1">
                        <a href="admin_services.php" class="flex items-center py-1.5 px-2.5 rounded-lg bg-brandNavy text-white transition-colors text-[11px] font-bold">
                            <span class="truncate">Services & Fleet (Current)</span>
                        </a>
                        <a href="admin_dashboard.php#capacities" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Daily Capacities</span>
                        </a>
                        <a href="admin_dashboard.php#regional-rates" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Transit Rates</span>
                        </a>
                    </div>
                </details>
            </div>

            <!-- 4. SUPPORT & SECURITY -->
            <div class="space-y-1">
                <div class="px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Support & Logs</div>
                <a href="admin_inquiries.php" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Support Inquiries & Reports">
                    <i class="fa-regular fa-envelope text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Support Inquiries</span>
                </a>
                <a href="admin_dashboard.php#audit-logs" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Security Audit Logs">
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

    <!-- Main Content -->
    <main class="flex-1 min-w-0 p-4 sm:p-6 md:p-8 max-w-7xl mx-auto transition-all">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading">
                    Service Catalog & Logistics Fleet
                </h1>
                <p class="text-slate-600 text-xs mt-0.5">Add new delivery tiers, adjust base and distance pricing, manage daily slot capacities, and toggle service availability.</p>
            </div>
            <button onclick="document.getElementById('addServiceModal').classList.remove('hidden')" 
                    class="bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs px-4 py-2 rounded-xl shadow brand-glow-orange transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-plus text-xs"></i>
                <span>Add New Service</span>
            </button>
        </div>

        <!-- Feedback Alerts -->
        <?php if (!empty($success)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-3.5 py-2.5 rounded-xl mb-5 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Services Cards / Table Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($services as $srv): ?>
                <?php 
                $isActive = (int)($srv['is_active'] ?? 1) === 1;
                ?>
                <div class="bg-white rounded-2xl border <?= $isActive ? 'border-slate-200 shadow-sm' : 'border-slate-200/60 bg-slate-50/70 opacity-80' ?> p-5 flex flex-col justify-between transition-all">
                    <div>
                        <div class="flex items-start justify-between gap-2 pb-3 border-b border-slate-100">
                            <div>
                                <span class="font-mono text-[10px] text-slate-400 font-bold">SERVICE #<?= $srv['id'] ?></span>
                                <h3 class="font-black text-brandNavy text-base mt-0.5"><?= htmlspecialchars($srv['name']) ?></h3>
                            </div>
                            <?php if ($isActive): ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-600 bg-slate-200 px-2.5 py-0.5 rounded-full">
                                    Inactive
                                </span>
                            <?php endif; ?>
                        </div>

                        <p class="text-xs text-slate-600 my-3 line-clamp-2">
                            <?= htmlspecialchars($srv['description']) ?>
                        </p>

                        <!-- Metric Badges -->
                        <div class="grid grid-cols-3 gap-2 bg-slate-50 rounded-xl p-2.5 text-center text-xs border border-slate-100 mb-4">
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block">Base Price</span>
                                <strong class="text-brandNavy text-xs font-black">₱<?= number_format((float)$srv['base_price'], 2) ?></strong>
                            </div>
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block">Rate / kg</span>
                                <strong class="text-brandNavy text-xs font-black">₱<?= number_format((float)$srv['price_per_kg'], 2) ?></strong>
                            </div>
                            <div>
                                <span class="text-[9px] uppercase font-bold text-slate-400 block">Slots Left</span>
                                <strong class="text-<?= $srv['capacity'] > 0 ? 'emerald-600' : 'rose-600' ?> text-xs font-black">
                                    <?= $srv['capacity'] ?>
                                </strong>
                            </div>
                        </div>

                        <div class="text-[11px] text-slate-500 space-y-1">
                            <p><strong>Total Lifetime Waybills:</strong> <?= $srv['total_bookings'] ?></p>
                            <p><strong>Active In-Transit Parcels:</strong> <?= $srv['active_bookings'] ?></p>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div class="pt-4 border-t border-slate-100 mt-4 flex items-center justify-between gap-2">
                        <!-- Toggle Soft Delete -->
                        <form method="POST" action="" onsubmit="return confirm('Change active status for <?= htmlspecialchars($srv['name']) ?>?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="service_id" value="<?= $srv['id'] ?>">
                            <input type="hidden" name="target_active" value="<?= $isActive ? 0 : 1 ?>">
                            <button type="submit" name="toggle_active" 
                                    class="text-xs font-bold px-3 py-1.5 rounded-lg border transition-colors <?= $isActive ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>">
                                <?= $isActive ? '<i class="fa-solid fa-power-off text-[10px] mr-1"></i> Deactivate' : '<i class="fa-solid fa-check text-[10px] mr-1"></i> Activate' ?>
                            </button>
                        </form>

                        <!-- Edit Button (Opens modal prefilled) -->
                        <button type="button" 
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($srv), ENT_QUOTES, 'UTF-8') ?>)"
                                class="bg-slate-100 hover:bg-slate-200 text-brandNavy font-bold text-xs px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                            <i class="fa-solid fa-pen text-[10px]"></i>
                            <span>Edit Service</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>
</div>

<!-- Modal: Add Service -->
<div id="addServiceModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 relative animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-base font-black text-brandNavy flex items-center gap-2">
                <i class="fa-solid fa-layer-group text-brandOrange"></i>
                <span>Add Courier Service Tier</span>
            </h3>
            <button type="button" onclick="document.getElementById('addServiceModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <div>
                <label class="block font-bold text-slate-700 mb-1">Service Name</label>
                <input type="text" name="name" required placeholder="e.g. Express Air Freight" 
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-semibold">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" rows="2" required placeholder="Short description of service tier..." 
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-slate-700"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Base Price (₱)</label>
                    <input type="number" step="0.01" name="base_price" required placeholder="150.00" 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Per kg (₱)</label>
                    <input type="number" step="0.01" name="price_per_kg" required placeholder="45.00" 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Daily Capacity</label>
                    <input type="number" name="capacity" required placeholder="50" 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('addServiceModal').classList.add('hidden')" 
                        class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 font-bold hover:bg-slate-200">
                    Cancel
                </button>
                <button type="submit" name="add_service" 
                        class="px-4 py-2 rounded-lg bg-brandOrange hover:bg-orange-600 text-white font-bold shadow brand-glow-orange">
                    Create Service
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Service -->
<div id="editServiceModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 relative animate-in fade-in zoom-in-95 duration-150">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
            <h3 class="text-base font-black text-brandNavy flex items-center gap-2">
                <i class="fa-solid fa-pen text-brandOrange"></i>
                <span>Edit Courier Service</span>
            </h3>
            <button type="button" onclick="document.getElementById('editServiceModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 p-1">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" action="" class="space-y-3.5 text-xs">
            <?= csrfField() ?>
            <input type="hidden" name="service_id" id="edit_service_id">

            <div>
                <label class="block font-bold text-slate-700 mb-1">Service Name</label>
                <input type="text" name="name" id="edit_name" required 
                       class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-semibold">
            </div>

            <div>
                <label class="block font-bold text-slate-700 mb-1">Description</label>
                <textarea name="description" id="edit_description" rows="2" required 
                          class="w-full px-3 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-slate-700"></textarea>
            </div>

            <div class="grid grid-cols-3 gap-2.5">
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Base Price (₱)</label>
                    <input type="number" step="0.01" name="base_price" id="edit_base_price" required 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Per kg (₱)</label>
                    <input type="number" step="0.01" name="price_per_kg" id="edit_price_per_kg" required 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 mb-1">Capacity</label>
                    <input type="number" name="capacity" id="edit_capacity" required 
                           class="w-full px-2.5 py-2 rounded-lg border border-slate-300 bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none font-bold">
                </div>
            </div>

            <div class="pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-slate-700">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-slate-300 text-brandOrange focus:ring-brandOrange">
                    <span>Service is actively available for customer booking</span>
                </label>
            </div>

            <div class="pt-3 flex justify-end gap-2 border-t border-slate-100">
                <button type="button" onclick="document.getElementById('editServiceModal').classList.add('hidden')" 
                        class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 font-bold hover:bg-slate-200">
                    Cancel
                </button>
                <button type="submit" name="edit_service" 
                        class="px-4 py-2 rounded-lg bg-brandNavy hover:bg-slate-900 text-white font-bold shadow">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(srv) {
    document.getElementById('edit_service_id').value = srv.id;
    document.getElementById('edit_name').value = srv.name;
    document.getElementById('edit_description').value = srv.description;
    document.getElementById('edit_base_price').value = srv.base_price;
    document.getElementById('edit_price_per_kg').value = srv.price_per_kg;
    document.getElementById('edit_capacity').value = srv.capacity;
    document.getElementById('edit_is_active').checked = (parseInt(srv.is_active || 1) === 1);
    document.getElementById('editServiceModal').classList.remove('hidden');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
