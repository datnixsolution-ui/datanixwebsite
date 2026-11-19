<?php
/**
 * DATNIX SOLUTIONS - Ultra-Fast Public Jobs API
 * Endpoint: /api/jobs.php
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=600'); // Cache for 10 mins

$staticJobs = [
    [
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
    [
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
    [
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
    [
        'id' => 4,
        'title' => 'Excel & Spreadsheet Data Entry Associate',
        'slug' => 'excel-spreadsheet-data-entry-associate',
        'department' => 'Back-Office Operations',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Structured spreadsheet data entry, formula applications, row/column consolidation, deduplication, and database import preparation in MS Excel.',
        'responsibilities' => "• Input and structure high-volume raw business data into standardized Excel and Google Sheets templates.\n• Apply spreadsheet formulas (VLOOKUP, SUMIFS, CONCATENATE, IF) for data validation.\n• Cleanse duplicate rows, standardize dates/currencies, and repair corrupted formatting.\n• Export finalized spreadsheets into clean CSV and database-ready formats.",
        'required_skills' => "• Strong practical experience in Microsoft Excel (Formulas, Pivot Tables, Conditional Formatting).\n• Fast 10-key numeric pad typing speed.\n• Disciplined attention to formula logic and data integrity.\n• Familiarity with Google Sheets and cloud spreadsheets.",
        'benefits' => "• Advanced Excel and data analytics coaching.\n• Flexible work hours with milestone-based completion.\n• Performance-based increments and annual bonus."
    ],
    [
        'id' => 5,
        'title' => 'E-Commerce Product Data Entry Specialist',
        'slug' => 'ecommerce-product-data-entry-specialist',
        'department' => 'E-Commerce Data',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Product catalog data entry, SKU specifications keying, pricing updates, image attribution, and category mapping for global retail portals.',
        'responsibilities' => "• Key in product titles, dimensions, materials, and bullet features into e-commerce spreadsheets.\n• Batch upload product feeds to e-commerce storefronts (Amazon, Shopify, Flipkart, WooCommerce).\n• Standardize brand specifications, color codes, and size options according to taxonomy guidelines.\n• Conduct periodic inventory and price updates from vendor catalogs.",
        'required_skills' => "• Prior experience in e-commerce listing, SKU entry, or catalog data management.\n• Familiarity with Amazon Seller Central, Shopify admin, or Excel product flat files.\n• Fast typing speed and strong web navigation skills.\n• Detail-oriented approach to image-to-SKU matching.",
        'benefits' => "• Exposure to international e-commerce brand projects.\n• Monthly performance incentives on catalog milestones.\n• Complete Work From Home autonomy."
    ],
    [
        'id' => 6,
        'title' => 'Data Entry Quality Control (QC) & Verification Auditor',
        'slug' => 'data-entry-qc-verification-auditor',
        'department' => 'Quality Assurance',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '2 - 4 Years',
        'short_description' => '100% and AQL sampling verification of completed data entry batches against original source scans, defect logging, and accuracy certification.',
        'responsibilities' => "• Perform field-by-field verification of completed data batches against source document scans.\n• Log character substitutions, omission errors, and formatting inconsistencies in defect tracking sheets.\n• Provide constructive feedback to entry operators to improve first-pass accuracy yields.\n• Issue official batch quality certification before data files are dispatched to clients.",
        'required_skills' => "• Deep understanding of data quality assurance methodologies and AQL sampling.\n• Exceptional eye for detail and high concentration stamina.\n• Advanced MS Excel skills for discrepancy reporting.\n• Minimum 2 years in quality auditing or senior data entry roles.",
        'benefits' => "• Senior position with leadership responsibilities.\n• Competitive compensation with quality-based performance incentives.\n• Professional training in Six Sigma quality concepts."
    ],
    [
        'id' => 7,
        'title' => 'Document Scanning & Indexing Data Entry Operator',
        'slug' => 'document-scanning-indexing-data-entry-operator',
        'department' => 'Document Processing',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '0 - 2 Years (Freshers Welcome)',
        'short_description' => 'Digital document prep, indexing, folder naming, and metadata data entry for legal and corporate archives.',
        'responsibilities' => "• Download and review scanned digital archives for legibility and completeness.\n• Enter metadata index fields (file number, customer name, date, classification code).\n• Tag folder structures and verify OCR text streams against original scans.\n• Maintain structured batch tracking sheets.",
        'required_skills' => "• Basic computer operating skills and keyboard typing.\n• Reliable PC and good broadband connection at home.\n• Reliable, punctual, and disciplined approach to daily quotas.\n• No prior experience necessary; complete on-the-job training provided.",
        'benefits' => "• 100% Work From Home (WFH) schedule.\n• Training on commercial digital archiving and indexing tools.\n• Growth opportunities into supervisory positions."
    ],
    [
        'id' => 8,
        'title' => 'Back-Office Data Entry & Processing Executive',
        'slug' => 'back-office-data-entry-executive',
        'department' => 'Back-Office Support',
        'location' => 'Work From Home (WFH) / Remote (All India)',
        'employment_type' => 'Full-Time / WFH',
        'experience_req' => '1 - 3 Years',
        'short_description' => 'Routine back-office data processing, invoice & purchase order entry, claims data keying, and daily operational status reporting.',
        'responsibilities' => "• Process and enter incoming supplier invoices, claims, and delivery notes into ERP systems.\n• Cross-verify invoice line items against purchase orders and approved rate sheets.\n• Update internal records, address books, and customer transaction logs.\n• Prepare daily shift activity summaries and transaction logs for management.",
        'required_skills' => "• Prior experience in back-office operations, accounting data entry, or commercial BPO.\n• Good numerical reasoning and typing accuracy.\n• Basic knowledge of accounting software or MS Excel.\n• Strong team collaboration and communication skills via Slack / WhatsApp.",
        'benefits' => "• Stable remote corporate environment with regular business hours.\n• Performance-linked quarterly rewards.\n• Work from anywhere in India."
    ]
];

// Attempt database connection with strict 2-second timeout
try {
    if (file_exists(__DIR__ . '/../config/database.php')) {
        require_once __DIR__ . '/../config/database.php';
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('
            SELECT id, title, slug, department, location, employment_type, experience_req, short_description, responsibilities, required_skills, benefits, created_at 
            FROM jobs 
            WHERE status = "Active" AND is_published = 1 
            ORDER BY id ASC
        ');
        $stmt->execute();
        $dbJobs = $stmt->fetchAll();

        if (!empty($dbJobs)) {
            echo json_encode([
                'success' => true,
                'count'   => count($dbJobs),
                'jobs'    => $dbJobs
            ]);
            exit;
        }
    }
} catch (Throwable $e) {
    // Database connection skipped or failed; instant fallback to staticJobs
}

// Return static jobs with zero latency
echo json_encode([
    'success' => true,
    'count'   => count($staticJobs),
    'jobs'    => $staticJobs
]);
