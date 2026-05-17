<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
redirect_if_logged_in();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name, role, password_hash, is_active FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif (!$user['is_active']) {
            $error = 'Your account has been deactivated. Please contact support.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            $destinations = [
                'student'  => BASE_URL . '/student/dashboard.php',
                'employer' => BASE_URL . '/employer/dashboard.php',
                'admin'    => BASE_URL . '/admin/dashboard.php',
            ];
            header('Location: ' . ($destinations[$user['role']] ?? BASE_URL . '/index.php'));
            exit;
        }
    }
}

$pre_error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — JobPortal</title>
  <meta name="description" content="Sign in to your JobPortal account to browse jobs, manage listings, and track applications.">
  <link rel="stylesheet" href="assets/style.css">
  <style>
    .login-container { max-width: 420px; }
    .divider-text { display: flex; align-items: center; gap: 12px; margin: 20px 0; color: var(--text-muted); font-size: 0.78rem; }
    .divider-text::before, .divider-text::after { content: ''; flex: 1; height: 1px; background: var(--glass-border); }
  </style>
</head>
<body>
<div class="auth-bg">
  <div class="auth-card login-container animate-in">
    <div class="auth-header">
      <div class="auth-logo">💼</div>
      <h2>Welcome back</h2>
      <p class="mt-8" style="font-size:0.9rem;">Sign in to your JobPortal account</p>
    </div>

    <div class="auth-body">
      <?php if ($error): ?>
        <div class="alert alert-danger">⚠️ <?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($pre_error): ?>
        <div class="alert alert-warning">⚠️ <?= $pre_error ?></div>
      <?php endif; ?>

      <form method="POST" id="loginForm">
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input id="email" name="email" type="email" class="form-control"
                 placeholder="you@example.com"
                 value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email">
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input id="password" name="password" type="password" class="form-control"
                 placeholder="••••••••" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
          Sign In
        </button>
      </form>

      <div class="divider">OR</div>

      <div class="d-flex flex-column gap-12">
        <button type="button" class="btn btn-outline btn-block" style="display:flex; align-items:center; justify-content:center; gap:10px;">
          <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" width="18"> Continue with Google
        </button>
        <button type="button" class="btn btn-outline btn-block" style="display:flex; align-items:center; justify-content:center; gap:10px;">
          <img src="https://upload.wikimedia.org/wikipedia/commons/c/ca/LinkedIn_logo_initials.png" width="18"> Continue with LinkedIn
        </button>
      </div>

      <div style="background:var(--glass-bg);border:1px solid var(--glass-border);border-radius:var(--radius-sm);padding:14px 16px;font-size:0.8rem;color:var(--text-muted); margin-top: 20px;">
        <strong style="color:var(--text-secondary)">🔐 Demo Accounts</strong><br>
        <span style="color:var(--accent-3)">Admin:</span> admin@jobportal.com<br>
        <span style="color:var(--success)">Employer:</span> hr@techcorp.com<br>
        <span style="color:var(--warning)">Student:</span> Register a new account<br>
        <span style="color:var(--text-muted);font-size:0.75rem;">All passwords: Admin@123</span>
      </div>

      <p class="text-center mt-16" style="font-size:0.875rem;color:var(--text-muted);">
        No account? <a href="register.php">Create one free</a>
      </p>
    </div>
  </div>
</div>

<script src="assets/script.js"></script>
</body>
</html>
