/**
 * DATNIX SOLUTIONS - Core JavaScript
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Drawer Navigation
  const mobileToggle = document.getElementById('mobileNavToggle');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const mobileClose = document.getElementById('mobileDrawerClose');
  const drawerBackdrop = document.getElementById('drawerBackdrop');

  function openDrawer() {
    if (mobileDrawer) mobileDrawer.classList.add('open');
    if (drawerBackdrop) drawerBackdrop.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeDrawer() {
    if (mobileDrawer) mobileDrawer.classList.remove('open');
    if (drawerBackdrop) drawerBackdrop.classList.remove('active');
    document.body.style.overflow = '';
  }

  if (mobileToggle) mobileToggle.addEventListener('click', openDrawer);
  if (mobileClose) mobileClose.addEventListener('click', closeDrawer);
  if (drawerBackdrop) drawerBackdrop.addEventListener('click', closeDrawer);

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && mobileDrawer && mobileDrawer.classList.contains('open')) {
      closeDrawer();
    }
  });

  // 2. Active Page Navigation Highlight
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const navLinks = document.querySelectorAll('.nav-link, .mobile-drawer-nav a');

  navLinks.forEach(link => {
    const linkHref = link.getAttribute('href');
    if (linkHref === currentPath || (currentPath === '' && linkHref === 'index.html')) {
      link.classList.add('active');
    }
  });

  // 3. Scroll to Top Button
  const scrollTopBtn = document.getElementById('scrollTopBtn');
  if (scrollTopBtn) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 400) {
        scrollTopBtn.classList.add('visible');
      } else {
        scrollTopBtn.classList.remove('visible');
      }
    });

    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
  }

  // 4. Contact Form Handler (if present)
  const contactForm = document.getElementById('consultationForm') || document.getElementById('businessEnquiryForm');
  const formStatus = document.getElementById('formStatus');

  // Pre-fill Service from URL query parameter (e.g., contact.html?service=Data+Entry#quote)
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const serviceQuery = urlParams.get('service');
    if (serviceQuery) {
      const serviceSelect = document.getElementById('serviceInterest');
      if (serviceSelect) {
        const cleanQuery = decodeURIComponent(serviceQuery).toLowerCase().replace(/[^a-z0-9]/g, '');
        let matched = false;
        for (let i = 0; i < serviceSelect.options.length; i++) {
          const optText = serviceSelect.options[i].text.toLowerCase().replace(/[^a-z0-9]/g, '');
          const optVal = serviceSelect.options[i].value.toLowerCase().replace(/[^a-z0-9]/g, '');
          if (optText.includes(cleanQuery) || cleanQuery.includes(optText) || optVal.includes(cleanQuery) || cleanQuery.includes(optVal)) {
            serviceSelect.selectedIndex = i;
            matched = true;
            break;
          }
        }
        // Fallback for BPO / Process queries to prefill 'Other' if specific option not present
        if (!matched && (cleanQuery.includes('bpo') || cleanQuery.includes('process'))) {
          for (let i = 0; i < serviceSelect.options.length; i++) {
            if (serviceSelect.options[i].value === 'Other' || serviceSelect.options[i].text === 'Other') {
              serviceSelect.selectedIndex = i;
              break;
            }
          }
        }
      }

      // Smooth scroll to the form if requested via parameter or hash
      setTimeout(() => {
        const quoteAnchor = document.getElementById('quote') || contactForm;
        if (quoteAnchor) {
          quoteAnchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 250);
    }
  } catch (err) {
    console.error('Service pre-fill error:', err);
  }

  if (contactForm) {
    // Clear field-level invalid states on input
    const inputs = contactForm.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
      input.addEventListener('input', () => {
        input.classList.remove('is-invalid');
        const errEl = document.getElementById(`${input.id}Error`);
        if (errEl) errEl.classList.remove('visible');
        if (formStatus && formStatus.classList.contains('error')) {
          formStatus.style.display = 'none';
        }
      });
    });

    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const nameInput = document.getElementById('fullName');
      const companyInput = document.getElementById('companyName');
      const emailInput = document.getElementById('workEmail');
      const phoneInput = document.getElementById('phoneNum');
      const serviceSelect = document.getElementById('serviceInterest');
      const volumeInput = document.getElementById('estimatedVolume');
      const detailsInput = document.getElementById('projectDetails');

      const name = nameInput ? nameInput.value.trim() : '';
      const company = companyInput ? companyInput.value.trim() : '';
      const email = emailInput ? emailInput.value.trim() : '';
      const phone = phoneInput ? phoneInput.value.trim() : '';
      const service = serviceSelect ? serviceSelect.value : 'General Outsourcing';
      const volume = volumeInput ? volumeInput.value.trim() : '';
      const message = detailsInput ? detailsInput.value.trim() : '';

      // Reset error displays
      inputs.forEach(inp => inp.classList.remove('is-invalid'));
      document.querySelectorAll('.field-error-text').forEach(err => err.classList.remove('visible'));

      // Validate required fields and formats
      let hasError = false;
      let firstErrorInput = null;

      function setError(inputEl, errorId, errorMsg) {
        hasError = true;
        if (inputEl) inputEl.classList.add('is-invalid');
        const errSpan = document.getElementById(errorId);
        if (errSpan) {
          errSpan.textContent = errorMsg;
          errSpan.classList.add('visible');
        }
        if (!firstErrorInput) firstErrorInput = inputEl;
      }

      if (!name || name.length < 2) {
        setError(nameInput, 'fullNameError', 'Please enter your full name (minimum 2 characters).');
      }

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!email || !emailRegex.test(email)) {
        setError(emailInput, 'workEmailError', 'Please enter a valid business email address (e.g. name@company.com).');
      }

      if (phone) {
        const cleanDigits = phone.replace(/[^0-9]/g, '');
        if (cleanDigits.length < 10) {
          setError(phoneInput, 'phoneNumError', 'Please enter a valid contact number (at least 10 digits).');
        }
      }

      if (!message || message.length < 10) {
        setError(detailsInput, 'projectDetailsError', 'Please describe your project scope or data tasks (minimum 10 characters).');
      }

      if (hasError) {
        if (formStatus) {
          formStatus.className = 'form-status error';
          formStatus.innerHTML = `<strong>Please correct the highlighted fields:</strong> Complete all required fields marked with an asterisk (*) and ensure proper email and phone formatting.`;
          formStatus.style.display = 'block';
        }
        if (firstErrorInput) firstErrorInput.focus();
        return;
      }

      const submitBtn = contactForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn ? submitBtn.innerHTML : 'Submit Enquiry';

      // Set Loading State
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
          <svg style="animation: spin 1s linear infinite; width: 18px; height: 18px; display: inline-block; vertical-align: middle; margin-right: 8px;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
            <path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"></path>
          </svg>
          Processing Enquiry...
        `;
      }
      if (formStatus) {
        formStatus.className = 'form-status loading';
        formStatus.textContent = 'Validating and preparing your enquiry...';
        formStatus.style.display = 'block';
      }

      // Capture honeypot spam trap
      const honeypotInput = document.getElementById('formHoneypot') || 
                            contactForm.querySelector('input[name="honeypot"]') || 
                            contactForm.querySelector('input[name="_gotcha"]');
      const honeypot = honeypotInput ? honeypotInput.value.trim() : '';

      // Prepare standardized enquiry payload
      const formspreeSubject = `New DATNIX SOLUTIONS Website Enquiry: ${service} - ${name}`;
      
      // Sync hidden form helper inputs
      const subjectInput = contactForm.querySelector('input[name="_subject"]');
      if (subjectInput) subjectInput.value = formspreeSubject;
      const replyToInput = contactForm.querySelector('input[name="_replyto"]');
      if (replyToInput) replyToInput.value = email;

      const enquiryPayload = {
        fullName: name,
        companyName: company || "Not specified",
        email: email,
        phone: phone || "Not provided",
        service: service,
        estimatedVolume: volume || "Flexible / Not specified",
        projectDetails: message,
        honeypot: honeypot,
        // Routing & backwards compatibility helpers
        serviceRequired: service,
        _gotcha: honeypot,
        _replyto: email,
        _subject: formspreeSubject,
        // Human-readable labels for Formspree table notifications
        "Full Name": name,
        "Company Name": company || "Not specified",
        "Email Address": email,
        "Mobile Number": phone || "Not provided",
        "Service Required": service,
        "Estimated Project Volume": volume || "Flexible / Not specified",
        "Project Details": message
      };

      // Direct communication channels for backup / direct transmission
      const whatsappText = encodeURIComponent(
        `*New Business Enquiry - DATNIX SOLUTIONS*\n\n` +
        `*Name:* ${name}\n` +
        `*Company:* ${company || 'N/A'}\n` +
        `*Email:* ${email}\n` +
        `*Phone:* ${phone || 'N/A'}\n` +
        `*Service:* ${service}\n` +
        `*Volume:* ${volume || 'N/A'}\n` +
        `*Project Scope:*\n${message}`
      );
      const waUrl = `https://wa.me/917387010393?text=${whatsappText}`;

      const mailSubject = encodeURIComponent(`Project Enquiry: ${service} - ${name}`);
      const mailBody = encodeURIComponent(
        `Hello DATNIX SOLUTIONS Team,\n\n` +
        `I would like to request a quote / consultation for your outsourcing services:\n\n` +
        `Full Name: ${name}\n` +
        `Company: ${company || 'N/A'}\n` +
        `Email: ${email}\n` +
        `Phone: ${phone || 'N/A'}\n` +
        `Service Required: ${service}\n` +
        `Estimated Volume: ${volume || 'N/A'}\n\n` +
        `Project Scope & Details:\n${message}\n\n` +
        `Regards,\n${name}`
      );
      const mailUrl = `mailto:datnixsolutions@gmail.com?subject=${mailSubject}&body=${mailBody}`;

      // Primary backend: hardened PHP + MySQL endpoint (api/send-enquiry.php)
      // Fallback backend: Formspree (for static serverless deploys)
      const phpEndpoint = 'api/send-enquiry.php';
      const formspreeEndpoint = (typeof SITE_CONFIG !== 'undefined' && SITE_CONFIG.formspree && SITE_CONFIG.formspree.endpoint)
        ? SITE_CONFIG.formspree.endpoint
        : (contactForm.getAttribute('action') || (SITE_CONFIG && SITE_CONFIG.contact && SITE_CONFIG.contact.formspreeEndpoint) || '');

      let submittedSuccessfully = false;

      // Attempt 1: Submit to hardened PHP/MySQL API
      try {
        const phpResp = await fetch(phpEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(enquiryPayload)
        });

        // If PHP endpoint exists and processes request
        if (phpResp.status !== 404 && phpResp.status !== 405) {
          const phpResult = await phpResp.json().catch(() => null);

          if (phpResp.ok && phpResult && phpResult.success) {
            contactForm.reset();
            submittedSuccessfully = true;
            if (formStatus) {
              formStatus.className = 'form-status success';
              formStatus.innerHTML = `
                <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.35rem; color: #065F46; display: flex; align-items: center; gap: 8px;">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  <span>Enquiry Logged Successfully</span>
                </div>
                <p style="margin: 0 0 0.5rem; font-size: 0.95rem; color: #166534; line-height: 1.5;">
                  Thank you for contacting DATNIX SOLUTIONS! Your enquiry has been recorded in our system under Reference ID:
                  <strong style="color: #065F46; font-family: monospace; font-size: 1.05rem;">${escapeHTML(phpResult.referenceNo || 'LOGGED')}</strong>.
                </p>
                <p style="margin: 0; font-size: 0.88rem; color: #15803D;">
                  Our operations team will review your specifications and contact you shortly. Please quote your reference number in any follow-up correspondence.
                </p>
              `;
            }
            return;
          } else if (phpResp.status === 429) {
            const waitNotice = phpResult && phpResult.error
              ? phpResult.error
              : 'Too many submissions received recently. Please wait a moment before trying again.';
            if (formStatus) {
              formStatus.className = 'form-status error';
              formStatus.innerHTML = `
                <div style="font-weight: 700; margin-bottom: 0.35rem; color: #991B1B;">
                  Submission Frequency Limit
                </div>
                <p style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #B91C1C; line-height: 1.5;">
                  ${escapeHTML(waitNotice)} Alternatively, reach our operations team directly:
                </p>
                <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
                  <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span>Chat on WhatsApp (+91 73870 10393)</span>
                  </a>
                  <a href="${mailUrl}" class="btn btn-navy btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span>Email Directly (datnixsolutions@gmail.com)</span>
                  </a>
                </div>
              `;
            }
            return;
          } else if (phpResult && phpResult.error) {
            // Validation error or server message
            throw new Error(phpResult.error);
          }
        }
      } catch (phpErr) {
        // If it was a 429 or explicit validation error, don't fallback to Formspree
        if (phpErr.message && !phpErr.message.includes('fetch') && !phpErr.message.includes('NetworkError') && !phpErr.message.includes('Failed to fetch')) {
          if (formStatus) {
            formStatus.className = 'form-status error';
            formStatus.innerHTML = `
              <div style="font-weight: 700; margin-bottom: 0.35rem; color: #991B1B;">Notice</div>
              <p style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #B91C1C; line-height: 1.5;">${escapeHTML(phpErr.message)}</p>
            `;
          }
          return;
        }
        console.warn('PHP API unavailable, falling back to secondary transport...', phpErr);
      }

      // Attempt 2: Fallback to Formspree if configured
      const isPlaceholder = !formspreeEndpoint ||
        formspreeEndpoint.includes('YOUR_FORM_ID') ||
        formspreeEndpoint.includes('PASTE') ||
        formspreeEndpoint.includes('[') ||
        formspreeEndpoint === '#';

      if (isPlaceholder) {
        // Honest handling: do not pretend an email was delivered without a real endpoint.
        setTimeout(() => {
          if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
          }

          if (formStatus) {
            formStatus.className = 'form-status error';
            formStatus.innerHTML = `
              <div style="font-weight: 700; margin-bottom: 0.35rem; color: #991B1B;">
                Direct Contact Channels
              </div>
              <p style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #B91C1C; line-height: 1.5;">
                Unable to reach the automated enquiry server. You can transmit your enquiry directly to our team:
              </p>
              <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                  <span>Chat on WhatsApp (+91 73870 10393)</span>
                </a>
                <a href="${mailUrl}" class="btn btn-navy btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                  <span>Email Directly (datnixsolutions@gmail.com)</span>
                </a>
              </div>
            `;
          }
        }, 300);
        return;
      }

      // Live Formspree submission path
      try {
        const resp = await fetch(formspreeEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(enquiryPayload)
        });

        const result = await resp.json().catch(() => null);

        if (resp.ok && (resp.status >= 200 && resp.status < 300)) {
          contactForm.reset();
          if (formStatus) {
            formStatus.className = 'form-status success';
            formStatus.innerHTML = `
              <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.35rem; color: #065F46; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>Enquiry Submitted Successfully</span>
              </div>
              <p style="margin: 0; font-size: 0.95rem; color: #166534; line-height: 1.5;">
                Thank you for contacting DATNIX SOLUTIONS! Your enquiry has been submitted successfully. Our team will get back to you soon.
              </p>
            `;
          }
        } else if (resp.status === 429) {
          const waitNotice = result && result.retryAfter
            ? `Please wait ${result.retryAfter} seconds before trying again.`
            : (result && result.error ? result.error : 'Too many enquiries submitted recently. Please wait a moment before trying again.');
          if (formStatus) {
            formStatus.className = 'form-status error';
            formStatus.innerHTML = `
              <div style="font-weight: 700; margin-bottom: 0.35rem; color: #991B1B;">
                Submission Limit Notice
              </div>
              <p style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #B91C1C; line-height: 1.5;">
                ${escapeHTML(waitNotice)} Alternatively, reach our team directly:
              </p>
              <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
                <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                  <span>Chat on WhatsApp (+91 73870 10393)</span>
                </a>
                <a href="${mailUrl}" class="btn btn-navy btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                  <span>Email Directly (datnixsolutions@gmail.com)</span>
                </a>
              </div>
            `;
          }
        } else {
          const errorMsg = result && result.errors && result.errors.length
            ? result.errors.map(e => e.message).join(', ')
            : (result && result.error ? result.error : `Server responded with status ${resp.status}`);
          throw new Error(errorMsg);
        }
      } catch (fetchErr) {
        console.error('Formspree submission error:', fetchErr);
        // Preserve entered information on error
        if (formStatus) {
          formStatus.className = 'form-status error';
          formStatus.innerHTML = `
            <div style="font-weight: 700; margin-bottom: 0.35rem; color: #991B1B;">
              Notice
            </div>
            <p style="margin-bottom: 0.75rem; font-size: 0.95rem; color: #B91C1C; line-height: 1.5;">
              Unable to submit your enquiry at the moment. Please try again or contact us directly.
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 0.5rem;">
              <a href="${waUrl}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>Chat on WhatsApp (+91 73870 10393)</span>
              </a>
              <a href="${mailUrl}" class="btn btn-navy btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                <span>Email Directly (datnixsolutions@gmail.com)</span>
              </a>
            </div>
          `;
        }
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalBtnText;
        }
      }
    });
  }

  function escapeHTML(str) {
    return str.replace(/[&<>'"]/g, 
      tag => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
      }[tag] || tag)
    );
  }

  // 5. Sticky Header Elevation on Scroll
  const siteHeader = document.querySelector('.site-header');
  if (siteHeader) {
    let ticking = false;
    const handleScroll = () => {
      if (!ticking) {
        window.requestAnimationFrame(() => {
          if (window.scrollY > 24) {
            siteHeader.classList.add('is-scrolled');
          } else {
            siteHeader.classList.remove('is-scrolled');
          }
          ticking = false;
        });
        ticking = true;
      }
    };
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
  }

  // 6. Scroll Reveal & IntersectionObserver Engine
  initScrollReveals();

  function initScrollReveals() {
    // Respect reduced-motion preferences immediately
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Automatic discovery of reveal elements across all pages
    const revealSelectors = [
      '.section-header',
      '.service-card',
      '.service-card-featured',
      '.feature-box',
      '.pillar-card',
      '.industry-card',
      '.process-step',
      '.process-step-card',
      '.process-sequence-card',
      '.value-card',
      '.contact-card',
      '.contact-info-card',
      '.approach-card',
      '.engagement-model-card',
      '.workflow-node-box',
      '.cta-banner',
      '.cta-banner-content',
      '.contact-cta-band',
      '.reveal-on-scroll',
      '.reveal-fade'
    ];

    const elementsToReveal = document.querySelectorAll(revealSelectors.join(', '));

    if (prefersReducedMotion || !('IntersectionObserver' in window)) {
      elementsToReveal.forEach(el => {
        el.classList.add('is-revealed');
      });
      return;
    }

    // Auto-apply staggering for grouped cards inside grids
    const gridContainers = document.querySelectorAll(
      '.services-grid-7, .services-grid, .industries-grid, .why-datnix-grid, .features-grid, .values-grid, .process-sequence-grid, .workflow-pipeline-nodes, .contact-grid'
    );

    gridContainers.forEach(grid => {
      const items = grid.children;
      Array.from(items).forEach((item, idx) => {
        const delayClass = `reveal-delay-${(idx % 6) + 1}`;
        item.classList.add(delayClass);
      });
    });

    // Add base reveal class if not present
    elementsToReveal.forEach(el => {
      if (!el.classList.contains('reveal-on-scroll') && !el.classList.contains('reveal-fade')) {
        el.classList.add('reveal-on-scroll');
      }
    });

    const observerOptions = {
      root: null,
      rootMargin: '0px 0px -40px 0px',
      threshold: 0.12
    };

    const revealObserver = new IntersectionObserver((entries, observer) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-revealed');
          observer.unobserve(entry.target);
        }
      });
    }, observerOptions);

    elementsToReveal.forEach(el => {
      revealObserver.observe(el);
    });
  }

  // 7. Interactive WhatsApp Chatbot Engine
  initWhatsAppChatbot();

  function initWhatsAppChatbot() {
    const waFloatingBtn = document.querySelector('.floating-whatsapp');
    if (!waFloatingBtn) return;

    // Add unread message notification badge to floating button
    if (!waFloatingBtn.querySelector('.wa-unread-badge')) {
      const badge = document.createElement('span');
      badge.className = 'wa-unread-badge';
      badge.textContent = '1';
      badge.setAttribute('aria-label', '1 new message from DATNIX SOLUTIONS');
      waFloatingBtn.appendChild(badge);
    }

    // Create the chatbot popup DOM
    const chatbotHtml = `
      <div id="waChatWidget" class="wa-chatbot-popup" role="dialog" aria-modal="true" aria-label="DATNIX SOLUTIONS WhatsApp Assistant">
        <div class="wa-chat-header">
          <div class="wa-chat-header-info">
            <div class="wa-avatar">
              <img src="assets/images/datnix-logo.png" alt="DATNIX" onerror="this.src='../assets/images/datnix-logo.png'">
              <span class="wa-avatar-online" title="Online"></span>
            </div>
            <div class="wa-header-text">
              <h4>
                DATNIX SOLUTIONS
                <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366" style="display:inline-block;"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
              </h4>
              <p>Top 13th BPO in India &bull; Typically replies in 5m</p>
            </div>
          </div>
          <button type="button" id="waChatClose" class="wa-btn-close" aria-label="Close WhatsApp chat">&times;</button>
        </div>

        <div class="wa-chat-body" id="waChatBody">
          <div class="wa-date-divider">Today</div>
          
          <div class="wa-msg wa-msg-bot">
            <div>Hello! 👋 Welcome to <strong>DATNIX SOLUTIONS</strong>.</div>
            <div style="font-size: 11px; color: #54656F; margin-top: 4px;">
              📍 Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006)<br>
              🏆 <strong>Top 13th Company of India</strong> in BPO &amp; Data Services<br>
              🛡️ <strong>9+ Years of Market Trust</strong> &bull; <strong>543+ Projects Delivered</strong>
            </div>
            <div class="wa-msg-time">Just now</div>
          </div>

          <div class="wa-msg wa-msg-bot">
            <div>How can our team assist you today? Select a service or career option below, or type your requirement:</div>
            
            <div class="wa-chips-container" id="waChips">
              <button type="button" class="wa-chip-btn" data-intent="Job Application &amp; Career Openings in Ahmedabad">
                💼 Job Application &amp; Career Openings
              </button>
              <button type="button" class="wa-chip-btn" data-intent="Submit Resume for Data Entry / Back-Office Jobs">
                📄 Submit Resume / HR Hiring Inquiry
              </button>
              <button type="button" class="wa-chip-btn" data-intent="Data Entry &amp; BPO Quotation">
                📊 Request BPO &amp; Data Entry Quote
              </button>
              <button type="button" class="wa-chip-btn" data-intent="Arabic Data Conversion &amp; Localization">
                🌐 Arabic Data Conversion &amp; Processing
              </button>
              <button type="button" class="wa-chip-btn" data-intent="OCR Processing &amp; ePUB/XML Conversion">
                📄 OCR, ePUB &amp; XML Data Conversion
              </button>
              <button type="button" class="wa-chip-btn" data-intent="Multilingual &amp; All-Language Data Entry">
                🔤 All-Language Data Entry (Multilingual)
              </button>
              <button type="button" class="wa-chip-btn" data-intent="General Business Requirement Discussion">
                💬 Custom Requirement / Live Discussion
              </button>
            </div>
            <div class="wa-msg-time">Just now</div>
          </div>
        </div>

        <form class="wa-chat-footer" id="waChatForm" onsubmit="return false;">
          <input type="text" id="waChatInput" class="wa-input" placeholder="Type your project or job inquiry..." autocomplete="off">
          <button type="submit" id="waChatSend" class="wa-btn-send" aria-label="Send WhatsApp message">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
              <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
            </svg>
          </button>
        </form>
      </div>
    `;

    document.body.insertAdjacentHTML('beforeend', chatbotHtml);

    const popup = document.getElementById('waChatWidget');
    const closeBtn = document.getElementById('waChatClose');
    const chatBody = document.getElementById('waChatBody');
    const chatInput = document.getElementById('waChatInput');
    const chatForm = document.getElementById('waChatForm');
    const chipsContainer = document.getElementById('waChips');

    // Click handler for floating button
    waFloatingBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const isActive = popup.classList.contains('active');
      if (isActive) {
        popup.classList.remove('active');
      } else {
        popup.classList.add('active');
        // Hide unread badge once opened
        const badge = waFloatingBtn.querySelector('.wa-unread-badge');
        if (badge) badge.style.display = 'none';
        chatInput.focus();
      }
    });

    // Close button handler
    closeBtn.addEventListener('click', () => {
      popup.classList.remove('active');
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && popup.classList.contains('active')) {
        popup.classList.remove('active');
      }
    });

    // Handle Quick Action Chips
    chipsContainer.addEventListener('click', (e) => {
      const chip = e.target.closest('.wa-chip-btn');
      if (!chip) return;
      const intent = chip.getAttribute('data-intent');
      handleUserAction(intent);
    });

    // Handle custom form input submission
    chatForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;
      chatInput.value = '';
      handleUserAction(text);
    });

    function handleUserAction(userMessage) {
      // Append user bubble
      const now = new Date();
      const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      
      const userBubble = document.createElement('div');
      userBubble.className = 'wa-msg wa-msg-user';
      userBubble.innerHTML = `<div>${escapeHtml(userMessage)}</div><div class="wa-msg-time">${timeStr}</div>`;
      chatBody.appendChild(userBubble);
      chatBody.scrollTop = chatBody.scrollHeight;

      // Disable chips to avoid multiple clicks
      chipsContainer.style.pointerEvents = 'none';
      chipsContainer.style.opacity = '0.6';

      // Bot response bubble
      setTimeout(() => {
        const botBubble = document.createElement('div');
        botBubble.className = 'wa-msg wa-msg-bot';
        
        const isCareerInquiry = /career|job|resume|hiring|vacancy|opening|interview|work|apply|fresher|experience/i.test(userMessage);

        let fullWaText = '';
        let buttonLabel = '';
        let extraActionHtml = '';

        if (isCareerInquiry) {
          fullWaText = `Hello DATNIX SOLUTIONS HR Team, I am inquiring regarding career opportunities and job openings at your Ahmedabad office (20, Satkar Complex, C.G. Rd - 380006). I would like to submit my profile/resume for: ${userMessage}.`;
          buttonLabel = 'Chat with HR on WhatsApp &rarr;';
          const waLink = `https://wa.me/917387010393?text=${encodeURIComponent(fullWaText)}`;

          extraActionHtml = `
            <div><strong>Career Desk Connected!</strong> 👋 We are actively hiring for Data Operations, Arabic OCR Conversion, Quality Auditing, and Back-Office pods at our Ahmedabad headquarters and hybrid setups.</div>
            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
              <a href="${waLink}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; font-size:12px; padding:7px 14px; text-decoration:none; background:#25D366; color:white; border-radius:4px; font-weight:700;">
                <span>${buttonLabel}</span>
              </a>
              <a href="careers.html" class="btn btn-navy btn-sm" style="display:inline-flex; align-items:center; justify-content:center; gap:6px; font-size:12px; padding:7px 14px; text-decoration:none; background:#092D78; color:white; border-radius:4px; font-weight:700;">
                <span>View All 8+ Open Jobs &amp; Apply Online &rarr;</span>
              </a>
            </div>
            <div class="wa-msg-time">${timeStr}</div>
          `;
          botBubble.innerHTML = extraActionHtml;
          chatBody.appendChild(botBubble);
          chatBody.scrollTop = chatBody.scrollHeight;

          setTimeout(() => {
            window.open(waLink, '_blank', 'noopener,noreferrer');
          }, 1400);

        } else {
          fullWaText = `Hello DATNIX SOLUTIONS, I am inquiring regarding: ${userMessage}. Please provide information and quotation details.`;
          buttonLabel = 'Open WhatsApp Chat Now &rarr;';
          const waLink = `https://wa.me/917387010393?text=${encodeURIComponent(fullWaText)}`;

          extraActionHtml = `
            <div>Thank you! Connecting you directly to our solution desk on WhatsApp...</div>
            <div style="margin-top: 8px;">
              <a href="${waLink}" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" style="display:inline-flex; align-items:center; gap:6px; font-size:12px; padding:7px 14px; text-decoration:none; background:#25D366; color:white; border-radius:4px; font-weight:700;">
                <span>${buttonLabel}</span>
              </a>
            </div>
            <div class="wa-msg-time">${timeStr}</div>
          `;
          botBubble.innerHTML = extraActionHtml;
          chatBody.appendChild(botBubble);
          chatBody.scrollTop = chatBody.scrollHeight;

          setTimeout(() => {
            window.open(waLink, '_blank', 'noopener,noreferrer');
          }, 1100);
        }

    function escapeHtml(str) {
      return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
  }
});

// Inline keyframe helper for spinner
const style = document.createElement('style');
style.innerHTML = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
document.head.appendChild(style);
