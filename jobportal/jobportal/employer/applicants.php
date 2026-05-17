<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid    = $_SESSION['user_id'];
$me     = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
$job_id = (int)($_GET['job_id'] ?? 0);
$success= $error = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['app_id'])) {
    $app_id    = (int)$_POST['app_id'];
    $new_status= $_POST['new_status'] ?? '';
    $allowed   = ['pending','shortlisted','rejected','hired'];

    if (in_array($new_status, $allowed)) {
        // Verify ownership
        $chk = $pdo->prepare(
            "SELECT a.id, a.student_id, j.title FROM applications a
             JOIN jobs j ON a.job_id=j.id
             WHERE a.id=? AND j.employer_id=?"
        );
        $chk->execute([$app_id, $uid]);
        $appl = $chk->fetch();

        if ($appl) {
            $pdo->prepare("UPDATE applications SET status=? WHERE id=?")->execute([$new_status, $app_id]);

            // Notify student
            $msgs = [
                'shortlisted' => ['⭐ You\'ve been shortlisted!', "Congratulations! {$me['company_name']} shortlisted you for \"{$appl['title']}\". Expect to hear more soon!", 'success'],
                'hired'       => ['🎉 Offer Extended!', "Great news! {$me['company_name']} wants to hire you for \"{$appl['title']}\". Congratulations!", 'success'],
                'rejected'    => ['Application Update', "{$me['company_name']} has reviewed your application for \"{$appl['title']}\" and moved forward with other candidates.", 'warning'],
                'pending'     => ['Application Reset', "Your application for \"{$appl['title']}\" has been reset to pending review.", 'info'],
            ];
            if (isset($msgs[$new_status])) {
                [$title, $msg, $type] = $msgs[$new_status];
                notify($pdo, $appl['student_id'], $title, $msg, $type);
                
                // Trigger simulated email workflow
                $userQ = $pdo->prepare("SELECT email, name FROM users WHERE id=?");
                $userQ->execute([$appl['student_id']]);
                $cand = $userQ->fetch();
                if ($cand) {
                    $body = "Hi {$cand['name']},\n\n$msg\n\nBest regards,\n{$me['company_name']} via JobPortal";
                    log_email($pdo, $cand['email'], $cand['name'], $title, $body);
                }
            }
            $success = 'Application status updated!';
        }
    }
    header('Location: applicants.php' . ($job_id ? "?job_id=$job_id" : '') . '&ok=1');
    exit;
}

if (isset($_GET['ok'])) $success = 'Status updated successfully!';

// Get my jobs for filter dropdown
$myJobs = $pdo->prepare("SELECT id,title FROM jobs WHERE employer_id=? ORDER BY created_at DESC");
$myJobs->execute([$uid]); $myJobs=$myJobs->fetchAll();

// Fetch applicants
$sql = "SELECT a.*, j.title AS job_title, j.job_type, j.id AS jid,
        u.name, u.email, u.phone, u.experience_level, u.skills, u.location AS student_location,
        u.resume_path, u.bio, u.video_path, u.username
        FROM applications a
        JOIN jobs j ON a.job_id=j.id
        JOIN users u ON a.student_id=u.id
        WHERE j.employer_id=?";
$params = [$uid];
if ($job_id) { $sql .= " AND j.id=?"; $params[]=$job_id; }

$status_filter = $_GET['status'] ?? '';
if ($status_filter) { $sql .= " AND a.status=?"; $params[]=$status_filter; }

