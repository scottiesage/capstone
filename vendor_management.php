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
    <h1 class="page-title">Vendor Management</h1>

    <?php if (!empty($errorMessage)) : ?>
        <div class="card">
            <p style="color: red; font-weight: bold; margin: 0;">
                <?php echo htmlspecialchars($errorMessage); ?>
            </p>
        </div>
    <?php else : ?>

        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap; margin-bottom: 18px;">
                <h2 style="margin: 0;">Vendors</h2>
                <a href="add_vendor.php" class="btn btn-primary">+ Add Vendor</a>
            </div>

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
                                    <span style="color: green; font-weight: 600;">Active</span>
                                <?php else: ?>
                                    <span style="color: #64748b; font-weight: 600;">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($vendor['created_at']); ?></td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="edit_vendor.php?id=<?php echo (int)$vendor['vendor_id']; ?>" class="btn btn-primary">
                                        Edit
                                    </a>

                                    <?php if ((int)$vendor['is_active'] === 1): ?>
                                        <a href="deactivate_vendor.php?id=<?php echo (int)$vendor['vendor_id']; ?>"
                                           class="btn"
                                           onclick="return confirm('Are you sure you want to deactivate this vendor?');">
                                           Deactivate
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-weight: 600; align-self: center;">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p style="margin: 0;">
                    No vendors found. Click <strong>+ Add Vendor</strong> to create your first vendor.
                </p>
            <?php endif; ?>
        </div>

    <?php endif; ?>
</div>

</body>
</html>

<?php
$conn->close();
?>