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

            if ($requires_password && empty($current_password)) {
                $message = "Your current password is required to change email or username.";
                $message_type = 'error';
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
                        $message = "Profile updated successfully.";
                        $message_type = 'success';
                        $user['name'] = $updated_name;
                        $user['email'] = $updated_email;
                        $user['username'] = $updated_username;
                        if ($username_changed) {
                            $_SESSION['username'] = $updated_username;
                        }
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="pro-text">Pro</span><span class="manage-text">Manage</span></h1>
            <p class="dashboard-subtitle">Update your profile information</p>
            <div class="header-actions">
                <a href="profile.php" class="index-btn">← Back to Profile</a>
                <a href="logout.php" class="logout-btn">Logout</a>
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
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" placeholder="Required only for email/username changes">
                    </div>
                    <button type="submit" class="submit-btn">Save Changes</button>
                </form>
            <?php endif; ?>

            <a href="change_password.php" class="profile-back">Change Password</a>
            <a href="profile.php" class="profile-back" style="margin-left: 10px;">Back to Profile</a>
        </div>
    </div>
</body>
</html>
