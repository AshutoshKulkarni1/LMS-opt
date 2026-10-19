<?php
$pageTitle = 'Student Dashboard';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';
startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Get student info with college details
$stmt = $pdo->prepare("SELECT s.*, b.name AS batch_name, c.name AS course_name, cl.name AS college_name, cl.logo AS college_logo FROM students s JOIN batches b ON b.id = s.batch_id JOIN courses c ON c.id = b.course_id JOIN colleges cl ON cl.id = c.college_id WHERE s.id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Get tests for this student's batch
$tests = getStudentTests($studentId);

// Stats
$totalTests = count($tests);
$completedTests = count(array_filter($tests, fn($t) => $t['submission_status'] === 'evaluated'));
$pendingTests = count(array_filter($tests, fn($t) => $t['submission_status'] === 'in_progress' || ($t['status'] === 'active' && !$t['submission_status'])));
$totalQuestions = array_sum(array_map(
    fn($t) => (int)($t['total_questions'] ?? 0),
    $tests
));
// Latest completed test details
$completedTestsList = array_values(array_filter($tests, fn($t) =>
    $t['submission_status'] === 'evaluated'
));

$latestCompletedTest = $completedTestsList[0] ?? null;

$completedPercentage = 0;
$completedTimeTaken = '—';
$completedDate = '—';

if ($latestCompletedTest) {
    // Calculate percentage
    $obtainedMarks = (float)($latestCompletedTest['total_marks_obtained'] ?? 0);
    $totalMarks = (float)($latestCompletedTest['total_marks'] ?? 0);

    if ($totalMarks > 0) {
        $completedPercentage = round(($obtainedMarks / $totalMarks) * 100);
    }

    // Format completion date
    if (!empty($latestCompletedTest['submitted_at'])) {
        $completedDate = (new DateTime($latestCompletedTest['submitted_at']))
            ->format('M j, Y • g:i A');
    }

    // Calculate actual time taken
    if (
        !empty($latestCompletedTest['started_at']) &&
        !empty($latestCompletedTest['submitted_at'])
    ) {
        $start = new DateTime($latestCompletedTest['started_at']);
        $end = new DateTime($latestCompletedTest['submitted_at']);

        $seconds = max(
            0,
            $end->getTimestamp() - $start->getTimestamp()
        );

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        $completedTimeTaken = $minutes . 'm';

        if ($remainingSeconds > 0) {
            $completedTimeTaken .= ' ' . $remainingSeconds . 's';
        }
    }
}
$inProgressTests = count(array_filter($tests, fn($t) => $t['submission_status'] === 'in_progress'));

$notStartedTests = count(array_filter($tests, fn($t) =>
    empty($t['submission_status']) &&
    $t['status'] !== 'completed'
));

$completionRate = $totalTests > 0
    ? round(($completedTests / $totalTests) * 100)
    : 0;
// Date helpers
$today = new DateTime();
$dayName = $today->format('l');
$formattedDate = $today->format('F j, Y');
$firstName = explode(' ', $student['name'])[0];
$currentPage = 'dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Test Platform</title>
    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
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
    </style>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0&display=swap">
</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1 class="welcome-heading">Welcome back, <?= h($firstName) ?></h1>
                        <p class="welcome-subtitle"><?= h($student['course_name']) ?></p>
                        <p class="welcome-batch">Batch <?= h($student['batch_name']) ?><?= !empty($student['section']) ? ' — Section ' . h($student['section']) : '' ?></p>
                    </div>
                    <!-- Date Card -->
                    <div class="date-card">
                        <div class="date-card-icon">
                            <?= icon('calendar.circle.fill', 24) ?>
                        </div>
                        <div class="date-card-info">
                            <div class="date-card-date"><?= h($formattedDate) ?></div>
                            <div class="date-card-day"><?= h($dayName) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Stat Cards -->
                <!-- KPI Cards — Apple SF Symbols naming, Lucide rendered, Material fallback -->
                <div class="stats-row">
                    <div class="stat-card-gradient stat-card-total expandable-card">

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

    <div class="stat-card-details">

        <div class="stat-detail-item">
            <span>Completed</span>
            <strong><?= $completedTests ?></strong>
        </div>

        <div class="stat-detail-item">
            <span>Pending / Active</span>
            <strong><?= $pendingTests ?></strong>
        </div>

        <div class="stat-detail-item">
            <span>In Progress</span>
            <strong><?= $inProgressTests ?></strong>
        </div>

        <div class="stat-detail-item">
            <span>Not Started</span>
            <strong><?= $notStartedTests ?></strong>
        </div>

        <div class="stat-detail-divider"></div>

        <div class="stat-detail-item completion-item">
            <span>Completion Rate</span>
            <strong><?= $completionRate ?>%</strong>
        </div>

        <div class="stat-detail-item">
            <span>Total Questions</span>
            <strong><?= $totalQuestions ?></strong>
        </div>

        <!-- View Full Analysis — only visible when expanded -->
        <a href="test-analysis.php" class="full-analysis-link">
            <span>View Full Analysis</span>
            <?= icon('arrow.right', 18) ?>
        </a>

    </div>

</div>
                    <div class="stat-card-gradient stat-card-completed expandable-card">
                        <div class="stat-card-icon">
                            <?= icon('checkmark.circle.fill', 24) ?>
                        </div>
                        <div class="stat-card-value"><?= $completedTests ?></div>
                        <div class="stat-card-label">Completed</div>
                        <div class="stat-card-desc">Evaluated submissions</div>
                        
                        <div class="stat-card-details">

    <?php if ($latestCompletedTest): ?>

        <div class="completed-test-name">
            <?= htmlspecialchars($latestCompletedTest['title']) ?>
        </div>

        <div class="completed-test-date">
            Completed <?= htmlspecialchars($completedDate) ?>
        </div>

        <div class="completed-test-meta">

            <div class="completed-test-meta-item">
                <span>Score</span>
                <strong>
                    <?= (float)$latestCompletedTest['total_marks_obtained'] ?>
                    /
                    <?= (float)$latestCompletedTest['total_marks'] ?>
                </strong>
            </div>

            <div class="completed-test-meta-item">
                <span>Percentage</span>
                <strong><?= $completedPercentage ?>%</strong>
            </div>

            <div class="completed-test-meta-item">
                <span>Time Taken</span>
                <strong><?= htmlspecialchars($completedTimeTaken) ?></strong>
            </div>

        </div>

        <a href="test-analysis.php" class="completed-analysis-link">
            View Analysis
            <?= icon('chevron.right', 16) ?>
        </a>

    <?php else: ?>

        <div class="completed-empty">
            <strong>No completed tests yet</strong>
            <span>Complete an assessment to see your results here.</span>
        </div>

    <?php endif; ?>

</div>
                       
                    </div>
                    <div class="stat-card-gradient stat-card-pending expandable-card">
                        <div class="stat-card-icon">
                            <?= icon('clock.badge.exclamationmark.fill', 24) ?>
                        </div>
                        <div class="stat-card-value"><?= $pendingTests ?></div>
                        <div class="stat-card-label">Pending / Active</div>
                        <div class="stat-card-desc">In progress or not started</div>
                       
                        <div class="stat-card-details">
    <div class="stat-detail-item">
        <span>Pending / Active</span>
        <strong><?= $pendingTests ?></strong>
    </div>

    <div class="stat-detail-item">
        <span>In Progress</span>
        <strong><?= $inProgressTests ?></strong>
    </div>

    <div class="stat-detail-item">
        <span>Not Started</span>
        <strong><?= $notStartedTests ?></strong>
    </div>
    
</div>
                    </div>
                </div>

                <?= flashMessage() ?>

                <!-- Your Tests Section -->
                <div class="tests-section">
                    <div class="tests-section-header">
                        <h2 class="tests-section-title">Your Tests</h2>
                        <div class="tests-view-toggle">
                            <button class="toggle-btn active" data-view="table" onclick="setView('table')">
                                <?= icon('list', 16) ?>
                                Table View
                            </button>
                            <button class="toggle-btn" data-view="card" onclick="setView('card')">
                                <?= icon('grid', 16) ?>
                                Card View
                            </button>
                        </div>
                    </div>

                    <?php if (empty($tests)): ?>
                        <div class="card-flat">
                            <div class="card-body">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <?= icon('tray.fill', 56) ?>
                                    </div>
                                    <h3>No Tests Yet</h3>
                                    <p>No additional assessments available.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Table View -->
                        <div class="tests-table-view" id="tableView">
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="th-icon"><?= icon('doc.text.fill', 14) ?></div>
                                                Assessment
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('timer', 14) ?></div>
                                                Duration
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('circle.fill', 14) ?></div>
                                                Status
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('progress.indicator', 14) ?></div>
                                                Your Status
                                            </th>
                                            <th class="actions">
                                                <div class="th-icon"><?= icon('arrow.right.circle.fill', 14) ?></div>
                                                Action
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($tests as $t): ?>
                                        <?php
                                            $now = time();
                                            $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                                            $end = $t['end_time'] ? strtotime($t['end_time']) : null;

                                            // Determine if test is joinable based on status and time windows
                                            $canStart = false;

                                            if ($t['submission_status'] === 'in_progress') {
                                                $canStart = true; // Already started
                                            } elseif ($t['status'] === 'active') {
                                                $startOk = $start === null || $now >= $start;
                                                $endOk = $end === null || $now <= $end;
                                                $canStart = $startOk && $endOk;
                                            }

                                            if ($t['submission_status'] === 'evaluated') {
                                                $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-ghost">' . icon('chart', 14) . ' View Result</a>';
                                            } elseif ($t['submission_status'] === 'submitted') {
                                                $isPendingReview = ($t['evaluation_status'] ?? '') === 'pending_manual_review';
                                                $actionBtn = $isPendingReview
                                                    ? '<span class="badge badge-pending">' . icon('clock', 12) . ' Under Evaluation</span>'
                                                    : '<span class="badge badge-pending">' . icon('clock', 12) . ' Submitted</span>';
                                            } elseif ($t['submission_status'] === 'in_progress' && $t['status'] !== 'completed') {
                                                $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' ' . ($t['status'] === 'paused' ? 'Resume (Paused)' : 'Resume') . '</a>';
                                            } elseif ($t['status'] === 'active' && $canStart) {
                                                $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' Start Test</a>';
                                            } elseif ($t['status'] === 'paused') {
                                                $actionBtn = '<span class="badge badge-pending">Paused by Admin</span>';
                                            } elseif ($t['status'] === 'upcoming') {
                                                $actionBtn = '<span class="badge badge-pending">Upcoming</span>';
                                            } elseif ($t['status'] === 'scheduled') {
                                                if ($start !== null && $now < $start) {
                                                    $mins = ceil(($start - $now) / 60);
                                                    $hours = floor($mins / 60);
                                                    $remainMins = $mins % 60;
                                                    $timeStr = $hours > 0 ? "{$hours}h {$remainMins}m" : "{$remainMins} min";
                                                    $actionBtn = '<span class="badge badge-info">' . icon('calendar', 12) . ' Starts in ' . $timeStr . '</span>';
                                                } else {
                                                    $actionBtn = '<span class="badge badge-pending">Scheduled</span>';
                                                }
                                            } elseif ($t['status'] === 'active' && !$canStart) {
                                                if ($start !== null && $now < $start) {
                                                    $mins = ceil(($start - $now) / 60);
                                                    $actionBtn = '<span class="badge badge-pending">Starts in ' . $mins . ' min</span>';
                                                } else {
                                                    $actionBtn = '<span class="badge badge-danger">Expired</span>';
                                                }
                                            } else {
                                                $actionBtn = '<span class="badge badge-pending">Missed</span>';
                                            }

            $statusClass = 'badge-info';
            if ($t['status'] === 'active') $statusClass = 'badge-active';
            elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
            elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
            elseif ($t['status'] === 'scheduled') $statusClass = 'badge-info';

                                            $submissionLabel = '';
                                            if ($t['submission_status'] === 'evaluated') {
                                                $submissionLabel = 'badge-success';
                                                $submissionText = $t['total_marks_obtained'] !== null && $t['total_marks'] > 0
                                                    ? round(($t['total_marks_obtained'] / $t['total_marks']) * 100) . '%'
                                                    : 'Evaluated';
                                            } elseif ($t['submission_status'] === 'submitted') {
                                                $submissionLabel = 'badge-pending';
                                                $submissionText = ($t['evaluation_status'] ?? '') === 'pending_manual_review' ? 'Under Evaluation' : 'Submitted';
                                            } elseif ($t['submission_status'] === 'in_progress') {
                                                $submissionLabel = 'badge-active';
                                                $submissionText = 'In Progress';
                                            } else {
                                                $submissionLabel = 'badge-info';
                                                $submissionText = 'Not Started';
                                            }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="test-name-cell">
                                                    <div class="test-icon"><?= icon('doc.text.fill', 18) ?></div>
                                                    <div>
                                                        <div class="test-name"><?= h($t['title']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="duration-cell"><?= $t['duration_minutes'] ?> min</td>
                                            <td>
                                                <span class="badge <?= $statusClass ?>"><?= ucfirst($t['status']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= $submissionLabel ?>"><?= $submissionText ?></span>
                                            </td>
                                            <td class="actions">
                                                <div class="action-cell">
                                                    <?= $actionBtn ?>
                                                    <div class="overflow-menu" onclick="toggleOverflow(this)">
                                                        <button class="btn btn-sm btn-ghost overflow-trigger" aria-label="More actions">
                                                            <?= icon('more-vertical', 14) ?>
                                                        </button>
                                                        <div class="overflow-dropdown">
                                                            <a href="test?test_id=<?= $t['id'] ?>" class="overflow-item"><?= icon('eye', 14) ?> View Details</a>
                                                            <a href="#" class="overflow-item"><?= icon('clock', 14) ?> View Schedule</a>
                                                            <?php if ($t['submission_status'] === 'evaluated'): ?>
                                                                <a href="#" class="overflow-item"><?= icon('download', 14) ?> Download Result</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                                                                </tr>

                                        <!-- Expanded Test Details -->
                                        <tr class="test-table-details-row">
                                            <td colspan="5">
                                                <div class="test-table-details-inner">
                                                    <div class="test-table-details-content">

                                                        <div class="test-table-details-header">
                                                            <div class="test-card-icon">
                                                                <?= icon('doc.text.fill', 24) ?>
                                                            </div>

                                                            <div class="test-table-details-title">
                                                                <h3><?= h($t['title']) ?></h3>
                                                                <span>
                                                                    <?= icon('clock', 13) ?>
                                                                    <?= $t['duration_minutes'] ?> min
                                                                </span>
                                                            </div>

                                                            <span class="badge <?= $statusClass ?>">
                                                                <?= ucfirst($t['status']) ?>
                                                            </span>
                                                        </div>

                                                        <div class="test-table-details-body">

                                                            <div class="test-detail-description">
                                                                <span class="test-detail-label">
                                                                    Description
                                                                </span>

                                                                <p>
                                                                    Assessment for evaluating your
                                                                    understanding of the topics covered
                                                                    in this test.
                                                                </p>
                                                            </div>

                                                            <div class="test-detail-grid">

                                                                <div class="test-detail-item">
                                                                    <span>Duration</span>
                                                                    <strong>
                                                                        <?= $t['duration_minutes'] ?> min
                                                                    </strong>
                                                                </div>

                                                                <div class="test-detail-item">
                                                                    <span>Your Status</span>
                                                                    <strong>
                                                                        <?= $t['submission_status']
                                                                            ? ucfirst(str_replace('_', ' ', $t['submission_status']))
                                                                            : 'Not Started'
                                                                        ?>
                                                                    </strong>
                                                                </div>

                                                                <div class="test-detail-item">
                                                                    <span>Start Time</span>
                                                                    <strong>
                                                                        <?= !empty($t['start_time'])
                                                                            ? date('g:i A', strtotime($t['start_time']))
                                                                            : '—'
                                                                        ?>
                                                                    </strong>
                                                                </div>

                                                                <div class="test-detail-item">
                                                                    <span>End Time</span>
                                                                    <strong>
                                                                        <?= !empty($t['end_time'])
                                                                            ? date('g:i A', strtotime($t['end_time']))
                                                                            : '—'
                                                                        ?>
                                                                    </strong>
                                                                </div>

                                                            </div>

                                                            <a
                                                                href="my-tests.php?test_id=<?= $t['id'] ?>"
                                                                class="test-full-details-link"
                                                                onclick="event.stopPropagation();"
                                                            >
                                                                <span>View Full Details</span>
                                                                <?= icon('arrow.right', 16) ?>
                                                            </a>

                                                        </div>

                                                    </div>
                                                </div>
                                            </td>
                                        </tr>

                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Card View -->
                        <div class="tests-card-view hidden" id="cardView">
                            <div class="card-grid">
                                <?php foreach ($tests as $t):
                                    $now = time();
                                    $start = $t['start_time'] ? strtotime($t['start_time']) : null;
                                    $end = $t['end_time'] ? strtotime($t['end_time']) : null;
                                    $canStart = false;
                                    if ($t['submission_status'] === 'in_progress') {
                                        $canStart = true;
                                    } elseif ($t['status'] === 'active') {
                                        $startOk = $start === null || $now >= $start;
                                        $endOk = $end === null || $now <= $end;
                                        $canStart = $startOk && $endOk;
                                    }
                                    if ($t['submission_status'] === 'evaluated') {
                                        $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-ghost">' . icon('chart', 14) . ' View Result</a>';
                                    } elseif ($t['submission_status'] === 'submitted') {
                                        $isPendingReview = ($t['evaluation_status'] ?? '') === 'pending_manual_review';
                                        $actionBtn = $isPendingReview
                                            ? '<span class="badge badge-pending">' . icon('clock', 12) . ' Under Evaluation</span>'
                                            : '<span class="badge badge-pending">' . icon('clock', 12) . ' Submitted</span>';
                                    } elseif ($t['submission_status'] === 'in_progress' && $t['status'] !== 'completed') {
                                        $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' ' . ($t['status'] === 'paused' ? 'Resume (Paused)' : 'Resume') . '</a>';
                                    } elseif ($t['status'] === 'active' && $canStart) {
                                        $actionBtn = '<a href="test?test_id=' . $t['id'] . '" class="btn btn-sm btn-primary">' . icon('play', 14) . ' Start Test</a>';
                                    } elseif ($t['status'] === 'paused') {
                                        $actionBtn = '<span class="badge badge-pending">Paused by Admin</span>';
                                    } elseif ($t['status'] === 'upcoming') {
                                        $actionBtn = '<span class="badge badge-pending">Upcoming</span>';
                                    } elseif ($t['status'] === 'scheduled') {
                                        if ($start !== null && $now < $start) {
                                            $mins = ceil(($start - $now) / 60);
                                            $hours = floor($mins / 60);
                                            $remainMins = $mins % 60;
                                            $timeStr = $hours > 0 ? "{$hours}h {$remainMins}m" : "{$remainMins} min";
                                            $actionBtn = '<span class="badge badge-info">' . icon('calendar', 12) . ' Starts in ' . $timeStr . '</span>';
                                        } else {
                                            $actionBtn = '<span class="badge badge-pending">Scheduled</span>';
                                        }
                                    } elseif ($t['status'] === 'active' && !$canStart) {
                                        if ($start !== null && $now < $start) {
                                            $mins = ceil(($start - $now) / 60);
                                            $actionBtn = '<span class="badge badge-pending">Starts in ' . $mins . ' min</span>';
                                        } else {
                                            $actionBtn = '<span class="badge badge-danger">Expired</span>';
                                        }
                                    } else {
                                        $actionBtn = '<span class="badge badge-pending">Missed</span>';
                                    }
                                    $statusClass = 'badge-info';
                                    if ($t['status'] === 'active') $statusClass = 'badge-active';
                                    elseif ($t['status'] === 'paused') $statusClass = 'badge-pending';
                                    elseif ($t['status'] === 'completed') $statusClass = 'badge-success';
                                    elseif ($t['status'] === 'scheduled') $statusClass = 'badge-info';
                                ?>
                                <div class="test-card test-expandable">

    <div class="test-card-top">

        <div class="test-card-icon">
            <?= icon('doc.text.fill', 24) ?>
        </div>

        <span class="badge <?= $statusClass ?>">
            <?= ucfirst($t['status']) ?>
        </span>

    </div>

    <h3 class="test-card-title">
        <?= h($t['title']) ?>
    </h3>

    <div class="test-card-meta">
        <span>
            <?= icon('clock', 13) ?>
            <?= $t['duration_minutes'] ?> min
        </span>
    </div>


    <!-- EXPANDED DETAILS -->

    <div class="test-card-details">

        <div class="test-detail-description">

            <span class="test-detail-label">
                Description
            </span>

            <p>
                Assessment for evaluating your understanding
                of the topics covered in this test.
            </p>

        </div>


        <div class="test-detail-grid">

            <div class="test-detail-item">
                <span>Duration</span>
                <strong><?= $t['duration_minutes'] ?> min</strong>
            </div>

            <div class="test-detail-item">
                <span>Your Status</span>
                <strong>
                    <?= $t['submission_status']
                        ? ucfirst(str_replace('_', ' ', $t['submission_status']))
                        : 'Not Started'
                    ?>
                </strong>
            </div>

            <div class="test-detail-item">
                <span>Start Time</span>
                <strong>
                    <?= !empty($t['start_time'])
                        ? date('g:i A', strtotime($t['start_time']))
                        : '—'
                    ?>
                </strong>
            </div>

            <div class="test-detail-item">
                <span>End Time</span>
                <strong>
                    <?= !empty($t['end_time'])
                        ? date('g:i A', strtotime($t['end_time']))
                        : '—'
                    ?>
                </strong>
            </div>

        </div>


        <a
            href="my-tests.php?test_id=<?= $t['id'] ?>"
            class="test-full-details-link"
            onclick="event.stopPropagation();"
        >
            <span>View Full Details</span>
            <?= icon('arrow.right', 16) ?>
        </a>

    </div>

</div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Bottom Information Card -->
                <div class="info-card">
                    <div class="info-card-icon">
                        <?= icon('info', 20) ?>
                    </div>
                    <div class="info-card-text">
                        You can review completed assessments or wait for newly published assessments.
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
// Toggle sidebar on mobile
function toggleSidebar(forceState) {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = forceState !== undefined ? forceState : !sidebar.classList.contains('open');
    sidebar.classList.toggle('open', isOpen);
    overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('sidebar-open', isOpen);
    // ARIA
    sidebar.setAttribute('aria-hidden', !isOpen);
}
function closeSidebar() { toggleSidebar(false); }

// Close sidebar on Escape / overlay click
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeSidebar();
});

//expand the card clicked
// Expand/collapse cards with smooth movement + resizing

const statsRow = document.querySelector('.stats-row');
const expandableCards = document.querySelectorAll('.expandable-card');

function animateCards(callback) {

    const cards = [...expandableCards];

    // Remember current position and size
    const first = new Map();

    cards.forEach(card => {
        const rect = card.getBoundingClientRect();

        first.set(card, {
            left: rect.left,
            top: rect.top,
            width: rect.width,
            height: rect.height
        });
    });

    // Change the layout
    callback();

    // Force the browser to calculate the new layout
    statsRow.offsetHeight;

    // Animate old position/size → new position/size
    cards.forEach(card => {

        const oldRect = first.get(card);
        const newRect = card.getBoundingClientRect();

        const deltaX = oldRect.left - newRect.left;
        const deltaY = oldRect.top - newRect.top;

        const scaleX = oldRect.width / newRect.width;
        const scaleY = oldRect.height / newRect.height;

        card.animate(
            [
                {
                    transform:
                        `translate(${deltaX}px, ${deltaY}px) scale(${scaleX}, ${scaleY})`
                },
                {
                    transform: 'translate(0, 0) scale(1, 1)'
                }
            ],
            {
                duration: 500,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                fill: 'both'
            }
        );
    });
}


expandableCards.forEach(card => {

    card.addEventListener('click', function () {

        const isAlreadyExpanded =
            card.classList.contains('expanded');

        animateCards(() => {

            // Reset all cards
            expandableCards.forEach(otherCard => {
                otherCard.classList.remove('expanded');
                otherCard.classList.remove('side-top');
                otherCard.classList.remove('side-bottom');
            });

            statsRow.classList.remove('focus-layout');

            // Expand clicked card
            if (!isAlreadyExpanded) {

                card.classList.add('expanded');
                statsRow.classList.add('focus-layout');

                const otherCards =
                    [...expandableCards]
                    .filter(other => other !== card);

                otherCards[0].classList.add('side-top');
                otherCards[1].classList.add('side-bottom');
            }
        });

    });

});

// Focus trap when sidebar open
document.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar || !sidebar.classList.contains('open')) return;
    const f = sidebar.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length-1].focus(); }
    else if (!e.shiftKey && document.activeElement === f[f.length-1]) { e.preventDefault(); f[0].focus(); }
});

