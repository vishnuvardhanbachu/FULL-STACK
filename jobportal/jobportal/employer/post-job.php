<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid   = $_SESSION['user_id'];
$me    = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
$editId= (int)($_GET['edit'] ?? 0);
$job   = null;
$error = $success = '';

// Load job for editing
if ($editId) {
    $jq = $pdo->prepare("SELECT * FROM jobs WHERE id=? AND employer_id=?");
    $jq->execute([$editId, $uid]);
    $job = $jq->fetch();
    if (!$job) { header('Location: my-jobs.php'); exit; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements= trim($_POST['requirements'] ?? '');
    $skills      = trim($_POST['skills_required'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $location    = trim($_POST['location'] ?? '');
    $salary_min  = (int)($_POST['salary_min'] ?? 0);
    $salary_max  = (int)($_POST['salary_max'] ?? 0);
    $exp_level   = $_POST['experience_level'] ?? 'any';
    $job_type    = $_POST['job_type'] ?? 'full-time';
    $status      = $_POST['status'] ?? 'active';
    $deadline    = $_POST['deadline'] ?: null;

    if (!$title || !$description) {
        $error = 'Title and description are required.';
    } else {
        if ($editId && $job) {
            $pdo->prepare("UPDATE jobs SET title=?,description=?,requirements=?,skills_required=?,
                category=?,location=?,salary_min=?,salary_max=?,experience_level=?,job_type=?,status=?,deadline=?
                WHERE id=? AND employer_id=?")
                ->execute([$title,$description,$requirements,$skills,$category,$location,
                           $salary_min,$salary_max,$exp_level,$job_type,$status,$deadline,$editId,$uid]);
            $success = 'Job updated successfully!';
            // Reload
            $jq->execute([$editId,$uid]); $job=$jq->fetch();
        } else {
            if ($me['credits'] < 1) {
                $error = "You don't have enough credits to post a new job. Please purchase more credits.";
            } else {
                $pdo->prepare("INSERT INTO jobs (employer_id,title,description,requirements,skills_required,
                    category,location,salary_min,salary_max,experience_level,job_type,status,deadline,is_approved)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0)")
                    ->execute([$uid,$title,$description,$requirements,$skills,$category,$location,
                               $salary_min,$salary_max,$exp_level,$job_type,$status,$deadline]);
                $pdo->prepare("UPDATE users SET credits = credits - 1 WHERE id=?")->execute([$uid]);
                header('Location: my-jobs.php?posted=1');
                exit;
            }
        }
    }
}

$v = fn($field) => e($job[$field] ?? $_POST[$field] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?=$editId?'Edit Job':'Post a Job'?> — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🏢</div>
    <div><div class="brand-name">JobPortal</div><div class="brand-tag">EMPLOYER PORTAL</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Recruit</div>
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item active" href="post-job.php"><span class="icon">➕</span> Post a Job</a>
    <a class="nav-item" href="my-jobs.php"><span class="icon">📋</span> My Jobs</a>
    <a class="nav-item" href="applicants.php"><span class="icon">👥</span> Applicants</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?=mb_strtoupper(mb_substr($me['company_name']??$me['name'],0,1))?></div>
      <div><div class="user-name"><?=e($me['company_name']??$me['name'])?></div><div class="user-role">Employer</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <?php if($editId): ?><a href="my-jobs.php" class="btn btn-ghost btn-sm">← Back</a><?php endif; ?>
      <h1 class="page-title"><?=$editId?'Edit Job Listing':'Post a New Job'?></h1>
    </div>
  </div>

  <div class="page-content">
    <?php if($error): ?><div class="alert alert-danger">⚠️ <?=e($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>
    <?php if(!$editId): ?>
      <div class="alert alert-info" style="background:rgba(99,102,241,0.1); border-color:var(--primary);">
        ℹ️ <strong>Note:</strong> New job listings require approval from our moderation team before they appear in the public board.
      </div>
    <?php endif; ?>

    <form method="POST" class="animate-in">
      <div style="display:grid;grid-template-columns:1fr 300px;gap:22px;align-items:start;">

        <!-- Main form -->
        <div>
          <div class="card card-p mb-16">
            <h3 style="margin-bottom:18px;">📝 Job Details</h3>
            <div class="form-group">
              <label class="form-label">Job Title *</label>
              <input name="title" type="text" class="form-control" placeholder="e.g. Senior Frontend Developer" value="<?=$v('title')?>" required>
            </div>
            <div class="form-row col-2">
              <div class="form-group">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                  <option value="">Select category</option>
                  <?php foreach(['Engineering','Product','Design','Analytics','Marketing','Sales','HR','Finance','Operations','Other'] as $cat): ?>
                    <option value="<?=$cat?>" <?=$v('category')===$cat?'selected':''?>><?=$cat?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Location</label>
                <input name="location" type="text" class="form-control" placeholder="City or Remote" value="<?=$v('location')?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Job Description *</label>
              <textarea name="description" class="form-control" rows="7"
                placeholder="Describe the role, responsibilities, team culture, and what makes this a great opportunity…" required><?=$v('description')?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Requirements</label>
              <textarea name="requirements" class="form-control" rows="4"
                placeholder="Education, years of experience, certifications required…"><?=$v('requirements')?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Skills Required <span class="text-muted">(comma separated)</span></label>
              <input name="skills_required" type="text" class="form-control"
                     placeholder="e.g. React, Node.js, MySQL, AWS"
                     value="<?=$v('skills_required')?>">
            </div>
          </div>
        </div>

        <!-- Sidebar settings -->
        <div>
          <div class="card card-p mb-16">
            <h4 style="margin-bottom:14px;">⚙️ Job Settings</h4>

            <div class="form-group">
              <label class="form-label">Job Type</label>
              <select name="job_type" class="form-control">
                <?php foreach(['full-time'=>'Full-Time','part-time'=>'Part-Time','internship'=>'Internship','contract'=>'Contract','remote'=>'Remote'] as $k=>$l): ?>
                  <option value="<?=$k?>" <?=$v('job_type')===$k?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Experience Level</label>
              <select name="experience_level" class="form-control">
                <?php foreach(['any'=>'Any Level','fresher'=>'Fresher','junior'=>'Junior (1-3 yrs)','mid'=>'Mid (3-5 yrs)','senior'=>'Senior (5+ yrs)'] as $k=>$l): ?>
                  <option value="<?=$k?>" <?=$v('experience_level')===$k?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-row col-2">
              <div class="form-group">
                <label class="form-label">Min Salary (₹)</label>
                <input name="salary_min" type="number" class="form-control" placeholder="500000" min="0" value="<?=$v('salary_min')?>">
              </div>
              <div class="form-group">
                <label class="form-label">Max Salary (₹)</label>
                <input name="salary_max" type="number" class="form-control" placeholder="1200000" min="0" value="<?=$v('salary_max')?>">
              </div>
            </div>

            <div class="form-group">
              <label class="form-label">Application Deadline</label>
              <input name="deadline" type="date" class="form-control"
                     min="<?=date('Y-m-d')?>"
                     value="<?=$v('deadline')?>">
            </div>

            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <?php foreach(['active'=>'Active (published)','draft'=>'Draft (hidden)','closed'=>'Closed'] as $k=>$l): ?>
                  <option value="<?=$k?>" <?=$v('status')===$k?'selected':''?>><?=$l?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">
              <?=$editId?'💾 Update Job':'🚀 Publish Job'?>
            </button>
            <?php if($editId): ?>
              <a href="my-jobs.php" class="btn btn-ghost btn-block" style="margin-top:8px;">Cancel</a>
            <?php endif; ?>
          </div>

          <!-- Tips -->
          <div class="card card-p">
            <h4 style="margin-bottom:10px;">💡 Tips for Better Results</h4>
            <ul style="list-style:none;font-size:0.8rem;color:var(--text-muted);line-height:2;">
              <li>✅ Use a clear, specific job title</li>
              <li>✅ List salary range to get 3× more applicants</li>
              <li>✅ Add required skills as comma-separated tags</li>
              <li>✅ Set a deadline to create urgency</li>
              <li>✅ Write at least 150 words in the description</li>
            </ul>
          </div>
        </div>
      </div>
    </form>
  </div>
</main>
</div>
<script>
document.querySelector('form').addEventListener('submit', function() {
  const btn = document.getElementById('submitBtn');
  btn.textContent = 'Saving…'; 
  setTimeout(() => btn.disabled = true, 50);
});
</script>
<script src="../assets/script.js"></script>
</body>
</html>
