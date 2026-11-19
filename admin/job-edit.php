<?php
/**
 * DATNIX SOLUTIONS - Create / Edit Job Opening Form
 * Tagline: DATA. PROCESS. PERFORMANCE.
 */

require_once __DIR__ . '/includes/auth.php';
requireAuth();

$jobId = (int)($_GET['id'] ?? 0);
$isEdit = ($jobId > 0);

$pageTitle = $isEdit ? 'Edit Job Opening' : 'Create Job Opening';
$breadcrumb = $isEdit ? 'Edit Job' : 'New Job';
require_once __DIR__ . '/includes/header.php';

$job = [
    'title'             => '',
    'slug'              => '',
    'department'        => 'Data Operations',
    'location'          => 'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)',
    'employment_type'   => 'Full-Time',
    'experience_req'    => '2 - 4 Years',
    'short_description' => '',
    'responsibilities'  => '',
    'required_skills'   => '',
    'benefits'          => '',
    'status'            => 'Active',
    'is_published'      => 1
];

if ($isEdit && !empty($pdo)) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM jobs WHERE id = :id');
        $stmt->execute([':id' => $jobId]);
        $existing = $stmt->fetch();
        if ($existing) {
            $job = $existing;
        } else {
            setFlashMessage('danger', 'Job opening record not found.');
            header('Location: careers.php');
            exit;
        }
    } catch (Throwable $e) {
        error_log('[JOB FETCH ERROR] ' . $e->getMessage());
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        setFlashMessage('danger', 'CSRF validation failed.');
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $department = trim((string)($_POST['department'] ?? 'Data Operations'));
        $location = trim((string)($_POST['location'] ?? 'Ahmedabad, Gujarat (20, Satkar Complex, C.G. Rd - 380006)'));
        $employmentType = trim((string)($_POST['employment_type'] ?? 'Full-Time'));
        $experienceReq = trim((string)($_POST['experience_req'] ?? ''));
        $shortDesc = trim((string)($_POST['short_description'] ?? ''));
        $responsibilities = trim((string)($_POST['responsibilities'] ?? ''));
        $requiredSkills = trim((string)($_POST['required_skills'] ?? ''));
        $benefits = trim((string)($_POST['benefits'] ?? ''));
        $status = trim((string)($_POST['status'] ?? 'Active'));
        $isPublished = !empty($_POST['is_published']) ? 1 : 0;

        // Validation
        if (empty($title) || empty($experienceReq) || empty($shortDesc) || empty($responsibilities) || empty($requiredSkills)) {
            setFlashMessage('danger', 'Please complete all required fields (marked with *).');
        } else {
            // Generate or preserve slug
            $baseSlug = generateSlug($title);
            $slug = $baseSlug;

            if (!empty($pdo)) {
                try {
                    // Check slug uniqueness
                    $slugStmt = $pdo->prepare('SELECT id FROM jobs WHERE slug = :slug AND id != :id');
                    $slugStmt->execute([':slug' => $slug, ':id' => $jobId]);
                    if ($slugStmt->fetch()) {
                        $slug = $baseSlug . '-' . time();
                    }

                    if ($isEdit) {
                        $updateSql = '
                            UPDATE jobs SET
                                title = :title,
                                slug = :slug,
                                department = :dept,
                                location = :loc,
                                employment_type = :type,
                                experience_req = :exp,
                                short_description = :short,
                                responsibilities = :resp,
                                required_skills = :skills,
                                benefits = :benefits,
                                status = :status,
                                is_published = :pub,
                                updated_at = NOW()
                            WHERE id = :id
                        ';
                        $stmt = $pdo->prepare($updateSql);
                        $stmt->execute([
                            ':title'    => $title,
                            ':slug'     => $slug,
                            ':dept'     => $department,
                            ':loc'      => $location,
                            ':type'     => $employmentType,
                            ':exp'      => $experienceReq,
                            ':short'    => $shortDesc,
                            ':resp'     => $responsibilities,
                            ':skills'   => $requiredSkills,
                            ':benefits' => $benefits,
                            ':status'   => $status,
                            ':pub'      => $isPublished,
                            ':id'       => $jobId
                        ]);

                        logActivity($pdo, $admin['id'], 'job_updated', 'job', $jobId, "Updated job: $title");
                        setFlashMessage('success', "Job opening '$title' updated successfully.");
                    } else {
                        $insertSql = '
                            INSERT INTO jobs (
                                title, slug, department, location, employment_type,
                                experience_req, short_description, responsibilities,
                                required_skills, benefits, status, is_published, created_at, updated_at
                            ) VALUES (
                                :title, :slug, :dept, :loc, :type,
                                :exp, :short, :resp,
                                :skills, :benefits, :status, :pub, NOW(), NOW()
                            )
                        ';
                        $stmt = $pdo->prepare($insertSql);
                        $stmt->execute([
                            ':title'    => $title,
                            ':slug'     => $slug,
                            ':dept'     => $department,
                            ':loc'      => $location,
                            ':type'     => $employmentType,
                            ':exp'      => $experienceReq,
                            ':short'    => $shortDesc,
                            ':resp'     => $responsibilities,
                            ':skills'   => $requiredSkills,
                            ':benefits' => $benefits,
                            ':status'   => $status,
                            ':pub'      => $isPublished
                        ]);

                        $newJobId = (int)$pdo->lastInsertId();
                        logActivity($pdo, $admin['id'], 'job_created', 'job', $newJobId, "Created job: $title");
                        setFlashMessage('success', "New job opening '$title' published successfully.");
                    }

                    header('Location: careers.php');
                    exit;

                } catch (Throwable $e) {
                    error_log('[JOB SAVE ERROR] ' . $e->getMessage());
                    setFlashMessage('danger', 'Failed to save job opening: ' . $e->getMessage());
                }
            }
        }
    }
}
?>

