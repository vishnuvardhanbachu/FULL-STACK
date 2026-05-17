<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid = $_SESSION['user_id'];
$me_q = $pdo->prepare("SELECT * FROM users WHERE id=?");
$me_q->execute([$uid]); $me = $me_q->fetch();

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

        $username= trim($_POST['username'] ?? '');
        $name    = trim($_POST['name'] ?? '');
        $phone   = trim($_POST['phone'] ?? '');
        $loc     = trim($_POST['location'] ?? '');
        $bio     = trim($_POST['bio'] ?? '');
        $skills  = trim($_POST['skills'] ?? '');
        $exp     = $_POST['experience_level'] ?? 'fresher';

        if (!$name) { $error = 'Name is required.'; }
        else {
            // Check username unique
            if ($username) {
                $check = $pdo->prepare("SELECT id FROM users WHERE username=? AND id!=?");
                $check->execute([$username, $uid]);
                if ($check->fetch()) { $error = 'Username is already taken.'; }
            }

            if (!$error) {
                $pdo->prepare("UPDATE users SET username=?,name=?,phone=?,location=?,bio=?,skills=?,experience_level=? WHERE id=?")
                    ->execute([$username,$name,$phone,$loc,$bio,$skills,$exp,$uid]);
                $_SESSION['user_name'] = $name;
                $success = 'Profile updated successfully!';
                $me_q->execute([$uid]); $me = $me_q->fetch();
            }
        }
    }

    if ($action === 'video') {
        $file = $_FILES['video'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid video file.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['mp4','webm','mov'])) {
                $error = 'Only MP4, WebM, and MOV videos are allowed.';
            } elseif ($file['size'] > 15 * 1024 * 1024) {
                $error = 'Video must be under 15 MB.';
            } else {
                $dir = __DIR__ . '/../uploads/videos/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if ($me['video_path'] && file_exists($dir . $me['video_path']))
                    unlink($dir . $me['video_path']);
                $fname = 'pitch_' . $uid . '_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $dir . $fname);
                $pdo->prepare("UPDATE users SET video_path=? WHERE id=?")->execute([$fname, $uid]);
                $success = 'Video pitch uploaded successfully!';
                $me_q->execute([$uid]); $me = $me_q->fetch();
            }
        }
    }

    if ($action === 'resume') {
        $file = $_FILES['resume'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid file.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf','doc','docx'])) {
                $error = 'Only PDF, DOC, and DOCX files are allowed.';
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $error = 'File size must be under 5 MB.';
            } else {
                $dir = __DIR__ . '/../uploads/resumes/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                // Delete old
                if ($me['resume_path'] && file_exists($dir . $me['resume_path']))
                    unlink($dir . $me['resume_path']);
                $fname = 'resume_' . $uid . '_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $dir . $fname);
                $pdo->prepare("UPDATE users SET resume_path=? WHERE id=?")->execute([$fname, $uid]);
                $success = 'Resume uploaded successfully!';
                $me_q->execute([$uid]); $me = $me_q->fetch();
            }
        }
    }

    if ($action === 'pic') {
        $file = $_FILES['profile_pic'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please select a valid image.';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
                $error = 'Only image files are allowed (JPG, PNG, GIF, WebP).';
            } elseif ($file['size'] > 3 * 1024 * 1024) {
                $error = 'Image must be under 3 MB.';
            } else {
                $dir = __DIR__ . '/../uploads/profile_pics/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                if ($me['profile_pic'] && file_exists($dir . $me['profile_pic']))
                    unlink($dir . $me['profile_pic']);
                $fname = 'pic_' . $uid . '_' . time() . '.' . $ext;
                move_uploaded_file($file['tmp_name'], $dir . $fname);
                $pdo->prepare("UPDATE users SET profile_pic=? WHERE id=?")->execute([$fname,$uid]);
                $success = 'Profile picture updated!';
                $me_q->execute([$uid]); $me = $me_q->fetch();
            }
        }
    }

    if ($action === 'password') {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $cnf = $_POST['confirm_password'] ?? '';
        if (!password_verify($cur, $me['password_hash'])) { $error = 'Current password is incorrect.'; }
        elseif (strlen($new) < 6) { $error = 'New password must be at least 6 characters.'; }
        elseif ($new !== $cnf) { $error = 'Passwords do not match.'; }
        else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([password_hash($new,PASSWORD_DEFAULT),$uid]);
            $success = 'Password changed successfully!';
        }
    }
}

// Application history
$apps = $pdo->prepare(
    "SELECT a.*,j.title,j.job_type,j.location,u.company_name FROM applications a
     JOIN jobs j ON a.job_id=j.id JOIN users u ON j.employer_id=u.id
     WHERE a.student_id=? ORDER BY a.applied_at DESC"
);
$apps->execute([$uid]); $applications=$apps->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<div class="app-shell">

