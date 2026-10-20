<?php
$pageTitle = 'Student Profile';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Student info with course/batch/college
$stmt = $pdo->prepare("
    SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    WHERE s.id = ?
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Build section display
$sectionDisplay = '';
if (!empty($student['section'])) {
    $sectionDisplay = ' — Section ' . $student['section'];
}

// Quick stats
$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = ?");
$stmt->execute([$studentId]);
$totalSubmissions = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = ? AND status = 'evaluated'");
$stmt->execute([$studentId]);
$evaluatedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT ROUND(AVG(CASE WHEN s.total_marks > 0 THEN (s.total_marks_obtained / s.total_marks) * 100 END), 1) FROM submissions s WHERE s.student_id = ? AND s.status = 'evaluated'");
$stmt->execute([$studentId]);
$avgScore = $stmt->fetchColumn() ?: 0;

// Completion rate
$stmt = $pdo->prepare("SELECT COUNT(*) FROM submissions WHERE student_id = ? AND status IN ('evaluated', 'submitted')");
$stmt->execute([$studentId]);
$completedCount = (int)$stmt->fetchColumn();
$completionRate = $totalSubmissions > 0 ? round(($completedCount / $totalSubmissions) * 100) : 0;

// Total assigned tests
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tests t JOIN batches b ON b.id = t.batch_id JOIN students s ON s.batch_id = b.id WHERE s.id = ?");
$stmt->execute([$studentId]);
$totalTests = (int)$stmt->fetchColumn();

// Recent activity
$stmt = $pdo->prepare("
    SELECT t.title, s.status, s.submitted_at, s.total_marks_obtained, s.total_marks, s.started_at
    FROM submissions s
    JOIN tests t ON t.id = s.test_id
    WHERE s.student_id = ?
    ORDER BY COALESCE(s.submitted_at, s.started_at) DESC
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentActivity = $stmt->fetchAll();

$firstName = explode(' ', $student['name'])[0];
$fullName = $student['name'];
$today = new DateTime();
$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');
$currentPage = 'profile';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Test Platform</title>
    <meta name="description" content="Manage your student account settings, view enrolled course and college details, and update your security preferences.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/student.css') ? filemtime(__DIR__ . '/../../../../assets/css/student.css') : time() ?>">
    <!-- Non-critical Chatbot Stylesheet (deferred to avoid render blocking) -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/chatbot.css') ? filemtime(__DIR__ . '/../../../../assets/css/chatbot.css') : time() ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css"></noscript>
    <style>
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
    </style>
</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1 class="welcome-heading">My Profile</h1>
                        <p class="welcome-subtitle"><?= h($student['course_name']) ?></p>
                        <p class="welcome-batch">Batch <?= h($student['batch_name']) ?><?= $sectionDisplay ? h($sectionDisplay) : '' ?></p>
                    </div>
                    <div class="date-card">
                        <div class="date-card-icon">
                            <?= icon('calendar', 24, 'var(--accent)') ?>
                        </div>
                        <div class="date-card-info">
                            <div class="date-card-date"><?= h($formattedDate) ?></div>
                            <div class="date-card-day"><?= h($dayName) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <!-- Quick Stats Row -->
<div class="stats-row" style="margin-bottom:var(--space-6);">

    <!-- Assigned Tests → Full Analysis -->
    <a
        href="test-analysis.php"
        class="stat-card-gradient stat-card-total"
        style="text-decoration:none; color:inherit;"
    >
        <div class="stat-card-icon">
            <?= icon('test', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $totalTests ?>
        </div>

        <div class="stat-card-label">
            Assigned Tests
        </div>

        <div class="stat-card-desc">
            Total assessments assigned
        </div>

        <div class="stat-card-arrow">
            <?= icon('arrow-right', 14) ?>
        </div>
    </a>


    <!-- Average Score → Average Score Page -->
    <a
        href="average-score.php"
        class="stat-card-gradient stat-card-completed"
        style="text-decoration:none; color:inherit;"
    >
        <div class="stat-card-icon">
            <?= icon('star', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $avgScore ?>%
        </div>

        <div class="stat-card-label">
            Average Score
        </div>

        <div class="stat-card-desc">
            Across evaluated tests
        </div>

        <div class="stat-card-arrow">
            <?= icon('arrow-right', 14) ?>
        </div>
    </a>


    <!-- Completion Rate — Static, No Expansion -->
    <div class="stat-card-gradient stat-card-pending">

        <div class="stat-card-icon">
            <?= icon('check-circle', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $completionRate ?>%
        </div>

        <div class="stat-card-label">
            Completion Rate
        </div>

        <div class="stat-card-desc">
            Tests submitted vs assigned
        </div>

    </div>

</div>

                <!-- Profile Content Grid -->
                <div class="profile-grid">
                    <!-- Left: Avatar Card + Student ID -->
                    <div class="profile-avatar-card">
                        <div class="profile-avatar-large">
                            <?= strtoupper($student['name'][0]) ?>
                        </div>
                        <h2 class="profile-name"><?= h($fullName) ?></h2>
                        <div class="profile-role-badge">
                            <?= icon('student', 14) ?> Student
                        </div>
                        <?php if (!empty($student['roll_number'])): ?>
                        <div class="profile-id-badge">
                            <?= icon('badge-check', 14) ?> <?= h($student['roll_number']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="profile-meta-row">
                            <span class="profile-meta-item">
                                <?= icon('book-open', 14) ?> <?= h($student['course_name']) ?>
                            </span>
                            <span class="profile-meta-item">
                                <?= icon('layers', 14) ?> Batch <?= h($student['batch_name']) ?>
                            </span>
                            <?php if (!empty($student['section'])): ?>
                            <span class="profile-meta-item">
                                <?= icon('grid', 14) ?> Section <?= h($student['section']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-stats-compact">
                            <div class="profile-stat-compact">
                                <span class="profile-stat-c-value"><?= $evaluatedCount ?></span>
                                <span class="profile-stat-c-label">Evaluated</span>
                            </div>
                            <div class="profile-stat-compact">
                                <span class="profile-stat-c-value"><?= $totalSubmissions ?></span>
                                <span class="profile-stat-c-label">Submissions</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Details + Activity -->
                    <div class="profile-right-col">
                        <!-- Personal Information -->
                        <div class="profile-details-card">
                            <h3 class="profile-section-title">
                                <?= icon('user', 16) ?> Personal Information
                            </h3>
                            <div class="profile-details-grid">
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('mail', 12) ?> Email</span>
                                    <span class="profile-detail-value"><?= h($student['email']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('phone', 12) ?> Phone</span>
                                    <span class="profile-detail-value"><?= h($student['phone']) ?? 'Not provided' ?></span>
                                </div>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('user', 12) ?> Gender</span>
                                    <span class="profile-detail-value"><?= ucfirst(h($student['gender'])) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('calendar', 12) ?> Member Since</span>
                                    <span class="profile-detail-value"><?= date('F Y', strtotime($student['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Academic Information -->
                        <div class="profile-details-card">
                            <h3 class="profile-section-title">
                                <?= icon('book-open', 16) ?> Academic Information
                            </h3>
                            <div class="profile-details-grid">
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('building2', 12) ?> College</span>
                                    <span class="profile-detail-value"><?= h($student['college_name']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('book-open', 12) ?> Course</span>
                                    <span class="profile-detail-value"><?= h($student['course_name']) ?></span>
                                </div>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('git-branch', 12) ?> Branch</span>
                                    <span class="profile-detail-value"><?= h($student['branch']) ?></span>
                                </div>
                                <?php if (!empty($student['section'])): ?>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('grid', 12) ?> Section</span>
                                    <span class="profile-detail-value"><?= h($student['section']) ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="profile-detail">
                                    <span class="profile-detail-label"><?= icon('calendar', 12) ?> Year of Joining</span>
                                    <span class="profile-detail-value"><?= h($student['year_of_joining']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="profile-details-card">
                            <h3 class="profile-section-title">
                                <?= icon('activity', 16) ?> Recent Activity
                            </h3>
                            <?php if (!empty($recentActivity)): ?>
                            <div class="profile-activity-list">
                                <?php foreach ($recentActivity as $act):
                                    $actTime = $act['submitted_at'] ?? $act['started_at'];
                                    $timeAgo = $actTime ? timeAgo($actTime) : '—';
                                    $isEvaluated = $act['status'] === 'evaluated';
                                    $isSubmitted = $act['status'] === 'submitted';
                                    $isInProgress = $act['status'] === 'in_progress';
                                    if ($isEvaluated):
                                        $pct = ($act['total_marks'] > 0) ? round(($act['total_marks_obtained'] / $act['total_marks']) * 100) : 0;
                                        $statusIcon = 'check-circle';
                                        $statusColor = $pct >= 40 ? 'var(--green)' : 'var(--red)';
                                    elseif ($isSubmitted):
                                        $statusIcon = 'clock';
                                        $statusColor = 'var(--yellow)';
                                    elseif ($isInProgress):
                                        $statusIcon = 'play';
                                        $statusColor = 'var(--accent)';
                                    else:
                                        $statusIcon = 'file-text';
                                        $statusColor = 'var(--gray-50)';
                                    endif;
                                ?>
                                <div class="profile-activity-item">
                                    <div class="profile-activity-icon profile-activity-icon--<?= $isEvaluated ? 'evaluated' : ($isSubmitted ? 'submitted' : ($isInProgress ? 'progress' : 'default')) ?>">
                                        <?= icon($statusIcon, 16) ?>
                                    </div>
                                    <div class="profile-activity-content">
                                        <div class="profile-activity-title"><?= h($act['title']) ?></div>
                                        <div class="profile-activity-meta">
                                            <span class="badge <?= $isEvaluated ? 'badge-success' : ($isSubmitted ? 'badge-pending' : 'badge-active') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $act['status'])) ?>
                                            </span>
                                            <span class="profile-activity-time"><?= $timeAgo ?></span>
                                        </div>
                                        <?php if ($isEvaluated && isset($pct)): ?>
                                        <div class="profile-activity-score">
                                            <div class="progress-bar" style="width:60px;">
                                                <div class="progress-fill <?= $pct >= 70 ? 'success' : ($pct >= 40 ? 'warning' : 'danger') ?>" style="width:<?= $pct ?>%;"></div>
                                            </div>
                                            <span class="profile-activity-pct"><?= $pct ?>%</span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding:var(--space-6) 0;">
                                <div class="empty-icon" style="opacity:0.1;"><?= icon('activity', 32) ?></div>
                                <p style="font-size:var(--fs-13);">No recent activity to show.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
    sidebar.setAttribute('aria-hidden', !isOpen);
}
function closeSidebar() { toggleSidebar(false); }
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
    if (e.key === 'Tab') {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar || !sidebar.classList.contains('open')) return;
        const f = sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length-1].focus(); }
        else if (!e.shiftKey && document.activeElement === f[f.length-1]) { e.preventDefault(); f[0].focus(); }
    }
});
</script>
</body>
</html>