<div class="page-header-row">
  <div>
    <a href="careers.php" style="color: var(--admin-text-muted);">&larr; Back to Job Openings</a>
    <h1 class="page-title" style="margin-top: 0.35rem;"><?php echo $isEdit ? 'Edit Job Opening' : 'Create New Job Opening'; ?></h1>
  </div>
</div>

<div class="admin-card">
  <div class="admin-card-header">
    <h3 class="card-title">Job Specification &amp; Public Display Settings</h3>
    <span style="font-size: 12px; color: var(--admin-text-muted);">* Required fields</span>
  </div>

  <div class="admin-card-body">
    <form method="POST" action="job-edit.php<?php echo $isEdit ? '?id=' . $jobId : ''; ?>">
      <?php echo csrfField(); ?>

      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; margin-bottom: 1.25rem;">
        <div class="form-group">
          <label for="title" class="form-label">Job Title <span style="color: #EF4444;">*</span></label>
          <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Senior Data Entry Specialist" required value="<?php echo e($job['title']); ?>">
        </div>

        <div class="form-group">
          <label for="department" class="form-label">Department <span style="color: #EF4444;">*</span></label>
          <select id="department" name="department" class="form-control">
            <?php 
            $depts = ['Data Operations', 'Quality Assurance', 'Back-Office Support', 'E-Commerce Data', 'Human Resources', 'Administration'];
            foreach ($depts as $d):
            ?>
              <option value="<?php echo e($d); ?>" <?php echo $job['department'] === $d ? 'selected' : ''; ?>><?php echo e($d); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
        <div class="form-group">
          <label for="location" class="form-label">Location</label>
          <input type="text" id="location" name="location" class="form-control" placeholder="e.g. Ahmedabad, Gujarat (Satkar Complex - 380006)" value="<?php echo e($job['location']); ?>">
        </div>

        <div class="form-group">
          <label for="employment_type" class="form-label">Employment Type</label>
          <select id="employment_type" name="employment_type" class="form-control">
            <option value="Full-Time" <?php echo $job['employment_type'] === 'Full-Time' ? 'selected' : ''; ?>>Full-Time</option>
            <option value="Part-Time" <?php echo $job['employment_type'] === 'Part-Time' ? 'selected' : ''; ?>>Part-Time</option>
            <option value="Contract" <?php echo $job['employment_type'] === 'Contract' ? 'selected' : ''; ?>>Contract</option>
            <option value="Internship" <?php echo $job['employment_type'] === 'Internship' ? 'selected' : ''; ?>>Internship</option>
          </select>
        </div>

        <div class="form-group">
          <label for="experience_req" class="form-label">Experience Required <span style="color: #EF4444;">*</span></label>
          <input type="text" id="experience_req" name="experience_req" class="form-control" placeholder="e.g. 2 - 4 Years or Fresher" required value="<?php echo e($job['experience_req']); ?>">
        </div>
      </div>

      <div class="form-group">
        <label for="short_description" class="form-label">Short Summary (Preview on Careers Listing) <span style="color: #EF4444;">*</span></label>
        <textarea id="short_description" name="short_description" class="form-control" style="min-height: 70px;" placeholder="Brief 1-2 sentence overview of the role..." required><?php echo e($job['short_description']); ?></textarea>
      </div>

      <div class="form-group">
        <label for="responsibilities" class="form-label">Key Responsibilities <span style="color: #EF4444;">*</span> (1 per line with &bull; or -)</label>
        <textarea id="responsibilities" name="responsibilities" class="form-control" style="min-height: 120px;" placeholder="• Execute high-volume data transcription from invoices
