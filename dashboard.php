<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

$errorMessage = "";

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
$total_revenue = 0;
$total_expenses = 0;
$net_income = 0;
$total_transactions = 0;

$alerts = [];
$large_transactions = [];
$duplicate_transactions = [];

/*
|--------------------------------------------------------------------------
| Get account balances by type
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
        ON (t.debit_account_id = a.account_id OR t.credit_account_id = a.account_id)
        AND t.user_id = ?
    WHERE a.user_id = ?
    GROUP BY a.account_id, a.account_name, a.account_type
    ORDER BY a.account_type, a.account_name
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed (balances): " . $conn->error;
} else {
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $balance = (float)$row['balance'];

        switch ($row['account_type']) {
            case 'Asset':
                $total_assets += $balance;
                if ($balance < 0) {
                    $alerts[] = "Negative asset balance detected in account: " . $row['account_name'];
                }
                break;

            case 'Liability':
                $total_liabilities += $balance;
                break;

            case 'Equity':
                $total_equity += $balance;
                break;

            case 'Revenue':
                $total_revenue += $balance;
                break;

            case 'Expense':
                $total_expenses += $balance;
                break;
        }
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Count transactions
|--------------------------------------------------------------------------
*/
$countSql = "SELECT COUNT(*) AS total_transactions FROM `Transaction` WHERE user_id = ?";
$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    if (empty($errorMessage)) {
        $errorMessage = "Prepare failed (count): " . $conn->error;
    }
} else {
    $countStmt->bind_param("i", $user_id);
    $countStmt->execute();
    $countResult = $countStmt->get_result();

    if ($countRow = $countResult->fetch_assoc()) {
        $total_transactions = (int)$countRow['total_transactions'];
    }

    $countStmt->close();
}

/*
|--------------------------------------------------------------------------
| Detect large transactions
|--------------------------------------------------------------------------
*/
$largeSql = "
    SELECT
        transaction_id,
        transaction_date,
        amount,
        description
    FROM `Transaction`
    WHERE user_id = ?
      AND amount > 10000
    ORDER BY amount DESC, transaction_date DESC
";

$largeStmt = $conn->prepare($largeSql);

if (!$largeStmt) {
    if (empty($errorMessage)) {
        $errorMessage = "Prepare failed (large transactions): " . $conn->error;
    }
} else {
    $largeStmt->bind_param("i", $user_id);
    $largeStmt->execute();
    $largeResult = $largeStmt->get_result();

    while ($row = $largeResult->fetch_assoc()) {
        $large_transactions[] = $row;
    }

    $largeStmt->close();
}

/*
|--------------------------------------------------------------------------
| Detect possible duplicate transactions
|--------------------------------------------------------------------------
*/
$duplicateSql = "
    SELECT
        t.transaction_date,
        t.amount,
        t.vendor_id,
        v.vendor_name,
        COUNT(*) AS duplicate_count
    FROM `Transaction` t
    LEFT JOIN Vendor v
        ON t.vendor_id = v.vendor_id
    WHERE t.user_id = ?
    GROUP BY t.transaction_date, t.amount, t.vendor_id, v.vendor_name
    HAVING COUNT(*) > 1
    ORDER BY t.transaction_date DESC, t.amount DESC
";

$duplicateStmt = $conn->prepare($duplicateSql);

if (!$duplicateStmt) {
    if (empty($errorMessage)) {
        $errorMessage = "Prepare failed (duplicates): " . $conn->error;
    }
} else {
    $duplicateStmt->bind_param("i", $user_id);
    $duplicateStmt->execute();
    $duplicateResult = $duplicateStmt->get_result();

    while ($row = $duplicateResult->fetch_assoc()) {
        $duplicate_transactions[] = $row;
    }

    $duplicateStmt->close();
}

/*
|--------------------------------------------------------------------------
| Final calculations
|--------------------------------------------------------------------------
*/
$net_income = $total_revenue - $total_expenses;
$balance_check = $total_assets - ($total_liabilities + $total_equity + $net_income);
$is_balanced = abs($balance_check) < 0.01;

