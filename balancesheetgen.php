<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

$assets = [];
$liabilities = [];
$equity = [];

$total_assets = 0.00;
$total_liabilities = 0.00;
$total_equity = 0.00;
$net_income = 0.00;
$errorMessage = "";

/*
|--------------------------------------------------------------------------
| Validate selected date
|--------------------------------------------------------------------------
*/
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $as_of_date)) {
    $as_of_date = date('Y-m-d');
}

/*
|--------------------------------------------------------------------------
| Load balance sheet accounts (Assets, Liabilities, Equity)
|--------------------------------------------------------------------------
|
| Balance rules:
| - Asset accounts increase with debits, decrease with credits
| - Liability / Equity accounts increase with credits, decrease with debits
|
| IMPORTANT:
| - Balance Sheet is "as of" a date, so only include transactions where
|   transaction_date <= selected date
|
*/
$balanceSheetSql = "
    SELECT
        a.account_id,
        a.account_code,
        a.account_name,
        a.account_type,
        a.is_active,
        COALESCE(SUM(
            CASE
                WHEN t.debit_account_id = a.account_id THEN
                    CASE
                        WHEN a.account_type = 'Asset' THEN t.amount
                        ELSE -t.amount
                    END
                WHEN t.credit_account_id = a.account_id THEN
                    CASE
                        WHEN a.account_type IN ('Liability', 'Equity') THEN t.amount
                        ELSE -t.amount
                    END
                ELSE 0
            END
        ), 0) AS balance
    FROM Account a
    LEFT JOIN `Transaction` t
        ON (
            a.account_id = t.debit_account_id
            OR a.account_id = t.credit_account_id
        )
        AND t.transaction_date <= ?
    WHERE a.user_id = ?
      AND a.account_type IN ('Asset', 'Liability', 'Equity')
    GROUP BY
        a.account_id,
        a.account_code,
        a.account_name,
        a.account_type,
        a.is_active
    ORDER BY
        FIELD(a.account_type, 'Asset', 'Liability', 'Equity'),
        a.account_code ASC,
        a.account_name ASC
";

$balanceStmt = $conn->prepare($balanceSheetSql);

