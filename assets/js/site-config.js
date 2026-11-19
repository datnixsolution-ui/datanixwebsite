/**
 * DATNIX SOLUTIONS - Central Website Configuration
 * Tagline: DATA. PROCESS. PERFORMANCE.
 * 
 * Edit contact information and company details here.
 * Any update in this configuration file automatically updates
 * all phone links, email links, WhatsApp links, and brand text across the entire website.
 */

const SITE_CONFIG = {
  company: {
    name: "DATNIX SOLUTIONS",
    tagline: "DATA. PROCESS. PERFORMANCE.",
    description: "Enterprise-grade B2B outsourcing partner delivering precision data management, standardized process execution, and dependable operational performance.",
    logoPath: "assets/images/datnix-logo.png",
    logoAlt: "DATNIX SOLUTIONS",
  },

  contact: {
    mobile: "7387010393",
    mobileDisplay: "+91 73870 10393",
    mobileTel: "tel:+917387010393",
    
    email: "datnixsolution@gmail.com",
    emailMailto: "mailto:datnixsolution@gmail.com",
    
    whatsapp: "+91 7387010393",
    whatsappDisplay: "+91 73870 10393",
    whatsappLink: "https://wa.me/917387010393",
    whatsappPreFilledMessage: "Hello DATNIX SOLUTIONS, I am interested in your data entry and BPO services. I would like to discuss a business requirement.",
    whatsappFloatingLink: "https://wa.me/917387010393?text=Hello%20DATNIX%20SOLUTIONS%2C%20I%20am%20interested%20in%20your%20data%20entry%20and%20BPO%20services.%20I%20would%20like%20to%20discuss%20a%20business%20requirement.",

    responseCommitment: "Prompt inquiries response within 2-4 business hours",
    formspreeEndpoint: "api/send-enquiry.php",
    backendEndpoint: "api/send-enquiry.php",
  },

  formspree: {
    endpoint: "api/send-enquiry.php",
    recipientEmail: "datnixsolution@gmail.com",
    subjectPrefix: "New DATNIX SOLUTIONS Website Enquiry"
  },

  address: {
    line1: "20, Satkar Complex",
    street: "Chimanlal Girdharlal Rd (C.G. Road)",
    area: "Ellisbridge",
    city: "Ahmedabad",
    state: "Gujarat",
    pincode: "380006",
    country: "India",
    full: "20, Satkar Complex, Chimanlal Girdharlal Rd, Ellisbridge, Ahmedabad, Gujarat - 380006, India",
    display: "20, Satkar Complex, Chimanlal Girdharlal Rd, Ahmedabad - 380006, Gujarat",
    short: "Ahmedabad, Gujarat - 380006"
  },

  credentials: {
    rank: "Ranked Top 13th BPO & Data Solutions Company of India",
    rankShort: "Top 13th Company of India",
    marketTrust: "9+ Years of Market Trust & Excellence",
    projectsDelivered: "543+ Client Projects Delivered",
    accuracySla: "99.8% Data Accuracy SLA",
    experienceYears: "9 Years in Industry",
    projectCount: "543+"
  },

  brandColors: {
    navy: "#092D78",
    orange: "#FF7900",
    white: "#FFFFFF",
    lightGrey: "#F4F6FA",
    slateDark: "#0F172A",
  },

  navLinks: [
    { name: "Home", href: "index.html", id: "home" },
    { name: "About Us", href: "about.html", id: "about" },
    { name: "Services", href: "services.html", id: "services" },
    { name: "Industries", href: "industries.html", id: "industries" },
    { name: "Our Process", href: "process.html", id: "process" },
    { name: "Why Choose Us", href: "why-choose-us.html", id: "why-choose-us" },
    { name: "Careers", href: "careers.html", id: "careers" },
    { name: "Contact Us", href: "contact.html", id: "contact" }
  ]
};

/**
 * Automatically applies configuration data to the DOM elements.
 * Supports:
 * - data-config="phone" -> updates innerText
 * - data-config="phone-link" -> updates href to tel:...
 * - data-config="email" -> updates innerText
 * - data-config="email-link" -> updates href to mailto:...
 * - data-config="whatsapp" -> updates innerText
 * - data-config="whatsapp-link" -> updates href to wa.me...
 * - data-config="tagline" -> updates innerText
 * - data-config="company-name" -> updates innerText
 * - data-config="logo" -> updates src and alt
 * - data-config="year" -> updates to current year
 */
