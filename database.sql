-- =============================================================================
-- DATNIX SOLUTIONS - ENTERPRISE MYSQL DATABASE SCHEMA
-- Version: 1.1.0 (Safe Non-Destructive Fresh Install & Migration Schema)
-- Charset: utf8mb4 / utf8mb4_unicode_ci
-- Tagline: DATA. PROCESS. PERFORMANCE.
--
-- SAFETY NOTICE:
-- This script uses CREATE TABLE IF NOT EXISTS throughout and contains NO destructive
-- DROP TABLE statements. It is safe for fresh installs and non-destructive migrations.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- 1. Table: admins
-- Purpose: Admin users with secure bcrypt hashes, roles, and lockout counters
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('superadmin', 'admin', 'editor') NOT NULL DEFAULT 'admin',
  `status` ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
  `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` DATETIME NULL DEFAULT NULL,
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_username` (`username`),
  UNIQUE KEY `uk_admin_email` (`email`),
  KEY `idx_admin_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Table: enquiries
-- Purpose: Customer enquiries submitted from website forms
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `enquiries` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` VARCHAR(32) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(120) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `service` VARCHAR(100) NOT NULL,
  `estimated_volume` VARCHAR(100) DEFAULT NULL,
  `project_details` TEXT NOT NULL,
  `status` ENUM('New', 'Contacted', 'In Discussion', 'Quotation Sent', 'Converted', 'Closed') NOT NULL DEFAULT 'New',
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_enquiry_ref` (`reference_no`),
  KEY `idx_enquiry_status` (`status`),
  KEY `idx_enquiry_created` (`created_at`),
  KEY `idx_enquiry_email` (`email`),
  KEY `idx_enquiry_service` (`service`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Table: enquiry_notes
-- Purpose: Internal admin notes and progress updates on enquiries
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `enquiry_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `enquiry_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_enquiry_note_enquiry` (`enquiry_id`),
  KEY `idx_enquiry_note_admin` (`admin_id`),
  CONSTRAINT `fk_enquiry_notes_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enquiry_notes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Table: jobs
-- Purpose: Job openings published on public careers page
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(160) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `location` VARCHAR(100) NOT NULL DEFAULT 'Ahmedabad, Gujarat (Satkar Complex - 380006)',
  `employment_type` ENUM('Full-Time', 'Part-Time', 'Contract', 'Internship') NOT NULL DEFAULT 'Full-Time',
  `experience_req` VARCHAR(100) NOT NULL,
  `short_description` VARCHAR(350) NOT NULL,
  `responsibilities` TEXT NOT NULL,
  `required_skills` TEXT NOT NULL,
  `benefits` TEXT DEFAULT NULL,
  `status` ENUM('Active', 'Closed', 'Draft') NOT NULL DEFAULT 'Draft',
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_job_slug` (`slug`),
  KEY `idx_job_status_pub` (`status`, `is_published`),
  KEY `idx_job_department` (`department`),
  KEY `idx_job_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Table: applications
-- Purpose: Candidate applications submitted for job openings (or general talent pool)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` VARCHAR(32) NOT NULL,
  `job_id` INT UNSIGNED NULL DEFAULT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `experience_years` VARCHAR(50) NOT NULL,
  `current_location` VARCHAR(100) NOT NULL,
  `notice_period` VARCHAR(50) NOT NULL,
  `additional_info` TEXT DEFAULT NULL,
  `status` ENUM('New', 'Under Review', 'Shortlisted', 'Interview Scheduled', 'Selected', 'Rejected') NOT NULL DEFAULT 'New',
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_app_ref` (`reference_no`),
  KEY `idx_app_job` (`job_id`),
  KEY `idx_app_status` (`status`),
  KEY `idx_app_email` (`email`),
  KEY `idx_app_created` (`created_at`),
  CONSTRAINT `fk_applications_job` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. Table: application_files
-- Purpose: Uploaded candidate resumes and supporting documents
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_stored` (`stored_filename`),
  KEY `idx_file_app` (`application_id`),
  CONSTRAINT `fk_application_files_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. Table: application_notes
-- Purpose: Internal admin review comments on candidate profiles
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `application_notes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `note` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_app_note_app` (`application_id`),
  KEY `idx_app_note_admin` (`admin_id`),
  CONSTRAINT `fk_app_notes_app` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_notes_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. Table: activity_logs
-- Purpose: Comprehensive audit trail for all admin actions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` INT UNSIGNED NULL DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` INT UNSIGNED NULL DEFAULT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_action` (`action`),
  KEY `idx_log_entity` (`entity_type`, `entity_id`),
  KEY `idx_log_created` (`created_at`),
  KEY `idx_log_admin` (`admin_id`),
  CONSTRAINT `fk_activity_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. Table: login_attempts
-- Purpose: IP-based login rate limiting & brute-force defense
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempt_lookup` (`ip_address`, `username`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sample Job Vacancies & Active Openings (100% Data Entry & Back-Office Focus)
-- Location: 20, Satkar Complex, Chimanlal Girdharlal Rd (C.G. Road), Ahmedabad - 380006, Gujarat
-- -----------------------------------------------------------------------------

-- Clean up any previous non-data-entry openings
DELETE FROM `jobs` WHERE `slug` IN ('ocr-digital-publishing-epub-xml', 'arabic-data-conversion-specialist', 'senior-data-entry-specialist-all-languages');

-- 1. Data Entry Operator (Fresher & Experienced)
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Data Entry Operator (Fresher & Experienced)',
  'data-entry-operator-fresher-experienced',
  'Data Entry Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '0 - 2 Years (Freshers Welcome)',
  'Accurate and high-speed alphanumeric data entry, form keying, master record updating, and spreadsheet data entry across client projects.',
  '• Key alphanumeric data from invoices, paper receipts, and scanned records into spreadsheets.\n• Achieve daily target output quotas while maintaining 98%+ character accuracy.\n• Perform initial self-audit checks against source documents prior to batch submission.\n• Comply strictly with enterprise client confidentiality, clean-desk, and NDA protocols.',
  '• Typing speed of 30+ WPM with high accuracy.\n• Basic proficiency in Microsoft Excel, Google Sheets, and keyboard numerical entry.\n• High attention to detail and error spotting.\n• Freshers with good typing skills and basic computer literacy are encouraged to apply.',
  '• Fixed day shifts with predictable working hours.\n• Comprehensive typing and tools training provided on onboarding.\n• Performance-linked monthly incentives and annual appraisals.\n• Prime central Ahmedabad office at Satkar Complex, C.G. Road.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'data-entry-operator-fresher-experienced');

-- 2. Senior Data Entry Specialist (High-Volume & Speed)
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Senior Data Entry Specialist (High-Volume & Speed)',
  'senior-data-entry-specialist',
  'Data Entry Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '2 - 4 Years',
  'High-volume, rapid-turnaround batch data entry, complex tabulations, dual-screen comparison, and specialized customer database updating.',
  '• Transcribe complex alphanumeric records from multi-format source files into enterprise systems.\n• Execute dual-screen verification checks to ensure zero-defect output conformity (99.8% SLA).\n• Assist team supervisors in tracking hourly team productivity metrics and turnaround SLAs.\n• Flag ambiguous source records, formatting anomalies, and validation errors to project managers.',
  '• Proven typing speed of 45+ WPM with 99%+ accuracy.\n• High proficiency in 10-key numeric keypad, keyboard shortcuts, and Excel functions.\n• Minimum 2 years of prior experience in international data entry / BPO operations.\n• Proven ability to handle high-volume repetitive tasks with steady focus.',
  '• Competitive salary package + high-performance bonuses.\n• Fast-track leadership progression to Team Lead / Quality Lead.\n• Modern ergonomic workstations with dual-monitor setups.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'senior-data-entry-specialist');

-- 3. Multilingual Data Entry Executive (English & Regional)
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Multilingual Data Entry Executive (English & Regional)',
  'multilingual-data-entry-executive',
  'Multilingual Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '1 - 3 Years',
  'Data entry and verification for multi-language documents, bilingual records, and regional Indian language datasets with Unicode standards.',
  '• Transcribe customer records, public registries, and surveys into English and regional Unicode text.\n• Verify transliteration accuracy and spelling consistency for names, addresses, and local terminology.\n• Key in alphanumeric IDs, phone numbers, and financial figures from multilingual forms.\n• Perform cross-check validation between English and vernacular language fields.',
  '• Strong written English plus fluency in Hindi, Gujarati, or other regional languages.\n• Experience with Unicode keyboard layouts and bilingual data entry software.\n• Typing speed of 35+ WPM with meticulous character fidelity.\n• Prior experience in census, voter list, or multilingual banking data entry is a plus.',
  '• Multilingual skill allowance.\n• Structured work hours and supportive team environment.\n• Rapid internal growth into Multilingual QA Lead.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'multilingual-data-entry-executive');

-- 4. Excel & Spreadsheet Data Entry Associate
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Excel & Spreadsheet Data Entry Associate',
  'excel-spreadsheet-data-entry-associate',
  'Back-Office Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '1 - 3 Years',
  'Structured spreadsheet data entry, formula applications, row/column consolidation, deduplication, and database import preparation in MS Excel.',
  '• Input and structure high-volume raw business data into standardized Excel and Google Sheets templates.\n• Apply spreadsheet formulas (VLOOKUP, SUMIFS, CONCATENATE, IF) for data validation.\n• Cleanse duplicate rows, standardize dates/currencies, and repair corrupted formatting.\n• Export finalized spreadsheets into clean CSV and database-ready formats.',
  '• Strong practical experience in Microsoft Excel (Formulas, Pivot Tables, Conditional Formatting).\n• Fast 10-key numeric pad typing speed.\n• Disciplined attention to formula logic and data integrity.\n• Familiarity with Google Sheets and cloud spreadsheets.',
  '• Advanced Excel and data analytics coaching.\n• Regular day shifts with Saturday afternoon wrap-ups.\n• Performance-based increments and annual bonus.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'excel-spreadsheet-data-entry-associate');

-- 5. E-Commerce Product Data Entry Specialist
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'E-Commerce Product Data Entry Specialist',
  'ecommerce-product-data-entry-specialist',
  'E-Commerce Data',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '1 - 3 Years',
  'Product catalog data entry, SKU specifications keying, pricing updates, image attribution, and category mapping for global retail portals.',
  '• Key in product titles, dimensions, materials, and bullet features into e-commerce spreadsheets.\n• Batch upload product feeds to e-commerce storefronts (Amazon, Shopify, Flipkart, WooCommerce).\n• Standardize brand specifications, color codes, and size options according to taxonomy guidelines.\n• Conduct periodic inventory and price updates from vendor catalogs.',
  '• Prior experience in e-commerce listing, SKU entry, or catalog data management.\n• Familiarity with Amazon Seller Central, Shopify admin, or Excel product flat files.\n• Fast typing speed and strong web navigation skills.\n• Detail-oriented approach to image-to-SKU matching.',
  '• Exposure to international e-commerce brand projects.\n• Monthly performance incentives on catalog milestones.\n• Modern corporate environment at C.G. Road, Ahmedabad.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'ecommerce-product-data-entry-specialist');

-- 6. Data Entry Quality Control (QC) & Verification Auditor
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Data Entry Quality Control (QC) & Verification Auditor',
  'data-entry-qc-verification-auditor',
  'Quality Assurance',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '2 - 4 Years',
  '100% and AQL sampling verification of completed data entry batches against original source scans, defect logging, and accuracy certification.',
  '• Perform field-by-field verification of completed data batches against source document scans.\n• Log character substitutions, omission errors, and formatting inconsistencies in defect tracking sheets.\n• Provide constructive feedback to entry operators to improve first-pass accuracy yields.\n• Issue official batch quality certification before data files are dispatched to clients.',
  '• Deep understanding of data quality assurance methodologies and AQL sampling.\n• Exceptional eye for detail and high concentration stamina.\n• Advanced MS Excel skills for discrepancy reporting.\n• Minimum 2 years in quality auditing or senior data entry roles.',
  '• Senior position with leadership responsibilities.\n• Competitive compensation with quality-based performance incentives.\n• Professional training in Six Sigma quality concepts.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'data-entry-qc-verification-auditor');

-- 7. Document Scanning & Indexing Data Entry Operator
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Document Scanning & Indexing Data Entry Operator',
  'document-scanning-indexing-data-entry-operator',
  'Document Processing',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '0 - 2 Years (Freshers Welcome)',
  'Physical document prep, high-speed rotary scanning, folder naming, indexing, and metadata data entry for legal and corporate archives.',
  '• Prepare physical files for scanning by removing staples, clips, and smoothing folds.\n• Operate production document scanners and review image quality (de-skew, contrast).\n• Enter metadata index fields (file number, customer name, date, classification code).\n• Re-assemble scanned physical files in pristine order for archiving.',
  '• High degree of physical dexterity, organization, and care with original documents.\n• Basic computer operating skills and keyboard typing.\n• Reliable, punctual, and disciplined approach to daily quotas.\n• No prior experience necessary; complete on-the-job training provided.',
  '• Fixed day shifts in a modern, climate-controlled document center.\n• Training on commercial scanning hardware and indexing tools.\n• Growth opportunities into supervisory positions.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'document-scanning-indexing-data-entry-operator');

-- 8. Back-Office Data Entry & Processing Executive
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Back-Office Data Entry & Processing Executive',
  'back-office-data-entry-executive',
  'Back-Office Support',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '1 - 3 Years',
  'Routine back-office data processing, invoice & purchase order entry, claims data keying, and daily operational status reporting.',
  '• Process and enter incoming supplier invoices, claims, and delivery notes into ERP systems.\n• Cross-verify invoice line items against purchase orders and approved rate sheets.\n• Update internal records, address books, and customer transaction logs.\n• Prepare daily shift activity summaries and transaction logs for management.',
  '• Prior experience in back-office operations, accounting data entry, or commercial BPO.\n• Good numerical reasoning and typing accuracy.\n• Basic knowledge of accounting software or MS Excel.\n• Strong team collaboration and communication skills.',
  '• Stable corporate environment with regular business hours.\n• Performance-linked quarterly rewards.\n• Centrally located Ahmedabad office at Satkar Complex.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'back-office-data-entry-executive');

SET FOREIGN_KEY_CHECKS = 1;