• Perform dual-pass verification audits
• Adhere to client SOPs..." required><?php echo e($job['responsibilities']); ?></textarea>
      </div>

      <div class="form-group">
        <label for="required_skills" class="form-label">Required Skills &amp; Qualifications <span style="color: #EF4444;">*</span></label>
        <textarea id="required_skills" name="required_skills" class="form-control" style="min-height: 120px;" placeholder="• Typing speed 40+ WPM
• Strong MS Excel / Sheets knowledge
• Graduate degree..." required><?php echo e($job['required_skills']); ?></textarea>
      </div>

      <div class="form-group">
        <label for="benefits" class="form-label">Perks &amp; Benefits (Optional)</label>
        <textarea id="benefits" name="benefits" class="form-control" style="min-height: 80px;" placeholder="• Performance bonus
• Fixed day shifts
• Career advancement..."><?php echo e($job['benefits']); ?></textarea>
      </div>

      <div style="background: var(--admin-bg); padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; display: flex; gap: 2rem; align-items: center; flex-wrap: wrap;">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <label for="status" style="font-weight: 700; color: var(--admin-navy-dark);">Job Status:</label>
          <select id="status" name="status" class="form-control" style="width: auto; font-weight: 600;">
            <option value="Active" <?php echo $job['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
            <option value="Closed" <?php echo $job['status'] === 'Closed' ? 'selected' : ''; ?>>Closed</option>
            <option value="Draft" <?php echo $job['status'] === 'Draft' ? 'selected' : ''; ?>>Draft</option>
          </select>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
          <input type="checkbox" id="is_published" name="is_published" value="1" <?php echo $job['is_published'] ? 'checked' : ''; ?> style="width: 18px; height: 18px; cursor: pointer;">
          <label for="is_published" style="font-weight: 600; color: var(--admin-navy-dark); cursor: pointer;">
            Publish position publicly on Careers page
          </label>
        </div>
      </div>

      <div style="display: flex; gap: 1rem;">
        <button type="submit" class="btn btn-primary">
          <span><?php echo $isEdit ? 'Save Changes' : 'Publish Job Opening'; ?></span>
        </button>
        <a href="careers.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
