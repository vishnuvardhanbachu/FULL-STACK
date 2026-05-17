<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid = $_SESSION['user_id'];
$me = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

$other_user = (int)($_GET['user_id'] ?? 0);
$job_id = (int)($_GET['job_id'] ?? 0);

if (!$other_user || !$job_id) {
    die("Invalid parameters");
}

// Get student info
$stq = $pdo->prepare("SELECT name FROM users WHERE id=?"); $stq->execute([$other_user]); $student=$stq->fetch();
$jq = $pdo->prepare("SELECT title FROM jobs WHERE id=?"); $jq->execute([$job_id]); $job=$jq->fetch();
if(!$student || !$job) die("Not found");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with <?=e($student['name'])?> — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .chat-box { background:var(--bg-1); border:1px solid var(--glass-border); border-radius:var(--radius-lg); height:60vh; display:flex; flex-direction:column; overflow:hidden; }
    .chat-header { padding:16px 20px; border-bottom:1px solid var(--glass-border); background:var(--bg-2); font-weight:bold; d-flex; justify-content:space-between; align-items:center; }
    .messages-area { flex:1; padding:20px; overflow-y:auto; display:flex; flex-direction:column; gap:12px; }
    .msg { max-width:75%; padding:10px 16px; border-radius:18px; line-height:1.4; position:relative; word-wrap:break-word; }
    .msg.me { align-self:flex-end; background:var(--accent-1); color:#fff; border-bottom-right-radius:4px; }
    .msg.them { align-self:flex-start; background:var(--bg-3); color:var(--text-primary); border-bottom-left-radius:4px; }
    .msg-time { display:block; font-size:0.7rem; opacity:0.7; margin-top:4px; text-align:right; }
    .chat-input-area { padding:14px; border-top:1px solid var(--glass-border); background:var(--bg-2); display:flex; gap:10px; }
    .chat-input-area input { flex:1; }
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
      <h1 class="page-title">Direct Message</h1>
    </div>
    <div class="topbar-right">
      <a href="applicants.php?job_id=<?=$job_id?>" class="btn btn-ghost btn-sm">⬅ Back to Applicants</a>
    </div>
  </div>

  <div class="page-content">
    <div class="chat-box animate-in">
      <div class="chat-header">
        <div>
          <div style="font-size:1.1rem;"><?=e($student['name'])?></div>
          <div style="font-size:0.8rem;color:var(--text-muted);font-weight:normal;">Regarding: <?=e($job['title'])?></div>
        </div>
      </div>
      <div class="messages-area" id="messagesArea">
        <!-- Messages will be loaded here via JS -->
      </div>
      <div id="typing" class="typing-indicator" style="display:none">
        <span>Candidate is typing</span>
        <span class="dot"></span><span class="dot"></span><span class="dot"></span>
      </div>
      <form class="chat-input-area" id="chatForm" onsubmit="sendMessage(event)">
        <input type="text" id="msgInput" class="form-control" placeholder="Type a message..." autocomplete="off" required>
        <button type="submit" class="btn btn-primary">Send</button>
      </form>
    </div>
  </div>
</main>
</div>

<script>
const uid = <?=$uid?>;
const other_user = <?=$other_user?>;
const job_id = <?=$job_id?>;
const messagesArea = document.getElementById('messagesArea');

async function loadMessages() {
    try {
        const res = await fetch(`../api.php?action=get_messages&other_user=${other_user}&job_id=${job_id}`);
        const data = await res.json();
        
        if (data.messages) {
            messagesArea.innerHTML = '';
            data.messages.forEach(m => {
                const isMe = parseInt(m.sender_id) === uid;
                const cls = isMe ? 'me' : 'them';
                messagesArea.insertAdjacentHTML('beforeend', `
                    <div class="msg ${cls}">
                      <div>${m.message}</div>
                    </div>
                `);
            });
            messagesArea.scrollTop = messagesArea.scrollHeight;
        }
    } catch(err) {
        console.error("Load MSGs error: ", err);
    }
}

async function sendMessage(e) {
    if(e) e.preventDefault();
    const input = document.getElementById('msgInput');
    const msg = input.value.trim();
    if(!msg) return;

    input.value = '';
    
    // Add optimistically
    messagesArea.insertAdjacentHTML('beforeend', `
        <div class="msg me" style="opacity:0.7">
          <div>${msg}</div>
        </div>
    `);
    messagesArea.scrollTop = messagesArea.scrollHeight;

    try {
        const res = await fetch('../api.php?action=send_message', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({other_user, job_id, message: msg})
        });
        const data = await res.json();
        if(data.ok) {
            loadMessages(); // reload to get proper IDs and times
        }
    } catch(err) {
        alert("Failed to send message.");
    }
}

// Polling
loadMessages();
setInterval(loadMessages, 3000);

document.getElementById('msgInput').addEventListener('focus', () => {
    document.getElementById('typing').style.display = 'flex';
    setTimeout(() => { document.getElementById('typing').style.display = 'none'; }, 2000);
});

</script>
<script src="../assets/script.js"></script>
</body>
</html>
