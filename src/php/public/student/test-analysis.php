<?php

$pageTitle = 'Test Analysis';

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';

startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

// Get student info
$stmt = $pdo->prepare("
    SELECT s.*,
           b.name AS batch_name,
           c.name AS course_name,
           cl.name AS college_name,
           cl.logo AS college_logo
    FROM students s
    JOIN batches b ON b.id = s.batch_id
    JOIN courses c ON c.id = b.course_id
    JOIN colleges cl ON cl.id = c.college_id
    WHERE s.id = ?
");

$stmt->execute([$studentId]);
$student = $stmt->fetch();

// Get tests
$tests = getStudentTests($studentId);

// ============================================================
// TEST ANALYSIS DATA
// ============================================================

// Test-wise performance
$performanceData = [];

foreach ($tests as $test) {

    if (empty($test['submission_id'])) {
        continue;
    }

    $marksObtained = (float)($test['total_marks_obtained'] ?? 0);
    $totalMarks = (float)($test['total_marks'] ?? 0);

    $percentage = $totalMarks > 0
        ? round(($marksObtained / $totalMarks) * 100, 1)
        : 0;

    // Calculate time taken
    $timeTaken = null;

    if (!empty($test['started_at']) && !empty($test['submitted_at'])) {
        $start = new DateTime($test['started_at']);
        $end = new DateTime($test['submitted_at']);

        $seconds = max(0, $end->getTimestamp() - $start->getTimestamp());

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        $timeTaken = $minutes . 'm';

        if ($remainingSeconds > 0) {
            $timeTaken .= ' ' . $remainingSeconds . 's';
        }
    }

    // --------------------------------------------------------
    // Question performance
    // --------------------------------------------------------

    $stmtAnswers = $pdo->prepare("
        SELECT
            sa.marks_obtained,
            q.marks,
            q.type
        FROM student_answers sa
        JOIN questions q ON q.id = sa.question_id
        WHERE sa.submission_id = ?
    ");

    $stmtAnswers->execute([
        $test['submission_id']
    ]);

    $answers = $stmtAnswers->fetchAll();

    $correct = 0;
    $wrong = 0;
    $unanswered = 0;

    foreach ($answers as $answer) {

        $marksObtained = $answer['marks_obtained'];
        $maxMarks = (float)$answer['marks'];

        if ($marksObtained === null) {
            $unanswered++;
        } elseif ((float)$marksObtained >= $maxMarks) {
            $correct++;
        } else {
            $wrong++;
        }
    }

    // --------------------------------------------------------
    // PCI
    // --------------------------------------------------------

    $stmtPCI = $pdo->prepare("
        SELECT
            pci_score,
            mcq_score,
            coding_score,
            explanation_score,
            mcq_weight,
            coding_weight,
            explanation_weight
        FROM pci_records
        WHERE student_id = ?
          AND test_id = ?
        LIMIT 1
    ");

    $stmtPCI->execute([
        $studentId,
        $test['id']
    ]);

    $pci = $stmtPCI->fetch();

    $performanceData[] = [
        'test_id' => (int)$test['id'],
        'title' => $test['title'],

        'score' => $marksObtained,
        'total' => $totalMarks,
        'percentage' => $percentage,

        'correct' => $correct,
        'wrong' => $wrong,
        'unanswered' => $unanswered,

        'time_taken' => $timeTaken ?? '—',

        'pci_score' => $pci ? (float)$pci['pci_score'] : 0,
        'mcq_score' => $pci ? (float)$pci['mcq_score'] : 0,
        'coding_score' => $pci ? (float)$pci['coding_score'] : 0,
        'explanation_score' => $pci ? (float)$pci['explanation_score'] : 0,
    ];
}


// ============================================================
// OVERALL ANALYSIS
// ============================================================

$evaluatedPerformance = array_filter(
    $performanceData,
    fn($test) => $test['total'] > 0
);

$averageScore = count($evaluatedPerformance) > 0
    ? round(
        array_sum(
            array_column($evaluatedPerformance, 'percentage')
        ) / count($evaluatedPerformance),
        1
    )
    : 0;

$totalCorrect = array_sum(
    array_column($performanceData, 'correct')
);

$totalWrong = array_sum(
    array_column($performanceData, 'wrong')
);

$totalUnanswered = array_sum(
    array_column($performanceData, 'unanswered')
);

$averagePCI = count($performanceData) > 0
    ? round(
        array_sum(
            array_column($performanceData, 'pci_score')
        ) / count($performanceData),
        1
    )
    : 0;


// ============================================================
// DATA FOR JAVASCRIPT CHARTS
// ============================================================

$scoreChartData = [];

$questionChartData = [
    [
        'label' => 'Correct',
        'value' => $totalCorrect
    ],
    [
        'label' => 'Wrong',
        'value' => $totalWrong
    ],
    [
        'label' => 'Unanswered',
        'value' => $totalUnanswered
    ]
];

$pciChartData = [];

foreach ($performanceData as $test) {

    $pciChartData[] = [
        'test' => $test['title'],
        'pci' => $test['pci_score']
    ];

    $scoreChartData[] = [
        'test' => $test['title'],
        'percentage' => $test['percentage']
    ];
}


// Current page
$currentPage = 'test-analysis';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= h($pageTitle) ?> | Test Platform</title>
    <meta name="description" content="Comprehensive assessment analysis covering accuracy rates, topic strengths, improvement areas, and question difficulty breakdown.">

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

        /* ─── Test Analysis Page Theme Guarantees ─────────── */
        section.analysis-section {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            box-shadow: none !important;
            margin-top: 32px;
            margin-bottom: 32px;
        }

        .analysis-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 20px;
        }

        .analysis-section-header h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .analysis-section-header p {
            margin: 6px 0 0;
            color: var(--text-secondary);
            font-size: 14px;
        }

        .analysis-average {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .analysis-average-value {
            font-size: 28px;
            font-weight: 800;
            line-height: 1;
            color: var(--accent);
        }

        .analysis-average-label {
            margin-top: 5px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .analysis-card {
            background: var(--surface-card, #151B26) !important;
            border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
            border-radius: 18px !important;
            padding: 24px !important;
            box-shadow: var(--shadow-sm);
        }

        [data-theme="light"] .analysis-card,
        html:not([data-theme="dark"]) .analysis-card {
            background: #FFFFFF !important;
            border-color: #DAE0E8 !important;
        }

        [data-theme="dark"] .analysis-card {
            background: #151B26 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
            box-shadow: none !important;
        }

        .analysis-card-header h3 {
            margin: 0;
            font-size: 17px;
            font-weight: 700;
            color: var(--text-primary) !important;
        }

        .analysis-card-header p {
            margin: 5px 0 0;
            color: var(--text-secondary) !important;
            font-size: 13px;
        }

        .analysis-table-card {
            background: var(--surface-card, #151B26) !important;
            border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
            border-radius: 18px !important;
            box-shadow: var(--shadow-sm);
            overflow: hidden !important;
        }

        [data-theme="light"] .analysis-table-card,
        html:not([data-theme="dark"]) .analysis-table-card {
            background: #FFFFFF !important;
            border-color: #DAE0E8 !important;
        }

        [data-theme="dark"] .analysis-table-card {
            background: #151B26 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
            box-shadow: none !important;
        }

        .analysis-table th {
            padding: 16px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary) !important;
            background: var(--bg-secondary, #0E1117) !important;
            border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
            white-space: nowrap;
        }

        [data-theme="light"] .analysis-table th,
        html:not([data-theme="dark"]) .analysis-table th {
            background: #F0F2F5 !important;
            color: #667085 !important;
            border-bottom-color: #DAE0E8 !important;
        }

        .analysis-table td {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.08)) !important;
            font-size: 14px;
            white-space: nowrap;
            color: var(--text-primary) !important;
        }

        [data-theme="light"] .analysis-table td,
        html:not([data-theme="dark"]) .analysis-table td {
            border-bottom-color: #DAE0E8 !important;
            color: #101828 !important;
        }

        .analysis-table tbody tr:hover {
            background: var(--surface-hover, #1C2433) !important;
        }

        [data-theme="light"] .analysis-table tbody tr:hover,
        html:not([data-theme="dark"]) .analysis-table tbody tr:hover {
            background: #F8FAFD !important;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 320px;
            background: transparent !important;
        }

        .chart-container canvas {
            background: transparent !important;
        }

        .chart-container-small {
            height: 320px;
        }

        /* ─── Mobile Viewport Optimizations (<= 768px) ─── */
        @media (max-width: 768px) {
            .student-main {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            section.analysis-section {
                margin-top: 20px !important;
                margin-bottom: 20px !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .analysis-section-header {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 12px !important;
                margin-bottom: 16px !important;
                width: 100% !important;
            }

            .analysis-section-header h2 {
                font-size: 19px !important;
            }

            .analysis-section-header p {
                font-size: 13px !important;
            }

            .analysis-average {
                flex-direction: row !important;
                align-items: baseline !important;
                gap: 8px !important;
                align-self: flex-start !important;
                background: var(--surface-hover, rgba(255, 255, 255, 0.04));
                padding: 6px 14px;
                border-radius: 10px;
                border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
            }

            .analysis-average-value {
                font-size: 22px !important;
            }

            .analysis-average-label {
                margin-top: 0 !important;
                font-size: 12px !important;
            }

            .analysis-chart-grid {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .analysis-card {
                padding: 16px !important;
                border-radius: 14px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .analysis-card-header h3 {
                font-size: 16px !important;
            }

            .analysis-card-header p {
                font-size: 12px !important;
            }

            .chart-container {
                height: 250px !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .chart-container-small {
                height: 250px !important;
            }

            .analysis-table-card {
                border-radius: 14px !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .analysis-table-wrapper {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: thin;
            }

            .analysis-table {
                min-width: 520px !important;
                width: 100% !important;
            }

            .analysis-table th,
            .analysis-table td {
                padding: 12px 14px !important;
                font-size: 13px !important;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
</head>

<body>

<?= iconSprite() ?>

<?php include __DIR__ . '/../../includes/student_header.php'; ?>

<div class="student-main">

    <!-- Page Header with Back Navigation -->
    <div class="welcome-section" style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; gap: 16px;">

        <div class="welcome-text">

            <div style="margin-bottom: 10px;">
                <a href="<?= BASE_URL ?>/student/results.php" class="back-link" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--accent); text-decoration: none; padding: 6px 12px; border-radius: 8px; background: var(--accent-bg); border: 1px solid var(--border-color); transition: background 0.15s ease;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    <span>Back to Results</span>
                </a>
            </div>

            <h1 class="welcome-heading">
                Test Analysis
            </h1>

            <p class="welcome-subtitle">
                Understand your performance across assessments
            </p>

        </div>

    </div>


    <!-- =====================================================
         PERFORMANCE OVERVIEW
         ===================================================== -->

    <section class="analysis-section">

        <div class="analysis-section-header">

            <div>
                <h2>Performance Overview</h2>

                <p>
                    Your average performance across evaluated tests.
                </p>
            </div>

            <div class="analysis-average">

                <span class="analysis-average-value">
                    <?= $averageScore ?>%
                </span>

                <span class="analysis-average-label">
                    Average Score
                </span>

            </div>

        </div>


        <div class="analysis-chart-grid">

            <!-- Score Chart -->
            <div class="analysis-card">

                <div class="analysis-card-header">

                    <div>
                        <h3>Score by Test</h3>

                        <p>
                            Percentage achieved in each assessment.
                        </p>
                    </div>

                </div>

                <div class="chart-container">

                    <canvas id="scoreChart"></canvas>

                </div>

            </div>


            <!-- Question Performance -->
            <div class="analysis-card">

                <div class="analysis-card-header">

                    <div>
                        <h3>Question Performance</h3>

                        <p>
                            Correct, wrong and unanswered questions.
                        </p>
                    </div>

                </div>

                <div class="chart-container chart-container-small">

                    <canvas id="questionChart"></canvas>

                </div>

            </div>

        </div>

    </section>


    <!-- =====================================================
         PCI ANALYSIS
         ===================================================== -->

    <section class="analysis-section">

        <div class="analysis-section-header">

            <div>

                <h2>Performance Consistency Index</h2>

                <p>
                    View your consistency across evaluated assessments.
                </p>

            </div>

            <div class="analysis-average">

                <span class="analysis-average-value">
                    <?= $averagePCI ?>
                </span>

                <span class="analysis-average-label">
                    Average PCI
                </span>

            </div>

        </div>


        <div class="analysis-card">

            <div class="analysis-card-header">

                <div>

                    <h3>PCI by Test</h3>

                    <p>
                        Performance consistency for each assessment.
                    </p>

                </div>

            </div>

            <div class="chart-container">

                <canvas id="pciChart"></canvas>

            </div>

        </div>

    </section>


    <!-- =====================================================
         TEST-WISE PERFORMANCE
         ===================================================== -->

    <section class="analysis-section">

        <div class="analysis-section-header">

            <div>

                <h2>Test-wise Performance</h2>

                <p>
                    Detailed breakdown of your completed assessments.
                </p>

            </div>

        </div>


        <div class="analysis-table-card">

            <?php if (empty($performanceData)): ?>

                <div class="analysis-empty">

                    <h3>No completed tests yet</h3>

                    <p>
                        Complete an assessment to see your detailed
                        performance here.
                    </p>

                </div>

            <?php else: ?>

                <div class="analysis-table-wrapper">

                    <table class="analysis-table">

                        <thead>

                            <tr>

                                <th>Test</th>

                                <th>Score</th>

                                <th>Accuracy</th>

                                <th>Correct</th>

                                <th>Wrong</th>

                                <th>Unanswered</th>

                                <th>Time</th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($performanceData as $test): ?>

                                <tr>

                                    <td>

                                        <div class="test-name">
                                            <?= h($test['title']) ?>
                                        </div>

                                    </td>

                                    <td>

                                        <strong>
                                            <?= h($test['score']) ?>
                                            /
                                            <?= h($test['total']) ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <span class="accuracy-badge">
                                            <?= h($test['percentage']) ?>%
                                        </span>

                                    </td>

                                    <td>
                                        <?= h($test['correct']) ?>
                                    </td>

                                    <td>
                                        <?= h($test['wrong']) ?>
                                    </td>

                                    <td>
                                        <?= h($test['unanswered']) ?>
                                    </td>

                                    <td>
                                        <?= h($test['time_taken']) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </section>

</div>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
let scoreChartInstance = null;
let questionChartInstance = null;
let pciChartInstance = null;

function getChartThemeColors(isDark) {
    return {
        text: isDark ? '#99A2AD' : '#667085',
        grid: isDark ? 'rgba(255, 255, 255, 0.06)' : 'rgba(0, 0, 0, 0.06)',
        tooltipBg: isDark ? 'rgba(21, 27, 38, 0.95)' : 'rgba(16, 24, 40, 0.95)',
        tooltipTitle: isDark ? '#F4F6F8' : '#FFFFFF',
        tooltipBody: isDark ? '#D6DCE4' : '#F4F6F8',
        barBg: isDark ? 'rgba(108, 160, 255, 0.85)' : 'rgba(79, 140, 255, 0.85)',
        barHover: isDark ? '#4F8CFF' : '#3A78F0',
        unanswered: isDark ? '#3D4B64' : '#CBD2DC',
        pciLine: isDark ? '#6CA0FF' : '#4F8CFF',
        pciPointBg: isDark ? '#6CA0FF' : '#4F8CFF',
        pciPointBorder: isDark ? '#151B26' : '#FFFFFF'
    };
}

function applyChartTheme(isDark) {
    const c = getChartThemeColors(isDark);
    if (scoreChartInstance) {
        if (scoreChartInstance.options.scales && scoreChartInstance.options.scales.x) {
            scoreChartInstance.options.scales.x.ticks.color = c.text;
            scoreChartInstance.options.scales.x.grid.color = c.grid;
        }
        if (scoreChartInstance.options.scales && scoreChartInstance.options.scales.y) {
            scoreChartInstance.options.scales.y.ticks.color = c.text;
            scoreChartInstance.options.scales.y.grid.color = c.grid;
        }
        if (scoreChartInstance.data.datasets && scoreChartInstance.data.datasets[0]) {
            scoreChartInstance.data.datasets[0].backgroundColor = c.barBg;
            scoreChartInstance.data.datasets[0].hoverBackgroundColor = c.barHover;
        }
        scoreChartInstance.update();
    }

    if (questionChartInstance) {
        if (questionChartInstance.options.plugins && questionChartInstance.options.plugins.legend) {
            questionChartInstance.options.plugins.legend.labels.color = c.text;
        }
        if (questionChartInstance.data.datasets && questionChartInstance.data.datasets[0]) {
            questionChartInstance.data.datasets[0].backgroundColor = [
                '#22C55E',
                '#EF4444',
                c.unanswered
            ];
        }
        questionChartInstance.update();
    }

    if (pciChartInstance) {
        if (pciChartInstance.options.scales && pciChartInstance.options.scales.x) {
            pciChartInstance.options.scales.x.ticks.color = c.text;
            pciChartInstance.options.scales.x.grid.color = c.grid;
        }
        if (pciChartInstance.options.scales && pciChartInstance.options.scales.y) {
            pciChartInstance.options.scales.y.ticks.color = c.text;
            pciChartInstance.options.scales.y.grid.color = c.grid;
        }
        if (pciChartInstance.data.datasets && pciChartInstance.data.datasets[0]) {
            pciChartInstance.data.datasets[0].borderColor = c.pciLine;
            pciChartInstance.data.datasets[0].pointBackgroundColor = c.pciPointBg;
            pciChartInstance.data.datasets[0].pointBorderColor = c.pciPointBorder;
        }
        pciChartInstance.update();
    }
}

// ============================================================
// ANALYSIS CHARTS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const c = getChartThemeColors(isDark);

    const scoreChartData = <?= json_encode($scoreChartData) ?>;
    const questionChartData = <?= json_encode($questionChartData) ?>;
    const pciChartData = <?= json_encode($pciChartData) ?>;

    // ------------------------------------------------------------
    // Score by Test
    // ------------------------------------------------------------
    const scoreCanvas = document.getElementById('scoreChart');
    if (scoreCanvas && scoreChartData.length > 0) {
        scoreChartInstance = new Chart(scoreCanvas, {
            type: 'bar',
            data: {
                labels: scoreChartData.map(item => item.test),
                datasets: [{
                    label: 'Score (%)',
                    data: scoreChartData.map(item => item.percentage),
                    backgroundColor: c.barBg,
                    hoverBackgroundColor: c.barHover,
                    borderWidth: 0,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: c.grid },
                        ticks: {
                            color: c.text,
                            font: { family: 'Inter', size: 12 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: c.grid },
                        ticks: {
                            color: c.text,
                            font: { family: 'Inter', size: 12 },
                            callback: value => value + '%'
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: c.tooltipBg,
                        titleColor: c.tooltipTitle,
                        bodyColor: c.tooltipBody,
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });
    }

    // ------------------------------------------------------------
    // Question Performance
    // ------------------------------------------------------------
    const questionCanvas = document.getElementById('questionChart');
    if (questionCanvas) {
        questionChartInstance = new Chart(questionCanvas, {
            type: 'doughnut',
            data: {
                labels: questionChartData.map(item => item.label),
                datasets: [{
                    data: questionChartData.map(item => item.value),
                    backgroundColor: ['#22C55E', '#EF4444', c.unanswered],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: c.text,
                            font: { family: 'Inter', size: 12 },
                            padding: 16
                        }
                    },
                    tooltip: {
                        backgroundColor: c.tooltipBg,
                        titleColor: c.tooltipTitle,
                        bodyColor: c.tooltipBody,
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });
    }

    // ------------------------------------------------------------
    // PCI by Test
    // ------------------------------------------------------------
    const pciCanvas = document.getElementById('pciChart');
    if (pciCanvas && pciChartData.length > 0) {
        pciChartInstance = new Chart(pciCanvas, {
            type: 'line',
            data: {
                labels: pciChartData.map(item => item.test),
                datasets: [{
                    label: 'PCI',
                    data: pciChartData.map(item => item.pci),
                    borderColor: c.pciLine,
                    backgroundColor: 'rgba(79, 140, 255, 0.08)',
                    borderWidth: 3,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: c.pciPointBg,
                    pointBorderColor: c.pciPointBorder,
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { color: c.grid },
                        ticks: {
                            color: c.text,
                            font: { family: 'Inter', size: 12 }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: { color: c.grid },
                        ticks: {
                            color: c.text,
                            font: { family: 'Inter', size: 12 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: c.tooltipBg,
                        titleColor: c.tooltipTitle,
                        bodyColor: c.tooltipBody,
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });
    }
});
</script>

</body>
</html>
