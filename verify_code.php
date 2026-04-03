<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['pending_user_id'];
$pendingEmail = $_SESSION['pending_user_email'] ?? '';

$errorMessage = "";
$successMessage = "";

// Flash messages from resend_code.php
if (isset($_SESSION['verify_error'])) {
    $errorMessage = $_SESSION['verify_error'];
    unset($_SESSION['verify_error']);
}

if (isset($_SESSION['verify_success'])) {
    $successMessage = $_SESSION['verify_success'];
    unset($_SESSION['verify_success']);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entered = trim($_POST['code'] ?? '');

    if (empty($entered)) {
        $errorMessage = "Please enter the verification code.";
    } elseif (!preg_match('/^\d{6}$/', $entered)) {
        $errorMessage = "Verification code must be exactly 6 digits.";
    } else {
        $stmt = $conn->prepare("
            SELECT email, verification_code, verification_expires, is_verified
            FROM User
            WHERE user_id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            $errorMessage = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $errorMessage = "User not found.";
            } elseif ((int)$user['is_verified'] === 1) {
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $user['email'];

                unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email']);

                header("Location: dashboard.php");
                exit();
            } elseif (empty($user['verification_code']) || empty($user['verification_expires'])) {
                $errorMessage = "No active verification code found. Please click Resend Code.";
            } elseif (strtotime($user['verification_expires']) < time()) {
                $errorMessage = "This verification code has expired. Please click Resend Code.";
            } elseif ($entered !== $user['verification_code']) {
                $errorMessage = "Invalid verification code.";
            } else {
                $verify = $conn->prepare("
                    UPDATE User
                    SET is_verified = 1,
                        verification_code = NULL,
                        verification_expires = NULL
                    WHERE user_id = ?
                ");

                if (!$verify) {
                    $errorMessage = "Database error: " . $conn->error;
                } else {
                    $verify->bind_param("i", $user_id);

                    if ($verify->execute()) {
                        $verify->close();

                        session_regenerate_id(true);

                        $_SESSION['user_id'] = $user_id;
                        $_SESSION['user_email'] = $user['email'];

                        unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email']);

                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $errorMessage = "Failed to verify account.";
                        $verify->close();
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
    <link rel="stylesheet" href="base.css">
    <title>Verify Code</title>
</head>
<body>

<div class="container">
    <h2>Verify Your Account</h2>

    <?php if (!empty($pendingEmail)): ?>
        <p class="email-note">
            A verification code was sent to
            <strong><?php echo htmlspecialchars($pendingEmail); ?></strong>
        </p>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <p class="message-error"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <p class="message-success"><?php echo htmlspecialchars($successMessage); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="code">Enter Code</label>
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
        <button type="submit">Verify Account</button>
    </form>

    <form action="resend_code.php" method="POST">
        <button type="submit" class="secondary-btn">Resend Code</button>
    </form>

    <a class="back-link" href="login.php">Back to Login</a>
</div>

</body>
</html>
