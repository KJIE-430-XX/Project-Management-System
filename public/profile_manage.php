<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
require_once 'csrf.php';

$user_id = $_SESSION['user_id'];
$user = null;
$message = '';
$message_type = 'success';
$updated_name = '';
$updated_email = '';
$updated_username = '';
$profile_error = '';

if ($conn !== null) {
    $stmt = $conn->prepare("SELECT id, name, email, username, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if ($conn === null) {
    $profile_error = $db_error;
} elseif (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
} else {
    $updated_name = $user['name'];
    $updated_email = $user['email'];
    $updated_username = $user['username'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        $updated_name = trim($_POST['name'] ?? '');
        $updated_email = trim($_POST['email'] ?? '');
        $updated_username = trim($_POST['username'] ?? '');
        $current_password = $_POST['current_password'] ?? '';

        if (!validateCSRFToken($csrf_token)) {
            $message = "Security validation failed. Please try again.";
            $message_type = 'error';
        } elseif (empty($updated_name) || empty($updated_email) || empty($updated_username)) {
            $message = "Name, email, and username are required.";
            $message_type = 'error';
        } elseif (!filter_var($updated_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Please enter a valid email address.";
            $message_type = 'error';
        } else {
            $email_changed = $updated_email !== $user['email'];
            $username_changed = $updated_username !== $user['username'];
            $requires_password = $email_changed || $username_changed;

            if (false) {
                // placeholder — password modal handles collection client-side
            } else {
                if ($requires_password) {
                    $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $verify_stmt->bind_param("i", $user_id);
                    $verify_stmt->execute();
                    $verify_result = $verify_stmt->get_result();
                    $stored_user = $verify_result->fetch_assoc();
                    $verify_stmt->close();

                    if (!$stored_user || !password_verify($current_password, $stored_user['password_hash'])) {
                        $message = "Current password is incorrect.";
                        $message_type = 'error';
                    }
                }

                if (empty($message) && ($email_changed || $username_changed)) {
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?");
                    $check_stmt->bind_param("ssi", $updated_email, $updated_username, $user_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();

                    if ($check_result->num_rows > 0) {
                        $message = "Email or username is already in use.";
                        $message_type = 'error';
                    }
                    $check_stmt->close();
                }

                if (empty($message)) {
                    $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?");
                    $update_stmt->bind_param("sssi", $updated_name, $updated_email, $updated_username, $user_id);

                    if ($update_stmt->execute()) {
                        $_SESSION['success'] = "Profile updated successfully.";
                        $user['name'] = $updated_name;
                        $user['email'] = $updated_email;
                        $user['username'] = $updated_username;
                        if ($username_changed) {
                            $_SESSION['username'] = $updated_username;
                        }
                        header("Location: profile.php");
                        exit;
                    } else {
                        $message = "Failed to save changes. Please try again.";
                        $message_type = 'error';
                    }

                    $update_stmt->close();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - ProManage</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .profile-card {
            background-color: #161B26;
            border: 1px solid rgba(168, 85, 247, 0.15);
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        }

        .profile-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #F8FAFC;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #E2E8F0;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            max-width: 320px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            background-color: #0B0F19;
            color: #F8FAFC;
            box-sizing: border-box;
        }

        .submit-btn {
            margin-top: 8px;
            background-color: #A855F7;
            color: #FFFFFF;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .submit-btn:hover {
            background-color: #C084FC;
        }

        .message {
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 14px;
            font-weight: 600;
        }

        .message.error {
            background-color: rgba(220, 38, 38, 0.15);
            color: #FCA5A5;
        }

        .message.success {
            background-color: rgba(34, 197, 94, 0.15);
            color: #86EFAC;
        }

        .profile-back {
            display: inline-block;
            margin-top: 20px;
            background-color: #A855F7;
            color: #FFFFFF;
            padding: 10px 18px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }

        .profile-back:hover {
            background-color: #C084FC;
        }
        /* Password confirmation modal */
        .pw-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .pw-modal-overlay.active {
            display: flex;
        }
        .pw-modal {
            background: #161B26;
            border: 1px solid rgba(168, 85, 247, 0.3);
            border-radius: 14px;
            padding: 32px 28px;
            width: 100%;
            max-width: 360px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
            animation: modalSlideIn 0.2s ease;
        }
        @keyframes modalSlideIn {
            from { transform: translateY(-16px); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
        .pw-modal h3 {
            margin: 0 0 8px;
            color: #F8FAFC;
            font-size: 18px;
        }
        .pw-modal p {
            margin: 0 0 18px;
            color: #94A3B8;
            font-size: 14px;
        }
        .pw-modal input[type=password] {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(148, 163, 184, 0.3);
            background-color: #0B0F19;
            color: #F8FAFC;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 6px;
        }
        .pw-modal input[type=password]:focus {
            outline: none;
            border-color: #A855F7;
        }
        .pw-modal-error {
            color: #FCA5A5;
            font-size: 13px;
            min-height: 18px;
            margin-bottom: 14px;
        }
        .pw-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        .pw-modal-actions .btn-cancel {
            background: transparent;
            border: 1px solid rgba(148,163,184,0.3);
            color: #94A3B8;
            padding: 9px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .pw-modal-actions .btn-cancel:hover {
            background: rgba(148,163,184,0.1);
        }
        .pw-modal-actions .btn-confirm {
            background: #A855F7;
            border: none;
            color: #fff;
            padding: 9px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .pw-modal-actions .btn-confirm:hover {
            background: #C084FC;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>
            <p class="dashboard-subtitle">Update your profile information</p>
            <div class="header-actions">
                <a href="profile.php" class="index-btn">← Back to Profile</a>
            </div>
        </div>

        <div class="profile-card">
            <h2>Edit Profile</h2>
            <?php if (!empty($profile_error)): ?>
                <p><?php echo htmlspecialchars($profile_error); ?></p>
            <?php else: ?>
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                    <div class="form-group">
                        <label for="name">Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($updated_name); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($updated_email); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($updated_username); ?>" required>
                    </div>
                    <input type="hidden" id="current_password" name="current_password" value="">
                    <button type="submit" class="submit-btn" id="saveChangesBtn">Save Changes</button>
                </form>

                <!-- Password Confirmation Modal -->
                <div class="pw-modal-overlay" id="pwModalOverlay">
                    <div class="pw-modal" role="dialog" aria-modal="true" aria-labelledby="pwModalTitle">
                        <h3 id="pwModalTitle">🔒 Confirm Your Identity</h3>
                        <p>You're changing your email or username. Please enter your current password to continue.</p>
                        <div style="position: relative;">
                            <input type="password" id="pwModalInput" placeholder="Current password" autocomplete="current-password" style="padding-right: 40px;">
                            <button type="button" onclick="togglePwModal()" tabindex="-1" aria-label="Toggle password visibility" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#64748B;padding:0;font-size:16px;line-height:1;" id="pwModalEyeBtn">&#128065;</button>
                        </div>
                        <div class="pw-modal-error" id="pwModalError"></div>
                        <div class="pw-modal-actions">
                            <button type="button" class="btn-cancel" id="pwModalCancel">Cancel</button>
                            <button type="button" class="btn-confirm" id="pwModalConfirm">Confirm &amp; Save</button>
                        </div>
                    </div>
                </div>

                <script>
                (function () {
                    const originalEmail    = <?php echo json_encode($user['email']); ?>;
                    const originalUsername = <?php echo json_encode($user['username']); ?>;

                    const form        = document.querySelector('form');
                    const emailInput  = document.getElementById('email');
                    const userInput   = document.getElementById('username');
                    const pwHidden    = document.getElementById('current_password');

                    const overlay     = document.getElementById('pwModalOverlay');
                    const pwInput     = document.getElementById('pwModalInput');
                    const pwError     = document.getElementById('pwModalError');
                    const btnCancel   = document.getElementById('pwModalCancel');
                    const btnConfirm  = document.getElementById('pwModalConfirm');

                    let pendingSubmit = false;

                    form.addEventListener('submit', function (e) {
                        if (pendingSubmit) return; // password already collected — let it through

                        const emailChanged    = emailInput.value.trim() !== originalEmail;
                        const usernameChanged = userInput.value.trim()  !== originalUsername;

                        if (emailChanged || usernameChanged) {
                            e.preventDefault();
                            openModal();
                        }
                    });

                    function openModal() {
                        pwInput.value = '';
                        pwError.textContent = '';
                        overlay.classList.add('active');
                        pwInput.focus();
                    }

                    function closeModal() {
                        overlay.classList.remove('active');
                        pwHidden.value = '';
                    }

                    btnCancel.addEventListener('click', closeModal);

                    overlay.addEventListener('click', function (e) {
                        if (e.target === overlay) closeModal();
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape' && overlay.classList.contains('active')) closeModal();
                        if (e.key === 'Enter'  && overlay.classList.contains('active')) confirmAndSubmit();
                    });

                    btnConfirm.addEventListener('click', confirmAndSubmit);

                    function confirmAndSubmit() {
                        const pw = pwInput.value;
                        if (!pw) {
                            pwError.textContent = 'Please enter your password.';
                            pwInput.focus();
                            return;
                        }
                        pwHidden.value = pw;
                        pendingSubmit = true;
                        overlay.classList.remove('active');
                        form.submit();
                    }
                })();

                function togglePwModal() {
                    const input = document.getElementById('pwModalInput');
                    const btn   = document.getElementById('pwModalEyeBtn');
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    btn.innerHTML = isHidden ? '&#128683;' : '&#128065;';
                }
                </script>
            <?php endif; ?>

            <!-- <a href="change_password.php" class="profile-back">Change Password</a> -->
        </div>
    </div>
</body>
</html>
