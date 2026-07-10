<?php
session_start();
include 'db.php';
include 'csrf.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate CSRF token
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $message = "Security validation failed. Please try again.";
    } else {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($name) || empty($email) || empty($username) || empty($password)) {
            $message = "All fields are required!";
        } elseif ($password !== $confirm_password) {
            $message = "Passwords do not match!";
        } else {
            // Check if username or email already exists
            $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($check_sql);
            
            if ($stmt === false) {
                $message = "Database error: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $message = "Username or email already exists!";
                } else {
                    // Clear pending results before next query
                    $result->free();
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert user
                    $insert_sql = "INSERT INTO users (name, email, username, password_hash) VALUES (?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    
                    if ($insert_stmt === false) {
                        $message = "Database error: " . $conn->error;
                    } else {
                        $insert_stmt->bind_param("ssss", $name, $email, $username, $hashed_password);

                        if ($insert_stmt->execute()) {
                            $message = "Registration successful! Redirecting to login...";
                            header("refresh:2;url=login.php");
                        } else {
                            $message = "Error: " . $insert_stmt->error;
                        }
                        $insert_stmt->close();
                    }
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link rel="stylesheet" href="assets/css/common.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
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
        <div class="system-title">
            <span class="pro">Pro</span><span class="manage">Manage</span>
        </div>
        <h1>Register</h1>

        <?php if ($message): ?>
            <div class="message <?php echo (strpos($message, 'successful') !== false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
            
            <div class="form-group">
                <label for="name">Full Name:</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <div class="pw-input-wrap">
                    <input type="password" id="password" name="password" required>
                    <button type="button" class="pw-eye-btn" onclick="togglePw('password', this)" tabindex="-1" aria-label="Toggle password visibility">&#128065;</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <div class="pw-input-wrap">
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <button type="button" class="pw-eye-btn" onclick="togglePw('confirm_password', this)" tabindex="-1" aria-label="Toggle password visibility">&#128065;</button>
                </div>
            </div>

            <button type="submit">Register</button>
        </form>

        <div class="link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
    <script>
    function togglePw(id, btn) {
        const input = document.getElementById(id);
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        btn.innerHTML = isHidden ? '&#128683;' : '&#128065;';
    }
    </script>
</body>
</html>
