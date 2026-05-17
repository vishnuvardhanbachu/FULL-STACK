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
        match($action) {
            'close'  => $pdo->prepare("UPDATE jobs SET status='closed' WHERE id=?")->execute([$job_id]),
            'reopen' => $pdo->prepare("UPDATE jobs SET status='active' WHERE id=?")->execute([$job_id]),
            'delete' => $pdo->prepare("DELETE FROM jobs WHERE id=?")->execute([$job_id]),
            default  => null
        };
    }
    header('Location: jobs.php?ok=1'); exit;
}
if (isset($_GET['ok'])) $success = 'Action completed.';

$status = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT j.*, u.company_name, u.name AS employer_name,
        (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count
        FROM jobs j JOIN users u ON j.employer_id=u.id WHERE 1=1";
$params = [];
if ($status) { $sql .= " AND j.status=?"; $params[]=$status; }
if ($search) { $sql .= " AND (j.title LIKE ? OR u.company_name LIKE ?)"; $kw="%$search%"; $params=array_merge($params,[$kw,$kw]); }
$sql .= " ORDER BY j.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $jobs=$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Jobs — Admin | JobPortal</title>
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
    <a class="nav-item active" href="jobs.php"><span class="icon">📋</span> Manage Jobs</a>
    <div class="nav-section-label">System</div>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> View Site</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar" style="background:linear-gradient(135deg,#ef4444,#f97316);"><?=mb_strtoupper(mb_substr($me['name'],0,1))?></div>
      <div><div class="user-name"><?=e($me['name'])?></div><div class="user-role">Administrator</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Manage Jobs</h1>
    </div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?=count($jobs)?> listings</span>
    </div>
  </div>

  <div class="page-content">
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <div class="card card-p mb-24 animate-in">
      <form method="GET" class="d-flex gap-12 align-center flex-wrap">
        <input name="q" type="text" class="form-control" style="max-width:260px;" placeholder="🔍 Search job or company…" value="<?=e($search)?>">
        <select name="status" class="form-control" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Statuses</option>
          <?php foreach(['active','closed','draft'] as $st): ?>
            <option value="<?=$st?>" <?=$status===$st?'selected':''?>><?=ucfirst($st)?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if($status||$search): ?><a href="jobs.php" class="btn btn-ghost btn-sm">✕ Reset</a><?php endif; ?>
      </form>
    </div>

    <div class="card animate-in animate-delay-1">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>Job</th><th>Employer</th><th>Type</th><th>Applications</th><th>Status</th><th>Posted</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach($jobs as $job): ?>
          <tr>
            <td>
              <div class="font-bold text-sm"><?=e($job['title'])?></div>
              <?php if($job['category']): ?><div class="text-xs text-muted"><?=e($job['category'])?> · <?=e($job['location'])?></div><?php endif; ?>
            </td>
            <td>
              <div class="text-sm"><?=e($job['company_name'])?></div>
              <div class="text-xs text-muted"><?=e($job['employer_name'])?></div>
            </td>
            <td><span class="badge badge-indigo"><?=ucfirst(str_replace('-',' ',$job['job_type']))?></span></td>
            <td class="text-sm">📩 <?=$job['app_count']?></td>
            <td><span class="badge status-<?=$job['status']?>"><?=ucfirst($job['status'])?></span></td>
            <td class="text-muted text-sm"><?=time_ago($job['created_at'])?></td>
            <td>
              <div class="d-flex gap-8">
                <?php if($job['status']==='active'): ?>
                  <form method="POST" onsubmit="return confirm('Close this job?')">
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="job_id" value="<?=$job['id']?>">
                    <button class="btn btn-ghost btn-sm" style="color:var(--warning);">⏸ Close</button>
                  </form>
                <?php else: ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="reopen">
                    <input type="hidden" name="job_id" value="<?=$job['id']?>">
                    <button class="btn btn-success btn-sm">▶ Open</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Delete this job listing permanently?')">
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

  </div>
</main>
</div>
<script src="../assets/script.js"></script>
</body>
</html>
