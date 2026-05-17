<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('admin');

$uid = $_SESSION['user_id'];
$me  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = (int)($_POST['job_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($job_id) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE jobs SET is_approved=1, status='active' WHERE id=?")->execute([$job_id]);
            // Notify employer
            $job = $pdo->prepare("SELECT employer_id, title FROM jobs WHERE id=?");
            $job->execute([$job_id]); $j = $job->fetch();
            notify($pdo, $j['employer_id'], "✅ Job Approved", "Your job posting '{$j['title']}' has been approved and is now live.", 'success');
            $success = 'Job approved and is now live.';
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE jobs SET is_approved=0, status='draft' WHERE id=?")->execute([$job_id]);
            $success = 'Job rejected and moved back to drafts.';
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$job_id]);
            $success = 'Job deleted.';
        }
    }
}

// Fetch pending jobs
$stmt = $pdo->query("SELECT j.*, u.company_name, u.name AS employer_name 
                    FROM jobs j JOIN users u ON j.employer_id=u.id 
                    WHERE j.is_approved=0 ORDER BY j.created_at DESC");
$pending = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Job Moderation — Admin</title>
  <link rel="stylesheet" href="../assets/style.css">
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
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="users.php"><span class="icon">👥</span> Manage Users</a>
    <a class="nav-item" href="jobs.php"><span class="icon">📋</span> Manage Jobs</a>
    <a class="nav-item active" href="moderation.php"><span class="icon">⚖️</span> Moderation</a>
    <div class="nav-section-label">System</div>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> View Site</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar" style="background:var(--danger);"><?=mb_strtoupper(mb_substr($me['name'],0,1))?></div>
      <div><div class="user-name"><?=e($me['name'])?></div><div class="user-role">Administrator</div></div>
    </div>
  </div>
</aside>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Job Moderation</h1>
    </div>
    <div class="topbar-right">
        <span class="badge badge-indigo"><?= count($pending) ?> pending</span>
    </div>
  </div>

  <div class="page-content">
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <?php if(empty($pending)): ?>
      <div class="empty-state">
        <div class="icon">✅</div>
        <h3>No jobs pending moderation</h3>
        <p>All job postings are currently up to date.</p>
      </div>
    <?php else: ?>
      <div class="job-grid">
        <?php foreach($pending as $job): ?>
          <div class="card card-p animate-in">
            <div class="d-flex justify-between align-start">
              <div>
                <h4 class="font-bold"><?= e($job['title']) ?></h4>
                <div class="text-sm text-muted">🏢 <?= e($job['company_name']) ?> (<?= e($job['employer_name']) ?>)</div>
                <div class="text-xs text-muted mt-4">📍 <?= e($job['location']) ?> · ⏱ <?= ucfirst($job['job_type']) ?></div>
              </div>
              <span class="badge status-pending">Pending Approval</span>
            </div>
            
            <div class="mt-16 text-sm text-secondary" style="background:rgba(255,255,255,0.02); padding:10px; border-radius:4px; max-height:100px; overflow-y:auto;">
                <strong>Description:</strong><br>
                <?= nl2br(e(mb_strimwidth($job['description'], 0, 300, "..."))) ?>
            </div>

            <div class="d-flex gap-8 mt-16">
              <form method="POST" style="flex:1;">
                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-success btn-block">✅ Approve</button>
              </form>
              <form method="POST" style="flex:1;">
                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn btn-outline btn-block" style="color:var(--warning); border-color:var(--warning);">❌ Reject</button>
              </form>
              <form method="POST" onsubmit="return confirm('Delete this job permanently?')">
                <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn btn-danger">🗑</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</div>
<script src="../assets/script.js"></script>
</body>
</html>
