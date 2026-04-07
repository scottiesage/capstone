<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";
$successMessage = "";

$account_code = "";
$account_name = "";
$account_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $account_code = trim($_POST['account_code'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_type = trim($_POST['account_type'] ?? '');

    if ($account_code === "" || $account_name === "" || $account_type === "") {
        $errorMessage = "Account code, account name, and account type are required.";
    } else {
        $checkSql = "
            SELECT account_id
            FROM Account
            WHERE user_id = ?
              AND (account_code = ? OR account_name = ?)
            LIMIT 1
        ";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            $errorMessage = "Prepare failed (duplicate check): " . $conn->error;
        } else {
            $checkStmt->bind_param("iss", $user_id, $account_code, $account_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $errorMessage = "An account with that code or name already exists.";
            }

            $checkStmt->close();
        }
    }

    if ($errorMessage === "") {
        $insertSql = "
            INSERT INTO Account
            (user_id, account_code, account_name, account_type, is_active)
            VALUES (?, ?, ?, ?, 1)
        ";

        $insertStmt = $conn->prepare($insertSql);

        if (!$insertStmt) {
            $errorMessage = "Prepare failed (insert): " . $conn->error;
        } else {
            $insertStmt->bind_param(
                "isss",
                $user_id,
                $account_code,
                $account_name,
                $account_type
            );

            if ($insertStmt->execute()) {
                header("Location: account_management.php");
                exit();
            } else {
                $errorMessage = "Failed to add account: " . $insertStmt->error;
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
    <title>Add Account</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Add Account</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 720px;">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="">
            <label for="account_code">Account Code *</label>
            <input
                type="text"
                id="account_code"
                name="account_code"
                required
                value="<?php echo htmlspecialchars($account_code); ?>"
            >

            <label for="account_name">Account Name *</label>
            <input
                type="text"
                id="account_name"
                name="account_name"
                required
                value="<?php echo htmlspecialchars($account_name); ?>"
            >

            <label for="account_type">Account Type *</label>
            <select
                id="account_type"
                name="account_type"
                required
            >
                <option value="">Select account type</option>
                <option value="Asset" <?php echo ($account_type === 'Asset') ? 'selected' : ''; ?>>Asset</option>
                <option value="Liability" <?php echo ($account_type === 'Liability') ? 'selected' : ''; ?>>Liability</option>
                <option value="Equity" <?php echo ($account_type === 'Equity') ? 'selected' : ''; ?>>Equity</option>
                <option value="Revenue" <?php echo ($account_type === 'Revenue') ? 'selected' : ''; ?>>Revenue</option>
                <option value="Expense" <?php echo ($account_type === 'Expense') ? 'selected' : ''; ?>>Expense</option>
            </select>

            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px;">
                <button type="submit" class="btn btn-primary">Add Account</button>
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