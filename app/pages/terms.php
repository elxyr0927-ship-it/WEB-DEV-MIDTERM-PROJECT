<?php
$pageTitle = "Terms of Service | YOCOR Express";
$activeNav = "terms";
include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-10 sm:py-14 bg-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <!-- Breadcrumb & Header -->
        <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brandNavy/5 border border-brandNavy/10 text-brandNavy font-bold text-xs uppercase tracking-wider mb-2">
                <i class="fa-solid fa-scale-balanced text-brandOrange"></i> Legal &amp; Customer Agreement
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-brandNavy font-heading">
                Terms of Service
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Effective Date: <?= date('F d, Y') ?> &bull; Governing all transport, courier, and freight agreements.
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 text-xs sm:text-sm text-slate-700 space-y-6 leading-relaxed">
            
            <section>
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">1</span>
                    Contractual Agreement
                </h2>
                <p>
                    By booking a shipment, scheduling a pickup, or utilizing tracking services provided by <strong>YOCOR Express Logistic</strong>, you agree to be bound by these Terms of Service. If you do not accept these terms, you must refrain from using our freight and logistics platform.
                </p>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">2</span>
                    Customer Responsibilities &amp; Accurate Declaration
                </h2>
                <p class="mb-2">
                    As the sender or cargo consignor, you warrant and agree that:
                </p>
                <ul class="list-disc list-inside space-y-1.5 ml-2 text-slate-600">
                    <li>All pickup and delivery addresses, recipient phone numbers, and contact names are accurate and complete.</li>
                    <li>Declared cargo weight (kg) and dimensional measurements reflect true parcel specifications.</li>
                    <li>Shipments are adequately packaged, padded, and labeled to endure transit by land, sea, or air.</li>
                </ul>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">3</span>
                    Strictly Prohibited &amp; Dangerous Goods
                </h2>
                <p class="mb-2">
                    YOCOR Express refuses shipment of any goods that violate Philippine law or transport safety protocols, including:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 text-slate-600">
                    <div class="p-2.5 rounded-lg bg-rose-50/70 border border-rose-100 flex items-center gap-2 text-xs">
                        <i class="fa-solid fa-ban text-rose-500 text-sm"></i>
                        <span>Illegal narcotics &amp; dangerous drugs</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-rose-50/70 border border-rose-100 flex items-center gap-2 text-xs">
                        <i class="fa-solid fa-bomb text-rose-500 text-sm"></i>
                        <span>Explosives, firearms &amp; ammunition</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-rose-50/70 border border-rose-100 flex items-center gap-2 text-xs">
                        <i class="fa-solid fa-biohazard text-rose-500 text-sm"></i>
                        <span>Flammable liquids &amp; hazardous chemicals</span>
                    </div>
                    <div class="p-2.5 rounded-lg bg-rose-50/70 border border-rose-100 flex items-center gap-2 text-xs">
                        <i class="fa-solid fa-paw text-rose-500 text-sm"></i>
                        <span>Live animals (without special permits)</span>
                    </div>
                </div>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">4</span>
                    Shipping Rates, Regional Island Fees &amp; Surcharges
                </h2>
                <p>
                    All delivery charges are calculated dynamically based on package weight, selected tier (Standard Parcel, Priority Freight, or Cold Chain), and Philippine Island transit routes (Luzon, Visayas, Mindanao). All rate changes made by administration apply to new bookings created subsequent to the update.
                </p>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">5</span>
                    Cargo Liability &amp; Transit Protection
                </h2>
                <p>
                    YOCOR Express implements high-density chain-of-custody tracking. Standard dispatches are covered by basic cargo protection up to ₱5,000.00 for proven loss or damage caused by carrier negligence. Higher-value shipments must request supplemental declared cargo insurance prior to booking dispatch.
                </p>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-orange-100 text-brandOrange flex items-center justify-center text-xs font-bold">6</span>
                    Governing Law &amp; Jurisdiction
                </h2>
                <p>
                    These terms are governed by and construed in accordance with the laws of the Republic of the Philippines. Any legal disputes arising from these conditions shall be submitted to the exclusive jurisdiction of the proper courts in the Philippines.
                </p>
            </section>

            <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-slate-500">
                <span>Questions regarding terms? Contact: <strong>legal@yocorexpress.com</strong></span>
                <a href="contact.php" class="text-brandOrange font-bold hover:underline">Contact Customer Support &rarr;</a>
            </div>

        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
