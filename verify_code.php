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
    <title>Verify Account</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<div class="auth-page">
    <div class="auth-box">
        <h1 class="text-center">Secure Ledger</h1>
        <p class="text-center">Verify your account</p>

        <?php if (!empty($pendingEmail)): ?>
            <p style="text-align: center; margin-bottom: 16px; color: #475569;">
                A verification code was sent to
                <strong><?php echo htmlspecialchars($pendingEmail); ?></strong>
            </p>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <p style="color: #dc2626; font-weight: 600; margin-bottom: 16px; text-align: center;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($successMessage)): ?>
            <p style="color: #15803d; font-weight: 600; margin-bottom: 16px; text-align: center;">
                <?php echo htmlspecialchars($successMessage); ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="verify_code.php">
            <div class="form-group">
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
            </div>

            <button type="submit" class="btn btn-primary btn-full">Verify Account</button>
        </form>

        <form action="resend_code.php" method="POST" style="margin-top: 12px;">
            <button type="submit" class="btn btn-full">Resend Code</button>
        </form>

        <div class="mt-3 text-center">
            <a href="login.php">Back to Login</a>
        </div>
    </div>
</div>

</body>
</html>