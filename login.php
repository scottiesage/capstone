<?php
session_start();
require_once 'db_connect.php';

$errorMessage = "";
$successMessage = "";

// Optional flash message from other pages
if (isset($_SESSION['login_error'])) {
    $errorMessage = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

if (isset($_SESSION['login_success'])) {
    $successMessage = $_SESSION['login_success'];
    unset($_SESSION['login_success']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $errorMessage = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, email, password_hash, is_verified
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

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errorMessage = "Invalid email or password.";
            } elseif ((int)$user['is_verified'] === 0) {
                $_SESSION['pending_user_id'] = $user['user_id'];
                $_SESSION['pending_user_email'] = $user['email'];
                $_SESSION['verify_error'] = "Please verify your email before logging in.";

                header("Location: verify_code.php");
                exit();
            } else {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_email'] = $user['email'];

                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email']);

                header("Location: dashboard.php");
                exit();
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
    <link rel="stylesheet" href="base.css">
    <title>Login</title>
</head>
<body>

<div class="container">
    <h2>Login</h2>

    <?php if (!empty($errorMessage)): ?>
        <div class="message-error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="message-success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
            required
        >

        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >

        <button type="submit">Login</button>
    </form>

    <div style="text-align:center; margin-top:15px;">
    <a class="register-link" href="forgot_password.php">Forgot Password?</a>
    <a class="register-link" href="register.php">Create Account</a>
    </div>
</div>

</body>
</html>