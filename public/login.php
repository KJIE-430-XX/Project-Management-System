<?php
session_start();
include 'db.php';
include 'csrf.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security validation failed. Please try again.";
        error_log("Login: CSRF token validation failed from IP " . $_SERVER['REMOTE_ADDR']);
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $message = "Username and password are required!";
            error_log("Login: Missing credentials from IP " . $_SERVER['REMOTE_ADDR']);
        } else {
            $select_sql = "SELECT id, name, username, password_hash FROM users WHERE username = ?";
            $stmt = $conn->prepare($select_sql);
            
            if ($stmt === false) {
                $message = "Database error: " . $conn->error;
                error_log("Login: Database prepare error - " . $conn->error);
            } else {
                $stmt->bind_param("s", $username);
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
            <div class="message error"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
        <div class="link">
            Don't have an account? <a href="register.php">Register here</a>
        </div>
    </div>
</body>
</html>