<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">💼</div>
    <div><div class="brand-name">JobPortal</div><div class="brand-tag">STUDENT PORTAL</div></div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
    <a class="nav-item" href="jobs.php"><span class="icon">🔍</span> Browse Jobs</a>
    <a class="nav-item" href="dashboard.php#applications"><span class="icon">📋</span> My Applications</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item active" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar">
        <?php if($me['profile_pic'] && file_exists('../uploads/profile_pics/'.$me['profile_pic'])): ?>
          <img src="../uploads/profile_pics/<?=e($me['profile_pic'])?>" alt="">
        <?php else: ?>
          <?=mb_strtoupper(mb_substr($me['name'],0,1))?>
        <?php endif; ?>
      </div>
      <div><div class="user-name"><?=e($me['name'])?></div><div class="user-role">Student</div></div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">My Profile</h1>
    </div>
  </div>

  <div class="page-content">
    <?php if($error): ?><div class="alert alert-danger">⚠️ <?=e($error)?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success">✅ <?=e($success)?></div><?php endif; ?>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:22px;align-items:start;">

      <!-- Left column -->
      <div>
        <!-- Profile Card -->
        <div class="card card-p text-center animate-in mb-16">
          <div class="avatar" style="width:80px;height:80px;font-size:2rem;margin:0 auto 14px;">
            <?php if($me['profile_pic'] && file_exists('../uploads/profile_pics/'.$me['profile_pic'])): ?>
              <img src="../uploads/profile_pics/<?=e($me['profile_pic'])?>" alt="">
            <?php else: ?>
              <?=mb_strtoupper(mb_substr($me['name'],0,1))?>
            <?php endif; ?>
          </div>
          <h3 style="margin-bottom:4px;"><?=e($me['name'])?></h3>
          <div class="text-muted text-sm"><?=e($me['email'])?></div>
          <?php if($me['location']): ?><div class="text-muted text-xs mt-8">📍 <?=e($me['location'])?></div><?php endif; ?>
          <span class="badge badge-indigo mt-8"><?=ucfirst($me['experience_level']??'fresher')?></span>
        </div>

        <!-- Upload Profile Pic -->
        <div class="card card-p animate-in animate-delay-1 mb-16">
          <h4 style="margin-bottom:12px;">🖼 Profile Picture</h4>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="pic">
            <div class="form-group">
              <input type="file" name="profile_pic" class="form-control" accept="image/*">
              <p class="form-hint">JPG/PNG/WebP, max 3 MB</p>
            </div>
            <button type="submit" class="btn btn-ghost btn-sm btn-block">Upload Photo</button>
          </form>
        </div>

        <!-- Video Pitch Upload -->
        <div class="card card-p animate-in animate-delay-2 mb-16">
          <h4 style="margin-bottom:12px;">📹 Video Intro Pitch</h4>
          <?php if($me['video_path']): ?>
            <div class="alert alert-success" style="margin-bottom:12px; font-size:0.75rem;">
              ✅ <strong>Uploaded:</strong> pitch_<?= $uid ?>.mp4
            </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="video">
            <div class="form-group">
              <input type="file" name="video" class="form-control" accept="video/mp4,video/webm">
              <p class="form-hint">MP4/WebM, max 15 MB (30s rec.)</p>
            </div>
            <button type="submit" class="btn btn-ghost btn-sm btn-block">Upload Video</button>
          </form>
        </div>

        <!-- Portfolio Link -->
        <div class="card card-p animate-in animate-delay-3" style="background:var(--accent-grad); color:#fff;">
          <h4 style="margin-bottom:8px;">🚀 Your Public Portfolio</h4>
          <p class="text-xs mb-16" style="opacity:0.9;">Share this link with employers or on social media.</p>
          <?php if($me['username']): ?>
            <input type="text" class="form-control" style="background:rgba(255,255,255,0.2); border:none; color:#fff; font-size:0.75rem;" value="<?= (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/u.php?username=' . e($me['username']) ?>" readonly onclick="this.select()">
            <a href="../u.php?username=<?= e($me['username']) ?>" target="_blank" class="btn btn-ghost btn-sm btn-block mt-12" style="background:#fff; color:var(--primary);">View Portfolio</a>
          <?php else: ?>
            <p class="text-xs">Set a <strong>Username</strong> below to generate your public portfolio link.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right column -->
      <div>
        <!-- Edit Profile -->
        <div class="card animate-in animate-delay-1 mb-16">
          <div class="card-header"><h3 class="card-title">✏️ Edit Profile</h3></div>
          <div class="card-p">
            <form method="POST">
              <input type="hidden" name="action" value="profile">
              <div class="form-row col-2">
                <div class="form-group">
                  <label class="form-label">Full Name *</label>
                  <input name="name" type="text" class="form-control" value="<?=e($me['name'])?>" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Username (for Portfolio) *</label>
                  <input name="username" type="text" class="form-control" placeholder="john-doe" value="<?=e($me['username']??'')?>" required>
                </div>
              </div>
              <div class="form-row col-2">
                <div class="form-group">
                  <label class="form-label">Phone</label>
                  <input name="phone" type="text" class="form-control" placeholder="+91 XXXXX XXXXX" value="<?=e($me['phone']??'')?>">
                </div>
              <div class="form-row col-2">
                <div class="form-group">
                  <label class="form-label">Location</label>
                  <input name="location" type="text" class="form-control" placeholder="City, State" value="<?=e($me['location']??'')?>">
                </div>
                <div class="form-group">
                  <label class="form-label">Experience Level</label>
                  <select name="experience_level" class="form-control">
                    <?php foreach(['fresher','junior','mid','senior'] as $x): ?>
                      <option value="<?=$x?>" <?=($me['experience_level']??'')===$x?'selected':''?>><?=ucfirst($x)?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Skills <span class="text-muted">(comma separated)</span></label>
                <input name="skills" id="skillsInput" type="text" class="form-control"
                       placeholder="e.g. React, Python, MySQL, Communication"
                       value="<?=e($me['skills']??'')?>">
              </div>
              <div class="form-group">
                <label class="form-label">Bio / Summary</label>
                <textarea name="bio" class="form-control" rows="4"
                          placeholder="Tell employers about yourself, your goals, and what makes you stand out…"><?=e($me['bio']??'')?></textarea>
              </div>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </form>
          </div>
        </div>

        <!-- Change Password -->
        <div class="card animate-in animate-delay-2 mb-16">
          <div class="card-header"><h3 class="card-title">🔐 Change Password</h3></div>
          <div class="card-p">
            <form method="POST">
              <input type="hidden" name="action" value="password">
              <div class="form-row col-3">
                <div class="form-group">
                  <label class="form-label">Current Password</label>
                  <input name="current_password" type="password" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">New Password</label>
                  <input name="new_password" type="password" class="form-control" required>
                </div>
                <div class="form-group">
                  <label class="form-label">Confirm Password</label>
                  <input name="confirm_password" type="password" class="form-control" required>
                </div>
              </div>
              <button type="submit" class="btn btn-ghost">Update Password</button>
            </form>
          </div>
        </div>

        <!-- Application History -->
        <div class="card animate-in animate-delay-3">
          <div class="card-header">
            <h3 class="card-title">📋 Application History</h3>
            <span class="badge badge-indigo"><?=count($applications)?></span>
          </div>
          <?php if(empty($applications)): ?>
            <div class="empty-state" style="padding:30px">
              <div class="icon">📭</div><h3>No applications yet</h3>
              <a href="jobs.php" class="btn btn-primary btn-sm mt-8">Browse Jobs</a>
            </div>
          <?php else: ?>
            <div class="table-wrap">
              <table class="data-table">
                <thead><tr><th>Job</th><th>Company</th><th>Status</th><th>Applied</th></tr></thead>
                <tbody>
                  <?php foreach($applications as $app): ?>
                  <tr>
                    <td>
                      <div class="font-bold text-sm"><?=e($app['title'])?></div>
                      <div class="text-xs text-muted"><?=ucfirst(str_replace('-',' ',$app['job_type']))?> · <?=e($app['location'])?></div>
                    </td>
                    <td class="text-sm"><?=e($app['company_name'])?></td>
                    <td><span class="badge status-<?=$app['status']?>"><?=ucfirst($app['status'])?></span></td>
                    <td class="text-muted text-sm"><?=time_ago($app['applied_at'])?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>
