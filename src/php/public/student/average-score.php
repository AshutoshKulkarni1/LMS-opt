<?php
$pageTitle = 'Average Score';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';

startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

/* =========================
   Student Information
========================= */
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


/* =========================
   Get Student Test Data
========================= */
$stmt = $pdo->prepare("
    SELECT 
        t.id AS test_id,
        t.title,
        t.duration_minutes,
        t.start_time,

        s.id AS submission_id,
        s.status AS submission_status,
        s.total_marks_obtained,
        s.total_marks,
        s.submitted_at,
        s.started_at

    FROM tests t

    LEFT JOIN submissions s
        ON s.test_id = t.id
        AND s.student_id = ?

    JOIN batches b
        ON b.id = t.batch_id

    JOIN students st
        ON st.batch_id = b.id

    WHERE st.id = ?

    ORDER BY t.start_time DESC
");

$stmt->execute([$studentId, $studentId]);

$allData = $stmt->fetchAll();


/* =========================
   Only Evaluated Tests
   Same logic as Analytics
========================= */
$evaluated = array_values(
    array_filter(
        $allData,
        fn($r) => $r['submission_status'] === 'evaluated'
    )
);


/* =========================
   Calculate Statistics
========================= */

$evaluatedCount = count($evaluated);

$averageScore = 0;
$highestScore = 0;
$lowestScore = 0;

$totalObtained = 0;
$totalPossible = 0;

$scoreHistory = [];


if ($evaluatedCount > 0) {

    $percentages = [];

    foreach ($evaluated as $result) {

        $obtained = (float) $result['total_marks_obtained'];
        $possible = (float) $result['total_marks'];

        $percentage = $possible > 0
            ? ($obtained / $possible) * 100
            : 0;

        $percentages[] = $percentage;

        $totalObtained += $obtained;
        $totalPossible += $possible;

        $scoreHistory[] = [
            'title' => $result['title'],
            'percentage' => round($percentage, 1),
            'obtained' => $obtained,
            'possible' => $possible,
            'date' => $result['submitted_at']
        ];
    }

    $averageScore = round(
        array_sum($percentages) / count($percentages),
        1
    );

    $highestScore = round(max($percentages), 1);
    $lowestScore = round(min($percentages), 1);
}


/* =========================
   Performance Level
========================= */

if ($averageScore >= 80) {
    $performanceLevel = 'Excellent';
} elseif ($averageScore >= 60) {
    $performanceLevel = 'Good';
} elseif ($averageScore >= 40) {
    $performanceLevel = 'Average';
} elseif ($evaluatedCount > 0) {
    $performanceLevel = 'Needs Improvement';
} else {
    $performanceLevel = 'No Data';
}


/* =========================
   Date Information
========================= */

$firstName = explode(' ', $student['name'])[0];

$today = new DateTime();
$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');

$currentPage = 'analytics';
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= h($pageTitle) ?> | Test Platform
    </title>
    <meta name="description" content="Detailed score breakdown and statistical analysis comparing your average assessment marks against course benchmarks.">

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


<!-- =========================
     Page Header
========================= -->

<div class="welcome-section">

    <div class="welcome-text">

        <h1 class="welcome-heading">
            Average Score
        </h1>

        <p class="welcome-subtitle">
            <?= h($student['course_name']) ?>
        </p>

        <p class="welcome-batch">

            Batch <?= h($student['batch_name']) ?>

            <?= !empty($student['section'])
                ? ' — Section ' . h($student['section'])
                : ''
            ?>

        </p>

    </div>


    <div class="date-card">

        <div class="date-card-icon">

            <?= icon('calendar', 24, 'var(--accent)') ?>

        </div>

        <div class="date-card-info">

            <div class="date-card-date">
                <?= h($formattedDate) ?>
            </div>

            <div class="date-card-day">
                <?= h($dayName) ?>
            </div>

        </div>

    </div>

</div>


<!-- =========================
     Main Score Cards
========================= -->

<div class="stats-row">

    <!-- Average -->

    <div class="stat-card-gradient stat-card-pending">

        <div class="stat-card-icon">
            <?= icon('graph', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $averageScore ?>%
        </div>

        <div class="stat-card-label">
            Average Score
        </div>

        <div class="stat-card-desc">
            Across evaluated tests
        </div>

    </div>


    <!-- Highest -->

    <div class="stat-card-gradient stat-card-completed">

        <div class="stat-card-icon">
            <?= icon('arrow-up', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $highestScore ?>%
        </div>

        <div class="stat-card-label">
            Highest Score
        </div>

        <div class="stat-card-desc">
            Best test performance
        </div>

    </div>


    <!-- Lowest -->

    <div class="stat-card-gradient stat-card-total">

        <div class="stat-card-icon">
            <?= icon('arrow-down', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $lowestScore ?>%
        </div>

        <div class="stat-card-label">
            Lowest Score
        </div>

        <div class="stat-card-desc">
            Lowest test performance
        </div>

    </div>

</div>


<!-- =========================
     Performance Overview
========================= -->

<div class="analytics-grid">


    <!-- Performance Level -->

    <div class="card-flat">

        <div class="card-header">

            <h3>
                <?= icon('graph', 16) ?>
                Performance Overview
            </h3>

        </div>


        <div class="card-body">

            <div class="analytics-summary-grid">

                <div class="analytics-summary-item">

                    <span class="analytics-summary-label">
                        Average Score
                    </span>

                    <span
                        class="analytics-summary-value"
                        style="color:var(--accent);"
                    >
                        <?= $averageScore ?>%
                    </span>

                </div>


                <div class="analytics-summary-item">

                    <span class="analytics-summary-label">
                        Performance Level
                    </span>

                    <span class="analytics-summary-value">
                        <?= h($performanceLevel) ?>
                    </span>

                </div>


                <div class="analytics-summary-item">

                    <span class="analytics-summary-label">
                        Evaluated Tests
                    </span>

                    <span class="analytics-summary-value">
                        <?= $evaluatedCount ?>
                    </span>

                </div>


                <div class="analytics-summary-item">

                    <span class="analytics-summary-label">
                        Total Marks
                    </span>

                    <span class="analytics-summary-value">
                        <?= h($totalObtained) ?>
                        /
                        <?= h($totalPossible) ?>
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- =========================
         Score History
    ========================= -->

    <div
        class="card-flat"
        style="grid-column:1/-1;"
    >

        <div class="card-header">

            <h3>
                <?= icon('chart', 16) ?>
                Score History
            </h3>

        </div>


        <div class="card-body">

            <?php if ($evaluatedCount > 0): ?>

                <div class="analytics-bar-list">

                    <?php foreach ($scoreHistory as $score): ?>

                        <div class="analytics-bar-item">

                            <span class="analytics-bar-label">

                                <?= h($score['title']) ?>

                            </span>


                            <span class="analytics-bar-value">

                                <?= $score['percentage'] ?>%

                            </span>


                            <div class="analytics-bar-track">

                                <div
                                    class="analytics-bar-fill
                                    <?= $score['percentage'] >= 60
                                        ? 'success'
                                        : 'danger'
                                    ?>"
                                    style="
                                        width:<?= min(
                                            $score['percentage'],
                                            100
                                        ) ?>%;
                                    "
                                ></div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div
                    class="empty-state"
                    style="padding:var(--space-8) var(--space-4);"
                >

                    <div class="empty-icon">

                        <?= icon('graph', 48) ?>

                    </div>

                    <p>
                        No evaluated tests yet to calculate your average score.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>


    <!-- =========================
         Detailed Test Scores
    ========================= -->

    <div
        class="card-flat"
        style="grid-column:1/-1;"
    >

        <div class="card-header">

            <h3>
                <?= icon('test', 16) ?>
                Test-wise Scores
            </h3>

        </div>


        <div class="card-body">

            <?php if ($evaluatedCount > 0): ?>

                <div style="overflow-x:auto;">

                    <table style="width:100%; border-collapse:collapse;">

                        <thead>

                            <tr>

                                <th
                                    style="
                                        text-align:left;
                                        padding:12px;
                                    "
                                >
                                    Test
                                </th>

                                <th
                                    style="
                                        text-align:center;
                                        padding:12px;
                                    "
                                >
                                    Score
                                </th>

                                <th
                                    style="
                                        text-align:center;
                                        padding:12px;
                                    "
                                >
                                    Percentage
                                </th>

                                <th
                                    style="
                                        text-align:right;
                                        padding:12px;
                                    "
                                >
                                    Completed On
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($scoreHistory as $score): ?>

                            <tr>

                                <td style="padding:12px;">

                                    <?= h($score['title']) ?>

                                </td>


                                <td
                                    style="
                                        text-align:center;
                                        padding:12px;
                                    "
                                >

                                    <?= h($score['obtained']) ?>
                                    /
                                    <?= h($score['possible']) ?>

                                </td>


                                <td
                                    style="
                                        text-align:center;
                                        padding:12px;
                                        font-weight:600;
                                    "
                                >

                                    <?= $score['percentage'] ?>%

                                </td>


                                <td
                                    style="
                                        text-align:right;
                                        padding:12px;
                                    "
                                >

                                    <?= !empty($score['date'])
                                        ? date(
                                            'M d, Y',
                                            strtotime($score['date'])
                                        )
                                        : '-'
                                    ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

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

/* =========================
   Sidebar
========================= */

function toggleSidebar(forceState) {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    const isOpen =
        forceState !== undefined
            ? forceState
            : !sidebar.classList.contains('open');

    sidebar.classList.toggle(
        'open',
        isOpen
    );

    overlay.classList.toggle(
        'show',
        isOpen
    );

    document.body.classList.toggle(
        'sidebar-open',
        isOpen
    );

    sidebar.setAttribute(
        'aria-hidden',
        !isOpen
    );
}


function closeSidebar() {

    toggleSidebar(false);

}


/* =========================
   Keyboard
========================= */

document.addEventListener(
    'keydown',
    function(e) {

        if (e.key === 'Escape') {

            closeSidebar();

        }

    }
);


</script>

</body>
</html>