if (!$is_balanced) {
    $alerts[] = "Balance sheet is not balanced.";
}

if (count($large_transactions) > 0) {
    $alerts[] = count($large_transactions) . " large transaction(s) detected over $10,000.";
}

if (count($duplicate_transactions) > 0) {
    $alerts[] = count($duplicate_transactions) . " possible duplicate transaction pattern(s) detected.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1>Accounting Dashboard</h1>

    <?php if (!empty($errorMessage)) : ?>
        <p style="color: red; font-weight: bold;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </p>
    <?php else : ?>

        <h2>Financial Summary</h2>
        <table>
            <tr>
                <th>Metric</th>
                <th>Amount</th>
            </tr>
            <tr>
                <td>Total Assets</td>
                <td>$<?php echo number_format($total_assets, 2); ?></td>
            </tr>
            <tr>
                <td>Total Liabilities</td>
                <td>$<?php echo number_format($total_liabilities, 2); ?></td>
            </tr>
            <tr>
                <td>Total Equity</td>
                <td>$<?php echo number_format($total_equity, 2); ?></td>
            </tr>
            <tr>
                <td>Total Revenue</td>
                <td>$<?php echo number_format($total_revenue, 2); ?></td>
            </tr>
            <tr>
                <td>Total Expenses</td>
                <td>$<?php echo number_format($total_expenses, 2); ?></td>
            </tr>
            <tr>
                <td>Net Income</td>
                <td>
                    <?php
                    if ($net_income >= 0) {
                        echo '$' . number_format($net_income, 2);
                    } else {
                        echo '-$' . number_format(abs($net_income), 2);
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <td>Total Transactions</td>
                <td><?php echo $total_transactions; ?></td>
            </tr>
        </table>

        <h2 style="margin-top: 30px;">System Status</h2>
        <?php if ($is_balanced): ?>
            <p style="color: green; font-weight: bold;">
                Balance Sheet Status: Balanced
            </p>
        <?php else: ?>
            <p style="color: red; font-weight: bold;">
                Balance Sheet Status: Not Balanced
                (Difference: $<?php echo number_format(abs($balance_check), 2); ?>)
            </p>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Fraud / Error Detection Alerts</h2>
        <?php if (count($alerts) > 0): ?>
            <ul>
                <?php foreach ($alerts as $alert): ?>
                    <li style="color: red;">
                        <?php echo htmlspecialchars($alert); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p style="color: green;">No fraud or error alerts detected.</p>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Large Transactions</h2>
        <?php if (count($large_transactions) > 0): ?>
            <table>
                <tr>
                    <th>Transaction ID</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Description</th>
                </tr>
                <?php foreach ($large_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo (int)$transaction['transaction_id']; ?></td>
                        <td><?php echo htmlspecialchars($transaction['transaction_date']); ?></td>
                        <td>$<?php echo number_format((float)$transaction['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No large transactions detected.</p>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Possible Duplicate Transactions</h2>
        <?php if (count($duplicate_transactions) > 0): ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Vendor</th>
                    <th>Duplicate Count</th>
                </tr>
                <?php foreach ($duplicate_transactions as $duplicate): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($duplicate['transaction_date']); ?></td>
                        <td>$<?php echo number_format((float)$duplicate['amount'], 2); ?></td>
                        <td>
                            <?php
                            echo htmlspecialchars(
                                $duplicate['vendor_name'] ?? ('Vendor ID ' . ($duplicate['vendor_id'] ?? 'None'))
                            );
                            ?>
                        </td>
                        <td><?php echo (int)$duplicate['duplicate_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No duplicate transaction patterns detected.</p>
        <?php endif; ?>

        <h2 style="margin-top: 30px;">Quick Actions</h2>
        <p><a href="transactionentry.php">Enter New Transaction</a></p>
        <p><a href="transactions.php">Review All Transactions</a></p>
        <p><a href="balancesheetgen.php">Open Balance Sheet</a></p>
        <p><a href="incomestatement.php">Open Income Statement</a></p>

    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>