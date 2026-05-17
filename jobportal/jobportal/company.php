<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$company_id = (int)($_GET['id'] ?? 0);
$comp = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='employer'");
$comp->execute([$company_id]);
$c = $comp->fetch();

if (!$c) { die("Company not found."); }

// Stats
$jobCount = $pdo->query("SELECT COUNT(*) FROM jobs WHERE employer_id=$company_id AND status='active'")->fetchColumn();
$revCount = $pdo->query("SELECT COUNT(*) FROM company_reviews WHERE employer_id=$company_id")->fetchColumn();
$avgRating = $pdo->query("SELECT AVG(rating) FROM company_reviews WHERE employer_id=$company_id")->fetchColumn() ?: 0;

// Active Jobs
$jobs = $pdo->prepare("SELECT * FROM jobs WHERE employer_id=? AND status='active' ORDER BY created_at DESC");
$jobs->execute([$company_id]); $activeJobs = $jobs->fetchAll();

// Reviews
$revs = $pdo->prepare("SELECT r.*, u.name FROM company_reviews r JOIN users u ON r.student_id=u.id WHERE r.employer_id=? ORDER BY r.created_at DESC");
$revs->execute([$company_id]); $reviews = $revs->fetchAll();

// Check if current user can review
$canReview = false;
if (is_logged_in() && current_role() === 'student') {
    $uid = $_SESSION['user_id'];
    $applied = $pdo->prepare("SELECT id FROM applications a JOIN jobs j ON a.job_id=j.id WHERE a.student_id=? AND j.employer_id=?");
    $applied->execute([$uid, $company_id]);
    if ($applied->fetch()) $canReview = true;
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canReview) {
    $rating = (int)($_POST['rating'] ?? 5);
    $review = trim($_POST['review'] ?? '');
    if ($review) {
        $pdo->prepare("INSERT INTO company_reviews (employer_id, student_id, rating, review) VALUES (?, ?, ?, ?)")
            ->execute([$company_id, $uid, $rating, $review]);
        header("Location: company.php?id=$company_id&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($c['company_name']) ?> — JobPortal</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .company-header { background: var(--accent-grad); padding: 80px 40px; color: #fff; text-align: center; border-radius: 0 0 var(--radius-xl) var(--radius-xl); }
    .company-logo { width: 100px; height: 100px; background: rgba(255,255,255,0.2); border: 4px solid #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3rem; margin: 0 auto 20px; }
    .review-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-md); padding: 20px; margin-bottom: 16px; }
    .star { color: var(--warning); }
  </style>
</head>
<body>

<div class="company-header animate-in">
    <div class="company-logo"><?= mb_substr($c['company_name'], 0, 1) ?></div>
    <h1><?= e($c['company_name']) ?></h1>
    <p style="color:rgba(255,255,255,0.8);"><?= e($c['industry'] ?? 'Technology') ?> · <?= e($c['location'] ?? 'Remote') ?></p>
    <div class="d-flex justify-center gap-24 mt-24">
        <div><strong><?= $jobCount ?></strong> <div class="text-xs">Active Jobs</div></div>
        <div><strong><?= round($avgRating, 1) ?>⭐</strong> <div class="text-xs"><?= $revCount ?> Reviews</div></div>
    </div>
</div>

<main class="container" style="padding: 40px;">
    <div class="grid col-2 gap-40">
        <!-- Left: Bio & Reviews -->
        <div>
            <section class="mb-40 animate-in animate-delay-1">
                <h2 class="mb-16">About the Company</h2>
                <div class="card card-p"><?= nl2br(e($c['company_bio'] ?? 'No bio available.')) ?></div>
            </section>

            <section class="animate-in animate-delay-2">
                <h2 class="mb-16">Student Reviews</h2>
                <?php if($canReview): ?>
                    <div class="card card-p mb-24" style="background:rgba(99,102,241,0.05);">
                        <h4>Leave a Review</h4>
                        <form method="POST" class="mt-12">
                            <div class="form-group">
                                <label class="form-label">Rating</label>
                                <select name="rating" class="form-control">
                                    <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                                    <option value="4">⭐⭐⭐⭐ (Good)</option>
                                    <option value="3">⭐⭐⭐ (Average)</option>
                                    <option value="2">⭐⭐ (Poor)</option>
                                    <option value="1">⭐ (Terrible)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Review</label>
                                <textarea name="review" class="form-control" placeholder="Share your experience..." required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Submit Review</button>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if(empty($reviews)): ?>
                    <p class="text-muted">No reviews yet. Be the first!</p>
                <?php else: ?>
                    <?php foreach($reviews as $r): ?>
                        <div class="review-card">
                            <div class="d-flex justify-between mb-8">
                                <div class="font-bold"><?= e($r['name']) ?></div>
                                <div class="star"><?= str_repeat('⭐', $r['rating']) ?></div>
                            </div>
                            <p class="text-sm"><?= nl2br(e($r['review'])) ?></p>
                            <div class="text-xs text-muted mt-8"><?= date('M d, Y', strtotime($r['created_at'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>
        </div>

        <!-- Right: Active Jobs -->
        <aside class="animate-in animate-delay-3">
            <h2 class="mb-16">Open Positions</h2>
            <?php foreach($activeJobs as $job): ?>
                <div class="job-card mb-16">
                    <h4><?= e($job['title']) ?></h4>
                    <div class="text-xs text-muted mb-8">📍 <?= e($job['location']) ?> · <?= ucfirst($job['job_type']) ?></div>
                    <a href="apply.php?job_id=<?= $job['id'] ?>" class="btn btn-outline btn-xs">View & Apply</a>
                </div>
            <?php endforeach; ?>
            <?php if(empty($activeJobs)): ?>
                <p class="text-muted">No active positions at the moment.</p>
            <?php endif; ?>
        </aside>
    </div>
</main>

<script src="assets/script.js"></script>
</body>
</html>
