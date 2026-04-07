<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: vendor_management.php");
    exit();
}

$vendor_id = (int)$_GET['id'];

$vendor_name = "";
$email = "";
$phone = "";
$address_line1 = "";
$city = "";
$state = "";
$zip_code = "";
$notes = "";
$is_active = 1;

/* LOAD VENDOR */
$loadSql = "
    SELECT
        vendor_id,
        vendor_name,
        email,
        phone,
        address_line1,
        city,
        state,
        zip_code,
        notes,
        is_active
    FROM Vendor
    WHERE vendor_id = ? AND user_id = ?
    LIMIT 1
";

$loadStmt = $conn->prepare($loadSql);

if (!$loadStmt) {
    $errorMessage = "Prepare failed (load): " . $conn->error;
} else {
    $loadStmt->bind_param("ii", $vendor_id, $user_id);
    $loadStmt->execute();
    $result = $loadStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $vendor_name = $row['vendor_name'] ?? "";
        $email = $row['email'] ?? "";
        $phone = $row['phone'] ?? "";
        $address_line1 = $row['address_line1'] ?? "";
        $city = $row['city'] ?? "";
        $state = $row['state'] ?? "";
        $zip_code = $row['zip_code'] ?? "";
        $notes = $row['notes'] ?? "";
        $is_active = (int)$row['is_active'];
    } else {
        $loadStmt->close();
        header("Location: vendor_management.php");
        exit();
    }

    $loadStmt->close();
}

/* UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($vendor_name === "") {
        $errorMessage = "Vendor name is required.";
    } else {
        $checkSql = "
            SELECT vendor_id
            FROM Vendor
            WHERE user_id = ?
              AND vendor_name = ?
              AND vendor_id <> ?
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errorMessage = "Prepare failed (duplicate check): " . $conn->error;
        } else {
            $checkStmt->bind_param("isi", $user_id, $vendor_name, $vendor_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errorMessage = "A vendor with that name already exists.";
            }

            $checkStmt->close();
        }
    }

    if ($errorMessage === "") {
        $updateSql = "
            UPDATE Vendor
            SET
                vendor_name = ?,
                email = ?,
                phone = ?,
                address_line1 = ?,
                city = ?,
                state = ?,
                zip_code = ?,
                notes = ?,
                is_active = ?
            WHERE vendor_id = ? AND user_id = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            $errorMessage = "Prepare failed (update): " . $conn->error;
        } else {
            $updateStmt->bind_param(
                "ssssssssiii",
                $vendor_name,
                $email,
                $phone,
                $address_line1,
                $city,
                $state,
                $zip_code,
                $notes,
                $is_active,
                $vendor_id,
                $user_id
            );

            if ($updateStmt->execute()) {
                $updateStmt->close();
                header("Location: vendor_management.php");
                exit();
            } else {
                $errorMessage = "Failed to update vendor: " . $updateStmt->error;
            }

            $updateStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vendor</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Edit Vendor</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 720px;">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="">

            <label>Vendor Name *</label>
            <input type="text" name="vendor_name" required
                   value="<?php echo htmlspecialchars($vendor_name); ?>">

            <label>Email</label>
            <input type="email" name="email"
                   value="<?php echo htmlspecialchars($email); ?>">

            <label>Phone</label>
            <input type="text" name="phone"
                   value="<?php echo htmlspecialchars($phone); ?>">

            <label>Address</label>
            <input type="text" name="address_line1"
                   value="<?php echo htmlspecialchars($address_line1); ?>">

            <label>City</label>
            <input type="text" name="city"
                   value="<?php echo htmlspecialchars($city); ?>">

            <label>State</label>
            <input type="text" name="state"
                   value="<?php echo htmlspecialchars($state); ?>">

            <label>Zip Code</label>
            <input type="text" name="zip_code"
                   value="<?php echo htmlspecialchars($zip_code); ?>">

            <label>Notes</label>
            <textarea name="notes" rows="4"><?php echo htmlspecialchars($notes); ?></textarea>

<div style="display: inline-flex; align-items: center; gap: 10px; margin-top: 15px;">
    <input
        type="checkbox"
        name="is_active"
        id="is_active"
        value="1"
        <?php echo $is_active ? 'checked' : ''; ?>
        style="width: auto; margin: 0;"
    >
    <label for="is_active" style="margin: 0; display: inline;">Active</label>
</div>

            <div style="display: flex; gap: 12px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">Update Vendor</button>
                <a href="vendor_management.php" class="btn">Cancel</a>
            </div>

        </form>
    </div>
</div>

</body>
</html>

<?php
$conn->close();
?>