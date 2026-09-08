<?php
require_once __DIR__ . '/../../database/config.php';
require_once __DIR__ . '/../../database/validation.php';

// Dynamically query available active courier services from database (matches booking.php exactly)
$activeServices = [];
try {
    $q_pdo = getConnection();
    $q_stmt = $q_pdo->query("SELECT id, name, description, capacity, base_price, price_per_kg FROM services WHERE capacity > 0 AND is_active = 1 ORDER BY id ASC");
    $activeServices = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $activeServices = [];
}
?>
    <!-- RATE CALCULATOR / INSTANT ESTIMATE SECTION -->
    <section id="quote" class="py-10 sm:py-14 lg:py-16 bg-gradient-to-b from-slate-50 via-white to-slate-100 border-t border-slate-200 relative overflow-hidden">
      <div class="absolute inset-0 opacity-50 pointer-events-none flex items-center justify-center">
        <img src="public/calculator-bg.svg" alt="Express Shipping Rate Calculator Vector Pattern" class="w-full max-w-7xl h-auto object-cover">
      </div>

      <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-10 space-y-2">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-[11px] uppercase tracking-wider">
            <i class="fa-solid fa-calculator"></i> Real-time Rate Engine
          </div>
          <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-brandNavy tracking-tight">
            Instant <span class="text-brandOrange underline decoration-brandNavy/20 decoration-wavy">Shipping Estimate</span>
          </h2>
          <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">
            Calculate instant estimated door-to-door courier rates, ground freight, or full container cargo pricing in seconds.
          </p>
        </div>

        <!-- Form Card Container -->
        <div class="bg-white/95 backdrop-blur-md p-5 sm:p-7 rounded-2xl shadow-xl border border-slate-200/90 relative">
          <!-- PARCEL EXPRESS RATE CALCULATOR -->
          <div id="tab-parcel">
            <form id="rate-form-parcel" class="space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[11px] font-extrabold text-brandNavy uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Pickup Region & City</span>
                    <span class="text-brandOrange font-normal text-[10px]"><i class="fa-solid fa-location-dot"></i> Origin</span>
                  </label>
                  <div class="grid grid-cols-5 gap-1.5">
                    <select id="calc-origin-region" class="col-span-2 px-2 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-bold text-brandNavy transition-all">
                      <option value="Luzon" selected>Luzon</option>
                      <option value="Visayas">Visayas</option>
                      <option value="Mindanao">Mindanao</option>
                    </select>
                    <div class="relative col-span-3">
                      <input id="calc-origin" type="text" placeholder="e.g. Manila" required class="w-full px-2.5 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-medium text-slate-900 transition-all">
                    </div>
                  </div>
                </div>

                <div>
                  <label class="block text-[11px] font-extrabold text-brandNavy uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Dropoff Region & City</span>
                    <span class="text-brandOrange font-normal text-[10px]"><i class="fa-solid fa-flag-checkered"></i> Destination</span>
                  </label>
                  <div class="grid grid-cols-5 gap-1.5">
                    <select id="calc-destination-region" class="col-span-2 px-2 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-bold text-brandNavy transition-all">
                      <option value="Luzon">Luzon</option>
                      <option value="Visayas" selected>Visayas</option>
                      <option value="Mindanao">Mindanao</option>
                    </select>
                    <div class="relative col-span-3">
                      <input id="calc-destination" type="text" placeholder="e.g. Cebu City" required class="w-full px-2.5 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-medium text-slate-900 transition-all">
                    </div>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-[11px] font-extrabold text-brandNavy uppercase tracking-wider mb-1">Package Weight (kg)</label>
                  <div class="relative">
                    <i class="fa-solid fa-weight-hanging absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="calc-weight" type="number" min="0.1" step="0.1" value="1.0" class="w-full pl-9 pr-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-medium text-slate-900 transition-all">
                  </div>
                </div>

                <div>
                  <label class="block text-[11px] font-extrabold text-brandNavy uppercase tracking-wider mb-1">Service Tier</label>
                  <div class="relative">
                    <i class="fa-solid fa-sliders absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <select id="calc-service-id" class="w-full pl-9 pr-3 py-2 rounded-lg bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-xs font-medium text-slate-900 transition-all">
                      <?php if (empty($activeServices)): ?>
                        <option value="">No services available currently</option>
                      <?php else: ?>
                        <?php foreach ($activeServices as $srv): ?>
                          <option value="<?= $srv['id'] ?>">
                            <?= htmlspecialchars($srv['name']) ?> 
                            (₱<?= number_format((float)$srv['base_price'], 2) ?> base + ₱<?= number_format((float)$srv['price_per_kg'], 2) ?>/kg)
                          </option>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Live Output Box -->
              <div class="p-3.5 rounded-xl bg-gradient-to-r from-orange-50 via-slate-50 to-orange-50 border border-orange-200/80 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-lg bg-brandOrange text-white flex items-center justify-center text-sm font-bold shadow-sm">
                    <i class="fa-solid fa-receipt"></i>
                  </div>
                  <div>
                    <p class="font-extrabold text-brandNavy text-xs">Estimated Total Rate</p>
                    <p class="text-slate-500 text-[10px]">Includes fuel surcharge, GST & doorstep insurance.</p>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-[10px] font-bold text-slate-400 uppercase block">Calculated Price</span>
                  <span id="calc-result-price" class="text-lg sm:text-xl font-black text-brandOrange">₱150.00 <span class="text-[11px] font-normal text-slate-500">PHP</span></span>
                </div>
              </div>

              <div class="pt-1">
                <button type="submit" class="w-full bg-brandOrange hover:bg-orange-600 text-white font-black py-2.5 px-5 rounded-xl transition-all shadow-md brand-glow-orange text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                  <i class="fa-solid fa-box-open text-xs"></i> Proceed to Book This Delivery
                </button>
              </div>
            </form>
          </div>

          <!-- Micro Security Footnote -->
          <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center justify-between text-[10px] text-slate-500 gap-2">
            <span class="flex items-center gap-1"><i class="fa-solid fa-shield-halved text-emerald-500"></i> No hidden fees. Transparent volumetric rates.</span>
            <span class="flex items-center gap-1"><i class="fa-solid fa-headset text-brandOrange"></i> Need enterprise corporate SLAs? <a href="contact.php" class="text-brandNavy font-bold hover:underline">Contact Sales</a></span>
          </div>
        </div>
      </div>
    </section>
