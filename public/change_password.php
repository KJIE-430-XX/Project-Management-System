<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';
require_once 'csrf.php';

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!validateCSRFToken($csrf_token)) {
        $message = "Security validation failed. Please try again.";
    } elseif ($conn === null) {
        $message = "Database connection is unavailable. Please try again later.";
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $message = "All password fields are required.";
        } elseif (strlen($new_password) < 3) {
            $message = "New password must be at least 3 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $message = "New passwords do not match.";
        } else {
            $verify_stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $verify_stmt->bind_param("i", $user_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $stored_user = $verify_result->fetch_assoc();
            $verify_stmt->close();

            if (!$stored_user || !password_verify($current_password, $stored_user['password_hash'])) {
                $message = "Current password is incorrect.";
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update_stmt->bind_param("si", $new_hash, $user_id);

                if ($update_stmt->execute()) {
                    $message = "Password updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Failed to update password. Please try again.";
                }

                $update_stmt->close();
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
    <title>Change Password - ProManage</title>
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

        /* Password eye-toggle */
        .pw-input-wrap {
            position: relative;
            max-width: 320px;
        }
        .pw-input-wrap input {
            max-width: 100%;
            width: 100%;
            padding-right: 40px;
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
            display: flex;
            align-items: center;
        }
        .pw-eye-btn:hover { color: #A855F7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>
            <p class="dashboard-subtitle">Update your password</p>
            <div class="header-actions">
                <!-- <a href="profile.php" class="index-btn">← Back to Profile</a>
                <a href="logout.php" class="logout-btn">Logout</a> -->
            </div>
        </div>

        <div class="profile-card">
            <h2>Change Password</h2>
            <?php if (!empty($message)): ?>
                <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="current_password" name="current_password" required>
                        <button type="button" class="pw-eye-btn" onclick="togglePw('current_password', this)" tabindex="-1" aria-label="Toggle password visibility"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="new_password" name="new_password" required>
                        <button type="button" class="pw-eye-btn" onclick="togglePw('new_password', this)" tabindex="-1" aria-label="Toggle password visibility"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <div class="pw-input-wrap">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <button type="button" class="pw-eye-btn" onclick="togglePw('confirm_password', this)" tabindex="-1" aria-label="Toggle password visibility"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                    </div>
                </div>
                <button type="submit" class="submit-btn">Update Password</button>
            </form>

            <a href="profile.php" class="profile-back">Cancel</a>
        </div>
    </div>

    <script>
    const eyeOpenSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';

    const eyeOffSVG = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden ? eyeOffSVG : eyeOpenSVG;
    }
    </script>
</body>
</html>
