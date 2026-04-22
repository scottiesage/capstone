<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$transaction_type = trim($_GET['transaction_type'] ?? '');
$category = trim($_GET['category'] ?? '');
$source = trim($_GET['source'] ?? '');
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';

/*
|--------------------------------------------------------------------------
| Validate date inputs
|--------------------------------------------------------------------------
*/
if ($start_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = '';
}

if ($end_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = '';
}

/*
|--------------------------------------------------------------------------
| Validate amount inputs
|--------------------------------------------------------------------------
*/
if ($min_amount !== '' && !is_numeric($min_amount)) {
    $min_amount = '';
}

if ($max_amount !== '' && !is_numeric($max_amount)) {
    $max_amount = '';
}

/*
|--------------------------------------------------------------------------
| Load distinct values for filter dropdowns
|--------------------------------------------------------------------------
*/
$transactionTypes = [];
$categories = [];
$sources = [];

/* Transaction Types */
$typeSql = "
    SELECT DISTINCT transaction_type
    FROM `Transaction`
    WHERE user_id = ?
      AND transaction_type IS NOT NULL
      AND transaction_type <> ''
    ORDER BY transaction_type ASC
";
$typeStmt = $conn->prepare($typeSql);
if ($typeStmt) {
    $typeStmt->bind_param("i", $user_id);
    $typeStmt->execute();
    $typeResult = $typeStmt->get_result();
    while ($row = $typeResult->fetch_assoc()) {
        $transactionTypes[] = $row['transaction_type'];
    }
    $typeStmt->close();
}

/* Categories */
$categorySql = "
    SELECT DISTINCT category
    FROM `Transaction`
    WHERE user_id = ?
      AND category IS NOT NULL
      AND category <> ''
    ORDER BY category ASC
";
$categoryStmt = $conn->prepare($categorySql);
if ($categoryStmt) {
    $categoryStmt->bind_param("i", $user_id);
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[] = $row['category'];
    }
    $categoryStmt->close();
}

/* Sources */
$sourceSql = "
    SELECT DISTINCT source
    FROM `Transaction`
    WHERE user_id = ?
      AND source IS NOT NULL
      AND source <> ''
    ORDER BY source ASC
";
$sourceStmt = $conn->prepare($sourceSql);
if ($sourceStmt) {
    $sourceStmt->bind_param("i", $user_id);
    $sourceStmt->execute();
    $sourceResult = $sourceStmt->get_result();
    while ($row = $sourceResult->fetch_assoc()) {
        $sources[] = $row['source'];
    }
    $sourceStmt->close();
}

/*
|--------------------------------------------------------------------------
| Main transaction query with dynamic filters
|--------------------------------------------------------------------------
*/
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
";

$params = [$user_id];
$types = "i";

if ($start_date !== '') {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $start_date;
    $types .= "s";
}

if ($end_date !== '') {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $end_date;
    $types .= "s";
}

if ($transaction_type !== '') {
    $sql .= " AND t.transaction_type = ?";
    $params[] = $transaction_type;
    $types .= "s";
}

if ($category !== '') {
    $sql .= " AND t.category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($source !== '') {
    $sql .= " AND t.source = ?";
    $params[] = $source;
    $types .= "s";
}

if ($min_amount !== '') {
    $sql .= " AND t.amount >= ?";
    $params[] = (float)$min_amount;
    $types .= "d";
}

if ($max_amount !== '') {
    $sql .= " AND t.amount <= ?";
    $params[] = (float)$max_amount;
    $types .= "d";
}

$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed: " . $conn->error;
    $transactions = false;
    $transaction_count = 0;
    $total_amount = 0.00;
} else {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $transactions = $stmt->get_result();

    $transaction_count = 0;
    $total_amount = 0.00;

    if ($transactions) {
        while ($row = $transactions->fetch_assoc()) {
            $transaction_count++;
            $total_amount += (float)$row['amount'];
            $transactionRows[] = $row;
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ensure transactionRows is always defined
|--------------------------------------------------------------------------
*/
if (!isset($transactionRows)) {
    $transactionRows = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions</title>

    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .filter-group label {
            font-weight: 600;
            color: #1f2937;
            font-size: 0.95rem;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.95rem;
            background: #fff;
            width: 100%;
            box-sizing: border-box;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .summary-row {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .summary-chip {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 14px;
            font-weight: 600;
        }

        .table-wrap {
            overflow-x: auto;
        }
    </style>
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

            <form method="GET" style="margin-bottom: 20px;">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label for="start_date">From Date</label>
                        <input
                            type="date"
                            id="start_date"
                            name="start_date"
                            value="<?php echo htmlspecialchars($start_date); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="end_date">To Date</label>
                        <input
                            type="date"
                            id="end_date"
                            name="end_date"
                            value="<?php echo htmlspecialchars($end_date); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="transaction_type">Transaction Type</label>
                        <select id="transaction_type" name="transaction_type">
                            <option value="">All Types</option>
                            <?php foreach ($transactionTypes as $typeOption): ?>
                                <option value="<?php echo htmlspecialchars($typeOption); ?>" <?php echo ($transaction_type === $typeOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($typeOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $categoryOption): ?>
                                <option value="<?php echo htmlspecialchars($categoryOption); ?>" <?php echo ($category === $categoryOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($categoryOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="source">Source</label>
                        <select id="source" name="source">
                            <option value="">All Sources</option>
                            <?php foreach ($sources as $sourceOption): ?>
                                <option value="<?php echo htmlspecialchars($sourceOption); ?>" <?php echo ($source === $sourceOption) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sourceOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="min_amount">Min Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            id="min_amount"
                            name="min_amount"
                            placeholder="0.00"
                            value="<?php echo htmlspecialchars($min_amount); ?>"
                        >
                    </div>

                    <div class="filter-group">
                        <label for="max_amount">Max Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            id="max_amount"
                            name="max_amount"
                            placeholder="0.00"
                            value="<?php echo htmlspecialchars($max_amount); ?>"
                        >
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="transactions.php" class="btn">Reset</a>
                </div>
            </form>

            <div class="summary-row">
                <div class="summary-chip">Results: <?php echo (int)$transaction_count; ?></div>
                <div class="summary-chip">Total Amount: $<?php echo number_format($total_amount, 2); ?></div>
            </div>

            <?php if (!empty($transactionRows)) : ?>
                <div class="table-wrap">
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

                        <?php foreach ($transactionRows as $row) : ?>
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
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else : ?>
                <p style="margin: 0;">No transactions found for the selected filters.</p>
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