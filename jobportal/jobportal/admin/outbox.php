<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('admin');

$uid = $_SESSION['user_id'];
$me  = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

$logs = $pdo->query("SELECT * FROM email_logs ORDER BY sent_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Outbox — Admin | JobPortal</title>
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
    <div class="nav-section-label">System</div>
    <a class="nav-item active" href="outbox.php"><span class="icon">📧</span> System Outbox</a>
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
      <h1 class="page-title">Simulated Email Outbox</h1>
    </div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?=count($logs)?> emails sent</span>
    </div>
  </div>

  <div class="page-content">
    <div class="mb-24">
      <p class="text-muted">This page displays all simulated automated emails sent by the JobPortal workflow (e.g., when a candidate is shortlisted or hired). Since XAMPP cannot send real SMTP emails out of the box, we log them here to demonstrate the business logic.</p>
    </div>

    <?php if(empty($logs)): ?>
      <div class="empty-state">
        <div class="icon">📭</div>
        <h3>No emails sent yet</h3>
        <p>Emails will appear here automatically when employers update application statuses.</p>
      </div>
    <?php else: ?>
      <div class="card animate-in">
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Recipient</th><th>Subject</th><th>Body Preview</th><th>Timestamp</th></tr>
            </thead>
            <tbody>
              <?php foreach($logs as $log): ?>
              <tr>
                <td>
                  <div class="font-bold text-sm"><?=e($log['recipient_name'])?></div>
                  <div class="text-xs text-muted"><?=e($log['recipient_email'])?></div>
                </td>
                <td class="font-bold text-sm"><?=e($log['subject'])?></td>
                <td class="text-sm text-muted">
                  <div style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?=e(str_replace("\n", " ", $log['body']))?>
                  </div>
                </td>
                <td class="text-muted text-sm"><?=time_ago($log['sent_at'])?></td>
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
