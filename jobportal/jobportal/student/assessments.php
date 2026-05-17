<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid = $_SESSION['user_id'];
$me = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();

$quizzes = [
    'HTML' => [
        ['q' => 'What does HTML stand for?', 'a' => ['HyperText Markup Language', 'Hyperlink Text Management', 'High Tech Modern Language'], 'c' => 0],
        ['q' => 'Which tag is used for the largest heading?', 'a' => ['<h6>', '<head>', '<h1>'], 'c' => 2],
        ['q' => 'What is the correct tag for a line break?', 'a' => ['<br>', '<lb>', '<break>'], 'c' => 0],
    ],
    'Javascript' => [
        ['q' => 'Which keyword is used to declare a variable that cannot be reassigned?', 'a' => ['var', 'let', 'const'], 'c' => 2],
        ['q' => 'How do you write "Hello World" in an alert box?', 'a' => ['msg("Hello World")', 'alert("Hello World")', 'print("Hello World")'], 'c' => 1],
        ['q' => 'Which operator is used to assign a value to a variable?', 'a' => ['*', '-', '='], 'c' => 2],
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skill Assessments — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .quiz-card { background: var(--glass-bg); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 20px; }
    .option { display: block; padding: 12px 16px; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); border-radius: var(--radius-sm); margin-top: 10px; cursor: pointer; transition: var(--transition); }
    .option:hover { background: rgba(99,102,241,0.1); border-color: var(--primary); }
    .option.selected { background: var(--primary); color: #fff; }
  </style>
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
    <div class="nav-section-label">Verify</div>
    <a class="nav-item active" href="assessments.php"><span class="icon">🏆</span> Skill Quizzes</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
</aside>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left"><h1 class="page-title">Skill Assessments</h1></div>
  </div>

  <div class="page-content">
    <div id="quizList">
        <div class="section-header">
            <h2>Get Verified Badges</h2>
            <p class="text-muted">Pass these quizzes with 70%+ score to show employers you have the skills.</p>
        </div>
        <div class="grid gap-24" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <?php foreach($quizzes as $name => $qs): ?>
                <div class="card card-p">
                    <h3><?= $name ?> Assessment</h3>
                    <p class="text-sm text-muted mt-8"><?= count($qs) ?> Questions · 5 Minutes</p>
                    <button onclick="startQuiz('<?= $name ?>')" class="btn btn-primary btn-sm mt-16">Start Quiz</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="quizWindow" style="display:none; max-width:600px; margin: 0 auto;">
        <div class="quiz-card animate-in">
            <div class="d-flex justify-between align-center mb-24">
                <h2 id="quizTitle">Quiz</h2>
                <div id="timer" class="badge badge-amber">05:00</div>
            </div>
            <div id="questionArea">
                <!-- JS Inject -->
            </div>
            <div class="mt-24 d-flex justify-between">
                <button onclick="prevQ()" id="prevBtn" class="btn btn-ghost btn-sm">Previous</button>
                <button onclick="nextQ()" id="nextBtn" class="btn btn-primary btn-sm">Next Question</button>
            </div>
        </div>
    </div>

    <div id="resultWindow" style="display:none; text-align:center;" class="animate-in">
        <div class="card card-p">
            <div style="font-size:4rem;" id="resIcon">🎉</div>
            <h2 id="resTitle" class="mt-16">Congratulations!</h2>
            <p id="resDesc" class="text-muted mt-8">You scored 100% and earned a verified badge.</p>
            <a href="dashboard.php" class="btn btn-primary mt-24">Back to Dashboard</a>
        </div>
    </div>
  </div>
</main>
</div>

<script>
const allQuizzes = <?= json_encode($quizzes) ?>;
let currentQuiz = null;
let currentQIdx = 0;
let userAnswers = [];
let timerInterval = null;

function startQuiz(name) {
    currentQuiz = { name, questions: allQuizzes[name] };
    currentQIdx = 0;
    userAnswers = new Array(currentQuiz.questions.length).fill(null);
    
    document.getElementById('quizList').style.display = 'none';
    document.getElementById('quizWindow').style.display = 'block';
    document.getElementById('quizTitle').innerText = name + ' Quiz';
    
    showQuestion();
}

function showQuestion() {
    const q = currentQuiz.questions[currentQIdx];
    const area = document.getElementById('questionArea');
    
    let html = `<h3 class="mb-16">Q${currentQIdx+1}: ${q.q}</h3>`;
    q.a.forEach((opt, idx) => {
        const cls = userAnswers[currentQIdx] === idx ? 'selected' : '';
        html += `<div class="option ${cls}" onclick="pickAnswer(${idx})">${opt}</div>`;
    });
    
    area.innerHTML = html;
    document.getElementById('prevBtn').disabled = currentQIdx === 0;
    document.getElementById('nextBtn').innerText = currentQIdx === currentQuiz.questions.length - 1 ? 'Finish Quiz' : 'Next Question';
}

function pickAnswer(idx) {
    userAnswers[currentQIdx] = idx;
    showQuestion();
}

function nextQ() {
    if(userAnswers[currentQIdx] === null) return alert("Please select an answer.");
    
    if(currentQIdx < currentQuiz.questions.length - 1) {
        currentQIdx++;
        showQuestion();
    } else {
        finishQuiz();
    }
}

function prevQ() {
    if(currentQIdx > 0) {
        currentQIdx--;
        showQuestion();
    }
}

async function finishQuiz() {
    let score = 0;
    currentQuiz.questions.forEach((q, idx) => {
        if(userAnswers[idx] === q.c) score++;
    });
    
    const finalScore = Math.round((score / currentQuiz.questions.length) * 100);
    
    // Save to DB
    try {
        await fetch('../api.php?action=submit_assessment', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({skill: currentQuiz.name, score: finalScore})
        });
    } catch(err) {}

    document.getElementById('quizWindow').style.display = 'none';
    document.getElementById('resultWindow').style.display = 'block';
    
    const icon = document.getElementById('resIcon');
    const title = document.getElementById('resTitle');
    const desc = document.getElementById('resDesc');
    
    if(finalScore >= 70) {
        icon.innerText = '🏆';
        title.innerText = 'Assessment Passed!';
        desc.innerText = `Great job! You scored ${finalScore}% and earned a verified badge for ${currentQuiz.name}.`;
    } else {
        icon.innerText = '❌';
        title.innerText = 'Assessment Failed';
        desc.innerText = `You scored ${finalScore}%. You need at least 70% to earn a badge. Try again later!`;
    }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
