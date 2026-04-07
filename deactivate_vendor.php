<?php
include 'auth_check.php';
include 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;

    if ($vendor_id > 0) {
        $sql = "
            UPDATE Vendor
            SET is_active = 0
            WHERE vendor_id = ? AND user_id = ?
        ";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("ii", $vendor_id, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

$conn->close();
header("Location: vendor_management.php");
exit;
?>