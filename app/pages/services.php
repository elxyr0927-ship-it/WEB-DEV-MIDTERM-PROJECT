<?php
$pageTitle = "Our Logistics Services | YOCOR Express";
$activeNav = "services";

include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow">
  
  <!-- Services Hero Banner -->
  <section class="py-16 bg-brandNavy text-white relative overflow-hidden">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-4">
      <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-brandOrange/20 border border-brandOrange/40 text-brandOrange font-bold text-xs uppercase tracking-widest">
        <i class="fa-solid fa-cubes"></i> End-to-End Solutions
      </div>
      <h1 class="text-3xl sm:text-4xl font-black">Comprehensive Freight & Express Services</h1>
      <p class="text-slate-200 text-sm sm:text-base max-w-2xl mx-auto">
        Designed for individual senders, small businesses, and enterprise supply chains requiring speed, safety, and transparency.
      </p>
    </div>
  </section>

  <!-- Include Core Services Grid -->
  <?php include __DIR__ . '/../includes/sections/services.php'; ?>

  <!-- Extended Services Details Section -->
  <section class="py-16 bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
      
      <div class="text-center max-w-2xl mx-auto">
        <h2 class="text-2xl sm:text-3xl font-black text-brandNavy">How We Deliver Value to Every Client</h2>
        <p class="text-slate-600 text-sm mt-2">Specialized logistics capabilities tailored for modern commerce.</p>
      </div>

      <div class="grid md:grid-cols-2 gap-8 items-center">
        <div class="p-8 rounded-3xl bg-slate-50 border border-slate-200 space-y-4">
          <div class="w-12 h-12 rounded-xl bg-brandOrange text-white flex items-center justify-center text-xl shadow-md">
            <i class="fa-solid fa-plane-up"></i>
          </div>
          <h3 class="text-xl font-bold text-brandNavy">Same-Day Urgent Air Dispatch</h3>
          <p class="text-slate-600 text-sm leading-relaxed">
            For critical legal documents, medical supplies, and urgent manufacturing components. Flight routing with dedicated priority baggage placement and instant courier handoff upon landing.
          </p>
          <ul class="text-xs text-slate-700 space-y-2 pt-2">
            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-brandOrange"></i> Dedicated airport ramp handoff</li>
            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-brandOrange"></i> Direct point-to-point courier escort</li>
          </ul>
        </div>

        <div class="p-8 rounded-3xl bg-slate-50 border border-slate-200 space-y-4">
          <div class="w-12 h-12 rounded-xl bg-brandNavy text-white flex items-center justify-center text-xl shadow-md">
            <i class="fa-solid fa-temperature-arrow-down"></i>
          </div>
          <h3 class="text-xl font-bold text-brandNavy">Cold Chain & Temperature-Controlled Transit</h3>
          <p class="text-slate-600 text-sm leading-relaxed">
            Refrigerated and frozen transport logistics equipped with IoT thermal sensors. Real-time temperature alerts safeguard perishable goods, pharmaceuticals, and temperature-sensitive chemicals.
          </p>
          <ul class="text-xs text-slate-700 space-y-2 pt-2">
            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-brandNavy"></i> Continuous thermal data logger logging</li>
            <li class="flex items-center gap-2"><i class="fa-solid fa-check text-brandNavy"></i> ISO-certified cold storage lockers</li>
          </ul>
        </div>
      </div>

    </div>
  </section>

  <!-- How It Works Section -->
  <?php include __DIR__ . '/../includes/sections/how-it-works.php'; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
