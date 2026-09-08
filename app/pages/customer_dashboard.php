<?php
// Start session safely and require login
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
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

$pdo = getConnection();
$userId = (int)$_SESSION['user_id'];

// Filter & Search parameters
$statusFilter = trim($_GET['status'] ?? 'all');
$validFilters = ['all', 'in_progress', 'delivered', 'under_review', 'unpaid'];
if (!in_array($statusFilter, $validFilters)) {
    $statusFilter = 'all';
}

$search = trim($_GET['q'] ?? '');
$whereParts = ["b.user_id = :user_id"];
$params = ['user_id' => $userId];

if ($statusFilter === 'in_progress') {
    $whereParts[] = "b.status IN ('pending', 'dispatched')";
} elseif ($statusFilter === 'delivered') {
    $whereParts[] = "b.status = 'delivered'";
} elseif ($statusFilter === 'under_review') {
    $whereParts[] = "b.payment_status = 'under_review'";
} elseif ($statusFilter === 'unpaid') {
    $whereParts[] = "b.payment_status = 'unpaid'";
}

if (!empty($search)) {
    $whereParts[] = "(b.tracking_code LIKE :search OR b.delivery_address LIKE :search OR CAST(b.id AS CHAR) = :exact_id)";
    $params['search'] = '%' . $search . '%';
    $params['exact_id'] = $search;
}

$whereSql = implode(' AND ', $whereParts);

