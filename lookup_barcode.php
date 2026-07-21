<?php
require_once 'includes/auth.php';
require_once 'connect.php';

header('Content-Type: application/json');

$barcode = trim($_GET['barcode'] ?? '');

if ($barcode === '') {
    echo json_encode(['found' => false]);
    exit();
}

$stmt = $db->prepare("SELECT medicineId, medicineName, sellingPrice, quantity FROM medicines WHERE barcode = ? AND quantity > 0 AND expiryDate >= CURDATE()");
$stmt->execute([$barcode]);
$med = $stmt->fetch();

if ($med) {
    echo json_encode(['found' => true, 'medicine' => $med]);
} else {
    echo json_encode(['found' => false]);
}