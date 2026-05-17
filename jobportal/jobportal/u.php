<?php
require_once 'config.php';
$username = $_GET['username'] ?? '';
if (!$username) die("Username required");

// Fetch user data via API logic (internal call)
$stmt = $pdo->prepare("SELECT * FROM users WHERE username=?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'student') die("Profile not found");

// Fetch verified skills
$ass = $pdo->prepare("SELECT skill, score FROM assessments WHERE user_id=? AND score >= 70");
$ass->execute([$user['id']]);
$verified = $ass->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($user['name']) ?> | Portfolio</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        body { background: var(--bg-0); overflow-x: hidden; }
        .portfolio-container { max-width: 900px; margin: 60px auto; padding: 20px; }
        .hero-section { text-align: center; margin-bottom: 40px; }
        .profile-vcard { 
            background: var(--glass-bg); 
            border: 1px solid var(--glass-border); 
            border-radius: 30px; 
            padding: 40px; 
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        .profile-vcard::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(99,102,241,0.1) 0%, transparent 70%);
            z-index: -1;
        }
        .p-avatar { width: 120px; height: 120px; border-radius: 50%; margin: 0 auto 20px; border: 4px solid var(--primary); padding: 5px; }
        .p-name { font-size: 2.5rem; font-weight: 800; margin-bottom: 10px; }
        .p-tagline { font-size: 1.1rem; color: var(--text-muted); margin-bottom: 20px; }
        
        .p-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 40px; }
        .p-card { background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 24px; }
        
        .badge-verified { background: linear-gradient(135deg, #10b981, #3b82f6); color: #fff; padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: bold; }
        
        .video-box { width: 100%; border-radius: var(--radius-md); overflow: hidden; border: 1px solid var(--glass-border); background: #000; aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; }
        
        @media (max-width: 768px) { .p-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="portfolio-container animate-in">
        <div class="profile-vcard">
            <div class="hero-section">
                <div class="p-avatar">
                    <div style="width:100%; height:100%; background:var(--accent-grad); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:3rem; color:#fff;">
                        <?= mb_strtoupper(mb_substr($user['name'],0,1)) ?>
                    </div>
                </div>
                <h1 class="p-name"><?= e($user['name']) ?></h1>
                <p class="p-tagline">📍 <?= e($user['location'] ?? 'Remote') ?> · <?= ucfirst($user['experience_level']) ?> Professional</p>
                
                <div class="d-flex justify-center gap-12">
                    <?php if($user['social_links']): $socials = json_decode($user['social_links'], true); foreach($socials as $plat => $url): ?>
                        <a href="<?= e($url) ?>" class="btn btn-ghost btn-sm"><?= ucfirst($plat) ?></a>
                    <?php endforeach; endif; ?>
                    <a href="mailto:<?= e($user['email']) ?>" class="btn btn-primary btn-sm">📧 Get in Touch</a>
                </div>
            </div>

            <div class="p-grid">
                <!-- Left: Bio & Skills -->
                <div>
                    <div class="p-card mb-24">
                        <h3 class="mb-12">👤 About Me</h3>
                        <p class="text-secondary" style="line-height:1.7;"><?= nl2br(e($user['bio'] ?? 'No bio provided.')) ?></p>
                    </div>
                    <div class="p-card">
                        <h3 class="mb-16">🛠️ Skills</h3>
                        <div class="d-flex flex-wrap gap-8">
                            <?php foreach(explode(',', $user['skills']) as $sk): if(!trim($sk)) continue; ?>
                                <span class="job-tag"><?= e(trim($sk)) ?></span>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if(!empty($verified)): ?>
                            <h4 class="mt-24 mb-12">✅ Verified Skills</h4>
                            <div class="d-flex flex-wrap gap-8">
                                <?php foreach($verified as $v): ?>
                                    <span class="badge-verified">⭐ <?= e($v['skill']) ?> (<?= $v['score'] ?>%)</span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Right: Video Pitch -->
                <div>
                    <div class="p-card">
                        <h3 class="mb-16">📹 Video Intro</h3>
                        <div class="video-box">
                            <?php if($user['video_path']): ?>
                                <video controls style="width:100%; height:100%;">
                                    <source src="uploads/videos/<?= e($user['video_path']) ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            <?php else: ?>
                                <div class="text-muted text-sm">No video intro uploaded yet.</div>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs text-muted mt-12">Watch a 30-second introduction to learn more about my background and goals.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-40">
            <p class="text-muted text-sm">Built with <span style="color:#ef4444;">❤</span> on JobPortal</p>
            <a href="index.php" class="btn btn-ghost btn-sm mt-12">← Back to Job Board</a>
        </div>
    </div>
</body>
</html>
