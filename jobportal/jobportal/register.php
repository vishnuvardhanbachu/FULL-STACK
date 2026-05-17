<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
redirect_if_logged_in();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role     = in_array($_POST['role'] ?? '', ['student','employer']) ? $_POST['role'] : 'student';
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Employer extras
    $company_name    = trim($_POST['company_name'] ?? '');
    $company_website = trim($_POST['company_website'] ?? '');
    $industry        = trim($_POST['industry'] ?? '');

    if (!$name || !$email || !$password || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'employer' && !$company_name) {
        $error = 'Company name is required for employers.';
    } else {
        // Check duplicate email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, company_name, company_website, industry)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hash, $role, $company_name ?: null, $company_website ?: null, $industry ?: null]);
            $success = 'Account created! Redirecting to login…';
            header('Refresh: 2; url=' . BASE_URL . '/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — JobPortal</title>
  <meta name="description" content="Create a free JobPortal account as a student or employer and start your journey today.">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .employer-fields { display: none; }
    .employer-fields.show { display: block; }
  </style>
</head>
<body>
<div class="auth-bg">
  <div class="auth-card animate-in" style="max-width:500px">
    <div class="auth-header">
      <div class="auth-logo">🚀</div>
      <h2>Create your account</h2>
      <p class="mt-8" style="font-size:0.875rem;">Join thousands of job seekers and employers</p>
    </div>

    <div class="auth-body">
      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= e($success) ?></div>
      <?php endif; ?>

      <!-- Role Toggle -->
      <div class="role-toggle" id="roleToggle">
        <button type="button" class="role-btn active" data-role="student" id="btnStudent">👨‍🎓 Student</button>
        <button type="button" class="role-btn" data-role="employer" id="btnEmployer">🏢 Employer</button>
      </div>

      <form method="POST" id="registerForm">
        <input type="hidden" name="role" id="roleInput" value="<?= e($_POST['role'] ?? 'student') ?>">

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" for="name">Full Name</label>
            <input id="name" name="name" type="text" class="form-control"
                   placeholder="Your full name"
                   value="<?= e($_POST['name'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <input id="email" name="email" type="email" class="form-control"
                   placeholder="you@example.com"
                   value="<?= e($_POST['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-row col-2">
          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control"
                   placeholder="Min 6 characters" required autocomplete="new-password">
          </div>
          <div class="form-group">
            <label class="form-label" for="confirm">Confirm Password</label>
            <input id="confirm" name="confirm" type="password" class="form-control"
                   placeholder="Repeat password" required>
          </div>
        </div>

        <!-- Employer extras -->
        <div class="employer-fields <?= (($_POST['role'] ?? '') === 'employer') ? 'show' : '' ?>" id="employerFields">
          <div class="form-group">
            <label class="form-label" for="company_name">Company Name *</label>
            <input id="company_name" name="company_name" type="text" class="form-control"
                   placeholder="e.g. Acme Corp"
                   value="<?= e($_POST['company_name'] ?? '') ?>">
          </div>
          <div class="form-row col-2">
            <div class="form-group">
              <label class="form-label" for="company_website">Website</label>
              <input id="company_website" name="company_website" type="url" class="form-control"
                     placeholder="https://…"
                     value="<?= e($_POST['company_website'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="industry">Industry</label>
              <select id="industry" name="industry" class="form-control">
                <option value="">Select industry</option>
                <?php foreach (['Information Technology','Finance & Banking','Product & SaaS','Healthcare','Education','E-commerce','Consulting','Manufacturing','Other'] as $ind): ?>
                  <option value="<?= $ind ?>" <?= (($_POST['industry'] ?? '') === $ind) ? 'selected' : '' ?>><?= $ind ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg mt-8" id="registerBtn">
          Create Account
        </button>
      </form>

      <p class="text-center mt-16" style="font-size:0.875rem;color:var(--text-muted);">
        Already have an account? <a href="login.php">Sign in</a>
      </p>
    </div>
  </div>
</div>

<script>
const roleBtns = document.querySelectorAll('.role-btn');
const roleInput = document.getElementById('roleInput');
const empFields = document.getElementById('employerFields');

roleBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    roleBtns.forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    roleInput.value = btn.dataset.role;
    empFields.classList.toggle('show', btn.dataset.role === 'employer');
  });
});

if (roleInput.value === 'employer') {
  document.getElementById('btnEmployer').classList.add('active');
  document.getElementById('btnStudent').classList.remove('active');
}
</script>
<script src="assets/script.js"></script>
</body>
</html>
