<?php
session_start();
require_once 'db_connect.php';

$errorMessage = "";
$successMessage = "";

if (isset($_SESSION['reset_success'])) {
    $successMessage = $_SESSION['reset_success'];
    unset($_SESSION['reset_success']);
}

$resetEmail = $_SESSION['reset_email'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($code) || empty($password) || empty($confirmPassword)) {
        $errorMessage = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $errorMessage = "Reset code must be exactly 6 digits.";
    } elseif ($password !== $confirmPassword) {
        $errorMessage = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, reset_code, reset_expires
            FROM User
            WHERE email = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $errorMessage = "No account found with that email.";
            } elseif (empty($user['reset_code']) || empty($user['reset_expires'])) {
                $errorMessage = "No active reset request found.";
            } elseif (strtotime($user['reset_expires']) < time()) {
                $errorMessage = "This reset code has expired. Please request a new one.";
            } elseif ($code !== $user['reset_code']) {
                $errorMessage = "Invalid reset code.";
            } else {
                $newHash = password_hash($password, PASSWORD_DEFAULT);

                $update = $conn->prepare("
                    UPDATE User
                    SET password_hash = ?,
                        reset_code = NULL,
                        reset_expires = NULL
                    WHERE user_id = ?
                ");

                if (!$update) {
                    $errorMessage = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("si", $newHash, $user['user_id']);

                    if ($update->execute()) {
                        $update->close();

                        unset($_SESSION['reset_email']);
                        $_SESSION['login_success'] = "Password reset successful. Please log in.";

                        header("Location: login.php");
                        exit();
                    } else {
                        $errorMessage = "Failed to reset password.";
                        $update->close();
                    }
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
    <title>Reset Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 420px;
            margin: 80px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        h2 {
            margin-top: 0;
            text-align: center;
        }

        .message-error {
            background: #fdeaea;
            color: #b30000;
            border: 1px solid #f5c2c2;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .message-success {
            background: #eafaf0;
            color: #1f7a3d;
            border: 1px solid #bfe5c8;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        label {
            font-weight: bold;
            display: block;
            margin-bottom: 8px;
        }

        input[type="email"],
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
            margin-bottom: 15px;
        }

        button {
            width: 100%;
            padding: 11px;
            border: none;
            border-radius: 6px;
            background: #2c7be5;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            text-decoration: none;
            color: #2c7be5;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Reset Password</h2>

    <?php if (!empty($successMessage)): ?>
        <div class="message-success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="message-error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?php echo htmlspecialchars($resetEmail ?: ($email ?? '')); ?>"
            required
        >

        <label for="code">Reset Code</label>
        <input
            type="text"
            id="code"
            name="code"
            maxlength="6"
            inputmode="numeric"
            pattern="\d{6}"
            placeholder="Enter 6-digit code"
            required
        >

        <label for="password">New Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <label for="confirm_password">Confirm New Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            required
        >

        <button type="submit">Reset Password</button>
    </form>

    <a class="back-link" href="login.php">Back to Login</a>
</div>

</body>
</html>