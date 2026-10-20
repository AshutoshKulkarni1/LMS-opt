<?php
/**
 * OTP Verification Page.
 * Student verification is bound securely to the server-side session.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
startSession();

// If already verified + logged in, redirect to dashboard
if (isStudent()) {
    redirect('/student/dashboard');
}

// Ensure verification challenge is bound to server session
$pending = $_SESSION['pending_verification'] ?? null;
if (!$pending || empty($pending['student_id']) || empty($pending['email'])) {
    // No active verification in session — redirect to login
    redirect('/login');
}

$studentId = (int)$pending['student_id'];
$email     = (string)$pending['email'];
$error     = '';
$success   = '';
$warning   = $_SESSION['otp_warning'] ?? '';
unset($_SESSION['otp_warning']);

// Check student record in database
$studentName = $pending['name'] ?? '';
try {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT name, otp_hash FROM unverified_students WHERE id = ?");
    $stmt->execute([$studentId]);
    $unverified = $stmt->fetch();

    if ($unverified) {
        if (empty($studentName)) {
            $studentName = $unverified['name'];
            $_SESSION['pending_verification']['name'] = $studentName;
        }
    } else {
        // Check if student was already verified
        $stmt = $pdo->prepare("SELECT name FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $verified = $stmt->fetch();
        if ($verified) {
            unset($_SESSION['pending_verification']);
            $studentName = $verified['name'];
            $success = 'Email already verified! You can sign in now.';
        } else {
            unset($_SESSION['pending_verification']);
            redirect('/signup');
        }
    }
} catch (Exception $e) {
    error_log('Error checking pending student: ' . $e->getMessage());
}

// Calculate cooldown
$lastSentAt = (int)($_SESSION['pending_verification']['last_sent_at'] ?? 0);
$cooldownRemaining = max(0, 60 - (time() - $lastSentAt));

// Handle OTP verification / resend POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } elseif ($_POST['action'] === 'verify') {
        $attempts = (int)($_SESSION['pending_verification']['attempts'] ?? 0);

        if ($attempts >= 5) {
            invalidateStudentOtp($studentId);
            $error = 'Maximum verification attempts (5) reached. This OTP has been invalidated. Please request a new OTP.';
        } else {
            $otp = trim($_POST['otp'] ?? '');
            if (!preg_match('/^\d{6}$/', $otp)) {
                $error = 'Please enter a valid 6-digit OTP code.';
            } else {
                $_SESSION['pending_verification']['attempts'] = $attempts + 1;
                $newAttempts = $_SESSION['pending_verification']['attempts'];

                $result = verifyStudentOtp($studentId, $otp);
                if ($result['success']) {
                    unset($_SESSION['pending_verification']);
                    session_regenerate_id(true);
                    $success = 'Email verified successfully! You can now sign in.';
                } else {
                    if ($newAttempts >= 5) {
                        invalidateStudentOtp($studentId);
                        $error = 'Maximum attempts reached. This OTP has been invalidated. Please request a new OTP.';
                    } else {
                        $remaining = 5 - $newAttempts;
                        $error = ($result['error'] ?? 'Invalid OTP.') . " ($remaining attempt" . ($remaining === 1 ? '' : 's') . " remaining)";
                    }
                }
            }
        }
    } elseif ($_POST['action'] === 'resend') {
        if ($cooldownRemaining > 0) {
            $error = "Please wait {$cooldownRemaining} seconds before requesting a new OTP.";
        } else {
            $result = resendStudentOtp($studentId);
            if ($result['success']) {
                $_SESSION['pending_verification']['last_sent_at'] = time();
                $_SESSION['pending_verification']['attempts'] = 0; // Reset attempts for new challenge
                $cooldownRemaining = 60;
                $success = 'A new OTP has been sent to your email.';
            } else {
                $error = $result['error'] ?? 'Failed to resend OTP. Please try again.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email — Test Platform</title>
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
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/student.css">
</head>
<body class="auth-page">

    <!-- Hero Section -->
    <div class="auth-hero">
        <div class="hero-logo">T</div>
        <div class="hero-text">
            <strong>Verify Your Email</strong>
            <span>One more step to activate your account and start testing.</span>
        </div>
        <div class="hero-features">
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Secure email verification
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Instant account activation
            </div>
            <div class="hero-feature">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                Start your tests right away
            </div>
        </div>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <div class="logo-mark">T</div>
        <h1>Verify Your Email</h1>
        <p class="subtitle">
            <?php if ($studentName): ?>
                Hi <strong><?= h($studentName) ?></strong>,
            <?php endif; ?>
            we sent a 6-digit code to <strong><?= h($email) ?></strong>
        </p>

        <?php if ($warning && !$error): ?>
            <div class="auth-alert error" style="background: rgba(234, 179, 8, 0.1); border-color: rgba(234, 179, 8, 0.3); color: #eab308;">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($warning) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="auth-alert error">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 1a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm0 9.5a.75.75 0 1 1 0 1.5.75.75 0 0 1 0-1.5zM10 6a.5.5 0 0 1 .5.5v4a.5.5 0 0 1-1 0v-4A.5.5 0 0 1 10 6z"/></svg>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="auth-alert success">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 0 0-1.4 0L8 12.6 4.7 9.3a1 1 0 0 0-1.4 1.4l4 4a1 1 0 0 0 1.4 0l8-8a1 1 0 0 0 0-1.4z"/></svg>
                <span><?= h($success) ?><br><a href="<?= BASE_URL ?>/login" class="text-accent" style="text-decoration:underline;margin-top:4px;display:inline-block;">Sign in</a></span>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="otpForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="verify">

            <div class="form-group">
                <label for="otp">Enter 6-digit OTP</label>
                <input class="form-input otp-input" type="text" id="otp" name="otp"
                       placeholder="000000" maxlength="6" inputmode="numeric"
                       pattern="[0-9]{6}" autocomplete="one-time-code"
                       value="<?= h($_POST['otp'] ?? '') ?>" required autofocus>
            </div>

            <button type="submit" class="btn btn-primary w-full">
                Verify Email
            </button>
        </form>

        <div class="auth-footer" style="display:flex;justify-content:space-between;align-items:center;">
            <form method="POST" id="resendForm" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="resend">
                <button type="submit" id="resendBtn" class="btn btn-sm btn-ghost text-accent" style="border:none;" <?= $cooldownRemaining > 0 ? 'disabled' : '' ?>>
                    <?= $cooldownRemaining > 0 ? "Resend OTP ({$cooldownRemaining}s)" : 'Resend OTP' ?>
                </button>
            </form>
            <span class="otp-timer-text">OTP expires in 10 minutes</span>
        </div>

        <div class="auth-footer" style="margin-top:var(--space-4);">
            <a href="<?= BASE_URL ?>/signup" class="auth-footer-link">← Back to registration</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        var cooldown = <?= (int)$cooldownRemaining ?>;
        var btn = document.getElementById('resendBtn');
        if (cooldown > 0 && btn) {
            var interval = setInterval(function() {
                cooldown--;
                if (cooldown <= 0) {
                    clearInterval(interval);
                    btn.disabled = false;
                    btn.textContent = 'Resend OTP';
                } else {
                    btn.textContent = 'Resend OTP (' + cooldown + 's)';
                }
            }, 1000);
        }
    })();
    </script>
</body>
</html>
