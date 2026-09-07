<?php
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

          <form id="contact-form" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Your Name</label>
                <input type="text" required placeholder="e.g. Alex Santos" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Email Address</label>
                <input type="email" required placeholder="e.g. alex@example.com" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
              </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Tracking ID (Optional)</label>
                <input type="text" placeholder="e.g. YCR-9824109" class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
              </div>
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">Inquiry Subject</label>
                <select class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all">
                  <option>Package Status & Delivery Delay</option>
                  <option>Commercial Freight Quote</option>
                  <option>Corporate Business Account</option>
                  <option>Claims & Transit Insurance</option>
                  <option>General Inquiries</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Detailed Message</label>
              <textarea rows="4" required placeholder="Describe your inquiry in detail..." class="w-full px-4 py-3 rounded-xl bg-slate-50 border border-slate-300 focus:bg-white focus:ring-2 focus:ring-brandOrange outline-none text-xs text-slate-900 transition-all"></textarea>
            </div>

            <button type="submit" class="w-full bg-brandNavy hover:bg-slate-900 text-white font-extrabold py-3.5 px-6 rounded-xl brand-glow-navy transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2">
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
