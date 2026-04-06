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
    <title>Income Statement</title>
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        .statement-toolbar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .statement-paper {
            background: #fff;
            max-width: 900px;
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

        .statement-section {
            margin-top: 30px;
        }

        .statement-section h2 {
            font-size: 1.2rem;
            margin-bottom: 14px;
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
            font-size: 1.1rem;
            border-top: 3px solid #0f172a;
            border-bottom: 3px double #0f172a;
            padding-top: 14px;
            padding-bottom: 14px;
        }

        .statement-final.income td {
            color: #15803d;
        }

        .statement-final.loss td {
            color: #b91c1c;
        }

        .print-only {
            display: none;
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

            .print-only {
                display: block;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <div class="statement-toolbar no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Save as PDF / Print</button>
    </div>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 900px; margin: 0 auto;">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php else : ?>

        <div class="statement-paper">
            <div class="statement-header">
                <h1>Secure Ledger</h1>
                <p><strong>Income Statement</strong></p>
                <p>For the Period Ending <?php echo date('F d, Y'); ?></p>
            </div>

            <table class="statement-table">
                <tbody>
                    <tr>
                        <td colspan="2" style="padding-bottom: 0; border-bottom: none;">
                            <h2 style="margin: 0 0 10px 0;">Revenue</h2>
                        </td>
                    </tr>

                    <?php if (count($revenues) > 0): ?>
                        <?php foreach ($revenues as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                                <td>$<?php echo number_format($account['balance'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>No revenue accounts found.</td>
                            <td>$0.00</td>
                        </tr>
                    <?php endif; ?>

                    <tr class="statement-subtotal">
                        <td>Total Revenue</td>
                        <td>$<?php echo number_format($total_revenue, 2); ?></td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-top: 28px; padding-bottom: 0; border-bottom: none;">
                            <h2 style="margin: 0 0 10px 0;">Expenses</h2>
                        </td>
                    </tr>

                    <?php if (count($expenses) > 0): ?>
                        <?php foreach ($expenses as $account): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($account['account_name']); ?></td>
                                <td>$<?php echo number_format($account['balance'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>No expense accounts found.</td>
                            <td>$0.00</td>
                        </tr>
                    <?php endif; ?>

                    <tr class="statement-subtotal">
                        <td>Total Expenses</td>
                        <td>$<?php echo number_format($total_expenses, 2); ?></td>
                    </tr>

                    <tr class="statement-final <?php echo $net_income >= 0 ? 'income' : 'loss'; ?>">
                        <td><?php echo $net_income >= 0 ? 'Net Income' : 'Net Loss'; ?></td>
                        <td>$<?php echo number_format(abs($net_income), 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</div>

</body>
</html>