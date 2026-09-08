<?php
// Start session and require admin authentication
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();
$error = '';
$success = '';

// Handle CSRF & State Mutation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid or expired security token (CSRF). Please refresh the page.';
    } else {
        // 1. Mark as Read
        if (isset($_POST['mark_read'])) {
            $msgId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
            if ($msgId) {
                $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = :id AND deleted_at IS NULL");
                $stmt->execute(['id' => $msgId]);
                $success = "Inquiry #{$msgId} marked as read.";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'READ_MESSAGE', "Marked inquiry #{$msgId} as read");
            }
        }
        
        // 2. Mark as Unread
        if (isset($_POST['mark_unread'])) {
            $msgId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
            if ($msgId) {
                $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 0 WHERE id = :id AND deleted_at IS NULL");
                $stmt->execute(['id' => $msgId]);
                $success = "Inquiry #{$msgId} marked as unread.";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'UNREAD_MESSAGE', "Marked inquiry #{$msgId} as unread");
            }
        }

        // 3. Delete Message (Soft delete)
        if (isset($_POST['delete_message'])) {
            $msgId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
            if ($msgId) {
                $stmt = $pdo->prepare("UPDATE contact_messages SET deleted_at = NOW() WHERE id = :id");
                $stmt->execute(['id' => $msgId]);
                $success = "Inquiry #{$msgId} has been archived/deleted.";
                logAdminAction($pdo, (int)$_SESSION['user_id'], 'DELETE_MESSAGE', "Deleted inquiry #{$msgId}");
            }
        }
    }
}

// Filter & Search Handling
$filter = trim($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'unread', 'read'])) {
    $filter = 'all';
}
$search = trim($_GET['q'] ?? '');

$whereParts = ["deleted_at IS NULL"];
$params = [];

if ($filter === 'unread') {
    $whereParts[] = "is_read = 0";
} elseif ($filter === 'read') {
    $whereParts[] = "is_read = 1";
}

if (!empty($search)) {
    $whereParts[] = "(name LIKE :search OR email LIKE :search OR subject LIKE :search OR message LIKE :search OR CAST(id AS CHAR) = :exact_id)";
    $params['search'] = '%' . $search . '%';
    $params['exact_id'] = $search;
}

$whereSql = implode(' AND ', $whereParts);

