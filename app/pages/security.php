<?php
$pageTitle = "Security & Compliance | YOCOR Express";
$activeNav = "security";
include __DIR__ . '/../includes/header.php';
?>

<main class="flex-grow py-10 sm:py-14 bg-slate-50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
        
        <!-- Breadcrumb & Header -->
        <div class="mb-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brandNavy/5 border border-brandNavy/10 text-brandNavy font-bold text-xs uppercase tracking-wider mb-2">
                <i class="fa-solid fa-shield-halved text-brandOrange"></i> Privacy, Data &amp; Regulatory Standards
            </div>
            <h1 class="text-2xl sm:text-3xl font-black text-brandNavy font-heading">
                Security &amp; Compliance
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">
                Last Updated: <?= date('F d, Y') ?> &bull; Committed to Philippine regulatory standards &amp; Data Privacy Act (DPA 2012).
            </p>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 sm:p-8 text-xs sm:text-sm text-slate-700 space-y-6 leading-relaxed">
            
            <section>
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">1</span>
                    Data Privacy Act of 2012 (Republic Act No. 10173)
                </h2>
                <p class="mb-2">
                    YOCOR Express strictly upholds the rights of data subjects. When you register an account or book a shipment, we only collect information essential for fulfilling delivery logistics:
                </p>
                <ul class="list-disc list-inside space-y-1.5 ml-2 text-slate-600">
                    <li><strong>Customer Identity &amp; Contact:</strong> Full name, telephone number, and email address for dispatch alerts and two-way verification.</li>
                    <li><strong>Geographical Routing:</strong> Detailed street addresses, landmarks, and island regions for optimal vehicle routing.</li>
                    <li><strong>Account Security:</strong> Encrypted password hashes using cryptographic standard <code>PASSWORD_DEFAULT</code> (bcrypt). Plaintext passwords are never stored.</li>
                </ul>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">2</span>
                    Physical &amp; Cargo Hub Security
                </h2>
                <p class="mb-2">
                    To prevent package tampering, loss, or theft during intra-island and cross-island transit:
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="flex items-center gap-2 font-bold text-brandNavy text-xs mb-1">
                            <i class="fa-solid fa-video text-brandOrange"></i> 24/7 CCTV Hub Monitoring
                        </div>
                        <p class="text-[11px] text-slate-500">Every sorting facility and warehouse distribution hub is monitored under 24-hour recorded digital surveillance.</p>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80">
                        <div class="flex items-center gap-2 font-bold text-brandNavy text-xs mb-1">
                            <i class="fa-solid fa-qrcode text-brandOrange"></i> Unique Waybill Verification
                        </div>
                        <p class="text-[11px] text-slate-500">Parcels are tracked through unique cryptographic identifiers (e.g. <code>YR-XXXXXX</code>) to prevent unauthorized interception.</p>
                    </div>
                </div>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">3</span>
                    Database &amp; Transaction Integrity
                </h2>
                <p>
                    Our backend infrastructure enforces prepared PDO statements across all SQL queries to eliminate SQL injection vulnerabilities. Financial and status updates utilize atomic ACID transactions with row-level locking (<code>FOR UPDATE</code>) to avoid race conditions when slots and fleets are booked.
                </p>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">4</span>
                    Compliance with Philippine Regulatory Agencies
                </h2>
                <p class="mb-2">
                    YOCOR Express operates under compliance with relevant regulatory standards across the Philippines:
                </p>
                <ul class="list-disc list-inside space-y-1.5 ml-2 text-slate-600">
                    <li><strong>National Privacy Commission (NPC):</strong> Adherence to lawful data processing, consent, and data retention standards.</li>
                    <li><strong>Department of Trade and Industry (DTI):</strong> Transparent freight pricing, fair business practices, and consumer protection.</li>
                    <li><strong>Civil Aviation Authority of the Philippines (CAAP):</strong> Stringent cargo screening and dangerous goods restrictions on all inter-island air transport.</li>
                </ul>
            </section>

            <section class="pt-4 border-t border-slate-100">
                <h2 class="text-sm sm:text-base font-bold text-brandNavy flex items-center gap-2 mb-2">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold">5</span>
                    Data Protection Officer (DPO) Contact
                </h2>
                <p>
                    If you have inquiries about how your personal data is handled, or wish to exercise your rights to data access, modification, or erasure under the DPA, please contact our Data Protection Officer:
                </p>
                <div class="mt-2.5 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono text-slate-600">
                    <strong>Email:</strong> dpo@yocorexpress.com<br>
                    <strong>Hotline:</strong> +63 (02) 8555-YOCR (DPO Inquiries)<br>
                    <strong>Office:</strong> YOCOR Central Dispatch Hub, Metro Manila, Philippines
                </div>
            </section>

            <div class="pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-slate-500">
                <span>View our customer agreement: <a href="terms.php" class="text-brandNavy font-bold hover:underline">Terms of Service</a></span>
                <a href="contact.php" class="text-brandOrange font-bold hover:underline">Speak with Compliance Team &rarr;</a>
            </div>

        </div>

    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
