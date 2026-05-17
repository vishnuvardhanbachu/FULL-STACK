<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('student');

$uid = $_SESSION['user_id'];
$me = $pdo->prepare("SELECT name, email, skills FROM users WHERE id=?");
$me->execute([$uid]);
$me = $me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resume Analyzer & Job Matcher — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
  <style>
    .analyzer-header {
      background: linear-gradient(135deg, var(--bg-1) 0%, var(--bg-0) 100%);
      padding: 40px;
      border-radius: var(--radius-lg);
      border: 1px solid var(--glass-border);
      text-align: center;
      margin-bottom: 24px;
      position: relative;
      overflow: hidden;
    }
    .file-dropzone {
      border: 2px dashed rgba(99, 102, 241, 0.4);
      background: rgba(99, 102, 241, 0.05);
      border-radius: var(--radius-md);
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      max-width: 500px;
      margin: 20px auto;
    }
    .file-dropzone:hover { background: rgba(99, 102, 241, 0.1); border-color: var(--accent-1); }
    .file-dropzone.dragover { background: rgba(99, 102, 241, 0.2); border-color: #fff; }
    
    .score-circle {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: conic-gradient(var(--success) 0%, var(--bg-3) 0%);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
      position: relative;
      transition: all 1s ease;
    }
    .score-inner {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: var(--bg-1);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }
    .score-value { font-size: 2rem; font-weight: 800; color: var(--success); }
    .score-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; }

    .skills-found-container {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: center;
      margin-top: 20px;
    }
    .job-card-external { border-top: 3px solid #f59e0b !important; }
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
    <a class="nav-item active" href="analyzer.php"><span class="icon">✨</span> Resume Analyzer</a>
    <a class="nav-item" href="dashboard.php#applications"><span class="icon">📋</span> My Applications</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item" href="profile.php"><span class="icon">👤</span> My Profile</a>
    <a class="nav-item" href="../index.php"><span class="icon">🌐</span> Public Board</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= mb_strtoupper(mb_substr($me['name'],0,1)) ?></div>
      <div>
        <div class="user-name"><?= e($me['name']) ?></div>
        <div class="user-role">Student</div>
      </div>
    </div>
  </div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left">
      <div class="hamburger" id="hamburger"><span></span><span></span><span></span></div>
      <h1 class="page-title">Resume Analyzer & Job Matcher</h1>
    </div>
  </div>

  <div class="page-content">
    <div class="analyzer-header animate-in">
      <h2>AI Resume Scanner</h2>
      <p class="text-muted" style="margin-top:8px;">Upload your resume to get an instant ATS score and match with real-world remote tech jobs.</p>
      
      <div id="uploadSection">
        <div class="file-dropzone" id="dropzone" onclick="document.getElementById('pdfUpload').click()">
          <div style="font-size:2rem;margin-bottom:10px;">📄</div>
          <h4 style="margin-bottom:6px;">Upload PDF Resume</h4>
          <p class="text-sm text-muted">Drag & drop or click to browse</p>
          <input type="file" id="pdfUpload" accept="application/pdf" style="display:none;">
        </div>
      </div>

      <div id="processingSection" style="display:none; padding: 40px 0;">
        <div class="spinner"></div>
        <p class="mt-16 text-muted">Analyzing typography, matching skills, and querying global jobs...</p>
      </div>

      <div id="resultsSection" style="display:none; margin-top: 30px;">
        <div class="d-flex justify-center" style="gap:40px; flex-wrap:wrap; align-items:center;">
          <div>
            <div class="score-circle" id="scoreCircle">
              <div class="score-inner">
                <div class="score-value" id="scoreValue">0</div>
                <div class="score-label">Score</div>
              </div>
            </div>
          </div>
          <div style="text-align:left; max-width:400px;">
            <h3 id="scoreTitle">Resume Score</h3>
            <p class="text-sm text-muted mt-8" id="scoreDesc">Based on keywords, length, and industry standards.</p>
            <div id="skillsContainer" class="skills-found-container" style="justify-content:flex-start;"></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Real-world jobs injected here -->
    <div id="jobsContainer" style="display:none;" class="animate-in animate-delay-1">
      <div class="d-flex justify-between" style="align-items:center; margin-bottom: 20px;">
        <h3>🌐 Live Remote Global Jobs</h3>
        <span class="badge badge-indigo text-xs">Powered by Remotive API</span>
      </div>
      <div class="job-grid" id="jobsGrid"></div>
    </div>

  </div>
</main>
</div>

<script>
pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js";

const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('pdfUpload');
const uploadSection = document.getElementById('uploadSection');
const processingSection = document.getElementById('processingSection');
const resultsSection = document.getElementById('resultsSection');
const scoreCircle = document.getElementById('scoreCircle');
const scoreValue = document.getElementById('scoreValue');
const skillsContainer = document.getElementById('skillsContainer');
const jobsContainer = document.getElementById('jobsContainer');
const jobsGrid = document.getElementById('jobsGrid');

const techMatrix = ['PHP','MySQL','React','Node','JavaScript','Python','AWS','Docker','Kubernetes','HTML','CSS','Java','Spring','SQL','NoSQL','Git','Linux','C++','C#','Ruby/Rails'];
const actionWords = ['managed','developed','led','designed','created','implemented','optimized','spearheaded','architected','resolved'];

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => dropzone.addEventListener(eventName, preventDefaults, false));
function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

