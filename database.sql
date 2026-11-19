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
-- Sample Job Vacancies & Active Openings
-- Location: 20, Satkar Complex, Chimanlal Girdharlal Rd (C.G. Road), Ahmedabad - 380006, Gujarat
-- -----------------------------------------------------------------------------
INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Senior Data Entry Specialist (All Languages & High-Volume)',
  'senior-data-entry-specialist-all-languages',
  'Data Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '2 - 4 Years',
  'High-volume, multi-language data entry, conversion, character encoding verification (UTF-8), and validation across enterprise client workflows.',
  '• Transcribe and digitize alphanumeric records from multilingual documents into client databases.\n• Execute dual-pass verification checks to ensure zero-defect output conformity (99.8% SLA).\n• Maintain strict adherence to client Standard Operating Procedures (SOPs).\n• Flag ambiguous source records, character encoding issues, and anomalies to team leads.',
  '• Typing speed of 45+ WPM with 98%+ accuracy.\n• Proficiency in MS Excel, Google Sheets, Unicode character sets, and data entry software.\n• Strong attention to detail and error spotting.\n• Prior experience in BPO / international data operations.',
  '• Competitive salary + performance incentives.\n• Comprehensive training on international data operations.\n• Central location on C.G. Road, Ahmedabad (Satkar Complex).',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'senior-data-entry-specialist-all-languages');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Arabic Data Conversion & Digitization Specialist',
  'arabic-data-conversion-specialist',
  'Multilingual & Localization',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '2 - 5 Years',
  'Specialized Arabic language OCR, bidirectional (RTL) text processing, Arabic manuscripts/deed conversion into Word, Excel, XML, and digital formats.',
  '• Conduct high-precision Arabic OCR processing, character alignment, and proofreading.\n• Convert Arabic legal deeds, government archives, and literature into structured XML/Word.\n• Validate Right-to-Left (RTL) typography, font encoding, and diacritical marks.\n• Collaborate with quality auditors on Arabic-to-English catalog matching.',
  '• Strong reading and writing proficiency in Modern Standard Arabic (MSA).\n• Experience with Arabic OCR tools, Abbyy FineReader Arabic, and bilingual indexing.\n• High keyboard typing speed in Arabic and English.\n• Prior translation or Middle East BPO data processing experience preferred.',
  '• Premium compensation for bilingual Arabic specialization.\n• Opportunities for rapid leadership in international client projects.\n• Modern corporate infrastructure at C.G. Road, Ahmedabad.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'arabic-data-conversion-specialist');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'OCR & Digital Publishing Associate (ePUB / XML / HTML5)',
  'ocr-digital-publishing-epub-xml',
  'Data Conversion',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '1 - 3 Years',
  'Book, journal, and paper scanning, OCR layout retention, and structured conversion into digital ePUB, XML (JATS/TEI), and HTML5 formats.',
  '• Perform high-speed OCR conversion from paper archives, microfilms, and non-searchable PDFs.\n• Structure and tag documents into valid XML (JATS, DocBook, TEI) with DTD validation.\n• Format digital books and journals into reflowable and fixed-layout ePUB/MOBI formats.\n• Clean up equations (LaTeX / MathML), complex tables, and footnotes.',
  '• Experience with OCR software (Abbyy FineReader, OmniPage) and text editors.\n• Knowledge of XML tagging, DTD/XSD schemas, and ePUB 3.0 standards.\n• Basic understanding of HTML/CSS for digital publication styling.\n• Meticulous attention to typography and structural hierarchy.',
  '• Specialized digital publishing skillset training.\n• Structured work shifts and performance rewards.\n• Prime office location in Ahmedabad (C.G. Road).',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'ocr-digital-publishing-epub-xml');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Multilingual Data Entry & Verification Executive',
  'multilingual-data-entry-executive',
  'International Operations',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '1 - 3 Years',
  'Data entry and cross-verification across European, Middle Eastern, and Indian language records with strict UTF-8 Unicode standards.',
  '• Process non-English catalogs, customer forms, invoices, and legal records.\n• Maintain strict accuracy standards across diverse character sets and alphabets.\n• Cross-verify fields against master business registers.\n• Work directly under SOP guidelines with 99.8% quality benchmarks.',
  '• Multi-language familiarity (English, Hindi, Gujarati, or foreign languages).\n• Accurate data keying speed (40+ WPM).\n• Excellent computer skills in Excel, Google Sheets, and CRM portals.\n• High focus on data confidentiality and NDA compliance.',
  '• Full-time permanent career opportunity.\n• Friendly, supportive team environment in Ahmedabad.\n• Annual performance bonuses and health coverage.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'multilingual-data-entry-executive');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Quality Assurance Auditor (Data & BPO)',
  'qa-auditor-data-bpo',
  'Quality Assurance',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '3 - 5 Years',
  'Independent secondary audits, statistical sampling, and error registers across all client data deliverables.',
  '• Perform Acceptable Quality Limit (AQL) random and 100% audits on batch deliverables.\n• Maintain defect logs, error taxonomy registers, and quality scorecards.\n• Coach data operators on procedural accuracy and edge-case exceptions.\n• Audit client data handling for strict NDA and security compliance.',
  '• In-depth knowledge of BPO quality inspection methodologies.\n• Advanced Excel skills (VLOOKUP, INDEX/MATCH, Pivot Tables, conditional formatting).\n• Strong analytical problem-solving and documentation skills.\n• Bachelor\'s degree in any discipline.',
  '• Attractive salary commensurate with experience.\n• Leadership opportunities within a rapidly expanding B2B provider.\n• Supportive leadership and structured career advancement.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'qa-auditor-data-bpo');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'E-Commerce Catalog & SKU Enrichment Specialist',
  'ecommerce-catalog-specialist',
  'E-Commerce Data',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '2 - 4 Years',
  'Multi-channel product listings, SKU taxonomy categorization, and digital asset matching for global retail portals.',
  '• Create and enrich product catalog listings across e-commerce portals.\n• Standardize brand attributes, sizing, specifications, and bulleted features.\n• Match high-resolution product imagery to SKUs following naming guidelines.\n• Conduct competitor price and inventory availability checks.',
  '• Hands-on experience with Shopify, Amazon Seller Central, or PIM platforms.\n• Knowledge of SEO-friendly title and description structures.\n• Detail-focused with ability to handle large product catalogs.',
  '• Dynamic e-commerce projects.\n• Performance-linked bonuses.\n• Collaborative team atmosphere.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'ecommerce-catalog-specialist');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Document Digitization & High-Speed Scanning Technician',
  'document-digitization-scanning-technician',
  'Document Management',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
  'Full-Time',
  '1 - 3 Years',
  'Operating production rotary and flatbed scanners, batch indexing, metadata assignment, and secure document archiving.',
  '• Ingest physical binders, legal files, and medical paper records.\n• Operate high-speed commercial scanners with de-skewing and image cleanup.\n• Index scanned files with multi-level metadata and Bates numbering.\n• Manage secure document storage, chain-of-custody, and shredding compliance.',
  '• Prior document scanning or indexing bureau experience.\n• Familiarity with image enhancement software (Kofax, Kodak Capture).\n• Disciplined physical handling of sensitive files.\n• Basic computer proficiency.',
  '• Clean, air-conditioned scanning facility.\n• Fixed day shifts.\n• Growth into supervisory data management roles.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'document-digitization-scanning-technician');

INSERT INTO `jobs` (`title`, `slug`, `department`, `location`, `employment_type`, `experience_req`, `short_description`, `responsibilities`, `required_skills`, `benefits`, `status`, `is_published`) 
SELECT 
  'Back-Office Operations Executive',
  'back-office-operations-executive',
  'Back-Office Support',
  'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006) / Hybrid',
  'Full-Time',
  '1 - 3 Years',
  'Routine administrative workflows, invoice verification, vendor records upkeep, and daily reporting.',
  '• Handle routine back-office transactions, order processing, and master catalog maintenance.\n• Cross-check vendor invoices against purchase orders and rate cards.\n• Maintain structured shift ledgers and activity summaries.\n• Execute scheduled database reconciliations.',
  '• Graduate degree in Commerce, Business Administration, or related field.\n• High operational discipline and process adherence.\n• Strong written and spoken English communication.\n• Familiarity with CRM or ERP databases.',
  '• Fixed day-shift / general operational hours.\n• Structured onboarding and workflow training.\n• Health and wellness coverage.',
  'Active',
  1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `jobs` WHERE `slug` = 'back-office-operations-executive');

SET FOREIGN_KEY_CHECKS = 1;
