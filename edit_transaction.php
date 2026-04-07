<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";

$allowed_types = ['Sale', 'Purchase', 'Payment', 'Receipt', 'Journal Entry'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: transactions.php");
    exit();
}

$transaction_id = (int)$_GET['id'];

/*
|--------------------------------------------------------------------------
| Load vendors
|--------------------------------------------------------------------------
*/
$vendorStmt = $conn->prepare("
    SELECT vendor_id, vendor_name
    FROM Vendor
    WHERE user_id = ?
    ORDER BY vendor_name ASC
");
$vendorStmt->bind_param("i", $user_id);
$vendorStmt->execute();
$vendorResult = $vendorStmt->get_result();

/*
|--------------------------------------------------------------------------
| Load accounts
|--------------------------------------------------------------------------
*/
$accountStmt = $conn->prepare("
    SELECT account_id, account_name, account_type
    FROM Account
    WHERE user_id = ?
    ORDER BY account_type ASC, account_name ASC
");
$accountStmt->bind_param("i", $user_id);
$accountStmt->execute();
$accountResult = $accountStmt->get_result();

$accounts = [];
while ($row = $accountResult->fetch_assoc()) {
    $accounts[] = $row;
}

/*
|--------------------------------------------------------------------------
| Load transaction
|--------------------------------------------------------------------------
*/
$loadStmt = $conn->prepare("
    SELECT *
    FROM `Transaction`
    WHERE transaction_id = ? AND user_id = ?
");
$loadStmt->bind_param("ii", $transaction_id, $user_id);
$loadStmt->execute();
$transactionResult = $loadStmt->get_result();
$transaction = $transactionResult->fetch_assoc();
$loadStmt->close();

if (!$transaction) {
    $vendorStmt->close();
    $accountStmt->close();
    $conn->close();
    header("Location: transactions.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| Default values
|--------------------------------------------------------------------------
*/
$vendor_id = $transaction['vendor_id'];
$customer_name = $transaction['customer_name'] ?? '';
$transaction_type = $transaction['transaction_type'] ?? '';
$transaction_date = $transaction['transaction_date'] ?? '';
$amount = $transaction['amount'] ?? '';
$description = $transaction['description'] ?? '';
$memo = $transaction['memo'] ?? '';
$category = $transaction['category'] ?? '';
$source = $transaction['source'] ?? 'Manual';
$debit_account_id = $transaction['debit_account_id'] ?? '';
$credit_account_id = $transaction['credit_account_id'] ?? '';

/*
|--------------------------------------------------------------------------
| Handle update
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $vendor_id = isset($_POST['vendor_id']) && $_POST['vendor_id'] !== '' ? (int)$_POST['vendor_id'] : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $transaction_type = trim($_POST['transaction_type'] ?? '');
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $memo = trim($_POST['memo'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $source = trim($_POST['source'] ?? 'Manual');
    $debit_account_id = isset($_POST['debit_account_id']) ? (int)$_POST['debit_account_id'] : 0;
    $credit_account_id = isset($_POST['credit_account_id']) ? (int)$_POST['credit_account_id'] : 0;

    if (!in_array($transaction_type, $allowed_types)) {
        $errorMessage = "Invalid transaction type.";
    } elseif (empty($transaction_date)) {
        $errorMessage = "Transaction date is required.";
    } elseif ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $errorMessage = "Amount must be a valid number greater than 0.";
    } elseif ($debit_account_id <= 0 || $credit_account_id <= 0) {
        $errorMessage = "Both debit and credit accounts are required.";
    } elseif ($debit_account_id === $credit_account_id) {
        $errorMessage = "Debit and credit accounts cannot be the same.";
    } else {
        $amount = (float)$amount;

        $stmt = $conn->prepare("
            UPDATE `Transaction`
            SET
                vendor_id = ?,
                customer_name = ?,
                transaction_type = ?,
                transaction_date = ?,
                amount = ?,
                description = ?,
                memo = ?,
                category = ?,
                source = ?,
                debit_account_id = ?,
                credit_account_id = ?
            WHERE transaction_id = ? AND user_id = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "isssdssssiiii",
                $vendor_id,
                $customer_name,
                $transaction_type,
                $transaction_date,
                $amount,
                $description,
                $memo,
                $category,
                $source,
                $debit_account_id,
                $credit_account_id,
                $transaction_id,
                $user_id
            );

            if ($stmt->execute()) {
                header("Location: transactions.php");
                exit();
            } else {
                $errorMessage = "Error updating transaction: " . $stmt->error;
            }

            $stmt->close();
        } else {
            $errorMessage = "Prepare failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="base.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Transaction</title>
</head>
<body>

<h2>Edit Transaction</h2>

<p>
    <a href="transactions.php">Back to Transactions</a> |
    <a href="dashboard.php">Dashboard</a> |
    <a href="logout.php">Logout</a>
</p>

<?php if (!empty($errorMessage)) : ?>
    <p style="color:red;"><?php echo htmlspecialchars($errorMessage); ?></p>
<?php endif; ?>

<form method="POST" action="">
    <label for="transaction_type">Transaction Type</label><br>
    <select name="transaction_type" id="transaction_type" required>
        <option value="">-- Select Type --</option>
        <?php foreach ($allowed_types as $type): ?>
            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($transaction_type === $type) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($type); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="transaction_date">Date</label><br>
    <input type="date" name="transaction_date" id="transaction_date" value="<?php echo htmlspecialchars($transaction_date); ?>" required>
    <br><br>

    <label for="amount">Amount</label><br>
    <input type="number" step="0.01" min="0.01" name="amount" id="amount" value="<?php echo htmlspecialchars($amount); ?>" required>
    <br><br>

    <label for="customer_name">Customer</label><br>
    <input type="text" name="customer_name" id="customer_name" value="<?php echo htmlspecialchars($customer_name); ?>">
    <br><br>

    <label for="vendor_id">Vendor</label><br>
    <select name="vendor_id" id="vendor_id">
        <option value="">-- No Vendor --</option>
        <?php
        if ($vendorResult && $vendorResult->num_rows > 0) {
            while ($vendor = $vendorResult->fetch_assoc()) {
                $selected = ($vendor_id !== null && $vendor_id !== '' && (int)$vendor_id === (int)$vendor['vendor_id']) ? 'selected' : '';
                echo "<option value='" . htmlspecialchars($vendor['vendor_id']) . "' $selected>" .
                     htmlspecialchars($vendor['vendor_name']) .
                     "</option>";
            }
        }
        ?>
    </select>
    <br><br>

    <label for="debit_account_id">Debit Account</label><br>
    <select name="debit_account_id" id="debit_account_id" required>
        <option value="">-- Select Debit Account --</option>
        <?php foreach ($accounts as $account): ?>
            <option value="<?php echo (int)$account['account_id']; ?>" <?php echo ((int)$debit_account_id === (int)$account['account_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($account['account_name'] . ' (' . $account['account_type'] . ')'); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="credit_account_id">Credit Account</label><br>
    <select name="credit_account_id" id="credit_account_id" required>
        <option value="">-- Select Credit Account --</option>
        <?php foreach ($accounts as $account): ?>
            <option value="<?php echo (int)$account['account_id']; ?>" <?php echo ((int)$credit_account_id === (int)$account['account_id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($account['account_name'] . ' (' . $account['account_type'] . ')'); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>

    <label for="description">Description</label><br>
    <input type="text" name="description" id="description" value="<?php echo htmlspecialchars($description); ?>">
    <br><br>

    <label for="memo">Memo</label><br>
    <textarea name="memo" id="memo"><?php echo htmlspecialchars($memo); ?></textarea>
    <br><br>

    <label for="category">Category</label><br>
    <input type="text" name="category" id="category" value="<?php echo htmlspecialchars($category); ?>">
    <br><br>

    <label for="source">Source</label><br>
    <input type="text" name="source" id="source" value="<?php echo htmlspecialchars($source); ?>">
    <br><br>

    <button type="submit">Update Transaction</button>
</form>

</body>
</html>

<?php
$vendorStmt->close();
$accountStmt->close();
$conn->close();
?>