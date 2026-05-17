<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid = $_SESSION['user_id'];
$me  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $job_id = (int)($_POST['job_id'] ?? 0);

    // Verify ownership
    $own = $pdo->prepare("SELECT id FROM jobs WHERE id=? AND employer_id=?");
    $own->execute([$job_id, $uid]);
    if ($own->fetch()) {
        if ($action === 'close') {
            $pdo->prepare("UPDATE jobs SET status='closed' WHERE id=?")->execute([$job_id]);
        } elseif ($action === 'reopen') {
            $pdo->prepare("UPDATE jobs SET status='active' WHERE id=?")->execute([$job_id]);
        } elseif ($action === 'delete') {
            $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$job_id]);
        } elseif ($action === 'feature') {
            $pdo->prepare("UPDATE jobs SET is_featured=1 WHERE id=?")->execute([$job_id]);
        } elseif ($action === 'unfeature') {
            $pdo->prepare("UPDATE jobs SET is_featured=0 WHERE id=?")->execute([$job_id]);
        }
    }
    header('Location: my-jobs.php'); exit;
}

$filter = $_GET['filter'] ?? 'all';
$sql = "SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count
        FROM jobs j WHERE j.employer_id=?";
$params = [$uid];
if ($filter !== 'all') { $sql .= " AND j.status=?"; $params[] = $filter; }
$sql .= " ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $jobs=$stmt->fetchAll();

$posted = isset($_GET['posted']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Jobs — JobPortal</title>
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
    <a class="nav-item" href="post-job.php"><span class="icon">➕</span> Post a Job</a>
    <a class="nav-item active" href="my-jobs.php"><span class="icon">📋</span> My Jobs</a>
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
      <h1 class="page-title">My Job Listings</h1>
    </div>
    <div class="topbar-right">
      <a href="post-job.php" class="btn btn-primary btn-sm">➕ Post New Job</a>
    </div>
  </div>

  <div class="page-content">
    <?php if($posted): ?><div class="alert alert-success">✅ Job published successfully! Candidates can now find and apply.</div><?php endif; ?>

    <!-- Filter tabs -->
    <div class="filter-bar animate-in mb-24">
      <?php foreach(['all'=>'All Jobs','active'=>'Active','draft'=>'Drafts','closed'=>'Closed'] as $k=>$l): ?>
        <a href="?filter=<?=$k?>" class="filter-chip <?=$filter===$k?'active':''?>"><?=$l?></a>
      <?php endforeach; ?>
    </div>

    <?php if(empty($jobs)): ?>
      <div class="empty-state">
        <div class="icon">📋</div>
        <h3>No jobs found</h3>
        <p>Post your first job listing to start attracting candidates.</p>
        <a href="post-job.php" class="btn btn-primary btn-sm mt-16">Post a Job</a>
      </div>
    <?php else: ?>
    <div class="card animate-in animate-delay-1">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Job Title</th>
              <th>Type</th>
              <th>Location</th>
              <th>Salary</th>
              <th>Applicants</th>
              <th>Status</th>
              <th>Posted</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($jobs as $job): ?>
          <tr>
            <td>
              <div class="font-bold"><?=e($job['title'])?></div>
              <?php if($job['category']): ?><div class="text-xs text-muted"><?=e($job['category'])?></div><?php endif; ?>
            </td>
            <td><span class="badge badge-indigo"><?=ucfirst(str_replace('-',' ',$job['job_type']))?></span></td>
            <td class="text-sm text-muted">📍 <?=e($job['location'])?></td>
            <td class="text-sm"><?=fmt_salary((int)$job['salary_min'],(int)$job['salary_max'])?></td>
            <td>
              <a href="applicants.php?job_id=<?=$job['id']?>" class="btn btn-ghost btn-sm">
                👥 <?=$job['app_count']?>
              </a>
            </td>
            <td><span class="badge status-<?=$job['status']?>"><?=ucfirst($job['status'])?></span></td>
            <td class="text-muted text-sm"><?=time_ago($job['created_at'])?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="<?= $job['is_featured'] ? 'unfeature' : 'feature' ?>">
                <input type="hidden" name="job_id" value="<?=$job['id']?>">
                <button class="btn btn-ghost btn-sm" title="<?= $job['is_featured'] ? 'Remove featured status' : 'Feature this job (premium)' ?>">
                  <?= $job['is_featured'] ? '⭐' : '☆' ?>
                </button>
              </form>
            </td>
            <td>
              <div class="d-flex gap-8">
                <a href="post-job.php?edit=<?=$job['id']?>" class="btn btn-ghost btn-sm">✏️ Edit</a>

                <?php if($job['status']==='active'): ?>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Close this job listing?')">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="job_id" value="<?=$job['id']?>">
                    <button class="btn btn-ghost btn-sm" style="color:var(--warning);">⏸ Close</button>
                  </form>
                <?php elseif($job['status']==='closed' || $job['status']==='draft'): ?>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="reopen">
                    <input type="hidden" name="job_id" value="<?=$job['id']?>">
                    <button class="btn btn-success btn-sm">▶ Publish</button>
                  </form>
                <?php endif; ?>

                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this job and all its applications? This cannot be undone.')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="job_id" value="<?=$job['id']?>">
                  <button class="btn btn-danger btn-sm">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</main>
</div>
<script src="../assets/script.js"></script>
</body>
</html>