$sql .= " ORDER BY a.applied_at DESC";
$stmt = $pdo->prepare($sql); $stmt->execute($params); $applicants=$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Applicants — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .applicant-card { background:var(--glass-bg); border:1px solid var(--glass-border); border-radius:var(--radius-lg); padding:20px; margin-bottom:14px; transition:var(--transition); }
    .applicant-card:hover { border-color:rgba(99,102,241,0.3); }
    .skill-tag { display:inline-block; background:rgba(99,102,241,0.1); color:var(--accent-3); border-radius:99px; padding:2px 9px; font-size:0.72rem; margin:2px; }
    .cover-letter { background:rgba(255,255,255,0.03); border:1px solid var(--glass-border); border-radius:var(--radius-sm); padding:14px; font-size:0.85rem; color:var(--text-secondary); line-height:1.7; max-height:100px; overflow:hidden; cursor:pointer; position:relative; }
    .cover-letter.expanded { max-height:none; }
    .cover-letter::after { content:'Click to expand'; position:absolute; bottom:0; right:10px; font-size:0.72rem; color:var(--accent-3); }
    .cover-letter.expanded::after { display:none; }
  </style>
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
    <a class="nav-item active" href="applicants.php"><span class="icon">👥</span> Applicants</a>
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
      <h1 class="page-title">Applicants</h1>
    </div>
    <div class="topbar-right">
      <span class="text-muted text-sm"><?=count($applicants)?> candidates</span>
    </div>
  </div>

  <div class="page-content">
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <div class="card card-p mb-24 animate-in">
      <div class="d-flex gap-12 flex-wrap align-center justify-between">
        <div class="d-flex gap-12 align-center">
          <div>
            <label class="form-label">Filter by Job</label>
            <select class="form-control" onchange="location.href='applicants.php?job_id='+this.value+'&amp;status=<?=e($status_filter)?>'">
              <option value="0">All My Jobs</option>
              <?php foreach($myJobs as $j): ?>
                <option value="<?=$j['id']?>" <?=$job_id==$j['id']?'selected':''?>><?=e($j['title'])?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label">Filter by Status</label>
            <select class="form-control" onchange="location.href='applicants.php?job_id=<?=$job_id?>&amp;status='+this.value">
              <option value="">All Statuses</option>
              <?php foreach(['pending','shortlisted','hired','rejected'] as $st): ?>
                <option value="<?=$st?>" <?=$status_filter===$st?'selected':''?>><?=ucfirst($st)?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="d-flex gap-8 align-center" style="margin-top:20px;">
          <a href="?view=list<?= $job_id ? "&job_id=$job_id" : "" ?>" class="btn <?= ($_GET['view']??'') !== 'board' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">📜 List View</a>
          <a href="?view=board<?= $job_id ? "&job_id=$job_id" : "" ?>" class="btn <?= ($_GET['view']??'') === 'board' ? 'btn-primary' : 'btn-ghost' ?> btn-sm">📋 Board View</a>
          <a href="export_csv.php?job_id=<?=$job_id?>&amp;status=<?=e($status_filter)?>" class="btn btn-ghost btn-sm">⬇️ Export CSV</a>
        </div>
      </div>
    </div>

    <?php if(empty($applicants)): ?>
      <div class="empty-state">
        <div class="icon">👥</div>
        <h3>No applicants yet</h3>
        <p>Share your job listings to attract candidates.</p>
      </div>
    <?php elseif(($_GET['view']??'') === 'board'): ?>
      <!-- Kanban Board View -->
      <div class="kanban-board">
        <?php foreach(['pending' => '⏳ Pending', 'shortlisted' => '⭐ Shortlisted', 'hired' => '🎉 Hired', 'rejected' => '❌ Rejected'] as $status => $label): ?>
          <div class="kanban-col">
            <h4 class="kanban-header"><?= $label ?> <span class="badge"><?= count(array_filter($applicants, fn($a) => $a['status'] === $status)) ?></span></h4>
            <div class="kanban-cards">
              <?php foreach($applicants as $app): if($app['status'] !== $status) continue; ?>
                <div class="kanban-card">
                  <div class="font-bold text-sm"><?= e($app['name']) ?></div>
                  <div class="text-xs text-muted"><?= e($app['job_title']) ?></div>
                  <div class="mt-8 d-flex justify-between align-center">
                    <span class="text-xs"><?= time_ago($app['applied_at']) ?></span>
                    <a href="applicants.php?job_id=<?= $job_id ?>&app_id=<?= $app['id'] ?>" class="text-xs" style="color:var(--primary)">View Details</a>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <style>
        .kanban-board { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; }
        .kanban-col { min-width: 280px; flex: 1; background: rgba(255,255,255,0.02); border-radius: var(--radius-lg); padding: 12px; height: fit-content; }
        .kanban-header { margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid var(--glass-border); display: flex; justify-content: space-between; align-items: center; }
        .kanban-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 12px; margin-bottom: 10px; cursor: pointer; transition: 0.2s; }
        .kanban-card:hover { border-color: var(--primary); transform: translateY(-2px); }
      </style>
    <?php else: ?>
      <?php foreach($applicants as $app): ?>
      <div class="applicant-card animate-in">
        <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:start;">

          <div>
            <!-- Candidate Header -->
            <div class="d-flex align-center gap-12 mb-12">
              <div class="avatar" style="width:46px;height:46px;font-size:1.2rem;flex-shrink:0;">
                <?=mb_strtoupper(mb_substr($app['name'],0,1))?>
              </div>
              <div>
                <div class="font-bold"><?=e($app['name'])?></div>
                <div class="text-sm text-muted"><?=e($app['email'])?><?=$app['phone']?' · '.e($app['phone']):'';?></div>
                <?php if($app['student_location']): ?>
                  <div class="text-xs text-muted">📍 <?=e($app['student_location'])?></div>
                <?php endif; ?>
              </div>
              <div>
                <span class="badge status-<?=$app['status']?>"><?=ucfirst($app['status'])?></span>
                <div class="text-xs text-muted mt-8">For: <em><?=e($app['job_title'])?></em></div>
                <?php if($app['username']): ?>
                  <a href="../u.php?username=<?= e($app['username']) ?>" target="_blank" class="btn btn-ghost btn-xs mt-8">🌐 View Portfolio</a>
                <?php endif; ?>
              </div>
            </div>

            <!-- Skills -->
            <?php if($app['skills']): ?>
            <div style="margin-bottom:10px;">
              <?php foreach(array_slice(explode(',',$app['skills']),0,6) as $sk): ?>
                <span class="skill-tag"><?=e(trim($sk))?></span>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Cover letter -->
            <?php if($app['cover_letter']): ?>
            <div class="cover-letter" onclick="this.classList.toggle('expanded')">
              <strong class="text-sm" style="color:var(--text-secondary);">📝 Cover Letter</strong><br>
              <?=nl2br(e($app['cover_letter']))?>
            </div>
            <?php endif; ?>
            <!-- Recruiter Notes -->
            <div class="mt-16">
              <label class="form-label text-xs">📝 Recruiter Notes (Private)</label>
              <textarea class="form-control" placeholder="Add private feedback, interview details..." 
                        style="font-size:0.8rem; background:rgba(255,255,255,0.02); min-height:80px;"
                        onblur="saveNotes(<?=$app['id']?>, this.value)"><?= e($app['employer_notes'] ?? '') ?></textarea>
              <div id="status-<?=$app['id']?>" class="text-xs mt-4 text-muted"></div>
            </div>
          </div>

          <!-- Action Panel -->
          <div style="min-width:200px;">
            <div class="text-xs text-muted mb-8">Applied <?=time_ago($app['applied_at'])?></div>

            <?php if($app['resume_path']): ?>
              <a href="../uploads/resumes/<?=e($app['resume_path'])?>" target="_blank" class="btn btn-ghost btn-sm btn-block mb-8">
                📎 View Resume
              </a>
            <?php else: ?>
              <div class="text-xs text-muted mb-8" style="text-align:center;">No resume uploaded</div>
            <?php endif; ?>

            <a href="chat.php?user_id=<?=$app['student_id']?>&job_id=<?=$app['jid']?>" class="btn btn-outline btn-sm btn-block mb-8">💬 Message</a>

            <?php if($app['video_path']): ?>
              <button onclick="playVideo('<?= e($app['video_path']) ?>')" class="btn btn-ghost btn-sm btn-block mb-8" style="background:rgba(99,102,241,0.1); color:var(--primary);">
                📹 Watch Pitch
              </button>
            <?php endif; ?>

            <button onclick="openInterviewModal(<?= $app['id'] ?>)" class="btn btn-primary btn-sm btn-block mb-8">
              📅 Schedule Interview
            </button>

            <div class="form-group" style="margin-bottom:8px;">
              <label class="form-label">Update Status</label>
              <form method="POST">
                <input type="hidden" name="app_id" value="<?=$app['id']?>">
                <select name="new_status" class="form-control" style="margin-bottom:8px;" onchange="this.form.submit()">
                  <?php foreach(['pending','shortlisted','hired','rejected'] as $st): ?>
                    <option value="<?=$st?>" <?=$app['status']===$st?'selected':''?>><?=ucfirst($st)?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </div>

            <div class="text-xs text-muted">
              Exp: <?=ucfirst($app['experience_level']??'—')?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </div>