// View toggle
function setView(view) {
    document.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.toggle-btn[data-view="${view}"]`).classList.add('active');
    if (view === 'table') {
        document.getElementById('tableView').classList.remove('hidden');
        document.getElementById('cardView').classList.add('hidden');
    } else {
        document.getElementById('tableView').classList.add('hidden');
        document.getElementById('cardView').classList.remove('hidden');
    }
}

// Overflow menu toggle
function toggleOverflow(el) {
    const wasOpen = el.classList.contains('open');
    document.querySelectorAll('.overflow-menu.open').forEach(m => m.classList.remove('open'));
    if (!wasOpen) el.classList.add('open');
}

// Close overflow on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.overflow-menu')) {
        document.querySelectorAll('.overflow-menu.open').forEach(m => m.classList.remove('open'));
    }
});

// Theme switcher
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    const newTheme = isDark ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateThemeUI(newTheme);
}
function updateThemeUI(theme) {
    const label = document.getElementById('themeLabel');
    if (label) label.textContent = theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    document.querySelectorAll('.theme-icon').forEach(el => {
        el.textContent = theme === 'dark' ? 'light_mode' : 'dark_mode';
    });
}
// Restore saved theme
(function() {
    const saved = localStorage.getItem('theme');
    if (saved) {
        document.documentElement.setAttribute('data-theme', saved);
        updateThemeUI(saved);
    }
})();


// Test card expansion

const testExpandableCards =
    document.querySelectorAll('.test-expandable');

testExpandableCards.forEach(card => {

    card.addEventListener('click', function () {

        const isExpanded =
            card.classList.contains('expanded');

        // Close every test card
        testExpandableCards.forEach(otherCard => {
            otherCard.classList.remove('expanded');

            const details =
                otherCard.querySelector('.test-card-details');

            if (details) {
                details.style.maxHeight = '0px';
            }
        });

        // Open clicked card
        if (!isExpanded) {

            card.classList.add('expanded');

            const details =
                card.querySelector('.test-card-details');

            if (details) {

                requestAnimationFrame(() => {

                    details.style.maxHeight =
                        details.scrollHeight + 'px';

                });

            }

        }

    });

});
// ─────────────────────────────────────────────
// Table View Test Expansion
// ─────────────────────────────────────────────

const testTableRows =
    document.querySelectorAll('.test-row-expandable');

testTableRows.forEach(row => {

    row.addEventListener('click', function (e) {

        // Don't expand when clicking an action/link/button
        if (
            e.target.closest('a') ||
            e.target.closest('button') ||
            e.target.closest('.overflow-menu')
        ) {
            return;
        }

        const detailsRow = row.nextElementSibling;

        if (
            !detailsRow ||
            !detailsRow.classList.contains('test-table-details-row')
        ) {
            return;
        }

        const isExpanded =
            detailsRow.classList.contains('expanded');


        // Close all other rows
        testTableRows.forEach(otherRow => {

            otherRow.classList.remove('expanded');

            const otherDetails =
                otherRow.nextElementSibling;

            if (
                otherDetails &&
                otherDetails.classList.contains(
                    'test-table-details-row'
                )
            ) {
                otherDetails.classList.remove('expanded');
            }
        });


        // Open clicked row
        if (!isExpanded) {

            row.classList.add('expanded');
            detailsRow.classList.add('expanded');

        }

    });

});

</script>
</body>
</html>
