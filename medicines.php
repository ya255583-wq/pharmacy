<?php
require_once 'includes/auth.php';
require_once 'connect.php';

/* ---------------------------------------------------------
   Read filters from the URL (all optional, all safe defaults)
   --------------------------------------------------------- */
$search      = trim($_GET['q'] ?? '');
$categoryId  = $_GET['category'] ?? '';
$stockStatus = $_GET['stock_status'] ?? '';   // '', 'low', 'expired', 'ok'
$expiryRange = $_GET['expiry_range'] ?? '';   // '', '30', '90', 'expired'

/* ---------------------------------------------------------
   Build the query dynamically but safely (prepared statement,
   parts added conditionally instead of concatenating user input)
   --------------------------------------------------------- */
$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(m.medicineName LIKE :search OR m.medicineId = :searchId)";
    $params['search']   = "%$search%";
    $params['searchId'] = is_numeric($search) ? (int)$search : 0;
}

if ($categoryId !== '') {
    $where[] = "m.categoryId = :categoryId";
    $params['categoryId'] = $categoryId;
}

if ($stockStatus === 'low') {
    $where[] = "m.quantity <= m.minStock AND m.expiryDate >= CURDATE()";
} elseif ($stockStatus === 'expired') {
    $where[] = "m.expiryDate < CURDATE()";
} elseif ($stockStatus === 'ok') {
    $where[] = "m.quantity > m.minStock AND m.expiryDate >= CURDATE()";
}

if ($expiryRange === '30') {
    $where[] = "m.expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($expiryRange === '90') {
    $where[] = "m.expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
} elseif ($expiryRange === 'expired') {
    $where[] = "m.expiryDate < CURDATE()";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
    SELECT m.medicineId, m.medicineName, c.categoryName, s.supplierName,
           m.quantity, m.minStock, m.price, m.sellingPrice, m.expiryDate
    FROM medicines m
    LEFT JOIN categories c ON c.categoryId = m.categoryId
    LEFT JOIN suppliers s  ON s.supplierId  = m.supplierId
    $whereSql
    ORDER BY m.medicineId ASC
";

$stmt = $db->prepare($sql);
// $stmt->execute($params);
$medicines = $stmt->fetchAll();

// For the Category filter dropdown
$categories = $db->query("SELECT categoryId, categoryName FROM categories ORDER BY categoryName")->fetchAll();

/* ---------------------------------------------------------
   Helpers
   --------------------------------------------------------- */
function medicineCode($id) {
    return 'MED-' . strtoupper(base_convert($id, 10, 36));
}

function medicineStatus($row) {
    $expired = strtotime($row['expiryDate']) < strtotime('today');
    $low     = $row['quantity'] <= $row['minStock'];

    if ($expired) {
        return ['label' => 'Expired', 'class' => 'badge-expired'];
    }
    // Expiring within 30 days
    $daysLeft = (strtotime($row['expiryDate']) - strtotime('today')) / 86400;
    if ($daysLeft <= 30) {
        return ['label' => 'Expiring Soon', 'class' => 'badge-warn'];
    }
    if ($low) {
        return ['label' => 'Low Stock', 'class' => 'badge-warn'];
    }
    return ['label' => 'OK', 'class' => 'badge-ok'];
}

$pageTitle = 'Medicines';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_medicines.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <!-- Search bar -->
            <form method="GET" class="med-search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 640 640">!Font Awesome Free v7.3.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc.<path d="M544 513L397.2 364.2C417.2 336.3 429.1 302 429.1 265C429.1 171.9 354.4 96.1 262.6 96.1C170.7 96 96 171.8 96 264.9C96 358 170.7 433.8 262.5 433.8C302.3 433.8 338.8 419.6 367.5 395.9L513.5 544L544 513zM262.5 394.8C191.9 394.8 134.4 336.5 134.4 264.9C134.4 193.3 191.9 135 262.5 135C333.1 135 390.6 193.3 390.6 264.9C390.6 336.5 333.2 394.8 262.5 394.8z"/></svg>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search Medicines by Name, ID">
                <button type="submit" class="med-search-btn">Search</button>
            </form>

            <!-- Filters -->
            <form method="GET" class="med-filters-bar">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">

                <div class="med-filter-group">
                    <label>Category</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['categoryId'] ?>" <?= $categoryId == $cat['categoryId'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['categoryName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="med-filter-group">
                    <label>Stock status</label>
                    <select name="stock_status" onchange="this.form.submit()">
                        <option value="">All stocks</option>
                        <option value="ok" <?= $stockStatus === 'ok' ? 'selected' : '' ?>>OK</option>
                        <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="expired" <?= $stockStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>

                <div class="med-filter-group">
                    <label>Expiry Range</label>
                    <select name="expiry_range" onchange="this.form.submit()">
                        <option value="">Any expiry</option>
                        <option value="30" <?= $expiryRange === '30' ? 'selected' : '' ?>>Next 30 days</option>
                        <option value="90" <?= $expiryRange === '90' ? 'selected' : '' ?>>Next 90 days</option>
                        <option value="expired" <?= $expiryRange === 'expired' ? 'selected' : '' ?>>Already expired</option>
                    </select>
                </div>

                <a href="add_medicine.php" class="med-add-btn">+ Add Medicine</a>
            </form>

            <!-- Table -->
            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Stock</th>
                            <th>Buying Price</th>
                            <th>Selling Price</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <!-- <th>Actions</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr><td colspan="10" class="empty-row">No medicines match your filters.</td></tr>
                        <?php else: foreach ($medicines as $m): $status = medicineStatus($m); ?>
                            <tr>
                                <td><?= medicineCode($m['medicineId']) ?></td>
                                <td><?= htmlspecialchars($m['medicineName']) ?></td>
                                <td><?= htmlspecialchars($m['categoryName'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($m['supplierName'] ?? 'N/A') ?></td>
                                <td><?= (int)$m['quantity'] ?></td>
                                <td>UGX <?= number_format($m['price']) ?></td>
                                <td>UGX <?= number_format($m['sellingPrice']) ?></td>
                                <td><?= date('d M Y', strtotime($m['expiryDate'])) ?></td>
                                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                                <!-- <td class="med-actions">
                                    <a href="view_medicine.php?id=<?= $m['medicineId'] ?>" title="View">&#128065;</a>
                                    <a href="edit_medicine.php?id=<?= $m['medicineId'] ?>" title="Edit">&#9998;</a>
                                    <a href="delete_medicine.php?id=<?= $m['medicineId'] ?>" title="Delete" class="med-delete" onclick="return confirm('Delete this medicine?');">&#128465;</a>
                                </td> -->
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