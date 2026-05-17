<?php
// ── Session Guard & Auth Helpers ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require any authenticated user. Redirect to login if not.
 */
function require_login(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php?error=Please+log+in+first');
        exit;
    }
}

/**
 * Require a specific role. Redirect if not authorised.
 */
function require_role(string $role): void {
    require_login();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: ' . BASE_URL . '/login.php?error=Access+denied');
        exit;
    }
}

/**
 * Return true if user is logged in.
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Return current user's role or empty string.
 */
function current_role(): string {
    return $_SESSION['user_role'] ?? '';
}

/**
 * Redirect logged-in users to their dashboard.
 */
function redirect_if_logged_in(): void {
    if (is_logged_in()) {
        $role = current_role();
        $map  = [
            'student'  => BASE_URL . '/student/dashboard.php',
            'employer' => BASE_URL . '/employer/dashboard.php',
            'admin'    => BASE_URL . '/admin/dashboard.php',
        ];
        header('Location: ' . ($map[$role] ?? BASE_URL . '/index.php'));
        exit;
    }
}

/**
 * Sanitise output for HTML context.
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Create a notification for a user.
 */
function notify(PDO $pdo, int $user_id, string $title, string $message, string $type = 'info', string $link = ''): void {
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([$user_id, $title, $message, $type, $link]);
}

/**
 * Format salary nicely.
 */
function fmt_salary(int $min, int $max): string {
    if ($min === 0 && $max === 0) return 'Not Disclosed';
    $fmt = fn(int $n) => $n >= 100000 ? '₹' . round($n / 100000, 1) . 'L' : '₹' . number_format($n);
    return $fmt($min) . ' – ' . $fmt($max);
}

/**
 * Time-ago helper.
 */
function time_ago(string $datetime): string {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->days === 0) return 'Today';
    if ($diff->days === 1) return '1 day ago';
    if ($diff->days < 30) return $diff->days . ' days ago';
    if ($diff->days < 365) return round($diff->days / 30) . ' months ago';
    return round($diff->days / 365) . ' years ago';
}

// ── i18n Management ──────────────────────────────────────────
$lang = $_SESSION['lang'] ?? 'en';
if (isset($_GET['set_lang'])) {
    $lang = $_GET['set_lang'];
    $_SESSION['lang'] = $lang;
}
$translations = require_once __DIR__ . "/lang/{$lang}.php";

/**
 * Translate a key.
 */
function __($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

/**
 * Simulate sending an email (writes to db instead)
 */
function log_email(PDO $pdo, string $email, string $name, string $subject, string $body): void {
    $stmt = $pdo->prepare("INSERT INTO email_logs (recipient_email, recipient_name, subject, body) VALUES (?, ?, ?, ?)");
    $stmt->execute([$email, $name, $subject, $body]);
}
?>