// Query filtered messages
$stmt = $pdo->prepare("
    SELECT * FROM contact_messages 
    WHERE {$whereSql} 
    ORDER BY created_at DESC
");
$stmt->execute($params);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counters
$unreadCount = getUnreadMessagesCount($pdo);
$totalInquiries = (int)$pdo->query("SELECT COUNT(*) FROM contact_messages WHERE deleted_at IS NULL")->fetchColumn();
$readCount = max(0, $totalInquiries - $unreadCount);

$pageTitle = "Support Inquiries & Customer Reports | YOCOR Express Admin";
$activeNav = "admin";
include __DIR__ . '/../includes/header.php';
?>

<style>
    /* Responsive Admin Sidebar Styling */
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
                    <i class="fa-solid fa-headset text-brandOrange"></i>
                </div>
                <div class="admin-sidebar-text whitespace-nowrap overflow-hidden">
                    <h3 class="font-black text-brandNavy text-sm">Inquiries Hub</h3>
                    <p class="text-[11px] text-slate-500 font-medium truncate">Admin: <?= htmlspecialchars($_SESSION['username']) ?></p>
                </div>
            </div>
            <button id="sidebarToggle" type="button" class="hidden md:flex p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-brandNavy transition-colors flex-shrink-0" title="Toggle Sidebar Width">
                <i id="sidebarToggleIcon" class="fa-solid fa-chevron-left text-xs transition-transform"></i>
            </button>
        </div>

        <nav class="space-y-4 text-xs font-bold">
            <!-- 1. DASHBOARD -->
            <div class="space-y-1">
                <a href="admin_dashboard.php" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors" title="Overview & Stats">
                    <i class="fa-solid fa-chart-pie text-slate-400 text-sm w-4 text-center flex-shrink-0"></i>
                    <span class="truncate">Dashboard Overview</span>
                </a>
            </div>

            <!-- 2. OPERATIONS -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Operations</div>
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

            <!-- 3. FLEET & PRICING -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Fleet & Rates</div>
                <details class="group">
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
                        <a href="admin_dashboard.php#capacities" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Daily Capacities</span>
                        </a>
                        <a href="admin_dashboard.php#regional-rates" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Transit Rates</span>
                        </a>
                        <a href="admin_dashboard.php#payment-settings" class="flex items-center py-1.5 px-2.5 rounded-lg text-slate-600 hover:text-brandNavy hover:bg-slate-100 transition-colors text-[11px]">
                            <span class="truncate">Payment Methods</span>
                        </a>
                    </div>
                </details>
            </div>

            <!-- 4. SUPPORT & SECURITY (ACTIVE PAGE) -->
            <div class="space-y-1">
                <div class="sidebar-heading px-3.5 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Support & Logs</div>
                <a href="admin_inquiries.php" class="flex items-center justify-between px-3.5 py-2 rounded-xl bg-brandNavy text-white shadow-sm transition-colors" title="Customer Inquiries">
                    <div class="flex items-center gap-3 truncate">
                        <i class="fa-regular fa-envelope text-brandOrange text-sm w-4 text-center flex-shrink-0"></i>
                        <span class="truncate">Support Inquiries</span>
                    </div>
                    <?php if ($unreadCount > 0): ?>
                        <span class="nav-badge px-1.5 py-0.5 rounded-full bg-brandOrange text-white font-black text-[9px] leading-none">
                            <?= $unreadCount ?>
                        </span>
                    <?php endif; ?>
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

    <!-- Main Content Area -->
    <main class="flex-1 min-w-0 p-4 sm:p-6 md:p-8">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            <div class="flex items-center gap-3">
                <button id="mobileSidebarToggle" class="md:hidden p-2 rounded-xl bg-white border border-slate-200 text-brandNavy shadow-sm hover:bg-slate-50 transition-colors" title="Toggle Navigation Menu">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading">
                        Customer Inquiries & Support Reports
                    </h1>
                    <p class="text-slate-600 text-xs mt-0.5">Review, inspect complete message bodies, and track customer contact requests.</p>
                </div>
            </div>

            <!-- Quick Filter Tabs -->
            <div class="flex items-center gap-1.5 bg-white p-1 rounded-xl border border-slate-200 shadow-sm text-xs font-bold">
                <a href="admin_inquiries.php?filter=all<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg transition-colors <?= $filter === 'all' ? 'bg-brandNavy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
                    All (<?= $totalInquiries ?>)
                </a>
                <a href="admin_inquiries.php?filter=unread<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg transition-colors inline-flex items-center gap-1.5 <?= $filter === 'unread' ? 'bg-brandOrange text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
                    <span>Unread</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="px-1.5 py-0.2 rounded-full text-[9px] <?= $filter === 'unread' ? 'bg-white text-brandOrange font-black' : 'bg-brandOrange/10 text-brandOrange' ?>"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_inquiries.php?filter=read<?= !empty($search) ? '&q=' . urlencode($search) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg transition-colors <?= $filter === 'read' ? 'bg-brandNavy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
                    Read (<?= $readCount ?>)
                </a>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
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

        <!-- Search Bar & Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <form method="GET" action="admin_inquiries.php" class="flex-1 flex items-center gap-2">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="relative w-full max-w-lg">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Search by sender name, email, subject, keyword, or ID..." 
                           class="w-full pl-9 pr-3 py-2 rounded-lg bg-slate-50 border border-slate-200 text-xs focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                </div>
                <button type="submit" class="bg-brandNavy hover:bg-slate-900 text-white font-bold px-4 py-2 rounded-lg text-xs transition-colors shadow-sm">
                    Search
                </button>
                <?php if (!empty($search)): ?>
                    <a href="admin_inquiries.php?filter=<?= htmlspecialchars($filter) ?>" class="text-xs text-slate-500 hover:text-brandOrange underline font-semibold">
                        Clear
                    </a>
                <?php endif; ?>
            </form>
            <div class="text-xs text-slate-500 font-medium whitespace-nowrap self-end sm:self-center">
                Showing <?= count($inquiries) ?> message(s)
            </div>
        </div>

        <!-- Inquiries Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 uppercase font-bold border-b border-slate-200 text-[10px] tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Received At</th>
                            <th class="px-4 py-3">Customer / Email</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">Message Snippet</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium">
                        <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-xl mx-auto mb-2">
                                        <i class="fa-regular fa-envelope-open"></i>
                                    </div>
                                    <p class="text-xs font-bold text-slate-600">No support inquiries found</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Try selecting a different filter or search keyword.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($inquiries as $msg): 
                                $isUnread = empty($msg['is_read']);
                                $preview = mb_substr($msg['message'], 0, 90);
                                if (mb_strlen($msg['message']) > 90) $preview .= '...';
                            ?>
                                <tr class="hover:bg-slate-50/80 transition-colors <?= $isUnread ? 'bg-orange-50/30' : '' ?>">
                                    <td class="px-4 py-3 text-slate-500 font-mono text-[11px] whitespace-nowrap">
                                        <?= date('M d, Y · h:i A', strtotime($msg['created_at'])) ?>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-brandNavy whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <?php if ($isUnread): ?>
                                                <span class="w-2 h-2 rounded-full bg-brandOrange inline-block" title="Unread"></span>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($msg['name']) ?></span>
                                        </div>
                                        <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" class="text-[10px] text-slate-400 font-normal hover:text-brandOrange underline block mt-0.5">
                                            <?= htmlspecialchars($msg['email']) ?>
                                        </a>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-800 text-[11px] whitespace-nowrap">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 text-[11px] max-w-xs break-words">
                                        <span class="line-clamp-2"><?= htmlspecialchars($preview) ?></span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <?php if ($isUnread): ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-brandOrange bg-orange-100 px-2.5 py-0.5 rounded-full">
                                                <i class="fa-solid fa-envelope text-[9px]"></i> Unread
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">
                                                <i class="fa-solid fa-check-double text-[9px]"></i> Read
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap space-x-1">
                                        <!-- Open Detail Modal Button -->
                                        <button type="button"
                                                onclick="openInquiryModal(<?= htmlspecialchars(json_encode([
                                                    'id' => $msg['id'],
                                                    'name' => $msg['name'],
                                                    'email' => $msg['email'],
                                                    'subject' => $msg['subject'],
                                                    'message' => $msg['message'],
                                                    'created_at' => date('F d, Y · h:i A', strtotime($msg['created_at'])),
                                                    'is_read' => (bool)$msg['is_read']
                                                ])) ?>)"
                                                class="px-2.5 py-1 rounded-lg bg-brandNavy hover:bg-slate-900 text-white font-bold text-[11px] shadow-sm transition-colors inline-flex items-center gap-1"
                                                title="View complete inquiry details">
                                            <i class="fa-regular fa-eye text-[10px]"></i> View
                                        </button>

                                        <!-- Mark Read / Unread -->
                                        <?php if ($isUnread): ?>
                                            <form method="POST" action="" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                <button type="submit" name="mark_read" title="Mark as Read"
                                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition-colors inline-flex items-center">
                                                    <i class="fa-solid fa-check text-[10px]"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="" class="inline">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                <button type="submit" name="mark_unread" title="Mark as Unread"
                                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold text-[11px] transition-colors inline-flex items-center">
                                                    <i class="fa-regular fa-envelope text-[10px]"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Delete / Archive -->
                                        <form method="POST" action="" class="inline" onsubmit="return confirm('Archive/delete inquiry #<?= $msg['id'] ?>?');">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" name="delete_message" title="Delete Inquiry"
                                                    class="px-2 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 font-bold text-[11px] transition-colors inline-flex items-center">
                                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                            </button>
                                        </form>
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

