<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid    = $_SESSION['user_id'];
$job_id = (int)($_GET['job_id'] ?? 0);

if (!$job_id) { header('Location: jobs.php'); exit; }

// Fetch job + employer
$stmt = $pdo->prepare(
    "SELECT j.*, u.name AS employer_name, u.company_name, u.company_website,
            u.industry, u.location AS hq_location, u.bio AS company_bio
     FROM jobs j JOIN users u ON j.employer_id=u.id
     WHERE j.id=? AND j.status='active'"
);
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    echo '<p style="color:#f87171;padding:40px">Job not found or no longer active. <a href="jobs.php">Back</a></p>';
    exit;
}

// Check already applied
$chk = $pdo->prepare("SELECT id,status FROM applications WHERE job_id=? AND student_id=?");
$chk->execute([$job_id, $uid]);
$existing = $chk->fetch();

// Student profile
$me = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me->execute([$uid]); $me=$me->fetch();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$existing) {
    $cover = trim($_POST['cover_letter'] ?? '');
    try {
        $ins = $pdo->prepare("INSERT INTO applications (job_id, student_id, cover_letter) VALUES (?,?,?)");
        $ins->execute([$job_id, $uid, $cover]);

        // Increment job views
        $pdo->prepare("UPDATE jobs SET views=views+1 WHERE id=?")->execute([$job_id]);

        // Notify employer
        notify($pdo, $job['employer_id'], 'New Application',
            $me['name'] . ' applied for "' . $job['title'] . '"', 'info');

        $success = 'Application submitted successfully! The employer will review it soon.';
        $existing = ['status'=>'pending'];
    } catch (Exception $e) {
        $error = 'Failed to submit application. You may have already applied.';
    }
}

