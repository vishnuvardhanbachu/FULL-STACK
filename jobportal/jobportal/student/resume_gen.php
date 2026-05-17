<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid = $_SESSION['user_id'];
$user = $pdo->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$uid]);
$u = $user->fetch();

if (!$u) die("User not found.");

// Format skills
$skills = explode(',', $u['skills']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Resume — <?= e($u['name']) ?></title>
    <style>
        :root { --primary: #6366f1; --text: #1f2937; --text-light: #6b7280; }
        body { font-family: 'Inter', sans-serif; color: var(--text); line-height: 1.6; padding: 40px; background: #fff; }
        .resume-container { max-width: 800px; margin: 0 auto; }
        header { border-bottom: 2px solid var(--primary); padding-bottom: 20px; margin-bottom: 30px; }
        h1 { margin: 0; color: var(--primary); font-size: 2.5rem; }
        .contact-info { display: flex; gap: 20px; color: var(--text-light); font-size: 0.9rem; margin-top: 10px; }
        section { margin-bottom: 30px; }
        h2 { font-size: 1.2rem; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 15px; }
        .skills-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
        .skill-item { background: #f3f4f6; padding: 5px 10px; border-radius: 4px; font-size: 0.85rem; }
        .bio { font-style: italic; color: var(--text-light); }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        .no-print { margin-bottom: 20px; text-align: right; }
        .btn { background: var(--primary); color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">Download PDF / Print</button>
        <a href="dashboard.php" class="btn" style="background:#6b7280">Back to Dashboard</a>
    </div>

    <div class="resume-container">
        <header>
            <h1><?= e($u['name']) ?></h1>
            <div class="contact-info">
                <span>📧 <?= e($u['email']) ?></span>
                <?php if($u['phone']): ?><span>📱 <?= e($u['phone']) ?></span><?php endif; ?>
                <?php if($u['location']): ?><span>📍 <?= e($u['location']) ?></span><?php endif; ?>
            </div>
        </header>

        <section>
            <h2>Professional Summary</h2>
            <p class="bio"><?= nl2br(e($u['bio'] ?: 'No bio provided.')) ?></p>
        </section>

        <section>
            <h2>Skills & Expertise</h2>
            <div class="skills-grid">
                <?php foreach($skills as $skill): if(trim($skill)): ?>
                    <div class="skill-item"><?= e(trim($skill)) ?></div>
                <?php endif; endforeach; ?>
            </div>
        </section>

        <section>
            <h2>Experience Level</h2>
            <p><?= ucfirst($u['experience_level']) ?></p>
        </section>

        <?php if($u['company_website']): ?>
        <section>
            <h2>Portfolio / Website</h2>
            <a href="<?= e($u['company_website']) ?>" target="_blank"><?= e($u['company_website']) ?></a>
        </section>
        <?php endif; ?>
    </div>
</body>
</html>
