<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid = $_SESSION['user_id'];
$me = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

// Last 4 weeks stats
$reports = [];
for($i=0; $i<4; $i++) {
    $start = date('Y-m-d', strtotime("-$i week last monday"));
    $end   = date('Y-m-d', strtotime("-$i week next sunday"));
    
    $s = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(status='hired') as hires,
        COUNT(DISTINCT student_id) as unique_cands
        FROM applications a JOIN jobs j ON a.job_id=j.id
        WHERE j.employer_id=? AND a.applied_at BETWEEN ? AND ?");
    $s->execute([$uid, $start, $end]);
    $res = $s->fetch();
    $res['period'] = date('M d', strtotime($start)) . " - " . date('M d', strtotime($end));
    $reports[] = $res;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Hiring Reports — JobPortal</title>
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
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="post-job.php"><span class="icon">➕</span> Post a Job</a>
    <a class="nav-item" href="my-jobs.php"><span class="icon">📋</span> My Jobs</a>
    <a class="nav-item active" href="reports.php"><span class="icon">📊</span> Hiring Reports</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="pricing.php"><span class="icon">💳</span> Pricing & Credits</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
</aside>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left"><h1 class="page-title">Hiring Performance Reports</h1></div>
    <div class="topbar-right">
        <button class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ Export PDF</button>
    </div>
  </div>

  <div class="page-content">
    <div class="section-header">
        <h2>Weekly Growth Metrics</h2>
        <p class="text-muted">Track your recruitment efficiency over time.</p>
    </div>

    <div class="grid col-2 gap-24 mb-40">
        <div class="card card-p animate-in">
            <h3>Applications Trend</h3>
            <canvas id="growthChart" style="margin-top:20px;"></canvas>
        </div>
        <div class="card card-p animate-in animate-delay-1">
            <h3>Weekly Summary</h3>
            <div class="data-table-wrapper mt-16">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Apps</th>
                            <th>Hires</th>
                            <th>Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($reports as $r): ?>
                            <tr>
                                <td><?= $r['period'] ?></td>
                                <td><?= $r['total'] ?></td>
                                <td><?= $r['hires'] ?? 0 ?></td>
                                <td><?= $r['total'] > 0 ? round(($r['hires']/$r['total'])*100, 1) : 0 ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-p animate-in animate-delay-2" style="background:var(--accent-grad); color:#fff;">
        <div class="d-flex justify-between align-center">
            <div>
                <h3>💡 Hiring Tip</h3>
                <p class="text-sm mt-8" style="opacity:0.9;">Jobs with "Featured" badges get 4x more applications on average.</p>
            </div>
            <a href="pricing.php" class="btn btn-ghost btn-sm" style="background:#fff; color:var(--primary);">Upgrade Plan</a>
        </div>
    </div>
  </div>
</main>
</div>

<script>
const ctx = document.getElementById('growthChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_reverse(array_column($reports, 'period'))) ?>,
        datasets: [{
            label: 'Total Applications',
            data: <?= json_encode(array_reverse(array_column($reports, 'total'))) ?>,
            borderColor: '#6366f1',
            backgroundColor: 'rgba(99,102,241,0.1)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } } }
    }
});
</script>
<script src="../assets/script.js"></script>
</body>
</html>