// Increment views on page load
$pdo->prepare("UPDATE jobs SET views=views+1 WHERE id=?")->execute([$job_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?=e($job['title'])?> — Apply | JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">💼</div>
    <div><div class="brand-name">JobPortal</div><div class="brand-tag">STUDENT PORTAL</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item active" href="jobs.php"><span class="icon">🔍</span> Browse Jobs</a>
    <a class="nav-item" href="dashboard.php#applications"><span class="icon">📋</span> My Applications</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?=mb_strtoupper(mb_substr($me['name'],0,1))?></div>
      <div><div class="user-name"><?=e($me['name'])?></div><div class="user-role">Student</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <a href="jobs.php" class="btn btn-ghost btn-sm">← Back</a>
      <h1 class="page-title" style="font-size:0.95rem;"><?=e($job['title'])?></h1>
    </div>
  </div>

  <div class="page-content">
    <?php if($error): ?><div class="alert alert-danger">⚠️ <?=e($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:22px;align-items:start;">

      <!-- Job Details -->
      <div>
        <div class="card card-p mb-16 animate-in">
          <div style="display:flex;align-items:flex-start;gap:16px;margin-bottom:20px;">
            <div class="job-company-logo" style="width:56px;height:56px;font-size:1.6rem;">
              <?=mb_substr($job['company_name'],0,1)?>
            </div>
            <div style="flex:1">
              <h2 style="margin-bottom:4px;"><?=e($job['title'])?></h2>
              <div class="text-muted"><?=e($job['company_name'])?></div>
              <?php if($job['company_website']): ?>
                <a href="<?=e($job['company_website'])?>" target="_blank" class="text-sm" style="color:var(--accent-3)"><?=e($job['company_website'])?> ↗</a>
              <?php endif; ?>
            </div>
            <?php if($existing): ?>
              <span class="badge status-<?=$existing['status']?>"><?=ucfirst($existing['status'])?></span>
            <?php else: ?>
              <span class="badge status-active">Active</span>
            <?php endif; ?>
          </div>

          <div class="job-meta" style="margin-bottom:16px;">
            <span class="job-tag">📍 <?=e($job['location'])?></span>
            <span class="job-tag">⏱ <?=ucfirst(str_replace('-',' ',$job['job_type']))?></span>
            <span class="job-tag">🎯 <?=ucfirst($job['experience_level'])?></span>
            <?php if($job['category']): ?><span class="job-tag">🗂 <?=e($job['category'])?></span><?php endif; ?>
            <span class="job-tag">👁 <?=$job['views']?> views</span>
          </div>

          <?php if($job['salary_min'] || $job['salary_max']): ?>
            <div class="job-salary mb-16">💰 <?=fmt_salary((int)$job['salary_min'],(int)$job['salary_max'])?> / year</div>
          <?php endif; ?>

          <?php if($job['deadline']): ?>
            <div class="text-sm text-muted mb-16">⏰ Application Deadline: <strong style="color:var(--text-primary)"><?=date('d M Y',strtotime($job['deadline']))?></strong></div>
          <?php endif; ?>

          <div class="divider"></div>

          <h4 style="margin-bottom:10px;">Job Description</h4>
          <p style="white-space:pre-line;line-height:1.8;"><?=e($job['description'])?></p>

          <?php if($job['requirements']): ?>
            <h4 style="margin:20px 0 10px;">Requirements</h4>
            <p style="white-space:pre-line;line-height:1.8;"><?=e($job['requirements'])?></p>
          <?php endif; ?>

          <?php if($job['skills_required']): ?>
            <h4 style="margin:20px 0 10px;">Skills Required</h4>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
              <?php foreach(explode(',',$job['skills_required']) as $sk): ?>
                <span class="badge badge-indigo"><?=e(trim($sk))?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Apply Form -->
        <?php if($existing): ?>
          <div class="card card-p text-center">
            <div style="font-size:2.5rem;margin-bottom:12px;">
              <?= $existing['status']==='hired' ? '🎉' : ($existing['status']==='shortlisted' ? '⭐' : ($existing['status']==='rejected' ? '❌' : '📬')) ?>
            </div>
            <h3 style="margin-bottom:8px;">Application <?=ucfirst($existing['status'])?></h3>
            <p class="text-muted text-sm">
              <?php if($existing['status']==='pending'): ?>The employer is reviewing your application.
              <?php elseif($existing['status']==='shortlisted'): ?>Great news! You've been shortlisted. Expect to hear soon.
              <?php elseif($existing['status']==='hired'): ?>Congratulations! You've been hired for this position.
              <?php else: ?>This application was not successful. Keep applying!
              <?php endif; ?>
            </p>
            <a href="jobs.php" class="btn btn-primary btn-sm mt-16">Browse More Jobs</a>
          </div>
        <?php else: ?>
      
      <!-- AI Optimizer Widget -->
      <div class="card mb-24 animate-in" style="background:linear-gradient(135deg, rgba(99,102,241,0.05), rgba(139,92,246,0.05)); border-color:var(--primary);">
        <div class="card-p">
          <div class="d-flex justify-between align-center">
            <div>
              <h3>🤖 AI Application Optimizer</h3>
              <p class="text-sm text-muted">Check if your profile matches this job perfectly.</p>
            </div>
            <button onclick="optimizeApp()" id="optBtn" class="btn btn-primary btn-sm">Scan Profile</button>
          </div>
          <div id="optResult" style="display:none;" class="mt-16">
            <div class="d-flex align-center gap-16 mb-16">
               <div id="optScore" class="font-bold text-xl" style="color:var(--primary);">0%</div>
               <div class="progress-bar" style="flex:1;"><div id="optProgress" class="progress-fill" style="width:0%;"></div></div>
            </div>
            <ul id="optSuggestions" class="text-sm" style="list-style:none; padding:0;"></ul>
          </div>
        </div>
      </div>

      <div class="card animate-in animate-delay-1">
            <div class="card-header"><h3 class="card-title">✍️ Submit Application</h3></div>
            <div class="card-p">
              <?php if(!$me['resume_path']): ?>
                <div class="alert alert-warning">⚠️ You haven't uploaded a resume yet. <a href="profile.php">Upload now</a> to strengthen your application.</div>
              <?php endif; ?>
              <form method="POST" id="applyForm">
                <div class="form-group">
                  <label class="form-label">Cover Letter <span class="text-muted">(optional but recommended)</span></label>
                  <textarea name="cover_letter" class="form-control" rows="6"
                    placeholder="Tell the employer why you're a great fit for this role. Mention relevant skills, experience, and why you're excited about this opportunity…"><?=e($_POST['cover_letter']??'')?></textarea>
                </div>
                <div class="d-flex align-center gap-12">
                  <?php if($me['resume_path']): ?>
                    <span class="text-sm text-muted">📎 Your resume will be attached automatically</span>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-primary btn-lg" id="applyBtn">
                    🚀 Submit Application
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- Sidebar info -->
      <div>
        <div class="card card-p animate-in animate-delay-2" style="margin-bottom:16px;">
          <h4 style="margin-bottom:12px;">🏢 About the Company</h4>
          <div class="text-sm" style="margin-bottom:8px;"><strong><?=e($job['company_name'])?></strong></div>
          <?php if($job['industry']): ?><div class="text-xs text-muted mb-8">🏭 <?=e($job['industry'])?></div><?php endif; ?>
          <?php if($job['hq_location']): ?><div class="text-xs text-muted mb-8">📍 HQ: <?=e($job['hq_location'])?></div><?php endif; ?>
          <?php if($job['company_bio']): ?><p class="text-sm"><?=e(mb_substr($job['company_bio'],0,200))?><?=mb_strlen($job['company_bio'])>200?'…':''?></p><?php endif; ?>
        </div>

        <div class="card card-p animate-in animate-delay-3">
          <h4 style="margin-bottom:12px;">👤 Your Profile Status</h4>
          <?php $fields=['resume_path'=>'📎 Resume','skills'=>'💡 Skills','bio'=>'📝 Bio','phone'=>'📞 Phone']; ?>
          <?php foreach($fields as $f=>$label): ?>
            <div class="d-flex align-center justify-between mb-8">
              <span class="text-sm text-muted"><?=$label?></span>
              <span><?=$me[$f]?'<span class="badge badge-green">✓</span>':'<span class="badge badge-red">✗</span>'?></span>
            </div>
          <?php endforeach; ?>
          <a href="profile.php" class="btn btn-ghost btn-sm btn-block mt-8">Edit Profile</a>
        </div>
      </div>
    </div>
  </div>
</main>
</div>
<script>
document.getElementById('applyForm')?.addEventListener('submit', function() {
  const btn = document.getElementById('applyBtn');
  btn.textContent = 'Submitting…'; 
  setTimeout(() => btn.disabled = true, 50);
});
</script>
<script>
async function optimizeApp() {
    const btn = document.getElementById('optBtn');
    const result = document.getElementById('optResult');
    const scoreEl = document.getElementById('optScore');
    const progress = document.getElementById('optProgress');
    const suggList = document.getElementById('optSuggestions');
    
    btn.disabled = true;
    btn.innerText = 'Analyzing...';
    
    try {
        const res = await fetch('../api.php?action=resume_optimizer', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({job_id: <?= $job_id ?>})
        });
        const data = await res.json();
        
        btn.innerText = 'Scan Profile';
        btn.disabled = false;
        result.style.display = 'block';
        scoreEl.innerText = data.match_percent + '% Match';
        progress.style.width = data.match_percent + '%';
        
        suggList.innerHTML = data.suggestions.map(s => `<li style="margin-bottom:8px;">✨ ${s}</li>`).join('');
    } catch(err) {
        alert("Failed to analyze profile.");
        btn.disabled = false;
    }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
