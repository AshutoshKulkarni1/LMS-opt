<?php
$pageTitle = 'Evaluated Tests';

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';

startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

/* ---------------------------------------------------------
   Student Information
--------------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT 
        s.*,
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


/* ---------------------------------------------------------
   Get Evaluated Tests
--------------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        t.id AS test_id,
        t.title AS test_title,
        t.duration_minutes,

        s.id AS submission_id,
        s.total_marks_obtained,
        s.total_marks,
        s.total_score,
        s.auto_score,
        s.manual_score,
        s.submitted_at,
        s.evaluation_status

    FROM submissions s

    JOIN tests t
        ON t.id = s.test_id

    WHERE s.student_id = ?
      AND s.status = 'evaluated'
      AND s.submitted_at IS NOT NULL

    ORDER BY s.submitted_at DESC
");

$stmt->execute([$studentId]);

$evaluatedTests = $stmt->fetchAll();


/* ---------------------------------------------------------
   Statistics
--------------------------------------------------------- */

$totalEvaluated = count($evaluatedTests);

$averageScore = 0;
$highestScore = 0;
$lowestScore = 100;

if ($totalEvaluated > 0) {

    $totalPercentage = 0;

    foreach ($evaluatedTests as $test) {

        $obtained = (float)($test['total_score'] ?? $test['total_marks_obtained']);
        $total = (float)$test['total_marks'];

        $percentage = $total > 0
            ? ($obtained / $total) * 100
            : 0;

        $totalPercentage += $percentage;

        if ($percentage > $highestScore) {
            $highestScore = $percentage;
        }

        if ($percentage < $lowestScore) {
            $lowestScore = $percentage;
        }
    }

    $averageScore = round(
        $totalPercentage / $totalEvaluated,
        1
    );

    $highestScore = round($highestScore);
    $lowestScore = round($lowestScore);

} else {

    $lowestScore = 0;
}


/* ---------------------------------------------------------
   Page Information
--------------------------------------------------------- */

$firstName = explode(' ', $student['name'])[0];

$today = new DateTime();

$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');

$currentPage = 'results';

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

    <link
        rel="stylesheet"
        href="<?= ASSETS_URL ?>/css/student.css"
    >

    <!-- Non-critical Chatbot Stylesheet (deferred to avoid render blocking) -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/chatbot.css') ? filemtime(__DIR__ . '/../../../../assets/css/chatbot.css') : time() ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css"></noscript>

    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
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


<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="welcome-section">

    <div class="welcome-text">

        <h1 class="welcome-heading">
            Evaluated Tests
        </h1>

        <p class="welcome-subtitle">
            <?= h($student['course_name']) ?>
        </p>

        <p class="welcome-batch">

            Batch <?= h($student['batch_name']) ?>

            <?php if (!empty($student['section'])): ?>

                — Section <?= h($student['section']) ?>

            <?php endif; ?>

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



<!-- =====================================================
     SUMMARY CARDS
===================================================== -->

<div class="stats-row">


    <!-- Total Evaluated -->

    <div class="stat-card-gradient stat-card-total">

        <div class="stat-card-icon">
            <?= icon('check-circle', 24) ?>
        </div>

        <div class="stat-card-value">
            <?= $totalEvaluated ?>
        </div>

        <div class="stat-card-label">
            Evaluated Tests
        </div>

        <div class="stat-card-desc">
            Completed and graded assessments
        </div>

    </div>



    <!-- Average -->

    <div class="stat-card-gradient stat-card-completed">

        <div class="stat-card-icon">
            <?= icon('chart', 24) ?>
        </div>

        <div class="stat-card-value">

            <?= $totalEvaluated > 0
                ? $averageScore . '%'
                : '—'
            ?>

        </div>

        <div class="stat-card-label">
            Average Score
        </div>

        <div class="stat-card-desc">
            Across evaluated tests
        </div>

    </div>



    <!-- Highest -->

    <div class="stat-card-gradient stat-card-pending">

        <div class="stat-card-icon">
            <?= icon('star', 24) ?>
        </div>

        <div class="stat-card-value">

            <?= $totalEvaluated > 0
                ? $highestScore . '%'
                : '—'
            ?>

        </div>

        <div class="stat-card-label">
            Highest Score
        </div>

        <div class="stat-card-desc">
            Best performance
        </div>

    </div>


</div>



<!-- =====================================================
     EVALUATED TESTS
===================================================== -->

