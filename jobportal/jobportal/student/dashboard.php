<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid = $_SESSION['user_id'];

// Stats
$stats = $pdo->prepare("SELECT
    COUNT(*) AS total,
    SUM(status='pending')    AS pending,
    SUM(status='shortlisted') AS shortlisted,
    SUM(status='hired')      AS hired,
    SUM(status='rejected')   AS rejected
  FROM applications WHERE student_id = ?");
$stats->execute([$uid]);
$s = $stats->fetch();

$me_q = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me_q->execute([$uid]); $me = $me_q->fetch();

// Recent applications
$recent = $pdo->prepare(
    "SELECT a.*, j.title, j.job_type, j.location, j.employer_id, u.company_name
     FROM applications a
     JOIN jobs j ON a.job_id = j.id
     JOIN users u ON j.employer_id = u.id
     WHERE a.student_id = ?
     ORDER BY a.applied_at DESC LIMIT 5"
);
$recent->execute([$uid]);
$applications = $recent->fetchAll();

// Pending interviews
$ints = $pdo->prepare(
    "SELECT i.*, j.title, u.company_name FROM interviews i
     JOIN applications a ON i.application_id=a.id
     JOIN jobs j ON a.job_id=j.id
     JOIN users u ON j.employer_id=u.id
     WHERE a.student_id=? AND i.status='suggested'
     ORDER BY i.created_at DESC"
);
$ints->execute([$uid]);
$interviews = $ints->fetchAll();

// Recommended jobs (Smart Match)
$studentSkills = array_filter(array_map('trim', explode(',', strtolower($me['skills'] ?? ''))));

$reco = $pdo->prepare(
    "SELECT j.*, u.company_name FROM jobs j
     JOIN users u ON j.employer_id = u.id
     WHERE j.status = 'active' AND j.is_approved = 1
       AND j.id NOT IN (SELECT job_id FROM applications WHERE student_id = ?)
     ORDER BY j.created_at DESC"
);
$reco->execute([$uid]);
$allJobs = $reco->fetchAll();

foreach ($allJobs as &$job) {
    $jobSkills = array_filter(array_map('trim', explode(',', strtolower($job['skills_required'] ?? ''))));
    $matchScore = 0;
    if (count($jobSkills) > 0 && count($studentSkills) > 0) {
        $intersection = array_intersect($studentSkills, $jobSkills);
        $matchScore = round((count($intersection) / count($jobSkills)) * 100);
    }
    // Boost score slightly if category matches (assumed preference based on industry)
    $job['match_score'] = min(100, $matchScore);
}
// Sort by match score DESC, then by date
usort($allJobs, fn($a, $b) => $b['match_score'] <=> $a['match_score'] ?: $b['created_at'] <=> $a['created_at']);
$recommended = array_slice($allJobs, 0, 6);


// Unread notifications
$notif = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
$notif->execute([$uid]);
$notifications = $notif->fetchAll();
$unread = count($notifications);


// Saved Jobs
$savedQuery = $pdo->prepare(
    "SELECT j.*, u.company_name FROM jobs j 
     JOIN saved_jobs s ON j.id=s.job_id 
     JOIN users u ON j.employer_id=u.id 
     WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 6"
);
$savedQuery->execute([$uid]);
$savedJobs = $savedQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — JobPortal Student</title>
  <link rel="manifest" href="../manifest.json">
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">

<!-- ── Sidebar ─────────────────────────────────────────────── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">💼</div>
    <div>
      <div class="brand-name">JobPortal</div>
      <div class="brand-tag">STUDENT PORTAL</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a class="nav-item active" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="jobs.php"><span class="icon">🔍</span> Browse Jobs</a>
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
      <div class="avatar">
        <?php if ($me['profile_pic'] && file_exists('../uploads/profile_pics/' . $me['profile_pic'])): ?>
          <img src="../uploads/profile_pics/<?= e($me['profile_pic']) ?>" alt="avatar">
        <?php else: ?>
          <?= mb_strtoupper(mb_substr($me['name'],0,1)) ?>
        <?php endif; ?>
      </div>
      <div>
        <div class="user-name"><?= e($me['name']) ?></div>
        <div class="user-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Main ────────────────────────────────────────────────── -->
<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Dashboard</h1>
    </div>
    <div class="topbar-right">
      <div class="notif-btn" id="notifBtn" title="Notifications">
        🔔
        <?php if ($unread): ?><span class="notif-dot"></span><?php endif; ?>
      </div>
      <a href="jobs.php" class="btn btn-primary btn-sm">Find Jobs</a>
    </div>
  </div>

  <div class="page-content">

    <div class="mb-24 d-flex justify-between" style="flex-wrap:wrap; gap:16px;">
      <div>
        <h2>Welcome back, <?= explode(' ', e($me['name']))[0] ?> 👋</h2>
        <p class="text-muted text-sm">Here's your job search overview.</p>
      </div>
      <div>
        <a href="resume_gen.php" class="btn btn-outline">📄 My Resume</a>
        <a href="analyzer.php" class="btn btn-outline" style="border-color:#f59e0b; color:#f59e0b;">✨ AI Analyzer</a>
      </div>
    </div>

    <!-- Stats -->
    <div class="stat-grid animate-in">
      <div class="stat-card">
        <div class="stat-icon indigo">📤</div>
        <div class="stat-info">
          <div class="stat-value"><?= $s['total'] ?></div>
          <div class="stat-label">Applications Sent</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon amber">⏳</div>
        <div class="stat-info">
          <div class="stat-value"><?= $s['pending'] ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon blue">⭐</div>
        <div class="stat-info">
          <div class="stat-value"><?= $s['shortlisted'] ?></div>
          <div class="stat-label">Shortlisted</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon green">🎉</div>
        <div class="stat-info">
          <div class="stat-value"><?= $s['hired'] ?></div>
          <div class="stat-label">Hired</div>
        </div>
      </div>
    </div>

    <!-- Profile completeness -->
    <?php
    $fields = ['phone','location','bio','skills','resume_path'];
    $filled = array_filter($fields, fn($f) => !empty($me[$f]));
    $pct    = round(count($filled) / count($fields) * 100);
    ?>
    <?php if ($pct < 100): ?>
    <div class="card card-p mb-24 animate-in animate-delay-1">
      <div class="d-flex align-center justify-between mb-8">
        <div>
          <h4>🧩 Complete Your Profile</h4>
          <p class="text-sm text-muted">A complete profile gets 3× more visibility</p>
        </div>
        <a href="profile.php" class="btn btn-ghost btn-sm">Edit Profile</a>
      </div>
      <div style="background:var(--bg-3);border-radius:99px;height:6px;overflow:hidden;">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--accent-grad);border-radius:99px;transition:width 0.8s ease;"></div>
      </div>
      <div class="text-sm text-muted mt-8"><?= $pct ?>% complete</div>
    </div>
    <?php endif; ?>

    <?php if(!empty($interviews)): ?>
        <div class="card mb-24 animate-in" style="border-color:var(--primary); background:rgba(99,102,241,0.05);">
          <div class="card-header"><h3 class="card-title">📅 Interview Invitations</h3></div>
          <div class="card-p">
            <div class="grid gap-16">
              <?php foreach($interviews as $i): $slots = json_decode($i['slots_json'], true); ?>
                <div class="d-flex justify-between align-center p-card">
                  <div>
                    <div class="font-bold"><?= e($i['company_name']) ?></div>
                    <div class="text-sm text-muted">Role: <?= e($i['title']) ?></div>
                  </div>
                  <div class="d-flex gap-8">
                    <?php foreach($slots as $s): ?>
                      <button onclick="confirmInterview(<?= $i['id'] ?>, '<?= $s ?>')" class="btn btn-ghost btn-sm">
                        <?= date('M d, H:i', strtotime($s)) ?>
                      </button>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 340px;gap:22px;align-items:start;" class="animate-in animate-delay-2">

      <!-- Recent Applications -->
      <div class="card" id="applications">
        <div class="card-header">
          <h3 class="card-title">📋 Recent Applications</h3>
          <a href="jobs.php" class="btn btn-ghost btn-sm">Browse More</a>
        </div>
        <?php if (empty($applications)): ?>
          <div class="empty-state">
            <div class="icon">📭</div>
            <h3>No applications yet</h3>
            <p>Start applying to jobs to track them here.</p>
            <a href="jobs.php" class="btn btn-primary btn-sm mt-16">Browse Jobs</a>
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Job</th><th>Company</th><th>Status</th><th>Applied</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applications as $app): ?>
                <tr>
                  <td><a href="apply.php?job_id=<?= $app['job_id'] ?>" class="font-bold" style="color:var(--text-primary)"><?= e($app['title']) ?></a>
                    <div class="text-xs text-muted"><?= ucfirst(str_replace('-',' ',$app['job_type'])) ?> · <?= e($app['location']) ?></div>
                  </td>
                  <td><?= e($app['company_name']) ?></td>
                  <td><span class="badge status-<?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span></td>
                  <td class="text-muted text-sm"><?= time_ago($app['applied_at']) ?></td>
                  <td><a href="chat.php?user_id=<?=$app['employer_id']?>&job_id=<?=$app['job_id']?>" class="btn btn-ghost btn-sm">💬 Chat</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

      <!-- Notifications -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">🔔 Notifications</h3>
          <?php if ($unread): ?>
            <span class="badge badge-indigo"><?= $unread ?> new</span>
          <?php endif; ?>
        </div>
        <?php if (empty($notifications)): ?>
          <div class="empty-state" style="padding:30px">
            <div class="icon">🔕</div>
            <h3>All caught up!</h3>
          </div>
        <?php else: ?>
          <div style="padding:8px 0;">
            <?php foreach ($notifications as $n): ?>
            <div style="padding:12px 20px;border-bottom:1px solid var(--glass-border);">
              <div class="d-flex align-center gap-8">
                <?php $icons=['success'=>'✅','info'=>'ℹ️','warning'=>'⚠️','danger'=>'❌']; ?>
                <span><?= $icons[$n['type']] ?? 'ℹ️' ?></span>
                <span class="font-bold text-sm"><?= e($n['title']) ?></span>
              </div>
              <p class="text-xs text-muted mt-8"><?= e($n['message']) ?></p>
              <div class="text-xs text-muted"><?= time_ago($n['created_at']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Saved Jobs -->
    <div class="mt-24 card animate-in animate-delay-2" id="saved">
      <div class="card-header">
        <h3 class="card-title">🔖 Saved Jobs</h3>
        <a href="jobs.php" class="btn btn-ghost btn-sm">Manage All</a>
      </div>
      <?php if (empty($savedJobs)): ?>
        <div class="empty-state" style="padding:40px">
          <div class="icon">🔖</div>
          <h3>No saved jobs</h3>
          <p>Browse jobs and click the bookmark icon to save them.</p>
        </div>
      <?php else: ?>
        <div class="job-grid" style="padding:20px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
          <?php foreach ($savedJobs as $job): ?>
          <div class="job-card" style="border: 1px solid var(--glass-border);">
            <div class="job-card-header">
              <div>
                <h4 class="job-title" style="font-size:0.95rem;"><?= e($job['title']) ?></h4>
                <div class="job-company">🏢 <?= e($job['company_name']) ?></div>
              </div>
            </div>
            <div class="job-card-footer mt-16">
              <span class="text-xs text-muted"><?= time_ago($job['created_at']) ?></span>
              <a href="apply.php?job_id=<?= $job['id'] ?>" class="btn btn-primary btn-sm">Apply</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Recommended Jobs -->
    <?php if (!empty($recommended)): ?>
    <div class="mt-24 animate-in animate-delay-3">
      <div class="d-flex align-center justify-between mb-16">
        <h3>✨ Recommended for You</h3>
        <a href="jobs.php" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="job-grid">
        <?php foreach ($recommended as $job): ?>
        <div class="job-card">
          <div class="job-card-header">
            <div>
              <h4 class="job-title"><?= e($job['title']) ?></h4>
              <div class="job-company">🏢 <?= e($job['company_name']) ?></div>
            </div>
            <div class="job-company-logo"><?= mb_substr($job['company_name'],0,1) ?></div>
          </div>
          <div class="job-meta">
            <span class="job-tag" style="background:rgba(16,185,129,0.15);color:#10b981;">🔥 <?= $job['match_score'] ?>% Match</span>
            <span class="job-tag">📍 <?= e($job['location']) ?></span>
            <span class="job-tag">⏱ <?= ucfirst(str_replace('-',' ',$job['job_type'])) ?></span>
          </div>
          <div class="job-salary"><?= fmt_salary((int)$job['salary_min'],(int)$job['salary_max']) ?></div>
          <div class="job-card-footer">
            <span class="job-posted"><?= time_ago($job['created_at']) ?></span>
            <a href="apply.php?job_id=<?= $job['id'] ?>" class="btn btn-primary btn-sm">Apply</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /page-content -->
</main>
</div><!-- /app-shell -->

<script>
async function confirmInterview(id, slot) {
    if(!confirm(`Confirm interview for ${slot}?`)) return;
    try {
        const res = await fetch('../api.php?action=confirm_interview', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({interview_id: id, slot})
        });
        const data = await res.json();
        if(data.ok) {
            alert("Interview confirmed! It will be moved to your calendar.");
            location.reload();
        }
    } catch(err) { alert("Failed to confirm."); }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
