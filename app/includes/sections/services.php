    <!-- SERVICES / OUR SOLUTIONS SECTION -->
    <section id="services" class="py-10 sm:py-14 lg:py-16 bg-gradient-to-b from-slate-50 via-white to-slate-50 relative overflow-hidden border-b border-slate-200">
      <div class="absolute inset-0 opacity-40 pointer-events-none flex items-center justify-center">
        <img src="public/logistics-network-bg.svg" alt="Global Supply Chain Network Vector Background" class="w-full max-w-7xl h-auto object-cover">
      </div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <!-- Section Header -->
        <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-12 space-y-2">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-100/80 border border-orange-200 text-brandOrange font-bold text-[11px] uppercase tracking-wider">
            <i class="fa-solid fa-cubes"></i> Tailored Freight & Express Solutions
          </div>
          <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-brandNavy tracking-tight">
            Comprehensive Logistics Built For <span class="text-brandOrange underline decoration-brandNavy/20 decoration-wavy">Global Business</span>
          </h2>
          <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">
            From local door-to-door express parcels to full container freight forwarding and automated fulfillment hubs, we optimize every segment of your supply chain.
          </p>
        </div>

        <!-- 3 Service Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 sm:gap-6">
          
          <!-- Solution Card 1: Door-to-Door Express -->
          <div class="p-5 sm:p-6 rounded-2xl bg-white/90 backdrop-blur-md border border-slate-200/90 shadow-sm hover:shadow-md hover:border-brandOrange/50 transition-all flex flex-col justify-between group relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-orange-500/5 rounded-bl-full pointer-events-none group-hover:bg-brandOrange/10 transition-all"></div>
            
            <div>
              <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-xl bg-orange-100 text-brandOrange flex items-center justify-center text-lg group-hover:bg-brandOrange group-hover:text-white transition-all shadow-sm">
                  <i class="fa-solid fa-truck-fast"></i>
                </div>
                <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-orange-50 text-brandOrange border border-orange-200">
                  Same-Day Available
                </span>
              </div>

              <h3 class="text-base font-extrabold text-brandNavy mb-2 group-hover:text-brandOrange transition-colors">Express Door-to-Door</h3>
              <p class="text-slate-600 text-xs leading-relaxed mb-4">
                Urgent local and inter-city parcel deliveries with guaranteed same-day or next-day turnaround options, live courier assignment, and direct doorstep signature verification.
              </p>

              <!-- Feature Highlights Tags -->
              <div class="space-y-1.5 mb-5 pt-3 border-t border-slate-100 text-[11px] text-slate-700">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Direct Door-to-Door Pickup
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Proof of Delivery Signature
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Priority Air & Ground Transit
                </div>
              </div>
            </div>

            <a href="<?= isset($_SESSION['user_id']) ? 'booking.php?service=parcel' : 'login.php?redirect=booking.php%3Fservice%3Dparcel' ?>" class="w-full bg-slate-50 hover:bg-brandOrange text-brandNavy hover:text-white font-bold py-2.5 px-4 rounded-xl border border-slate-200 hover:border-brandOrange transition-all flex items-center justify-between text-xs group-hover:shadow-sm">
              <span>Book Parcel Delivery</span>
              <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform text-[11px]"></i>
            </a>
          </div>

          <!-- Solution Card 2: Freight & Heavy Cargo -->
          <div class="p-5 sm:p-6 rounded-2xl bg-white/90 backdrop-blur-md border border-slate-200/90 shadow-sm hover:shadow-md hover:border-brandNavy/50 transition-all flex flex-col justify-between group relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-navy-500/5 rounded-bl-full pointer-events-none group-hover:bg-brandNavy/10 transition-all"></div>

            <div>
              <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-brandNavy flex items-center justify-center text-lg group-hover:bg-brandNavy group-hover:text-white transition-all shadow-sm">
                  <i class="fa-solid fa-boxes-stacked"></i>
                </div>
                <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-slate-100 text-brandNavy border border-slate-200">
                  Bulk & LTL / FTL
                </span>
              </div>

              <h3 class="text-base font-extrabold text-brandNavy mb-2 group-hover:text-brandNavy transition-colors">Freight & Heavy Cargo</h3>
              <p class="text-slate-600 text-xs leading-relaxed mb-4">
                Scalable bulk transportation, pallet shipments, multimodal container freight (Land, Air, Sea), and full truckload solutions for commercial enterprises and industrial suppliers.
              </p>

              <!-- Feature Highlights Tags -->
              <div class="space-y-1.5 mb-5 pt-3 border-t border-slate-100 text-[11px] text-slate-700">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Full Container & Pallet Loads
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Custom Brokerage & Clearance
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Heavy Equipment Transport
                </div>
              </div>
            </div>

            <a href="quote.php?tab=freight" class="w-full bg-slate-50 hover:bg-brandNavy text-brandNavy hover:text-white font-bold py-2.5 px-4 rounded-xl border border-slate-200 hover:border-brandNavy transition-all flex items-center justify-between text-xs group-hover:shadow-sm">
              <span>Request Freight Rates</span>
              <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform text-[11px]"></i>
            </a>
          </div>

          <!-- Solution Card 3: Warehousing & Fulfillment -->
          <div class="p-5 sm:p-6 rounded-2xl bg-white/90 backdrop-blur-md border border-slate-200/90 shadow-sm hover:shadow-md hover:border-brandOrange/50 transition-all flex flex-col justify-between group relative overflow-hidden md:col-span-2 lg:col-span-1">
            <div class="absolute top-0 right-0 w-24 h-24 bg-orange-500/5 rounded-bl-full pointer-events-none group-hover:bg-brandOrange/10 transition-all"></div>

            <div>
              <div class="flex items-center justify-between mb-4">
                <div class="w-10 h-10 rounded-xl bg-orange-100 text-brandOrange flex items-center justify-center text-lg group-hover:bg-brandOrange group-hover:text-white transition-all shadow-sm">
                  <i class="fa-solid fa-warehouse"></i>
                </div>
                <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                  Smart Storage Hub
                </span>
              </div>

              <h3 class="text-base font-extrabold text-brandNavy mb-2 group-hover:text-brandOrange transition-colors">Warehousing & Fulfillment</h3>
              <p class="text-slate-600 text-xs leading-relaxed mb-4">
                Secure, climate-controlled storage facilities equipped with automated WMS inventory management, pick-and-pack operations, and e-commerce channel integrations.
              </p>

              <!-- Feature Highlights Tags -->
              <div class="space-y-1.5 mb-5 pt-3 border-t border-slate-100 text-[11px] text-slate-700">
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Real-time Stock Tracking WMS
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> Climate-Controlled Units
                </div>
                <div class="flex items-center gap-2">
                  <i class="fa-solid fa-circle-check text-emerald-500 text-xs"></i> E-Commerce API Integrations
                </div>
              </div>
            </div>

            <a href="contact.php" class="w-full bg-slate-50 hover:bg-brandOrange text-brandNavy hover:text-white font-bold py-2.5 px-4 rounded-xl border border-slate-200 hover:border-brandOrange transition-all flex items-center justify-between text-xs group-hover:shadow-sm">
              <span>Explore Fulfillment</span>
              <i class="fa-solid fa-arrow-right group-hover:translate-x-1 transition-transform text-[11px]"></i>
            </a>
          </div>

        </div>
      </div>
    </section>
