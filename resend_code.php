<?php
session_start();
require_once 'db_connect.php';
require_once 'send_verification_email.php';

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int) $_SESSION['pending_user_id'];

$stmt = $conn->prepare("
    SELECT email, is_verified
    FROM User
    WHERE user_id = ?
");

if (!$stmt) {
    $_SESSION['verify_error'] = "Database error: " . $conn->error;
    header("Location: verify_code.php");
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['verify_error'] = "User not found.";
    header("Location: login.php");
    exit();
}

if ((int)$user['is_verified'] === 1) {
    $_SESSION['verify_success'] = "Your account is already verified. Please log in.";
    unset($_SESSION['pending_user_id'], $_SESSION['pending_user_email']);
    header("Location: login.php");
    exit();
}

$code = str_pad((string) rand(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$update = $conn->prepare("
    UPDATE User
    SET verification_code = ?, verification_expires = ?
    WHERE user_id = ?
");

if (!$update) {
    $_SESSION['verify_error'] = "Database error: " . $conn->error;
    header("Location: verify_code.php");
    exit();
}

$update->bind_param("ssi", $code, $expires, $user_id);

if (!$update->execute()) {
    $_SESSION['verify_error'] = "Failed to generate a new verification code.";
    $update->close();
    header("Location: verify_code.php");
    exit();
}

$update->close();

$emailResult = sendVerificationEmail($user['email'], $code);

if ($emailResult['success']) {
    $_SESSION['verify_success'] = "A new verification code has been sent to your email.";
} else {
    $_SESSION['verify_error'] = "Failed to send email: " . $emailResult['message'];
}

header("Location: verify_code.php");
exit();