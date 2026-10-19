<?php
require_once __DIR__ . '/../src/php/config/db.php';
require_once __DIR__ . '/../src/php/includes/session.php';
require_once __DIR__ . '/../src/php/includes/auth.php';

$role = $argv[1] ?? 'student';

startSession();

if ($role === 'student') {
    $res = studentLogin('student1@srit.edu', 'student123');
    if (!$res['success']) {
        die("Student login failed: " . ($res['error'] ?? ''));
    }
    file_put_contents(__DIR__ . '/../headers_student.json', json_encode(['Cookie' => 'PHPSESSID=' . session_id()]));
    echo "Student session created: " . session_id() . "\n";
} else {
    $res = adminLogin('admin@testplatform.com', 'admin123');
    if (!$res['success']) {
        die("Admin login failed: " . ($res['error'] ?? ''));
    }
    file_put_contents(__DIR__ . '/../headers_admin.json', json_encode(['Cookie' => 'PHPSESSID=' . session_id()]));
    echo "Admin session created: " . session_id() . "\n";
}
