<?php
/**
 * DATNIX SOLUTIONS - Public Careers Portal
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$jobs = [];
if (!empty($pdo)) {
    try {
        $stmt = $pdo->prepare('
            SELECT id, title, slug, department, location, employment_type, experience_req, short_description 
            FROM jobs 
            WHERE status = "Active" AND is_published = 1 
            ORDER BY created_at DESC
        ');
        $stmt->execute();
        $jobs = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[PUBLIC CAREERS FETCH ERROR] ' . $e->getMessage());
    }
}

// Fallback active jobs if database is currently cold/unseeded
if (empty($jobs)) {
    $jobs = [
        [
            'id' => 1,
            'title' => 'Senior Data Entry Specialist (All Languages & High-Volume)',
            'slug' => 'senior-data-entry-specialist-all-languages',
            'department' => 'Data Operations',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006) / Hybrid',
            'employment_type' => 'Full-Time',
            'experience_req' => '2 - 4 Years',
            'short_description' => 'High-volume, multi-language data entry, conversion, character encoding verification (UTF-8), and validation across enterprise client workflows.'
        ],
        [
            'id' => 2,
            'title' => 'Arabic Data Conversion & Digitization Specialist',
            'slug' => 'arabic-data-conversion-specialist',
            'department' => 'Multilingual & Localization',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006) / Hybrid',
            'employment_type' => 'Full-Time',
            'experience_req' => '2 - 5 Years',
            'short_description' => 'Specialized Arabic language OCR, bidirectional (RTL) text processing, Arabic manuscripts/deed conversion into Word, Excel, XML, and digital formats.'
        ],
        [
            'id' => 3,
            'title' => 'OCR & Digital Publishing Associate (ePUB / XML / HTML5)',
            'slug' => 'ocr-digital-publishing-epub-xml',
            'department' => 'Data Conversion',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006) / Hybrid',
            'employment_type' => 'Full-Time',
            'experience_req' => '1 - 3 Years',
            'short_description' => 'Book, journal, and paper scanning, OCR layout retention, and structured conversion into digital ePUB, XML (JATS/TEI), and HTML5 formats.'
        ],
        [
            'id' => 4,
            'title' => 'Multilingual Data Entry & Verification Executive',
            'slug' => 'multilingual-data-entry-executive',
            'department' => 'International Operations',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006)',
            'employment_type' => 'Full-Time',
            'experience_req' => '1 - 3 Years',
            'short_description' => 'Data entry and cross-verification across European, Middle Eastern, and Indian language records with strict UTF-8 Unicode standards.'
        ],
        [
            'id' => 5,
            'title' => 'Quality Assurance Auditor (Data & BPO)',
            'slug' => 'qa-auditor-data-bpo',
            'department' => 'Quality Assurance',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006)',
            'employment_type' => 'Full-Time',
            'experience_req' => '3 - 5 Years',
            'short_description' => 'Independent secondary audits, statistical sampling, error registers, and dual-pass verification across all client data deliverables.'
        ],
        [
            'id' => 6,
            'title' => 'E-Commerce Catalog & SKU Enrichment Specialist',
            'slug' => 'ecommerce-catalog-specialist',
            'department' => 'E-Commerce Data',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006) / Hybrid',
            'employment_type' => 'Full-Time',
            'experience_req' => '2 - 4 Years',
            'short_description' => 'Multi-channel product listings, SKU taxonomy categorization, and digital asset matching for global retail portals.'
        ],
        [
            'id' => 7,
            'title' => 'Document Digitization & High-Speed Scanning Technician',
            'slug' => 'document-digitization-scanning-technician',
            'department' => 'Document Management',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006)',
            'employment_type' => 'Full-Time',
            'experience_req' => '1 - 3 Years',
            'short_description' => 'Operating production rotary and flatbed scanners, batch indexing, metadata assignment, and secure document archiving.'
        ],
        [
            'id' => 8,
            'title' => 'Back-Office Operations Executive',
            'slug' => 'back-office-operations-executive',
            'department' => 'Back-Office Support',
            'location' => 'Ahmedabad, Gujarat (Satkar Complex, C.G. Rd - 380006) / Hybrid',
            'employment_type' => 'Full-Time',
            'experience_req' => '1 - 3 Years',
            'short_description' => 'Routine administrative workflows, invoice verification, vendor records upkeep, and daily reporting.'
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  
  <title>Careers &amp; Job Openings | DATNIX SOLUTIONS</title>
  <meta name="description" content="Explore open career opportunities at DATNIX SOLUTIONS. Join our operational pods in data entry, document processing, QA auditing, and back-office services.">
  <meta name="author" content="DATNIX SOLUTIONS">
  <link rel="canonical" href="https://datnixsolutions.com/careers.php">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="Careers &amp; Job Openings | DATNIX SOLUTIONS">
  <meta property="og:description" content="Explore open positions at DATNIX SOLUTIONS. Build a career in enterprise data operations and BPO services.">
  <meta property="og:url" content="https://datnixsolutions.com/careers.php">
  <meta property="og:image" content="https://datnixsolutions.com/assets/images/datnix-social-share.png">

  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">

  <!-- Stylesheet -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.min.css">
  <style>
    .job-card {
      background: var(--color-white);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 2rem;
      box-shadow: var(--shadow-sm);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
      position: relative;
    }
    .job-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--shadow-md);
      border-color: var(--color-navy);
    }
    .job-dept-badge {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--color-navy);
      background: rgba(9, 45, 120, 0.08);
      padding: 3px 8px;
      border-radius: var(--radius-sm);
      margin-bottom: 0.75rem;
      width: fit-content;
    }
    .job-meta-row {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      font-size: 0.85rem;
      color: var(--text-muted);
      margin-bottom: 1rem;
    }
    .job-meta-item {
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .job-meta-item svg {
      width: 15px;
      height: 15px;
      color: var(--color-orange);
    }
    .culture-card {
      background: var(--color-white);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 1.75rem;
      box-shadow: var(--shadow-sm);
    }
    .culture-icon {
      width: 44px;
      height: 44px;
      border-radius: var(--radius-sm);
      background: rgba(255, 121, 0, 0.12);
      color: var(--color-orange);
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>

  <!-- Top Utility Bar -->
  <div class="top-bar">
    <div class="container top-bar-inner">
      <div class="top-bar-left">
        <a href="tel:+917387010393" class="top-bar-item" data-config="phone-link">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
          </svg>
          <span data-config="phone">+91 73870 10393</span>
        </a>
        <a href="mailto:datnixsolutions@gmail.com" class="top-bar-item" data-config="email-link">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
            <polyline points="22,6 12,13 2,6"></polyline>
          </svg>
          <span data-config="email">datnixsolutions@gmail.com</span>
        </a>
      </div>
      <div class="top-bar-right">
        <span class="tagline-badge" data-config="tagline">DATA. PROCESS. PERFORMANCE.</span>
      </div>
    </div>
  </div>

  <!-- Header / Navigation -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.html" class="brand-link" aria-label="DATNIX SOLUTIONS Home">
        <img src="assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" class="brand-logo-img" data-config="logo" width="168" height="44" fetchpriority="high">
      </a>

      <nav class="main-nav" aria-label="Primary Navigation">
        <a href="index.html" class="nav-link">Home</a>
        <a href="about.html" class="nav-link">About Us</a>
        <a href="services.html" class="nav-link">Services</a>
        <a href="industries.html" class="nav-link">Industries</a>
        <a href="process.html" class="nav-link">Our Process</a>
        <a href="why-choose-us.html" class="nav-link">Why Choose Us</a>
        <a href="careers.php" class="nav-link active">Careers</a>
        <a href="contact.html" class="nav-link">Contact</a>
      </nav>

      <div class="header-actions">
        <a href="contact.html#quote" class="btn btn-primary btn-sm header-cta">Request a Quote</a>
        <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Toggle navigation menu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <!-- Mobile Drawer Backdrop & Menu -->
  <div class="drawer-backdrop" id="drawerBackdrop"></div>
  <aside class="mobile-drawer" id="mobileDrawer" aria-label="Mobile Navigation">
    <div class="mobile-drawer-header">
      <img src="assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" style="height: 44px; width: auto;" data-config="logo" width="168" height="44" fetchpriority="high">
      <button class="mobile-drawer-close" id="mobileDrawerClose" aria-label="Close menu">
        <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
      </button>
    </div>
    <nav class="mobile-drawer-nav">
      <a href="index.html">Home</a>
      <a href="about.html">About Us</a>
      <a href="services.html">Services</a>
      <a href="industries.html">Industries</a>
      <a href="process.html">Our Process</a>
      <a href="why-choose-us.html">Why Choose Us</a>
      <a href="careers.php" class="active">Careers</a>
      <a href="contact.html">Contact</a>
    </nav>
    <div class="mobile-drawer-contact">
      <a href="contact.html#quote" class="btn btn-primary btn-sm">Request a Quote</a>
      <a href="tel:+917387010393" class="btn btn-navy btn-sm" data-config="phone-link">Call: +91 73870 10393</a>
      <a href="https://wa.me/917387010393" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" data-config="whatsapp-link">Chat on WhatsApp</a>
    </div>
  </aside>

  <!-- Page Banner -->
  <section class="page-banner">
    <div class="container page-banner-content">
      <div class="breadcrumb">
        <a href="index.html">Home</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Careers</span>
      </div>
      <h1 class="page-banner-title">Build Your Career at DATNIX SOLUTIONS</h1>
      <p class="page-banner-desc">
        Join our disciplined operational teams. We offer structured training, career advancement, and a supportive workplace for data specialists, QA professionals, and back-office executives.
      </p>
    </div>
  </section>

  <!-- Why Work With Us (Culture & Benefits) -->
  <section class="section section-white">
    <div class="container">
      <div class="section-header">
        <div class="section-subtitle">Work Culture &amp; Growth</div>
        <h2 class="section-title">Why Professionals Choose DATNIX</h2>
        <p class="section-description">
          We combine institutional operational discipline with a people-first workplace culture that values accuracy, integrity, and career advancement.
        </p>
      </div>

      <div class="grid-3" style="gap: 2rem;">
        <div class="culture-card">
          <div class="culture-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
          </div>
          <h3 style="font-size: 1.25rem; color: var(--color-navy-dark); margin-bottom: 0.5rem;">Structured Workflow Training</h3>
          <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin: 0;">
            Every team member receives comprehensive onboarding on Standard Operating Procedures, dual-pass quality verification, and modern data platforms.
          </p>
        </div>

        <div class="culture-card">
          <div class="culture-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
          </div>
          <h3 style="font-size: 1.25rem; color: var(--color-navy-dark); margin-bottom: 0.5rem;">Merit-Based Progression</h3>
          <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin: 0;">
            Performance and quality adherence dictate career advancement. High-accuracy specialists quickly transition into QA audit and team lead roles.
          </p>
        </div>

        <div class="culture-card">
          <div class="culture-icon">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          </div>
          <h3 style="font-size: 1.25rem; color: var(--color-navy-dark); margin-bottom: 0.5rem;">Supportive Team Environment</h3>
          <p style="font-size: 0.9rem; color: var(--text-secondary); line-height: 1.6; margin: 0;">
            Predictable operational shifts, transparent management, ethical data confidentiality standards, and open communication channels.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Active Job Openings Section (MODULE 4) -->
  <section class="section section-light" id="openings">
    <div class="container">
      <div class="section-header">
        <div class="section-subtitle">Current Openings</div>
        <h2 class="section-title">Explore Open Positions</h2>
        <p class="section-description">
          Review our active operational openings below and submit your application with resume.
        </p>
      </div>

      <?php if (empty($jobs)): ?>
        <div style="background: var(--color-white); border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 3rem 2rem; text-align: center; max-width: 650px; margin: 0 auto;">
          <h3 style="color: var(--color-navy-dark); margin-bottom: 0.5rem;">No Active Openings at this Moment</h3>
          <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.5rem;">
            We regularly expand our operational pods for new projects. You can submit an open profile to our talent pool.
          </p>
          <a href="job-detail.php?id=0" class="btn btn-primary">Submit General Application</a>
        </div>
      <?php else: ?>
        <div class="grid-2" style="gap: 2rem;">
          <?php foreach ($jobs as $j): ?>
            <div class="job-card">
              <div>
                <span class="job-dept-badge"><?php echo e($j['department']); ?></span>
                <h3 style="font-size: 1.35rem; color: var(--color-navy-dark); margin-bottom: 0.65rem;"><?php echo e($j['title']); ?></h3>
                
                <div class="job-meta-row">
                  <div class="job-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span><?php echo e($j['location']); ?></span>
                  </div>
                  <div class="job-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    <span><?php echo e($j['employment_type']); ?></span>
                  </div>
                  <div class="job-meta-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
                    <span>Exp: <?php echo e($j['experience_req']); ?></span>
                  </div>
                </div>

                <p style="font-size: 0.92rem; color: var(--text-secondary); line-height: 1.6; margin-bottom: 1.5rem;">
                  <?php echo e($j['short_description']); ?>
                </p>
              </div>

              <div style="border-top: 1px solid var(--border-light); padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <a href="job-detail.php?id=<?php echo (int)$j['id']; ?>" class="btn btn-navy btn-sm">
                  <span>View Details &amp; Apply</span>
                  <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </a>
                <span style="font-size: 0.8rem; color: var(--text-muted);">Status: <strong style="color: #059669;">Actively Hiring</strong></span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- Open Talent Application Callout -->
  <section class="section section-white">
    <div class="container">
      <div style="background: linear-gradient(135deg, #092D78 0%, #061C4C 100%); border-radius: var(--radius-lg); padding: 3rem 2.5rem; color: var(--color-white); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 2rem; border-top: 4px solid var(--color-orange);">
        <div style="max-width: 650px;">
          <span class="tagline-badge" style="background: rgba(255, 121, 0, 0.2); margin-bottom: 1rem;">DATA OPERATIONS TALENT POOL</span>
          <h2 style="font-size: 1.85rem; color: #FFFFFF; margin-bottom: 0.75rem;">Don't See an Exact Role Matching Your Profile?</h2>
          <p style="color: #CBD5E1; font-size: 1rem; line-height: 1.6; margin: 0;">
            Submit an open application. Our HR and operational supervisors regularly review candidate profiles for upcoming enterprise data projects and seasonal backlog sprints.
          </p>
        </div>
        <div>
          <a href="job-detail.php?id=0" class="btn btn-primary btn-lg">Submit General Application &rarr;</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Corporate Footer -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div class="footer-col-brand">
          <div class="footer-logo-card">
            <img src="assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" class="footer-logo-img" data-config="logo" width="168" height="44">
          </div>
          <p class="footer-brand-desc">
            DATNIX SOLUTIONS delivers structured B2B outsourcing, high-precision data operations, and disciplined business process execution.
          </p>
          <div class="footer-tagline-text" data-config="tagline">DATA. PROCESS. PERFORMANCE.</div>
        </div>

        <div class="footer-col">
          <h4 class="footer-heading">Navigation</h4>
          <ul class="footer-links">
            <li><a href="index.html">Home</a></li>
            <li><a href="about.html">About Us</a></li>
            <li><a href="services.html">Services</a></li>
            <li><a href="industries.html">Industries</a></li>
            <li><a href="process.html">Our Process</a></li>
            <li><a href="careers.php">Careers</a></li>
            <li><a href="contact.html">Contact Us</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4 class="footer-heading">Our Services</h4>
          <ul class="footer-links">
            <li><a href="service-data-entry.html">Data Entry Services</a></li>
            <li><a href="service-data-processing.html">Data Processing</a></li>
            <li><a href="service-back-office-support.html">Back Office Support</a></li>
            <li><a href="service-document-management.html">Document Management</a></li>
            <li><a href="service-web-research.html">Web Research &amp; Collection</a></li>
            <li><a href="service-ecommerce-data-management.html">E-commerce Data</a></li>
            <li><a href="service-data-verification.html">Data Verification &amp; QA</a></li>
            <li><a href="service-bpo-process-support.html">BPO &amp; Process Support</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4 class="footer-heading">Contact Details</h4>
          <ul class="footer-contact-list">
            <li class="footer-contact-item">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #94A3B8;">Corporate Office</div>
                <span data-config="address">20, Satkar Complex, Chimanlal Girdharlal Rd, Ahmedabad - 380006, Gujarat</span>
              </div>
            </li>
            <li class="footer-contact-item">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #94A3B8;">Phone</div>
                <a href="tel:+917387010393" data-config="phone-link">+91 73870 10393</a>
              </div>
            </li>
            <li class="footer-contact-item">
              <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
              <div>
                <div style="font-size: 0.78rem; text-transform: uppercase; color: #94A3B8;">Email</div>
                <a href="mailto:datnixsolutions@gmail.com" data-config="email-link">datnixsolutions@gmail.com</a>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <div class="footer-bottom">
        <div>&copy; <?php echo date('Y'); ?> DATNIX SOLUTIONS. All Rights Reserved.</div>
        <div><span>DATA. PROCESS. PERFORMANCE.</span></div>
      </div>
    </div>
  </footer>

  <script src="assets/js/site-config.min.js" defer></script>
  <script src="assets/js/main.min.js" defer></script>
</body>
</html>
