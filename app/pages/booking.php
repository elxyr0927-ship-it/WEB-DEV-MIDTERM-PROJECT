<?php
$pageTitle = "Book Delivery & Dispatch | YOCOR Express";
$activeNav = "booking";
$prefilledService = isset($_GET['service']) ? htmlspecialchars($_GET['service']) : 'express';

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-12 bg-slate-50">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <!-- Header -->
    <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
      <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-xs uppercase tracking-widest">
        <i class="fa-solid fa-truck-pickup"></i> Dispatch Booking
      </div>
      <h1 class="text-3xl font-black text-brandNavy">Book a Shipment Pickup</h1>
      <p class="text-slate-600 text-sm">Schedule a direct courier pickup at your home, office, or warehouse.</p>
    </div>

    <!-- Booking Form Card -->
    <div class="bg-white p-8 sm:p-10 rounded-3xl shadow-xl border border-slate-200 relative">
      <form id="booking-form" class="space-y-8">
        
        <!-- Step 1: Sender & Pickup -->
        <div>
          <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
            <span class="w-6 h-6 rounded-full bg-brandOrange text-white text-xs flex items-center justify-center font-bold">1</span>
            Sender & Pickup Details
          </h3>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Full Name / Company</label>
              <input type="text" required placeholder="e.g. John Doe / Apex Corp" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Contact Phone Number</label>
              <input type="tel" required placeholder="e.g. +63 912 345 6789" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-slate-700 mb-1">Complete Pickup Address</label>
              <input type="text" required placeholder="Street address, building name, unit number, city" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
          </div>
        </div>

        <!-- Step 2: Recipient & Destination -->
        <div>
          <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
            <span class="w-6 h-6 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">2</span>
            Recipient & Delivery Details
          </h3>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Recipient Name</label>
              <input type="text" required placeholder="e.g. Maria Santos" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Recipient Phone Number</label>
              <input type="tel" required placeholder="e.g. +63 998 765 4321" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
            <div class="sm:col-span-2">
              <label class="block text-xs font-bold text-slate-700 mb-1">Complete Delivery Address</label>
              <input type="text" required placeholder="Destination address, city, postal code" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
          </div>
        </div>

        <!-- Step 3: Package Specifications -->
        <div>
          <h3 class="text-sm font-extrabold text-brandNavy uppercase tracking-wider mb-4 flex items-center gap-2 border-b border-slate-100 pb-2">
            <span class="w-6 h-6 rounded-full bg-brandNavy text-white text-xs flex items-center justify-center font-bold">3</span>
            Package Details & Preferred Service
          </h3>
          <div class="grid sm:grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Package Category</label>
              <select class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
                <option>Documents & Pouch</option>
                <option selected>Standard Parcel Box</option>
                <option>Fragile / Electronics</option>
                <option>Heavy / Commercial Pallet</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Estimated Weight (kg)</label>
              <input type="number" min="0.1" step="0.5" value="1.0" required class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Pickup Schedule</label>
              <select class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
                <option>Today (Morning 9AM - 12PM)</option>
                <option>Today (Afternoon 1PM - 5PM)</option>
                <option>Tomorrow (Morning 9AM - 12PM)</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Confirmation Banner -->
        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
          <div class="flex items-center gap-3">
            <i class="fa-solid fa-clipboard-check text-2xl text-brandOrange"></i>
            <div>
              <p class="font-bold text-brandNavy">Instant Digital Waybill Generation</p>
              <p class="text-slate-500">A barcode waybill ID will be assigned upon booking confirmation.</p>
            </div>
          </div>
          <button type="submit" class="w-full sm:w-auto bg-brandOrange hover:bg-orange-600 text-white font-extrabold py-3.5 px-8 rounded-xl brand-glow-orange transition-all text-xs uppercase tracking-wider">
            Confirm & Schedule Pickup
          </button>
        </div>

      </form>
    </div>

  </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
