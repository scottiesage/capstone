<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

$assets = [];
$liabilities = [];
$equity = [];

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;
$errorMessage = "";

/*
|--------------------------------------------------------------------------
| Get balances for all accounts
|--------------------------------------------------------------------------
| Rules:
| Asset, Expense     => Debit increases, Credit decreases
| Liability, Equity, Revenue => Credit increases, Debit decreases
|
| For the balance sheet, we only display:
| Asset, Liability, Equity
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
      AND a.account_type IN ('Asset', 'Liability', 'Equity')
    GROUP BY a.account_id, a.account_name, a.account_type
    ORDER BY
        FIELD(a.account_type, 'Asset', 'Liability', 'Equity'),
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

        if ($row['account_type'] === 'Asset') {
            $assets[] = $row;
            $total_assets += $row['balance'];
        } elseif ($row['account_type'] === 'Liability') {
            $liabilities[] = $row;
            $total_liabilities += $row['balance'];
        } elseif ($row['account_type'] === 'Equity') {
            $equity[] = $row;
            $total_equity += $row['balance'];
        }
    }

    $stmt->close();
}

$total_liabilities_and_equity = $total_liabilities + $total_equity;
$is_balanced = abs($total_assets - $total_liabilities_and_equity) < 0.01;

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet</title>
</head>
<body>

<h2>Balance Sheet</h2>

<p>
    <a href="transaction.php">Back to Transactions</a> |
    <a href="logout.php">Logout</a>
</p>

<?php if (!empty($errorMessage)) : ?>
    <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
<?php else : ?>

    <h3>Assets</h3>
    <?php if (count($assets) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($assets as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th>Total Assets</th>
                <th>$<?php echo number_format($total_assets, 2); ?></th>
            </tr>
        </table>
    <?php else: ?>
        <p>No asset accounts found.</p>
    <?php endif; ?>

    <br>

    <h3>Liabilities</h3>
    <?php if (count($liabilities) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($liabilities as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th>Total Liabilities</th>
                <th>$<?php echo number_format($total_liabilities, 2); ?></th>
            </tr>
        </table>
    <?php else: ?>
        <p>No liability accounts found.</p>
    <?php endif; ?>

    <br>

    <h3>Equity</h3>
    <?php if (count($equity) > 0): ?>
        <table border="1" cellpadding="8" cellspacing="0">
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($equity as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td>$<?php echo number_format($account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th>Total Equity</th>
                <th>$<?php echo number_format($total_equity, 2); ?></th>
            </tr>
        </table>
    <?php else: ?>
        <p>No equity accounts found.</p>
    <?php endif; ?>

    <br>

    <h3>Balance Check</h3>
    <p><strong>Total Assets:</strong> $<?php echo number_format($total_assets, 2); ?></p>
    <p><strong>Total Liabilities + Equity:</strong> $<?php echo number_format($total_liabilities_and_equity, 2); ?></p>

    <?php if ($is_balanced): ?>
        <p style="color: green;"><strong>Balanced:</strong> Assets = Liabilities + Equity</p>
    <?php else: ?>
        <p style="color: red;"><strong>Not Balanced:</strong> Assets do not equal Liabilities + Equity</p>
    <?php endif; ?>

<?php endif; ?>

</body>
</html>