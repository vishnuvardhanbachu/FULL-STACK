<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('admin');

$uid = $_SESSION['user_id'];
$me  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

$success = $error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = (int)($_POST['target_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($target && $target !== $uid) {
        match($action) {
            'activate'   => $pdo->prepare("UPDATE users SET is_active=1 WHERE id=?")->execute([$target]),
            'deactivate' => $pdo->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([$target]),
            'delete'     => $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$target]),
            default      => null
        };
        $success = 'User updated successfully.';
    }
    header('Location: users.php?ok=1'); exit;
}
if (isset($_GET['ok'])) $success = 'Action completed successfully.';

// Filters
$role   = $_GET['role'] ?? '';
$search = trim($_GET['q'] ?? '');

$sql = "SELECT u.*, (SELECT COUNT(*) FROM applications WHERE student_id=u.id) AS app_count,
        (SELECT COUNT(*) FROM jobs WHERE employer_id=u.id) AS job_count
        FROM users u WHERE 1=1";
$params = [];
if ($role)   { $sql .= " AND u.role=?"; $params[]=$role; }
if ($search) { $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $kw="%$search%"; $params=array_merge($params,[$kw,$kw]); }
$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $users=$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Users — Admin | JobPortal</title>
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
    <a class="nav-item active" href="users.php"><span class="icon">👥</span> Manage Users</a>
    <a class="nav-item" href="jobs.php"><span class="icon">📋</span> Manage Jobs</a>
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
      <h1 class="page-title">Manage Users</h1>
    </div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?=count($users)?> users</span>
    </div>
  </div>

  <div class="page-content">
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <!-- Search & Filters -->
    <div class="card card-p mb-24 animate-in">
      <form method="GET" class="d-flex gap-12 align-center flex-wrap">
        <input name="q" type="text" class="form-control" style="max-width:260px;" placeholder="🔍 Search name or email…" value="<?=e($search)?>">
        <select name="role" class="form-control" style="width:auto;" onchange="this.form.submit()">
          <option value="">All Roles</option>
          <?php foreach(['student','employer','admin'] as $r): ?>
            <option value="<?=$r?>" <?=$role===$r?'selected':''?>><?=ucfirst($r)?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Search</button>
        <?php if($role||$search): ?><a href="users.php" class="btn btn-ghost btn-sm">✕ Reset</a><?php endif; ?>
      </form>
    </div>

    <div class="card animate-in animate-delay-1">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr><th>User</th><th>Role</th><th>Activity</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach($users as $u): ?>
          <tr>
            <td>
              <div class="d-flex align-center gap-12">
                <div class="avatar" style="width:34px;height:34px;font-size:0.85rem;flex-shrink:0;">
                  <?=mb_strtoupper(mb_substr($u['name'],0,1))?>
                </div>
                <div>
                  <div class="font-bold text-sm"><?=e($u['name'])?></div>
                  <div class="text-xs text-muted"><?=e($u['email'])?></div>
                  <?php if($u['company_name']): ?><div class="text-xs text-muted">🏢 <?=e($u['company_name'])?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <?php $rc=['student'=>'badge-blue','employer'=>'badge-indigo','admin'=>'badge-red']; ?>
              <span class="badge <?=$rc[$u['role']]??'badge-gray'?>"><?=ucfirst($u['role'])?></span>
            </td>
            <td class="text-sm text-muted">
              <?php if($u['role']==='student'): ?>
                📩 <?=$u['app_count']?> apps
              <?php elseif($u['role']==='employer'): ?>
                📋 <?=$u['job_count']?> jobs
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?=$u['is_active']?'badge-green':'badge-red'?>">
                <?=$u['is_active']?'Active':'Disabled'?>
              </span>
            </td>
            <td class="text-muted text-sm"><?=time_ago($u['created_at'])?></td>
            <td>
              <?php if($u['id'] !== $uid): ?>
              <div class="d-flex gap-8">
                <?php if($u['is_active']): ?>
                  <form method="POST" onsubmit="return confirm('Deactivate this user?')">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="target_id" value="<?=$u['id']?>">
                    <button class="btn btn-ghost btn-sm" style="color:var(--warning);">⏸ Disable</button>
                  </form>
                <?php else: ?>
                  <form method="POST">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="target_id" value="<?=$u['id']?>">
                    <button class="btn btn-success btn-sm">▶ Enable</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Permanently delete this user and all their data?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="target_id" value="<?=$u['id']?>">
                  <button class="btn btn-danger btn-sm">🗑</button>
                </form>
              </div>
              <?php else: ?>
                <span class="text-xs text-muted">You</span>
              <?php endif; ?>
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
