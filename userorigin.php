<?php
require_once 'includes/auth.php';
require_once 'connect.php';

$deleteError = null;
$deleteSuccess = false;

/* ---------------------------------------------------------
   Handle delete request
   --------------------------------------------------------- */
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];

    if ($deleteId === (int)$_SESSION['userId']) {
        $deleteError = "You can't delete your own account while logged in as it.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM users WHERE userId = ?");
            $stmt->execute([$deleteId]);
            $deleteSuccess = true;
        } catch (PDOException $e) {
            // users.userId is referenced by sales.userId with no cascade rule,
            // so deleting a user who has recorded sales will hit a foreign
            // key constraint instead of silently corrupting sales history.
            $deleteError = "This user can't be deleted because they have sales records linked to their account.";
        }
    }
}

$result = $db->query("SELECT userId, fullName, userName, role, status FROM users ORDER BY fullName ASC")->fetchAll();
$total_accounts = count($result);

$pageTitle = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_users.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if ($deleteSuccess): ?>
                <div class="pos-alert pos-alert-success">User deleted successfully.</div>
            <?php endif; ?>
            <?php if ($deleteError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($deleteError) ?></div>
            <?php endif; ?>

            <div class="users-toolbar">
                <div class="users-count"><?= $total_accounts ?> user account(s)</div>
                <a href="add_user.php" class="users-add-btn">+ Add User</a>
            </div>

            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Status</th>
                            
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($result)): ?>
                            <tr><td colspan="5" class="empty-row">No users found.</td></tr>
                        <?php else: foreach ($result as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['fullName']) ?></td>
                                <td><?= htmlspecialchars($row['userName']) ?></td>
                                <td><?= htmlspecialchars($row['role']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($row['status']) === 'active' ? 'active' : '' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td class="users-actions">
                                    <a href="users.php?delete=<?= $row['userId'] ?>"
                                       title="Delete"
                                       class="users-delete"
                                       onclick="return confirm('Delete <?= htmlspecialchars(addslashes($row['fullName'])) ?>? This cannot be undone.');"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

</body>
</html>