</main>
</div>
<!-- Video Modal -->
<div id="videoModal" class="modal-overlay" onclick="closeVideo()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">📹 Student Pitch</h3>
            <button class="modal-close" onclick="closeVideo()">✕</button>
        </div>
        <div class="modal-body">
            <video id="modalVideo" controls style="width:100%; border-radius:12px;">
                <source src="" type="video/mp4">
            </video>
        </div>
    </div>
</div>

<!-- Interview Modal -->
<div id="interviewModal" class="modal-overlay" onclick="closeInterviewModal()">
    <div class="modal" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 class="modal-title">📅 Schedule Interview</h3>
            <button class="modal-close" onclick="closeInterviewModal()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-muted mb-16">Suggest 3 time slots for the candidate to choose from.</p>
            <div class="form-group"><label class="form-label">Slot 1</label><input type="datetime-local" id="slot1" class="form-control"></div>
            <div class="form-group"><label class="form-label">Slot 2</label><input type="datetime-local" id="slot2" class="form-control"></div>
            <div class="form-group"><label class="form-label">Slot 3</label><input type="datetime-local" id="slot3" class="form-control"></div>
            <input type="hidden" id="activeAppId">
            <button onclick="submitInterview()" class="btn btn-primary btn-block">Send Invitations</button>
        </div>
    </div>
