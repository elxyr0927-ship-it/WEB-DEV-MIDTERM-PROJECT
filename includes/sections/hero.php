    <!-- HERO SECTION WITH QUICK TRACKING -->
    <section id="tracking" class="relative py-16 lg:py-24 overflow-hidden brand-hero-bg text-white ripped-edge-bottom">
      <div class="absolute inset-0 opacity-10 pointer-events-none brand-pattern-bg"></div>

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid lg:grid-cols-12 gap-12 items-center">
          
          <!-- Hero Text -->
          <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-brandOrange/20 border border-brandOrange/40 text-brandOrange font-bold text-xs uppercase tracking-wider backdrop-blur-md">
              <i class="fa-solid fa-bolt text-brandOrange"></i> Fast, Secure & Reliable Global Supply Chain
            </div>
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight tracking-tight">
              Delivering Your Express Cargo With <span class="text-brandOrange underline decoration-white/20 decoration-wavy">Absolute Precision</span>
            </h1>
            <p class="text-base sm:text-lg text-slate-200 leading-relaxed max-w-2xl mx-auto lg:mx-0">
              We empower businesses and communities by providing seamless end-to-end transport, door-to-door courier services, freight forwarding, and warehousing solutions.
            </p>

            <!-- Key Features Micro Pill List -->
            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2 text-xs font-semibold text-white">
              <span class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md px-3.5 py-2 rounded-lg border border-white/20 shadow-sm">
                <i class="fa-solid fa-circle-check text-brandOrange"></i> Real-time GPS Tracking
              </span>
              <span class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md px-3.5 py-2 rounded-lg border border-white/20 shadow-sm">
                <i class="fa-solid fa-circle-check text-brandOrange"></i> 99.4% On-Time Rate
              </span>
              <span class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md px-3.5 py-2 rounded-lg border border-white/20 shadow-sm">
                <i class="fa-solid fa-circle-check text-brandOrange"></i> End-to-End Insurance
              </span>
            </div>
          </div>

          <!-- Interactive Quick Tracking Column -->
          <div class="lg:col-span-5">
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-2xl border border-slate-200/80 relative text-slate-800">
              <div class="absolute -top-3 left-6 bg-brandOrange text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider shadow-md">
                Live Shipment Hub
              </div>
              
              <h2 class="text-xl font-extrabold text-brandNavy mb-1">Track Your Package</h2>
              <p class="text-xs text-slate-500 mb-6">Enter your waybill or tracking ID to check live status.</p>

              <form action="tracking.php" method="GET" class="space-y-4">
                <div class="relative">
                  <i class="fa-solid fa-barcode absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                  <input name="tracking_no" type="text" placeholder="e.g. YCR-9824109" required class="w-full pl-12 pr-4 py-3.5 text-slate-900 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange focus:border-brandOrange outline-none text-sm font-medium transition-all">
                </div>
                <button type="submit" class="w-full bg-brandNavy hover:bg-slate-900 text-white font-bold py-3.5 px-6 rounded-xl transition-all flex items-center justify-center gap-2 text-sm shadow-lg brand-glow-navy">
                  <i class="fa-solid fa-magnifying-glass"></i> Track Package Now
                </button>
              </form>

              <div class="mt-6 pt-6 border-t border-slate-100 flex items-center justify-between text-xs">
                <span class="text-slate-500">Need an instant rate estimate?</span>
                <a href="quote.php" class="text-brandOrange font-bold hover:underline flex items-center gap-1">
                  Get Quote <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
              </div>
            </div>
          </div>

        </div>

        <!-- YOCOR Delivery Fleet & Skyline Vector Banner -->
        <div class="mt-10 pt-4 border-t border-white/10 relative z-10 w-full overflow-hidden flex justify-center">
          <img src="./public/truck design.svg" alt="YOCOR Express Delivery Fleet, Courier & Skyline Illustration" class="w-full max-w-7xl h-auto object-contain filter drop-shadow-xl hero-truck-overlay">
        </div>

      </div>
    </section>
