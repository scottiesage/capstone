<?php
session_start();
require_once 'db_connect.php';
require_once 'send_verification_email.php';

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errorMessage = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, email
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

            if ($user) {
                $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $update = $conn->prepare("
                    UPDATE User
                    SET reset_code = ?, reset_expires = ?
                    WHERE user_id = ?
                ");

                if (!$update) {
                    $errorMessage = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("ssi", $code, $expires, $user['user_id']);

                    if ($update->execute()) {
                        $_SESSION['reset_email'] = $user['email'];

                        $emailResult = sendPasswordResetEmail($user['email'], $code);

                        if ($emailResult['success']) {
                            $_SESSION['reset_success'] = "A password reset code has been sent to your email.";
                            header("Location: reset_password.php");
                            exit();
                        } else {
                            $errorMessage = "Email failed: " . $emailResult['message'];
                        }
                    } else {
                        $errorMessage = "Failed to create reset code.";
                    }

                    $update->close();
                }
            } else {
                $errorMessage = "No account found with that email address.";
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
    <title>Forgot Password</title>
</head>
<body>

<div class="container">
    <h2>Forgot Password</h2>

    <?php if (!empty($errorMessage)): ?>
        <div class="message-error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <div class="message-success"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="email">Enter your email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
            required
        >
        <button type="submit">Send Reset Code</button>
    </form>

    <a class="back-link" href="login.php">Back to Login</a>
</div>

</body>
</html>