function applySiteConfig() {
  // Update Phone texts
  document.querySelectorAll('[data-config="phone"]').forEach(el => {
    el.textContent = SITE_CONFIG.contact.mobileDisplay;
  });

  // Update Phone Links
  document.querySelectorAll('[data-config="phone-link"]').forEach(el => {
    el.setAttribute('href', SITE_CONFIG.contact.mobileTel);
    if (!el.textContent.trim() || el.hasAttribute('data-config-fill-text')) {
      el.textContent = SITE_CONFIG.contact.mobileDisplay;
    }
  });

  // Update Email texts
  document.querySelectorAll('[data-config="email"]').forEach(el => {
    el.textContent = SITE_CONFIG.contact.email;
  });

  // Update Email Links
  document.querySelectorAll('[data-config="email-link"]').forEach(el => {
    el.setAttribute('href', SITE_CONFIG.contact.emailMailto);
    if (!el.textContent.trim() || el.hasAttribute('data-config-fill-text')) {
      el.textContent = SITE_CONFIG.contact.email;
    }
  });

  // Update WhatsApp texts
  document.querySelectorAll('[data-config="whatsapp"]').forEach(el => {
    el.textContent = SITE_CONFIG.contact.whatsappDisplay;
  });

  // Update WhatsApp Links
  document.querySelectorAll('[data-config="whatsapp-link"]').forEach(el => {
    el.setAttribute('href', SITE_CONFIG.contact.whatsappLink);
    el.setAttribute('target', '_blank');
    el.setAttribute('rel', 'noopener noreferrer');
  });

  // Update Floating WhatsApp Links with Pre-filled Business Message
  document.querySelectorAll('.floating-whatsapp, [data-config="whatsapp-floating-link"]').forEach(el => {
    el.setAttribute('href', SITE_CONFIG.contact.whatsappFloatingLink);
    el.setAttribute('target', '_blank');
    el.setAttribute('rel', 'noopener noreferrer');
  });

  // Update Tagline
  document.querySelectorAll('[data-config="tagline"]').forEach(el => {
    el.textContent = SITE_CONFIG.company.tagline;
  });

  // Update Company Name
  document.querySelectorAll('[data-config="company-name"]').forEach(el => {
    el.textContent = SITE_CONFIG.company.name;
  });

  // Update Logo
  document.querySelectorAll('[data-config="logo"]').forEach(el => {
    const currentSrc = el.getAttribute('src') || '';
    const isNested = currentSrc.startsWith('../');
    const logoSrc = isNested ? `../${SITE_CONFIG.company.logoPath}` : SITE_CONFIG.company.logoPath;
    el.setAttribute('src', logoSrc);
    el.setAttribute('alt', SITE_CONFIG.company.logoAlt);
  });

  // Update Response Commitment
  document.querySelectorAll('[data-config="response-commitment"]').forEach(el => {
    el.textContent = SITE_CONFIG.contact.responseCommitment;
  });

  // Update Address and Location
  document.querySelectorAll('[data-config="address"]').forEach(el => {
    el.textContent = SITE_CONFIG.address.display;
  });
  document.querySelectorAll('[data-config="address-full"]').forEach(el => {
    el.textContent = SITE_CONFIG.address.full;
  });
  document.querySelectorAll('[data-config="city"]').forEach(el => {
    el.textContent = `${SITE_CONFIG.address.city}, ${SITE_CONFIG.address.state} - ${SITE_CONFIG.address.pincode}`;
  });
  document.querySelectorAll('[data-config="pincode"]').forEach(el => {
    el.textContent = SITE_CONFIG.address.pincode;
  });

  // Update Trust & Ranking Proof Points
  document.querySelectorAll('[data-config="rank"]').forEach(el => {
    el.textContent = SITE_CONFIG.credentials.rank;
  });
  document.querySelectorAll('[data-config="market-trust"]').forEach(el => {
    el.textContent = SITE_CONFIG.credentials.marketTrust;
  });
  document.querySelectorAll('[data-config="projects-delivered"]').forEach(el => {
    el.textContent = SITE_CONFIG.credentials.projectsDelivered;
  });

  // Update Current Year
  document.querySelectorAll('[data-config="year"]').forEach(el => {
    el.textContent = new Date().getFullYear();
  });

  // Ensure enquiry forms post to hardened PHP handler
  document.querySelectorAll('form#consultationForm, form#businessEnquiryForm').forEach(form => {
    form.setAttribute('action', 'api/send-enquiry.php');
    form.setAttribute('method', 'POST');
  });
}

// Run on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', applySiteConfig);
} else {
  applySiteConfig();
}
