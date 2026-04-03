<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";
$successMessage = "";

$vendor_name = "";
$email = "";
$phone = "";
$address_line1 = "";
$city = "";
$state = "";
$zip_code = "";
$notes = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($vendor_name === "") {
        $errorMessage = "Vendor name is required.";
    } else {
        $checkSql = "
            SELECT vendor_id
            FROM Vendor
            WHERE user_id = ? AND vendor_name = ?
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errorMessage = "Prepare failed (duplicate check): " . $conn->error;
        } else {
            $checkStmt->bind_param("is", $user_id, $vendor_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errorMessage = "A vendor with that name already exists.";
            }

            $checkStmt->close();
        }
    }

    if ($errorMessage === "") {
        $insertSql = "
            INSERT INTO Vendor
            (user_id, vendor_name, email, phone, address_line1, city, state, zip_code, notes, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ";

        $insertStmt = $conn->prepare($insertSql);

        if (!$insertStmt) {
            $errorMessage = "Prepare failed (insert): " . $conn->error;
        } else {
            $insertStmt->bind_param(
                "issssssss",
                $user_id,
                $vendor_name,
                $email,
                $phone,
                $address_line1,
                $city,
                $state,
                $zip_code,
                $notes
            );

            if ($insertStmt->execute()) {
                header("Location: vendor_management.php");
                exit();
            } else {
                $errorMessage = "Failed to add vendor: " . $insertStmt->error;
            }

            $insertStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vendor</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1>Add Vendor</h1>

    <?php if (!empty($errorMessage)) : ?>
        <p style="color: red; font-weight: bold;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="vendor_name">Vendor Name *</label>
        <input type="text" id="vendor_name" name="vendor_name" required
               value="<?php echo htmlspecialchars($vendor_name); ?>">

        <label for="email">Email</label>
        <input type="email" id="email" name="email"
               value="<?php echo htmlspecialchars($email); ?>">

        <label for="phone">Phone</label>
        <input type="text" id="phone" name="phone"
               value="<?php echo htmlspecialchars($phone); ?>">

        <label for="address_line1">Address</label>
        <input type="text" id="address_line1" name="address_line1"
               value="<?php echo htmlspecialchars($address_line1); ?>">

        <label for="city">City</label>
        <input type="text" id="city" name="city"
               value="<?php echo htmlspecialchars($city); ?>">

        <label for="state">State</label>
        <input type="text" id="state" name="state"
               value="<?php echo htmlspecialchars($state); ?>">

        <label for="zip_code">Zip Code</label>
        <input type="text" id="zip_code" name="zip_code"
               value="<?php echo htmlspecialchars($zip_code); ?>">

        <label for="notes">Notes</label>
        <textarea id="notes" name="notes" rows="4"><?php echo htmlspecialchars($notes); ?></textarea>

        <br>
        <input type="submit" value="Add Vendor">
        <a href="vendor_management.php" class="btn" style="margin-left: 10px;">Cancel</a>
    </form>
</div>

</body>
</html>

<?php
$conn->close();
?>