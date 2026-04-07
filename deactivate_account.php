<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: account_management.php");
    exit();
}

$account_id = (int)$_GET['id'];

$sql = "
    UPDATE Account
    SET is_active = 0
    WHERE account_id = ? AND user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ii", $account_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

header("Location: account_management.php");
exit();
?>