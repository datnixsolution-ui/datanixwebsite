<?php
/**
 * DATNIX SOLUTIONS - Job Detail & Application Portal
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$jobId = (int)($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');
$job = null;
$allActiveJobs = [];

$staticJobsList = [
    1 => [
        'id' => 1,
        'title' => 'Data Entry Operator (Fresher & Experienced)',
        'slug' => 'data-entry-operator-fresher-experienced',
        'department' => 'Data Entry Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '0 - 2 Years (Freshers Welcome)',
        'short_description' => 'Accurate and high-speed alphanumeric data entry, form keying, master record updating, and spreadsheet data entry across client projects.',
        'responsibilities' => "• Key alphanumeric data from invoices, paper receipts, and scanned records into spreadsheets.\n• Achieve daily target output quotas while maintaining 98%+ character accuracy.\n• Perform initial self-audit checks against source documents prior to batch submission.\n• Comply strictly with enterprise client confidentiality, clean-desk, and NDA protocols.",
        'required_skills' => "• Typing speed of 30+ WPM with high accuracy.\n• Basic proficiency in Microsoft Excel, Google Sheets, and keyboard numerical entry.\n• High attention to detail and error spotting.\n• Freshers with good typing skills and basic computer literacy are encouraged to apply.",
        'benefits' => "• 100% Work From Home (WFH) with flexible shift arrangements.\n• Comprehensive remote onboarding and digital tools training provided.\n• Performance-linked monthly incentives and annual appraisals."
    ],
    2 => [
        'id' => 2,
        'title' => 'Senior Data Entry Specialist (High-Volume & Speed)',
        'slug' => 'senior-data-entry-specialist',
        'department' => 'Data Entry Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '2 - 4 Years',
        'short_description' => 'High-volume, rapid-turnaround batch data entry, complex tabulations, dual-screen comparison, and specialized customer database updating.',
        'responsibilities' => "• Transcribe complex alphanumeric records from multi-format source files into enterprise systems.\n• Execute dual-screen verification checks to ensure zero-defect output conformity (99.8% SLA).\n• Assist team supervisors in tracking hourly team productivity metrics and turnaround SLAs.\n• Flag ambiguous source records, formatting anomalies, and validation errors to project managers.",
        'required_skills' => "• Proven typing speed of 45+ WPM with 99%+ accuracy.\n• High proficiency in 10-key numeric keypad, keyboard shortcuts, and Excel functions.\n• Minimum 2 years of prior experience in international data entry / BPO operations.\n• Proven ability to handle high-volume repetitive tasks with steady focus.",
        'benefits' => "• Competitive salary package + high-performance bonuses.\n• Fast-track leadership progression to Remote Pod Lead.\n• Modern cloud workflow tools and continuous technical support."
    ],
    3 => [
        'id' => 3,
        'title' => 'Multilingual Data Entry Executive (English & Regional)',
        'slug' => 'multilingual-data-entry-executive',
        'department' => 'Multilingual Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Data entry and verification for multi-language documents, bilingual records, and regional Indian language datasets with Unicode standards.',
        'responsibilities' => "• Transcribe customer records, public registries, and surveys into English and regional Unicode text.\n• Verify transliteration accuracy and spelling consistency for names, addresses, and local terminology.\n• Key in alphanumeric IDs, phone numbers, and financial figures from multilingual forms.\n• Perform cross-check validation between English and vernacular language fields.",
        'required_skills' => "• Strong written English plus fluency in Hindi, Gujarati, or other regional languages.\n• Experience with Unicode keyboard layouts and bilingual data entry software.\n• Typing speed of 35+ WPM with meticulous character fidelity.\n• Prior experience in census, voter list, or multilingual banking data entry is a plus.",
        'benefits' => "• Multilingual skill allowance.\n• 100% remote working flexibility.\n• Supportive virtual team structure and weekly peer syncs."
    ],
    4 => [
        'id' => 4,
        'title' => 'Excel & Spreadsheet Data Entry Associate',
        'slug' => 'excel-spreadsheet-data-entry-associate',
        'department' => 'Back-Office Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Structured spreadsheet data entry, formula applications, row/column consolidation, deduplication, and database import preparation in MS Excel.',
        'responsibilities' => "• Populate and maintain standardized Microsoft Excel workbooks and Google Sheets trackers.\n• Cleanse incoming datasets using trim, deduplication, find-and-replace, and VLOOKUP functions.\n• Convert unstructured customer text tables into normalized tabular records.\n• Generate daily activity logs and row counts for project managers.",
        'required_skills' => "• Strong hands-on knowledge of MS Excel formulas, filters, conditional formatting, and shortcuts.\n• Accurate 10-key numeric pad typing speed.\n• High analytical attentiveness to spot mismatched figures and orphan records.\n• Familiarity with CSV, XLSX, and Google Drive sharing protocols.",
        'benefits' => "• Flexible remote work schedule.\n• Skill enhancement programs covering advanced macro automation.\n• Monthly performance allowances."
    ],
    5 => [
        'id' => 5,
        'title' => 'E-commerce Product Data Entry Assistant',
        'slug' => 'ecommerce-product-data-entry-assistant',
        'department' => 'E-commerce Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 2 Years',
        'short_description' => 'Online product listing, SKU catalog keying, price updates, attribute tagging, and bulk inventory spreadsheet management for e-commerce platforms.',
        'responsibilities' => "• Enter product titles, SKU codes, manufacturer part numbers, dimensions, and prices into catalog sheets.\n• Map product attributes (color, size, material, category tags) accurately to marketplace taxonomies.\n• Cross-check vendor spec sheets against marketplace listings to prevent catalog inaccuracies.\n• Key in inventory stock levels and promotional pricing updates.",
        'required_skills' => "• Familiarity with online shopping platforms (Shopify, Amazon, Flipkart, WooCommerce portals).\n• Typing speed of 35+ WPM with strong attention to product spec details.\n• Experience uploading CSV batches or editing bulk product spreadsheets.\n• Diligent, detail-focused mindset for catalog uniformity.",
        'benefits' => "• 100% remote work setup.\n• Direct exposure to global e-commerce brand operations.\n• Product accuracy bonuses."
    ],
    6 => [
        'id' => 6,
        'title' => 'Remote Document Data Entry Clerk (Online / WFH)',
        'slug' => 'remote-document-data-entry-clerk',
        'department' => 'Data Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '0 - 2 Years (Freshers Welcome)',
        'short_description' => 'Digital indexing, metadata transcription, form entry, and verification from scanned enterprise records and digital forms.',
        'responsibilities' => "• Access secure cloud portals to view and transcribe scanned document batches.\n• Enter dates, invoice numbers, tax IDs, and transaction amounts with strict fidelity.\n• Tag and organize processed digital files into client repository structures.\n• Complete daily quota assignments on schedule under remote supervision.",
        'required_skills' => "• Minimum typing speed of 30+ WPM with 95%+ accuracy.\n• Reliable personal computer or laptop with stable high-speed broadband connection.\n• Basic computer proficiency and high degree of discipline for independent remote work.\n• Strong confidentiality ethics and compliance with remote data protection rules.",
        'benefits' => "• 100% Work From Home (WFH) across any state/city in India.\n• High-speed internet allowance assistance.\n• Paid digital training."
    ],
    7 => [
        'id' => 7,
        'title' => 'Data Verification & Quality Checking (QC) Specialist',
        'slug' => 'data-verification-qc-specialist',
        'department' => 'Quality Assurance',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '2 - 4 Years',
        'short_description' => 'Second-tier audit, dual-key verification, anomaly detection, character-level comparison, and 99.5%+ accuracy audit for completed data batches.',
        'responsibilities' => "• Conduct statistical sample audits and 100% dual-entry cross-verification across completed batches.\n• Compute error rates (DPU/DPMO) and compile daily quality scorecards for data entry operators.\n• Identify systemic keying errors and recommend process corrections to operations supervisors.\n• Authorize batch sign-off for final client delivery under 99.8% quality SLAs.",
        'required_skills' => "• 2+ years prior experience in quality control, auditing, or proof-checking within BPO/data services.\n• Outstanding error-detection capability and meticulous eagle-eye attention to detail.\n• Proficiency in data comparison tools, Excel diff scripts, and validation checklists.\n• Strong written and verbal communication for delivering objective operator feedback.",
        'benefits' => "• Senior-grade compensation package + quarterly quality incentives.\n• Fast-track promotion path to Remote Operations Supervisor.\n• Comprehensive health coverage support."
    ],
    8 => [
        'id' => 8,
        'title' => 'Back-Office Data Entry & Invoice Processing Clerk',
        'slug' => 'back-office-data-entry-invoice-clerk',
        'department' => 'Back-Office Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Entry and verification of vendor bills, accounts payable vouchers, purchase receipts, line-item reconciliation, and ledger record updating.',
        'responsibilities' => "• Process and enter incoming supplier invoices, claims, and delivery notes into ERP systems.\n• Cross-verify invoice line items against purchase orders and approved rate sheets.\n• Update internal records, address books, and customer transaction logs.\n• Prepare daily shift activity summaries and transaction logs for management.",
        'required_skills' => "• Prior experience in back-office operations, accounting data entry, or commercial BPO.\n• Good numerical reasoning and typing accuracy.\n• Basic knowledge of accounting software or MS Excel.\n• Strong team collaboration and communication skills via Slack / WhatsApp.",
        'benefits' => "• Stable remote corporate environment with regular business hours.\n• Performance-linked quarterly rewards.\n• Work from anywhere in India."
    ]
];

if (!empty($pdo)) {
    try {
        if ($jobId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM jobs WHERE id = :id AND status = "Active" AND is_published = 1');
            $stmt->execute([':id' => $jobId]);
            $job = $stmt->fetch();
        } elseif (!empty($slug)) {
            $stmt = $pdo->prepare('SELECT * FROM jobs WHERE slug = :slug AND status = "Active" AND is_published = 1');
            $stmt->execute([':slug' => $slug]);
            $job = $stmt->fetch();
        }

        // Fetch all active jobs for dropdown selector
        $allStmt = $pdo->query('SELECT id, title FROM jobs WHERE status = "Active" AND is_published = 1 ORDER BY title ASC');
        $allActiveJobs = $allStmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[JOB DETAIL FETCH ERROR] ' . $e->getMessage());
    }
}

// Fallback to static job dataset if database is cold/unseeded
if ($job === null) {
    if ($jobId > 0 && isset($staticJobsList[$jobId])) {
        $job = $staticJobsList[$jobId];
    } elseif (!empty($slug)) {
        foreach ($staticJobsList as $sj) {
            if ($sj['slug'] === $slug) {
                $job = $sj;
                break;
            }
        }
    }
}

if (empty($allActiveJobs)) {
    foreach ($staticJobsList as $sj) {
        $allActiveJobs[] = ['id' => $sj['id'], 'title' => $sj['title']];
    }
}

// Fallback for general talent pool application if job not found or id=0
$isGeneral = ($job === null);
$pageJobTitle = $isGeneral ? 'General Talent Pool Application' : $job['title'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  
  <!-- Primary SEO Metadata -->
  <title><?php echo e($pageJobTitle); ?> | 100% Work From Home (WFH) | DATNIX SOLUTIONS</title>
  <meta name="description" content="Apply for <?php echo e($pageJobTitle); ?> (100% Work From Home) at DATNIX SOLUTIONS. Submit your application online for remote data entry and operations jobs across India. Freshers welcome, no resume required.">
  <meta name="keywords" content="datnixs, datnixs solutions, datnixs 42web io, datnix, Work from home data entry jobs, remote data entry application, WFH data typing jobs, online data entry operator India, remote excel jobs, freshers data entry jobs, DATNIX SOLUTIONS careers">
  <meta name="author" content="DATNIX SOLUTIONS">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <link rel="canonical" href="https://datnixs.42web.io/job-detail.php<?php echo !empty($slug) ? '?slug=' . urlencode($slug) : ($jobId > 0 ? '?id=' . $jobId : ''); ?>">

  <!-- Geographic & Local Search Engine Optimization (GEO) -->
  <meta name="geo.region" content="IN">
  <meta name="geo.placename" content="India (Nationwide 100% Work From Home / Remote)">
  <meta name="geo.position" content="20.5937;78.9629">
  <meta name="ICBM" content="20.5937, 78.9629">

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="DATNIX SOLUTIONS">
  <meta property="og:title" content="<?php echo e($pageJobTitle); ?> | 100% Work From Home (WFH) | DATNIX SOLUTIONS">
  <meta property="og:description" content="Apply for <?php echo e($pageJobTitle); ?> (100% Work From Home) at DATNIX SOLUTIONS. Freshers welcome, no resume required.">
  <meta property="og:url" content="https://datnixs.42web.io/job-detail.php<?php echo !empty($slug) ? '?slug=' . urlencode($slug) : ($jobId > 0 ? '?id=' . $jobId : ''); ?>">
  <meta property="og:image" content="https://datnixs.42web.io/assets/images/datnix-social-share.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">

  <!-- Twitter / X Card -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo e($pageJobTitle); ?> | 100% Work From Home (WFH) | DATNIX SOLUTIONS">
  <meta name="twitter:description" content="Apply for <?php echo e($pageJobTitle); ?> at DATNIX SOLUTIONS. Remote WFH data entry openings across India.">
  <meta name="twitter:image" content="https://datnixs.42web.io/assets/images/datnix-social-share.png">

  <!-- Structured Data (JSON-LD: Google for Jobs & BreadcrumbList) -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "BreadcrumbList",
        "itemListElement": [
          {
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://datnixs.42web.io/"
          },
          {
            "@type": "ListItem",
            "position": 2,
            "name": "Careers",
            "item": "https://datnixs.42web.io/careers.php"
          },
          {
            "@type": "ListItem",
            "position": 3,
            "name": "Job Application",
            "item": "https://datnixs.42web.io/job-detail.php"
          }
        ]
      },
      {
        "@type": "JobPosting",
        "title": <?php echo json_encode($pageJobTitle . ' (100% Work From Home)'); ?>,
        "description": <?php echo json_encode('<p>DATNIX SOLUTIONS is hiring for ' . htmlspecialchars($pageJobTitle) . ' (100% Work From Home / Remote) positions across India. Freshers and experienced candidates welcome. Deliver high-accuracy data entry, spreadsheet management, and document verification from home.</p>'); ?>,
        "identifier": {
          "@type": "PropertyValue",
          "name": "DATNIX SOLUTIONS",
          "value": <?php echo json_encode('DNX-WFH-APP-' . ($job ? $job['id'] : 'GEN')); ?>
        },
        "datePosted": "2026-11-19",
        "validThrough": "2027-12-31T23:59:59+05:30",
        "employmentType": "FULL_TIME",
        "hiringOrganization": {
          "@type": "Organization",
          "name": "DATNIX SOLUTIONS",
          "alternateName": ["datnixs", "Datnixs", "DATNIX", "Datnix Solutions", "datnixs solutions", "datnixs.42web.io"],
          "sameAs": "https://datnixs.42web.io",
          "logo": "https://datnixs.42web.io/assets/images/datnix-logo.png"
        },
        "jobLocationType": "TELECOMMUTE",
        "applicantLocationRequirements": {
          "@type": "Country",
          "name": "India"
        },
        "baseSalary": {
          "@type": "MonetaryAmount",
          "currency": "INR",
          "value": {
            "@type": "QuantitativeValue",
            "minValue": 180000,
            "maxValue": 360000,
            "unitText": "YEAR"
          }
        }
      }
    ]
  }
  </script>

  <!-- Favicons -->
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/images/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/images/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/images/apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#092D78">

  <!-- Google Search Console Verification Placeholder -->
  <!-- <meta name="google-site-verification" content="YOUR_GOOGLE_SEARCH_CONSOLE_VERIFICATION_CODE"> -->

  <!-- Google Analytics 4 (GA4) Integration Placeholder -->
  <!--
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-XXXXXXXXXX');
  </script>
  -->

  <!-- Stylesheet -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/style.min.css">
  <style>
    .job-spec-box {
      background: var(--color-white);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      padding: 2.25rem;
      box-shadow: var(--shadow-sm);
    }
    .spec-header {
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 1.5rem;
      margin-bottom: 1.75rem;
    }
    .spec-meta-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 1rem;
      background: var(--color-light-bg);
      padding: 1.25rem;
      border-radius: var(--radius-sm);
      margin-bottom: 2rem;
    }
    .spec-meta-item div:first-child {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
    }
    .spec-meta-item div:last-child {
      font-size: 0.95rem;
      font-weight: 600;
      color: var(--color-navy);
      margin-top: 2px;
    }
    .section-list-box {
      margin-bottom: 2rem;
    }
    .section-list-box h3 {
      font-size: 1.15rem;
      color: var(--color-navy-dark);
      margin-bottom: 0.85rem;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .bullet-list {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 0.65rem;
      font-size: 0.92rem;
      color: var(--text-secondary);
      line-height: 1.6;
    }
    .bullet-list li {
      display: flex;
      align-items: flex-start;
      gap: 0.5rem;
    }
    .bullet-list li::before {
      content: "•";
      color: var(--color-orange);
      font-weight: 800;
      font-size: 1.2rem;
      line-height: 1;
    }
  </style>
</head>
<body>

  <!-- Top Utility Bar -->
  <div class="top-bar">
    <div class="container top-bar-inner">
      <div class="top-bar-left">
        <a href="tel:+917387010393" class="top-bar-item" data-config="phone-link">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
          <span data-config="phone">+91 73870 10393</span>
        </a>
        <a href="mailto:datnixsolution@gmail.com" class="top-bar-item" data-config="email-link">
          <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
          <span data-config="email">datnixsolution@gmail.com</span>
        </a>
      </div>
      <div class="top-bar-right">
        <span class="tagline-badge" data-config="tagline">DATA. PROCESS. PERFORMANCE.</span>
      </div>
    </div>
  </div>

  <!-- Header -->
  <header class="site-header">
    <div class="container header-inner">
      <a href="index.html" class="brand-link">
        <img src="assets/images/datnix-logo.png" alt="DATNIX SOLUTIONS" class="brand-logo-img" data-config="logo" width="168" height="44">
      </a>

      <nav class="main-nav">
        <a href="index.html" class="nav-link">Home</a>
        <a href="about.html" class="nav-link">About Us</a>
        <a href="services.html" class="nav-link">Services</a>
        <a href="industries.html" class="nav-link">Industries</a>
        <a href="process.html" class="nav-link">Our Process</a>
        <a href="careers.php" class="nav-link active">Careers</a>
        <a href="contact.html" class="nav-link">Contact</a>
      </nav>

      <div class="header-actions">
        <a href="#apply" class="btn btn-primary btn-sm header-cta">Apply for Position</a>
      </div>
    </div>
  </header>

  <!-- Page Banner -->
  <section class="page-banner">
    <div class="container page-banner-content">
      <div class="breadcrumb">
        <a href="index.html">Home</a>
        <span class="breadcrumb-separator">/</span>
        <a href="careers.php">Careers</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current"><?php echo e($pageJobTitle); ?></span>
      </div>
      <h1 class="page-banner-title"><?php echo e($pageJobTitle); ?></h1>
      <p class="page-banner-desc">
        <?php echo $isGeneral ? 'Submit an open application for our operational talent database in Ahmedabad.' : e($job['short_description']); ?>
      </p>
    </div>
  </section>

  <!-- Main Content Layout -->
  <section class="section section-white">
    <div class="container">
      <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 2.5rem; align-items: start;">
        
        <!-- Left Column: Job Description & Requirements -->
        <div>
          <div class="job-spec-box">
            <?php if (!$isGeneral): ?>
              <div class="spec-header">
                <span class="badge-pill" style="margin-bottom: 0.75rem;"><?php echo e($job['department']); ?></span>
                <h2 style="color: var(--color-navy-dark); font-size: 1.65rem; margin-bottom: 0.5rem;"><?php echo e($job['title']); ?></h2>
                <p style="color: var(--text-secondary); margin: 0;"><?php echo e($job['short_description']); ?></p>
              </div>

              <!-- Metadata Grid -->
              <div class="spec-meta-grid">
                <div class="spec-meta-item">
                  <div>Department</div>
                  <div><?php echo e($job['department']); ?></div>
                </div>
                <div class="spec-meta-item">
                  <div>Location</div>
                  <div><?php echo e($job['location']); ?></div>
                </div>
                <div class="spec-meta-item">
                  <div>Employment Type</div>
                  <div><?php echo e($job['employment_type']); ?></div>
                </div>
                <div class="spec-meta-item">
                  <div>Experience</div>
                  <div><?php echo e($job['experience_req']); ?></div>
                </div>
              </div>

              <!-- Responsibilities -->
              <div class="section-list-box">
                <h3>Key Responsibilities:</h3>
                <ul class="bullet-list">
                  <?php 
                  $respLines = explode("\n", $job['responsibilities']);
                  foreach ($respLines as $line):
                    $clean = trim(ltrim($line, "•-*\t "));
                    if (!empty($clean)):
                  ?>
                    <li><span><?php echo e($clean); ?></span></li>
                  <?php endif; endforeach; ?>
                </ul>
              </div>

              <!-- Required Skills -->
              <div class="section-list-box">
                <h3>Required Skills &amp; Qualifications:</h3>
                <ul class="bullet-list">
                  <?php 
                  $skillLines = explode("\n", $job['required_skills']);
                  foreach ($skillLines as $line):
                    $clean = trim(ltrim($line, "•-*\t "));
                    if (!empty($clean)):
                  ?>
                    <li><span><?php echo e($clean); ?></span></li>
                  <?php endif; endforeach; ?>
                </ul>
              </div>

              <!-- Benefits -->
              <?php if (!empty($job['benefits'])): ?>
                <div class="section-list-box">
                  <h3>What We Offer:</h3>
                  <ul class="bullet-list">
                    <?php 
                    $benefitLines = explode("\n", $job['benefits']);
                    foreach ($benefitLines as $line):
                      $clean = trim(ltrim($line, "•-*\t "));
                      if (!empty($clean)):
                    ?>
                      <li><span><?php echo e($clean); ?></span></li>
                    <?php endif; endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

            <?php else: ?>
              <!-- General Profile Information -->
              <div class="spec-header">
                <h2 style="color: var(--color-navy-dark); font-size: 1.65rem; margin-bottom: 0.5rem;">Join the DATNIX Operational Talent Pool</h2>
                <p style="color: var(--text-secondary); margin: 0;">Submit your profile for upcoming data processing, web research, catalog curation, and back-office projects.</p>
              </div>

              <div style="font-size: 0.95rem; line-height: 1.7; color: var(--text-secondary); margin-bottom: 1.5rem;">
                <p>DATNIX SOLUTIONS is always looking for meticulous, process-oriented operators, secondary QA checkers, and administrative support executives.</p>
                <p>When our enterprise clients scale operational volumes, we review our pre-screened talent database for immediate placement. Fill out the application form on the right and upload your current resume.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Column: Candidate Application Form (MODULE 5) -->
        <div id="apply">
          <div class="form-card" style="padding: 2.25rem;">
            <h3 class="form-title" style="font-size: 1.35rem;">Candidate Application</h3>
            <p class="form-desc" style="font-size: 0.88rem; margin-bottom: 1.5rem;">
              Complete the form below and upload your resume in PDF or DOC/DOCX format (max. 5MB).
            </p>

            <form id="applicationForm" action="api/apply.php" method="POST" enctype="multipart/form-data" novalidate>
              <!-- Hidden Honeypot -->
              <div style="position: absolute; left: -9999px; top: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                <input type="text" name="honeypot" tabindex="-1" autocomplete="off">
              </div>

              <div class="form-group">
                <label for="appFullName" class="form-label">Full Name <span style="color: #EF4444;">*</span></label>
                <input type="text" id="appFullName" name="fullName" class="form-control" placeholder="e.g. Rahul Sharma" required>
              </div>

              <div class="form-group">
                <label for="appEmail" class="form-label">Email Address <span style="color: #EF4444;">*</span></label>
                <input type="email" id="appEmail" name="email" class="form-control" placeholder="name@email.com" required>
              </div>

              <div class="form-group">
                <label for="appPhone" class="form-label">Mobile Number <span style="color: #EF4444;">*</span></label>
                <input type="tel" id="appPhone" name="phone" class="form-control" placeholder="+91 73870 10393" required>
              </div>

              <div class="form-group">
                <label for="appJobId" class="form-label">Position Applied For <span style="color: #EF4444;">*</span></label>
                <select id="appJobId" name="jobId" class="form-control" required>
                  <?php if ($job): ?>
                    <option value="<?php echo (int)$job['id']; ?>" selected><?php echo e($job['title']); ?> (<?php echo e($job['department']); ?>)</option>
                  <?php else: ?>
                    <option value="0">General Talent Pool (All Operations)</option>
                  <?php endif; ?>
                  <?php foreach ($allActiveJobs as $aj): ?>
                    <?php if (!$job || $aj['id'] !== $job['id']): ?>
                      <option value="<?php echo (int)$aj['id']; ?>"><?php echo e($aj['title']); ?></option>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </select>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                  <label for="appExp" class="form-label">Total Experience <span style="color: #EF4444;">*</span></label>
                  <input type="text" id="appExp" name="experience" class="form-control" placeholder="e.g. 2.5 Years / Fresher" required>
                </div>
                <div class="form-group">
                  <label for="appNotice" class="form-label">Notice Period <span style="color: #EF4444;">*</span></label>
                  <input type="text" id="appNotice" name="noticePeriod" class="form-control" placeholder="e.g. Immediate / 15 Days" required>
                </div>
              </div>

              <div class="form-group">
                <label for="appLocation" class="form-label">Current City &amp; State <span style="color: #EF4444;">*</span></label>
                <input type="text" id="appLocation" name="location" class="form-control" placeholder="e.g. Pune, Maharashtra / Jaipur, Rajasthan" required>
              </div>

              <div class="form-group">
                <label for="appCandidateAddress" class="form-label">Full Residential Address <span style="color: #EF4444;">*</span></label>
                <textarea id="appCandidateAddress" name="candidateAddress" class="form-control" style="min-height: 55px;" placeholder="House/Flat No., Street, Area, City, State, PIN Code (Required for WFH verification &amp; official communication)" required></textarea>
              </div>

              <!-- Resume Upload (Optional - PDF / DOC / DOCX max 5MB) -->
              <div class="form-group">
                <label for="appResume" class="form-label">Resume / CV Document <span style="font-weight: 400; color: var(--text-muted); font-size: 0.85rem;">(Optional)</span></label>
                <input type="file" id="appResume" name="resume" class="form-control" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" style="padding: 0.4rem;">
                <div style="font-size: 11.5px; color: var(--text-muted); margin-top: 3px;">Optional: Upload PDF, DOC, or DOCX (max 5MB) or apply directly without a file.</div>
              </div>

              <div class="form-group">
                <label for="appAdditional" class="form-label">Additional Information (Optional)</label>
                <textarea id="appAdditional" name="additionalInfo" class="form-control" style="min-height: 70px;" placeholder="Typing speed (WPM), software familiarity, or shift preferences..."></textarea>
              </div>

              <!-- Privacy Consent -->
              <div style="margin-bottom: 1.25rem; display: flex; align-items: flex-start; gap: 0.5rem; font-size: 12px; color: var(--text-muted);">
                <input type="checkbox" id="privacyConsent" name="privacyConsent" value="1" required style="margin-top: 2px;">
                <label for="privacyConsent" style="cursor: pointer; line-height: 1.4;">
                  I agree that DATNIX SOLUTIONS may store and process my application details for recruitment and operational selection purposes.
                </label>
              </div>

              <button type="submit" id="submitAppBtn" class="btn btn-primary btn-lg" style="width: 100%;">
                <span>Submit Job Application</span>
              </button>

              <div id="appStatus" class="form-status" style="margin-top: 1rem;"></div>
            </form>
          </div>
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
        </div>
        <div class="footer-col">
          <h4 class="footer-heading">Navigation</h4>
          <ul class="footer-links">
            <li><a href="index.html">Home</a></li>
            <li><a href="about.html">About Us</a></li>
            <li><a href="services.html">Services</a></li>
            <li><a href="careers.php">Careers</a></li>
            <li><a href="contact.html">Contact</a></li>
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
  <script>
    // AJAX Application Submission with Progress and Validation
    document.addEventListener('DOMContentLoaded', () => {
      const appForm = document.getElementById('applicationForm');
      const appStatus = document.getElementById('appStatus');
      const submitBtn = document.getElementById('submitAppBtn');

      function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }

      if (!appForm) return;

      // Check URL parameters for successful native form submission
      try {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('application_status') === 'success') {
          const refNo = urlParams.get('ref') || 'LOGGED';
          if (appStatus) {
            appStatus.className = 'form-status success';
            appStatus.innerHTML = `
              <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.35rem; color: #065F46;">
                Application Received Successfully!
              </div>
              <p style="margin: 0; font-size: 0.92rem; color: #166534; line-height: 1.5;">
                Thank you! Your job application has been logged under Reference ID: <strong>${escapeHtml(refNo)}</strong>. Our human resources and operational pod leads will review your credentials and reach out if your profile matches current openings.
              </p>
            `;
            appStatus.style.display = 'block';
          }
          if (submitBtn) submitBtn.style.display = 'none';
          appForm.reset();
        } else if (urlParams.get('application_status') === 'error') {
          const errMsg = urlParams.get('msg') || 'An error occurred during submission.';
          if (appStatus) {
            appStatus.className = 'form-status error';
            appStatus.innerHTML = `<strong>Submission Error:</strong> ${escapeHtml(errMsg)}`;
            appStatus.style.display = 'block';
          }
        }
      } catch (e) {}

      appForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const fileInput = document.getElementById('appResume');
        if (fileInput.files && fileInput.files.length > 0) {
          const file = fileInput.files[0];
          if (file.size > 5242880) { // 5MB
            appStatus.className = 'form-status error';
            appStatus.innerHTML = '<strong>File Too Large:</strong> The selected resume exceeds the 5MB file limit.';
            appStatus.style.display = 'block';
            return;
          }
        }

        const consent = document.getElementById('privacyConsent');
        if (!consent || !consent.checked) {
          appStatus.className = 'form-status error';
          appStatus.innerHTML = '<strong>Consent Required:</strong> Please agree to the privacy statement to submit your application.';
          appStatus.style.display = 'block';
          return;
        }

        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = 'Uploading &amp; Submitting Application...';
        appStatus.className = 'form-status loading';
        appStatus.textContent = 'Uploading your application and resume...';
        appStatus.style.display = 'block';

        const formData = new FormData(appForm);

        const abortCtrl = new AbortController();
        const abortTimer = setTimeout(() => abortCtrl.abort(), 25000);

        try {
          const resp = await fetch('api/apply.php', {
            method: 'POST',
            body: formData,
            signal: abortCtrl.signal
          });
          clearTimeout(abortTimer);

          const result = await resp.json().catch(() => null);

          if (resp.ok && result && result.success) {
            appForm.reset();
            appStatus.className = 'form-status success';
            appStatus.innerHTML = `
              <div style="font-weight: 700; font-size: 1.05rem; margin-bottom: 0.35rem; color: #065F46;">
                Application Received Successfully!
              </div>
              <p style="margin: 0; font-size: 0.92rem; color: #166534; line-height: 1.5;">
                Thank you! Your job application has been logged under Reference ID: <strong>${escapeHtml(result.referenceNo || 'LOGGED')}</strong>. Our human resources and operational pod leads will review your credentials and reach out if your profile matches current openings.
              </p>
            `;
            submitBtn.style.display = 'none';
          } else if (!result && resp.status !== 429) {
            console.warn('Non-JSON response received, submitting application natively via browser...');
            appForm.submit();
            return;
          } else {
            const errText = result && result.error ? result.error : 'An error occurred while uploading your application. Please try again.';
            appStatus.className = 'form-status error';
            appStatus.innerHTML = `<strong>Submission Error:</strong> ${escapeHtml(errText)}`;
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
          }
        } catch (err) {
          console.warn('AJAX application upload failed, submitting natively via browser...', err);
          appForm.submit();
          return;
        }
      });
    });
  </script>
</body>
</html>
