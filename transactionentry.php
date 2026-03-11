<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];

$errorMessage = "";

// Load vendors for this user
$vendorStmt = $conn->prepare("SELECT vendor_id, vendor_name FROM Vendor WHERE user_id = ? ORDER BY vendor_name ASC");
$vendorStmt->bind_param("i", $user_id);
$vendorStmt->execute();
$vendorResult = $vendorStmt->get_result();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $vendor_id = !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null;
    $customer_name = trim($_POST['customer_name']);
    $transaction_type = $_POST['transaction_type'];
    $transaction_date = $_POST['transaction_date'];
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);
    $memo = trim($_POST['memo']);
    $category = trim($_POST['category']);
    $source = trim($_POST['source']);

    $stmt = $conn->prepare("
        INSERT INTO `Transaction`
        (user_id, vendor_id, customer_name, transaction_type, transaction_date, amount, description, memo, category, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "iisssdssss",
        $user_id,
        $vendor_id,
        $customer_name,
        $transaction_type,
        $transaction_date,
        $amount,
        $description,
        $memo,
        $category,
        $source
    );

    if ($stmt->execute()) {
        header("Location: transaction.php");
        exit();
    } else {
        $errorMessage = "Error adding transaction: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Transaction</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 30px;
        }

        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.08);
        }

        .top-links {
            margin-bottom: 15px;
        }

        .back-btn,
        .logout-btn {
            display: inline-block;
            margin-right: 10px;
            padding: 10px 16px;
            text-decoration: none;
            color: white;
            border-radius: 5px;
            font-weight: bold;
        }

        .back-btn {
            background-color: #2c3e50;
        }

        .back-btn:hover {
            background-color: #1f2d3a;
        }

        .logout-btn {
            background-color: #c0392b;
        }

        .logout-btn:hover {
            background-color: #a93226;
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 6px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background-color: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn:hover {
            background-color: #219150;
        }

        .error {
            margin-top: 15px;
            padding: 10px;
            background-color: #f8d7da;
            color: #721c24;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="top-links">
        <a href="transaction.php" class="back-btn">← Back to Transactions</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>

    <h2>Transaction Entry</h2>

    <?php if (!empty($errorMessage)) : ?>
        <div class="error"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <label for="transaction_type">Transaction Type</label>
        <select name="transaction_type" id="transaction_type" required>
            <option value="">-- Select Type --</option>
            <option value="Sale">Sale</option>
            <option value="Purchase">Purchase</option>
            <option value="Payment">Payment</option>
            <option value="Receipt">Receipt</option>
        </select>

        <label for="transaction_date">Date</label>
        <input type="date" name="transaction_date" id="transaction_date" required>

        <label for="amount">Amount</label>
        <input type="number" step="0.01" name="amount" id="amount" required>

        <label for="customer_name">Customer</label>
        <input type="text" name="customer_name" id="customer_name">

        <label for="vendor_id">Vendor</label>
        <select name="vendor_id" id="vendor_id">
            <option value="">-- No Vendor --</option>
            <?php
            if ($vendorResult && $vendorResult->num_rows > 0) {
                while ($vendor = $vendorResult->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($vendor['vendor_id']) . "'>" .
                         htmlspecialchars($vendor['vendor_name']) . "</option>";
                }
            }
            ?>
        </select>

        <label for="description">Description</label>
        <input type="text" name="description" id="description">

        <label for="memo">Memo</label>
        <textarea name="memo" id="memo"></textarea>

        <label for="category">Category</label>
        <input type="text" name="category" id="category">

        <label for="source">Source</label>
        <input type="text" name="source" id="source" value="Manual">

        <button type="submit" class="btn">Save Transaction</button>
    </form>
</div>

</body>
</html>

<?php
$vendorStmt->close();
$conn->close();
?>