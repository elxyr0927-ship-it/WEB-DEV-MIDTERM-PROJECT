<?php
// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Require login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();

// Get booking parameter (by ?id= or ?code=)
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$trackingCode = trim($_GET['code'] ?? '');

if (!$bookingId && empty($trackingCode)) {
    header('Location: customer_dashboard.php');
    exit;
}

// Fetch booking with user & service details
if ($bookingId) {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS service_name, s.base_price, s.price_per_kg, u.username, u.email, u.phone, u.age 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        JOIN user u ON b.user_id = u.id 
        WHERE b.id = :id
    ");
    $stmt->execute(['id' => $bookingId]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS service_name, s.base_price, s.price_per_kg, u.username, u.email, u.phone, u.age 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        JOIN user u ON b.user_id = u.id 
        WHERE b.tracking_code = :code
    ");
    $stmt->execute(['code' => $trackingCode]);
}
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Receipt Not Found | YOCOR Express</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <script>
        tailwind.config = {
          theme: {
            extend: {
              colors: {
                brandNavy: '#1D3563',
                brandOrange: '#F37B23',
              }
            }
          }
        }
      </script>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
      <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-slate-200 p-8 text-center space-y-5">
        <div class="w-16 h-16 mx-auto bg-amber-100 rounded-2xl flex items-center justify-center text-amber-600 text-2xl shadow-sm">
          <i class="fa-solid fa-file-circle-question"></i>
        </div>
        <div>
          <h1 class="text-xl font-black text-brandNavy">Receipt Not Found</h1>
          <p class="text-slate-500 text-xs mt-1.5 leading-relaxed">We couldn't locate a shipment waybill matching this ID or tracking number. It may have been canceled or belongs to another session.</p>
        </div>
        <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-2.5">
          <a href="<?= ($_SESSION['role'] ?? '') === 'admin' ? 'admin_dashboard.php' : 'customer_dashboard.php' ?>" 
             class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow-sm">
            <i class="fa-solid fa-boxes-stacked text-[11px]"></i>
            <span>Back to Dashboard</span>
          </a>
          <a href="booking.php" 
             class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs px-4 py-2.5 rounded-xl transition-all">
            <i class="fa-solid fa-plus text-[11px]"></i>
            <span>Book New Delivery</span>
          </a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

// Security Authorization: Only booking owner or admin can view this receipt
if ($_SESSION['role'] !== 'admin' && (int)$_SESSION['user_id'] !== (int)$booking['user_id']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Access Denied | YOCOR Express</title>
      <script src="https://cdn.tailwindcss.com"></script>
      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
      <script>
        tailwind.config = {
          theme: {
            extend: {
              colors: {
                brandNavy: '#1D3563',
                brandOrange: '#F37B23',
              }
            }
          }
        }
      </script>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
      <div class="max-w-md w-full bg-white rounded-2xl shadow-lg border border-slate-200 p-8 text-center space-y-4">
        <div class="w-16 h-16 mx-auto bg-rose-100 rounded-2xl flex items-center justify-center text-rose-600 text-2xl shadow-sm">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
          <h1 class="text-xl font-black text-brandNavy">Access Restricted</h1>
          <p class="text-slate-500 text-xs mt-1.5 leading-relaxed">You are not authorized to view this receipt. It belongs to another customer account.</p>
        </div>
        <div class="pt-2">
          <a href="<?= ($_SESSION['role'] ?? '') === 'admin' ? 'admin_dashboard.php' : 'customer_dashboard.php' ?>" 
             class="inline-flex items-center gap-2 bg-brandNavy hover:bg-slate-900 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition-all shadow">
            <i class="fa-solid fa-arrow-left text-[11px]"></i>
            <span>Back to Dashboard</span>
          </a>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
}

$displayCode = !empty($booking['tracking_code']) ? $booking['tracking_code'] : ('YR-' . str_pad($booking['id'], 6, '0', STR_PAD_LEFT));
$isPaid = (($booking['payment_status'] ?? 'unpaid') === 'paid');

// Calculate itemized breakdown
$weight = (float)$booking['weight'];
$basePrice = (float)($booking['base_price'] ?? 100);
$pricePerKg = (float)($booking['price_per_kg'] ?? 30);
$weightCost = $weight * $pricePerKg;
$totalCost = (float)($booking['total_cost'] ?? ($basePrice + $weightCost));
$transitFee = max(0, $totalCost - ($basePrice + $weightCost));

$pageTitle = "Official Waybill Receipt - {$displayCode} | YOCOR Express";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brandNavy: '#1D3563',
            brandOrange: '#F37B23',
          }
        }
      }
    }
  </script>
  <style>
    @media print {
      .no-print { display: none !important; }
      body { background: #fff !important; }
      .print-shadow-none { box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    }
  </style>
</head>
<body class="bg-slate-100 text-slate-900 py-6 sm:py-8 px-3 sm:px-6 font-sans antialiased min-h-screen flex flex-col justify-between overflow-x-hidden">

  <div class="max-w-3xl mx-auto w-full">

    <!-- Action Bar (Hide on Print) - Mobile stacked -->
    <div class="no-print flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-5">
      <a href="<?= $_SESSION['role'] === 'admin' ? 'admin_dashboard.php' : 'customer_dashboard.php' ?>" 
         class="inline-flex items-center justify-center sm:justify-start gap-1.5 text-xs font-bold text-slate-600 hover:text-brandOrange transition-colors min-h-[44px] px-2">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to <?= $_SESSION['role'] === 'admin' ? 'Admin Center' : 'My Orders' ?></span>
      </a>

      <div class="flex flex-wrap items-center justify-center sm:justify-end gap-2">
        <?php if (!$isPaid && $_SESSION['role'] !== 'admin'): ?>
          <a href="payment.php?id=<?= $booking['id'] ?>" class="flex-1 sm:flex-none inline-flex items-center justify-center bg-brandOrange text-white font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-orange-600 transition-colors shadow min-h-[44px]">
            <i class="fa-solid fa-credit-card mr-1.5"></i> Pay Now
          </a>
        <?php endif; ?>
        <button onclick="window.print()" class="flex-1 sm:flex-none inline-flex items-center justify-center bg-brandNavy text-white font-bold text-xs px-4 py-2.5 rounded-xl hover:bg-slate-900 transition-colors gap-1.5 shadow min-h-[44px]">
          <i class="fa-solid fa-print"></i>
          <span>Print / Save PDF</span>
        </button>
      </div>
    </div>

    <!-- Payment Submitted Feedback Alert (Hide on Print) -->
    <?php if (isset($_GET['submitted']) || isset($_SESSION['payment_submitted'])): ?>
      <?php unset($_SESSION['payment_submitted']); ?>
      <div class="no-print bg-gradient-to-r from-emerald-500 to-emerald-600 text-white p-4 sm:p-5 rounded-2xl mb-6 shadow-lg flex items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-white/20 text-white flex items-center justify-center text-xl flex-shrink-0 shadow-inner">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div class="space-y-0.5">
            <h4 class="font-extrabold text-sm text-white flex items-center gap-2">
              <span>Payment Reference Submitted Successfully! ✓</span>
              <span class="text-[10px] bg-white/25 px-2 py-0.5 rounded-full uppercase tracking-wider font-mono">Under Review</span>
            </h4>
            <p class="text-xs text-emerald-50 leading-relaxed">
              Your payment reference <strong class="font-mono bg-emerald-700/40 px-1.5 py-0.5 rounded text-white"><?= htmlspecialchars($booking['payment_ref'] ?? '') ?></strong> has been recorded and is currently <strong class="text-white">under administrator review</strong>. Once verified, dispatch will unlock immediately.
            </p>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Printable Receipt Card - Mobile optimized -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-md p-4 sm:p-10 print-shadow-none space-y-6 sm:space-y-8 overflow-hidden">
      
      <!-- Top Brand & Receipt Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-200 pb-6">
        <div>
          <div class="flex items-center gap-3 mb-2">
            <img src="public/Asset 5.svg" onerror="this.onerror=null; this.src='../public/Asset 5.svg';" alt="YOCOR Express Logo" class="h-9 sm:h-10 w-auto object-contain">
          </div>
          <p class="text-[11px] text-slate-500 font-medium">Global Freight & Regional Island Logistics Carrier</p>
          <p class="text-[10px] text-slate-400">Metro Terminal 100, Gateway Blvd • support@yocorexpress.com</p>
        </div>

        <div class="sm:text-right">
          <span class="inline-block uppercase tracking-widest text-[10px] font-black text-brandOrange">Official Delivery Waybill</span>
          <h2 class="text-xl sm:text-2xl font-black font-mono text-brandNavy tracking-wide">
            <?= htmlspecialchars($displayCode) ?>
          </h2>
          <p class="text-[11px] text-slate-500 mt-0.5">
            Issued: <strong><?= date('F d, Y · h:i A', strtotime($booking['created_at'])) ?></strong>
          </p>
        </div>
      </div>

      <!-- Payment Clearance Status Stamp -->
      <?php 
      $isUnderReview = (($booking['payment_status'] ?? 'unpaid') === 'under_review');
      ?>
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 rounded-xl border <?= $isPaid ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : ($isUnderReview ? 'bg-blue-50/70 border-blue-200 text-blue-900' : 'bg-amber-50/70 border-amber-200 text-amber-900') ?> gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl <?= $isPaid ? 'bg-emerald-500 text-white' : ($isUnderReview ? 'bg-blue-500 text-white' : 'bg-amber-500 text-white') ?> flex items-center justify-center text-lg flex-shrink-0 shadow-sm">
            <i class="fa-solid <?= $isPaid ? 'fa-check' : ($isUnderReview ? 'fa-hourglass-half' : 'fa-clock') ?>"></i>
          </div>
          <div>
            <h4 class="font-bold text-xs uppercase tracking-wider">
              <?= $isPaid ? 'Payment Cleared &amp; Confirmed' : ($isUnderReview ? 'Payment Submitted · Under Admin Review' : 'Payment Awaiting Settlement') ?>
            </h4>
            <p class="text-[11px] <?= $isPaid ? 'text-emerald-700' : ($isUnderReview ? 'text-blue-700' : 'text-amber-700') ?>">
              <?php if ($isPaid): ?>
                Settled on <?= !empty($booking['paid_at']) ? date('M d, Y · h:i A', strtotime($booking['paid_at'])) : 'Verified' ?>
                <?php if (!empty($booking['payment_method'])): ?>
                  via <?= strtoupper(htmlspecialchars($booking['payment_method'])) ?>
                <?php endif; ?>
                <?php if (!empty($booking['payment_ref'])): ?>
                  (Ref: <span class="font-mono font-bold"><?= htmlspecialchars($booking['payment_ref']) ?></span>)
                <?php endif; ?>
              <?php elseif ($isUnderReview): ?>
                Proof submitted via <?= strtoupper(htmlspecialchars($booking['payment_method'] ?? 'Online')) ?>.
                <?php if (!empty($booking['payment_ref'])): ?>
                  Ref No: <strong class="font-mono"><?= htmlspecialchars($booking['payment_ref']) ?></strong>.
                <?php endif; ?>
                Awaiting administrator confirmation.
              <?php else: ?>
                Unsettled balance. Must be settled before release or delivery completion.
              <?php endif; ?>
            </p>
          </div>
        </div>

        <div class="font-mono text-right font-black text-sm uppercase px-3 py-1 rounded-lg border <?= $isPaid ? 'bg-emerald-100/70 text-emerald-800 border-emerald-300' : ($isUnderReview ? 'bg-blue-100/70 text-blue-800 border-blue-300' : 'bg-amber-100/70 text-amber-800 border-amber-300') ?>">
          <?= $isPaid ? 'PAID' : ($isUnderReview ? 'VERIFYING' : 'UNPAID') ?>
        </div>
      </div>

      <!-- Customer & Route Information Grid -->
      <div class="grid sm:grid-cols-2 gap-6 text-xs">
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80 space-y-2">
          <h5 class="font-black text-brandNavy uppercase tracking-wider text-[10px] pb-1 border-b border-slate-200">
            Customer / Shipper Info
          </h5>
          <p class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($booking['username']) ?></p>
          <p class="text-slate-500 font-medium">Account ID: <span class="font-mono text-slate-700 font-bold">#<?= $booking['user_id'] ?></span></p>
          <p class="text-slate-500 font-medium">Email: <?= htmlspecialchars($booking['email']) ?></p>
          <p class="text-slate-500 font-medium">Phone: <span class="text-slate-700 font-semibold"><?= !empty($booking['phone']) ? htmlspecialchars($booking['phone']) : '<em class="text-slate-400 font-normal">Not provided</em>' ?></span></p>
        </div>

        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200/80 space-y-2">
          <h5 class="font-black text-brandNavy uppercase tracking-wider text-[10px] pb-1 border-b border-slate-200">
            Dispatch Details
          </h5>
          <p class="text-slate-700 font-bold text-sm"><?= htmlspecialchars($booking['service_name']) ?></p>
          <p class="text-slate-500 font-medium">Route: <strong class="text-brandOrange"><?= htmlspecialchars($booking['pickup_region'] ?? 'Luzon') ?> &rarr; <?= htmlspecialchars($booking['delivery_region'] ?? 'Luzon') ?></strong></p>
          <p class="text-slate-500 font-medium">Status: <strong class="uppercase text-slate-800"><?= htmlspecialchars($booking['status']) ?></strong></p>
        </div>
      </div>

      <!-- Route Addresses -->
      <div class="grid sm:grid-cols-2 gap-4 text-xs">
        <div class="border border-slate-200 rounded-xl p-3.5 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Pickup Address</span>
          <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($booking['pickup_address']) ?></p>
        </div>
        <div class="border border-slate-200 rounded-xl p-3.5 space-y-1">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Delivery Destination</span>
          <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($booking['delivery_address']) ?></p>
        </div>
      </div>

      <!-- Itemized Pricing Breakdown Table - Mobile scrollable -->
      <div>
        <h5 class="font-black text-brandNavy uppercase tracking-wider text-xs mb-2">Itemized Charges</h5>
        <div class="border border-slate-200 rounded-xl overflow-hidden text-xs overflow-x-auto -mx-4 sm:mx-0 scroll-smooth">
          <table class="w-full text-left min-w-[520px]">
            <thead class="bg-slate-50 font-bold text-slate-600 border-b border-slate-200 text-[11px]">
              <tr>
                <th class="px-4 py-2.5">Charge Description</th>
                <th class="px-4 py-2.5 text-center">Unit / Metric</th>
                <th class="px-4 py-2.5 text-right">Rate</th>
                <th class="px-4 py-2.5 text-right">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
              <tr>
                <td class="px-4 py-2.5">
                  <strong>Base Freight Service:</strong> <?= htmlspecialchars($booking['service_name']) ?>
                </td>
                <td class="px-4 py-2.5 text-center">1 package</td>
                <td class="px-4 py-2.5 text-right font-mono">₱<?= number_format($basePrice, 2) ?></td>
                <td class="px-4 py-2.5 text-right font-mono font-bold">₱<?= number_format($basePrice, 2) ?></td>
              </tr>
              <tr>
                <td class="px-4 py-2.5">
                  <strong>Weight Fee</strong> (<?= number_format($weight, 2) ?> kg @ ₱<?= number_format($pricePerKg, 2) ?>/kg)
                </td>
                <td class="px-4 py-2.5 text-center"><?= number_format($weight, 2) ?> kg</td>
                <td class="px-4 py-2.5 text-right font-mono">₱<?= number_format($pricePerKg, 2) ?></td>
                <td class="px-4 py-2.5 text-right font-mono font-bold">₱<?= number_format($weightCost, 2) ?></td>
              </tr>
              <?php if ($transitFee > 0): ?>
                <tr>
                  <td class="px-4 py-2.5">
                    <strong>Regional Distance Transit Fee</strong> (<?= htmlspecialchars($booking['pickup_region'] ?? 'Luzon') ?> &rarr; <?= htmlspecialchars($booking['delivery_region'] ?? 'Luzon') ?>)
                  </td>
                  <td class="px-4 py-2.5 text-center">Inter-Island</td>
                  <td class="px-4 py-2.5 text-right font-mono">₱<?= number_format($transitFee, 2) ?></td>
                  <td class="px-4 py-2.5 text-right font-mono font-bold">₱<?= number_format($transitFee, 2) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
            <tfoot class="bg-slate-50 font-bold border-t border-slate-200">
              <tr>
                <td colspan="3" class="px-4 py-3 text-right text-slate-700 text-sm">Total Amount Due:</td>
                <td class="px-4 py-3 text-right text-brandOrange text-base font-black font-heading">
                  ₱<?= number_format($totalCost, 2) ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Footer Notes & Barcode Simulation - Mobile centered -->
      <div class="border-t border-slate-200 pt-6 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left text-[11px] text-slate-400 px-1">
        <div>
          <p class="font-bold text-slate-600">Thank you for choosing YOCOR Express!</p>
          <p>This document serves as an official electronic delivery receipt and proof of waybill creation.</p>
        </div>
        <div class="text-center font-mono">
          <div class="text-2xl text-slate-800 tracking-widest font-barcode uppercase">
            *<?= htmlspecialchars($displayCode) ?>*
          </div>
          <span class="text-[9px] text-slate-400">Waybill Verification Code</span>
        </div>
      </div>

    </div>

    <!-- Bottom Print Reminder -->
    <div class="no-print text-center mt-6 text-xs text-slate-500">
      <p>Need assistance with this shipment? Contact support with reference <strong><?= htmlspecialchars($displayCode) ?></strong></p>
    </div>

  </div>

</body>
</html>