<!-- Complete Inquiry Details Modal -->
<div id="inquiryModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 transition-all opacity-0 pointer-events-none">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 w-full max-w-xl overflow-hidden transform scale-95 transition-all">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-brandNavy to-slate-900 text-white p-5 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-brandOrange/20 text-brandOrange flex items-center justify-center text-sm font-bold">
                    <i class="fa-regular fa-envelope-open"></i>
                </div>
                <div>
                    <span class="text-[10px] uppercase font-bold text-brandOrange tracking-widest block" id="modalInquiryId">Inquiry #0</span>
                    <h3 class="font-extrabold text-sm font-heading" id="modalSubject">Subject Title</h3>
                </div>
            </div>
            <button onclick="closeInquiryModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center text-sm transition-colors">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Modal Content Details -->
        <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
            <!-- Metadata Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                <div>
                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Sender Full Name</span>
                    <span class="font-bold text-brandNavy" id="modalSenderName">-</span>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Contact Email</span>
                    <a href="#" id="modalSenderEmail" class="font-bold text-brandOrange hover:underline">-</a>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Date Sent</span>
                    <span class="font-mono text-slate-700" id="modalDate">-</span>
                </div>
                <div>
                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Status</span>
                    <span id="modalStatusBadge" class="inline-block mt-0.5">-</span>
                </div>
            </div>

            <!-- Message Full Text -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Complete Message</label>
                <div class="p-4 rounded-xl bg-slate-50/70 border border-slate-200 text-xs text-slate-800 leading-relaxed whitespace-pre-wrap font-sans" id="modalMessageBody">
                    -
                </div>
            </div>

            <!-- Quick Action Form inside Modal -->
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                <a href="#" id="modalMailtoBtn" class="px-4 py-2 rounded-xl bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs transition-colors shadow-sm inline-flex items-center gap-2">
                    <i class="fa-solid fa-reply text-xs"></i> Reply via Email
                </a>
                
                <form method="POST" action="" id="modalMarkReadForm" class="inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="message_id" id="modalFormMessageId" value="">
                    <button type="submit" name="mark_read" id="modalMarkReadBtn" class="px-4 py-2 rounded-xl bg-brandNavy hover:bg-slate-900 text-white font-bold text-xs transition-colors shadow-sm inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-xs"></i> Mark as Read
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Modal Functions
    const modal = document.getElementById('inquiryModal');
    const modalBox = modal.querySelector('div');

    function openInquiryModal(data) {
        document.getElementById('modalInquiryId').textContent = 'Inquiry #' + data.id;
        document.getElementById('modalSubject').textContent = data.subject;
        document.getElementById('modalSenderName').textContent = data.name;
        
        const emailEl = document.getElementById('modalSenderEmail');
        emailEl.textContent = data.email;
        emailEl.href = 'mailto:' + encodeURIComponent(data.email) + '?subject=' + encodeURIComponent('Re: ' + data.subject);

        const mailtoBtn = document.getElementById('modalMailtoBtn');
        mailtoBtn.href = 'mailto:' + encodeURIComponent(data.email) + '?subject=' + encodeURIComponent('Re: ' + data.subject);

        document.getElementById('modalDate').textContent = data.created_at;
        document.getElementById('modalMessageBody').textContent = data.message;
        document.getElementById('modalFormMessageId').value = data.id;

        const badge = document.getElementById('modalStatusBadge');
        const markReadBtn = document.getElementById('modalMarkReadBtn');

        if (data.is_read) {
            badge.className = 'inline-flex items-center gap-1 text-[10px] font-bold text-slate-500 bg-slate-200 px-2 py-0.5 rounded-full';
            badge.innerHTML = '<i class="fa-solid fa-check-double text-[9px]"></i> Read';
            markReadBtn.style.display = 'none';
        } else {
            badge.className = 'inline-flex items-center gap-1 text-[10px] font-bold text-brandOrange bg-orange-100 px-2 py-0.5 rounded-full';
            badge.innerHTML = '<i class="fa-solid fa-envelope text-[9px]"></i> Unread';
            markReadBtn.style.display = 'inline-flex';
        }

        modal.classList.remove('opacity-0', 'pointer-events-none');
        modalBox.classList.remove('scale-95');
        modalBox.classList.add('scale-100');
    }

    function closeInquiryModal() {
        modal.classList.add('opacity-0', 'pointer-events-none');
        modalBox.classList.remove('scale-100');
        modalBox.classList.add('scale-95');
    }

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeInquiryModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !modal.classList.contains('pointer-events-none')) {
            closeInquiryModal();
        }
    });

    // Admin Sidebar Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('adminSidebar');
        const desktopToggle = document.getElementById('sidebarToggle');
        const mobileToggle = document.getElementById('mobileSidebarToggle');

        if (window.innerWidth >= 768) {
            const isCollapsed = localStorage.getItem('yocor_admin_sidebar_collapsed') === 'true';
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
        }

        if (desktopToggle && sidebar) {
            desktopToggle.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem('yocor_admin_sidebar_collapsed', sidebar.classList.contains('collapsed'));
            });
        }

        if (mobileToggle && sidebar) {
            mobileToggle.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-hidden');
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
