<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('admin');

$uid = $_SESSION['user_id'];
$me  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

// Platform-wide stats
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users WHERE role='employer') AS employers,
    (SELECT COUNT(*) FROM jobs)                        AS total_jobs,
    (SELECT COUNT(*) FROM jobs WHERE is_approved=0)    AS pending_jobs,
    (SELECT COUNT(*) FROM jobs WHERE status='active' AND is_approved=1) AS active_jobs,
    (SELECT COUNT(*) FROM applications)                AS total_apps,
    (SELECT COUNT(*) FROM applications WHERE status='pending')     AS pending,
    (SELECT COUNT(*) FROM applications WHERE status='shortlisted') AS shortlisted,
    (SELECT COUNT(*) FROM applications WHERE status='hired')       AS hired
")->fetch();

// Recent registrations
$newUsers = $pdo->query(
    "SELECT id,name,email,role,created_at,is_active FROM users ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

// Recent jobs
$newJobs = $pdo->query(
    "SELECT j.id,j.title,j.status,j.created_at,j.job_type,u.company_name
     FROM jobs j JOIN users u ON j.employer_id=u.id ORDER BY j.created_at DESC LIMIT 6"
)->fetchAll();

// Chart Data: Applications last 7 days
$trend = $pdo->query("SELECT DATE(applied_at) as d, COUNT(*) as c 
    FROM applications 
    WHERE applied_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(applied_at) ORDER BY d ASC")->fetchAll();
$chartLabels = [];
$chartValues = [];
for($i=6; $i>=0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    $found = array_values(array_filter($trend, fn($x)=>$x['d']===$date));
    $chartValues[] = $found ? (int)$found[0]['c'] : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-shell">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">🔴</div>
    <div><div class="brand-name">JobPortal</div><div class="brand-tag">ADMIN PANEL</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Platform</div>
    <a class="nav-item active" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="users.php"><span class="icon">👥</span> Manage Users</a>
    <a class="nav-item" href="jobs.php"><span class="icon">📋</span> Manage Jobs</a>
    <a class="nav-item" href="moderation.php"><span class="icon">⚖️</span> Moderation</a>
    <div class="nav-section-label">System</div>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> View Site</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar" style="background:linear-gradient(135deg,#ef4444,#f97316);">
        <?=mb_strtoupper(mb_substr($me['name'],0,1))?>
      </div>
      <div><div class="user-name"><?=e($me['name'])?></div><div class="user-role">Administrator</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Admin Dashboard</h1>
    </div>
    <div class="topbar-right">
      <span class="badge badge-red">System Admin</span>
    </div>
  </div>

  <div class="page-content">

    <div class="mb-24">
      <h2>Platform Overview 📊</h2>
      <p class="text-muted text-sm">Live statistics across the entire JobPortal platform.</p>
    </div>

    <!-- Stats Row 1 -->
    <div class="stat-grid animate-in">
      <div class="stat-card"><div class="stat-icon blue">👨‍🎓</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['students'])?></div><div class="stat-label">Registered Students</div></div></div>
      <div class="stat-card"><div class="stat-icon indigo">🏢</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['employers'])?></div><div class="stat-label">Employers</div></div></div>
      <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['active_jobs'])?></div><div class="stat-label">Active Jobs</div></div></div>
      <div class="stat-card"><div class="stat-icon purple">📋</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['total_jobs'])?></div><div class="stat-label">Total Jobs Posted</div></div></div>
      <div class="stat-card"><div class="stat-icon amber">⚖️</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['pending_jobs'])?></div><div class="stat-label">Pending Moderation</div></div></div>
    </div>

    <!-- Stats Row 2 — Applications funnel -->
    <div class="stat-grid animate-in animate-delay-1" style="margin-top:0;">
      <div class="stat-card"><div class="stat-icon blue">📩</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['total_apps'])?></div><div class="stat-label">Total Applications</div></div></div>
      <div class="stat-card"><div class="stat-icon amber">⏳</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['pending'])?></div><div class="stat-label">Pending Review</div></div></div>
      <div class="stat-card"><div class="stat-icon indigo">⭐</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['shortlisted'])?></div><div class="stat-label">Shortlisted</div></div></div>
      <div class="stat-card"><div class="stat-icon green">🎉</div><div class="stat-info"><div class="stat-value"><?=number_format($stats['hired'])?></div><div class="stat-label">Hired</div></div></div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;" class="animate-in animate-delay-2">

      <!-- Recent Users -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">👥 Recent Registrations</h3>
          <a href="users.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach($newUsers as $u): ?>
            <tr>
              <td>
                <div class="font-bold text-sm"><?=e($u['name'])?></div>
                <div class="text-xs text-muted"><?=e($u['email'])?></div>
              </td>
              <td>
                <?php $rc=['student'=>'badge-blue','employer'=>'badge-indigo','admin'=>'badge-red']; ?>
                <span class="badge <?=$rc[$u['role']]??'badge-gray'?>"><?=ucfirst($u['role'])?></span>
              </td>
              <td><span class="badge <?=$u['is_active']?'badge-green':'badge-red'?>"><?=$u['is_active']?'Active':'Disabled'?></span></td>
              <td class="text-muted text-sm"><?=time_ago($u['created_at'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Jobs -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">📋 Recent Job Listings</h3>
          <a href="jobs.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Job</th><th>Company</th><th>Status</th><th>Posted</th></tr></thead>
            <tbody>
            <?php foreach($newJobs as $j): ?>
            <tr>
              <td>
                <div class="font-bold text-sm"><?=e($j['title'])?></div>
                <div class="text-xs text-muted"><?=ucfirst(str_replace('-',' ',$j['job_type']))?></div>
              </td>
              <td class="text-sm"><?=e($j['company_name'])?></td>
              <td><span class="badge status-<?=$j['status']?>"><?=ucfirst($j['status'])?></span></td>
              <td class="text-muted text-sm"><?=time_ago($j['created_at'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- Quick links & Chart -->
    <div style="display:grid;grid-template-columns:300px 1fr;gap:22px;align-items:start;" class="mt-24 animate-in animate-delay-3">
      <div class="card card-p">
        <h3 style="margin-bottom:16px;">⚡ Quick Actions</h3>
        <div class="d-flex" style="flex-direction:column;gap:12px;">
          <a href="users.php" class="btn btn-primary btn-block">👥 Manage Users</a>
          <a href="jobs.php" class="btn btn-ghost btn-block">📋 Manage Jobs</a>
          <a href="moderation.php" class="btn btn-ghost btn-block" style="background:rgba(245,158,11,0.1); border-color:#f59e0b; color:#f59e0b;">⚖️ Job Moderation</a>
          <a href="outbox.php" class="btn btn-ghost btn-block">📧 View System Outbox</a>
          <a href="../index.php" class="btn btn-ghost btn-block">🌐 View Public Site</a>
        </div>
      </div>
      <div class="card card-p">
        <h3 style="margin-bottom:16px;">📈 Platform Application Trends</h3>
        <div style="height:250px; position:relative;">
          <canvas id="adminTrendChart"></canvas>
        </div>
      </div>
    </div>

  </div>
</main>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('adminTrendChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?=json_encode($chartLabels)?>,
            datasets: [{
                label: 'Global Applications',
                data: <?=json_encode($chartValues)?>,
                backgroundColor: 'rgba(239,68,68,0.2)',
                borderColor: '#ef4444',
                borderWidth: 2,
                borderRadius: 4
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

  </div>
</main>
</div>
<script src="../assets/script.js"></script>
</body>
</html>
