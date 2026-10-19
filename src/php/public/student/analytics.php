<?php
$pageTitle = 'My Analytics';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Student info
$stmt = $pdo->prepare("SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo FROM students s JOIN batches b ON b.id = s.batch_id JOIN courses c ON c.id = b.course_id JOIN colleges cl ON cl.id = c.college_id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// All tests with submissions
$stmt = $pdo->prepare("
    SELECT t.title, t.duration_minutes, t.status AS test_status,
           s.status AS submission_status,
           s.total_marks_obtained, s.total_marks, s.submitted_at, s.started_at
    FROM tests t
    LEFT JOIN submissions s ON s.test_id = t.id AND s.student_id = ?
    JOIN batches b ON b.id = t.batch_id
    JOIN students st ON st.batch_id = b.id
    WHERE st.id = ?
    ORDER BY t.start_time DESC
");
$stmt->execute([$studentId, $studentId]);
$allData = $stmt->fetchAll();

// Compute analytics
$totalTests = count($allData);
$evaluated = array_filter($allData, fn($r) => $r['submission_status'] === 'evaluated');
$submitted = array_filter($allData, fn($r) => $r['submission_status'] === 'submitted');
$inProgress = array_filter($allData, fn($r) => $r['submission_status'] === 'in_progress');
$notStarted = array_filter($allData, fn($r) => !$r['submission_status']);

$evaluatedCount = count($evaluated);
$submittedCount = count($submitted);
$inProgressCount = count($inProgress);
$notStartedCount = count($notStarted);

$avgPercentage = 0;
$bestPercentage = 0;
$lowestPercentage = 100;
$scoreDistribution = ['excellent' => 0, 'good' => 0, 'average' => 0, 'poor' => 0];

if ($evaluatedCount > 0) {
    $totalPct = 0;
    foreach ($evaluated as $r) {
        $pct = ($r['total_marks'] > 0) ? ($r['total_marks_obtained'] / $r['total_marks']) * 100 : 0;
        $totalPct += $pct;
        if ($pct > $bestPercentage) $bestPercentage = $pct;
        if ($pct < $lowestPercentage) $lowestPercentage = $pct;
        if ($pct >= 80) $scoreDistribution['excellent']++;
        elseif ($pct >= 60) $scoreDistribution['good']++;
        elseif ($pct >= 40) $scoreDistribution['average']++;
        else $scoreDistribution['poor']++;
    }
    $avgPercentage = round($totalPct / $evaluatedCount, 1);
    $bestPercentage = round($bestPercentage);
    $lowestPercentage = round($lowestPercentage);
} else {
    $lowestPercentage = 0;
}

$firstName = explode(' ', $student['name'])[0];
$today = new DateTime();
$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');
$currentPage = 'analytics';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Test Platform</title>
    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/student.css') ? filemtime(__DIR__ . '/../../../../assets/css/student.css') : time() ?>">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/chatbot.css') ? filemtime(__DIR__ . '/../../../../assets/css/chatbot.css') : time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 100 900;
            font-display: swap;
            src: url('<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }

        a.stat-card-link,
        a.stat-card-gradient,
        .stat-card-link,
        .stat-card-gradient,
        .stats-row a {
            text-decoration: none !important;
            color: inherit !important;
        }

        a.stat-card-link:hover,
        a.stat-card-gradient:hover,
        .stat-card-link:hover,
        .stat-card-gradient:hover,
        .stats-row a:hover,
        a.stat-card-link:focus,
        a.stat-card-gradient:focus,
        .stat-card-link:focus,
        .stat-card-gradient:focus,
        .stats-row a:focus,
        a.stat-card-link *,
        a.stat-card-gradient *,
        .stat-card-link *,
        .stat-card-gradient *,
        .stats-row a * {
            text-decoration: none !important;
        }
    </style>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0&display=swap">
</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1 class="welcome-heading">My Analytics</h1>
                        <p class="welcome-subtitle"><?= h($student['course_name']) ?></p>
                        <p class="welcome-batch">Batch <?= h($student['batch_name']) ?><?= !empty($student['section']) ? ' — Section ' . h($student['section']) : '' ?></p>
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

                <!-- Overview Stats -->
                <div class="stats-row">
                    <a href="test-analysis.php" class="stat-card-gradient stat-card-total">
    <div class="stat-card-icon">
        <?= icon('doc.text.fill', 24) ?>
    </div>

    <div class="stat-card-value">
        <?= $totalTests ?>
    </div>

    <div class="stat-card-label">
        Total Tests
    </div>

    <div class="stat-card-desc">
        All assigned assessments
    </div>
</a>
                    <a href="evaluated.php" class="stat-card-gradient stat-card-completed">
    <div class="stat-card-icon">
        <?= icon('check-circle', 24) ?>
    </div>

    <div class="stat-card-value"><?= $evaluatedCount ?></div>

    <div class="stat-card-label">Evaluated</div>

    <div class="stat-card-desc">Graded assessments</div>

    <div class="stat-card-arrow">
        <?= icon('arrow-right', 14) ?>
    </div>
</a>
        
                    <a href="average-score.php" class="stat-card-gradient stat-card-pending">
    <div class="stat-card-icon"><?= icon('graph', 24) ?></div>

    <div class="stat-card-value">
        <?= $avgPercentage ?>%
    </div>

    <div class="stat-card-label">
        Avg Score
    </div>

    <div class="stat-card-desc">
        Average performance
    </div>

    <div class="stat-card-arrow">
        <?= icon('arrow-right', 14) ?>
    </div>
</a>
                </div>

                <!-- Status Breakdown -->
                <div class="analytics-grid">
                    <!-- Test Status -->
                    <div class="card-flat">
                        <div class="card-header">
                            <h3><?= icon('status', 16) ?> Test Status Overview</h3>
                        </div>
                        <div class="card-body">
                            <div class="analytics-bar-list">
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Evaluated</span>
                                    <span class="analytics-bar-value"><?= $evaluatedCount ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill success" style="width:<?= $totalTests > 0 ? ($evaluatedCount / $totalTests) * 100 : 0 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Submitted</span>
                                    <span class="analytics-bar-value"><?= $submittedCount ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill pending" style="width:<?= $totalTests > 0 ? ($submittedCount / $totalTests) * 100 : 0 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">In Progress</span>
                                    <span class="analytics-bar-value"><?= $inProgressCount ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill active" style="width:<?= $totalTests > 0 ? ($inProgressCount / $totalTests) * 100 : 0 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Not Started</span>
                                    <span class="analytics-bar-value"><?= $notStartedCount ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill" style="background:var(--gray-20);width:<?= $totalTests > 0 ? ($notStartedCount / $totalTests) * 100 : 0 ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Score Distribution -->
                    <div class="card-flat">
                        <div class="card-header">
                            <h3><?= icon('chart', 16) ?> Score Distribution</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($evaluatedCount > 0): ?>
                            <div class="analytics-bar-list">
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Excellent (80-100%)</span>
                                    <span class="analytics-bar-value"><?= $scoreDistribution['excellent'] ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill success" style="width:<?= ($scoreDistribution['excellent'] / $evaluatedCount) * 100 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Good (60-79%)</span>
                                    <span class="analytics-bar-value"><?= $scoreDistribution['good'] ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill active" style="width:<?= ($scoreDistribution['good'] / $evaluatedCount) * 100 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Average (40-59%)</span>
                                    <span class="analytics-bar-value"><?= $scoreDistribution['average'] ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill pending" style="width:<?= ($scoreDistribution['average'] / $evaluatedCount) * 100 ?>%;"></div>
                                    </div>
                                </div>
                                <div class="analytics-bar-item">
                                    <span class="analytics-bar-label">Poor (0-39%)</span>
                                    <span class="analytics-bar-value"><?= $scoreDistribution['poor'] ?></span>
                                    <div class="analytics-bar-track">
                                        <div class="analytics-bar-fill danger" style="width:<?= ($scoreDistribution['poor'] / $evaluatedCount) * 100 ?>%;"></div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="empty-state" style="padding:var(--space-8) var(--space-4);">
                                <div class="empty-icon"><?= icon('graph', 48) ?></div>
                                <p>No evaluated tests yet to show distribution.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Performance Summary -->
                    <div class="card-flat" style="grid-column:1/-1;">
                        <div class="card-header">
                            <h3><?= icon('graph', 16) ?> Performance Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="analytics-summary-grid">
                                <div class="analytics-summary-item">
                                    <span class="analytics-summary-label">Best Score</span>
                                    <span class="analytics-summary-value" style="color:var(--green);"><?= $bestPercentage ?>%</span>
                                </div>
                                <div class="analytics-summary-item">
                                    <span class="analytics-summary-label">Average Score</span>
                                    <span class="analytics-summary-value" style="color:var(--accent);"><?= $avgPercentage ?>%</span>
                                </div>
                                <div class="analytics-summary-item">
                                    <span class="analytics-summary-label">Lowest Score</span>
                                    <span class="analytics-summary-value" style="color:var(--red);"><?= $lowestPercentage ?>%</span>
                                </div>
                                <div class="analytics-summary-item">
                                    <span class="analytics-summary-label">Tests Completed</span>
                                    <span class="analytics-summary-value"><?= $evaluatedCount ?>/<?= $totalTests ?></span>
                                </div>
                                <div class="analytics-summary-item">
                                    <span class="analytics-summary-label">Completion Rate</span>
                                    <span class="analytics-summary-value"><?= $totalTests > 0 ? round(($evaluatedCount / $totalTests) * 100) : 0 ?>%</span>
                                </div>
                            </div>
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
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    document.cookie = 'theme=' + newTheme + ';path=/;max-age=31536000;SameSite=Lax';
    updateThemeUI(newTheme);
}
function updateThemeUI(theme) {
    const label = document.getElementById('themeLabel');
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    document.querySelectorAll('.theme-icon').forEach(el => {
        el.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
    });
}
(function() {
    const saved = localStorage.getItem('theme') || (document.cookie.match(/(^|; )theme=([^;]+)/) || [])[2];
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        updateThemeUI(saved);
    }
})();
</script>
</body>
</html>
