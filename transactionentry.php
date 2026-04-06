<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";
$showDuplicateWarning = false;
$duplicateTransaction = null;

$allowed_types = ['Sale', 'Purchase', 'Payment', 'Receipt', 'Journal Entry'];

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
| Default form values
|--------------------------------------------------------------------------
*/
$vendor_id = '';
$customer_name = '';
$transaction_type = '';
$transaction_date = '';
$amount = '';
$description = '';
$memo = '';
$category = '';
$source = 'Manual';
$debit_account_id = '';
$credit_account_id = '';

/*
|--------------------------------------------------------------------------
| Handle form submission
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
    $force_insert = isset($_POST['force_insert']) && $_POST['force_insert'] === '1';

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

        /*
        |--------------------------------------------------------------------------
        | Duplicate check
        |--------------------------------------------------------------------------
        */
        if (!$force_insert) {
            $duplicateStmt = null;

            if ($vendor_id === null) {
                $duplicateSql = "
                    SELECT
                        t.transaction_id,
                        t.transaction_date,
                        t.amount,
                        t.description,
                        v.vendor_name
                    FROM `Transaction` t
                    LEFT JOIN Vendor v
                        ON t.vendor_id = v.vendor_id
                    WHERE t.user_id = ?
                      AND t.transaction_date = ?
                      AND t.amount = ?
                      AND t.vendor_id IS NULL
                    LIMIT 1
                ";

                $duplicateStmt = $conn->prepare($duplicateSql);

                if ($duplicateStmt) {
                    $duplicateStmt->bind_param(
                        "isd",
                        $user_id,
                        $transaction_date,
                        $amount
                    );
                }
            } else {
                $duplicateSql = "
                    SELECT
                        t.transaction_id,
                        t.transaction_date,
                        t.amount,
                        t.description,
                        v.vendor_name
                    FROM `Transaction` t
                    LEFT JOIN Vendor v
                        ON t.vendor_id = v.vendor_id
                    WHERE t.user_id = ?
                      AND t.transaction_date = ?
                      AND t.amount = ?
                      AND t.vendor_id = ?
                    LIMIT 1
                ";

                $duplicateStmt = $conn->prepare($duplicateSql);

                if ($duplicateStmt) {
                    $duplicateStmt->bind_param(
                        "isdi",
                        $user_id,
                        $transaction_date,
                        $amount,
                        $vendor_id
                    );
                }
            }

            if ($duplicateStmt) {
                $duplicateStmt->execute();
                $duplicateResult = $duplicateStmt->get_result();

                if ($duplicateResult->num_rows > 0) {
                    $showDuplicateWarning = true;
                    $duplicateTransaction = $duplicateResult->fetch_assoc();
                }

                $duplicateStmt->close();
            } else {
                $errorMessage = "Prepare failed during duplicate check: " . $conn->error;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Insert transaction
        |--------------------------------------------------------------------------
        */
        if (empty($errorMessage) && !$showDuplicateWarning) {
            $stmt = $conn->prepare("
                INSERT INTO `Transaction`
                (
                    user_id,
                    vendor_id,
                    customer_name,
                    transaction_type,
                    transaction_date,
                    amount,
                    description,
                    memo,
                    category,
                    source,
                    debit_account_id,
                    credit_account_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "iisssdssssii",
                    $user_id,
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
                    $credit_account_id
                );

                if ($stmt->execute()) {
                    header("Location: transactions.php");
                    exit();
                } else {
                    $errorMessage = "Error adding transaction: " . $stmt->error;
                }

                $stmt->close();
            } else {
                $errorMessage = "Prepare failed: " . $conn->error;
            }
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
    <title>Enter Transaction</title>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1 class="page-title">Transaction Entry</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card" style="max-width: 700px;">
            <p style="color:red; font-weight:bold; margin:0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="card form-card">
        <form method="POST" action="">
            <input type="hidden" name="force_insert" id="force_insert" value="0">

            <label for="transaction_type">Transaction Type</label>
            <select name="transaction_type" id="transaction_type" required>
                <option value="">-- Select Type --</option>
                <?php foreach ($allowed_types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($transaction_type === $type) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($type); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="transaction_date">Date</label>
            <input
                type="date"
                name="transaction_date"
                id="transaction_date"
                value="<?php echo htmlspecialchars($transaction_date); ?>"
                required
            >

            <label for="amount">Amount</label>
            <input
                type="number"
                step="0.01"
                min="0.01"
                name="amount"
                id="amount"
                value="<?php echo htmlspecialchars($amount); ?>"
                required
            >

            <label for="customer_name">Customer</label>
            <input
                type="text"
                name="customer_name"
                id="customer_name"
                value="<?php echo htmlspecialchars($customer_name); ?>"
            >

            <label for="vendor_id">Vendor</label>
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

            <label for="debit_account_id">Debit Account</label>
            <select name="debit_account_id" id="debit_account_id" required>
                <option value="">-- Select Debit Account --</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?php echo (int)$account['account_id']; ?>" <?php echo ((int)$debit_account_id === (int)$account['account_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($account['account_name'] . ' (' . $account['account_type'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="credit_account_id">Credit Account</label>
            <select name="credit_account_id" id="credit_account_id" required>
                <option value="">-- Select Credit Account --</option>
                <?php foreach ($accounts as $account): ?>
                    <option value="<?php echo (int)$account['account_id']; ?>" <?php echo ((int)$credit_account_id === (int)$account['account_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($account['account_name'] . ' (' . $account['account_type'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="description">Description</label>
            <input
                type="text"
                name="description"
                id="description"
                value="<?php echo htmlspecialchars($description); ?>"
            >

            <label for="memo">Memo</label>
            <textarea name="memo" id="memo"><?php echo htmlspecialchars($memo); ?></textarea>

            <label for="category">Category</label>
            <input
                type="text"
                name="category"
                id="category"
                value="<?php echo htmlspecialchars($category); ?>"
            >

            <label for="source">Source</label>
            <input
                type="text"
                name="source"
                id="source"
                value="<?php echo htmlspecialchars($source); ?>"
            >

            <div style="display:flex; gap:12px; margin-top:20px; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">Save Transaction</button>
                <a href="transactions.php" class="btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php if ($showDuplicateWarning): ?>
    <div style="
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 9999;
    ">
        <div style="
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            max-width: calc(100% - 32px);
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        ">
            <h2 style="margin-top:0; color:#dc2626;">Duplicate Transaction</h2>
            <p style="color:#475569;">
                A similar transaction already exists.
            </p>

            <?php if ($duplicateTransaction): ?>
                <div style="
                    background:#f8fafc;
                    padding:16px;
                    border-radius:10px;
                    margin:15px 0;
                ">
                    <p><strong>ID:</strong> <?php echo (int)$duplicateTransaction['transaction_id']; ?></p>
                    <p><strong>Date:</strong> <?php echo htmlspecialchars($duplicateTransaction['transaction_date']); ?></p>
                    <p><strong>Amount:</strong> $<?php echo number_format((float)$duplicateTransaction['amount'], 2); ?></p>
                    <p><strong>Vendor:</strong> <?php echo htmlspecialchars($duplicateTransaction['vendor_name'] ?? 'No Vendor'); ?></p>
                    <p><strong>Description:</strong> <?php echo htmlspecialchars($duplicateTransaction['description'] ?? ''); ?></p>
                </div>
            <?php endif; ?>

            <div style="display:flex; gap:10px; margin-top:20px; flex-wrap: wrap;">
                <button type="button" onclick="proceedInsert()" class="btn btn-primary">Proceed Anyway</button>
                <button type="button" onclick="closeWarning()" class="btn">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function proceedInsert() {
            document.getElementById('force_insert').value = '1';
            document.forms[0].submit();
        }

        function closeWarning() {
            window.location.href = 'transactionentry.php';
        }
    </script>
<?php endif; ?>

</body>
</html>

<?php
$vendorStmt->close();
$accountStmt->close();
$conn->close();
?>