// Fetch user's bookings with filters applied
$stmt = $pdo->prepare("
    SELECT b.*, s.name AS service_name 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    WHERE {$whereSql}
    ORDER BY b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate user count metrics
$allCountStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :uid");
$allCountStmt->execute(['uid' => $userId]);
$totalOrdersCount = (int)$allCountStmt->fetchColumn();

$inProgStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND status IN ('pending', 'dispatched')");
$inProgStmt->execute(['uid' => $userId]);
$inProgressCount = (int)$inProgStmt->fetchColumn();

$delivStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND status = 'delivered'");
$delivStmt->execute(['uid' => $userId]);
$deliveredCount = (int)$delivStmt->fetchColumn();

$reviewStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND payment_status = 'under_review'");
$reviewStmt->execute(['uid' => $userId]);
$underReviewCount = (int)$reviewStmt->fetchColumn();

$unpaidStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = :uid AND payment_status = 'unpaid'");
$unpaidStmt->execute(['uid' => $userId]);
$unpaidCount = (int)$unpaidStmt->fetchColumn();
?>
    
<?php 
$pageTitle = "Customer Portal & My Shipments | YOCOR Express";
$activeNav = "customer_dashboard";
include __DIR__ . '/../includes/header.php'; 
?>

<style>
    /* Smooth Transition & Collapsible States for Customer Sidebar */
    .customer-sidebar {
        transition: width 0.2s cubic-bezier(0.4, 0, 0.2, 1), padding 0.2s ease;
    }
    .customer-sidebar details > summary::-webkit-details-marker {
        display: none;
    }
    .customer-sidebar details[open] .sidebar-chevron {
        transform: rotate(90deg);
    }
    @media (min-width: 768px) {
        .customer-sidebar.collapsed {
            width: 4.5rem !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .customer-sidebar.collapsed .customer-sidebar-text,
        .customer-sidebar.collapsed nav span,
        .customer-sidebar.collapsed nav .nav-badge,
        .customer-sidebar.collapsed nav .sidebar-heading,
        .customer-sidebar.collapsed nav .customer-sidebar-heading,
        .customer-sidebar.collapsed nav .sidebar-chevron,
        .customer-sidebar.collapsed nav .nested-nav {
            display: none !important;
        }
        .customer-sidebar.collapsed nav a,
        .customer-sidebar.collapsed nav summary {
            justify-content: center !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
        .customer-sidebar.collapsed #customerSidebarToggleIcon {
            transform: rotate(180deg);
        }
    }
    @media (max-width: 767px) {
        .customer-sidebar.mobile-hidden {
            display: none !important;
        }
    }
</style>

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] bg-slate-50">

    <!-- Categorized Modern Customer Sidebar -->
    <aside id="customerSidebar" class="customer-sidebar w-full md:w-64 bg-white border-r border-slate-200 p-4 sm:p-5 md:py-6 flex-shrink-0 md:sticky md:top-16 md:h-[calc(100vh-4rem)] md:overflow-y-auto">
        <div class="mb-5 pb-4 border-b border-slate-100 flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 rounded-xl bg-brandOrange/10 text-brandOrange flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
                <div class="customer-sidebar-text whitespace-nowrap overflow-hidden">
                    <h3 class="font-black text-brandNavy text-sm truncate">Customer Hub</h3>
                    <p class="text-xs text-slate-500 font-medium truncate">@<?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
            <!-- Desktop Sidebar Collapse Toggle Button -->
            <button id="customerSidebarToggle" type="button" class="hidden md:flex p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-brandNavy transition-colors flex-shrink-0" title="Toggle Sidebar">
                <i id="customerSidebarToggleIcon" class="fa-solid fa-chevron-left text-xs transition-transform"></i>
            </button>
        </div>

        <nav class="space-y-4 text-xs font-bold">
            <!-- Section 1: Shipping Operations -->
            <div class="space-y-1">
                <div class="sidebar-heading customer-sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Shipment Activity</div>
                <a href="customer_dashboard.php" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl <?= $statusFilter === 'all' && empty($search) ? 'bg-brandOrange/10 text-brandOrange' : 'text-slate-600 hover:bg-slate-100' ?> transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-boxes-stacked text-sm w-4 text-center"></i>
                        <span class="truncate">All My Orders</span>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-700">
                        <?= $totalOrdersCount ?>
                    </span>
                </a>
                <a href="customer_dashboard.php?status=in_progress" class="flex items-center justify-between px-3.5 py-2 rounded-xl <?= $statusFilter === 'in_progress' ? 'bg-brandOrange/10 text-brandOrange' : 'text-slate-600 hover:bg-slate-100' ?> transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-truck-fast text-sm w-4 text-center text-slate-400"></i>
                        <span class="truncate">In Transit</span>
                    </div>
                    <?php if ($inProgressCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-blue-100 text-blue-700">
                            <?= $inProgressCount ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="customer_dashboard.php?status=under_review" class="flex items-center justify-between px-3.5 py-2 rounded-xl <?= $statusFilter === 'under_review' ? 'bg-brandOrange/10 text-brandOrange' : 'text-slate-600 hover:bg-slate-100' ?> transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-hourglass-half text-sm w-4 text-center text-slate-400"></i>
                        <span class="truncate">Verifying Payment</span>
                    </div>
                    <?php if ($underReviewCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-[9px] font-extrabold bg-amber-100 text-amber-800">
                            <?= $underReviewCount ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Section 2: Quick Booking Tools -->
            <div class="space-y-1">
                <div class="sidebar-heading customer-sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Quick Tools</div>
                <a href="booking.php" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-truck-pickup text-slate-400 text-sm w-4 text-center"></i>
                    <span>Book Courier Pickup</span>
                </a>
                <a href="tracking.php" target="_blank" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-magnifying-glass-location text-slate-400 text-sm w-4 text-center"></i>
                        <span>Live Parcel Tracker</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-300"></i>
                </a>
                <a href="quote.php" target="_blank" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-calculator text-slate-400 text-sm w-4 text-center"></i>
                        <span>Rate Estimator</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-slate-300"></i>
                </a>
            </div>

            <!-- Section 3: Support & Account -->
            <div class="space-y-1">
                <div class="sidebar-heading customer-sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Assistance</div>
                <a href="contact.php" class="flex items-center gap-3 px-3.5 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                    <i class="fa-regular fa-comment-dots text-slate-400 text-sm w-4 text-center"></i>
                    <span>Contact Support Hub</span>
                </a>
            </div>
            
            <div class="pt-3 border-t border-slate-100 mt-3">
                <a href="logout.php" class="flex items-center justify-between px-3.5 py-2 rounded-xl text-red-600 hover:bg-red-50 transition-colors">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-solid fa-right-from-bracket text-sm w-4 text-center"></i>
                        <span>Sign Out</span>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-red-300"></i>
                </a>
            </div>
        </nav>
    </aside>

    <!-- Customer Main Orders Area -->
    <main class="flex-grow p-4 sm:p-6 md:p-8 max-w-6xl w-full min-w-0 mx-auto transition-all">
        
        <!-- Welcome Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            <div class="flex items-center gap-3">
                <!-- Mobile Sidebar Toggle -->
                <button id="mobileCustomerSidebarToggle" class="md:hidden p-2 rounded-xl bg-white border border-slate-200 text-brandNavy shadow-sm hover:bg-slate-50 transition-colors" title="Toggle Navigation Menu">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading">
                        Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </h1>
                    <p class="text-slate-600 text-xs mt-0.5">Manage and track all your courier dispatches, status milestones, and official receipts.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="quote.php" 
                   class="bg-white hover:bg-slate-50 text-brandNavy font-bold px-3.5 py-2 rounded-xl border border-slate-200 transition-all text-xs inline-flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-calculator text-[11px] text-brandOrange"></i>
                    <span>Calculate Rate</span>
                </a>
                <a href="booking.php" 
                   class="bg-brandOrange hover:bg-orange-600 text-white font-bold px-4 py-2 rounded-xl transition-all shadow-sm brand-glow-orange inline-flex items-center gap-1.5 text-xs">
                    <i class="fa-solid fa-plus text-[11px]"></i>
                    <span>Schedule Pickup</span>
                </a>
            </div>
        </div>

        <!-- Success Message (from registration, booking or payment submission) -->
        <?php if (!empty($_SESSION['welcome_new_user'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span>Welcome to YOCOR Express, <?= htmlspecialchars($_SESSION['username']) ?>! Your account has been registered and you are now signed in.</span>
            </div>
            <?php unset($_SESSION['welcome_new_user']); ?>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'booking_success'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> 
                <span>Booking successful! Your shipment has been scheduled with our courier.</span>
            </div>
        <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'payment_submitted'): ?>
            <div class="bg-blue-50 border border-blue-200 text-blue-900 px-3.5 py-2.5 rounded-xl mb-4 text-xs font-bold flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-hourglass-half text-blue-600 text-sm"></i> 
                <span><?= htmlspecialchars($_SESSION['payment_submitted'] ?? 'Payment submitted! Our administrator is currently reviewing the payment before confirming it.') ?></span>
            </div>
            <?php unset($_SESSION['payment_submitted']); ?>
        <?php endif; ?>

        <!-- Customer Summary Cards (Interactive Filters) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <a href="customer_dashboard.php?status=all" class="bg-white rounded-xl p-3.5 border <?= $statusFilter === 'all' && empty($search) ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-slate-100 text-brandNavy flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Orders</p>
                        <p class="text-xl font-black text-brandNavy font-heading"><?= $totalOrdersCount ?></p>
                    </div>
                </div>
            </a>

            <a href="customer_dashboard.php?status=in_progress" class="bg-white rounded-xl p-3.5 border <?= $statusFilter === 'in_progress' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-orange-50 text-brandOrange flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-truck-fast"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">In Transit</p>
                        <p class="text-xl font-black text-brandOrange font-heading"><?= $inProgressCount ?></p>
                    </div>
                </div>
            </a>

            <a href="customer_dashboard.php?status=under_review" class="bg-white rounded-xl p-3.5 border <?= $statusFilter === 'under_review' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> shadow-sm hover:border-slate-300 transition-all">
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

            <a href="customer_dashboard.php?status=delivered" class="bg-white rounded-xl p-3.5 border <?= $statusFilter === 'delivered' ? 'border-brandOrange ring-2 ring-brandOrange/20' : 'border-slate-200/90' ?> shadow-sm hover:border-slate-300 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-base flex-shrink-0">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Delivered</p>
                        <p class="text-xl font-black text-emerald-600 font-heading"><?= $deliveredCount ?></p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Bookings Table Container -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
            <!-- Table Header & Filter Bar -->
            <div class="p-4 sm:p-5 border-b border-slate-100 bg-white">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-brandNavy text-sm flex items-center gap-2">
                            <i class="fa-solid fa-receipt text-brandOrange"></i>
                            <span>Shipment History &amp; Waybills</span>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                <?= count($bookings) ?> Showing
                            </span>
                        </h2>
                    </div>

                    <!-- Search Form -->
                    <form method="GET" action="" class="w-full sm:w-64 flex items-center gap-1.5">
                        <?php if ($statusFilter !== 'all'): ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                        <?php endif; ?>
                        <div class="relative w-full">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search tracking # or city..." 
                                   class="w-full pl-8 pr-3 py-1.5 rounded-xl border border-slate-200 text-xs bg-slate-50 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                        </div>
                        <?php if (!empty($search)): ?>
                            <a href="customer_dashboard.php?status=<?= urlencode($statusFilter) ?>" 
                               class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs" title="Clear Search">
                                <i class="fa-solid fa-xmark"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Interactive Filter Tabs -->
                <div class="flex flex-wrap items-center gap-1.5 mt-3 pt-3 border-t border-slate-100 text-xs">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mr-1">Status:</span>
                    <a href="customer_dashboard.php?status=all<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                       class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'all' ? 'bg-brandNavy text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
                        All (<?= $totalOrdersCount ?>)
                    </a>
                    <a href="customer_dashboard.php?status=in_progress<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                       class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'in_progress' ? 'bg-brandOrange text-white' : 'bg-orange-50 text-brandOrange hover:bg-orange-100' ?>">
                        In Progress (<?= $inProgressCount ?>)
                    </a>
                    <a href="customer_dashboard.php?status=under_review<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                       class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'under_review' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-700 hover:bg-blue-100' ?>">
                        Under Review (<?= $underReviewCount ?>)
                    </a>
                    <a href="customer_dashboard.php?status=unpaid<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                       class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'unpaid' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100' ?>">
                        Unpaid (<?= $unpaidCount ?>)
                    </a>
                    <a href="customer_dashboard.php?status=delivered<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                       class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all <?= $statusFilter === 'delivered' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' ?>">
                        Delivered (<?= $deliveredCount ?>)
                    </a>
                </div>
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
                                    <td class="px-4 py-2.5 space-y-1">
                                        <?php 
                                        $statusBadges = [
                                            'pending' => 'bg-amber-100 text-amber-800 border-amber-200',
                                            'dispatched' => 'bg-blue-100 text-blue-800 border-blue-200',
                                            'delivered' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                            'cancelled' => 'bg-rose-100 text-rose-800 border-rose-200'
                                        ];
                                        $badge = $statusBadges[$booking['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                        $isPaid = ($booking['payment_status'] ?? 'unpaid') === 'paid';
                                        ?>
                                        <div>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-wide border <?= $badge ?>">
                                                <?= htmlspecialchars($booking['status']) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <?php if ($isPaid): ?>
                                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 rounded">
                                                    <i class="fa-solid fa-circle-check text-[8px]"></i> Paid
                                                </span>
                                            <?php elseif (($booking['payment_status'] ?? 'unpaid') === 'under_review'): ?>
                                                <div class="space-y-0.5">
                                                    <span class="inline-flex items-center gap-1 text-[9px] font-bold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded" title="Admin is verifying this payment">
                                                        <i class="fa-solid fa-hourglass-half text-[8px]"></i> Verifying
                                                    </span>
                                                    <?php if (!empty($booking['payment_ref'])): ?>
                                                        <span class="block text-[8px] font-mono text-slate-400 truncate max-w-[100px]" title="Ref: <?= htmlspecialchars($booking['payment_ref']) ?>">
                                                            <?= htmlspecialchars($booking['payment_ref']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">
                                                    <i class="fa-solid fa-clock text-[8px]"></i> Unpaid
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-slate-500 text-[10px]">
                                        <?= date('M d, Y', strtotime($booking['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-2.5 text-right space-x-1 whitespace-nowrap">
                                        <?php if (!$isPaid && $booking['status'] !== 'cancelled'): ?>
                                            <?php if (($booking['payment_status'] ?? 'unpaid') === 'under_review'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-200" title="Submitted for Admin Review">
                                                    <i class="fa-solid fa-hourglass-half text-[9px]"></i>
                                                    <span>In Review</span>
                                                </span>
                                            <?php else: ?>
                                                <a href="payment.php?id=<?= (int)$booking['id'] ?>" 
                                                   class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-brandOrange text-white font-bold text-[11px] hover:bg-orange-600 transition-colors shadow-sm">
                                                    <i class="fa-solid fa-credit-card text-[9px]"></i>
                                                    <span>Pay</span>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <a href="receipt.php?id=<?= (int)$booking['id'] ?>" 
                                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition-colors shadow-sm"
                                           title="View Waybill Receipt">
                                            <i class="fa-solid fa-receipt text-[10px] text-brandNavy"></i>
                                            <span>Receipt</span>
                                        </a>
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

<!-- Customer Sidebar Toggle Logic -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('customerSidebar');
        const desktopToggle = document.getElementById('customerSidebarToggle');
        const mobileToggle = document.getElementById('mobileCustomerSidebarToggle');

        // Restore saved desktop state from localStorage
        if (window.innerWidth >= 768) {
            const isCollapsed = localStorage.getItem('yocor_customer_sidebar_collapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }

        // Desktop toggle handler
        if (desktopToggle && sidebar) {
            desktopToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const isNowCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('yocor_customer_sidebar_collapsed', isNowCollapsed);
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