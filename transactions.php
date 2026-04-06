<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";

$sql = "
    SELECT
        t.transaction_id,
        t.transaction_date,
        t.transaction_type,
        t.amount,
        t.customer_name,
        v.vendor_name,
        da.account_name AS debit_account_name,
        ca.account_name AS credit_account_name,
        t.description,
        t.memo,
        t.category,
        t.source
    FROM `Transaction` t
    LEFT JOIN Vendor v
        ON t.vendor_id = v.vendor_id
    INNER JOIN Account da
        ON t.debit_account_id = da.account_id
    INNER JOIN Account ca
        ON t.credit_account_id = ca.account_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.transaction_id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed: " . $conn->error;
    $transactions = false;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $transactions = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions</title>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Transactions</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php else : ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                <h2 style="margin: 0;">Transaction History</h2>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="transactionentry.php" class="btn btn-primary">+ Add Transaction</a>
                    <a href="balancesheetgen.php" class="btn">Balance Sheet</a>
                    <a href="incomestatement.php" class="btn">Income Statement</a>
                </div>
            </div>

            <?php if ($transactions && $transactions->num_rows > 0) : ?>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Customer</th>
                        <th>Vendor</th>
                        <th>Debit Account</th>
                        <th>Credit Account</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Source</th>
                        <th>Memo</th>
                        <th>Actions</th>
                    </tr>

                    <?php while ($row = $transactions->fetch_assoc()) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['transaction_type']); ?></td>
                            <td>$<?php echo number_format((float)$row['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($row['customer_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['vendor_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['debit_account_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['credit_account_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['description'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['category'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['source'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['memo'] ?? ''); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="edit_transaction.php?id=<?php echo (int)$row['transaction_id']; ?>" class="btn btn-primary">
                                        Edit
                                    </a>
                                    <a href="delete_transaction.php?id=<?php echo (int)$row['transaction_id']; ?>"
                                       class="btn"
                                       onclick="return confirm('Are you sure you want to delete this transaction?');">
                                       Delete
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            <?php else : ?>
                <p style="margin: 0;">No transactions found.</p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

</body>
</html>

<?php
if (isset($stmt) && $stmt) {
    $stmt->close();
}
$conn->close();
?>