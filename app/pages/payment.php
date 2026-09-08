<?php
// Start session and require login
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Redirect admin to admin dashboard
if ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();
$error = '';
$success = '';

// Determine target booking
$bookingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$trackingCode = trim($_GET['code'] ?? ($_POST['tracking_code'] ?? ''));

if (!$bookingId && empty($trackingCode)) {
    header('Location: customer_dashboard.php');
    exit;
}

// Fetch booking and ensure it belongs to the logged-in customer
if ($bookingId) {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS service_name 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        WHERE b.id = :id AND b.user_id = :user_id
    ");
    $stmt->execute(['id' => $bookingId, 'user_id' => $_SESSION['user_id']]);
} else {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS service_name 
        FROM bookings b 
        JOIN services s ON b.service_id = s.id 
        WHERE b.tracking_code = :code AND b.user_id = :user_id
    ");
    $stmt->execute(['code' => $trackingCode, 'user_id' => $_SESSION['user_id']]);
}
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: customer_dashboard.php');
    exit;
}

// Handle Payment Submission (Simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token expired or invalid. Please try again.';
    } elseif ($booking['payment_status'] === 'paid') {
        $error = 'This booking has already been paid.';
    } else {
        $paymentMethod = trim($_POST['payment_method'] ?? 'gcash');
        $validMethods = ['gcash', 'maya', 'card', 'cod'];
        if ($mErr = validateInArray($paymentMethod, $validMethods, 'payment method')) {
            $error = $mErr;
        } else {
            $accountName = trim($_POST['account_name'] ?? '');
            $accountNumber = trim($_POST['account_number'] ?? '');
            $paymentReference = trim($_POST['payment_reference'] ?? '');

            if ($paymentMethod !== 'cod' && empty($paymentReference)) {
                $error = 'Please enter the transaction reference number provided by your payment provider.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Update booking payment status to under_review with proof details for admin verification
                    $updateStmt = $pdo->prepare("
                        UPDATE bookings 
                        SET payment_status = 'under_review',
                            payment_method = :method,
                            payment_account_name = :acc_name,
                            payment_account_number = :acc_num,
                            payment_ref = :ref,
                            paid_at = NULL 
                        WHERE id = :id AND user_id = :user_id
                    ");
                    $updateStmt->execute([
                        'method' => $paymentMethod,
                        'acc_name' => !empty($accountName) ? $accountName : null,
                        'acc_num' => !empty($accountNumber) ? $accountNumber : null,
                        'ref' => !empty($paymentReference) ? $paymentReference : null,
                        'id' => $booking['id'],
                        'user_id' => $_SESSION['user_id']
                    ]);

                    // Record in status_history
                    $proofNote = ($paymentMethod === 'cod') 
                        ? 'Customer selected Cash on Pickup (COD) - pending admin/rider verification' 
                        : 'Customer submitted ' . strtoupper($paymentMethod) . ' payment (Ref: ' . $paymentReference . ')';

                    $historyStmt = $pdo->prepare("
                        INSERT INTO status_history (booking_id, old_status, new_status, changed_by, notes) 
                        VALUES (:b_id, :status, :status, :user_id, :notes)
                    ");
                    $historyStmt->execute([
                        'b_id' => $booking['id'],
                        'status' => $booking['status'],
                        'user_id' => $_SESSION['user_id'],
                        'notes' => $proofNote
                    ]);

                    $pdo->commit();

                    // Refresh booking state
                    $booking['payment_status'] = 'under_review';
                    $booking['payment_method'] = $paymentMethod;
                    $booking['payment_ref'] = $paymentReference;

                    $_SESSION['payment_submitted'] = "Payment details submitted for Shipment " . ($booking['tracking_code'] ?? ('#' . $booking['id'])) . ". Your payment reference is currently under admin verification.";
                    header('Location: customer_dashboard.php?msg=payment_submitted');
                    exit;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Payment error: " . $e->getMessage());
                    $error = 'Payment transaction could not be completed. Please try again or contact support.';
                }
            }
        }
    }
}

$pageTitle = "Checkout & Payment | YOCOR Express";
$activeNav = "booking";
include __DIR__ . '/../includes/header.php';

