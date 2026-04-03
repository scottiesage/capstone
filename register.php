<?php
session_start();
require_once 'db_connect.php';
require_once 'send_verification_email.php';

$errorMessage = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password) || empty($confirm)) {
        $errorMessage = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif ($password !== $confirm) {
        $errorMessage = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $errorMessage = "Password must be at least 8 characters long.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT user_id, is_verified FROM User WHERE email = ?");
        if (!$check) {
            $errorMessage = "Database error: " . $conn->error;
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $result = $check->get_result();
            $existingUser = $result->fetch_assoc();
            $check->close();

            if ($existingUser) {
                if ((int)$existingUser['is_verified'] === 1) {
                    $errorMessage = "An account with that email already exists.";
                } else {
                    // Update unverified account with a new code instead of making duplicate account
                    $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $update = $conn->prepare("
                        UPDATE User
                        SET password_hash = ?, verification_code = ?, verification_expires = ?
                        WHERE user_id = ?
                    ");

                    if (!$update) {
                        $errorMessage = "Database error: " . $conn->error;
                    } else {
                        $update->bind_param("sssi", $hash, $code, $expires, $existingUser['user_id']);

                        if ($update->execute()) {
                            $_SESSION['pending_user_id'] = $existingUser['user_id'];
                            $_SESSION['pending_user_email'] = $email;

                            $emailResult = sendVerificationEmail($email, $code);

                            if ($emailResult['success']) {
                                header("Location: verify_code.php");
                                exit();
                            } else {
                                $errorMessage = "Account saved, but email failed: " . $emailResult['message'];
                            }
                        } else {
                            $errorMessage = "Failed to update account.";
                        }

                        $update->close();
                    }
                }
            } else {
                // Create new account
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $insert = $conn->prepare("
                    INSERT INTO User (email, password_hash, verification_code, verification_expires, is_verified)
                    VALUES (?, ?, ?, ?, 0)
                ");

                if (!$insert) {
                    $errorMessage = "Database error: " . $conn->error;
                } else {
                    $insert->bind_param("ssss", $email, $hash, $code, $expires);

                    if ($insert->execute()) {
                        $user_id = $insert->insert_id;

                        $_SESSION['pending_user_id'] = $user_id;
                        $_SESSION['pending_user_email'] = $email;

                        $emailResult = sendVerificationEmail($email, $code);

                        if ($emailResult['success']) {
                            header("Location: verify_code.php");
                            exit();
                        } else {
                            $errorMessage = "Account created, but email failed: " . $emailResult['message'];
                        }
                    } else {
                        $errorMessage = "Failed to create account.";
                    }

                    $insert->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="base.css">
    <meta charset="UTF-8">
    <title>Register</title>
</head>
<body>

    <h2>Create Account</h2>

    <?php if (!empty($errorMessage)): ?>
        <p style="color:red;"><?php echo htmlspecialchars($errorMessage); ?></p>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
        <p style="color:green;"><?php echo htmlspecialchars($successMessage); ?></p>
    <?php endif; ?>

    <form method="POST" action="">
        <label>Email:</label><br>
        <input type="email" name="email" required
               value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
        <br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required>
        <br><br>

        <label>Confirm Password:</label><br>
        <input type="password" name="confirm_password" required>
        <br><br>

        <button type="submit">Register</button>
    </form>

    <br>
    <a href="login.php">Back to Login</a>

</body>
</html>