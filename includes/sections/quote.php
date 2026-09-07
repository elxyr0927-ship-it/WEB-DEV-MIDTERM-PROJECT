    <!-- RATE CALCULATOR / INSTANT ESTIMATE SECTION -->
    <section id="quote" class="py-24 bg-gradient-to-b from-slate-50 via-white to-slate-100 border-t border-slate-200 relative overflow-hidden">
      <div class="absolute inset-0 opacity-50 pointer-events-none flex items-center justify-center">
        <img src="./public/calculator-bg.svg" alt="Express Shipping Rate Calculator Vector Pattern" class="w-full max-w-7xl h-auto object-cover">
      </div>

      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-12 space-y-3">
          <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-orange-100 text-brandOrange font-bold text-xs uppercase tracking-widest">
            <i class="fa-solid fa-calculator"></i> Real-time Rate Engine
          </div>
          <h2 class="text-3xl sm:text-4xl font-black text-brandNavy tracking-tight">
            Instant <span class="text-brandOrange underline decoration-brandNavy/20 decoration-wavy">Shipping Estimate</span>
          </h2>
          <p class="text-slate-600 text-sm leading-relaxed">
            Calculate instant estimated door-to-door courier rates, ground freight, or full container cargo pricing in seconds.
          </p>
        </div>

        <!-- Form Card Container -->
        <div class="bg-white/95 backdrop-blur-md p-8 sm:p-12 rounded-3xl shadow-2xl border border-slate-200/90 relative">
          <!-- Top Mode Selector Tabs -->
          <div class="flex items-center justify-center gap-3 mb-8 p-1.5 bg-slate-100 rounded-2xl max-w-md mx-auto text-xs font-bold text-slate-600">
            <button type="button" data-tab="parcel" class="tab-button flex-1 py-2.5 px-4 rounded-xl bg-white text-brandNavy shadow-sm border border-slate-200 flex items-center justify-center gap-2 transition-all active">
              <i class="fa-solid fa-box text-brandOrange"></i> Parcel Express
            </button>
            <button type="button" data-tab="freight" class="tab-button flex-1 py-2.5 px-4 rounded-xl hover:text-brandNavy flex items-center justify-center gap-2 transition-all">
              <i class="fa-solid fa-truck-ramp-box"></i> Commercial Freight
            </button>
          </div>

          <!-- PARCEL EXPRESS TAB CONTENT -->
          <div id="tab-parcel" class="tab-content">
            <form id="rate-form-parcel" class="space-y-6">
              <div class="grid sm:grid-cols-2 gap-6">
                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2 flex items-center justify-between">
                    <span>Origin City / Zip</span>
                    <span class="text-brandOrange font-normal text-[11px]"><i class="fa-solid fa-location-dot"></i> Pickup</span>
                  </label>
                  <div class="relative">
                    <i class="fa-solid fa-plane-departure absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input id="calc-origin" type="text" placeholder="e.g. Manila (1000)" required class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2 flex items-center justify-between">
                    <span>Destination City / Zip</span>
                    <span class="text-brandOrange font-normal text-[11px]"><i class="fa-solid fa-flag-checkered"></i> Dropoff</span>
                  </label>
                  <div class="relative">
                    <i class="fa-solid fa-plane-arrival absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input id="calc-destination" type="text" placeholder="e.g. Cebu City (6000)" required class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                  </div>
                </div>
              </div>

              <div class="grid sm:grid-cols-2 gap-6">
                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Package Weight (kg)</label>
                  <div class="relative">
                    <i class="fa-solid fa-weight-hanging absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input id="calc-weight" type="number" min="0.5" step="0.5" value="1.0" class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Service Tier</label>
                  <div class="relative">
                    <i class="fa-solid fa-sliders absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <select id="calc-tier" class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                      <option value="sameday">Same-Day Express Air (Guaranteed)</option>
                      <option value="priority" selected>Priority Express (1-2 Days)</option>
                      <option value="standard">Standard Ground Courier (3-5 Days)</option>
                    </select>
                  </div>
                </div>
              </div>

              <!-- Live Output Box -->
              <div class="p-4 rounded-2xl bg-gradient-to-r from-orange-50 via-slate-50 to-orange-50 border border-orange-200/80 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 rounded-xl bg-brandOrange text-white flex items-center justify-center text-lg font-bold shadow-md">
                    <i class="fa-solid fa-receipt"></i>
                  </div>
                  <div>
                    <p class="font-extrabold text-brandNavy text-sm">Estimated Total Rate</p>
                    <p class="text-slate-500 text-[11px]">Includes fuel surcharge, GST & doorstep insurance.</p>
                  </div>
                </div>
                <div class="text-right">
                  <span class="text-xs font-bold text-slate-400 uppercase block">Calculated Price</span>
                  <span id="calc-result-price" class="text-2xl font-black text-brandOrange">₱150.00 <span class="text-xs font-normal text-slate-500">PHP</span></span>
                </div>
              </div>

              <div class="pt-2">
                <button type="submit" class="w-full bg-brandOrange hover:bg-orange-600 text-white font-black py-4 px-6 rounded-xl transition-all shadow-xl brand-glow-orange text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                  <i class="fa-solid fa-box-open"></i> Proceed to Book This Delivery
                </button>
              </div>
            </form>
          </div>

          <!-- COMMERCIAL FREIGHT TAB CONTENT -->
          <div id="tab-freight" class="tab-content hidden">
            <form id="rate-form-freight" class="space-y-6">
              <div class="grid sm:grid-cols-2 gap-6">
                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Freight Type</label>
                  <div class="relative">
                    <i class="fa-solid fa-boxes-stacked absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <select class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                      <option>Less-Than-Truckload (LTL Pallet)</option>
                      <option>Full Truckload (FTL 10-Wheeler / 40ft)</option>
                      <option>Multimodal Container Ocean Freight</option>
                      <option>Industrial Oversized Equipment</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Estimated Total Weight (Tons)</label>
                  <div class="relative">
                    <i class="fa-solid fa-scale-unbalanced-flip absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="number" min="0.5" step="0.1" value="2.5" class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                  </div>
                </div>
              </div>

              <div class="grid sm:grid-cols-2 gap-6">
                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Origin Port / Logistics Center</label>
                  <input type="text" placeholder="e.g. Subic FreePort Zone" required class="w-full px-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                </div>
                <div>
                  <label class="block text-xs font-extrabold text-brandNavy uppercase tracking-wider mb-2">Destination Cargo Terminal</label>
                  <input type="text" placeholder="e.g. Davao Port Terminal" required class="w-full px-4 py-3.5 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium text-slate-900 transition-all">
                </div>
              </div>

              <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between text-xs">
                <span class="text-slate-600 font-medium"><i class="fa-solid fa-circle-info text-brandNavy mr-1.5"></i> Commercial Freight requires dedicated route planning by our dispatch team.</span>
                <span class="text-brandNavy font-extrabold">Custom Quote</span>
              </div>

              <button type="submit" class="w-full bg-brandNavy hover:bg-slate-900 text-white font-black py-4 px-6 rounded-xl transition-all shadow-xl brand-glow-navy text-sm uppercase tracking-wider flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane"></i> Request Dedicated Commercial Freight Quote
              </button>
            </form>
          </div>

          <!-- Micro Security Footnote -->
          <div class="mt-6 pt-6 border-t border-slate-100 flex flex-wrap items-center justify-between text-[11px] text-slate-500">
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-shield-halved text-emerald-500"></i> No hidden fees. Transparent volumetric rates.</span>
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-headset text-brandOrange"></i> Need enterprise corporate SLAs? <a href="contact.php" class="text-brandNavy font-bold hover:underline">Contact Sales</a></span>
          </div>
        </div>
      </div>
    </section>
