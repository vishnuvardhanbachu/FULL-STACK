<?php
/**
 * Job Portal — JSON API
 * All endpoints require a valid session (some require specific roles).
 * POST body: JSON or form-data depending on endpoint.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

$method   = $_SERVER['REQUEST_METHOD'];
$action   = $_GET['action'] ?? '';

// Helper: send JSON and exit
function resp(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// ── Public endpoints ──────────────────────────────────────────
if ($action === 'jobs') {
    $q    = trim($_GET['q'] ?? '');
    $loc  = trim($_GET['location'] ?? '');
    $type = trim($_GET['type'] ?? '');
    $cat  = trim($_GET['category'] ?? '');
    $limit= min((int)($_GET['limit'] ?? 20), 50);
    $offset = max((int)($_GET['offset'] ?? 0), 0);

    $sql = "SELECT j.id,j.title,j.location,j.job_type,j.salary_min,j.salary_max,
                   j.experience_level,j.category,j.deadline,j.created_at,
                   u.company_name FROM jobs j JOIN users u ON j.employer_id=u.id
            WHERE j.status='active'";
    $p = [];
    if ($q)    { $sql .= " AND (j.title LIKE ? OR j.skills_required LIKE ?)"; $kw="%$q%"; $p=array_merge($p,[$kw,$kw]); }
    if ($loc)  { $sql .= " AND j.location LIKE ?"; $p[]="%$loc%"; }
    if ($type) { $sql .= " AND j.job_type=?"; $p[]=$type; }
    if ($cat)  { $sql .= " AND j.category=?"; $p[]=$cat; }
    
    // Add sorting and pagination
    $sql .= " ORDER BY j.created_at DESC LIMIT $limit OFFSET $offset";
    
    global $pdo;
    $stmt = $pdo->prepare($sql); $stmt->execute($p);
    
    // Generate basic HTML cards for infinite scroll injection
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $html = '';
    require_once __DIR__ . '/auth.php'; // ensure fmt_salary and time_ago are available
    
    // Determine context URL
    $isLoggedIn = is_logged_in();
    $role = current_role();
    
    foreach($jobs as $job) {
        $applyUrl = 'apply.php?job_id='.$job['id'];
        // If the request comes from index.php (e.g., origin doesn't have /student/)
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/student/') === false) {
            $applyUrl = ($isLoggedIn && $role === 'student') ? 'student/apply.php?job_id='.$job['id'] : 'login.php';
        }

        $html .= '<div class="job-card animate-in">';
        $html .= '<div class="job-card-header"><div><h4 class="job-title">'.e($job['title']).'</h4><div class="job-company">🏢 '.e($job['company_name']).'</div></div><div class="job-company-logo">'.mb_substr($job['company_name'],0,1).'</div></div>';
        $html .= '<div class="job-meta"><span class="job-tag">📍 '.e($job['location']).'</span><span class="job-tag">⏱ '.ucfirst($job['job_type']).'</span></div>';
        $html .= '<div class="job-salary">'.fmt_salary((int)$job['salary_min'],(int)$job['salary_max']).'</div>';
        $html .= '<div class="job-card-footer"><span class="job-posted">'.time_ago($job['created_at']).'</span><a href="'.$applyUrl.'" class="btn btn-primary btn-sm">Apply</a></div>';
        $html .= '</div>';
    }
    
    resp(['jobs' => $jobs, 'html' => $html]);
}

if ($action === 'job_detail') {
    $id = (int)($_GET['id'] ?? 0);
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT j.*,u.company_name,u.industry,u.company_website,u.bio AS company_bio,u.location AS hq
         FROM jobs j JOIN users u ON j.employer_id=u.id WHERE j.id=? AND j.status='active'"
    );
    $stmt->execute([$id]);
    $job = $stmt->fetch();
    if (!$job) resp(['error'=>'Job not found'], 404);
    $pdo->prepare("UPDATE jobs SET views=views+1 WHERE id=?")->execute([$id]);
    resp(['job'=>$job]);
}

// ── Auth required below ───────────────────────────────────────
if (!is_logged_in()) resp(['error' => 'Unauthenticated'], 401);

$uid  = $_SESSION['user_id'];
$role = current_role();

// ── Mark notification as read ─────────────────────────────────
if ($action === 'mark_read' && $method === 'POST') {
    global $pdo;
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    resp(['ok' => true]);
}

// ── Get notifications ─────────────────────────────────────────
if ($action === 'notifications') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$uid]);
    $notifs = $stmt->fetchAll();
    $unread = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0")->execute([$uid]);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $cnt->execute([$uid]); $unread=(int)$cnt->fetchColumn();
    resp(['notifications'=>$notifs, 'unread'=>$unread]);
}

// ── Student: my applications ──────────────────────────────────
if ($action === 'my_applications' && $role === 'student') {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT a.*,j.title,j.job_type,j.location,u.company_name
         FROM applications a JOIN jobs j ON a.job_id=j.id JOIN users u ON j.employer_id=u.id
         WHERE a.student_id=? ORDER BY a.applied_at DESC"
    );
    $stmt->execute([$uid]);
    resp(['applications'=>$stmt->fetchAll()]);
}

// ── Employer: stats ───────────────────────────────────────────
if ($action === 'employer_stats' && $role === 'employer') {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) AS total_jobs,
         SUM(status='active') AS active_jobs
         FROM jobs WHERE employer_id=?"
    );
    $stmt->execute([$uid]);
    $s = $stmt->fetch();

    $a = $pdo->prepare(
        "SELECT COUNT(*) AS total,
         SUM(a.status='pending') AS pending,
         SUM(a.status='shortlisted') AS shortlisted,
         SUM(a.status='hired') AS hired
         FROM applications a JOIN jobs j ON a.job_id=j.id WHERE j.employer_id=?"
    );
    $a->execute([$uid]);
    resp(array_merge($s, $a->fetch()));
}

// ── Employer: update application status ───────────────────────
if ($action === 'update_status' && $method === 'POST' && $role === 'employer') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $app_id = (int)($body['app_id'] ?? 0);
    $status = $body['status'] ?? '';
    global $pdo;

    $allowed = ['pending','shortlisted','hired','rejected'];
    if (!in_array($status, $allowed)) resp(['error'=>'Invalid status'], 400);

    // Verify ownership
    $chk = $pdo->prepare(
        "SELECT a.id,a.student_id,j.title FROM applications a
         JOIN jobs j ON a.job_id=j.id WHERE a.id=? AND j.employer_id=?"
    );
    $chk->execute([$app_id,$uid]); $appl=$chk->fetch();
    if (!$appl) resp(['error'=>'Not found'], 404);

    $pdo->prepare("UPDATE applications SET status=? WHERE id=?")->execute([$status,$app_id]);
    resp(['ok'=>true, 'new_status'=>$status]);
}

// ── Employer: update private notes ────────────────────────────
if ($action === 'update_notes' && $method === 'POST' && $role === 'employer') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $app_id = (int)($body['app_id'] ?? 0);
    $notes  = $body['notes'] ?? '';
    global $pdo;

    // Verify ownership
    $chk = $pdo->prepare("SELECT a.id FROM applications a JOIN jobs j ON a.job_id=j.id WHERE a.id=? AND j.employer_id=?");
    $chk->execute([$app_id,$uid]);
    if (!$chk->fetch()) resp(['error'=>'Not authorized'], 403);

    $pdo->prepare("UPDATE applications SET employer_notes=? WHERE id=?")->execute([$notes, $app_id]);
    resp(['ok'=>true]);
}

// ── Employer: job analytics ───────────────────────────────────
if ($action === 'job_analytics' && $role === 'employer') {
    $job_id = (int)($_GET['job_id'] ?? 0);
    global $pdo;
    
    // Verify ownership
    $chk = $pdo->prepare("SELECT id FROM jobs WHERE id=? AND employer_id=?");
    $chk->execute([$job_id,$uid]);
    if (!$chk->fetch()) resp(['error'=>'Not authorized'], 403);

    $stats = $pdo->prepare("SELECT views, (SELECT COUNT(*) FROM applications WHERE job_id=?) as total_apps FROM jobs WHERE id=?");
    $stats->execute([$job_id, $job_id]);
    resp($stats->fetch());
}

// ── Admin: platform summary ───────────────────────────────────
if ($action === 'platform_stats' && $role === 'admin') {
    global $pdo;
    $stats = $pdo->query("SELECT
        (SELECT COUNT(*) FROM users WHERE role='student')  AS students,
        (SELECT COUNT(*) FROM users WHERE role='employer') AS employers,
        (SELECT COUNT(*) FROM jobs WHERE status='active')  AS active_jobs,
        (SELECT COUNT(*) FROM applications)                AS total_apps
    ")->fetch();
    resp($stats);
}

// ── Chat Endpoints ────────────────────────────────────────────
if ($action === 'get_messages') {
    $other_user = (int)($_GET['other_user'] ?? 0);
    $job_id = (int)($_GET['job_id'] ?? 0);
    global $pdo;
    $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id = u.id 
                           WHERE job_id=? AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$job_id, $uid, $other_user, $other_user, $uid]);
    // Mark as read
    $pdo->prepare("UPDATE messages SET is_read=1 WHERE job_id=? AND sender_id=? AND receiver_id=?")->execute([$job_id, $other_user, $uid]);
    resp(['messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($action === 'send_message' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $other_user = (int)($body['other_user'] ?? 0);
    $job_id = (int)($body['job_id'] ?? 0);
    $message = trim($body['message'] ?? '');
    if (!$other_user || !$job_id || !$message) resp(['error' => 'Missing fields'], 400);

    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, job_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$uid, $other_user, $job_id, $message]);
    
    // Check other user name for response
    $nm = $pdo->prepare("SELECT name FROM users WHERE id=?"); $nm->execute([$uid]);
    resp(['ok' => true, 'id' => $pdo->lastInsertId(), 'sender_name' => $nm->fetchColumn(), 'message' => e($message), 'time' => 'Just now']);
}

// ── Student: save/unsave job ──────────────────────────────────
if ($action === 'save_job' && $role === 'student') {
    $job_id = (int)($_GET['job_id'] ?? 0);
    global $pdo;
    
    $check = $pdo->prepare("SELECT id FROM saved_jobs WHERE user_id=? AND job_id=?");
    $check->execute([$uid, $job_id]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM saved_jobs WHERE user_id=? AND job_id=?")->execute([$uid, $job_id]);
        resp(['ok'=>true, 'saved'=>false]);
    } else {
        $pdo->prepare("INSERT INTO saved_jobs (user_id, job_id) VALUES (?, ?)")->execute([$uid, $job_id]);
        resp(['ok'=>true, 'saved'=>true]);
    }
}

// ── Student: get saved jobs ───────────────────────────────────
if ($action === 'my_saved_jobs' && $role === 'student') {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT j.*, u.company_name FROM jobs j 
         JOIN saved_jobs s ON j.id=s.job_id 
         JOIN users u ON j.employer_id=u.id 
         WHERE s.user_id=? ORDER BY s.created_at DESC"
    );
    $stmt->execute([$uid]);
    resp(['jobs' => $stmt->fetchAll()]);
}

// ── Student: match score ──────────────────────────────────────
if ($action === 'match_score' && $role === 'student') {
    $job_id = (int)($_GET['job_id'] ?? 0);
    global $pdo;
    
    // Get student skills
    $st = $pdo->prepare("SELECT skills FROM users WHERE id=?"); $st->execute([$uid]);
    $st_skills = array_map('trim', explode(',', strtolower($st->fetchColumn())));
    
    // Get job skills
    $jb = $pdo->prepare("SELECT skills_required FROM jobs WHERE id=?"); $jb->execute([$job_id]);
    $jb_skills = array_map('trim', explode(',', strtolower($jb->fetchColumn())));
    
    if (empty($jb_skills) || (count($jb_skills) === 1 && $jb_skills[0] === '')) {
        resp(['score' => 100]); // No requirements
    }
    
    $matches = array_intersect($st_skills, $jb_skills);
    $score = round((count($matches) / count($jb_skills)) * 100);
    
    resp(['score' => min(100, $score), 'matches' => array_values($matches)]);
}

// ── External API integrations ─────────────────────────────────
if ($action === 'analyze_real_jobs') {
    $skills = $_GET['skills'] ?? '';
    if (!$skills) resp(['error' => 'No skills provided', 'jobs' => []]);
    
    // Convert comma/space separated skills to a clean query
    $query = urlencode(substr(trim($skills), 0, 50));
    $url = "https://remotive.com/api/remote-jobs?search={$query}&limit=12";
    
    // Make request
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: JobPortal/1.0\r\n"
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    
    if ($response) {
        $json = json_decode($response, true);
        if (isset($json['jobs'])) {
            $formatted_jobs = array_map(function($j) {
                return [
                    'id' => $j['id'],
                    'title' => $j['title'],
                    'company' => $j['company_name'],
                    'location' => $j['candidate_required_location'] ?? 'Remote',
                    'type' => $j['job_type'] ?? 'full-time',
                    'url' => $j['url'],
                    'tags' => array_slice($j['tags'] ?? [], 0, 3)
                ];
            }, array_slice($json['jobs'], 0, 12));
            resp(['ok' => true, 'jobs' => $formatted_jobs]);
        }
    }
    resp(['error' => 'Failed to fetch jobs', 'jobs' => []]);
}

// ── Public: get student profile ─────────────────────────────
if ($action === 'get_public_profile') {
    $username = $_GET['username'] ?? '';
    if (!$username) resp(['error'=>'Username required'], 400);

    $stmt = $pdo->prepare("SELECT name, bio, skills, location, experience_level, video_path, company_name, industry FROM users WHERE username=?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) resp(['error'=>'User not found'], 404);

    // Get verified skills
    $ass = $pdo->prepare("SELECT skill, score, passed_at FROM assessments WHERE user_id=(SELECT id FROM users WHERE username=?) AND score >= 70");
    $ass->execute([$username]);
    $user['verified_skills'] = $ass->fetchAll();

    resp($user);
}
// ── Ultra-Premium: Interviews ───────────────────────────────
if ($action === 'schedule_interview' && $role === 'employer') {
    $body = json_decode(file_get_contents('php://input'), true);
    $app_id = (int)($body['app_id'] ?? 0);
    $slots  = $body['slots'] ?? []; // Array of datetime strings
    if (!$app_id || empty($slots)) resp(['error'=>'Invalid data'], 400);

    $pdo->prepare("INSERT INTO interviews (application_id, slots_json) VALUES (?, ?)")
        ->execute([$app_id, json_encode($slots)]);
    resp(['ok'=>true]);
}

if ($action === 'confirm_interview' && $role === 'student') {
    $body = json_decode(file_get_contents('php://input'), true);
    $int_id = (int)($body['interview_id'] ?? 0);
    $slot   = $body['slot'] ?? '';
    if (!$int_id || !$slot) resp(['error'=>'Invalid data'], 400);

    $pdo->prepare("UPDATE interviews SET confirmed_slot=?, status='confirmed' WHERE id=?")
        ->execute([$slot, $int_id]);
    resp(['ok'=>true]);
}

// ── Ultra-Premium: Assessments ─────────────────────────────
if ($action === 'submit_assessment' && $role === 'student') {
    $body = json_decode(file_get_contents('php://input'), true);
    $skill = $body['skill'] ?? '';
    $score = (int)($body['score'] ?? 0);
    if (!$skill) resp(['error'=>'Invalid skill'], 400);

    $pdo->prepare("INSERT INTO assessments (user_id, skill, score) VALUES (?, ?, ?)")
        ->execute([$uid, $skill, $score]);
    resp(['ok'=>true]);
}

// ── Ultra-Premium: SaaS Mock ───────────────────────────────
if ($action === 'buy_credits' && $role === 'employer') {
    $body = json_decode(file_get_contents('php://input'), true);
    $amount = (int)($body['amount'] ?? 10);
    $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id=?")
        ->execute([$amount, $uid]);
    resp(['ok'=>true, 'new_balance' => $pdo->query("SELECT credits FROM users WHERE id=$uid")->fetchColumn()]);
}

// ── AI Resume Optimizer ───────────────────────────────────
if ($action === 'resume_optimizer' && $role === 'student') {
    $body = json_decode(file_get_contents('php://input'), true);
    $job_id = (int)($body['job_id'] ?? 0);
    
    $job = $pdo->prepare("SELECT title, skills_required FROM jobs WHERE id=?");
    $job->execute([$job_id]);
    $j = $job->fetch();
    
    $me = $pdo->prepare("SELECT skills, bio FROM users WHERE id=?");
    $me->execute([$uid]);
    $m = $me->fetch();
    
    if (!$j || !$m) resp(['error'=>'Data not found'], 404);
    
    $jSkills = array_filter(array_map('trim', explode(',', strtolower($j['skills_required']))));
    $mSkills = array_filter(array_map('trim', explode(',', strtolower($m['skills'] ?? ''))));
    
    $missing = array_diff($jSkills, $mSkills);
    $matched = array_intersect($jSkills, $mSkills);
    
    $suggestions = [];
    if (!empty($missing)) {
        $suggestions[] = "Consider adding " . implode(', ', array_slice($missing, 0, 3)) . " to your skills.";
    }
    if (strlen($m['bio'] ?? '') < 50) {
        $suggestions[] = "Your bio is short. Mention " . ($matched[0] ?? "relevant tech") . " more clearly.";
    }
    
    resp([
        'ok' => true,
        'match_percent' => count($jSkills) > 0 ? round((count($matched)/count($jSkills))*100) : 0,
        'suggestions' => $suggestions
    ]);
}

resp(['error' => 'Unknown action'], 400);
?>
