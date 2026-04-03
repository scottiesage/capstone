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

<h2>Transactions</h2>

<p>
    <a href="dashboard.php">Back to Dashboard</a> |
    <a href="transaction_entry.php">Add Transaction</a> |
    <a href="balancesheetgen.php">View Balance Sheet</a> |
    <a href="incomestatement.php">View Income Statement</a> |
    <a href="logout.php">Logout</a>
</p>

<?php if (!empty($errorMessage)) : ?>
    <p style="color:red;"><?php echo htmlspecialchars($errorMessage); ?></p>
<?php else : ?>

    <?php if ($transactions && $transactions->num_rows > 0) : ?>
        <table border="1" cellpadding="8" cellspacing="0">
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
                        <a href="edit_transaction.php?id=<?php echo (int)$row['transaction_id']; ?>">Edit</a> |
                        <a href="delete_transaction.php?id=<?php echo (int)$row['transaction_id']; ?>"
                           onclick="return confirm('Are you sure you want to delete this transaction?');">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else : ?>
        <p>No transactions found.</p>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>

<?php
if (isset($stmt) && $stmt) {
    $stmt->close();
}
$conn->close();
?>