</div>

<script>
let currentAppId = null;
function openInterviewModal(appId) {
    currentAppId = appId;
    document.getElementById('interviewModal').classList.add('open');
}
function closeInterviewModal() {
    document.getElementById('interviewModal').classList.remove('open');
}
async function submitInterview() {
    const slots = [
        document.getElementById('slot1').value,
        document.getElementById('slot2').value,
        document.getElementById('slot3').value
    ].filter(s => s !== '');
    
    if(slots.length < 1) return alert("Please select at least one slot.");
    
    try {
        const res = await fetch('../api.php?action=schedule_interview', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({app_id: currentAppId, slots})
        });
        const data = await res.json();
        if(data.ok) {
            alert("Interview invitations sent!");
            closeInterviewModal();
        }
    } catch(err) { alert("Failed to schedule."); }
}

function playVideo(path) {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('modalVideo');
    video.src = '../uploads/videos/' + path;
    modal.classList.add('open');
    video.play();
}
function closeVideo() {
    const modal = document.getElementById('videoModal');
    const video = document.getElementById('modalVideo');
    modal.classList.remove('open');
    video.pause();
}

async function saveNotes(appId, notes) {
    const statusEl = document.getElementById('status-' + appId);
    statusEl.innerText = 'Saving...';
    try {
        const res = await fetch('../api.php?action=update_notes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ app_id: appId, notes: notes })
        });
        const data = await res.json();
        if (data.ok) statusEl.innerText = '✅ Saved';
        else statusEl.innerText = '❌ Failed';
    } catch(e) { statusEl.innerText = '❌ Error'; }
    setTimeout(() => statusEl.innerText = '', 2000);
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
