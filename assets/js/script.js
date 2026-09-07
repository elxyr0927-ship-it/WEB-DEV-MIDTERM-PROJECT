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

  // Rate calculator tabs switching
  const tabButtons = document.querySelectorAll('.tab-button');
  tabButtons.forEach((btn) => {
    btn.addEventListener('click', function () {
      const tab = this.dataset.tab;
      
      tabButtons.forEach((b) => {
        b.classList.remove('active', 'bg-white', 'text-brandNavy', 'shadow-sm', 'border', 'border-slate-200');
        b.classList.add('hover:text-brandNavy', 'text-slate-600');
      });
      this.classList.add('active', 'bg-white', 'text-brandNavy', 'shadow-sm', 'border', 'border-slate-200');
      this.classList.remove('hover:text-brandNavy');

      document.querySelectorAll('.tab-content').forEach((el) => {
        el.classList.add('hidden');
      });
      const targetContent = document.getElementById('tab-' + tab);
      if (targetContent) {
        targetContent.classList.remove('hidden');
      }
    });
  });

  // Dynamic Rate Calculation formula
  const calcWeight = document.getElementById('calc-weight');
  const calcTier = document.getElementById('calc-tier');
  const calcResultPrice = document.getElementById('calc-result-price');

  function calculateRate() {
    if (!calcWeight || !calcTier || !calcResultPrice) return;
    const weight = parseFloat(calcWeight.value) || 1.0;
    const tier = calcTier.value;
    
    let baseRate = 100;
    let multiplier = 50; // per kg

    if (tier === 'sameday') {
      baseRate = 220;
      multiplier = 80;
    } else if (tier === 'priority') {
      baseRate = 150;
      multiplier = 60;
    } else {
      baseRate = 100;
      multiplier = 40;
    }

    const calculatedTotal = baseRate + (Math.max(0, weight - 1) * multiplier);
    calcResultPrice.innerHTML = `₱${calculatedTotal.toFixed(2)} <span class="text-xs font-normal text-slate-500">PHP</span>`;
  }

  if (calcWeight && calcTier) {
    calcWeight.addEventListener('input', calculateRate);
    calcTier.addEventListener('change', calculateRate);
  }

  // Rate calculator parcel form submit
  const parcelForm = document.getElementById('rate-form-parcel');
  if (parcelForm) {
    parcelForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const weightVal = calcWeight ? calcWeight.value : '1';
      window.location.href = `booking.php?weight=${encodeURIComponent(weightVal)}&service=parcel`;
    });
  }

  // Rate calculator freight form submit
  const freightForm = document.getElementById('rate-form-freight');
  if (freightForm) {
    freightForm.addEventListener('submit', (e) => {
      e.preventDefault();
      alert('Your Commercial Freight inquiry has been logged. Our freight operations team will contact you within 15 minutes.');
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
