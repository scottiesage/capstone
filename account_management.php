<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";
$accounts = [];

$sql = "
    SELECT
        account_id,
        account_code,
        account_name,
        account_type,
        is_active,
        created_at
    FROM Account
    WHERE user_id = ?
    ORDER BY account_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Account Management</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php else : ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                <h2 style="margin: 0;">Accounts</h2>
                <a href="add_account.php" class="btn btn-primary">+ Add Account</a>
            </div>

            <?php if (count($accounts) > 0): ?>
                <table>
                    <tr>
                        <th>Code</th>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>

                    <?php foreach ($accounts as $account): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($account['account_code']); ?></td>
                            <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                            <td><?php echo htmlspecialchars($account['account_type']); ?></td>

                            <td>
                                <?php if ((int)$account['is_active'] === 1): ?>
                                    <span style="color: green; font-weight: 600;">Active</span>
                                <?php else: ?>
                                    <span style="color: #64748b; font-weight: 600;">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td><?php echo htmlspecialchars($account['created_at']); ?></td>

                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="edit_account.php?id=<?php echo (int)$account['account_id']; ?>" class="btn btn-primary">
                                        Edit
                                    </a>

                                    <?php if ((int)$account['is_active'] === 1): ?>
                                        <a href="deactivate_account.php?id=<?php echo (int)$account['account_id']; ?>"
                                           class="btn"
                                           onclick="return confirm('Are you sure you want to deactivate this account?');">
                                           Deactivate
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-weight: 600; align-self: center;">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="margin: 0;">
                    No accounts found. Click <strong>+ Add Account</strong> to create your first account.
                </p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>