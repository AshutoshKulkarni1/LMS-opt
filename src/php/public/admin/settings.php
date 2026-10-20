<?php
$pageTitle = 'Settings & Database';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
startSession();
requireAdmin();

$pdo = getDB();
$message = '';
$error = '';
$adminRole = $_SESSION['admin_role'] ?? 'admin';
$isSuperAdmin = in_array($adminRole, ['super_admin', 'platform_admin'], true);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'load_seed' || $action === 'reset_seed') {
        if (!$isSuperAdmin) {
            $error = 'Only Super Admins or Platform Admins can manage sample seed data.';
        } else {
            $clearFirst = ($action === 'reset_seed');
            $seederFile = __DIR__ . '/../../../../sql/seed_test_data.php';

            if (!file_exists($seederFile)) {
                $error = 'Seed script file not found at: ' . $seederFile;
            } else {
                try {
                    require_once $seederFile;
                    if (!function_exists('runDatabaseSeeder')) {
                        throw new Exception('Seeder function runDatabaseSeeder() is missing.');
                    }
                    $stats = runDatabaseSeeder($pdo, $clearFirst);
                    $verb = $clearFirst ? 'Reset and re-seeded' : 'Loaded';
                    $message = "{$verb} sample data successfully! Created {$stats['colleges']} colleges, {$stats['batches']} batches, {$stats['students']} students, {$stats['tests']} tests, and {$stats['submissions']} submissions.";
                } catch (Throwable $e) {
                    $error = 'Seeding error: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch live database statistics
$stats = [
    'colleges'    => 0,
    'streams'     => 0,
    'batches'     => 0,
    'students'    => 0,
    'tests'       => 0,
    'questions'   => 0,
    'submissions' => 0,
];

try {
    $stats['colleges']    = (int)$pdo->query("SELECT COUNT(*) FROM colleges WHERE status != 'archived'")->fetchColumn();
    $stats['streams']     = (int)$pdo->query("SELECT COUNT(*) FROM college_streams")->fetchColumn();
    $stats['batches']     = (int)$pdo->query("SELECT COUNT(*) FROM batches WHERE status = 'active'")->fetchColumn();
    $stats['students']    = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $stats['tests']       = (int)$pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();
    $stats['questions']   = (int)$pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $stats['submissions'] = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
} catch (Throwable $e) {
    // Non-fatal if a table is missing
}

require_once __DIR__ . '/../../includes/admin_header.php';
?>

<div class="dashboard-header" style="margin-bottom:var(--space-4);">
    <div class="dashboard-header-left">
        <h1>Platform Settings</h1>
        <p class="dashboard-subtitle">Manage environment options, database records, and sample test data</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success" style="margin-bottom:var(--space-4);">
        <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
        <span><?= h($message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom:var(--space-4);">
        <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
        <span><?= h($error) ?></span>
    </div>
<?php endif; ?>

<!-- Top Metrics -->
<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:var(--space-3);margin-bottom:var(--space-5);">
    <div class="card-flat" style="padding:var(--space-4);text-align:center;">
        <div style="font-size:var(--fs-12);color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Colleges</div>
        <div style="font-size:var(--fs-28);font-weight:700;color:var(--accent);"><?= $stats['colleges'] ?></div>
        <div style="font-size:var(--fs-12);color:var(--text-muted);margin-top:2px;"><?= $stats['streams'] ?> Streams</div>
    </div>
    <div class="card-flat" style="padding:var(--space-4);text-align:center;">
        <div style="font-size:var(--fs-12);color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Batches</div>
        <div style="font-size:var(--fs-28);font-weight:700;color:var(--accent);"><?= $stats['batches'] ?></div>
        <div style="font-size:var(--fs-12);color:var(--text-muted);margin-top:2px;">Active groups</div>
    </div>
    <div class="card-flat" style="padding:var(--space-4);text-align:center;">
        <div style="font-size:var(--fs-12);color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Students</div>
        <div style="font-size:var(--fs-28);font-weight:700;color:var(--accent);"><?= $stats['students'] ?></div>
        <div style="font-size:var(--fs-12);color:var(--text-muted);margin-top:2px;">Registered users</div>
    </div>
    <div class="card-flat" style="padding:var(--space-4);text-align:center;">
        <div style="font-size:var(--fs-12);color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px;">Assessments</div>
        <div style="font-size:var(--fs-28);font-weight:700;color:var(--accent);"><?= $stats['tests'] ?></div>
        <div style="font-size:var(--fs-12);color:var(--text-muted);margin-top:2px;"><?= $stats['submissions'] ?> Submissions</div>
    </div>
</div>

<!-- Database & Seed Data Section -->
<div class="card-flat" style="padding:var(--space-5);margin-bottom:var(--space-5);">
    <div style="display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-3);">
        <div style="width:36px;height:36px;border-radius:var(--radius-md);background:rgba(99,102,241,0.12);display:flex;align-items:center;justify-content:center;color:var(--accent);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        </div>
        <div>
            <h2 style="font-size:var(--fs-18);font-weight:600;margin:0;">Database & Demo Seed Data</h2>
            <p style="font-size:var(--fs-13);color:var(--text-secondary);margin:2px 0 0 0;">Populate or refresh initial demo colleges, courses, batches, students, tests, questions, and submissions</p>
        </div>
    </div>

    <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:var(--space-4);margin-bottom:var(--space-4);">
        <h3 style="font-size:var(--fs-14);font-weight:600;margin:0 0 var(--space-2) 0;">What gets generated:</h3>
        <ul style="font-size:var(--fs-13);color:var(--text-secondary);margin:0;padding-left:var(--space-4);line-height:1.7;">
            <li><strong>5 Colleges:</strong> Sri Ramachandra (SRIT), St. Joseph's (SJCE), BMSCE, PESIT, RVCE with codes, addresses, and NAAC ratings</li>
            <li><strong>5 Streams & Batches:</strong> Linked for the College Creation Wizard, College Dashboard, and Signup dropdowns</li>
            <li><strong>50 Students:</strong> 10 students per batch across CSE, ISE, ECE, ME, and AIML branches</li>
            <li><strong>5 Active Assessments:</strong> With 25 multiple-choice questions (5 per test)</li>
            <li><strong>50 Submissions & PCI Analytics:</strong> Graded mock results with answer records and PCI scores for instant dashboard reporting</li>
        </ul>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:var(--space-3);align-items:center;">
        <form method="POST" style="margin:0;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="load_seed">
            <button type="submit" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:var(--space-2);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                Load Sample Seed Data
            </button>
        </form>

        <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ Warning: Reset & Re-Seed will clear existing test platform tables (colleges, batches, students, tests) and replace them with fresh seed data. Admin accounts are preserved. Continue?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_seed">
            <button type="submit" class="btn btn-secondary" style="display:inline-flex;align-items:center;gap:var(--space-2);color:var(--danger, #ef4444);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M3 21v-5h5"/></svg>
                Reset &amp; Re-Seed Database
            </button>
        </form>
    </div>
</div>

<!-- Student Logins Quick Reference -->
<div class="card-flat" style="padding:var(--space-5);margin-bottom:var(--space-5);">
    <h2 style="font-size:var(--fs-16);font-weight:600;margin:0 0 var(--space-3) 0;">Seed Credentials Reference</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:var(--space-3);">
        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:var(--space-3);">
            <div style="font-weight:600;font-size:var(--fs-13);color:var(--text-primary);margin-bottom:4px;">🔑 Administrator Account</div>
            <div style="font-size:var(--fs-12);color:var(--text-secondary);font-family:monospace;">admin@testplatform.com</div>
            <div style="font-size:var(--fs-12);color:var(--text-secondary);margin-top:4px;">Password: <code style="color:var(--accent);">admin123</code></div>
        </div>

        <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:var(--radius-md);padding:var(--space-3);">
            <div style="font-weight:600;font-size:var(--fs-13);color:var(--text-primary);margin-bottom:4px;">👨‍🎓 50 Sample Students</div>
            <div style="font-size:var(--fs-12);color:var(--text-secondary);font-family:monospace;">student1@srit.edu ... student50@rvce.edu</div>
            <div style="font-size:var(--fs-12);color:var(--text-secondary);margin-top:4px;">Password for all: <code style="color:var(--accent);">student123</code></div>
        </div>
    </div>
</div>

<!-- System & Connection Details -->
<div class="card-flat" style="padding:var(--space-5);">
    <h2 style="font-size:var(--fs-16);font-weight:600;margin:0 0 var(--space-3) 0;">Database Connection</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:var(--space-3);font-size:var(--fs-13);">
        <div>
            <span style="color:var(--text-muted);display:block;font-size:var(--fs-11);text-transform:uppercase;">Host</span>
            <span style="font-family:monospace;"><?= h(DB_HOST) ?></span>
        </div>
        <div>
            <span style="color:var(--text-muted);display:block;font-size:var(--fs-11);text-transform:uppercase;">Port</span>
            <span style="font-family:monospace;"><?= h((string)DB_PORT) ?></span>
        </div>
        <div>
            <span style="color:var(--text-muted);display:block;font-size:var(--fs-11);text-transform:uppercase;">Database Name</span>
            <span style="font-family:monospace;"><?= h(DB_NAME) ?></span>
        </div>
        <div>
            <span style="color:var(--text-muted);display:block;font-size:var(--fs-11);text-transform:uppercase;">Auto-Seed on Startup</span>
            <span><?= (getenv('AUTO_SEED') === 'true' || getenv('AUTO_SEED') === '1') ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-neutral">Disabled (Set AUTO_SEED=true)</span>' ?></span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/admin_footer.php'; ?>
