<?php
$pageTitle = 'Result Details';

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';

startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

/* ---------------------------------------------------------
   STUDENT INFORMATION
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

if (!$student) {
    exit('Student not found.');
}


/* ---------------------------------------------------------
   WHICH DETAIL PAGE ARE WE SHOWING?
--------------------------------------------------------- */

$type = $_GET['type'] ?? 'evaluated';

$allowedTypes = [
    'evaluated',
    'average',
    'highest',
    'pending'
];

if (!in_array($type, $allowedTypes, true)) {
    $type = 'evaluated';
}


/* ---------------------------------------------------------
   GET ALL SUBMISSIONS
--------------------------------------------------------- */

$stmt = $pdo->prepare("
    SELECT
        t.id AS test_id,
        t.title AS test_title,
        t.duration_minutes,

        s.id AS submission_id,
        s.total_marks_obtained,
        s.total_marks,
        s.submitted_at,

        s.status AS submission_status,
        s.evaluation_status,

        s.auto_score,
        s.manual_score,
        s.total_score,

        COALESCE(qm.max_mcq, 0) AS max_mcq,
        COALESCE(qs.max_subj, 0) AS max_subj

    FROM submissions s

    JOIN tests t
        ON t.id = s.test_id

    LEFT JOIN (
        SELECT
            test_id,
            SUM(marks) AS max_mcq
        FROM questions
        WHERE type = 'mcq'
        GROUP BY test_id
    ) qm
        ON qm.test_id = t.id

    LEFT JOIN (
        SELECT
            test_id,
            SUM(marks) AS max_subj
        FROM questions
        WHERE type <> 'mcq'
        GROUP BY test_id
    ) qs
        ON qs.test_id = t.id

    WHERE
        s.student_id = ?
        AND s.status IN ('submitted', 'evaluated')
        AND s.submitted_at IS NOT NULL

    ORDER BY s.submitted_at DESC
");

$stmt->execute([$studentId]);

$allAttempts = $stmt->fetchAll();


/* ---------------------------------------------------------
   SPLIT EVALUATED / PENDING
--------------------------------------------------------- */

$evaluatedResults = array_values(
    array_filter(
        $allAttempts,
        fn($r) => $r['evaluation_status'] === 'evaluated'
    )
);

$pendingResults = array_values(
    array_filter(
        $allAttempts,
        fn($r) => $r['evaluation_status'] === 'pending_manual_review'
    )
);


/* ---------------------------------------------------------
   CALCULATE BASIC STATS
--------------------------------------------------------- */

$totalEvaluated = count($evaluatedResults);
$pendingCount = count($pendingResults);

$averageScore = 0;
$highestScore = 0;
$highestResult = null;

if ($totalEvaluated > 0) {

    $totalPercentage = 0;

    foreach ($evaluatedResults as $result) {

        $obtained = (float)(
            $result['total_score']
            ?? $result['total_marks_obtained']
            ?? 0
        );

        $total = (float)$result['total_marks'];

        $percentage = $total > 0
            ? ($obtained / $total) * 100
            : 0;

        $totalPercentage += $percentage;

        if ($percentage > $highestScore) {
            $highestScore = $percentage;
            $highestResult = $result;
        }
    }

    $averageScore = $totalPercentage / $totalEvaluated;
}


/* ---------------------------------------------------------
   PCI RECORDS
--------------------------------------------------------- */

$pciByTest = [];

$stmt = $pdo->prepare("
    SELECT
        test_id,
        pci_score,
        mcq_score,
        coding_score,
        explanation_score,
        generated_at
    FROM pci_records
    WHERE student_id = ?
    ORDER BY generated_at DESC
");

$stmt->execute([$studentId]);

foreach ($stmt->fetchAll() as $pci) {

    $testId = (int)$pci['test_id'];

    /*
     * Keep only the latest PCI record
     * for each test.
     */
    if (!isset($pciByTest[$testId])) {
        $pciByTest[$testId] = $pci;
    }
}


/* ---------------------------------------------------------
   OPTIONAL INDIVIDUAL TEST DETAIL
--------------------------------------------------------- */

$selectedSubmissionId = isset($_GET['submission_id'])
    ? (int)$_GET['submission_id']
    : 0;

$selectedResult = null;

if ($selectedSubmissionId > 0) {

    foreach ($evaluatedResults as $result) {

        if ((int)$result['submission_id'] === $selectedSubmissionId) {
            $selectedResult = $result;
            break;
        }
    }
}


/* ---------------------------------------------------------
   QUESTION LEVEL DATA FOR SELECTED TEST
--------------------------------------------------------- */

$selectedQuestions = [];

if ($selectedResult) {

    $stmt = $pdo->prepare("
        SELECT
            sa.marks_obtained,
            sa.evaluation_remarks,
            sa.answer_json,

            q.type,
            q.question_text,
            q.options_json,
            q.correct_answer,
            q.marks,
            q.sort_order

        FROM student_answers sa

        JOIN questions q
            ON q.id = sa.question_id

        WHERE sa.submission_id = ?

        ORDER BY q.sort_order, q.id
    ");

    $stmt->execute([
        $selectedSubmissionId
    ]);

    $selectedQuestions = $stmt->fetchAll();
}


/* ---------------------------------------------------------
   PAGE INFORMATION
--------------------------------------------------------- */

$firstName = explode(' ', $student['name'])[0];

$today = new DateTime();

$formattedDate = $today->format('F j, Y');
$dayName = $today->format('l');

$currentPage = 'results';


/* ---------------------------------------------------------
   PAGE TITLE / DESCRIPTION
--------------------------------------------------------- */

$pageHeading = 'Result Details';
$pageDescription = 'Detailed overview of your assessment performance.';

switch ($type) {

    case 'average':
        $pageHeading = 'Average Score';
        $pageDescription = 'Your performance across all evaluated assessments.';
        break;

    case 'highest':
        $pageHeading = 'Highest Score';
        $pageDescription = 'Your highest-performing assessment.';
        break;

    case 'pending':
        $pageHeading = 'Under Evaluation';
        $pageDescription = 'Assessments whose results are still being evaluated.';
        break;

    case 'evaluated':
    default:
        $pageHeading = 'Tests Evaluated';
        $pageDescription = 'All assessments whose results have been fully evaluated.';
        break;
}

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

    <link
        rel="stylesheet"
        href="<?= ASSETS_URL ?>/css/chatbot.css"
    >

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,300,0,0&display=swap">
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

    <style>

        /* -----------------------------------------------------
           RESULT DETAILS PAGE
        ----------------------------------------------------- */

        .result-details-header {
            margin-bottom: var(--space-6);
        }

        .result-details-header h1 {
            margin: 0;
            font-size: var(--fs-28);
            font-weight: 800;
            color: var(--gray-90);
        }

        .result-details-header p {
            margin-top: 6px;
            color: var(--gray-50);
        }

        .result-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
            color: var(--accent);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .result-back-link:hover {
            text-decoration: underline;
        }

        .result-summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .result-summary-card {
            background: var(--surface-card);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            padding: var(--space-5);
            box-shadow: var(--neu-card);
        }

        .result-summary-label {
            color: var(--gray-50);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .result-summary-value {
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-90);
        }

        .result-section {
            background: var(--surface-card);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-xl);
            box-shadow: var(--neu-card);
            margin-bottom: var(--space-6);
            overflow: hidden;
        }

        .result-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: var(--space-5) var(--space-6);
            border-bottom: 1px solid var(--gray-10);
        }

        .result-section-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-90);
        }

        .result-section-body {
            padding: var(--space-6);
        }

        .result-table-wrapper {
            overflow-x: auto;
        }

        .result-table {
            width: 100%;
            border-collapse: collapse;
        }

        .result-table th {
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--gray-50);
            padding: 12px;
            border-bottom: 1px solid var(--gray-10);
        }

        .result-table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--gray-10);
            color: var(--gray-80);
        }

        .result-table tr:last-child td {
            border-bottom: none;
        }

        .result-test-name {
            font-weight: 700;
            color: var(--gray-90);
        }

        .result-test-date {
            font-size: 12px;
            color: var(--gray-50);
            margin-top: 4px;
        }

        .result-score {
            font-weight: 800;
            color: var(--gray-90);
        }

        .result-progress {
            width: 120px;
            height: 7px;
            background: var(--gray-10);
            border-radius: 10px;
            overflow: hidden;
        }

        .result-progress-fill {
            height: 100%;
            border-radius: inherit;
        }

        .result-analysis-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-5);
        }

        .result-chart-card {
            min-height: 300px;
        }

        .result-chart-container {
            position: relative;
            height: 280px;
        }

        .question-analysis {
            display: grid;
            gap: 14px;
        }

        .question-analysis-item {
            border: 1px solid var(--gray-10);
            border-radius: 12px;
            padding: 16px;
        }

        .question-analysis-top {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 8px;
        }

        .question-analysis-text {
            font-size: 14px;
            line-height: 1.5;
            color: var(--gray-80);
        }

        .question-analysis-score {
            white-space: nowrap;
            font-weight: 700;
        }

        .question-analysis-answer {
            margin-top: 10px;
            padding: 10px;
            background: var(--gray-5);
            border-radius: 8px;
            font-size: 13px;
        }

        .question-analysis-remarks {
            margin-top: 8px;
            color: var(--accent);
            font-size: 13px;
        }

        .result-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
            color: var(--accent);
            border: 1px solid var(--glass-border);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
        }

        .result-action:hover {
            background: var(--gray-5);
        }

        .result-empty {
            text-align: center;
            padding: 50px 20px;
            color: var(--gray-50);
        }

        .result-empty-icon {
            margin-bottom: 15px;
            opacity: .6;
        }

        .pci-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }

        .pci-item {
            padding: 14px;
            border-radius: 12px;
            background: var(--gray-5);
        }

        .pci-item-label {
            font-size: 12px;
            color: var(--gray-50);
            margin-bottom: 5px;
        }

        .pci-item-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--gray-90);
        }

        @media (max-width: 900px) {

            .result-summary-grid {
                grid-template-columns: 1fr 1fr;
            }

            .result-analysis-grid {
                grid-template-columns: 1fr;
            }

            .pci-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {

            .result-summary-grid {
                grid-template-columns: 1fr;
            }

            .pci-grid {
                grid-template-columns: 1fr;
            }

            .result-section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>

</head>


<body>

<?= iconSprite() ?>

<?php include __DIR__ . '/../../includes/student_header.php'; ?>


<div class="result-details-header">

    <a
        href="results.php"
        class="result-back-link"
    >
        <?= icon('arrow-left', 15) ?>
        Back to Results
    </a>

    <h1>
        <?= h($pageHeading) ?>
    </h1>

    <p>
        <?= h($pageDescription) ?>
    </p>

</div>


<!-- =========================================================
     SUMMARY
========================================================= -->

<div class="result-summary-grid">

    <div class="result-summary-card">

        <div class="result-summary-label">
            Tests Evaluated
        </div>

        <div class="result-summary-value">
            <?= $totalEvaluated ?>
        </div>

    </div>


    <div class="result-summary-card">

        <div class="result-summary-label">
            Average Score
        </div>

        <div class="result-summary-value">
            <?= $totalEvaluated > 0
                ? number_format($averageScore, 1) . '%'
                : '—'
            ?>
        </div>

    </div>


    <div class="result-summary-card">

        <div class="result-summary-label">
            Highest Score
        </div>

        <div class="result-summary-value">
            <?= $totalEvaluated > 0
                ? round($highestScore) . '%'
                : '—'
            ?>
        </div>

    </div>

</div>


<?php if ($selectedResult): ?>

    <!-- =====================================================
         INDIVIDUAL TEST ANALYSIS
    ====================================================== -->

    <?php

        $selectedObtained = (float)(
            $selectedResult['total_score']
            ?? $selectedResult['total_marks_obtained']
            ?? 0
        );

        $selectedTotal = (float)$selectedResult['total_marks'];

        $selectedPercentage = $selectedTotal > 0
            ? round(($selectedObtained / $selectedTotal) * 100, 1)
            : 0;

        $selectedPci =
            $pciByTest[(int)$selectedResult['test_id']]
            ?? null;

    ?>


    <div class="result-section">

        <div class="result-section-header">

            <div>

                <h2>
                    <?= h($selectedResult['test_title']) ?>
                </h2>

                <div class="result-test-date">
                    Submitted
                    <?= $selectedResult['submitted_at']
                        ? date('M j, Y · g:i A', strtotime($selectedResult['submitted_at']))
                        : '—'
                    ?>
                </div>

            </div>

            <a
                href="result-details.php?type=<?= h($type) ?>"
                class="result-action"
            >
                <?= icon('arrow-left', 14) ?>
                All Results
            </a>

        </div>


        <div class="result-section-body">

            <div class="result-summary-grid">

                <div class="result-summary-card">

                    <div class="result-summary-label">
                        Final Score
                    </div>

                    <div class="result-summary-value">
                        <?= number_format($selectedObtained, 1) ?>
                        /
                        <?= number_format($selectedTotal, 1) ?>
                    </div>

                </div>


                <div class="result-summary-card">

                    <div class="result-summary-label">
                        Percentage
                    </div>

                    <div class="result-summary-value">
                        <?= $selectedPercentage ?>%
                    </div>

                </div>


                <div class="result-summary-card">

                    <div class="result-summary-label">
                        Result
                    </div>

                    <div class="result-summary-value">

                        <?php if ($selectedPercentage >= 40): ?>

                            <span style="color:var(--green);">
                                PASS
                            </span>

                        <?php else: ?>

                            <span style="color:var(--red);">
                                FAIL
                            </span>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- Score Breakdown -->

            <div class="result-section" style="box-shadow:none;">

                <div class="result-section-header">

                    <h2>
                        Score Breakdown
                    </h2>

                </div>

                <div class="result-section-body">

                    <div class="pci-grid">

                        <div class="pci-item">

                            <div class="pci-item-label">
                                Objective
                            </div>

                            <div class="pci-item-value">

                                <?= number_format(
                                    (float)$selectedResult['auto_score'],
                                    1
                                ) ?>

                                /

                                <?= number_format(
                                    (float)$selectedResult['max_mcq'],
                                    1
                                ) ?>

                            </div>

                        </div>


                        <div class="pci-item">

                            <div class="pci-item-label">
                                Subjective
                            </div>

                            <div class="pci-item-value">

                                <?= number_format(
                                    (float)$selectedResult['manual_score'],
                                    1
                                ) ?>

                                /

                                <?= number_format(
                                    (float)$selectedResult['max_subj'],
                                    1
                                ) ?>

                            </div>

                        </div>


                        <div class="pci-item">

                            <div class="pci-item-label">
                                Total
                            </div>

                            <div class="pci-item-value">

                                <?= number_format(
                                    $selectedObtained,
                                    1
                                ) ?>

                                /

                                <?= number_format(
                                    $selectedTotal,
                                    1
                                ) ?>

                            </div>

                        </div>


                        <div class="pci-item">

                            <div class="pci-item-label">
                                Performance
                            </div>

                            <div class="pci-item-value">

                                <?= $selectedPercentage ?>%

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- PCI -->

            <?php if ($selectedPci): ?>

                <div class="result-section" style="box-shadow:none;">

                    <div class="result-section-header">

                        <h2>
                            PCI Analysis
                        </h2>

                    </div>

                    <div class="result-section-body">

                        <div class="pci-grid">

                            <div class="pci-item">

                                <div class="pci-item-label">
                                    PCI Score
                                </div>

                                <div class="pci-item-value">
                                    <?= number_format(
                                        (float)$selectedPci['pci_score'],
                                        1
                                    ) ?>
                                </div>

                            </div>


                            <div class="pci-item">

                                <div class="pci-item-label">
                                    MCQ
                                </div>

                                <div class="pci-item-value">
                                    <?= number_format(
                                        (float)$selectedPci['mcq_score'],
                                        1
                                    ) ?>
                                </div>

                            </div>


                            <div class="pci-item">

                                <div class="pci-item-label">
                                    Coding
                                </div>

                                <div class="pci-item-value">
                                    <?= number_format(
                                        (float)$selectedPci['coding_score'],
                                        1
                                    ) ?>
                                </div>

                            </div>


                            <div class="pci-item">

                                <div class="pci-item-label">
                                    Explanation
                                </div>

                                <div class="pci-item-value">
                                    <?= number_format(
                                        (float)$selectedPci['explanation_score'],
                                        1
                                    ) ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endif; ?>


            <!-- GRAPH -->

            <div class="result-section" style="box-shadow:none;">

                <div class="result-section-header">

                    <h2>
                        Performance Visualization
                    </h2>

                </div>

                <div class="result-section-body">

                    <div class="result-chart-container">

                        <canvas id="scoreChart"></canvas>

                    </div>

                </div>

            </div>


            <!-- QUESTION ANALYSIS -->

            <div class="result-section" style="box-shadow:none;">

                <div class="result-section-header">

                    <h2>
                        Question-by-Question Analysis
                    </h2>

                </div>

                <div class="result-section-body">

                    <?php if (empty($selectedQuestions)): ?>

                        <div class="result-empty">
                            No question-level data available.
                        </div>

                    <?php else: ?>

                        <div class="question-analysis">

                            <?php foreach (
                                $selectedQuestions
                                as $index => $question
                            ): ?>

                                <?php

                                    $answer =
                                        json_decode(
                                            $question['answer_json'],
                                            true
                                        ) ?: [];

                                    if ($question['type'] === 'mcq') {

                                        $yourAnswer =
                                            $answer['selected']
                                            ?? '—';

                                    } elseif (
                                        $question['type'] === 'coding'
                                    ) {

                                        $yourAnswer =
                                            $answer['code']
                                            ?? '—';

                                    } else {

                                        $yourAnswer =
                                            $answer['text']
                                            ?? '—';
                                    }

                                ?>

                                <div class="question-analysis-item">

                                    <div class="question-analysis-top">

                                        <strong>
                                            Q<?= $index + 1 ?>
                                            ·
                                            <?= h(
                                                ucfirst(
                                                    $question['type']
                                                )
                                            ) ?>
                                        </strong>

                                        <span
                                            class="question-analysis-score"
                                            style="
                                                color:
                                                <?= (
                                                    (float)$question['marks_obtained']
                                                    >=
                                                    (float)$question['marks']
                                                )
                                                    ? 'var(--green)'
                                                    : 'var(--red)'
                                                ?>;
                                            "
                                        >

                                            <?= number_format(
                                                (float)$question['marks_obtained'],
                                                1
                                            ) ?>

                                            /

                                            <?= number_format(
                                                (float)$question['marks'],
                                                1
                                            ) ?>

                                        </span>

                                    </div>


                                    <div class="question-analysis-text">

                                        <?= h(
                                            $question['question_text']
                                        ) ?>

                                    </div>


                                    <div class="question-analysis-answer">

                                        <strong>
                                            Your Answer:
                                        </strong>

                                        <div style="margin-top:5px;white-space:pre-wrap;">

                                            <?= h(
                                                $yourAnswer !== ''
                                                    ? $yourAnswer
                                                    : '—'
                                            ) ?>

                                        </div>

                                    </div>


                                    <?php if (
                                        !empty(
                                            $question['evaluation_remarks']
                                        )
                                    ): ?>

                                        <div class="question-analysis-remarks">

                                            <strong>
                                                Evaluator:
                                            </strong>

                                            <?= h(
                                                $question['evaluation_remarks']
                                            ) ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    </div>


<?php else: ?>


    <!-- =====================================================
         OVERVIEW
    ====================================================== -->

    <?php if ($type === 'pending'): ?>


        <!-- PENDING RESULTS -->

        <div class="result-section">

            <div class="result-section-header">

                <h2>
                    Assessments Under Evaluation
                </h2>

            </div>

            <div class="result-section-body">

                <?php if (empty($pendingResults)): ?>

                    <div class="result-empty">

                        <div class="result-empty-icon">
                            <?= icon('check-circle', 48) ?>
                        </div>

                        <h3>
                            No Pending Results
                        </h3>

                        <p>
                            All your submitted assessments have been evaluated.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="result-table-wrapper">

                        <table class="result-table">

                            <thead>

                                <tr>
                                    <th>Assessment</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach ($pendingResults as $result): ?>

                                    <tr>

                                        <td>

                                            <div class="result-test-name">
                                                <?= h($result['test_title']) ?>
                                            </div>

                                        </td>

                                        <td>

                                            <?= $result['submitted_at']
                                                ? date(
                                                    'M j, Y',
                                                    strtotime(
                                                        $result['submitted_at']
                                                    )
                                                )
                                                : '—'
                                            ?>

                                        </td>

                                        <td>

                                            <span class="badge badge-pending">
                                                <?= icon('clock', 12) ?>
                                                Under Evaluation
                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>


    <?php elseif ($type === 'highest'): ?>


        <!-- HIGHEST SCORE -->

        <div class="result-section">

            <div class="result-section-header">

                <h2>
                    Highest Performing Assessment
                </h2>

            </div>

            <div class="result-section-body">

                <?php if (!$highestResult): ?>

                    <div class="result-empty">
                        No evaluated assessments available.
                    </div>

                <?php else: ?>

                    <?php

                        $obtained = (float)(
                            $highestResult['total_score']
                            ?? $highestResult['total_marks_obtained']
                            ?? 0
                        );

                        $total = (float)$highestResult['total_marks'];

                        $percentage = $total > 0
                            ? round(($obtained / $total) * 100, 1)
                            : 0;

                    ?>

                    <div class="result-summary-grid">

                        <div class="result-summary-card">

                            <div class="result-summary-label">
                                Assessment
                            </div>

                            <div
                                class="result-summary-value"
                                style="font-size:20px;"
                            >
                                <?= h($highestResult['test_title']) ?>
                            </div>

                        </div>


                        <div class="result-summary-card">

                            <div class="result-summary-label">
                                Score
                            </div>

                            <div class="result-summary-value">
                                <?= number_format($obtained, 1) ?>
                                /
                                <?= number_format($total, 1) ?>
                            </div>

                        </div>


                        <div class="result-summary-card">

                            <div class="result-summary-label">
                                Percentage
                            </div>

                            <div class="result-summary-value">
                                <?= $percentage ?>%
                            </div>

                        </div>

                    </div>


                    <a
                        href="result-details.php?type=highest&submission_id=<?= (int)$highestResult['submission_id'] ?>"
                        class="result-action"
                    >
                        <?= icon('chart', 14) ?>
                        View Full Test Analysis
                    </a>

                <?php endif; ?>

            </div>

        </div>


    <?php elseif ($type === 'average'): ?>

    <!-- Average Score Header -->
    <div class="analysis-header">
        <div>
            <a href="results.php" class="back-link">
                <?= icon('arrow-left', 16) ?>
                Back to Results
            </a>

            <h1>Average Score Analysis</h1>
            <p>
                A detailed breakdown of your average performance across all evaluated tests.
            </p>
        </div>
    </div>


    <!-- Average Summary -->
    <div class="analysis-stats">

        <div class="analysis-stat-card">
            <div class="analysis-stat-icon">
                <?= icon('star', 22) ?>
            </div>

            <div>
                <div class="analysis-stat-value">
                    <?= $totalEvaluated > 0 ? number_format($averageScore, 1) . '%' : '—' ?>
                </div>

                <div class="analysis-stat-label">
                    Overall Average
                </div>
            </div>
        </div>


        <div class="analysis-stat-card">
            <div class="analysis-stat-icon">
                <?= icon('chart', 22) ?>
            </div>

            <div>
                <div class="analysis-stat-value">
                    <?= $totalEvaluated ?>
                </div>

                <div class="analysis-stat-label">
                    Evaluated Tests
                </div>
            </div>
        </div>


        <div class="analysis-stat-card">
            <div class="analysis-stat-icon">
                <?= icon('graph', 22) ?>
            </div>

            <div>
                <div class="analysis-stat-value">
                    <?= $totalEvaluated > 0 ? number_format($highestScore, 1) . '%' : '—' ?>
                </div>

                <div class="analysis-stat-label">
                    Highest Score
                </div>
            </div>
        </div>

    </div>


    <!-- Performance Chart -->
    <div class="analysis-section">

        <div class="section-heading">
            <div>
                <h2>Performance Across Tests</h2>
                <p>
                    Your score in each evaluated test compared with your overall average.
                </p>
            </div>
        </div>

        <?php if ($totalEvaluated > 0): ?>

            <div class="chart-container">
                <canvas id="averagePerformanceChart"></canvas>
            </div>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-state-icon">
                    <?= icon('chart', 32) ?>
                </div>

                <h3>No Evaluated Tests</h3>

                <p>
                    Your average score will appear here once a test has been evaluated.
                </p>
            </div>

        <?php endif; ?>

    </div>


    <!-- Test Breakdown -->
    <div class="analysis-section">

        <div class="section-heading">
            <div>
                <h2>Test-wise Breakdown</h2>
                <p>
                    Individual scores used to calculate your average.
                </p>
            </div>
        </div>


        <?php if ($totalEvaluated > 0): ?>

            <div class="results-table-wrapper">

                <table class="results-table">

                    <thead>
                        <tr>
                            <th>Test</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Date</th>
                            <th>Analysis</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($evaluatedResults as $result): ?>

                            <?php
                                $obtained = (float)(
                                    $result['total_score']
                                    ?? $result['total_marks_obtained']
                                    ?? 0
                                );

                                $total = (float)$result['total_marks'];

                                $percentage = $total > 0
                                    ? ($obtained / $total) * 100
                                    : 0;
                            ?>

                            <tr>

                                <td>
                                    <div class="test-name">
                                        <?= htmlspecialchars($result['test_title']) ?>
                                    </div>
                                </td>


                                <td>
                                    <strong>
                                        <?= number_format($obtained, 1) ?>
                                    </strong>
                                    /
                                    <?= number_format($total, 1) ?>
                                </td>


                                <td>
                                    <span class="percentage-value">
                                        <?= number_format($percentage, 1) ?>%
                                    </span>
                                </td>


                                <td>
                                    <?= !empty($result['submitted_at'])
                                        ? date('d M Y', strtotime($result['submitted_at']))
                                        : '—'
                                    ?>
                                </td>


                                <td>

                                    <a
                                        href="result-details.php?type=evaluated&submission_id=<?= (int)$result['submission_id'] ?>"
                                        class="view-analysis-btn"
                                    >
                                        View Analysis
                                        <?= icon('arrow-right', 14) ?>
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    <?= icon('doc.text.fill', 32) ?>
                </div>

                <h3>No Results Available</h3>

                <p>
                    There are currently no evaluated tests to include in your average.
                </p>

            </div>

        <?php endif; ?>

    </div>


        <!-- ALL EVALUATED TESTS -->

        <div class="result-section">

            <div class="result-section-header">

                <div>

                    <h2>
                        Evaluated Assessments
                    </h2>

                </div>

            </div>


            <div class="result-section-body">

                <?php if (empty($evaluatedResults)): ?>

                    <div class="result-empty">

                        <div class="result-empty-icon">
                            <?= icon('chart', 48) ?>
                        </div>

                        <h3>
                            No Results Yet
                        </h3>

                        <p>
                            Your evaluated assessment results will appear here.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="result-table-wrapper">

                        <table class="result-table">

                            <thead>

                                <tr>

                                    <th>
                                        Assessment
                                    </th>

                                    <th>
                                        Score
                                    </th>

                                    <th>
                                        Objective
                                    </th>

                                    <th>
                                        Subjective
                                    </th>

                                    <th>
                                        Performance
                                    </th>

                                    <th>
                                        Analysis
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $evaluatedResults
                                    as $result
                                ): ?>

                                    <?php

                                        $obtained = (float)(
                                            $result['total_score']
                                            ?? $result['total_marks_obtained']
                                            ?? 0
                                        );

                                        $total =
                                            (float)$result['total_marks'];

                                        $percentage =
                                            $total > 0
                                            ? round(
                                                ($obtained / $total) * 100
                                            )
                                            : 0;

                                        $pass =
                                            $percentage >= 40;

                                    ?>

                                    <tr>

                                        <td>

                                            <div class="result-test-name">

                                                <?= h(
                                                    $result['test_title']
                                                ) ?>

                                            </div>

                                            <div class="result-test-date">

                                                <?= $result['submitted_at']
                                                    ? date(
                                                        'M j, Y',
                                                        strtotime(
                                                            $result['submitted_at']
                                                        )
                                                    )
                                                    : '—'
                                                ?>

                                            </div>

                                        </td>


                                        <td>

                                            <span class="result-score">

                                                <?= number_format(
                                                    $obtained,
                                                    1
                                                ) ?>

                                                /

                                                <?= number_format(
                                                    $total,
                                                    1
                                                ) ?>

                                            </span>

                                        </td>


                                        <td>

                                            <?= number_format(
                                                (float)$result['auto_score'],
                                                1
                                            ) ?>

                                            /

                                            <?= number_format(
                                                (float)$result['max_mcq'],
                                                1
                                            ) ?>

                                        </td>


                                        <td>

                                            <?= number_format(
                                                (float)$result['manual_score'],
                                                1
                                            ) ?>

                                            /

                                            <?= number_format(
                                                (float)$result['max_subj'],
                                                1
                                            ) ?>

                                        </td>


                                        <td>

                                            <div style="display:flex;align-items:center;gap:10px;">

                                                <div class="result-progress">

                                                    <div
                                                        class="result-progress-fill"
                                                        style="
                                                            width:<?= $percentage ?>%;
                                                            background:
                                                            <?= $percentage >= 70
                                                                ? 'var(--green)'
                                                                : (
                                                                    $percentage >= 40
                                                                    ? 'var(--yellow)'
                                                                    : 'var(--red)'
                                                                )
                                                            ?>;
                                                        "
                                                    ></div>

                                                </div>

                                                <strong>
                                                    <?= $percentage ?>%
                                                </strong>

                                            </div>

                                        </td>


                                        <td>

                                            <a
                                                href="result-details.php?type=evaluated&submission_id=<?= (int)$result['submission_id'] ?>"
                                                class="result-action"
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

                <?php endif; ?>

            </div>

        </div>

   

<?php elseif ($type === 'highest'): ?>

    <!-- Highest Score Header -->
    <div class="analysis-header">
        <div>
            <a href="results.php" class="back-link">
                <?= icon('arrow-left', 16) ?>
                Back to Results
            </a>

            <h1>Highest Score Analysis</h1>
            <p>
                A detailed look at your best-performing evaluated test.
            </p>
        </div>
    </div>


    <?php if ($highestResult): ?>

        <?php
            $highestObtained = (float)(
                $highestResult['total_score']
                ?? $highestResult['total_marks_obtained']
                ?? 0
            );

            $highestTotal = (float)$highestResult['total_marks'];

            $highestPercentage = $highestTotal > 0
                ? ($highestObtained / $highestTotal) * 100
                : 0;
        ?>


        <!-- Highest Score Summary -->
        <div class="analysis-stats">

            <div class="analysis-stat-card">
                <div class="analysis-stat-icon">
                    <?= icon('star', 22) ?>
                </div>

                <div>
                    <div class="analysis-stat-value">
                        <?= number_format($highestPercentage, 1) ?>%
                    </div>

                    <div class="analysis-stat-label">
                        Highest Score
                    </div>
                </div>
            </div>


            <div class="analysis-stat-card">
                <div class="analysis-stat-icon">
                    <?= icon('doc.text.fill', 22) ?>
                </div>

                <div>
                    <div class="analysis-stat-value">
                        <?= htmlspecialchars($highestResult['test_title']) ?>
                    </div>

                    <div class="analysis-stat-label">
                        Best Performing Test
                    </div>
                </div>
            </div>


            <div class="analysis-stat-card">
                <div class="analysis-stat-icon">
                    <?= icon('chart', 22) ?>
                </div>

                <div>
                    <div class="analysis-stat-value">
                        <?= number_format($highestObtained, 1) ?>
                        /
                        <?= number_format($highestTotal, 1) ?>
                    </div>

                    <div class="analysis-stat-label">
                        Marks Obtained
                    </div>
                </div>
            </div>

        </div>


        <!-- Best Result -->
        <div class="analysis-section">

            <div class="section-heading">
                <div>
                    <h2>Best Performing Test</h2>
                    <p>
                        Details of the test where you achieved your highest percentage.
                    </p>
                </div>
            </div>


            <div class="best-result-card">

                <div class="best-result-info">

                    <h3>
                        <?= htmlspecialchars($highestResult['test_title']) ?>
                    </h3>

                    <div class="best-result-meta">

                        <span>
                            <?= number_format($highestObtained, 1) ?>
                            /
                            <?= number_format($highestTotal, 1) ?>
                            marks
                        </span>

                        <span>
                            <?= !empty($highestResult['submitted_at'])
                                ? date('d M Y', strtotime($highestResult['submitted_at']))
                                : '—'
                            ?>
                        </span>

                    </div>

                </div>


                <div class="best-result-score">
                    <?= number_format($highestPercentage, 1) ?>%
                </div>


                <a
                    href="result-details.php?type=evaluated&submission_id=<?= (int)$highestResult['submission_id'] ?>"
                    class="view-analysis-btn"
                >
                    View Full Analysis
                    <?= icon('arrow-right', 14) ?>
                </a>

            </div>

        </div>


        <!-- All Scores -->
        <div class="analysis-section">

            <div class="section-heading">
                <div>
                    <h2>All Evaluated Scores</h2>
                    <p>
                        Your evaluated tests arranged from highest to lowest score.
                    </p>
                </div>
            </div>


            <div class="results-table-wrapper">

                <table class="results-table">

                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Test</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Date</th>
                            <th>Analysis</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        $rankedResults = $evaluatedResults;

                        usort(
                            $rankedResults,
                            function ($a, $b) {

                                $aObtained = (float)(
                                    $a['total_score']
                                    ?? $a['total_marks_obtained']
                                    ?? 0
                                );

                                $bObtained = (float)(
                                    $b['total_score']
                                    ?? $b['total_marks_obtained']
                                    ?? 0
                                );

                                $aTotal = (float)$a['total_marks'];
                                $bTotal = (float)$b['total_marks'];

                                $aPercentage = $aTotal > 0
                                    ? $aObtained / $aTotal
                                    : 0;

                                $bPercentage = $bTotal > 0
                                    ? $bObtained / $bTotal
                                    : 0;

                                return $bPercentage <=> $aPercentage;
                            }
                        );

                        $rank = 1;

                        ?>

                        <?php foreach ($rankedResults as $result): ?>

                            <?php
                                $obtained = (float)(
                                    $result['total_score']
                                    ?? $result['total_marks_obtained']
                                    ?? 0
                                );

                                $total = (float)$result['total_marks'];

                                $percentage = $total > 0
                                    ? ($obtained / $total) * 100
                                    : 0;
                            ?>

                            <tr>

                                <td>
                                    <strong>#<?= $rank ?></strong>
                                </td>

                                <td>
                                    <div class="test-name">
                                        <?= htmlspecialchars($result['test_title']) ?>
                                    </div>
                                </td>

                                <td>
                                    <?= number_format($obtained, 1) ?>
                                    /
                                    <?= number_format($total, 1) ?>
                                </td>

                                <td>
                                    <span class="percentage-value">
                                        <?= number_format($percentage, 1) ?>%
                                    </span>
                                </td>

                                <td>
                                    <?= !empty($result['submitted_at'])
                                        ? date('d M Y', strtotime($result['submitted_at']))
                                        : '—'
                                    ?>
                                </td>

                                <td>
                                    <a
                                        href="result-details.php?type=evaluated&submission_id=<?= (int)$result['submission_id'] ?>"
                                        class="view-analysis-btn"
                                    >
                                        View Analysis
                                        <?= icon('arrow-right', 14) ?>
                                    </a>
                                </td>

                            </tr>

                            <?php $rank++; ?>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>


    <?php else: ?>

        <div class="analysis-section">

            <div class="empty-state">

                <div class="empty-state-icon">
                    <?= icon('star', 32) ?>
                </div>

                <h3>No Evaluated Tests</h3>

                <p>
                    Your highest score will appear here once a test has been evaluated.
                </p>

            </div>

        </div>

    <?php endif; ?>

    <?php elseif ($type === 'pending'): ?>

    <!-- Pending Header -->
    <div class="analysis-header">
        <div>
            <a href="results.php" class="back-link">
                <?= icon('arrow-left', 16) ?>
                Back to Results
            </a>

            <h1>Tests Under Evaluation</h1>
            <p>
                Tests you have submitted whose results are still being evaluated.
            </p>
        </div>
    </div>


    <!-- Pending Summary -->
    <div class="analysis-stats">

        <div class="analysis-stat-card">

            <div class="analysis-stat-icon">
                <?= icon('clock', 22) ?>
            </div>

            <div>

                <div class="analysis-stat-value">
                    <?= $pendingCount ?>
                </div>

                <div class="analysis-stat-label">
                    Under Evaluation
                </div>

            </div>

        </div>

    </div>


    <?php if ($pendingCount > 0): ?>

        <div class="analysis-section">

            <div class="section-heading">

                <div>

                    <h2>Pending Results</h2>

                    <p>
                        These tests have been submitted and are awaiting evaluation.
                    </p>

                </div>

            </div>


            <div class="results-table-wrapper">

                <table class="results-table">

                    <thead>

                        <tr>
                            <th>Test</th>
                            <th>Duration</th>
                            <th>Submitted On</th>
                            <th>Status</th>
                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($pendingResults as $result): ?>

                            <tr>

                                <td>

                                    <div class="test-name">
                                        <?= htmlspecialchars($result['test_title']) ?>
                                    </div>

                                </td>


                                <td>

                                    <?= !empty($result['duration_minutes'])
                                        ? htmlspecialchars($result['duration_minutes']) . ' min'
                                        : '—'
                                    ?>

                                </td>


                                <td>

                                    <?= !empty($result['submitted_at'])
                                        ? date('d M Y, h:i A', strtotime($result['submitted_at']))
                                        : '—'
                                    ?>

                                </td>


                                <td>

                                    <span class="pending-status">

                                        <?= icon('clock', 14) ?>

                                        Under Evaluation

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- Information -->
        <div class="analysis-section pending-info">

            <div class="pending-info-icon">
                <?= icon('info', 22) ?>
            </div>

            <div>

                <h3>What happens next?</h3>

                <p>
                    Once the evaluation is completed, your score and detailed
                    analysis will become available in the Results section.
                </p>

            </div>

        </div>


    <?php else: ?>

        <div class="analysis-section">

            <div class="empty-state">

                <div class="empty-state-icon">
                    <?= icon('checkmark', 32) ?>
                </div>

                <h3>No Tests Under Evaluation</h3>

                <p>
                    All your submitted tests have been evaluated.
                </p>

            </div>

        </div>

    <?php endif; ?>

<?php endif; ?>

<?php endif; ?>

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


/* ---------------------------------------------------------
   THEME
--------------------------------------------------------- */

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


/* ---------------------------------------------------------
   CHARTS
--------------------------------------------------------- */

<?php if ($selectedResult): ?>

const scoreChart =
    document.getElementById('scoreChart');

if (scoreChart) {

    new Chart(scoreChart, {

        type: 'doughnut',

        data: {

            labels: [
                'Score',
                'Remaining'
            ],

            datasets: [{
                data: [
                    <?= $selectedPercentage ?>,
                    <?= max(0, 100 - $selectedPercentage) ?>
                ]
            }]

        },

        options: {

            responsive: true,

            maintainAspectRatio: false,

            plugins: {

                legend: {
                    position: 'bottom'
                }

            }

        }

    });

}

<?php endif; ?>


<?php if ($type === 'average' && !empty($evaluatedResults)): ?>

const averageChart =
    document.getElementById('averageChart');

if (averageChart) {

    new Chart(averageChart, {

        type: 'bar',

        data: {

            labels: [

                <?php foreach ($evaluatedResults as $result): ?>

                    <?= json_encode($result['test_title']) ?>,

                <?php endforeach; ?>

            ],

            datasets: [{

                label: 'Score %',

                data: [

                    <?php foreach ($evaluatedResults as $result): ?>

                        <?php

                            $obtained =
                                (float)(
                                    $result['total_score']
                                    ?? $result['total_marks_obtained']
                                    ?? 0
                                );

                            $total =
                                (float)$result['total_marks'];

                            $percentage =
                                $total > 0
                                ? round(
                                    ($obtained / $total) * 100,
                                    1
                                )
                                : 0;

                        ?>

                        <?= $percentage ?>,

                    <?php endforeach; ?>

                ]

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

<?php endif; ?>
<?php if ($type === 'average' && $totalEvaluated > 0): ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
const averageLabels = <?= json_encode(
    array_map(
        fn($result) => $result['test_title'],
        $evaluatedResults
    )
) ?>;

const averageScores = <?= json_encode(
    array_map(
        function ($result) {
            $obtained = (float)(
                $result['total_score']
                ?? $result['total_marks_obtained']
                ?? 0
            );

            $total = (float)$result['total_marks'];

            return $total > 0
                ? round(($obtained / $total) * 100, 1)
                : 0;
        },
        $evaluatedResults
    )
) ?>;

const overallAverage = <?= json_encode(round($averageScore, 1)) ?>;


const ctx = document
    .getElementById('averagePerformanceChart')
    .getContext('2d');


new Chart(ctx, {

    data: {

        labels: averageLabels,

        datasets: [

            {
                type: 'bar',

                label: 'Test Score',

                data: averageScores,

                borderWidth: 1,

                borderRadius: 8,

                maxBarThickness: 55
            },

            {
                type: 'line',

                label: 'Overall Average',

                data: averageLabels.map(() => overallAverage),

                borderWidth: 2,

                pointRadius: 0,

                pointHoverRadius: 0,

                fill: false,

                tension: 0
            }

        ]

    },

    options: {

        responsive: true,

        maintainAspectRatio: false,

        interaction: {
            mode: 'index',
            intersect: false
        },

        plugins: {

            legend: {
                display: true
            },

            tooltip: {

                callbacks: {

                    label: function(context) {

                        return context.dataset.label
                            + ': '
                            + context.parsed.y.toFixed(1)
                            + '%';

                    }

                }

            }

        },

        scales: {

            y: {

                beginAtZero: true,

                max: 100,

                ticks: {

                    callback: function(value) {
                        return value + '%';
                    }

                },

                title: {
                    display: true,
                    text: 'Score (%)'
                }

            },

            x: {

                title: {
                    display: true,
                    text: 'Tests'
                }

            }

        }

    });
<?php endif; ?>
</script>

</body>
</html>