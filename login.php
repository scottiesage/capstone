<?php
session_start();
require_once 'db_connect.php';

$errorMessage = "";
$successMessage = "";
$email = "";

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
            $errorMessage = "Database error. Please try again.";
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errorMessage = "Email or password is incorrect.";
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
    <title>Login | Secure Ledger</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-box">

        <h1 class="text-center">Secure Ledger</h1>
        <p class="text-center">Login to your account</p>

        <?php if (!empty($errorMessage)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <div class="card" style="padding: 12px; margin-bottom: 15px; text-align: center; color: #166534; background-color: #dcfce7; border: 1px solid #bbf7d0;">
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php echo htmlspecialchars($email); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full">Login</button>
        </form>

        <div class="mt-3 text-center">
            <a href="forgot_password.php">Forgot Password?</a> |
            <a href="register.php">Create Account</a>
        </div>

    </div>
</div>

</body>
</html>