<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: account_management.php");
    exit();
}

$account_id = (int)$_GET['id'];

$account_code = "";
$account_name = "";
$account_type = "";
$is_active = 1;

/* LOAD ACCOUNT */
$loadSql = "
    SELECT
        account_id,
        account_code,
        account_name,
        account_type,
        is_active
    FROM Account
    WHERE account_id = ? AND user_id = ?
    LIMIT 1
";

$loadStmt = $conn->prepare($loadSql);

if (!$loadStmt) {
    $errorMessage = "Prepare failed (load): " . $conn->error;
} else {
    $loadStmt->bind_param("ii", $account_id, $user_id);
    $loadStmt->execute();
    $result = $loadStmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $account_code = $row['account_code'] ?? "";
        $account_name = $row['account_name'] ?? "";
        $account_type = $row['account_type'] ?? "";
        $is_active = (int)$row['is_active'];
    } else {
        $loadStmt->close();
        header("Location: account_management.php");
        exit();
    }

    $loadStmt->close();
}

/* UPDATE */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $account_code = trim($_POST['account_code'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($account_code === "" || $account_name === "" || $account_type === "") {
        $errorMessage = "Account code, account name, and account type are required.";
    } else {
        $checkSql = "
            SELECT account_id
            FROM Account
            WHERE user_id = ?
              AND (account_code = ? OR account_name = ?)
              AND account_id <> ?
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errorMessage = "Prepare failed (duplicate check): " . $conn->error;
        } else {
            $checkStmt->bind_param("issi", $user_id, $account_code, $account_name, $account_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errorMessage = "An account with that code or name already exists.";
            }

            $checkStmt->close();
        }
    }

    if ($errorMessage === "") {
        $updateSql = "
            UPDATE Account
            SET
                account_code = ?,
                account_name = ?,
                account_type = ?,
                is_active = ?
            WHERE account_id = ? AND user_id = ?
        ";

        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            $errorMessage = "Prepare failed (update): " . $conn->error;
        } else {
            $updateStmt->bind_param(
                "sssiii",
                $account_code,
                $account_name,
                $account_type,
                $is_active,
                $account_id,
                $user_id
            );

            if ($updateStmt->execute()) {
                $updateStmt->close();
                header("Location: account_management.php");
                exit();
            } else {
                $errorMessage = "Failed to update account: " . $updateStmt->error;
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
    <title>Edit Account</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Edit Account</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 720px;">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="">

            <label>Account Code *</label>
            <input type="text" name="account_code" required
                   value="<?php echo htmlspecialchars($account_code); ?>">

            <label>Account Name *</label>
            <input type="text" name="account_name" required
                   value="<?php echo htmlspecialchars($account_name); ?>">

            <label>Account Type *</label>
            <select name="account_type" required>
                <option value="">Select account type</option>
                <option value="Asset" <?php echo ($account_type === 'Asset') ? 'selected' : ''; ?>>Asset</option>
                <option value="Liability" <?php echo ($account_type === 'Liability') ? 'selected' : ''; ?>>Liability</option>
                <option value="Equity" <?php echo ($account_type === 'Equity') ? 'selected' : ''; ?>>Equity</option>
                <option value="Revenue" <?php echo ($account_type === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                <option value="Expense" <?php echo ($account_type === 'Expense') ? 'selected' : ''; ?>>Expense</option>
            </select>

<div style="display: flex; align-items: center; gap: 10px; margin-top: 15px;">
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
                <button type="submit" class="btn btn-primary">Update Account</button>
                <a href="account_management.php" class="btn">Cancel</a>
            </div>

        </form>
    </div>
</div>

</body>
</html>

<?php
$conn->close();
?>