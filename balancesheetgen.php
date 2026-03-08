<?php
include 'db_connect.php';

$sql = "SELECT
            a.account_id,
            a.account_name,
            a.account_type,
            COALESCE(SUM(CASE WHEN tl.line_type = 'Debit' THEN tl.amount ELSE 0 END), 0) AS total_debits,
            COALESCE(SUM(CASE WHEN tl.line_type = 'Credit' THEN tl.amount ELSE 0 END), 0) AS total_credits,
            CASE
                WHEN a.account_type = 'Asset' THEN
                    COALESCE(SUM(CASE WHEN tl.line_type = 'Debit' THEN tl.amount ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN tl.line_type = 'Credit' THEN tl.amount ELSE 0 END), 0)
                WHEN a.account_type IN ('Liability', 'Equity') THEN
                    COALESCE(SUM(CASE WHEN tl.line_type = 'Credit' THEN tl.amount ELSE 0 END), 0) -
                    COALESCE(SUM(CASE WHEN tl.line_type = 'Debit' THEN tl.amount ELSE 0 END), 0)
                ELSE 0
            END AS balance
        FROM Account a
        LEFT JOIN TransactionLine tl ON a.account_id = tl.account_id
        WHERE a.account_type IN ('Asset', 'Liability', 'Equity')
        GROUP BY a.account_id, a.account_name, a.account_type
        ORDER BY a.account_type, a.account_name";

$result = $conn->query($sql);

$assets = [];
$liabilities = [];
$equity = [];

$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $balance = (float)$row['balance'];

        if ($row['account_type'] === 'Asset') {
            $assets[] = $row;
            $total_assets += $balance;
        } elseif ($row['account_type'] === 'Liability') {
            $liabilities[] = $row;
            $total_liabilities += $balance;
        } elseif ($row['account_type'] === 'Equity') {
            $equity[] = $row;
            $total_equity += $balance;
        }
    }
}

$isBalanced = round($total_assets, 2) == round(($total_liabilities + $total_equity), 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 950px;
            margin: auto;
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .top-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .back-btn,
        .pdf-btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
        }

        .back-btn {
            background-color: #2c3e50;
            color: white;
        }

        .back-btn:hover {
            background-color: #1f2d3a;
        }

        .pdf-btn {
            background-color: #c0392b;
            color: white;
        }

        .pdf-btn:hover {
            background-color: #a93226;
        }

        h1 {
            text-align: center;
            margin-bottom: 10px;
        }

        .report-date {
            text-align: center;
            color: #555;
            margin-bottom: 30px;
        }

        h2 {
            margin-top: 30px;
            margin-bottom: 10px;
            color: #2c3e50;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        th {
            background-color: #2c3e50;
            color: white;
            text-align: left;
        }

        td.amount {
            text-align: right;
            white-space: nowrap;
        }

        .total-row {
            font-weight: bold;
            background-color: #f2f2f2;
        }

        .summary-table {
            margin-top: 15px;
        }

        .summary-table td {
            font-weight: bold;
        }

        .status {
            margin-top: 20px;
            padding: 12px;
            border-radius: 5px;
            font-weight: bold;
            text-align: center;
        }

        .balanced {
            background-color: #d4edda;
            color: #155724;
        }

        .not-balanced {
            background-color: #f8d7da;
            color: #721c24;
        }

        .empty-message {
            color: #777;
            font-style: italic;
            padding: 10px 0;
        }

        @media print {
            body {
                background-color: white;
                margin: 0;
            }

            .top-buttons {
                display: none;
            }

            .container {
                box-shadow: none;
                border: none;
                max-width: 100%;
                width: 100%;
                margin: 0;
                padding: 10px;
            }

            .status {
                border: 1px solid #ccc;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-buttons">
        <a href="transaction.php" class="back-btn">← Back to Transactions</a>
        <button onclick="window.print()" class="pdf-btn">Save as PDF</button>
    </div>

    <h1>Balance Sheet</h1>
    <div class="report-date">Generated on <?php echo date("F j, Y"); ?></div>

    <h2>Assets</h2>
    <?php if (!empty($assets)): ?>
        <table>
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($assets as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td class="amount">$<?php echo number_format((float)$account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total Assets</td>
                <td class="amount">$<?php echo number_format($total_assets, 2); ?></td>
            </tr>
        </table>
    <?php else: ?>
        <p class="empty-message">No asset accounts found.</p>
    <?php endif; ?>

    <h2>Liabilities</h2>
    <?php if (!empty($liabilities)): ?>
        <table>
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($liabilities as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td class="amount">$<?php echo number_format((float)$account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total Liabilities</td>
                <td class="amount">$<?php echo number_format($total_liabilities, 2); ?></td>
            </tr>
        </table>
    <?php else: ?>
        <p class="empty-message">No liability accounts found.</p>
    <?php endif; ?>

    <h2>Equity</h2>
    <?php if (!empty($equity)): ?>
        <table>
            <tr>
                <th>Account</th>
                <th>Balance</th>
            </tr>
            <?php foreach ($equity as $account): ?>
                <tr>
                    <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                    <td class="amount">$<?php echo number_format((float)$account['balance'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td>Total Equity</td>
                <td class="amount">$<?php echo number_format($total_equity, 2); ?></td>
            </tr>
        </table>
    <?php else: ?>
        <p class="empty-message">No equity accounts found.</p>
    <?php endif; ?>

    <h2>Summary</h2>
    <table class="summary-table">
        <tr>
            <td>Total Assets</td>
            <td class="amount">$<?php echo number_format($total_assets, 2); ?></td>
        </tr>
        <tr>
            <td>Total Liabilities + Equity</td>
            <td class="amount">$<?php echo number_format(($total_liabilities + $total_equity), 2); ?></td>
        </tr>
    </table>

    <?php if ($isBalanced): ?>
        <div class="status balanced">The balance sheet is balanced.</div>
    <?php else: ?>
        <div class="status not-balanced">The balance sheet is not balanced.</div>
    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>