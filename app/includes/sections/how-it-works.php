    <!-- HOW IT WORKS / CUSTOMER JOURNEY (STEP-BY-STEP) -->
    <section class="py-10 sm:py-14 bg-white border-b border-slate-200">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-2xl mx-auto mb-8 sm:mb-12 space-y-2">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-orange-100 text-brandOrange font-bold text-[11px] uppercase tracking-wider">
            <i class="fa-solid fa-route"></i> Seamless Process
          </div>
          <h2 class="text-xl sm:text-2xl lg:text-3xl font-black text-brandNavy tracking-tight">
            How Our Express Delivery <span class="text-brandOrange underline decoration-brandNavy/20 decoration-wavy">Works</span>
          </h2>
          <p class="text-slate-600 text-xs sm:text-sm leading-relaxed">
            From instant online booking to final doorstep delivery, we make shipping effortless for you and your customers.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5 relative">
          
          <!-- Step 1 -->
          <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 hover:border-brandOrange/50 hover:shadow-md transition-all text-center relative group">
            <div class="w-9 h-9 rounded-full bg-brandNavy text-white font-black text-sm flex items-center justify-center mx-auto mb-3 group-hover:bg-brandOrange transition-colors shadow-sm">
              1
            </div>
            <h3 class="font-extrabold text-brandNavy text-sm mb-1.5">1. Get Instant Rate</h3>
            <p class="text-[11px] text-slate-500 leading-relaxed">
              Use our quick calculator to estimate standard parcel or commercial freight rates in seconds.
            </p>
          </div>

          <!-- Step 2 -->
          <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 hover:border-brandOrange/50 hover:shadow-md transition-all text-center relative group">
            <div class="w-9 h-9 rounded-full bg-brandNavy text-white font-black text-sm flex items-center justify-center mx-auto mb-3 group-hover:bg-brandOrange transition-colors shadow-sm">
              2
            </div>
            <h3 class="font-extrabold text-brandNavy text-sm mb-1.5">2. Book & Dispatch</h3>
            <p class="text-[11px] text-slate-500 leading-relaxed">
              Fill out sender, recipient, and package details to schedule same-day doorstep pickup.
            </p>
          </div>

          <!-- Step 3 -->
          <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 hover:border-brandOrange/50 hover:shadow-md transition-all text-center relative group">
            <div class="w-9 h-9 rounded-full bg-brandNavy text-white font-black text-sm flex items-center justify-center mx-auto mb-3 group-hover:bg-brandOrange transition-colors shadow-sm">
              3
            </div>
            <h3 class="font-extrabold text-brandNavy text-sm mb-1.5">3. Track In Real-Time</h3>
            <p class="text-[11px] text-slate-500 leading-relaxed">
              Receive your tracking number and monitor live transit checkpoints 24/7 across our network.
            </p>
          </div>

          <!-- Step 4 -->
          <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 hover:border-brandOrange/50 hover:shadow-md transition-all text-center relative group">
            <div class="w-9 h-9 rounded-full bg-brandNavy text-white font-black text-sm flex items-center justify-center mx-auto mb-3 group-hover:bg-brandOrange transition-colors shadow-sm">
              4
            </div>
            <h3 class="font-extrabold text-brandNavy text-sm mb-1.5">4. Safe Delivery</h3>
            <p class="text-[11px] text-slate-500 leading-relaxed">
              Package arrives securely with signature proof of delivery and instant confirmation notice.
            </p>
          </div>

        </div>

        <div class="mt-8 sm:mt-10 text-center">
          <a href="<?= isset($_SESSION['user_id']) ? 'booking.php' : 'login.php?redirect=booking.php' ?>" class="inline-flex items-center gap-2 bg-brandOrange hover:bg-orange-600 text-white font-bold text-xs sm:text-sm px-5 py-2.5 rounded-xl brand-glow-orange transition-all shadow-sm">
            <span>Start Shipping With Us Today</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
          </a>
        </div>

      </div>
    </section>
