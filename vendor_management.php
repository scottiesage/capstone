<?php
include 'auth_check.php';
include 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errorMessage = "";
$vendors = [];

$sql = "
    SELECT
        vendor_id,
        vendor_name,
        email,
        phone,
        city,
        state,
        is_active,
        created_at
    FROM Vendor
    WHERE user_id = ?
    ORDER BY vendor_name ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = "Prepare failed: " . $conn->error;
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Management</title>
    <link rel="stylesheet" href="base.css">
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="main-content">
    <h1>Vendor Management</h1>

    <p>
        <a href="add_vendor.php" class="btn">+ Add Vendor</a>
    </p>

    <?php if (!empty($errorMessage)) : ?>
        <p style="color: red; font-weight: bold;">
            <?php echo htmlspecialchars($errorMessage); ?>
        </p>
    <?php else : ?>

        <?php if (count($vendors) > 0): ?>
            <table>
                <tr>
                    <th>Vendor Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>State</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>

                <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($vendor['vendor_name']); ?></td>
                        <td><?php echo htmlspecialchars($vendor['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($vendor['phone'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($vendor['city'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($vendor['state'] ?? ''); ?></td>
                        <td>
                            <?php if ((int)$vendor['is_active'] === 1): ?>
                                Active
                            <?php else: ?>
                                Inactive
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($vendor['created_at']); ?></td>
                        <td>
                            <a href="edit_vendor.php?id=<?php echo (int)$vendor['vendor_id']; ?>" class="btn btn-primary">Edit</a>

                            <?php if ((int)$vendor['is_active'] === 1): ?>
                                <form method="POST" action="deactivate_vendor.php" style="display: inline;">
                                    <input type="hidden" name="vendor_id" value="<?php echo (int)$vendor['vendor_id']; ?>">
                                    <button type="submit"
                                            class="btn"
                                            onclick="return confirm('Are you sure you want to deactivate this vendor?');">
                                        Deactivate
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-weight: 600; align-self: center;">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php else: ?>
            <p>No vendors found. Click <strong>+ Add Vendor</strong> to create your first vendor.</p>
        <?php endif; ?>

    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>