// Dynamic Payment Methods from Database (configured by Admin)
$dbPaymentMethods = getPaymentMethods($pdo, true);
$methodIcons = [
    'gcash' => 'fa-mobile-screen-button',
    'maya'  => 'fa-wallet',
    'card'  => 'fa-credit-card',
    'cod'   => 'fa-hand-holding-dollar'
];
$methodIconColor = [
    'gcash' => 'text-blue-600',
    'maya'  => 'text-emerald-600',
    'card'  => 'text-brandNavy',
    'cod'   => 'text-amber-600'
];
$methodIconBg = [
    'gcash' => 'bg-blue-600',
    'maya'  => 'bg-emerald-600',
    'card'  => 'bg-brandNavy',
    'cod'   => 'bg-amber-600'
];
$methodBoxBg = [
    'gcash' => 'bg-blue-50/80 border-blue-200 text-blue-900',
    'maya'  => 'bg-emerald-50/80 border-emerald-200 text-emerald-900',
    'card'  => 'bg-slate-100 border-slate-300 text-slate-800',
    'cod'   => 'bg-amber-50/80 border-amber-200 text-amber-900'
];
$methodSubtitles = [
    'gcash' => 'E-Wallet',
    'maya'  => 'E-Wallet',
    'card'  => 'Debit/Credit',
    'cod'   => 'Cash on Pickup'
];

$defaultMethod = !empty($dbPaymentMethods) ? $dbPaymentMethods[0]['method_code'] : 'gcash';
$totalDue = (float)($booking['total_cost'] ?? 0);
$formattedDue = number_format($totalDue, 2);

// Build client-side dynamic configurations
$clientMethodConfig = [];
foreach ($dbPaymentMethods as $pm) {
    $code = $pm['method_code'];
    $accNum = $pm['account_number'] ?? '';
    $accName = $pm['account_name'] ?? '';
    $rawText = $pm['instructions'] ?? '';

    // If template placeholders exist, replace them with formatted values; else construct clear prompt
    if (strpos($rawText, '{amount}') !== false || strpos($rawText, '{account_number}') !== false) {
        $parsedText = str_replace(
            ['{amount}', '{account_number}', '{account_name}'],
            [
                '<strong class="text-brandOrange font-mono font-bold">₱' . $formattedDue . '</strong>',
                '<strong class="font-mono bg-blue-100 px-1.5 py-0.5 rounded text-blue-950 font-black">' . htmlspecialchars($accNum) . '</strong>',
                htmlspecialchars($accName)
            ],
            htmlspecialchars($rawText)
        );
        // decode strong tags
        $parsedText = html_entity_decode($parsedText, ENT_QUOTES);
    } else {
        if ($code === 'cod') {
            $parsedText = 'No online pre-payment required. Please prepare exact cash of <strong class="text-brandOrange font-mono font-bold">₱' . $formattedDue . '</strong> for the rider upon package handover.';
        } elseif (!empty($accNum)) {
            $parsedText = 'Transfer exactly <strong class="text-brandOrange font-mono font-bold">₱' . $formattedDue . '</strong> to ' . htmlspecialchars($pm['method_name']) . ' No. <strong class="font-mono bg-blue-100 px-1.5 py-0.5 rounded text-blue-950 font-black">' . htmlspecialchars($accNum) . '</strong>' . (!empty($accName) ? ' (Account: ' . htmlspecialchars($accName) . ')' : '') . '. ' . htmlspecialchars($rawText);
        } else {
            $parsedText = htmlspecialchars($rawText);
        }
    }

    $clientMethodConfig[$code] = [
        'title' => $pm['method_name'] . ' Settlement',
        'text' => $parsedText,
        'note' => ($code === 'cod') ? '' : 'After completing your transfer, enter your Sender Account Name, Mobile/Card No., and generated Reference Number below.',
        'icon' => $methodIcons[$code] ?? 'fa-wallet',
        'iconBg' => $methodIconBg[$code] ?? 'bg-blue-600',
        'boxBg' => $methodBoxBg[$code] ?? 'bg-blue-50/80 border-blue-200 text-blue-900',
        'btnText' => ($code === 'cod') ? 'Confirm Cash on Pickup Request' : 'Submit ' . $pm['method_name'] . ' Reference for Verification',
        'needsRef' => ($code !== 'cod')
    ];
}
?>