<div class="tests-section">

    <div class="tests-section-header">

        <h2 class="tests-section-title">
            Evaluated Assessments
        </h2>

    </div>


    <?php if (empty($evaluatedTests)): ?>

        <!-- Empty State -->

        <div class="card-flat">

            <div class="card-body">

                <div class="empty-state">

                    <div class="empty-icon">

                        <?= icon('check-circle', 56) ?>

                    </div>

                    <h3>
                        No Evaluated Tests Yet
                    </h3>

                    <p>
                        Your evaluated assessments will appear here once they have been graded.
                    </p>

                </div>

            </div>

        </div>


    <?php else: ?>


        <!-- Results Table -->

        <div class="tests-table-view">

            <div class="table-wrapper">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>

                                <div class="th-icon">
                                    <?= icon('test', 14) ?>
                                </div>

                                Assessment

                            </th>


                            <th>

                                <div class="th-icon">
                                    <?= icon('star', 14) ?>
                                </div>

                                Score

                            </th>


                            <th>

                                <div class="th-icon">
                                    <?= icon('chart', 14) ?>
                                </div>

                                Percentage

                            </th>


                            <th>

                                <div class="th-icon">
                                    <?= icon('calendar', 14) ?>
                                </div>

                                Completed On

                            </th>


                            <th class="actions">

                                <div class="th-icon">
                                    <?= icon('arrow-right-circle-fill', 14) ?>
                                </div>

                                Action

                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach ($evaluatedTests as $test): ?>


                            <?php

                            $obtained = (float)(
                                $test['total_score']
                                ?? $test['total_marks_obtained']
                            );

                            $total = (float)$test['total_marks'];

                            $percentage = $total > 0
                                ? round(($obtained / $total) * 100)
                                : 0;


                            $performanceClass =
                                $percentage >= 70
                                    ? 'success'
                                    : (
                                        $percentage >= 40
                                            ? 'warning'
                                            : 'danger'
                                    );


                            $performanceColor =
                                $percentage >= 70
                                    ? 'var(--green)'
                                    : (
                                        $percentage >= 40
                                            ? 'var(--yellow)'
                                            : 'var(--red)'
                                    );

                            ?>


                            <tr>


                                <!-- Assessment -->

                                <td>

                                    <div class="test-name-cell">

                                        <div class="test-icon">

                                            <?= icon('test', 18) ?>

                                        </div>


                                        <div>

                                            <div class="test-name">

                                                <?= h($test['test_title']) ?>

                                            </div>


                                            <span
                                                class="badge badge-success"
                                                style="font-size:var(--fs-10);"
                                            >
                                                Evaluated
                                            </span>

                                        </div>

                                    </div>

                                </td>



                                <!-- Score -->

                                <td>

                                    <strong style="font-size:1rem;">

                                        <?= number_format($obtained, 1) ?>

                                    </strong>

                                    <span class="text-muted">

                                        /
                                        <?= number_format($total, 1) ?>

                                    </span>

                                </td>



                                <!-- Percentage -->

                                <td>

                                    <div class="result-performance">

                                        <div
                                            class="progress-bar"
                                            style="width:100px;"
                                        >

                                            <div
                                                class="progress-fill <?= $performanceClass ?>"
                                                style="width:<?= $percentage ?>%;"
                                            ></div>

                                        </div>


                                        <span
                                            class="text-sm"
                                            style="
                                                font-weight:600;
                                                color:<?= $performanceColor ?>;
                                            "
                                        >

                                            <?= $percentage ?>%

                                        </span>

                                    </div>

                                </td>



                                <!-- Completed Date -->

                                <td class="text-sm text-muted">

                                    <?= !empty($test['submitted_at'])
                                        ? date(
                                            'M j, Y',
                                            strtotime($test['submitted_at'])
                                        )
                                        : '—'
                                    ?>

                                </td>



                                <!-- Action -->

                                <td class="actions">

                                    <a
                                        href="test-analysis.php"
                                        class="btn btn-sm btn-ghost"
                                    >

                                        <?= icon('chart', 14) ?>

                                        View Analysis

                                    </a>

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


document.addEventListener(
    'keydown',
    function(e) {

        if (e.key === 'Escape') {

            closeSidebar();

        }


        if (e.key === 'Tab') {

            const sidebar =
                document.getElementById('sidebar');


            if (
                !sidebar ||
                !sidebar.classList.contains('open')
            ) {
                return;
            }


            const focusable =
                sidebar.querySelectorAll(
                    'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'
                );


            if (!focusable.length) {
                return;
            }


            if (
                e.shiftKey &&
                document.activeElement === focusable[0]
            ) {

                e.preventDefault();

                focusable[
                    focusable.length - 1
                ].focus();

            }


            else if (
                !e.shiftKey &&
                document.activeElement === focusable[focusable.length - 1]
            ) {

                e.preventDefault();

                focusable[0].focus();

            }

        }

    }
);


function toggleTheme() {

    const html =
        document.documentElement;

    const isDark =
        html.getAttribute('data-theme') === 'dark';

    const newTheme =
        isDark ? 'light' : 'dark';


    html.setAttribute(
        'data-theme',
        newTheme
    );

    localStorage.setItem(
        'theme',
        newTheme
    );

    updateThemeUI(newTheme);

}


function updateThemeUI(theme) {

    const label =
        document.getElementById('themeLabel');

    if (label) {

        label.textContent =
            theme === 'dark'
                ? 'Light Mode'
                : 'Dark Mode';

    }


    document
        .querySelectorAll('.theme-icon')
        .forEach(el => {

            el.textContent =
                theme === 'dark'
                    ? 'light_mode'
                    : 'dark_mode';

        });

}


(function() {

    const saved =
        localStorage.getItem('theme');

    if (saved) {

        document.documentElement
            .setAttribute(
                'data-theme',
                saved
            );

        updateThemeUI(saved);

    }

})();
</script>

</body>

</html>