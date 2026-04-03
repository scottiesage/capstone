<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

$revenues = [];
$expenses = [];

$total_revenue = 0;
$total_expenses = 0;
$net_income = 0;
$errorMessage = "";

/*
|--------------------------------------------------------------------------
| Get balances for revenue and expense accounts
|--------------------------------------------------------------------------
| Rules:
| Asset, Expense => Debit increases, Credit decreases
| Liability, Equity, Revenue => Credit increases, Debit decreases
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        a.account_id,
        a.account_name,
        a.account_type,
        COALESCE(SUM(
            CASE
                WHEN t.debit_account_id = a.account_id THEN
                    CASE
                        WHEN a.account_type IN ('Asset', 'Expense') THEN t.amount
                        ELSE -t.amount
                    END
                WHEN t.credit_account_id = a.account_id THEN
                    CASE
                        WHEN a.account_type IN ('Liability', 'Equity', 'Revenue') THEN t.amount
                        ELSE -t.amount
                    END
                ELSE 0
            END
        ), 0) AS balance
    FROM Account a
    LEFT JOIN `Transaction` t
        ON a.account_id = t.debit_account_id
        OR a.account_id = t.credit_account_id
    WHERE a.user_id = ?
      AND a.account_type IN ('Revenue', 'Expense')
    GROUP BY a.account_id, a.account_name, a.account_type
    ORDER BY
        FIELD(a.account_type, 'Revenue', 'Expense'),
        a.account_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $row['balance'] = (float)$row['balance'];

        if ($row['account_type'] === 'Revenue') {
            $revenues[] = $row;
            $total_revenue += $row['balance'];
        } elseif ($row['account_type'] === 'Expense') {
            $expenses[] = $row;
            $total_expenses += $row['balance'];
        }
    }

    $stmt->close();
}

$net_income = $total_revenue - $total_expenses;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Statement</title>
</head>
<body>

<h2>Income Statement</h2>

<p>
    <a href="dashboard.php">Back to Dashboard</a> |
    <a href="transaction.php">View Transactions</a> |
    <a href="balancesheetgen.php">View Balance Sheet</a> |
    <a href="logout.php">Logout</a>
</p>

<?php if (!empty($errorMessage)) : ?>
    <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
<?php else : ?>

    <h3>Revenue</h3>
    <?php if (count($revenues) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($revenues as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th>Total Revenue</th>
                <th>$<?php echo number_format($total_revenue, 2); ?></th>
            </tr>
        </table>
    <?php else: ?>
        <p>No revenue accounts found.</p>
    <?php endif; ?>

    <br>

    <h3>Expenses</h3>
    <?php if (count($expenses) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($expenses as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th>Total Expenses</th>
                <th>$<?php echo number_format($total_expenses, 2); ?></th>
            </tr>
        </table>
    <?php else: ?>
        <p>No expense accounts found.</p>
    <?php endif; ?>

    <br>

    <h3>Net Income</h3>
    <?php if ($net_income >= 0): ?>
        <p style="color: green;"><strong>Net Income:</strong> $<?php echo number_format($net_income, 2); ?></p>
    <?php else: ?>
        <p style="color: red;"><strong>Net Loss:</strong> $<?php echo number_format(abs($net_income), 2); ?></p>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>