['dragenter', 'dragover'].forEach(eventName => dropzone.addEventListener(eventName, () => dropzone.classList.add('dragover'), false));
['dragleave', 'drop'].forEach(eventName => dropzone.addEventListener(eventName, () => dropzone.classList.remove('dragover'), false));

dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    if(files.length) handleFile(files[0]);
});
fileInput.addEventListener('change', function() {
    if(this.files.length) handleFile(this.files[0]);
});

async function handleFile(file) {
    if(file.type !== "application/pdf") return alert("Please upload a valid PDF.");
    
    uploadSection.style.display = 'none';
    processingSection.style.display = 'block';
    
    const fileReader = new FileReader();
    fileReader.onload = async function() {
        try {
            const typedArray = new Uint8Array(this.result);
            const pdf = await pdfjsLib.getDocument(typedArray).promise;
            let fullText = "";
            for (let i = 1; i <= pdf.numPages; i++) {
                const page = await pdf.getPage(i);
                const textContent = await page.getTextContent();
                fullText += " " + textContent.items.map(s => s.str).join(" ");
            }
            analyzeText(fullText);
        } catch(e) {
            console.error(e);
            alert("Could not parse PDF.");
            location.reload();
        }
    };
    fileReader.readAsArrayBuffer(file);
}

function analyzeText(text) {
    const textLower = text.toLowerCase();
    
    // 1. Detect Skills
    let foundSkills = [];
    techMatrix.forEach(tech => {
        if (textLower.includes(tech.toLowerCase())) foundSkills.push(tech);
    });

    // 2. Count Action Words
    let actionCount = 0;
    actionWords.forEach(word => {
        if(textLower.includes(word)) actionCount++;
    });

    // 3. Length score (optimal word count is around 300 - 800)
    const wordCount = text.split(/\s+/).length;
    let lengthScore = 30; // base out of 30
    if(wordCount < 100) lengthScore = 10;
    else if(wordCount > 1000) lengthScore = 15;

    // Calculate total score (max 100)
    // Skills (Max 40 pts)
    let scoreSkills = Math.min(40, foundSkills.length * 5); 
    // Action Words (Max 30 pts)
    let scoreActions = Math.min(30, actionCount * 6);
    
    let totalScore = lengthScore + scoreSkills + scoreActions;
    totalScore = Math.max(10, Math.min(100, totalScore)); // Clamp 10-100

    // Render Stats
    setTimeout(() => {
        processingSection.style.display = 'none';
        resultsSection.style.display = 'block';
        
        // Animate Circle
        let current = 0;
        let intv = setInterval(() => {
            current++;
            scoreValue.innerText = current;
            let color = current > 70 ? 'var(--success)' : (current > 40 ? 'var(--warning)' : 'var(--danger)');
            scoreValue.style.color = color;
            scoreCircle.style.background = `conic-gradient(${color} ${current}%, var(--bg-3) 0%)`;
            if(current >= totalScore) clearInterval(intv);
        }, 15);

        // Render chips
        skillsContainer.innerHTML = '';
        if(foundSkills.length === 0) foundSkills = ['Tech']; // fallback
        foundSkills.forEach(s => {
            skillsContainer.innerHTML += `<span class="badge badge-indigo">${s}</span>`;
        });
        
        // Fetch real jobs
        fetchLiveJobs(foundSkills.join(','));

    }, 800);
}

async function fetchLiveJobs(skillsStr) {
    jobsContainer.style.display = 'none';
    jobsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;" class="text-muted">Fetching real-world vacancies...</div>';
    jobsContainer.style.display = 'block';

    try {
        const res = await fetch('../api.php?action=analyze_real_jobs&skills=' + encodeURIComponent(skillsStr));
        const data = await res.json();
        
        if (data.jobs && data.jobs.length > 0) {
            jobsGrid.innerHTML = '';
            data.jobs.forEach(j => {
                jobsGrid.innerHTML += `
                    <div class="job-card job-card-external animate-in">
                        <div class="job-card-header">
                            <div><h4 class="job-title" style="font-size:1rem;">${j.title}</h4><div class="job-company">🏢 ${j.company}</div></div>
                        </div>
                        <div class="job-meta">
                            <span class="job-tag">📍 ${j.location}</span>
                            <span class="job-tag">⏱ ${j.type}</span>
                        </div>
                        <div style="margin:10px 0;font-size:0.75rem;color:var(--text-muted);">
                           ${j.tags.map(t => `<span class="badge badge-indigo" style="margin:2px;">${t}</span>`).join('')}
                        </div>
                        <div class="job-card-footer mt-16">
                            <span class="text-xs" style="color:#f59e0b;">External Board</span>
                            <a href="${j.url}" target="_blank" class="btn btn-outline btn-sm" style="border-color:#f59e0b;color:#f59e0b;">Apply Externally &nearr;</a>
                        </div>
                    </div>
                `;
            });
        } else {
            jobsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;" class="text-muted">No external jobs found for your skills at the moment.</div>';
        }
    } catch(e) {
        jobsGrid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:20px;" class="text-danger">Failed to connect to real-world job API.</div>';
    }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
