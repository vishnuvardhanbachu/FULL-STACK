<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid = $_SESSION['user_id'];
$me = $pdo->prepare("SELECT * FROM users WHERE id=?"); $me->execute([$uid]); $me=$me->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pricing Plans — JobPortal</title>
  <link rel="stylesheet" href="../assets/style.css">
  <style>
    .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-top: 40px; }
    .price-card { 
      background: var(--glass-bg); 
      border: 1px solid var(--glass-border); 
      border-radius: var(--radius-xl); 
      padding: 40px; 
      text-align: center; 
      transition: var(--transition);
      position: relative;
    }
    .price-card.featured { border-color: var(--primary); box-shadow: 0 0 30px rgba(99,102,241,0.2); transform: scale(1.05); }
    .price-card.featured::after { 
        content: 'MOST POPULAR'; position: absolute; top: -12px; left: 50%; transform: translateX(-50%);
        background: var(--primary); color: #fff; padding: 4px 12px; border-radius: 99px; font-size: 0.7rem; font-weight: 800;
    }
    .price-val { font-size: 3rem; font-weight: 800; margin: 20px 0; }
    .price-val span { font-size: 1rem; color: var(--text-muted); }
    .price-features { list-style: none; padding: 0; margin: 30px 0; text-align: left; }
    .price-features li { margin-bottom: 12px; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
    .price-features li::before { content: '✓'; color: var(--success); font-weight: bold; }
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
    <a class="nav-item" href="applicants.php"><span class="icon">👥</span> Applicants</a>
    <div class="nav-section-label">Account</div>
    <a class="nav-item active" href="pricing.php"><span class="icon">💳</span> Pricing & Credits</a>
    <a class="nav-item" href="../logout.php"><span class="icon">🚪</span> Sign Out</a>
  </nav>
</aside>

<main class="main-content">
  <div class="topbar">
    <div class="topbar-left"><h1 class="page-title">Pricing & Credits</h1></div>
    <div class="topbar-right">
        <div class="badge badge-indigo">Current Balance: <?= $me['credits'] ?> Credits</div>
    </div>
  </div>

  <div class="page-content">
    <div class="section-header">
      <h2>Scale your hiring with Pro features</h2>
      <p class="text-muted">Choose the plan that fits your recruitment needs.</p>
    </div>

    <div class="pricing-grid">
      <!-- Free -->
      <div class="price-card animate-in">
        <h3>Starter</h3>
        <div class="price-val">$0 <span>/mo</span></div>
        <ul class="price-features">
          <li>5 Job Credits</li>
          <li>Basic Applicant List</li>
          <li>Community Support</li>
        </ul>
        <button class="btn btn-ghost btn-block" disabled>Current Plan</button>
      </div>

      <!-- Pro -->
      <div class="price-card featured animate-in animate-delay-1">
        <h3>Recruiter Pro</h3>
        <div class="price-val">$49 <span>/mo</span></div>
        <ul class="price-features">
          <li>50 Job Credits /mo</li>
          <li>Kanban ATS Access</li>
          <li>Verified Talent Search</li>
          <li>Priority Support</li>
        </ul>
        <button onclick="buyCredits(50)" class="btn btn-primary btn-block">Upgrade to Pro</button>
      </div>

      <!-- Enterprise -->
      <div class="price-card animate-in animate-delay-2">
        <h3>Enterprise</h3>
        <div class="price-val">$199 <span>/mo</span></div>
        <ul class="price-features">
          <li>Unlimited Credits</li>
          <li>Custom Branding</li>
          <li>API Access</li>
          <li>Dedicated Account Manager</li>
        </ul>
        <button onclick="buyCredits(999)" class="btn btn-ghost btn-block">Contact Sales</button>
      </div>
    </div>

    <div class="card mt-40 animate-in animate-delay-3">
        <div class="card-p">
            <h3>Need more credits?</h3>
            <p class="text-muted mb-24">Buy individual credits to post more jobs without upgrading.</p>
            <div class="d-flex gap-16">
                <button onclick="buyCredits(5)" class="btn btn-outline">Add 5 Credits ($10)</button>
                <button onclick="buyCredits(10)" class="btn btn-outline">Add 10 Credits ($18)</button>
                <button onclick="buyCredits(25)" class="btn btn-outline">Add 25 Credits ($40)</button>
            </div>
        </div>
    </div>
  </div>
</main>
</div>

<script>
async function buyCredits(amount) {
    if(!confirm(`Confirm purchase of ${amount} credits? (Mock Checkout)`)) return;
    
    try {
        const res = await fetch('../api.php?action=buy_credits', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({amount})
        });
        const data = await res.json();
        if(data.ok) {
            alert(`Success! Your new balance is ${data.new_balance} credits.`);
            location.reload();
        }
    } catch(err) {
        alert("Transaction failed.");
    }
}
</script>
<script src="../assets/script.js"></script>
</body>
</html>