</main>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
// PDF.js Resume Parsing Logic
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

document.getElementById('resumeInput')?.addEventListener('change', async function(e) {
  const file = e.target.files[0];
  if(file && file.type === 'application/pdf') {
    const reader = new FileReader();
    reader.onload = async function() {
      try {
        const typedarray = new Uint8Array(this.result);
        const pdf = await pdfjsLib.getDocument(typedarray).promise;
        let fullText = '';
        for (let i = 1; i <= pdf.numPages; i++) {
          const page = await pdf.getPage(i);
          const textContent = await page.getTextContent();
          fullText += textContent.items.map(s => s.str).join(' ');
        }
        
        const knownSkills = ['react','javascript','php','mysql','python','java','html','css','node.js','aws','docker','express','typescript','mongodb','sql','c++','c#','linux'];
        let foundSkills = [];
        const lowerText = fullText.toLowerCase();
        knownSkills.forEach(skill => {
          // Use regex bound to avoid sub-word matching (e.g. "java" in "javascript")
          const regex = new RegExp('\\b' + skill.replace('.', '\\.') + '\\b');
          if(regex.test(lowerText)) foundSkills.push(skill);
        });
        
        if(foundSkills.length > 0) {
          const skillsInput = document.getElementById('skillsInput');
          // merge old and new, and unique it
          let existing = skillsInput.value.split(',').map(s=>s.trim().toLowerCase()).filter(s=>s!=='');
          let merged = [...new Set([...existing, ...foundSkills])];
          skillsInput.value = merged.map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(', ');
          alert('✨ Magic Parse: We detected some skills from your PDF and updated the Skills field! Don\'t forget to click "Save Changes" on your profile.');
          skillsInput.style.boxShadow = '0 0 0 3px rgba(16,185,129,0.3)';
          setTimeout(() => skillsInput.style.boxShadow='', 2000);
        }
      } catch(err) {
        console.error("PDF Parsing Error:", err);
      }
    };
    reader.readAsArrayBuffer(file);
  }
});
</script>
<script src="../assets/script.js"></script>
</body>
</html>
