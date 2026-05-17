<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid      = $_SESSION['user_id'];
$keyword  = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$type     = trim($_GET['type'] ?? '');
$category = trim($_GET['category'] ?? '');
$exp      = trim($_GET['exp'] ?? '');

// Build query
$sql = "SELECT j.*, u.company_name, u.industry,
        (SELECT id FROM applications WHERE job_id=j.id AND student_id=?) AS already_applied,
        (SELECT id FROM saved_jobs WHERE job_id=j.id AND user_id=?) AS is_saved
        FROM jobs j JOIN users u ON j.employer_id=u.id
        WHERE j.status='active' AND j.is_approved = 1";
$params = [$uid, $uid];

if ($keyword)  { $sql .= " AND (j.title LIKE ? OR j.skills_required LIKE ? OR u.company_name LIKE ?)"; $kw="%$keyword%"; $params=array_merge($params,[$kw,$kw,$kw]); }
if ($location) { $sql .= " AND j.location LIKE ?"; $params[]="%$location%"; }
if ($type)     { $sql .= " AND j.job_type=?";  $params[]=$type; }
if ($category) { $sql .= " AND j.category=?";  $params[]=$category; }
if ($exp)      { $sql .= " AND j.experience_level=?"; $params[]=$exp; }

$sql .= " ORDER BY j.is_featured DESC, j.created_at DESC LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

$categories = $pdo->query("SELECT DISTINCT category FROM jobs WHERE status='active' AND category!='' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$me = $pdo->prepare("SELECT name,profile_pic FROM users WHERE id=?");
$me->execute([$uid]); $me=$me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Jobs — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .job-card-featured { 
      border: 1px solid rgba(245, 158, 11, 0.4) !important; 
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, var(--bg-1) 100%) !important;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.1);
    }
  </style>
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
    <a class="nav-item" href="dashboard.php#saved"><span class="icon">🔖</span> Saved Jobs</a>
    <a class="nav-item" href="resume_gen.php"><span class="icon">📄</span> Resume Builder</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> Public Board</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= mb_strtoupper(mb_substr($me['name'],0,1)) ?></div>
      <div><div class="user-name"><?= e($me['name']) ?></div><div class="user-role">Student</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Browse Jobs</h1>
    </div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?= count($jobs) ?> results</span>
    </div>
  </div>

  <div class="page-content">

    <!-- Search & Filters -->
    <div class="card card-p mb-24 animate-in">
      <form method="GET" id="filterForm">
        <div class="form-row col-3" style="margin-bottom:12px;">
          <div class="form-group" style="margin-bottom:0">
            <input name="q" type="text" class="form-control" placeholder="🔍 Title, skill, company…" value="<?= e($keyword) ?>">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <input name="location" type="text" class="form-control" placeholder="📍 Location" value="<?= e($location) ?>">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <button type="submit" class="btn btn-primary btn-block">Search</button>
          </div>
        </div>
        <div class="d-flex gap-8" style="flex-wrap:wrap;align-items:center;">
          <select name="type" class="form-control" style="width:auto;min-width:130px;" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach(['full-time','part-time','internship','contract','remote'] as $t): ?>
              <option value="<?=$t?>" <?=$type===$t?'selected':''?>><?=ucfirst($t)?></option>
            <?php endforeach; ?>
          </select>
          <select name="exp" class="form-control" style="width:auto;min-width:140px;" onchange="this.form.submit()">
            <option value="">All Experience</option>
            <?php foreach(['fresher','junior','mid','senior'] as $x): ?>
              <option value="<?=$x?>" <?=$exp===$x?'selected':''?>><?=ucfirst($x)?></option>
            <?php endforeach; ?>
          </select>
          <select name="category" class="form-control" style="width:auto;min-width:140px;" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
              <option value="<?=$cat?>" <?=$category===$cat?'selected':''?>><?=e($cat)?></option>
            <?php endforeach; ?>
          </select>
          <?php if($keyword||$location||$type||$category||$exp): ?>
            <a href="jobs.php" class="btn btn-ghost btn-sm">✕ Reset</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Job Grid -->
    <?php if(empty($jobs)): ?>
      <div class="empty-state">
        <div class="icon">🔎</div>
        <h3>No jobs match your search</h3>
        <p>Try different keywords or remove some filters.</p>
        <a href="jobs.php" class="btn btn-primary btn-sm mt-16">Show All Jobs</a>
      </div>
    <?php else: ?>
    <div class="job-grid">
      <?php foreach($jobs as $i=>$job): ?>
      <div class="job-card animate-in <?= $job['is_featured'] ? 'job-card-featured' : '' ?>" style="animation-delay:<?=$i*0.04?>s">
        <div class="job-card-header">
          <div>
            <?php if($job['is_featured']): ?><span class="badge badge-amber text-xs mb-4" style="background:#f59e0b;color:#fff;">🌟 Featured</span><?php endif; ?>
            <h4 class="job-title"><?=e($job['title'])?></h4>
            <div class="job-company">🏢 <?=e($job['company_name'])?></div>
          </div>
          <div class="job-company-logo"><?=mb_substr($job['company_name'],0,1)?></div>
          <button class="save-btn <?= $job['is_saved'] ? 'saved' : '' ?>" data-id="<?= $job['id'] ?>" onclick="toggleSave(this)" style="background:none;border:none;cursor:pointer;font-size:1.2rem;margin-left:8px;" title="Save Job">
            <?= $job['is_saved'] ? '🔖' : '📑' ?>
          </button>
        </div>

        <?php if($job['skills_required']): ?>
        <div style="margin:10px 0;font-size:0.75rem;color:var(--text-muted);">
          <?php foreach(array_slice(explode(',',$job['skills_required']),0,4) as $sk): ?>
            <span class="badge badge-indigo" style="margin:2px;"><?=e(trim($sk))?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="job-meta">
          <span class="job-tag">📍 <?=e($job['location'])?></span>
          <span class="job-tag">⏱ <?=ucfirst(str_replace('-',' ',$job['job_type']))?></span>
          <span class="job-tag">🎯 <?=ucfirst($job['experience_level'])?></span>
        </div>

        <?php if($job['deadline']): ?>
          <div class="text-xs text-muted" style="margin-top:6px;">
            ⏰ Deadline: <?=date('d M Y',strtotime($job['deadline']))?>
            <?php if(strtotime($job['deadline']) < time() + 86400*3): ?><span class="badge badge-red" style="margin-left:4px;">Closing soon</span><?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="job-salary mt-8"><?=fmt_salary((int)$job['salary_min'],(int)$job['salary_max'])?></div>

        <div id="score-<?=$job['id']?>" class="text-xs mt-8" style="color:var(--text-muted)">Calculating match score...</div>
        <script>
            fetch(`../api.php?action=match_score&job_id=<?=$job['id']?>`)
                .then(r => r.json())
                .then(d => {
                    const el = document.getElementById('score-<?=$job['id']?>');
                    if(d.score) {
                        const color = d.score > 70 ? '#10b981' : (d.score > 40 ? '#f59e0b' : '#ef4444');
                        el.innerHTML = `<span style="color:${color};font-weight:700;">✨ ${d.score}% Match</span> based on your skills.`;
                    } else { el.innerHTML = ''; }
                });
        </script>

        <div class="job-card-footer">
          <span class="job-posted"><?=time_ago($job['created_at'])?></span>
          <?php if($job['already_applied']): ?>
            <span class="badge badge-green">✅ Applied</span>
          <?php else: ?>
            <a href="apply.php?job_id=<?=$job['id']?>" class="btn btn-primary btn-sm">Apply Now</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    
    <div id="loading" style="text-align:center; padding:20px; display:none;">
      <span class="text-muted">Loading more jobs...</span>
    </div>
    <div id="endReached" style="text-align:center; padding:20px; display:none;">
      <span class="text-muted text-sm">You have reached the end.</span>
    </div>
    
    <?php endif; ?>
  </div>
