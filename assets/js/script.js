// YOCOR Express Logistics - Interactive Client Script
document.addEventListener('DOMContentLoaded', () => {
  
  // Mobile navigation drawer toggle
  const mobileMenuBtn = document.getElementById('mobile-menu-btn');
  const mobileMenu = document.getElementById('mobile-menu');
  if (mobileMenuBtn && mobileMenu) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileMenu.classList.toggle('hidden');
    });
  }

  // Dynamic Rate Calculation formula matching booking.php exactly
  const calcWeight = document.getElementById('calc-weight');
  const calcServiceId = document.getElementById('calc-service-id');
  const calcOriginRegion = document.getElementById('calc-origin-region');
  const calcDestRegion = document.getElementById('calc-destination-region');
  const calcResultPrice = document.getElementById('calc-result-price');

  function getRouteFee(origin, dest) {
    const rates = window.regionalRates || { intra_island: 0, inter_island: 60, cross_island: 120 };
    const o = (origin || '').toLowerCase();
    const d = (dest || '').toLowerCase();
    if (!o || !d || o === d) {
      return rates.intra_island ?? 0;
    }
    if ((o === 'luzon' && d === 'mindanao') || (o === 'mindanao' && d === 'luzon')) {
      return rates.cross_island ?? 120;
    }
    return rates.inter_island ?? 60;
  }

  function calculateRate() {
    if (!calcWeight || !calcServiceId || !calcResultPrice) return;
    const weight = parseFloat(calcWeight.value) || 1.0;
    const serviceId = calcServiceId.value ? parseInt(calcServiceId.value, 10) : 0;
    const originRegion = calcOriginRegion ? calcOriginRegion.value : 'Luzon';
    const destRegion = calcDestRegion ? calcDestRegion.value : 'Luzon';
    
    // Dynamic service pricing from DB window.serviceRates
    const sRates = window.serviceRates || {};
    const selectedService = sRates[serviceId];

    if (!selectedService) {
      calcResultPrice.innerHTML = `₱0.00 <span class="text-xs font-normal text-slate-500">PHP</span>`;
      return;
    }

    const baseRate = parseFloat(selectedService.base) || 100.00;
    const multiplier = parseFloat(selectedService.perKg) || 40.00; // per kg for extra weight
    const regionalDistanceFee = getRouteFee(originRegion, destRegion);
    
    // Formula matches booking.php: base + (max(0, weight - 1) * perKg) + distanceFee
    const calculatedTotal = baseRate + (Math.max(0, weight - 1) * multiplier) + regionalDistanceFee;
    calcResultPrice.innerHTML = `₱${calculatedTotal.toFixed(2)} <span class="text-xs font-normal text-slate-500">PHP</span>`;
  }

  if (calcWeight && calcServiceId) {
    calcWeight.addEventListener('input', calculateRate);
    calcServiceId.addEventListener('change', calculateRate);
    calculateRate(); // Initial calculation on load
  }
  if (calcOriginRegion) calcOriginRegion.addEventListener('change', calculateRate);
  if (calcDestRegion) calcDestRegion.addEventListener('change', calculateRate);

  // Rate calculator parcel form submit
  const parcelForm = document.getElementById('rate-form-parcel');
  if (parcelForm) {
    parcelForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const weightVal = calcWeight ? calcWeight.value : '1';
      const serviceIdVal = calcServiceId ? calcServiceId.value : '';
      const oReg = calcOriginRegion ? calcOriginRegion.value : 'Luzon';
      const dReg = calcDestRegion ? calcDestRegion.value : 'Luzon';
      const oAddr = document.getElementById('calc-origin') ? document.getElementById('calc-origin').value : '';
      const dAddr = document.getElementById('calc-destination') ? document.getElementById('calc-destination').value : '';
      
      const bookingUrl = `booking.php?weight=${encodeURIComponent(weightVal)}&service_id=${encodeURIComponent(serviceIdVal)}&origin_region=${encodeURIComponent(oReg)}&dest_region=${encodeURIComponent(dReg)}&pickup=${encodeURIComponent(oAddr)}&delivery=${encodeURIComponent(dAddr)}`;

      if (window.isLoggedIn) {
        window.location.href = bookingUrl;
      } else {
        window.location.href = `login.php?redirect=${encodeURIComponent(bookingUrl)}`;
      }
    });
  }

  // Dedicated Tracking Page Form Handling
  const trackingPageForm = document.getElementById('tracking-page-form');
  if (trackingPageForm) {
    trackingPageForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = document.getElementById('tracking-page-input');
      const val = input ? input.value.trim() : '';
      if (!val) {
        alert('Please enter a valid tracking waybill number.');
        return;
      }
      
      const resultCard = document.getElementById('tracking-result-card');
      const idElem = document.getElementById('result-tracking-id');
      if (resultCard && idElem) {
        idElem.textContent = val.toUpperCase();
        resultCard.classList.remove('hidden');
        resultCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  }

  // Booking Form Submission Handling
  const bookingForm = document.getElementById('booking-form');
  if (bookingForm) {
    bookingForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const randomID = 'YCR-' + Math.floor(1000000 + Math.random() * 9000000);
      alert(`Booking Confirmed!\n\nYour assigned Tracking Waybill is: ${randomID}\nOur courier will arrive at your pickup address within the selected time window.`);
      window.location.href = `tracking.php?tracking_no=${randomID}`;
    });
  }

  // Contact Form Submission Handling
  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      alert('Thank you! Your message has been sent to our 24/7 customer support center. Reference ticket created.');
      contactForm.reset();
    });
  }
});
