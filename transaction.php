<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email'];

$stmt = $conn->prepare("
    SELECT
        t.transaction_id,
        t.transaction_type,
        t.transaction_date,
        t.amount,
        t.customer_name,
        v.vendor_name,
        t.description,
        t.memo,
        t.category,
        t.source,
        t.created_at
    FROM `Transaction` t
    LEFT JOIN Vendor v ON t.vendor_id = v.vendor_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.transaction_id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background-color: #f8f9fa;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .welcome {
            font-size: 14px;
            color: #444;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .button-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        h2 {
            margin: 0;
        }

        .add-btn,
        .balance-btn,
        .logout-btn {
            color: white;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            display: inline-block;
        }

        .add-btn {
            background-color: #27ae60;
        }

        .add-btn:hover {
            background-color: #219150;
        }

        .balance-btn {
            background-color: #2980b9;
        }

        .balance-btn:hover {
            background-color: #21618c;
        }

        .logout-btn {
            background-color: #c0392b;
        }

        .logout-btn:hover {
            background-color: #a93226;
        }

        .table-container {
            overflow-x: auto;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 10px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            background-color: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #2c3e50;
            color: white;
            white-space: nowrap;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
        }

        .empty {
            color: #777;
            font-style: italic;
        }

        .no-records {
            text-align: center;
            padding: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="welcome">Logged in as: <?php echo htmlspecialchars($user_email); ?></div>
    </div>

    <div class="page-header">
        <h2>Transaction List</h2>
        <div class="button-group">
            <a href="transactionentry.php" class="add-btn">+ Add Transaction</a>
            <a href="balancesheetgen.php" class="balance-btn">Generate Balance Sheet</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="table-container">
        <table>
            <tr>
                <th>ID</th>
                <th>Type</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Customer</th>
                <th>Vendor</th>
                <th>Description</th>
                <th>Memo</th>
                <th>Category</th>
                <th>Source</th>
                <th>Created At</th>
            </tr>

            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['transaction_id']); ?></td>
                        <td><?php echo htmlspecialchars($row['transaction_type']); ?></td>
                        <td><?php echo htmlspecialchars($row['transaction_date']); ?></td>
                        <td class="amount">$<?php echo number_format((float)$row['amount'], 2); ?></td>
                        <td>
                            <?php echo !empty($row['customer_name']) ? htmlspecialchars($row['customer_name']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['vendor_name']) ? htmlspecialchars($row['vendor_name']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['description']) ? htmlspecialchars($row['description']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['memo']) ? htmlspecialchars($row['memo']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['category']) ? htmlspecialchars($row['category']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td>
                            <?php echo !empty($row['source']) ? htmlspecialchars($row['source']) : "<span class='empty'>N/A</span>"; ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" class="no-records">No transactions found</td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>