<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_role('employer');

$uid    = $_SESSION['user_id'];
$job_id = (int)($_GET['job_id'] ?? 0);
$status = $_GET['status'] ?? '';

// Fetch applicants ensuring they belong to employer's jobs
$sql = "SELECT j.title AS job_title, u.name, u.email, u.phone, 
               u.location AS student_location, u.experience_level, u.skills,
               a.status, a.applied_at
        FROM applications a
        JOIN jobs j ON a.job_id=j.id
        JOIN users u ON a.student_id=u.id
        WHERE j.employer_id=?";

$params = [$uid];
if ($job_id) { $sql .= " AND j.id=?"; $params[]=$job_id; }
if ($status) { $sql .= " AND a.status=?"; $params[]=$status; }

$sql .= " ORDER BY a.applied_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="applicants_export_'.date('Ymd_His').'.csv"');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['Job Title', 'Candidate Name', 'Email', 'Phone', 'Location', 'Experience', 'Skills', 'Status', 'Applied At']);

foreach ($applicants as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
