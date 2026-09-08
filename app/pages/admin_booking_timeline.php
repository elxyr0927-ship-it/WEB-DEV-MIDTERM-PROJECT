<?php
/**
 * YOCOR Express Logistics - Admin Live Checkpoint & Status Timeline Manager
 * Allows administrators to continuously update hub locations and rider notes with a full historical audit trail.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();
$error = '';
$success = '';

$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$bookingId) {
    header('Location: admin_dashboard.php#all-bookings');
    exit;
}

// Handle adding new live checkpoint update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_checkpoint'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security check failed: CSRF token invalid or expired. Please refresh the page.';
    } else {
        $checkpoint = trim($_POST['checkpoint'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (empty($checkpoint)) {
            $error = 'Hub/Location name is required.';
        } elseif (mb_strlen($checkpoint) > 150) {
            $error = 'Hub/Location name cannot exceed 150 characters.';
        } elseif (mb_strlen($notes) > 500) {
            $error = 'Rider/Status note cannot exceed 500 characters.';
        } else {
            try {
                $statusStmt = $pdo->prepare("SELECT status, current_checkpoint FROM bookings WHERE id = :id");
                $statusStmt->execute(['id' => $bookingId]);
                $currBooking = $statusStmt->fetch(PDO::FETCH_ASSOC);

                if (!$currBooking) {
                    $error = 'Target booking record not found.';
                } elseif ($currBooking['status'] === 'delivered') {
                    $error = 'Shipment is already marked as Delivered. Checkpoints are archived and locked.';
                } else {
                    $currStatus = $currBooking['status'];
                    $pdo->beginTransaction();

                    // 1. Update current live checkpoint in bookings
                    $uStmt = $pdo->prepare("
                        UPDATE bookings 
                        SET current_checkpoint = :cp, tracker_notes = :notes 
                        WHERE id = :id
                    ");
                    $uStmt->execute([
                        'cp' => $checkpoint,
                        'notes' => !empty($notes) ? $notes : null,
                        'id' => $bookingId
                    ]);

                    // 2. Append to status_history to maintain continuous audit trail
                    $hStmt = $pdo->prepare("
                        INSERT INTO status_history (booking_id, old_status, new_status, changed_by, checkpoint, notes)
                        VALUES (:b_id, :old_status, :new_status, :uid, :cp, :notes)
                    ");
                    $hStmt->execute([
                        'b_id' => $bookingId,
                        'old_status' => $currStatus,
                        'new_status' => $currStatus,
                        'uid' => $_SESSION['user_id'],
                        'cp' => $checkpoint,
                        'notes' => !empty($notes) ? $notes : null
                    ]);

                    $pdo->commit();
                    $success = "Live checkpoint updated successfully to '{$checkpoint}'. Customers can immediately view this on their tracking portal.";
                    logAdminAction($pdo, (int)$_SESSION['user_id'], 'UPDATE_CHECKPOINT', "Added live checkpoint '{$checkpoint}' for Booking #{$bookingId}");
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Checkpoint update error: " . $e->getMessage());
                $error = 'Database error while saving checkpoint: ' . $e->getMessage();
            }
        }
    }
}

// Fetch booking details
$bookingStmt = $pdo->prepare("
    SELECT b.*, s.name AS service_name, u.username, u.email 
    FROM bookings b 
    JOIN services s ON b.service_id = s.id 
    JOIN user u ON b.user_id = u.id 
    WHERE b.id = :id
");
$bookingStmt->execute(['id' => $bookingId]);
$booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: admin_dashboard.php#all-bookings');
    exit;
}

// Fetch full status and checkpoint history
$historyStmt = $pdo->prepare("
    SELECT h.*, u.username AS changer_name 
    FROM status_history h 
    LEFT JOIN user u ON h.changed_by = u.id 
    WHERE h.booking_id = :b_id 
    ORDER BY h.created_at DESC
");
$historyStmt->execute(['b_id' => $bookingId]);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Checkpoint Manager - " . htmlspecialchars($booking['tracking_code']) . " | YOCOR Express Admin";
include __DIR__ . '/../includes/header.php';
?>

<div class="flex flex-col md:flex-row min-h-[calc(100vh-80px)] w-full bg-slate-50">

    <!-- Responsive Admin Sidebar -->
    <aside class="w-full md:w-64 bg-white border-r border-slate-200 p-4 sm:p-5 md:py-6 flex-shrink-0">
        <div class="mb-5 pb-4 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-brandNavy text-white flex items-center justify-center font-bold text-lg shadow-sm flex-shrink-0">
                <i class="fa-solid fa-timeline text-brandOrange"></i>
            </div>
            <div>
                <h3 class="font-black text-brandNavy text-sm">Checkpoint Ops</h3>
                <p class="text-[11px] text-slate-500 font-medium truncate">Parcel #<?= $bookingId ?></p>
            </div>
        </div>

        <nav class="space-y-2 text-xs font-bold">
            <a href="admin_dashboard.php#all-bookings" class="flex items-center gap-2.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors">
                <i class="fa-solid fa-arrow-left text-slate-500"></i>
                <span>Back to All Shipments</span>
            </a>

            <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl text-brandOrange bg-orange-50 hover:bg-orange-100 transition-colors">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-satellite-dish"></i>
                    <span>Customer View</span>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
            </a>

            <a href="receipt.php?id=<?= (int)$booking['id'] ?>" target="_blank" class="flex items-center justify-between px-3 py-2 rounded-xl text-slate-600 hover:bg-slate-100 transition-colors">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-slate-400"></i>
                    <span>Waybill Receipt</span>
                </span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
            </a>
        </nav>

        <!-- Booking Specs Card -->
        <div class="mt-6 pt-5 border-t border-slate-100 text-xs space-y-2.5 text-slate-600">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block">Shipment Summary</span>
            <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1.5 font-medium text-[11px]">
                <div class="flex justify-between">
                    <span class="text-slate-400">Tracking:</span>
                    <span class="font-mono font-black text-brandNavy"><?= htmlspecialchars($booking['tracking_code']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Customer:</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['username']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Service:</span>
                    <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['service_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Weight:</span>
                    <span class="font-bold text-slate-800"><?= $booking['weight'] ?> kg</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Status:</span>
                    <span class="font-black uppercase <?= $booking['status'] === 'delivered' ? 'text-emerald-600' : 'text-brandOrange' ?>"><?= $booking['status'] ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Payment:</span>
                    <span class="font-bold uppercase <?= ($booking['payment_status'] ?? 'unpaid') === 'paid' ? 'text-emerald-600' : 'text-amber-600' ?>"><?= $booking['payment_status'] ?? 'unpaid' ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="flex-1 min-w-0 p-4 sm:p-6 md:p-8">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200 mb-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-brandOrange font-black text-[10px] uppercase tracking-wider">Live Checkpoints</span>
                    <span class="font-mono text-xs font-bold text-slate-400">ID #<?= $booking['id'] ?></span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-brandNavy font-heading flex items-center gap-2">
                    <span><?= htmlspecialchars($booking['tracking_code']) ?></span>
                    <?php if (!empty($booking['current_checkpoint'])): ?>
                        <span class="text-xs font-bold text-emerald-800 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span>At: <?= htmlspecialchars($booking['current_checkpoint']) ?></span>
                        </span>
                    <?php endif; ?>
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Route: <strong class="text-slate-700"><?= htmlspecialchars($booking['pickup_address'] ?? 'Origin') ?></strong> 
                    &rarr; <strong class="text-slate-700"><?= htmlspecialchars($booking['delivery_address'] ?? 'Destination') ?></strong>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" target="_blank" class="px-3.5 py-2 rounded-xl bg-brandNavy text-white font-bold text-xs hover:bg-slate-900 transition-colors shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-location-crosshairs text-brandOrange"></i>
                    <span>Open Live Tracker</span>
                </a>
            </div>
        </div>

        <!-- Feedback Messages -->
        <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl mb-6 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-6 text-xs font-bold flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Checkpoint Update Form (1 col) -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sticky top-20">
                    <div class="flex items-center gap-2 mb-4 pb-3 border-b border-slate-100">
                        <div class="w-7 h-7 rounded-lg bg-orange-50 text-brandOrange flex items-center justify-center font-bold text-xs">
                            <i class="fa-solid fa-plus"></i>
                        </div>
                        <div>
                            <h2 class="font-extrabold text-brandNavy text-sm">Post Live Checkpoint</h2>
                            <p class="text-[10px] text-slate-400">Updates the client's tracking status instantly</p>
                        </div>
                    </div>

                    <?php if ($booking['status'] === 'delivered'): ?>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-center space-y-2">
                            <i class="fa-solid fa-lock text-slate-400 text-2xl"></i>
                            <h4 class="font-bold text-xs text-slate-700">Parcel Delivered &amp; Locked</h4>
                            <p class="text-[11px] text-slate-500 leading-relaxed">
                                This shipment has reached its final destination. No further checkpoint entries can be posted.
                            </p>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="" class="space-y-4">
                            <?= csrfField() ?>
                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Current Hub / Location <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <i class="fa-solid fa-warehouse absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                    <input type="text" name="checkpoint" required 
                                           placeholder="e.g., YOCOR Pasay Sorting Hub, Sorting Center" 
                                           class="w-full pl-8 pr-3 py-2 text-xs font-semibold text-slate-800 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all">
                                </div>
                                <p class="text-[10px] text-slate-400 mt-1">Specify warehouse name, transit hub, or arrival station.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 mb-1.5">
                                    Rider / Operational Message <span class="text-slate-400 font-normal">(Optional)</span>
                                </label>
                                <textarea name="notes" rows="3" 
                                          placeholder="e.g., Package scanned at conveyor. Departing to regional destination with Rider Juan (0917-XXX-XXXX)..."
                                          class="w-full px-3 py-2 text-xs text-slate-800 bg-slate-50 border border-slate-300 rounded-xl focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all"></textarea>
                                <p class="text-[10px] text-slate-400 mt-0.5">Visible to the client in their live courier feed.</p>
                            </div>

                            <button type="submit" name="add_checkpoint" 
                                    class="w-full py-2.5 px-4 rounded-xl bg-brandOrange hover:bg-orange-600 text-white font-extrabold text-xs transition-all shadow-sm hover:shadow flex items-center justify-center gap-2">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Publish Checkpoint Update</span>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Current active hub preview card -->
                    <div class="mt-6 pt-4 border-t border-slate-100">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block mb-2">Latest Active Hub</span>
                        <div class="bg-blue-50/70 border border-blue-200/80 p-3 rounded-xl space-y-1">
                            <div class="flex items-center gap-1.5 text-xs font-black text-brandNavy">
                                <i class="fa-solid fa-location-dot text-brandOrange"></i>
                                <span><?= htmlspecialchars($booking['current_checkpoint'] ?? 'Not set yet') ?></span>
                            </div>
                            <?php if (!empty($booking['tracker_notes'])): ?>
                                <p class="text-[11px] text-slate-600 italic">"<?= htmlspecialchars($booking['tracker_notes']) ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Full Audit Timeline Display (2 cols) -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 sm:p-6">
                    <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-100">
                        <div>
                            <h2 class="font-extrabold text-brandNavy text-base flex items-center gap-2">
                                <i class="fa-solid fa-clock-rotate-left text-brandOrange"></i>
                                <span>Live Audit Trail &amp; Checkpoint History</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">Chronological record of all hub movements and status transitions</p>
                        </div>
                        <span class="text-xs font-bold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">
                            <?= count($history) ?> log entries
                        </span>
                    </div>

                    <?php if (empty($history)): ?>
                        <div class="text-center py-12 text-slate-400 space-y-2">
                            <i class="fa-solid fa-route text-3xl text-slate-300"></i>
                            <p class="text-xs font-semibold">No audit logs or checkpoints recorded yet for this booking.</p>
                            <p class="text-[11px] text-slate-400">Use the form on the left to submit the initial checkpoint update.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($history as $index => $entry): 
                                $isStatusChange = ($entry['old_status'] !== $entry['new_status']);
                                $isCheckpoint = !empty($entry['checkpoint']) || (!empty($entry['notes']) && !$isStatusChange);
                            ?>
                                <div class="relative pl-7 pb-4 border-l-2 border-slate-200 last:border-transparent last:pb-0">
                                    <!-- Dot icon -->
                                    <div class="absolute -left-2.5 top-0 w-5 h-5 rounded-full border-2 border-white flex items-center justify-center shadow-xs <?= $isStatusChange ? 'bg-blue-600 text-white' : 'bg-emerald-500 text-white' ?>">
                                        <?php if ($isStatusChange): ?>
                                            <i class="fa-solid fa-arrows-rotate text-[8px]"></i>
                                        <?php else: ?>
                                            <i class="fa-solid fa-location-dot text-[8px]"></i>
                                        <?php endif; ?>
                                    </div>

                                    <div class="bg-slate-50 hover:bg-slate-100/80 p-3.5 rounded-xl border border-slate-200 transition-colors">
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                                            <div class="flex items-center gap-2">
                                                <time class="font-mono text-[11px] font-bold text-slate-500">
                                                    <?= date('M d, Y - h:i A', strtotime($entry['created_at'])) ?>
                                                </time>
                                                <?php if ($index === 0): ?>
                                                    <span class="px-2 py-0.2 rounded-full bg-brandOrange/10 text-brandOrange font-black text-[9px] uppercase tracking-wider">Latest</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="flex items-center gap-1.5">
                                                <?php if ($isStatusChange): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 font-extrabold text-[10px]">
                                                        Status Transition
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($isCheckpoint): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 font-extrabold text-[10px]">
                                                        Hub Checkpoint
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <?php if ($isStatusChange): ?>
                                            <div class="text-xs font-bold text-slate-800 flex items-center gap-1.5 mt-1">
                                                <span class="uppercase text-slate-500"><?= htmlspecialchars($entry['old_status']) ?></span>
                                                <i class="fa-solid fa-arrow-right text-slate-400 text-[10px]"></i>
                                                <span class="uppercase text-brandOrange font-black"><?= htmlspecialchars($entry['new_status']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entry['checkpoint'])): ?>
                                            <div class="text-xs font-extrabold text-brandNavy flex items-center gap-1.5 mt-1">
                                                <i class="fa-solid fa-location-dot text-brandOrange text-xs"></i>
                                                <span><?= htmlspecialchars($entry['checkpoint']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($entry['notes'])): ?>
                                            <p class="text-xs text-slate-600 italic bg-white p-2 rounded-lg border border-slate-200/60 mt-1.5">
                                                &ldquo;<?= htmlspecialchars($entry['notes']) ?>&rdquo;
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($entry['changer_name'])): ?>
                                            <div class="text-[10px] text-slate-400 mt-2 flex items-center gap-1">
                                                <i class="fa-solid fa-user-shield text-[9px]"></i>
                                                <span>Updated by admin <strong><?= htmlspecialchars($entry['changer_name']) ?></strong></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
