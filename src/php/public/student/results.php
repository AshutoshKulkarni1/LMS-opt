<?php
$pageTitle = 'My Results';
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

// All finished attempts (submitted or evaluated) with objective/subjective maximums
$stmt = $pdo->prepare("
    SELECT t.title AS test_title, t.duration_minutes,
           s.id AS submission_id, s.total_marks_obtained, s.total_marks, s.submitted_at,
           s.status AS submission_status, s.evaluation_status,
           s.auto_score, s.manual_score, s.total_score,
           COALESCE(qm.max_mcq, 0)  AS max_mcq,
           COALESCE(qs.max_subj, 0) AS max_subj
    FROM submissions s
    JOIN tests t ON t.id = s.test_id
    LEFT JOIN (SELECT test_id, SUM(marks) AS max_mcq FROM questions WHERE type = 'mcq' GROUP BY test_id) qm ON qm.test_id = t.id
    LEFT JOIN (SELECT test_id, SUM(marks) AS max_subj FROM questions WHERE type <> 'mcq' GROUP BY test_id) qs ON qs.test_id = t.id
    WHERE s.student_id = ? AND s.status IN ('submitted', 'evaluated') AND s.submitted_at IS NOT NULL
    ORDER BY s.submitted_at DESC
");
$stmt->execute([$studentId]);
$allAttempts = $stmt->fetchAll();

// Split: fully evaluated results vs those awaiting manual review
$results = array_values(array_filter($allAttempts, fn($r) => $r['evaluation_status'] === 'evaluated'));
$pendingResults = array_values(array_filter($allAttempts, fn($r) => $r['evaluation_status'] === 'pending_manual_review'));

// Itemized review (question-by-question) for evaluated tests, incl. evaluator remarks
$reviewBySubmission = [];
if ($results) {
    $ids = array_column($results, 'submission_id');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT sa.submission_id, sa.marks_obtained, sa.evaluation_remarks, sa.answer_json,
               q.type, q.question_text, q.options_json, q.correct_answer, q.marks, q.sort_order
        FROM student_answers sa
        JOIN questions q ON q.id = sa.question_id
        WHERE sa.submission_id IN ($ph)
        ORDER BY sa.submission_id, q.sort_order, q.id
    ");
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $reviewBySubmission[(int)$row['submission_id']][] = $row;
    }
}

// Stats — computed ONLY over fully evaluated tests (never over pending ones)
$totalEvaluated = count($results);
$pendingCount = count($pendingResults);
$avgPercentage = 0;
$highestScore = 0;
if ($totalEvaluated > 0) {
    $totalPct = 0;
    foreach ($results as $r) {
        $pct = ($r['total_marks'] > 0) ? (($r['total_score'] ?? $r['total_marks_obtained']) / $r['total_marks']) * 100 : 0;
        $totalPct += $pct;
        if ($pct > $highestScore) $highestScore = $pct;
    }
    $avgPercentage = round($totalPct / $totalEvaluated, 1);
}

$firstName = explode(' ', $student['name'])[0];
$today = new DateTime();
$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');
$currentPage = 'results';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> | Test Platform</title>
    <meta name="description" content="Review your historical test results, total marks obtained, completion timestamps, and evaluation feedback across all courses.">
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

</head>
<body>
<?= iconSprite() ?>
<?php include __DIR__ . '/../../includes/student_header.php'; ?>
                <div class="welcome-section">
                    <div class="welcome-text">
                        <h1 class="welcome-heading">My Results</h1>
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

                <!-- Summary Stats -->
                <div class="stats-row">
                    <a href="result-details?type=evaluated" class="stat-card-link">
    <div class="stat-card-gradient stat-card-total">
        <div class="stat-card-icon"><?= icon('chart', 24) ?></div>
        <div class="stat-card-value"><?= $totalEvaluated ?></div>
        <div class="stat-card-label">Tests Evaluated</div>
        <div class="stat-card-desc">Completed assessments</div>
        <div class="stat-card-arrow"><?= icon('arrow-right', 14) ?></div>
    </div>
