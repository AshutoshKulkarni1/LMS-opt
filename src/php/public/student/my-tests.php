<?php

$pageTitle = 'My Tests';

require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/icons.php';

startSession();
requireStudent();

$pdo = getDB();
$studentId = $_SESSION['student_id'];

/*
|--------------------------------------------------------------------------
| Get Student Information
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| Get Student Tests
|--------------------------------------------------------------------------
*/

$tests = getStudentTests($studentId);

/*
|--------------------------------------------------------------------------
| Page Variables
|--------------------------------------------------------------------------
*/

$currentPage = 'my-tests';

$firstName = explode(' ', $student['name'])[0];

$now = time();

$upcomingTests = [];
$allTests = $tests;

/*
|--------------------------------------------------------------------------
| Identify Upcoming Tests
|--------------------------------------------------------------------------
*/

foreach ($tests as $test) {

    $start = !empty($test['start_time'])
        ? strtotime($test['start_time'])
        : null;

    $isUpcoming =
        $start !== null &&
        $start > $now &&
        empty($test['submission_status']);

    if ($isUpcoming) {
        $upcomingTests[] = $test;
    }
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Get the display status of a test for the student.
 */
function getTestDisplayStatus(array $test): array
{
    $submission = $test['submission_status'] ?? null;
    $testStatus = $test['status'] ?? null;

    /*
     * Evaluated
     */
    if ($submission === 'evaluated') {
        return [
            'label' => 'Completed',
            'class' => 'badge-success',
            'filter' => 'completed'
        ];
    }

    /*
     * Submitted but not evaluated
     */
    if ($submission === 'submitted') {
        return [
            'label' => 'Under Evaluation',
            'class' => 'badge-pending',
            'filter' => 'completed'
        ];
    }

    /*
     * Currently in progress
     */
    if ($submission === 'in_progress') {
        return [
            'label' => 'In Progress',
            'class' => 'badge-active',
            'filter' => 'in_progress'
        ];
    }

    /*
     * Upcoming
     */
    if (
        !empty($test['start_time']) &&
        strtotime($test['start_time']) > time()
    ) {
        return [
            'label' => 'Upcoming',
            'class' => 'badge-info',
            'filter' => 'upcoming'
        ];
    }

    /*
     * Test completed by admin but student did not submit
     */
    if ($testStatus === 'completed') {
        return [
            'label' => 'Missed',
            'class' => 'badge-pending',
            'filter' => 'not_started'
        ];
    }

    /*
     * Default
     */
    return [
        'label' => 'Not Started',
        'class' => 'badge-info',
        'filter' => 'not_started'
    ];
}

/**
 * Format date/time.
 */
function formatTestDate(?string $date): string
{
    if (empty($date)) {
        return 'Not specified';
    }

    return (new DateTime($date))->format('M j, Y • g:i A');
}

/**
 * Calculate percentage.
 */
function calculatePercentage(array $test): ?int
{
    $obtained = (float)($test['total_marks_obtained'] ?? 0);
    $total = (float)($test['total_marks'] ?? 0);

    if ($total <= 0) {
        return null;
    }

    return round(($obtained / $total) * 100);
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
    <meta name="description" content="Browse and launch your assigned assessments, practice exams, and course tests with status indicators and time allocations.">

    <!-- Student stylesheet -->
    <link
        rel="stylesheet"
        href="<?= ASSETS_URL ?>/css/student.css"
    >

    <link rel="preload" href="<?= ASSETS_URL ?>/fonts/Inter-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/student.css') ? filemtime(__DIR__ . '/../../../../assets/css/student.css') : time() ?>">
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
    <!-- Non-critical Chatbot Stylesheet (deferred to avoid render blocking) -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css?v=<?= file_exists(__DIR__ . '/../../../../assets/css/chatbot.css') ? filemtime(__DIR__ . '/../../../../assets/css/chatbot.css') : time() ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= ASSETS_URL ?>/css/chatbot.css"></noscript>
</head>

<body>

<?= iconSprite() ?>

<?php include __DIR__ . '/../../includes/student_header.php'; ?>


<!--
|--------------------------------------------------------------------------
| Main Content
|--------------------------------------------------------------------------
-->

<div class="student-page-content">

    <!-- Page Header -->
    <div class="page-header">

        <div>

            <h1 class="page-title">
                My Tests
            </h1>

            <p class="page-subtitle">
                View and manage all assessments assigned to you.
            </p>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Upcoming Tests
    |--------------------------------------------------------------------------
    -->

    <section class="my-tests-section">

        <div class="my-tests-section-header">

            <div>

                <h2 class="my-tests-section-title">
                    Upcoming Tests
                </h2>

                <p class="my-tests-section-subtitle">
                    Tests scheduled for you that have not started yet.
                </p>

            </div>

            <span class="my-tests-count">
                <?= count($upcomingTests) ?>
                <?= count($upcomingTests) === 1 ? 'Test' : 'Tests' ?>
            </span>

        </div>


        <?php if (empty($upcomingTests)): ?>

            <div class="my-tests-empty">

                <div class="my-tests-empty-icon">
                    <?= icon('calendar', 28) ?>
                </div>

                <div>

                    <h3>
                        No Upcoming Tests
                    </h3>

                    <p>
                        You currently have no upcoming assessments.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <div class="my-tests-grid">

                <?php foreach ($upcomingTests as $test): ?>

                    <?php
                        $status = getTestDisplayStatus($test);

                        $startTimestamp = !empty($test['start_time'])
                            ? strtotime($test['start_time'])
                            : null;

                        $endTimestamp = !empty($test['end_time'])
                            ? strtotime($test['end_time'])
                            : null;
                    ?>

                    <div
                        class="my-test-card upcoming-test-card expandable-test-card"
                        data-title="<?= h(strtolower($test['title'])) ?>"
                        data-status="upcoming"
                    >

                        <!-- Card Header -->

                        <div class="my-test-card-header">

                            <div class="my-test-card-icon">
                                <?= icon('doc.text.fill', 22) ?>
                            </div>

                            <span class="badge <?= h($status['class']) ?>">
                                <?= h($status['label']) ?>
                            </span>

                        </div>


                        <!-- Title -->

                        <h3 class="my-test-card-title">
                            <?= h($test['title']) ?>
                        </h3>


                        <!-- Description -->

                        <?php if (!empty($test['description'])): ?>

                            <p class="my-test-card-description">

                                <?= h($test['description']) ?>

                            </p>

                        <?php else: ?>

                            <p class="my-test-card-description muted">

                                No description provided.

                            </p>

                        <?php endif; ?>


                        <!-- Quick Meta -->

                        <div class="my-test-card-meta">

                            <span>
                                <?= icon('clock', 15) ?>

                                <?= (int)$test['duration_minutes'] ?> min
                            </span>

                            <span>
                                <?= icon('list', 15) ?>

                                <?= (int)($test['total_questions'] ?? 0) ?> questions
                            </span>

                        </div>


                        <!-- Expandable Details -->

                        <div class="my-test-card-details">

                            <div class="my-test-details-grid">

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Start Time
                                    </span>

                                    <strong>
                                        <?= h(formatTestDate($test['start_time'])) ?>
                                    </strong>

                                </div>


                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        End Time
                                    </span>

                                    <strong>
                                        <?= h(formatTestDate($test['end_time'])) ?>
                                    </strong>

                                </div>


                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Duration
                                    </span>

                                    <strong>
                                        <?= (int)$test['duration_minutes'] ?> minutes
                                    </strong>

                                </div>


                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Total Questions
                                    </span>

                                    <strong>
                                        <?= (int)($test['total_questions'] ?? 0) ?>
                                    </strong>

                                </div>


                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Total Marks
                                    </span>

                                    <strong>
                                        <?= h($test['total_marks'] ?? '—') ?>
                                    </strong>

                                </div>


                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Your Status
                                    </span>

                                    <strong>
                                        Not Started
                                    </strong>

                                </div>

                            </div>


                            <!-- Full Details -->

                            <div class="my-test-details-action">

                                <a
                                    href="my-tests.php?test_id=<?= (int)$test['id'] ?>"
                                    class="my-test-details-link"
                                    onclick="event.stopPropagation();"
                                >

                                    <span>
                                        View Full Details
                                    </span>

                                    <?= icon('arrow.right', 17) ?>

                                </a>

                            </div>

                        </div>


                        <!-- Expand Indicator -->

                        <div class="my-test-expand-indicator">

                            <span>
                                Click to <?= 'view details' ?>
                            </span>

                            <?= icon('chevron.down', 17) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | All Tests
    |--------------------------------------------------------------------------
    -->

    <section class="my-tests-section">

        <div class="my-tests-section-header">

            <div>

                <h2 class="my-tests-section-title">
                    All Tests
                </h2>

                <p class="my-tests-section-subtitle">
                    All assessments assigned to you.
                </p>

            </div>

            <span class="my-tests-count">
                <?= count($allTests) ?>
                <?= count($allTests) === 1 ? 'Test' : 'Tests' ?>
            </span>

        </div>


        <!-- Search + Filter -->

        <div class="my-tests-toolbar">

            <div class="my-tests-search">

                <?= icon('search', 18) ?>

                <label for="testSearch" class="visually-hidden">Search tests</label>
                <input
                    type="text"
                    id="testSearch"
                    placeholder="Search tests..."
                    autocomplete="off"
                    aria-label="Search tests"
                >

            </div>


            <div class="my-tests-filter">

                <label for="testStatusFilter" class="visually-hidden">Filter tests by status</label>
                <select id="testStatusFilter" aria-label="Filter tests by status">

                    <option value="all">
                        All Tests
                    </option>

                    <option value="upcoming">
                        Upcoming
                    </option>

                    <option value="in_progress">
                        In Progress
                    </option>

                    <option value="completed">
                        Completed
                    </option>

                    <option value="not_started">
                        Not Started
                    </option>

                </select>

            </div>

        </div>


        <!-- Test Cards -->

        <?php if (empty($allTests)): ?>

            <div class="my-tests-empty">

                <div class="my-tests-empty-icon">
                    <?= icon('tray', 28) ?>
                </div>

                <div>

                    <h3>
                        No Tests Available
                    </h3>

                    <p>
                        No assessments have been assigned to you yet.
                    </p>

                </div>

            </div>

        <?php else: ?>

            <div
                class="my-tests-list"
                id="allTestsList"
            >

                <?php foreach ($allTests as $test): ?>

                    <?php
                        $status = getTestDisplayStatus($test);

                        $percentage = calculatePercentage($test);

                        $submissionStatus =
                            $test['submission_status'] ?? null;
                    ?>

                    <div
                        class="my-test-card all-test-card expandable-test-card"
                        data-title="<?= h(strtolower($test['title'])) ?>"
                        data-status="<?= h($status['filter']) ?>"
                    >

                        <!-- Card Top -->

                        <div class="my-test-card-header">

                            <div class="my-test-card-icon">

                                <?= icon('doc.text.fill', 22) ?>

                            </div>

                            <span class="badge <?= h($status['class']) ?>">

                                <?= h($status['label']) ?>

                            </span>

                        </div>


                        <!-- Test Title -->

                        <h3 class="my-test-card-title">

                            <?= h($test['title']) ?>

                        </h3>


                        <!-- Description -->

                        <?php if (!empty($test['description'])): ?>

                            <p class="my-test-card-description">

                                <?= h($test['description']) ?>

                            </p>

                        <?php else: ?>

                            <p class="my-test-card-description muted">

                                No description provided.

                            </p>

                        <?php endif; ?>


                        <!-- Quick Information -->

                        <div class="my-test-card-meta">

                            <span>

                                <?= icon('clock', 15) ?>

                                <?= (int)$test['duration_minutes'] ?> min

                            </span>


                            <span>

                                <?= icon('list', 15) ?>

                                <?= (int)($test['total_questions'] ?? 0) ?>
                                questions

                            </span>


                            <?php if (!empty($test['total_marks'])): ?>

                                <span>

                                    <?= icon('chart', 15) ?>

                                    <?= h($test['total_marks']) ?> marks

                                </span>

                            <?php endif; ?>

                        </div>


                        <!-- Expandable Area -->

                        <div class="my-test-card-details">

                            <div class="my-test-details-grid">

                                <!-- Start -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Start Time
                                    </span>

                                    <strong>

                                        <?= h(
                                            formatTestDate(
                                                $test['start_time']
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                                <!-- End -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        End Time
                                    </span>

                                    <strong>

                                        <?= h(
                                            formatTestDate(
                                                $test['end_time']
                                            )
                                        ) ?>

                                    </strong>

                                </div>


                                <!-- Duration -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Duration
                                    </span>

                                    <strong>

                                        <?= (int)$test['duration_minutes'] ?>
                                        minutes

                                    </strong>

                                </div>


                                <!-- Questions -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Questions
                                    </span>

                                    <strong>

                                        <?= (int)(
                                            $test['total_questions'] ?? 0
                                        ) ?>

                                    </strong>

                                </div>


                                <!-- Marks -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Total Marks
                                    </span>

                                    <strong>

                                        <?= h(
                                            $test['total_marks'] ?? '—'
                                        ) ?>

                                    </strong>

                                </div>


                                <!-- Student Status -->

                                <div class="my-test-detail">

                                    <span class="my-test-detail-label">
                                        Your Status
                                    </span>

                                    <strong>

                                        <?= h($status['label']) ?>

                                    </strong>

                                </div>


                                <!-- Score -->

                                <?php if ($submissionStatus === 'evaluated'): ?>

                                    <div class="my-test-detail">

                                        <span class="my-test-detail-label">
                                            Score
                                        </span>

                                        <strong>

                                            <?= h(
                                                $test['total_marks_obtained']
                                            ) ?>

                                            /

                                            <?= h(
                                                $test['total_marks']
                                            ) ?>

                                        </strong>

                                    </div>


                                    <!-- Percentage -->

                                    <?php if ($percentage !== null): ?>

                                        <div class="my-test-detail">

                                            <span class="my-test-detail-label">
                                                Percentage
                                            </span>

                                            <strong>

                                                <?= $percentage ?>%

                                            </strong>

                                        </div>

                                    <?php endif; ?>

                                <?php endif; ?>


                                <!-- Submitted -->

                                <?php if (!empty($test['submitted_at'])): ?>

                                    <div class="my-test-detail">

                                        <span class="my-test-detail-label">
                                            Submitted
                                        </span>

                                        <strong>

                                            <?= h(
                                                formatTestDate(
                                                    $test['submitted_at']
                                                )
                                            ) ?>

                                        </strong>

                                    </div>

                                <?php endif; ?>

                            </div>


                            <!-- Additional Information -->

                            <div class="my-test-status-message">

                                <?php if ($submissionStatus === 'evaluated'): ?>

                                    <span>
                                        <?= icon('checkmark.circle.fill', 16) ?>

                                        This assessment has been evaluated.
                                    </span>

                                <?php elseif ($submissionStatus === 'submitted'): ?>

                                    <span>
                                        <?= icon('clock', 16) ?>

                                        Your submission is awaiting evaluation.
                                    </span>

                                <?php elseif ($submissionStatus === 'in_progress'): ?>

                                    <span>
                                        <?= icon('clock', 16) ?>

                                        This assessment is currently in progress.
                                    </span>

                                <?php elseif ($status['filter'] === 'upcoming'): ?>

                                    <span>
                                        <?= icon('calendar', 16) ?>

                                        This assessment is scheduled for a
                                        future date.
                                    </span>

                                <?php else: ?>

                                    <span>
                                        <?= icon('info', 16) ?>

                                        Details about this assessment are
                                        available here.
                                    </span>

                                <?php endif; ?>

                            </div>


                            <!-- Full Details Link -->

                            <div class="my-test-details-action">

                                <a
                                    href="my-tests.php?test_id=<?= (int)$test['id'] ?>"
                                    class="my-test-details-link"
                                    onclick="event.stopPropagation();"
                                >

                                    <span>
                                        View Full Details
                                    </span>

                                    <?= icon('arrow.right', 17) ?>

                                </a>

                            </div>

                        </div>


                        <!-- Expand Indicator -->

                        <div class="my-test-expand-indicator">

                            <span>
                                Click to view details
                            </span>

                            <?= icon('chevron.down', 17) ?>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>


            <!-- No Search Results -->

            <div
                class="my-tests-no-results"
                id="noTestResults"
                style="display:none;"
            >

                <div class="my-tests-empty-icon">

                    <?= icon('search', 28) ?>

                </div>

                <h3>
                    No Tests Found
                </h3>

                <p>
                    Try changing your search or filter.
                </p>

            </div>

        <?php endif; ?>

    </section>


    <!--
    |--------------------------------------------------------------------------
    | Information Card
    |--------------------------------------------------------------------------
    -->

    <div class="info-card">

        <div class="info-card-icon">

            <?= icon('info', 20) ?>

        </div>

        <div class="info-card-text">

            Expand any test to view its schedule, duration,
            questions, marks and submission information.
            Use <strong>View Full Details</strong> for the complete
            assessment page.

        </div>

    </div>

</div>


<?php include __DIR__ . '/../../includes/student_footer.php'; ?>


<!--
|--------------------------------------------------------------------------
| JavaScript
|--------------------------------------------------------------------------
-->

<script>

/*
|--------------------------------------------------------------------------
| Sidebar
|--------------------------------------------------------------------------
*/

function toggleSidebar(forceState) {

    const sidebar =
        document.getElementById('sidebar');

    const overlay =
        document.getElementById('sidebarOverlay');

    if (!sidebar || !overlay) return;

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


/*
|--------------------------------------------------------------------------
| Escape closes sidebar
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (e) {

        if (e.key === 'Escape') {

            closeSidebar();

        }

    }
);


/*
|--------------------------------------------------------------------------
| Test Card Expansion
|--------------------------------------------------------------------------
*/

const expandableTestCards =
    document.querySelectorAll(
        '.expandable-test-card'
    );


expandableTestCards.forEach(card => {

    card.addEventListener(
        'click',
        function (event) {

            /*
             * Do not expand when clicking
             * the full-details link.
             */
            if (
                event.target.closest(
                    '.my-test-details-link'
                )
            ) {
                return;
            }


            const isExpanded =
                card.classList.contains(
                    'expanded'
                );


            /*
             * Close every other card.
             */

            expandableTestCards.forEach(
                otherCard => {

                    if (otherCard !== card) {

                        otherCard.classList.remove(
                            'expanded'
                        );

                    }

                }
            );


            /*
             * Toggle clicked card.
             */

            if (!isExpanded) {

                card.classList.add(
                    'expanded'
                );

            } else {

                card.classList.remove(
                    'expanded'
                );

            }

        }
    );

});


/*
|--------------------------------------------------------------------------
| Search + Filter
|--------------------------------------------------------------------------
*/

const searchInput =
    document.getElementById(
        'testSearch'
    );

const statusFilter =
    document.getElementById(
        'testStatusFilter'
    );

const testCards =
    document.querySelectorAll(
        '.all-test-card'
    );

const noResults =
    document.getElementById(
        'noTestResults'
    );


function filterTests() {

    const searchTerm =
        searchInput
            ? searchInput.value
                .trim()
                .toLowerCase()
            : '';

    const selectedStatus =
        statusFilter
            ? statusFilter.value
            : 'all';

    let visibleCount = 0;


    testCards.forEach(card => {

        const title =
            card.dataset.title || '';

        const status =
            card.dataset.status || '';


        const matchesSearch =
            title.includes(
                searchTerm
            );


        const matchesStatus =
            selectedStatus === 'all' ||
            status === selectedStatus;


        if (
            matchesSearch &&
            matchesStatus
        ) {

            card.style.display = '';

            visibleCount++;

        } else {

            card.style.display = 'none';

            card.classList.remove(
                'expanded'
            );

        }

    });


    if (noResults) {

        noResults.style.display =
            visibleCount === 0
                ? 'flex'
                : 'none';

    }

}


if (searchInput) {

    searchInput.addEventListener(
        'input',
        filterTests
    );

}


if (statusFilter) {

    statusFilter.addEventListener(
        'change',
        filterTests
    );

}





/*
|--------------------------------------------------------------------------
| Focus Trap for Mobile Sidebar
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function (e) {

        if (e.key !== 'Tab') {
            return;
        }

        const sidebar =
            document.getElementById(
                'sidebar'
            );

        if (
            !sidebar ||
            !sidebar.classList.contains(
                'open'
            )
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
            document.activeElement ===
                focusable[
                    focusable.length - 1
                ]
        ) {

            e.preventDefault();

            focusable[0].focus();

        }

    }
);


</script>

</body>

</html>