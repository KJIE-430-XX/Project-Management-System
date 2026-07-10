<?php
session_start();
include 'db.php';
include 'csrf.php';

$message = '';
$message_type = 'error';

// Flash message from successful password reset
if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $message = "Password reset successfully! Please log in with your new password.";
    $message_type = 'success';
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Always reset to error on a new POST submission
    $message_type = 'error';

    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security validation failed. Please try again.";
        error_log("Login: CSRF token validation failed from IP " . $_SERVER['REMOTE_ADDR']);
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $message = "Username or email and password are required!";
            error_log("Login: Missing credentials from IP " . $_SERVER['REMOTE_ADDR']);
        } else {
            // Accept login by username OR email
            $select_sql = "SELECT id, name, username, password_hash FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($select_sql);
            
            if ($stmt === false) {
                $message = "Database error: " . $conn->error;
                error_log("Login: Database prepare error - " . $conn->error);
            } else {
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    if (password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['name'] = $user['name'];

                        error_log("Login: Successful login for user ID " . $user['id'] . " from IP " . $_SERVER['REMOTE_ADDR']);

                        $stmt->close();
                        header("Location: dashboard.php");
                        exit;
                    } else {
                        $message = "Invalid username or password!";
                        error_log("Login: Invalid password attempt for username '" . htmlspecialchars($username) . "' from IP " . $_SERVER['REMOTE_ADDR']);
                    }
                } else {
                    $message = "Invalid username or password!";
                    error_log("Login: User not found - username '" . htmlspecialchars($username) . "' from IP " . $_SERVER['REMOTE_ADDR']);
                }
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="system-title">
            <span class="pro">Pro</span><span class="manage">Manage</span>
        </div>
        <h1>Login</h1>
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <div class="form-group">
                <label for="username">Username or Email:</label>
                <input type="text" id="username" name="username" placeholder="Enter username or email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <div style="position: relative;">
                    <input type="password" id="password" name="password" required style="width: 100%; padding-right: 40px; box-sizing: border-box;">
                    <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </span>
                </div>
            </div>
            <button type="submit">Login</button>
            <div style="text-align: right; margin-top: 8px;">
                <a href="forgot_password.php" style="font-size: 13px; color: #A855F7; text-decoration: none;" id="forgot-password-link">Forgot Password?</a>
            </div>
        </form>
        <div class="link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function (e) {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'text') {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';
            } else {
                this.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
            }
        });
    </script>
</body>
</html>