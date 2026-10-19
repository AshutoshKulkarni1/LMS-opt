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

    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css">

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
</head>

<body>

<?= iconSprite() ?>

<?php include __DIR__ . '/../../includes/student_header.php'; ?>

<main class="student-main">

    <!-- Page Header -->
    <div class="welcome-section">

        <div class="welcome-text">

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

</main>
<?php include __DIR__ . '/../../includes/student_footer.php'; ?>

<script>
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

    if (label) {
        label.textContent =
            theme === 'dark' ? 'Light Mode' : 'Dark Mode';
    }

    document.querySelectorAll('.theme-icon').forEach(el => {
        el.textContent =
            theme === 'dark' ? 'light_mode' : 'dark_mode';
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

// ============================================================
// ANALYSIS CHARTS
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
const scoreChartData = <?= json_encode($scoreChartData) ?>;
const questionChartData = <?= json_encode($questionChartData) ?>;
const pciChartData = <?= json_encode($pciChartData) ?>;


// ------------------------------------------------------------
// Score by Test
// ------------------------------------------------------------

const scoreCanvas = document.getElementById('scoreChart');

if (scoreCanvas && scoreChartData.length > 0) {

    new Chart(scoreCanvas, {

        type: 'bar',

        data: {

            labels: scoreChartData.map(item => item.test),

            datasets: [{
                label: 'Score (%)',

                data: scoreChartData.map(
                    item => item.percentage
                ),

                borderWidth: 0,

                borderRadius: 8
            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    max: 100,

                    ticks: {
                        callback: value => value + '%'
                    }

                }

            },

            plugins: {

                legend: {
                    display: false
                },

                tooltip: {

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

const questionCanvas =
    document.getElementById('questionChart');

if (questionCanvas) {

    new Chart(questionCanvas, {

        type: 'doughnut',

        data: {

            labels: questionChartData.map(
                item => item.label
            ),

            datasets: [{

                data: questionChartData.map(
                    item => item.value
                ),

                borderWidth: 0

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            cutout: '65%',

            plugins: {

                legend: {

                    position: 'bottom'

                }

            }

        }

    });

}


// ------------------------------------------------------------
// PCI by Test
// ------------------------------------------------------------

const pciCanvas =
    document.getElementById('pciChart');

if (pciCanvas && pciChartData.length > 0) {

    new Chart(pciCanvas, {

        type: 'line',

        data: {

            labels: pciChartData.map(
                item => item.test
            ),

            datasets: [{

                label: 'PCI',

                data: pciChartData.map(
                    item => item.pci
                ),

                borderWidth: 3,

                tension: 0.35,

                fill: false,

                pointRadius: 5,

                pointHoverRadius: 7

            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            scales: {

                y: {

                    beginAtZero: true,

                    max: 100

                }

            },

            plugins: {

                legend: {
                    display: false
                }

            }

        }

    });

}
});
</script>

</body>
</html>
