<?php
$pageTitle = "Track Shipment Status | YOCOR Express Logistics";
$activeNav = "tracking";

require_once __DIR__ . '/../database/config.php';

// Accept tracking by ?id=... or ?code=... or ?tracking_no=... or form post
$searchQuery = trim($_GET['id'] ?? ($_GET['code'] ?? ($_GET['tracking_no'] ?? ($_POST['tracking_no'] ?? ''))));

$booking = null;
$error = '';

if (!empty($searchQuery)) {
    try {
        $pdo = getConnection();
        
        // Check if input is formatted as tracking code (e.g. YR-XXXXXX) or a pure number
        if (stripos($searchQuery, 'YR-') === 0 || preg_match('/^[A-Za-z0-9-]+$/', $searchQuery)) {
            $stmt = $pdo->prepare("
                SELECT b.*, s.name AS service_name, u.username 
                FROM bookings b 
                JOIN services s ON b.service_id = s.id 
                JOIN user u ON b.user_id = u.id 
                WHERE b.tracking_code = :query OR b.id = :num_id
            ");
            $numericFallback = is_numeric($searchQuery) ? (int)$searchQuery : 0;
            $stmt->execute([
                'query' => strtoupper($searchQuery),
                'num_id' => $numericFallback
            ]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($booking) {
            // Fetch status audit history timeline
            $hStmt = $pdo->prepare("
                SELECT h.*, u.username AS changer_name 
                FROM status_history h 
                LEFT JOIN user u ON h.changed_by = u.id 
                WHERE h.booking_id = :b_id 
                ORDER BY h.created_at ASC
            ");
            $hStmt->execute(['b_id' => $booking['id']]);
            $statusHistory = $hStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "No shipment found matching Booking Code or ID '" . htmlspecialchars($searchQuery) . "'. Please verify your tracking code and try again.";
        }
    } catch (PDOException $e) {
        $error = "Database query error: " . $e->getMessage();
    }
}

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-8 bg-slate-50">
  <div class="max-w-4xl mx-auto px-4 sm:px-6">
    
    <!-- Page Header Banner -->
    <div class="text-center max-w-2xl mx-auto mb-6 space-y-1">
      <div class="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-orange-100 text-brandOrange font-bold text-[11px] uppercase tracking-widest">
        <i class="fa-solid fa-satellite-dish text-xs"></i> GPS Tracking Engine
      </div>
      <h1 class="text-2xl font-black text-brandNavy font-heading">Shipment Tracking Center</h1>
      <p class="text-slate-600 text-xs">Enter your unique Booking Tracking Code (e.g. <strong>YR-A7F3B9</strong>) to view real-time courier status.</p>
    </div>

    <!-- Search Box Card -->
    <div class="bg-white p-4 sm:p-5 rounded-xl shadow-sm border border-slate-200 mb-6 max-w-xl mx-auto">
      <form method="GET" action="" class="flex flex-col sm:flex-row gap-2.5">
        <div class="relative flex-grow">
          <i class="fa-solid fa-barcode absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
          <input type="text" name="id" value="<?= htmlspecialchars($searchQuery) ?>" 
                 placeholder="Enter Booking ID (e.g. YR-A7F3B9)" required 
                 class="w-full pl-9 pr-3 py-2 text-slate-900 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-semibold transition-all uppercase">
        </div>
        <button type="submit" 
                class="bg-brandOrange hover:bg-orange-600 text-white font-bold py-2 px-5 rounded-lg transition-all flex items-center justify-center gap-1.5 text-xs shadow-sm brand-glow-orange">
          <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
          <span>Track</span>
        </button>
      </form>

      <?php if (!empty($_SESSION['user_id'])): ?>
        <p class="text-center text-[11px] text-slate-400 mt-2.5">
          Tip: You can also click <strong>"Track"</strong> directly on any row in your <a href="customer_dashboard.php" class="text-brandOrange font-bold hover:underline">Orders Dashboard</a>.
        </p>
      <?php endif; ?>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
      <div class="max-w-xl mx-auto bg-rose-50 border border-rose-200 text-rose-800 p-3 rounded-xl text-xs font-bold flex items-center gap-2 mb-6 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <!-- Live Real Database Result Section -->
    <?php if ($booking): ?>
      <?php 
        $status = strtolower($booking['status']);
        $stepOrder = ['pending' => 1, 'dispatched' => 2, 'delivered' => 3, 'cancelled' => 0];
        $currentStep = $stepOrder[$status] ?? 1;
        $displayCode = !empty($booking['tracking_code']) ? $booking['tracking_code'] : ('YR-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT));
      ?>

      <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        
        <!-- Status Top Header -->
        <div class="bg-gradient-to-r from-brandNavy to-slate-900 text-white p-5 sm:p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
          <div>
            <span class="text-[10px] text-brandOrange font-bold uppercase tracking-wider block mb-0.5">Waybill Tracking Code</span>
            <h2 class="text-xl font-black font-mono tracking-wide text-white flex items-center gap-2">
                <span><?= htmlspecialchars($displayCode) ?></span>
            </h2>
            <p class="text-xs text-slate-300 mt-1 flex flex-wrap items-center gap-2">
              <span><i class="fa-solid fa-box text-brandOrange"></i> <?= htmlspecialchars($booking['service_name']) ?></span>
              <span>•</span>
              <span>Customer: <?= htmlspecialchars($booking['username']) ?></span>
              <span>•</span>
              <span>Booked: <?= date('M d, Y h:i A', strtotime($booking['created_at'])) ?></span>
            </p>
          </div>

          <!-- Status Pill & Payment Info -->
          <div class="flex flex-wrap items-center gap-2">
            <?php if (($booking['payment_status'] ?? 'unpaid') === 'paid'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-xs font-bold">
                <i class="fa-solid fa-circle-check text-xs"></i> Paid
              </span>
            <?php elseif (($booking['payment_status'] ?? 'unpaid') === 'under_review'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-500/20 text-blue-300 border border-blue-400/30 text-xs font-bold">
                <i class="fa-solid fa-hourglass-half text-xs"></i> Payment Under Review
              </span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-400/30 text-xs font-bold">
                <i class="fa-solid fa-clock text-xs"></i> Awaiting Payment
              </span>
              <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$booking['user_id']): ?>
                <a href="payment.php?id=<?= $booking['id'] ?>" class="bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs px-3 py-1 rounded-lg transition-colors shadow">
                  Pay Now
                </a>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($status === 'delivered'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold uppercase tracking-wider">
                <i class="fa-solid fa-circle-check"></i> Delivered
              </span>
            <?php elseif ($status === 'cancelled'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-500/20 border border-rose-500/40 text-rose-300 text-xs font-bold uppercase tracking-wider">
                <i class="fa-solid fa-circle-xmark"></i> Cancelled
              </span>
            <?php elseif ($status === 'dispatched'): ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-blue-500/20 border border-blue-500/40 text-blue-300 text-xs font-bold uppercase tracking-wider">
                <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span> In Transit
              </span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300 text-xs font-bold uppercase tracking-wider">
                <i class="fa-solid fa-clock"></i> Order Pending
              </span>
            <?php endif; ?>
          </div>
        </div>

        <?php if (($booking['payment_status'] ?? 'unpaid') === 'under_review'): ?>
          <div class="bg-blue-50 border-b border-blue-200 px-5 py-3 text-blue-950 text-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 font-medium">
              <i class="fa-solid fa-hourglass-half text-blue-600 text-sm"></i>
              <span><strong>Notice:</strong> Payment has been submitted and is awaiting confirmation by an administrator. Courier dispatch unlocks upon confirmation.</span>
            </div>
          </div>
        <?php elseif (($booking['payment_status'] ?? 'unpaid') !== 'paid'): ?>
          <div class="bg-amber-50 border-b border-amber-200 px-5 py-3 text-amber-900 text-xs flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 font-medium">
              <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
              <span><strong>Notice:</strong> This order is unpaid. Parcels will only be dispatched once settlement has been cleared.</span>
            </div>
            <?php if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$booking['user_id']): ?>
              <a href="payment.php?id=<?= $booking['id'] ?>" class="text-brandOrange hover:underline font-bold whitespace-nowrap">
                Complete Payment &rarr;
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Progress Stepper Indicator -->
        <div class="p-6 bg-slate-50 border-b border-slate-200">
          <div class="max-w-2xl mx-auto grid grid-cols-3 gap-2 text-center text-xs font-bold relative">
            
            <!-- Step 1 -->
            <div class="space-y-2">
              <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center <?= ($currentStep >= 1 && $status !== 'cancelled') ? 'bg-brandOrange text-white' : 'bg-slate-200 text-slate-500' ?> shadow-sm">
                <i class="fa-solid fa-clipboard-check text-xs"></i>
              </div>
              <p class="<?= ($currentStep >= 1 && $status !== 'cancelled') ? 'text-brandNavy font-extrabold' : 'text-slate-400' ?>">1. Order Placed</p>
              <p class="text-[10px] text-slate-500 font-normal">Pending courier pickup</p>
            </div>

            <!-- Step 2 -->
            <div class="space-y-2">
              <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center <?= ($currentStep >= 2 && $status !== 'cancelled') ? 'bg-brandOrange text-white' : 'bg-slate-200 text-slate-500' ?> shadow-sm">
                <i class="fa-solid fa-truck-fast text-xs"></i>
              </div>
              <p class="<?= ($currentStep >= 2 && $status !== 'cancelled') ? 'text-brandNavy font-extrabold' : 'text-slate-400' ?>">2. In Transit</p>
              <p class="text-[10px] text-slate-500 font-normal">Dispatched with courier</p>
            </div>

            <!-- Step 3 -->
            <div class="space-y-2">
              <div class="w-8 h-8 mx-auto rounded-full flex items-center justify-center <?= ($currentStep >= 3 && $status !== 'cancelled') ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500' ?> shadow-sm">
                <i class="fa-solid fa-house-circle-check text-xs"></i>
              </div>
              <p class="<?= ($currentStep >= 3 && $status !== 'cancelled') ? 'text-emerald-700 font-extrabold' : 'text-slate-400' ?>">3. Delivered</p>
              <p class="text-[10px] text-slate-500 font-normal">Handed over to recipient</p>
            </div>
          </div>
        </div>

        <!-- Checkpoint & Live Notes Banner -->
        <?php if (!empty($booking['current_checkpoint']) || !empty($booking['tracker_notes'])): ?>
          <div class="bg-blue-50/70 border-b border-blue-200/80 px-6 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
            <div class="space-y-1">
              <?php if (!empty($booking['current_checkpoint'])): ?>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-blue-900 uppercase tracking-wider text-[10px] bg-blue-100 px-2 py-0.5 rounded">Current Location:</span>
                  <span class="font-extrabold text-blue-950 text-sm flex items-center gap-1.5">
                    <i class="fa-solid fa-location-dot text-brandOrange"></i>
                    <?= htmlspecialchars($booking['current_checkpoint']) ?>
                  </span>
                </div>
              <?php endif; ?>
              <?php if (!empty($booking['tracker_notes'])): ?>
                <p class="text-blue-800 text-xs italic">
                  &ldquo;<?= htmlspecialchars($booking['tracker_notes']) ?>&rdquo;
                </p>
              <?php endif; ?>
            </div>
            <span class="text-[10px] text-blue-600 font-semibold bg-white px-2.5 py-1 rounded-full border border-blue-200">
              Live Courier Feed
            </span>
          </div>
        <?php endif; ?>

        <!-- Content Body: Delivery Details -->
        <div class="p-6 sm:p-8 grid md:grid-cols-2 gap-6 text-xs">
          
          <!-- Origin & Destination Cards -->
          <div class="space-y-4">
            <h4 class="font-black text-brandNavy uppercase tracking-wider text-xs border-b border-slate-200 pb-2">
              Route & Dispatch Information
            </h4>
            
            <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-3 shadow-sm">
              <div class="flex items-start gap-3">
                <div class="w-7 h-7 rounded-xl bg-orange-100 text-brandOrange flex items-center justify-center font-bold flex-shrink-0 mt-0.5">
                  <i class="fa-solid fa-location-dot text-xs"></i>
                </div>
                <div>
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Pickup Address</span>
                  <p class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($booking['pickup_address']) ?></p>
                  <span class="text-[10px] text-brandOrange font-semibold"><?= htmlspecialchars($booking['pickup_region'] ?? 'Luzon') ?></span>
                </div>
              </div>

              <div class="border-t border-slate-100 pt-3 flex items-start gap-3">
                <div class="w-7 h-7 rounded-xl bg-brandNavy/10 text-brandNavy flex items-center justify-center font-bold flex-shrink-0 mt-0.5">
                  <i class="fa-solid fa-flag-checkered text-xs"></i>
                </div>
                <div>
                  <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Delivery Destination</span>
                  <p class="font-bold text-slate-800 text-sm mt-0.5"><?= htmlspecialchars($booking['delivery_address']) ?></p>
                  <span class="text-[10px] text-brandOrange font-semibold"><?= htmlspecialchars($booking['delivery_region'] ?? 'Luzon') ?></span>
                </div>
              </div>
            </div>

            <!-- Status Timeline -->
            <?php if (!empty($statusHistory)): ?>
              <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
                <h5 class="font-black text-brandNavy uppercase tracking-wider text-[11px] border-b border-slate-100 pb-2">
                  <i class="fa-solid fa-clock-rotate-left text-brandOrange mr-1"></i> Audit Status Timeline
                </h5>
                <ol class="relative border-l border-slate-200 ml-2.5 space-y-3 text-xs">
                  <?php foreach ($statusHistory as $h): ?>
                    <li class="ml-4">
                      <div class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full border-2 border-white bg-brandOrange"></div>
                      <time class="mb-0.5 text-[10px] font-normal leading-none text-slate-400 block">
                        <?= date('M d, Y - h:i A', strtotime($h['created_at'])) ?>
                      </time>
                      <h6 class="font-extrabold text-slate-900 text-xs">
                        <?= ucfirst(htmlspecialchars($h['new_status'])) ?>
                      </h6>
                      <?php if (!empty($h['checkpoint'])): ?>
                        <p class="text-[11px] text-brandNavy font-semibold">Checkpoint: <?= htmlspecialchars($h['checkpoint']) ?></p>
                      <?php endif; ?>
                      <?php if (!empty($h['notes'])): ?>
                        <p class="text-[11px] text-slate-500 mt-0.5"><?= htmlspecialchars($h['notes']) ?></p>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </div>
            <?php endif; ?>
          </div>

          <!-- Parcel Specifications -->
          <div class="space-y-4">
            <h4 class="font-black text-brandNavy uppercase tracking-wider text-xs border-b border-slate-200 pb-2">
              Parcel & Service Specs
            </h4>

            <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-2.5 shadow-sm">
              <div class="flex justify-between py-1 border-b border-slate-100">
                <span class="text-slate-500">Service Category:</span>
                <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['service_name']) ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100">
                <span class="text-slate-500">Total Weight:</span>
                <span class="font-bold text-slate-800"><?= $booking['weight'] ?> kg</span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100">
                <span class="text-slate-500">Total Freight Cost:</span>
                <span class="font-extrabold text-brandOrange">₱<?= number_format((float)($booking['total_cost'] ?? 0), 2) ?></span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100">
                <span class="text-slate-500">Payment Status:</span>
                <span class="font-bold <?= ($booking['payment_status'] ?? 'unpaid') === 'paid' ? 'text-emerald-600' : 'text-amber-600' ?>">
                  <?= strtoupper(htmlspecialchars($booking['payment_status'] ?? 'unpaid')) ?>
                </span>
              </div>
              <div class="flex justify-between py-1 border-b border-slate-100">
                <span class="text-slate-500">Dispatch Status:</span>
                <span class="font-bold uppercase text-brandOrange"><?= htmlspecialchars($booking['status']) ?></span>
              </div>
              <div class="flex justify-between py-1">
                <span class="text-slate-500">Last System Update:</span>
                <span class="font-bold text-slate-700"><?= date('F d, Y - h:i A', strtotime($booking['created_at'])) ?></span>
              </div>
            </div>

            <!-- Hotline Box -->
            <div class="p-3.5 rounded-2xl bg-orange-50 border border-orange-200 text-xs flex items-center gap-3">
              <i class="fa-solid fa-headset text-brandOrange text-xl"></i>
              <div>
                <p class="font-bold text-brandNavy">Need dispatch assistance?</p>
                <p class="text-slate-600">Call 24/7 Dispatch Desk: <strong class="text-brandOrange">+1 (800) 555-YOCOR</strong></p>
              </div>
            </div>
          </div>

        </div>

      </div>
    <?php endif; ?>

  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