</a>
                    <a href="result-details?type=average" class="stat-card-link">
    <div class="stat-card-gradient stat-card-completed">
        <div class="stat-card-icon">
            <?= icon('star', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $totalEvaluated > 0 ? $avgPercentage . '%' : '—' ?>
        </div>

        <div class="stat-card-label">
            Average Score
        </div>

        <div class="stat-card-desc">
            Across all evaluated tests
        </div>

        <div class="stat-card-arrow">
            <?= icon('arrow-right', 14) ?>
        </div>
    </div>
</a>
                    <a href="result-details?type=highest" class="stat-card-link">
    <div class="stat-card-gradient stat-card-pending">
        <div class="stat-card-icon">
            <?= icon('graph', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $totalEvaluated > 0 ? round($highestScore) . '%' : '—' ?>
        </div>

        <div class="stat-card-label">
            Highest Score
        </div>

        <div class="stat-card-desc">
            Best performance
        </div>

        <div class="stat-card-arrow">
            <?= icon('arrow-right', 14) ?>
        </div>
    </div>
</a>
                    <?php if ($pendingCount > 0): ?>

<a href="result-details?type=pending" class="stat-card-link">
    <div
        class="stat-card-gradient stat-card-completed"
        style="--grad:#826A00,#B8860B;"
    >

        <div class="stat-card-icon">
            <?= icon('clock', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $pendingCount ?>
        </div>

        <div class="stat-card-label">
            Under Evaluation
        </div>

        <div class="stat-card-desc">
            Results not yet announced
        </div>

        <div class="stat-card-arrow">
            <?= icon('arrow-right', 14) ?>
        </div>

    </div>
</a>

<?php endif; ?>
                </div>

                <!-- Pending evaluation banner — no premature scores shown -->
                <?php if ($pendingCount > 0): ?>
                <div class="alert alert-warning" style="display:flex;gap:12px;align-items:flex-start;margin-bottom:16px;">
                    <svg viewBox="0 0 20 20" fill="currentColor" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"><path d="M10 2a1 1 0 0 1 .9.55l7 13A1 1 0 0 1 17 17H3a1 1 0 0 1-.9-1.45l7-13A1 1 0 0 1 10 2zm0 5a1 1 0 0 0-1 1v2a1 1 0 1 0 2 0V8a1 1 0 0 0-1-1zm0 7.2a1.1 1.1 0 1 0 0-2.2 1.1 1.1 0 0 0 0 2.2z"/></svg>
                    <div>
                        <strong>Result Not Yet Announced</strong>
                        <p style="margin:4px 0 8px;font-size:0.875rem;">
                            <?= $pendingCount ?> test<?= $pendingCount > 1 ? 's' : '' ?> <?= $pendingCount > 1 ? 'are' : 'is' ?> under evaluation.
                            Scores appear here once the evaluator finishes grading the written/coding answers.
                        </p>
                        <?php foreach ($pendingResults as $p): ?>
                            <span class="badge badge-pending" style="margin:2px 6px 2px 0;">Under Evaluation · <?= h($p['test_title']) ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="tests-section">
                    <div class="tests-section-header">
                        <h2 class="tests-section-title">Assessment Results</h2>
                    </div>

                    <?php if (empty($results)): ?>
                        <div class="card-flat">
                            <div class="card-body">
                                <div class="empty-state">
                                    <div class="empty-icon"><?= icon('chart', 56) ?></div>
                                    <h3>No Results Yet</h3>
                                    <p>Your evaluated test results will appear here once assessments are graded.</p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="tests-table-view">
                            <div class="table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>
                                                <div class="th-icon"><?= icon('test', 14) ?></div>
                                                Assessment
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('star', 14) ?></div>
                                                Score Breakdown
                                            </th>
                                            <th>
                                                <div class="th-icon"><?= icon('clock', 14) ?></div>
                                                Submitted
                                            </th>
                                            <th>Performance</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $r):
                                            $obtained = (float)($r['total_score'] ?? $r['total_marks_obtained']);
                                            $total = (float)$r['total_marks'];
                                            $pct = ($total > 0) ? round(($obtained / $total) * 100) : 0;
                                            $pass = $pct >= 40;
                                            $barClass = $pct >= 70 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
                                            $review = $reviewBySubmission[(int)$r['submission_id']] ?? [];
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="test-name-cell">
                                                    <div class="test-icon"><?= icon('test', 16) ?></div>
                                                    <div>
                                                        <div class="test-name"><?= h($r['test_title']) ?></div>
                                                        <span class="badge <?= $pass ? 'badge-success' : 'badge-danger' ?>" style="font-size:var(--fs-10);"><?= $pass ? 'PASS' : 'FAIL' ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong style="font-size:1rem;"><?= number_format($obtained, 1) ?></strong>
                                                <span class="text-muted">/ <?= number_format($total, 1) ?></span>
                                                <div class="text-sm text-muted" style="margin-top:2px;font-size:0.75rem;">
                                                    Objective: <strong><?= number_format((float)$r['auto_score'], 1) ?></strong>/<?= number_format((float)$r['max_mcq'], 1) ?>
                                                    <?php if ((float)$r['max_subj'] > 0): ?>
                                                      &nbsp;·&nbsp; Subjective: <strong><?= number_format((float)$r['manual_score'], 1) ?></strong>/<?= number_format((float)$r['max_subj'], 1) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($review): ?>
                                                <details style="margin-top:6px;">
                                                    <summary style="cursor:pointer;font-size:0.75rem;color:var(--accent);user-select:none;">Question-by-question review</summary>
                                                    <div style="margin-top:6px;border-left:2px solid var(--gray-20);padding-left:10px;display:grid;gap:8px;">
                                                        <?php foreach ($review as $qIdx => $a): ?>
                                                            <?php
                                                                $ans = json_decode($a['answer_json'], true) ?: [];
                                                                $yourAns = $a['type'] === 'mcq' ? ($ans['selected'] ?? '—')
                                                                         : ($a['type'] === 'coding' ? mb_substr($ans['code'] ?? '', 0, 80) : mb_substr($ans['text'] ?? '', 0, 80));
                                                                $full = (float)$a['marks'] === (float)$a['marks_obtained'];
                                                            ?>
                                                            <div style="font-size:0.75rem;line-height:1.5;">
                                                                <strong>Q<?= $qIdx + 1 ?></strong> (<?= h($a['type']) ?>, <?= (float)$a['marks_obtained'] ?>/<?= (float)$a['marks'] ?>)
                                                                <?= $full ? '<span style="color:var(--green);">✓</span>' : '' ?>
                                                                <div class="text-muted">Your answer: <?= h($yourAns !== '' ? $yourAns : '—') ?></div>
                                                                <?php if (!empty($a['evaluation_remarks'])): ?>
                                                                    <div style="color:var(--accent);">Evaluator: <?= h($a['evaluation_remarks']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </details>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-sm text-muted">
                                                <?= $r['submitted_at'] ? date('M j, Y', strtotime($r['submitted_at'])) : '—' ?>
                                            </td>
                                            <td>
                                                <div class="result-performance">
                                                    <div class="progress-bar" style="width:100px;">
                                                        <div class="progress-fill <?= $barClass ?>" style="width:<?= $pct ?>%;"></div>
                                                    </div>
                                                    <span class="text-sm" style="font-weight:600;color:<?= $pct >= 70 ? 'var(--green)' : ($pct >= 40 ? 'var(--yellow)' : 'var(--red)') ?>;">
                                                        <?= $pct ?>%
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
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
