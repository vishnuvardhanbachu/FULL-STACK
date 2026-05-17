<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Search / filter params
$keyword  = trim($_GET['q'] ?? '');
$location = trim($_GET['location'] ?? '');
$category = trim($_GET['category'] ?? '');
$type     = trim($_GET['type'] ?? '');

// Fetch live stats
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM jobs WHERE status='active') AS active_jobs,
    (SELECT COUNT(*) FROM users WHERE role='employer') AS employers,
    (SELECT COUNT(*) FROM users WHERE role='student') AS students,
    (SELECT COUNT(*) FROM applications) AS applications
")->fetch();

// Build job query with filters
$sql = "SELECT j.*, u.company_name, u.location AS company_location, u.industry
        FROM jobs j
        JOIN users u ON j.employer_id = u.id
        WHERE j.status = 'active' AND j.is_approved = 1";
$params = [];

if ($keyword) {
    $sql .= " AND (j.title LIKE ? OR j.description LIKE ? OR j.skills_required LIKE ?)";
    $kw = "%$keyword%";
    $params = array_merge($params, [$kw,$kw,$kw]);
}
if ($location) {
    $sql .= " AND (j.location LIKE ? OR u.location LIKE ?)";
    $loc = "%$location%";
    $params = array_merge($params, [$loc,$loc]);
}
if ($category) {
    $sql .= " AND j.category = ?";
    $params[] = $category;
}
if ($type) {
    $sql .= " AND j.job_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY j.is_featured DESC, j.created_at DESC LIMIT 24";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();

// Categories
$categories = $pdo->query("SELECT DISTINCT category FROM jobs WHERE status='active' AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>JobPortal — Find Your Dream Job</title>
  <meta name="description" content="Discover thousands of job opportunities across India. Search full-time, part-time, internship, and remote positions on JobPortal.">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body { background: var(--bg-0); }
    .btn-outline-white {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.2);
      color: #fff;
      border-radius: var(--radius-sm);
      padding: 9px 18px;
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      display: inline-flex; align-items: center; gap: 6px;
    }
    .btn-outline-white:hover { background: rgba(255,255,255,0.12); color: #fff; }
    .jobs-section { padding: 48px 40px; }
    @media(max-width:600px) { .jobs-section { padding: 28px 16px; } }
    .job-card-featured { 
      border: 1px solid rgba(245, 158, 11, 0.4) !important; 
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, var(--bg-1) 100%) !important;
      box-shadow: 0 0 20px rgba(245, 158, 11, 0.1);
    }
    .badge-amber { background: #f59e0b; color: #fff; }
  </style>
</head>
<body>

<!-- ── Header ──────────────────────────────────────────────── -->
<header class="landing-header">
  <a href="index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
    <div class="brand-icon" style="width:34px;height:34px;font-size:1rem;">💼</div>
    <span style="font-weight:800;font-size:1.1rem;color:var(--text-primary);">JobPortal</span>
  </a>
  <div style="display:flex;align-items:center;gap:10px;">
    <?php if (is_logged_in()): ?>
      <?php $dest = ['student'=>'student/dashboard.php','employer'=>'employer/dashboard.php','admin'=>'admin/dashboard.php']; ?>
      <a href="<?= $dest[current_role()] ?? 'index.php' ?>" class="btn btn-ghost btn-sm">Dashboard</a>
      <a href="logout.php" class="btn btn-ghost btn-sm">Sign Out</a>
    <?php else: ?>
      <a href="login.php" class="btn-outline-white btn-sm">Sign In</a>
      <a href="register.php" class="btn btn-primary btn-sm">Get Started</a>
    <?php endif; ?>
  </div>
</header>

<!-- ── Hero ────────────────────────────────────────────────── -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">✨ <?= number_format($stats['active_jobs']) ?> active opportunities</div>
    <h1>Find the Job <span class="gradient-text">You Deserve</span></h1>
    <p>Connect with top employers across India. Search full-time, remote, internship, and contract roles — all in one place.</p>
    <div class="hero-actions">
      <a href="register.php?role=student" class="btn btn-primary btn-lg">🚀 Find Jobs</a>
      <a href="register.php?role=employer" class="btn btn-ghost btn-lg">🏢 Post a Job</a>
    </div>

    <!-- Search bar -->
    <form class="hero-search" method="GET" action="index.php">
      <input type="text" name="q" placeholder="🔍 Job title, skills, company…" value="<?= e($keyword) ?>">
      <input type="text" name="location" placeholder="📍 Location" value="<?= e($location) ?>">
      <select name="type">
        <option value="">All Types</option>
        <?php foreach (['full-time','part-time','internship','contract','remote'] as $t): ?>
          <option value="<?= $t ?>" <?= $type === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="white-space:nowrap;">Search</button>
    </form>
  </div>
</section>

<!-- ── Stats Bar ────────────────────────────────────────────── -->
<div class="stats-bar">
  <div class="stat-item">
    <div class="num"><?= number_format($stats['active_jobs']) ?>+</div>
    <div class="lbl">Active Jobs</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($stats['employers']) ?>+</div>
    <div class="lbl">Employers</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($stats['students']) ?>+</div>
    <div class="lbl">Job Seekers</div>
  </div>
  <div class="stat-item">
    <div class="num"><?= number_format($stats['applications']) ?>+</div>
    <div class="lbl">Applications</div>
  </div>
</div>

<!-- ── Job Listings ─────────────────────────────────────────── -->
<section class="jobs-section">
  <!-- filters -->
  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <h2 style="font-size:1.3rem;">
      <?= ($keyword || $location || $category || $type) ? 'Search Results' : 'Latest Opportunities' ?>
      <span style="font-size:0.875rem;font-weight:400;color:var(--text-muted);margin-left:8px;"><?= count($jobs) ?> jobs</span>
    </h2>
    <?php if ($keyword || $location || $category || $type): ?>
      <a href="index.php" class="btn btn-ghost btn-sm">✕ Clear Filters</a>
    <?php endif; ?>
  </div>

  <!-- category chips -->
  <?php if ($categories): ?>
  <div class="filter-bar mb-24">
    <a href="index.php" class="filter-chip <?= !$category ? 'active' : '' ?>">All</a>
    <?php foreach ($categories as $cat): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['category' => $cat])) ?>"
         class="filter-chip <?= $category === $cat ? 'active' : '' ?>"><?= e($cat) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (empty($jobs)): ?>
    <div class="empty-state">
      <div class="icon">🔍</div>
      <h3>No jobs found</h3>
      <p>Try adjusting your search filters or check back later.</p>
    </div>
  <?php else: ?>
  <div class="job-grid">
    <?php foreach ($jobs as $i => $job): ?>
    <div class="job-card animate-in <?= $job['is_featured'] ? 'job-card-featured' : '' ?>" style="animation-delay:<?= $i * 0.04 ?>s">
      <div class="job-card-header">
        <div>
          <?php if($job['is_featured']): ?><span class="badge badge-amber text-xs mb-4">🌟 Featured</span><?php endif; ?>
          <h4 class="job-title"><?= e($job['title']) ?></h4>
          <div class="job-company">🏢 <?= e($job['company_name'] ?? 'Company') ?></div>
        </div>
        <div class="job-company-logo">
          <?= mb_substr($job['company_name'] ?? 'C', 0, 1) ?>
        </div>
      </div>

      <div class="job-meta">
        <span class="job-tag">📍 <?= e($job['location'] ?? 'Remote') ?></span>
        <span class="job-tag">⏱ <?= ucfirst(str_replace('-',' ', $job['job_type'])) ?></span>
        <span class="job-tag">🎯 <?= ucfirst($job['experience_level']) ?></span>
        <?php if ($job['category']): ?>
          <span class="job-tag">🗂 <?= e($job['category']) ?></span>
        <?php endif; ?>
      </div>

      <div class="job-salary"><?= fmt_salary((int)$job['salary_min'], (int)$job['salary_max']) ?> / year</div>

        <div class="job-card-footer">
        <span class="job-posted"><?= time_ago($job['created_at']) ?></span>
        <?php if (is_logged_in() && current_role() === 'student'): ?>
          <a href="student/apply.php?job_id=<?= $job['id'] ?>" class="btn btn-primary btn-sm">Apply Now</a>
        <?php elseif (!is_logged_in()): ?>
          <a href="login.php" class="btn btn-primary btn-sm">Apply Now</a>
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
</section>

<!-- ── Footer ──────────────────────────────────────────────── -->
<footer style="border-top:1px solid var(--glass-border);padding:24px 40px;text-align:center;color:var(--text-muted);font-size:0.8rem;">
  © <?= date('Y') ?> JobPortal — Built with PHP &amp; MySQL
</footer>

<script>
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
            const res = await fetch('api.php?action=jobs&' + urlParams.toString());
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
<script src="assets/script.js"></script>
</body>
</html>
