<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../database/validation.php';

$pageTitle = "Instant Shipping Rate Calculator | YOCOR Express";
$activeNav = "quote";

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow">
  <!-- Calculator Hero Banner -->
  <section class="py-12 bg-brandNavy text-white text-center">
    <div class="max-w-4xl mx-auto px-4 space-y-2">
      <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-brandOrange/20 border border-brandOrange/40 text-brandOrange font-bold text-xs uppercase tracking-widest">
        <i class="fa-solid fa-calculator"></i> Pricing Engine
      </div>
      <h1 class="text-3xl font-black">Shipping Cost Estimator</h1>
      <p class="text-slate-200 text-sm">Transparent rates with no hidden fuel charges or pickup fees.</p>
    </div>
  </section>

  <!-- Quote Section Form -->
  <?php include __DIR__ . '/../includes/sections/quote.php'; ?>

  <!-- FAQ / Price Breakdown Info -->
  <section class="py-16 bg-white border-t border-slate-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
      <h2 class="text-2xl font-bold text-brandNavy text-center">Frequently Asked Pricing Questions</h2>
      
      <div class="grid sm:grid-cols-2 gap-6 text-xs text-slate-600">
        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
          <h3 class="font-bold text-brandNavy text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-question text-brandOrange"></i> How is volumetric weight calculated?
          </h3>
          <p>Volumetric weight uses standard IATA formula: (Length × Width × Height in cm) / 5000. Senders are charged whichever is greater between actual weight and dimensional weight.</p>
        </div>

        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
          <h3 class="font-bold text-brandNavy text-sm flex items-center gap-2">
            <i class="fa-solid fa-circle-question text-brandOrange"></i> Are packages insured during transit?
          </h3>
          <p>Yes, all standard express shipments automatically include basic cargo loss protection up to ₱5,000. Extended declaration insurance can be added at booking checkout.</p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
