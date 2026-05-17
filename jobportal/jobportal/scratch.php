<?php
require_once __DIR__ . '/config.php';
$stmt = $pdo->prepare("SELECT id, name, role, password_hash, is_active FROM users WHERE email = 'hr@techcorp.com'");
$stmt->execute();
$user = $stmt->fetch();
var_dump($user);
echo "Verify Admin@123: "; var_dump(password_verify('Admin@123', $user['password_hash']));
