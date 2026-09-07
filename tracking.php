<?php
$pageTitle = "Track Shipment Status | YOCOR Express Logistics";
$activeNav = "tracking";
$searchedTracking = isset($_GET['tracking_no']) ? trim($_GET['tracking_no']) : '';

include 'includes/header.php';
?>

<main class="flex-grow py-12 bg-slate-50">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Page Header Banner -->
    <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
      <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-xs uppercase tracking-widest">
        <i class="fa-solid fa-satellite-dish"></i> GPS Tracking Engine
      </div>
      <h1 class="text-3xl font-black text-brandNavy">Shipment Tracking Center</h1>
      <p class="text-slate-600 text-sm">Enter your tracking waybill code to get full live timeline and status updates.</p>
    </div>

    <!-- Search Box Card -->
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-xl border border-slate-200 mb-8">
      <form id="tracking-page-form" class="flex flex-col sm:flex-row gap-4">
        <div class="relative flex-grow">
          <i class="fa-solid fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
          <input id="tracking-page-input" type="text" value="<?php echo htmlspecialchars($searchedTracking); ?>" placeholder="e.g. YCR-9824109 or EXP-77301" required class="w-full pl-12 pr-4 py-3.5 text-slate-900 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-semibold transition-all">
        </div>
        <button type="submit" class="bg-brandOrange hover:bg-orange-600 text-white font-bold py-3.5 px-8 rounded-xl transition-all flex items-center justify-center gap-2 text-sm shadow-md brand-glow-orange">
          <i class="fa-solid fa-magnifying-glass"></i>
          <span>Track</span>
        </button>
      </form>
      
      <!-- Quick Demo Badges -->
      <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-slate-500">
        <span>Try sample IDs:</span>
        <button type="button" onclick="loadSampleTracking('YCR-9824109')" class="px-2.5 py-1 bg-slate-100 hover:bg-orange-100 hover:text-brandOrange rounded-md font-mono font-medium transition-colors">YCR-9824109</button>
        <button type="button" onclick="loadSampleTracking('EXP-5541092')" class="px-2.5 py-1 bg-slate-100 hover:bg-orange-100 hover:text-brandOrange rounded-md font-mono font-medium transition-colors">EXP-5541092</button>
      </div>
    </div>

    <!-- Live Tracking Result Section (Dynamic UI) -->
    <div id="tracking-result-card" class="bg-white rounded-3xl shadow-xl border border-slate-200 overflow-hidden <?php echo empty($searchedTracking) ? 'hidden' : ''; ?>">
      
      <!-- Status Top Header -->
      <div class="bg-gradient-to-r from-brandNavy to-slate-900 text-white p-6 sm:p-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <span class="text-xs text-brandOrange font-bold uppercase tracking-wider block mb-1">Waybill Tracking Number</span>
          <h2 id="result-tracking-id" class="text-2xl font-black font-mono tracking-wide"><?php echo htmlspecialchars($searchedTracking ?: 'YCR-9824109'); ?></h2>
          <p class="text-xs text-slate-300 mt-1 flex items-center gap-2">
            <span><i class="fa-solid fa-box text-brandOrange"></i> Standard Parcel Express</span>
            <span>•</span>
            <span>Estimated Delivery: Today by 5:00 PM</span>
          </p>
        </div>
        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-xs font-bold uppercase tracking-wider">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
          <span id="result-status-text">Out for Delivery</span>
        </div>
      </div>

      <!-- Content Body: Timeline & Package Specs -->
      <div class="p-6 sm:p-8 grid lg:grid-cols-12 gap-8">
        
        <!-- Left: Interactive Timeline -->
        <div class="lg:col-span-7 space-y-6">
          <h3 class="font-extrabold text-brandNavy text-base flex items-center gap-2">
            <i class="fa-solid fa-timeline text-brandOrange"></i> Shipment Progress
          </h3>

          <div class="relative pl-6 border-l-2 border-slate-200 space-y-8 ml-3">
            
            <!-- Checkpoint 4 (Active/Latest) -->
            <div class="relative">
              <div class="absolute -left-[31px] top-0 w-6 h-6 rounded-full bg-brandOrange text-white flex items-center justify-center text-xs shadow-md">
                <i class="fa-solid fa-truck-fast"></i>
              </div>
              <p class="text-xs font-bold text-brandOrange uppercase">Out For Delivery</p>
              <h4 class="text-sm font-extrabold text-brandNavy">Dispatched with Local Courier Team</h4>
              <p class="text-xs text-slate-500 mt-0.5">Courier assigned: Michael S. (Vehicle Plate: NCF-8821)</p>
              <span class="text-[11px] text-slate-400 font-medium mt-1 inline-block">Today - 09:15 AM • Central City Sorting Hub</span>
            </div>

            <!-- Checkpoint 3 -->
            <div class="relative">
              <div class="absolute -left-[31px] top-0 w-6 h-6 rounded-full bg-brandNavy text-white flex items-center justify-center text-xs shadow-md">
                <i class="fa-solid fa-warehouse"></i>
              </div>
              <p class="text-xs font-bold text-slate-500 uppercase">Arrived at Destination Hub</p>
              <h4 class="text-sm font-bold text-slate-800">Scanned at Primary Inbound Sorting Center</h4>
              <span class="text-[11px] text-slate-400 font-medium mt-1 inline-block">Today - 04:30 AM • Inbound Facility Gate 4</span>
            </div>

            <!-- Checkpoint 2 -->
            <div class="relative">
              <div class="absolute -left-[31px] top-0 w-6 h-6 rounded-full bg-slate-300 text-slate-700 flex items-center justify-center text-xs">
                <i class="fa-solid fa-plane"></i>
              </div>
              <p class="text-xs font-bold text-slate-500 uppercase">In Transit</p>
              <h4 class="text-sm font-bold text-slate-800">Departed Origin Air Freight Terminal</h4>
              <span class="text-[11px] text-slate-400 font-medium mt-1 inline-block">Yesterday - 10:45 PM • Flight EXP-902</span>
            </div>

            <!-- Checkpoint 1 -->
            <div class="relative">
              <div class="absolute -left-[31px] top-0 w-6 h-6 rounded-full bg-slate-300 text-slate-700 flex items-center justify-center text-xs">
                <i class="fa-solid fa-box"></i>
              </div>
              <p class="text-xs font-bold text-slate-500 uppercase">Shipment Created</p>
              <h4 class="text-sm font-bold text-slate-800">Parcel Picked Up by Sender & Barcode Generated</h4>
              <span class="text-[11px] text-slate-400 font-medium mt-1 inline-block">Yesterday - 02:20 PM • Origin Branch</span>
            </div>

          </div>
        </div>

        <!-- Right: Shipment Specs & Recipient Box -->
        <div class="lg:col-span-5 space-y-4">
          <div class="bg-slate-50 p-5 rounded-2xl border border-slate-200 space-y-3 text-xs">
            <h4 class="font-extrabold text-brandNavy uppercase tracking-wider text-xs border-b border-slate-200 pb-2">
              Shipment Information
            </h4>
            
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-500">Sender:</span>
              <span class="font-bold text-slate-800">Apex Commercial Ltd.</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-500">Destination:</span>
              <span class="font-bold text-slate-800">Metro Hub, Office 402</span>
            </div>
            <div class="flex justify-between py-1 border-b border-slate-100">
              <span class="text-slate-500">Weight & Volume:</span>
              <span class="font-bold text-slate-800">2.4 kg (30x20x15 cm)</span>
            </div>
            <div class="flex justify-between py-1">
              <span class="text-slate-500">Proof of Delivery:</span>
              <span class="font-bold text-emerald-600">Signature Required</span>
            </div>
          </div>

          <!-- Help Callout -->
          <div class="p-4 rounded-2xl bg-orange-50 border border-orange-200 text-xs space-y-2">
            <p class="font-bold text-brandNavy flex items-center gap-1.5">
              <i class="fa-solid fa-headset text-brandOrange"></i> Need to reschedule delivery?
            </p>
            <p class="text-slate-600">Call our direct dispatch hotline at <strong class="text-brandOrange">+1 (800) 555-YOCOR</strong> with your waybill number.</p>
          </div>
        </div>

      </div>
    </div>

  </div>
</main>

<script>
function loadSampleTracking(id) {
  const input = document.getElementById('tracking-page-input');
  if (input) {
    input.value = id;
    document.getElementById('tracking-page-form').dispatchEvent(new Event('submit'));
  }
}
</script>

<?php include 'includes/footer.php'; ?>
