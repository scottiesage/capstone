<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $transaction_id = (int)$_GET['id'];

    $stmt = $conn->prepare("
        DELETE FROM `Transaction`
        WHERE transaction_id = ? AND user_id = ?
    ");

    if ($stmt) {
        $stmt->bind_param("ii", $transaction_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

$conn->close();

header("Location: transactions.php");
exit();
?>