if (!$balanceStmt) {
    $errorMessage = "Prepare failed while loading balance sheet accounts: " . $conn->error;
} else {
    $balanceStmt->bind_param("si", $as_of_date, $user_id);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();

    while ($row = $balanceResult->fetch_assoc()) {
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

    $balanceStmt->close();
}

/*
|--------------------------------------------------------------------------
| Calculate net income through selected date
|--------------------------------------------------------------------------
|
| Net Income = Revenues - Expenses
|
| Revenue normal balance = credit
| Expense normal balance = debit
|
*/
if (empty($errorMessage)) {
    $netIncomeSql = "
        SELECT
            COALESCE(SUM(
                CASE
                    WHEN a.account_type = 'Revenue' AND t.credit_account_id = a.account_id THEN t.amount
                    WHEN a.account_type = 'Revenue' AND t.debit_account_id = a.account_id THEN -t.amount
                    WHEN a.account_type = 'Expense' AND t.debit_account_id = a.account_id THEN -t.amount
                    WHEN a.account_type = 'Expense' AND t.credit_account_id = a.account_id THEN t.amount
                    ELSE 0
                END
            ), 0) AS net_income
        FROM Account a
        LEFT JOIN `Transaction` t
            ON (
                a.account_id = t.debit_account_id
                OR a.account_id = t.credit_account_id
            )
            AND t.transaction_date <= ?
        WHERE a.user_id = ?
          AND a.account_type IN ('Revenue', 'Expense')
    ";

    $netStmt = $conn->prepare($netIncomeSql);

    if (!$netStmt) {
        $errorMessage = "Prepare failed while calculating net income: " . $conn->error;
    } else {
        $netStmt->bind_param("si", $as_of_date, $user_id);
        $netStmt->execute();
        $netResult = $netStmt->get_result();

        if ($netRow = $netResult->fetch_assoc()) {
            $net_income = (float)$netRow['net_income'];
        }

        $netStmt->close();
    }
}

$total_equity_with_income = $total_equity + $net_income;
$total_liabilities_and_equity = $total_liabilities + $total_equity_with_income;
$is_balanced = abs($total_assets - $total_liabilities_and_equity) < 0.01;

$conn->close();

/*
|--------------------------------------------------------------------------
| Helper for display names
|--------------------------------------------------------------------------
*/
function formatAccountDisplayName(array $account): string
{
    $displayName = '';

    if (!empty($account['account_code'])) {
        $displayName .= $account['account_code'] . ' - ';
    }

    $displayName .= $account['account_name'];

    if ((int)$account['is_active'] !== 1) {
        $displayName .= ' (Inactive)';
    }

    return $displayName;
}

/*
|--------------------------------------------------------------------------
| Helper for formatted statement date
|--------------------------------------------------------------------------
*/
function formatStatementDate(string $date): string
{
    $timestamp = strtotime($date);
    return $timestamp ? date('F d, Y', $timestamp) : date('F d, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet</title>
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        .statement-toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: end;
            margin-bottom: 24px;
        }

        .statement-filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .statement-filter-group label {
            font-weight: 600;
            color: #334155;
        }

        .statement-toolbar input[type="date"] {
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.95rem;
            background: #fff;
            color: #0f172a;
        }

        .statement-paper {
            background: #fff;
            max-width: 1000px;
            margin: 0 auto;
            padding: 48px;
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            border: 1px solid #e2e8f0;
        }

        .statement-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .statement-header h1 {
            margin: 0;
            font-size: 2rem;
            color: #0f172a;
        }

        .statement-header p {
            margin: 6px 0;
            color: #64748b;
        }

        .balance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }

        .statement-section h2 {
            font-size: 1.2rem;
            margin: 0 0 14px 0;
            color: #0f172a;
            border-bottom: 2px solid #cbd5e1;
            padding-bottom: 8px;
        }

        .statement-table {
            width: 100%;
            border-collapse: collapse;
        }

        .statement-table td {
            padding: 10px 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        .statement-table td:last-child {
            text-align: right;
            white-space: nowrap;
            width: 180px;
        }

        .statement-subtotal td {
            font-weight: 700;
            border-top: 2px solid #94a3b8;
            border-bottom: 2px solid #94a3b8;
            padding-top: 12px;
            padding-bottom: 12px;
        }

        .statement-final td {
            font-weight: 800;
            font-size: 1.05rem;
            border-top: 3px solid #0f172a;
            border-bottom: 3px double #0f172a;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .balance-check {
            margin-top: 36px;
            padding-top: 20px;
            border-top: 2px solid #cbd5e1;
        }

        .balance-check p {
            margin: 8px 0;
        }

        .balanced-text {
            color: #15803d;
            font-weight: 700;
        }

        .unbalanced-text {
            color: #b91c1c;
            font-weight: 700;
        }

        @media (max-width: 900px) {
            .balance-grid {
                grid-template-columns: 1fr;
                gap: 28px;
            }

            .statement-paper {
                padding: 28px;
            }

            .statement-toolbar {
                flex-direction: column;
                align-items: stretch;
            }
        }

        @media print {
            .sidebar,
            .fab,
            .statement-toolbar,
            .no-print {
                display: none !important;
            }

            body {
                background: white !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .statement-paper {
                max-width: 100%;
                box-shadow: none;
                border: none;
                border-radius: 0;
                padding: 0;
            }

            .balance-grid {
                grid-template-columns: 1fr 1fr;
                gap: 28px;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <form method="GET" class="statement-toolbar no-print">
        <div class="statement-filter-group">
            <label for="as_of_date">As of Date</label>
            <input
                type="date"
                id="as_of_date"
                name="as_of_date"
                value="<?php echo htmlspecialchars($as_of_date); ?>"
            >
        </div>

        <button type="submit" class="btn btn-primary">Apply Date</button>
        <button type="button" class="btn btn-primary" onclick="window.print()">Save as PDF / Print</button>
    </form>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 1000px; margin: 0 auto;">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php else : ?>
        <div class="statement-paper">
            <div class="statement-header">
                <h1>Secure Ledger</h1>
                <p><strong>Balance Sheet</strong></p>
                <p>As of <?php echo htmlspecialchars(formatStatementDate($as_of_date)); ?></p>
            </div>

            <div class="balance-grid">
                <div class="statement-section">
                    <h2>Assets</h2>
                    <table class="statement-table">
                        <tbody>
                            <?php if (!empty($assets)): ?>
                                <?php foreach ($assets as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(formatAccountDisplayName($account)); ?></td>
                                        <td>$<?php echo number_format($account['balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td>No asset accounts found.</td>
                                    <td>$0.00</td>
                                </tr>
                            <?php endif; ?>

                            <tr class="statement-final">
                                <td>Total Assets</td>
                                <td>$<?php echo number_format($total_assets, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="statement-section">
                    <h2>Liabilities</h2>
                    <table class="statement-table">
                        <tbody>
                            <?php if (!empty($liabilities)): ?>
                                <?php foreach ($liabilities as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(formatAccountDisplayName($account)); ?></td>
                                        <td>$<?php echo number_format($account['balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td>No liability accounts found.</td>
                                    <td>$0.00</td>
                                </tr>
                            <?php endif; ?>

                            <tr class="statement-subtotal">
                                <td>Total Liabilities</td>
                                <td>$<?php echo number_format($total_liabilities, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="height: 28px;"></div>

                    <h2>Equity</h2>
                    <table class="statement-table">
                        <tbody>
                            <?php if (!empty($equity)): ?>
                                <?php foreach ($equity as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(formatAccountDisplayName($account)); ?></td>
                                        <td>$<?php echo number_format($account['balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td>No equity accounts found.</td>
                                    <td>$0.00</td>
                                </tr>
                            <?php endif; ?>

                            <tr>
                                <td>Net Income</td>
                                <td>$<?php echo number_format($net_income, 2); ?></td>
                            </tr>

                            <tr class="statement-subtotal">
                                <td>Total Equity</td>
                                <td>$<?php echo number_format($total_equity_with_income, 2); ?></td>
                            </tr>

                            <tr class="statement-final">
                                <td>Total Liabilities &amp; Equity</td>
                                <td>$<?php echo number_format($total_liabilities_and_equity, 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="balance-check">
                <p><strong>Total Assets:</strong> $<?php echo number_format($total_assets, 2); ?></p>
                <p><strong>Total Liabilities + Equity:</strong> $<?php echo number_format($total_liabilities_and_equity, 2); ?></p>

                <?php if ($is_balanced): ?>
                    <p class="balanced-text">Balanced: Assets = Liabilities + Equity</p>
                <?php else: ?>
                    <p class="unbalanced-text">Not Balanced: Assets do not equal Liabilities + Equity</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>