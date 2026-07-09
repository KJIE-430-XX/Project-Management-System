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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ProManage</title>
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

        .profile-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
            color: #E2E8F0;
        }

        .profile-label {
            font-weight: 600;
            color: #A855F7;
        }

        .profile-back {
            display: inline-block;
            margin-top: 24px;
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

        .password-form {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(148, 163, 184, 0.2);
        }

        .password-form h3 {
            margin: 0 0 16px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>
            <p class="dashboard-subtitle">Your account details</p>
            <div class="header-actions">
                <a href="dashboard.php" class="index-btn">Back</a>
            </div>
        </div>

        <div class="profile-card">
            <h2>My Profile</h2>
            <?php
            if (isset($_SESSION['success'])) {
                echo '<div class="message success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            <?php if (!empty($profile_error)): ?>
                <p><?php echo htmlspecialchars($profile_error); ?></p>
            <?php else: ?>
                <div class="profile-row"><span class="profile-label">Name</span><span><?php echo htmlspecialchars($user['name']); ?></span></div>
                <div class="profile-row"><span class="profile-label">Email</span><span><?php echo htmlspecialchars($user['email']); ?></span></div>
                <div class="profile-row"><span class="profile-label">Username</span><span><?php echo htmlspecialchars($user['username']); ?></span></div>
                <div class="profile-row"><span class="profile-label">Member Since</span><span><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span></div>
                <div class="profile-row" style="border-bottom:none; justify-content:flex-start; gap: 10px; margin-top: 20px;">
                    <a href="profile_manage.php" class="submit-btn" style="display: inline-block; text-decoration: none;">Edit Profile</a>
                </div>
            <?php endif; ?>

            <div class="password-form">
                <h3>Security</h3>
                <a href="change_password.php" class="submit-btn" style="display: inline-block; text-decoration: none;">Change Password</a>
            </div>
        </div>
    </div>
</body>
</html>
