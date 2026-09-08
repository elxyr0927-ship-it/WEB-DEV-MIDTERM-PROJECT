<?php
session_start();
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pdo = getConnection();
$contactSuccess = '';
$contactError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_contact_message'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $contactError = 'Security token expired or invalid (CSRF). Please refresh and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? 'General Inquiries');
        $trackingId = trim($_POST['tracking_id'] ?? '');
        $message = trim($_POST['message'] ?? '');

        $errs = [
            validateRequired($name, 'Your Name'),
            validateStringLength($name, 'Your Name', 100),
            validateRequired($email, 'Email Address'),
            validateEmailFormat($email),
            validateRequired($message, 'Message'),
            validateStringLength($message, 'Message', 3000, 10)
        ];
        $cleanErrs = array_filter($errs);

        if (!empty($cleanErrs)) {
            $contactError = reset($cleanErrs);
        } else {
            try {
                $finalSubject = $subject . (!empty($trackingId) ? " [Ref: {$trackingId}]" : "");
                $stmt = $pdo->prepare("
                    INSERT INTO contact_messages (name, email, subject, message, is_read, created_at) 
                    VALUES (:name, :email, :subject, :message, 0, NOW())
                ");
                $stmt->execute([
                    'name'    => $name,
                    'email'   => $email,
                    'subject' => $finalSubject,
                    'message' => $message
                ]);
                $contactSuccess = "Thank you, {$name}! Your message has been safely received. A support specialist will follow up at {$email} shortly.";
            } catch (PDOException $e) {
                $contactError = 'Error submitting your inquiry: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = "Contact & Hub Locations | YOCOR Express";
$activeNav = "contact";

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-12 bg-slate-50">
  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="text-center max-w-2xl mx-auto mb-12 space-y-2">
      <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-xs uppercase tracking-widest">
        <i class="fa-solid fa-headset"></i> Customer Service Hub
      </div>
      <h1 class="text-3xl font-black text-brandNavy">Get in Touch with Our Logistics Experts</h1>
      <p class="text-slate-600 text-sm">Have an inquiry regarding a package, enterprise corporate accounts, or freight rates? We are ready to help 24/7.</p>
    </div>

    <div class="grid lg:grid-cols-12 gap-8">
      
      <!-- Contact Info & Hub Addresses -->
      <div class="lg:col-span-5 space-y-6">
        
        <div class="bg-brandNavy text-white p-8 rounded-3xl shadow-xl space-y-6">
          <h2 class="text-xl font-bold border-b border-white/10 pb-3">Central Operations Hub</h2>
          
          <div class="space-y-4 text-xs">
            <div class="flex items-start gap-3">
              <i class="fa-solid fa-location-dot text-brandOrange text-base mt-0.5"></i>
              <div>
                <strong class="text-white block">Global Headquarters:</strong>
                <span class="text-slate-300">YOCOR Express Center, 100 Logistics Blvd, Metro Gateway Terminal</span>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <i class="fa-solid fa-phone text-brandOrange text-base mt-0.5"></i>
              <div>
                <strong class="text-white block">24/7 Toll-Free Support:</strong>
                <span class="text-slate-300">+1 (800) 555-YOCOR (9626)</span>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <i class="fa-solid fa-envelope text-brandOrange text-base mt-0.5"></i>
              <div>
                <strong class="text-white block">Customer Inquiries:</strong>
                <span class="text-slate-300">support@yocorexpress.com</span>
              </div>
            </div>

            <div class="flex items-start gap-3">
              <i class="fa-solid fa-truck-fast text-brandOrange text-base mt-0.5"></i>
              <div>
                <strong class="text-white block">Commercial Dispatch Desk:</strong>
                <span class="text-slate-300">dispatch@yocorexpress.com</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Regional Branch Pills -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-3">
          <h3 class="font-extrabold text-brandNavy text-xs uppercase tracking-wider">Major Regional Sorting Hubs</h3>
          <div class="grid grid-cols-2 gap-2 text-xs text-slate-700">
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
              <p class="font-bold text-brandNavy">North Hub</p>
              <p class="text-[11px] text-slate-500">Clark Aviation Zone</p>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
              <p class="font-bold text-brandNavy">South Hub</p>
              <p class="text-[11px] text-slate-500">Batangas Port Dock</p>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
              <p class="font-bold text-brandNavy">Visayas Hub</p>
              <p class="text-[11px] text-slate-500">Mactan Terminal</p>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200">
              <p class="font-bold text-brandNavy">Mindanao Hub</p>
              <p class="text-[11px] text-slate-500">Davao Cargo Center</p>
            </div>
          </div>
        </div>

      </div>

      <!-- Contact Message Form -->
      <div class="lg:col-span-7">
        <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl border border-slate-200">
          <h2 class="text-xl font-extrabold text-brandNavy mb-1">Send a Message to Support</h2>
          <p class="text-xs text-slate-500 mb-6">Our average customer support response time is under 15 minutes.</p>

          <?php if (!empty($contactSuccess)): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2.5 mb-5 shadow-sm">
              <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
              <span><?= htmlspecialchars($contactSuccess) ?></span>
            </div>
          <?php endif; ?>

          <?php if (!empty($contactError)): ?>
            <div class="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-xs font-bold flex items-center gap-2.5 mb-5 shadow-sm">
              <i class="fa-solid fa-circle-exclamation text-rose-600 text-base"></i>
              <span><?= htmlspecialchars($contactError) ?></span>
            </div>
          <?php endif; ?>

          <form method="POST" action="" class="space-y-4">
            <?= csrfField() ?>
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Your Name</label>
                <input type="text" name="name" required placeholder="e.g. Alex Santos" 
                       value="<?= htmlspecialchars($_POST['name'] ?? ($_SESSION['username'] ?? '')) ?>"
                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                <input type="email" name="email" required placeholder="e.g. alex@example.com" 
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
              </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tracking ID (Optional)</label>
                <input type="text" name="tracking_id" placeholder="e.g. YR-A7F3B9" 
                       value="<?= htmlspecialchars($_POST['tracking_id'] ?? '') ?>"
                       class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all uppercase font-mono">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Inquiry Subject</label>
                <select name="subject" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
                  <option value="Package Status & Delivery Delay">Package Status & Delivery Delay</option>
                  <option value="Commercial Freight Quote">Commercial Freight Quote</option>
                  <option value="Corporate Business Account">Corporate Business Account</option>
                  <option value="Claims & Transit Insurance">Claims & Transit Insurance</option>
                  <option value="General Inquiries">General Inquiries</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Detailed Message</label>
              <textarea name="message" rows="4" required placeholder="Describe your inquiry in detail (minimum 10 characters)..." 
                        class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="send_contact_message" class="w-full bg-brandNavy hover:bg-slate-900 text-white font-extrabold py-3.5 px-6 rounded-xl brand-glow-navy transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2">
              <i class="fa-solid fa-paper-plane"></i>
              <span>Send Message</span>
            </button>
          </form>
        </div>
      </div>

    </div>

  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
