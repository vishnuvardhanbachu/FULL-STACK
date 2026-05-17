<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid = $_SESSION['user_id'];

// Stats
$stmt = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM jobs WHERE employer_id=?) AS total_jobs,
    (SELECT COUNT(*) FROM jobs WHERE employer_id=? AND status='active') AS active_jobs,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=?) AS total_apps,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='pending') AS pending_apps,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='shortlisted') AS shortlisted,
    (SELECT COUNT(*) FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=? AND a.status='hired') AS hired
");
$stmt->execute([$uid,$uid,$uid,$uid,$uid,$uid]);
$s = $stmt->fetch();

// Recent applications across all my jobs
$recent = $pdo->prepare(
    "SELECT a.*, j.title AS job_title, u.name AS student_name, u.email AS student_email,
            u.experience_level, u.skills, u.resume_path
     FROM applications a
     JOIN jobs j ON a.job_id=j.id
     JOIN users u ON a.student_id=u.id
     WHERE j.employer_id=?
     ORDER BY a.applied_at DESC LIMIT 8"
);
$recent->execute([$uid]); $applications=$recent->fetchAll();

// My jobs
$jobs = $pdo->prepare("SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count
    FROM jobs j WHERE j.employer_id=? ORDER BY j.created_at DESC LIMIT 5");
$jobs->execute([$uid]); $myjobs=$jobs->fetchAll();

// 7-day trend for Chart.js
$trend = $pdo->prepare("SELECT DATE(a.applied_at) as d, COUNT(*) as c 
    FROM applications a JOIN jobs j ON a.job_id=j.id 
    WHERE j.employer_id=? AND a.applied_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(a.applied_at) ORDER BY d ASC");
$trend->execute([$uid]);
$trendData = $trend->fetchAll();
$chartLabels = [];
$chartValues = [];
for($i=6; $i>=0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    $found = array_values(array_filter($trendData, fn($x)=>$x['d']===$date));
    $chartValues[] = $found ? (int)$found[0]['c'] : 0;
}

$me = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me->execute([$uid]); $me=$me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employer Dashboard — JobPortal</title>
  <link rel="manifest" href="../manifest.json">
  <link rel="stylesheet" href="../assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    <a class="nav-item active" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="post-job.php"><span class="icon">➕</span> Post a Job</a>
    <a class="nav-item" href="my-jobs.php"><span class="icon">📋</span> My Jobs</a>
    <a class="nav-item" href="applicants.php"><span class="icon">👥</span> Applicants</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="pricing.php"><span class="icon">💳</span> Pricing & Credits</a>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> Public Board</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?=mb_strtoupper(mb_substr($me['company_name']??$me['name'],0,1))?></div>
      <div>
        <div class="user-name"><?=e($me['company_name']??$me['name'])?></div>
        <div class="user-role">Employer</div>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Dashboard</h1>
    </div>
    <div class="topbar-right">
      <a href="post-job.php" class="btn btn-primary btn-sm">➕ Post Job</a>
    </div>
  </div>

  <div class="page-content">

    <div class="mb-24">
      <h2>Welcome back, <?=e(explode(' ',$me['name'])[0])?> 👋</h2>
      <p class="text-muted text-sm">Here's your recruitment overview for <?=e($me['company_name']??'your company')?>.</p>
    </div>

    <!-- Stats -->
    <div class="stat-grid animate-in">
      <div class="stat-card"><div class="stat-icon indigo">📋</div><div class="stat-info"><div class="stat-value"><?=$s['total_jobs']?></div><div class="stat-label">Total Jobs Posted</div></div></div>
      <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><div class="stat-value"><?=$s['active_jobs']?></div><div class="stat-label">Active Listings</div></div></div>
      <div class="stat-card">
        <div class="stat-icon indigo">📊</div>
        <div class="stat-info"><div class="stat-value"><?=$s['total_apps']?></div><div class="stat-label">Total Applications</div></div>
      </div>
      <div class="stat-card" style="background:var(--accent-grad); color:#fff;">
        <div class="stat-icon" style="background:rgba(255,255,255,0.2); color:#fff;">💳</div>
        <div class="stat-info">
            <div class="stat-value"><?= $me['credits'] ?></div>
            <div class="stat-label" style="color:rgba(255,255,255,0.8);">Credits Left</div>
            <a href="pricing.php" style="font-size:0.7rem; color:#fff; text-decoration:underline;">Upgrade Plan</a>
        </div>
      </div>
      <div class="stat-card"><div class="stat-icon amber">⏳</div><div class="stat-info"><div class="stat-value"><?=$s['pending_apps']?></div><div class="stat-label">Pending Review</div></div></div>
      <div class="stat-card"><div class="stat-icon purple">⭐</div><div class="stat-info"><div class="stat-value"><?=$s['shortlisted']?></div><div class="stat-label">Shortlisted</div></div></div>
      <div class="stat-card"><div class="stat-icon green">🎉</div><div class="stat-info"><div class="stat-value"><?=$s['hired']?></div><div class="stat-label">Hired</div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start;" class="animate-in animate-delay-1">

      <!-- Recent Applications -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">📩 Recent Applications</h3>
          <a href="applicants.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <?php if(empty($applications)): ?>
          <div class="empty-state"><div class="icon">📭</div><h3>No applications yet</h3><p>Post a job to start receiving candidates.</p><a href="post-job.php" class="btn btn-primary btn-sm mt-16">Post a Job</a></div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead><tr><th>Candidate</th><th>Job</th><th>Status</th><th>Applied</th><th>Action</th></tr></thead>
              <tbody>
              <?php foreach($applications as $app): ?>
              <tr>
                <td>
                  <div class="font-bold text-sm"><?=e($app['student_name'])?></div>
                  <div class="text-xs text-muted"><?=e($app['student_email'])?></div>
                </td>
                <td class="text-sm"><?=e($app['job_title'])?></td>
                <td><span class="badge status-<?=$app['status']?>"><?=ucfirst($app['status'])?></span></td>
                <td class="text-muted text-sm"><?=time_ago($app['applied_at'])?></td>
                <td><a href="applicants.php?job_id=<?=$app['job_id']?>" class="btn btn-ghost btn-sm">Review</a></td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- My Active Jobs -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">📋 My Jobs</h3>
          <a href="my-jobs.php" class="btn btn-ghost btn-sm">Manage</a>
        </div>
        <?php if(empty($myjobs)): ?>
          <div class="empty-state" style="padding:30px"><div class="icon">📋</div><h3>No jobs yet</h3><a href="post-job.php" class="btn btn-primary btn-sm mt-8">Post First Job</a></div>
        <?php else: ?>
          <div style="padding:8px 0;">
            <?php foreach($myjobs as $job): ?>
            <div style="padding:14px 20px;border-bottom:1px solid var(--glass-border);">
              <div class="d-flex align-center justify-between">
                <div>
                  <div class="font-bold text-sm"><?=e($job['title'])?></div>
                  <div class="text-xs text-muted"><?=ucfirst(str_replace('-',' ',$job['job_type']))?> · <?=e($job['location'])?></div>
                </div>
                <span class="badge status-<?=$job['status']?>"><?=ucfirst($job['status'])?></span>
              </div>
              <div class="d-flex align-center gap-8 mt-8">
                <span class="text-xs text-muted">📩 <?=$job['app_count']?> applicants</span>
                <a href="applicants.php?job_id=<?=$job['id']?>" class="btn btn-ghost btn-sm">View</a>
                <a href="post-job.php?edit=<?=$job['id']?>" class="btn btn-ghost btn-sm">Edit</a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Analytics Chart -->
    <div class="card card-p mt-24 animate-in animate-delay-2">
      <h3 style="margin-bottom:16px;">📈 Application Trends (Last 7 Days)</h3>
      <div style="height:300px; position:relative;">
        <canvas id="trendChart"></canvas>
      </div>
    </div>

  </div>
</main>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?=json_encode($chartLabels)?>,
            datasets: [{
                label: 'New Applications',
                data: <?=json_encode($chartValues)?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { stepSize: 1, color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            }
        }
    });
});
</script>
<script src="../assets/script.js"></script>
</body>
</html>