</main>
</div>
<script>
async function toggleSave(btn) {
    const id = btn.dataset.id;
    const res = await fetch(`../api.php?action=save_job&job_id=${id}`);
    const data = await res.json();
    if (data.ok) {
        btn.classList.toggle('saved');
        btn.innerHTML = data.saved ? '🔖' : '📑';
        btn.title = data.saved ? 'Unsave Job' : 'Save Job';
    }
}

let offset = 6; 
let loading = false;
let endReached = false;
const loadingEl = document.getElementById('loading');
const endEl = document.getElementById('endReached');
const jobGrid = document.querySelector('.job-grid');

if ('IntersectionObserver' in window && jobGrid) {
    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !loading && !endReached) {
            loadMoreJobs();
        }
    }, { rootMargin: '100px' });

    if (jobGrid.lastElementChild) {
        observer.observe(jobGrid.lastElementChild);
    }

    async function loadMoreJobs() {
        loading = true;
        if(loadingEl) loadingEl.style.display = 'block';

        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('offset', offset);
        urlParams.set('limit', 6);
        
        try {
            const res = await fetch('../api.php?action=jobs&' + urlParams.toString());
            const data = await res.json();
            
            if (data.jobs && data.jobs.length > 0) {
                jobGrid.insertAdjacentHTML('beforeend', data.html);
                offset += data.jobs.length;
                observer.disconnect();
                observer.observe(jobGrid.lastElementChild);
            } else {
                endReached = true;
                if(endEl) endEl.style.display = 'block';
            }
        } catch (err) {
            console.error("Failed to load jobs", err);
        } finally {
            loading = false;
            if(loadingEl) loadingEl.style.display = 'none';
        }
    }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