<main class="flex-grow py-8 bg-slate-50">
  <div class="max-w-2xl mx-auto px-4 sm:px-6">
    
    <!-- Top Step Breadcrumb -->
    <div class="mb-6 flex items-center justify-between text-xs font-semibold text-slate-500">
      <a href="customer_dashboard.php" class="hover:text-brandOrange flex items-center gap-1.5 transition-colors">
        <i class="fa-solid fa-arrow-left"></i> Back to Orders
      </a>
      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700 font-bold">
        Step 2 of 2: Checkout & Settlement
      </span>
    </div>

    <!-- Error Alert -->
    <?php if (!empty($error)): ?>
      <div class="bg-rose-50 border border-rose-200 text-rose-800 p-3.5 rounded-xl text-xs font-bold flex items-center gap-2 mb-5 shadow-sm">
        <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
        <span><?= htmlspecialchars($error) ?></span>
      </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
      <!-- Card Header -->
      <div class="bg-gradient-to-r from-brandNavy to-slate-900 text-white p-5 sm:p-6 flex justify-between items-center">
        <div>
          <span class="text-[10px] uppercase font-black text-brandOrange tracking-widest block">Secure Payment Gateway</span>
          <h1 class="text-xl sm:text-2xl font-black font-heading">Waybill Settlement</h1>
        </div>
        <div class="text-right">
          <span class="font-mono text-xs bg-white/10 px-2.5 py-1 rounded-md border border-white/20 block font-bold">
            <?= htmlspecialchars($booking['tracking_code'] ?? ('#' . $booking['id'])) ?>
          </span>
          <span class="text-[10px] text-slate-300 block mt-0.5">Booking ID: #<?= $booking['id'] ?></span>
        </div>
      </div>

      <!-- Booking Summary Details -->
      <div class="p-5 sm:p-6 space-y-5">
        <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 text-xs space-y-2.5">
          <div class="flex justify-between items-center pb-2 border-b border-slate-200">
            <span class="text-slate-500 font-semibold">Courier Service:</span>
            <span class="font-bold text-slate-800"><?= htmlspecialchars($booking['service_name']) ?></span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-200">
            <span class="text-slate-500 font-semibold">Route & Region:</span>
            <span class="font-bold text-slate-800">
              <?= htmlspecialchars($booking['pickup_region'] ?? 'Luzon') ?> &rarr; <?= htmlspecialchars($booking['delivery_region'] ?? 'Luzon') ?>
            </span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-200">
            <span class="text-slate-500 font-semibold">Package Weight:</span>
            <span class="font-bold text-slate-800"><?= number_format((float)$booking['weight'], 2) ?> kg</span>
          </div>
          <div class="flex justify-between items-center pb-2 border-b border-slate-200">
            <span class="text-slate-500 font-semibold">Current Payment Status:</span>
            <?php if ($booking['payment_status'] === 'paid'): ?>
              <span class="inline-flex items-center gap-1 font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full text-xs">
                <i class="fa-solid fa-circle-check text-[10px]"></i> Paid &amp; Verified
              </span>
            <?php elseif ($booking['payment_status'] === 'under_review'): ?>
              <span class="inline-flex items-center gap-1 font-bold text-blue-700 bg-blue-100 px-2 py-0.5 rounded-full text-xs">
                <i class="fa-solid fa-hourglass-half text-[10px]"></i> Under Admin Review
              </span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded-full text-xs">
                <i class="fa-solid fa-clock text-[10px]"></i> Awaiting Settlement
              </span>
            <?php endif; ?>
          </div>
          <div class="flex justify-between items-center pt-1 text-sm font-bold">
            <span class="text-brandNavy">Total Amount Due:</span>
            <span class="text-brandOrange text-lg font-black font-heading">
              ₱<?= number_format((float)($booking['total_cost'] ?? 0), 2) ?>
            </span>
          </div>
        </div>

        <?php if ($booking['payment_status'] === 'paid'): ?>
          <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-center space-y-2">
            <i class="fa-solid fa-circle-check text-2xl text-emerald-600"></i>
            <h3 class="font-bold text-sm">Shipment Has Already Been Settled</h3>
            <p class="text-xs text-emerald-700">Verified &amp; confirmed by Admin on <?= htmlspecialchars($booking['paid_at'] ?? 'Confirmed') ?>. Your courier is now scheduled for dispatch.</p>
            <div class="pt-2 flex justify-center gap-3">
              <a href="customer_dashboard.php" class="bg-brandNavy text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors">
                Back to Dashboard
              </a>
              <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" class="bg-brandOrange text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                Track Shipment
              </a>
            </div>
          </div>
        <?php elseif ($booking['payment_status'] === 'under_review'): ?>
          <div class="bg-blue-50 border border-blue-200 text-blue-900 p-5 rounded-xl text-center space-y-2.5">
            <i class="fa-solid fa-hourglass-half text-2xl text-blue-600"></i>
            <h3 class="font-bold text-sm">Payment Under Admin Review</h3>
            <p class="text-xs text-blue-700 max-w-md mx-auto">Your payment reference has been submitted and is currently being verified by our operations administrator. Dispatch will unlock as soon as payment is confirmed.</p>
            
            <?php if (!empty($booking['payment_ref']) || !empty($booking['payment_method'])): ?>
              <div class="bg-white/80 border border-blue-200 rounded-lg p-3 max-w-sm mx-auto text-left text-xs space-y-1">
                <div class="flex justify-between">
                  <span class="text-slate-500 font-semibold">Payment Channel:</span>
                  <span class="font-bold text-slate-800 uppercase"><?= htmlspecialchars($booking['payment_method'] ?? 'Online') ?></span>
                </div>
                <?php if (!empty($booking['payment_ref'])): ?>
                  <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Reference No:</span>
                    <span class="font-mono font-bold text-brandNavy"><?= htmlspecialchars($booking['payment_ref']) ?></span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($booking['payment_account_name'])): ?>
                  <div class="flex justify-between">
                    <span class="text-slate-500 font-semibold">Sender Name:</span>
                    <span class="font-medium text-slate-700"><?= htmlspecialchars($booking['payment_account_name']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <div class="pt-2 flex justify-center gap-3">
              <a href="customer_dashboard.php" class="bg-brandNavy text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-slate-900 transition-colors">
                Back to Dashboard
              </a>
              <a href="tracking.php?id=<?= urlencode($booking['tracking_code'] ?? $booking['id']) ?>" class="bg-brandOrange text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-orange-600 transition-colors">
                Track Shipment
              </a>
            </div>
          </div>
        <?php else: ?>
          <!-- Payment Form -->
          <form method="POST" action="" class="space-y-5">
            <?= csrfField() ?>
            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-2">Select Payment Method</label>
              <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5" id="payment-method-selector">
                <?php foreach ($dbPaymentMethods as $index => $pm): 
                  $code = $pm['method_code'];
                  $isDefault = ($code === $defaultMethod);
                  $icon = $methodIcons[$code] ?? 'fa-credit-card';
                  $iconColor = $methodIconColor[$code] ?? 'text-brandNavy';
                  $subtitle = $methodSubtitles[$code] ?? 'Payment Option';
                ?>
                  <label class="payment-method-card relative flex flex-col items-center justify-center p-3 rounded-xl border-2 <?= $isDefault ? 'border-brandOrange bg-orange-50/40' : 'border-slate-200' ?> cursor-pointer hover:border-brandOrange transition-all text-center select-none" data-method="<?= htmlspecialchars($code) ?>">
                    <input type="radio" name="payment_method" value="<?= htmlspecialchars($code) ?>" <?= $isDefault ? 'checked' : '' ?> class="sr-only">
                    <i class="fa-solid <?= $icon ?> text-xl <?= $iconColor ?> mb-1 pointer-events-none"></i>
                    <span class="text-xs font-extrabold text-slate-800 pointer-events-none"><?= htmlspecialchars($pm['method_name']) ?></span>
                    <span class="text-[9px] text-slate-400 pointer-events-none"><?= htmlspecialchars($subtitle) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Dynamic Payment Account Info & Reference Number Section -->
            <div id="payment-details-section" class="space-y-4 pt-1">
              
              <!-- Payment Instructions & Official Account Banner -->
              <?php 
                $initCfg = $clientMethodConfig[$defaultMethod] ?? reset($clientMethodConfig);
              ?>
              <div id="method-instructions" class="rounded-xl p-3.5 border transition-all text-xs flex items-start gap-3 <?= $initCfg['boxBg'] ?? 'bg-blue-50/80 border-blue-200 text-blue-900' ?>">
                <div id="method-icon" class="w-8 h-8 rounded-lg <?= $initCfg['iconBg'] ?? 'bg-blue-600' ?> text-white flex items-center justify-center text-sm flex-shrink-0 shadow-sm mt-0.5">
                  <i class="fa-solid <?= $initCfg['icon'] ?? 'fa-wallet' ?>"></i>
                </div>
                <div class="space-y-1">
                  <h4 id="method-title" class="font-extrabold text-xs text-blue-950"><?= htmlspecialchars($initCfg['title'] ?? 'Official Merchant Settlement') ?></h4>
                  <p id="method-instructions-text" class="text-blue-800 leading-relaxed text-[11px]">
                    <?= $initCfg['text'] ?? '' ?>
                  </p>
                  <p id="method-note" class="text-[10px] text-blue-700/80 italic" style="<?= empty($initCfg['note']) ? 'display:none;' : '' ?>">
                    <?= htmlspecialchars($initCfg['note'] ?? '') ?>
                  </p>
                </div>
              </div>

              <!-- COD-only Banner Message (Shown when COD is clicked) -->
              <div id="cod-message" class="p-4 rounded-xl border border-amber-200 bg-amber-50/90 text-amber-900 text-center space-y-2" style="<?= ($defaultMethod === 'cod') ? '' : 'display: none;' ?>">
                <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center text-lg mx-auto shadow-sm">
                  <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
                <div>
                  <h4 class="font-extrabold text-xs text-amber-950 uppercase tracking-wide">Cash on Pickup (COD) Selected</h4>
                  <p class="text-xs text-amber-800 mt-0.5">
                    No online pre-payment required. Please prepare exact cash of <strong class="text-brandOrange font-mono font-bold">₱<?= $formattedDue ?></strong> for the rider upon package handover.
                  </p>
                </div>
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-100 text-amber-900 text-[10px] font-bold">
                  <i class="fa-solid fa-circle-info text-amber-700"></i>
                  <span>Payment reference is NOT required for COD</span>
                </div>
              </div>

              <!-- Inputs for E-Wallet / Card details -->
              <div id="dynamic-inputs" class="grid sm:grid-cols-2 gap-3.5 bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    <span id="label-account-name">Sender Account Name</span>
                    <span class="text-slate-400 font-normal">(Optional)</span>
                  </label>
                  <input type="text" name="account_name" id="account_name"
                         placeholder="e.g., Juan Dela Cruz"
                         class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs">
                </div>
                <div>
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    <span id="label-account-number">Sender Mobile / Card Number</span>
                    <span class="text-slate-400 font-normal">(Optional)</span>
                  </label>
                  <input type="text" name="account_number" id="account_number"
                         placeholder="e.g., 09171234567 or Last 4 digits"
                         class="w-full px-3 py-2 rounded-lg bg-white border border-slate-300 focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none transition-all text-xs font-mono">
                </div>
                <div class="sm:col-span-2">
                  <label class="block text-[11px] font-bold text-slate-800 mb-1 flex items-center justify-between">
                    <span>
                      <span id="label-reference">Unique Payment Reference / Transaction ID</span>
                      <span class="text-red-500">*</span>
                    </span>
                    <span class="text-[10px] font-semibold text-brandOrange">Required for Admin Confirmation</span>
                  </label>
                  <div class="relative">
                    <input type="text" name="payment_reference" id="payment_reference" required
                           placeholder="e.g., 100293847581 or MP-883921"
                           class="w-full px-3.5 py-2.5 rounded-lg bg-white border-2 border-slate-300 focus:border-brandOrange focus:ring-2 focus:ring-brandOrange/20 outline-none transition-all text-xs font-mono font-bold text-brandNavy uppercase tracking-wider">
                    <div class="absolute right-3 top-2.5 text-slate-400">
                      <i class="fa-solid fa-receipt text-sm"></i>
                    </div>
                  </div>
                  <p class="text-[10px] text-slate-500 mt-1">This unique reference will be submitted to the dispatch admin to verify your transaction.</p>
                </div>
              </div>

            </div>

            <!-- Verification Policy Banner -->
            <div class="bg-blue-50 border border-blue-200 text-blue-900 rounded-xl p-3 text-xs flex items-start gap-2.5">
              <i class="fa-solid fa-shield-halved text-blue-600 text-sm mt-0.5 flex-shrink-0"></i>
              <div>
                <strong>Admin Clearance Requirement:</strong> After submitting your payment reference, our accounts team will verify the payment directly before marking your waybill as <code class="bg-blue-100 px-1 py-0.5 rounded font-bold">Paid</code> and clearing it for courier dispatch.
              </div>
            </div>

            <div class="flex items-center gap-3 pt-2">
              <a href="customer_dashboard.php" class="w-1/3 py-2.5 text-center rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
                Pay Later
              </a>
              <button type="submit" name="process_payment" class="w-2/3 py-2.5 rounded-xl bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs shadow brand-glow-orange transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-lock text-[11px]"></i>
                <span id="submit-btn-text">Submit Payment Reference for Review</span>
              </button>
            </div>
          </form>

          <script>
            document.addEventListener('DOMContentLoaded', function() {
              const radios = document.querySelectorAll('input[name="payment_method"]');
              const detailsSection = document.getElementById('payment-details-section');
              const dynamicInputs = document.getElementById('dynamic-inputs');
              const instructionsBox = document.getElementById('method-instructions');
              const methodIcon = document.getElementById('method-icon');
              const methodTitle = document.getElementById('method-title');
              const methodText = document.getElementById('method-instructions-text');
              const methodNote = document.getElementById('method-note');
              const refInput = document.getElementById('payment_reference');
              const submitBtnText = document.getElementById('submit-btn-text');

              const methodConfig = <?= json_encode($clientMethodConfig) ?>;

              function updateCardStyles(selectedMethod) {
                const cards = document.querySelectorAll('.payment-method-card');
                cards.forEach(card => {
                  if (card.dataset.method === selectedMethod) {
                    card.classList.add('border-brandOrange', 'bg-orange-50/40');
                    card.classList.remove('border-slate-200');
                  } else {
                    card.classList.remove('border-brandOrange', 'bg-orange-50/40');
                    card.classList.add('border-slate-200');
                  }
                });
              }

              function applyPaymentMethod(method) {
                const cfg = methodConfig[method] || methodConfig.gcash;
                const codMsg = document.getElementById('cod-message');

                // Update instructions box content
                if (methodTitle) methodTitle.textContent = cfg.title;
                if (methodText) methodText.innerHTML = cfg.text;
                if (methodIcon) {
                  methodIcon.className = 'w-8 h-8 rounded-lg text-white flex items-center justify-center text-sm flex-shrink-0 shadow-sm mt-0.5 ' + cfg.iconBg;
                  methodIcon.innerHTML = '<i class="fa-solid ' + cfg.icon + '"></i>';
                }
                if (instructionsBox) {
                  instructionsBox.className = 'rounded-xl p-3.5 border transition-all text-xs flex items-start gap-3 ' + cfg.boxBg;
                }
                if (submitBtnText) submitBtnText.textContent = cfg.btnText;

                if (methodNote) {
                  if (cfg.note) {
                    methodNote.textContent = cfg.note;
                    methodNote.style.display = 'block';
                  } else {
                    methodNote.textContent = '';
                    methodNote.style.display = 'none';
                  }
                }

                // Strict display toggle between COD and Online e-wallets/card
                if (method === 'cod') {
                  // Hide inputs and instructions box, show dedicated COD confirmation
                  if (dynamicInputs) dynamicInputs.style.display = 'none';
                  if (instructionsBox) instructionsBox.style.display = 'none';
                  if (codMsg) codMsg.style.display = 'block';
                  if (refInput) {
                    refInput.removeAttribute('required');
                    refInput.value = '';
                  }
                } else {
                  // Reveal inputs and instructions box, hide COD confirmation
                  if (dynamicInputs) dynamicInputs.style.display = 'grid';
                  if (instructionsBox) instructionsBox.style.display = 'flex';
                  if (codMsg) codMsg.style.display = 'none';
                  if (refInput) {
                    refInput.setAttribute('required', 'required');
                  }
                }

                updateCardStyles(method);
              }

              radios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                  if (this.checked) {
                    applyPaymentMethod(this.value);
                  }
                });
                if (radio.checked) {
                  applyPaymentMethod(radio.value);
                }
              });
            });
          </script>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
