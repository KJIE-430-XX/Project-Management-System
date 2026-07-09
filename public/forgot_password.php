<?php
session_start();
include 'db.php';
include 'csrf.php';

// Handle "Start over" reset before any logic runs
if (isset($_GET['reset'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_otp'], $_SESSION['fp_otp_expiry']);
    header("Location: forgot_password.php");
    exit;
}

$message = '';
$message_type = 'error';

// Initialize step if not set
if (!isset($_SESSION['fp_step'])) {
    $_SESSION['fp_step'] = 1;
}

$current_step = $_SESSION['fp_step'];

// ─────────────────────────────────────────────
// Handle POST requests
// ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security validation failed. Please try again.";
    } else {

        $posted_step = (int)($_POST['step'] ?? 0);

        // ── STEP 1: Validate email ──────────────────
        if ($posted_step === 1) {
            $email = trim($_POST['email'] ?? '');

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = "Please enter a valid email address.";
            } else {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if ($stmt === false) {
                    $message = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 0) {
                        $message = "No account found with that email address.";
                        error_log("ForgotPassword: Email not found - " . htmlspecialchars($email));
                    } else {
                        // Generate a mock 6-digit OTP
                        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                        $_SESSION['fp_email']      = $email;
                        $_SESSION['fp_otp']        = $otp;
                        $_SESSION['fp_otp_expiry'] = time() + 120; // 2 minutes
                        $_SESSION['fp_step']       = 2;
                        $current_step              = 2;

                        $message      = "otp_sent";
                        $message_type = 'info';
                        error_log("ForgotPassword: OTP generated for " . htmlspecialchars($email) . " (mock: $otp)");
                    }
                    $stmt->close();
                }
            }
        }

        // ── STEP 2: Verify OTP ──────────────────────
        elseif ($posted_step === 2 && $current_step === 2) {
            $entered_otp = trim($_POST['otp'] ?? '');

            if (empty($entered_otp)) {
                $message = "Please enter the OTP.";
            } elseif (time() > ($_SESSION['fp_otp_expiry'] ?? 0)) {
                $message = "Your OTP has expired. Please start over.";
                // Reset session to step 1
                unset($_SESSION['fp_email'], $_SESSION['fp_otp'], $_SESSION['fp_otp_expiry']);
                $_SESSION['fp_step'] = 1;
                $current_step = 1;
            } elseif ($entered_otp !== ($_SESSION['fp_otp'] ?? '')) {
                $message = "Incorrect OTP. Please try again.";
            } else {
                // OTP correct — advance to step 3
                $_SESSION['fp_step'] = 3;
                $current_step        = 3;
                $message_type        = 'success';
            }
        }

        // ── STEP 3: Reset password ──────────────────
        elseif ($posted_step === 3 && $current_step === 3) {
            $new_password     = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($new_password) || empty($confirm_password)) {
                $message = "Both password fields are required.";
            } elseif (strlen($new_password) < 6) {
                $message = "Password must be at least 6 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $message = "Passwords do not match.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $email  = $_SESSION['fp_email'] ?? '';

                $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                if ($stmt === false) {
                    $message = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ss", $hashed, $email);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        // Clear all forgot-password session data
                        unset($_SESSION['fp_step'], $_SESSION['fp_email'], $_SESSION['fp_otp'], $_SESSION['fp_otp_expiry']);
                        $stmt->close();
                        error_log("ForgotPassword: Password reset successful for " . htmlspecialchars($email));
                        header("Location: login.php?reset=success");
                        exit;
                    } else {
                        $message = "Failed to update password. Please try again.";
                        error_log("ForgotPassword: Password update failed for " . htmlspecialchars($email));
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// ─────────────────────────────────────────────
// Helpers for step indicator rendering
// ─────────────────────────────────────────────
function stepClass(int $step, int $current): string {
    if ($step < $current) return 'done';
    if ($step === $current) return 'active';
    return '';
}

function lineClass(int $afterStep, int $current): string {
    return $afterStep < $current ? 'done' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – ProManage</title>
    <meta name="description" content="Reset your ProManage account password securely.">
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .auth-container {
            max-width: 420px;
        }
        .step-label {
            text-align: center;
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 18px;
            letter-spacing: 0.3px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #6B7280;
            font-size: 13px;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
            width: auto;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #A855F7;
            background: none;
        }
        .email-display {
            background-color: #1F2937;
            border: 1px solid #374151;
            border-radius: 6px;
            padding: 10px 14px;
            color: #9CA3AF;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .email-display strong {
            color: #E5E7EB;
        }
        .countdown {
            font-size: 11px;
            color: #6B7280;
            text-align: right;
            margin-top: 4px;
        }
        .countdown span {
            color: #F59E0B;
            font-weight: 600;
        }
        .resend-link {
            color: #A855F7;
            text-decoration: none;
            font-size: 13px;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            width: auto;
            font-weight: 500;
        }
        .resend-link:hover {
            background: none;
            color: #C084FC;
        }
        /* Password eye-toggle */
        .pw-input-wrap {
            position: relative;
        }
        .pw-input-wrap input {
            width: 100%;
            padding-right: 40px;
            box-sizing: border-box;
        }
        .pw-eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #64748B;
            padding: 0;
            font-size: 16px;
            line-height: 1;
        }
        .pw-eye-btn:hover { color: #A855F7; }
    </style>
</head>
<body>
    <div class="auth-container">
        <!-- Brand -->
        <div class="system-title">
            <span class="pro">Pro</span><span class="manage">Manage</span>
        </div>

        <h1>Reset Password</h1>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot <?php echo stepClass(1, $current_step); ?>" title="Verify Email">1</div>
            <div class="step-line <?php echo lineClass(1, $current_step); ?>"></div>
            <div class="step-dot <?php echo stepClass(2, $current_step); ?>" title="Enter OTP">2</div>
            <div class="step-line <?php echo lineClass(2, $current_step); ?>"></div>
            <div class="step-dot <?php echo stepClass(3, $current_step); ?>" title="New Password">3</div>
        </div>
        <div class="step-label">
            <?php
            $labels = [1 => 'Verify your email', 2 => 'Enter OTP code', 3 => 'Create new password'];
            echo htmlspecialchars($labels[$current_step] ?? '');
            ?>
        </div>

        <!-- Messages -->
        <?php if ($message && $message !== 'otp_sent'): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- ══════════════════════════════════════ -->
        <!-- STEP 1 – Email Input                  -->
        <!-- ══════════════════════════════════════ -->
        <?php if ($current_step === 1): ?>
            <form method="POST" id="step1-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="step" value="1">

                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="Enter your registered email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" id="send-otp-btn">Send OTP Code</button>
            </form>

            <div class="link">
                <a href="login.php">← Back to Login</a>
            </div>

        <!-- ══════════════════════════════════════ -->
        <!-- STEP 2 – OTP Verification             -->
        <!-- ══════════════════════════════════════ -->
        <?php elseif ($current_step === 2): ?>

            <!-- Mock OTP notice (demo only) -->
            <div class="message info">
                📧 A verification code has been sent to your email.
                <br>
                <strong>(Demo mode — your OTP is:)</strong>
                <span class="otp-display"><?php echo htmlspecialchars($_SESSION['fp_otp'] ?? '------'); ?></span>
            </div>

            <!-- Email being verified -->
            <div class="email-display">
                <span>📮</span>
                <span>Sending to: <strong><?php echo htmlspecialchars($_SESSION['fp_email'] ?? ''); ?></strong></span>
            </div>

            <form method="POST" id="step2-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="step" value="2">

                <div class="form-group">
                    <label for="otp">Enter OTP Code:</label>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        class="otp-input"
                        maxlength="6"
                        placeholder="000000"
                        autocomplete="one-time-code"
                        required
                        autofocus
                    >
                    <div class="countdown" id="countdown-display">
                        Code expires in: <span id="timer">02:00</span>
                    </div>
                </div>

                <button type="submit" id="verify-otp-btn">Verify OTP</button>
            </form>

            <div class="link" style="margin-top: 12px;">
                Wrong email? <a href="forgot_password.php?reset=1" class="resend-link">Start over</a>
            </div>

        <!-- ══════════════════════════════════════ -->
        <!-- STEP 3 – New Password                 -->
        <!-- ══════════════════════════════════════ -->
        <?php elseif ($current_step === 3): ?>

            <div class="email-display">
                <span>✅</span>
                <span>OTP verified for: <strong><?php echo htmlspecialchars($_SESSION['fp_email'] ?? ''); ?></strong></span>
            </div>

            <form method="POST" id="step3-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <input type="hidden" name="step" value="3">

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <div class="pw-input-wrap">
                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            placeholder="At least 6 characters"
                            required
                            autofocus
                        >
                        <button type="button" class="pw-eye-btn" onclick="togglePw('new_password', this)" tabindex="-1" aria-label="Toggle password visibility">&#128065;</button>
                    </div>
                    <span class="hint">Minimum 6 characters.</span>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <div class="pw-input-wrap">
                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            placeholder="Re-enter your new password"
                            required
                        >
                        <button type="button" class="pw-eye-btn" onclick="togglePw('confirm_password', this)" tabindex="-1" aria-label="Toggle password visibility">&#128065;</button>
                    </div>
                    <span class="hint" id="match-hint" style="display:none; color:#F87171;">Passwords do not match.</span>
                </div>

                <button type="submit" id="reset-btn">Reset Password</button>
            </form>

        <?php endif; ?>
    </div>

    <script>
    // ── OTP countdown timer (Step 2) ───────────────
    (function () {
        const timerEl = document.getElementById('timer');
        if (!timerEl) return;

        const expiry = <?php echo (int)($_SESSION['fp_otp_expiry'] ?? (time() + 600)); ?>;

        function updateTimer() {
            const remaining = expiry - Math.floor(Date.now() / 1000);
            if (remaining <= 0) {
                timerEl.textContent = '00:00';
                timerEl.style.color = '#EF4444';
                clearInterval(interval);
                return;
            }
            const m = String(Math.floor(remaining / 60)).padStart(2, '0');
            const s = String(remaining % 60).padStart(2, '0');
            timerEl.textContent = m + ':' + s;
            if (remaining < 60) timerEl.style.color = '#EF4444';
        }
        updateTimer();
        const interval = setInterval(updateTimer, 1000);
    })();

    // ── OTP input: digits only ─────────────────────
    const otpInput = document.getElementById('otp');
    if (otpInput) {
        otpInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    // ── Password match hint (Step 3) ───────────────
    const newPwd  = document.getElementById('new_password');
    const confPwd = document.getElementById('confirm_password');
    const hint    = document.getElementById('match-hint');

    if (confPwd && newPwd && hint) {
        confPwd.addEventListener('input', function () {
            if (this.value.length === 0) {
                hint.style.display = 'none';
            } else if (this.value !== newPwd.value) {
                hint.style.display = 'block';
            } else {
                hint.style.display = 'none';
            }
        });
    }

    // ── Prevent double-submit ──────────────────────
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('button[type="submit"]');
            if (btn && !btn.classList.contains('resend-link')) {
                btn.disabled = true;
                btn.textContent = 'Please wait…';
            }
        });
    });

    // ── Password eye-toggle ────────────────────────
    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden ? '&#128683;' : '&#128065;';
    }
    </script>
</body>
</html>
