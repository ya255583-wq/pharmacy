<?php
require_once 'includes/auth.php';
require_once 'connect.php';

//Process POS Checkout Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    $user_id = $_SESSION['userId'];
    $payment_method = $_POST['payment_method'];
    $cart_data = json_decode($_POST['cart_data'], true);

    if (!empty($cart_data)) {
        // Calculate Total Amount Securely
        $total_amount = 0;
        foreach ($cart_data as $item) {
            $total_amount += $item['price'] * $item['qty'];
        }

        // Write to sales table (userId / totalAmount / paymentMethod match your schema)
        $stmt = $db->prepare("INSERT INTO sales (userId, totalAmount, paymentMethod) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $total_amount, $payment_method]);
        $sale_id = $db->lastInsertId();

        // Write to sale_details & update current quantities
        foreach ($cart_data as $item) {
            $subtotal = $item['price'] * $item['qty'];

            // sale_details columns: saleId, medicineId, quantity, price, subtotal
            $stmt_det = $db->prepare("INSERT INTO sale_details (saleId, medicineId, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt_det->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $subtotal]);

            $stmt_upd = $db->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineId = ?");
            $stmt_upd->execute([$item['qty'], $item['id']]);
        }
        echo "<script>alert('Sale Transaction Completed Successfully!'); window.location.href='new_sale.php';</script>";
    }
}

// Fetch all available medicines for grid rendering
$meds = $db->query("SELECT * FROM medicines WHERE quantity > 0 AND expiryDate >= CURDATE()")->fetchAll();
$pageTitle = 'New Sale';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="includes/style_new_sale.css">
    <link rel="stylesheet" href="includes/header.css">
    <link rel="stylesheet" href="includes/dashboard.css">
</head>

<body>

    <div class="app-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="pims-main">
            <?php include 'includes/header.php'; ?>


            <div class="pos-container">
                <div class="inventory-pane">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchBar" placeholder="Search Medicines by Name, ID..." onkeyup="filterMedicines()">
                    </div>

                    <div class="med-grid" id="medicineGrid">
                        <?php foreach ($meds as $row): ?>
                            <div class="med-card" onclick="addToCart(<?= $row['medicineId'] ?>, '<?= addslashes($row['medicineName']) ?>', <?= $row['sellingPrice'] ?>, <?= $row['quantity'] ?>)">
                                <div class="med-name"><?= htmlspecialchars($row['medicineName']) ?></div>
                                <div class="med-meta">
                                    <span>UGX <?= number_format($row['sellingPrice']) ?></span>
                                    <span><?= $row['quantity'] ?> in stock</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-pane">
                    <div style="display:flex; flex-direction:column; flex-grow:1;">
                        <h3>Shopping Cart</h3>
                        <div class="cart-items-list">
                            <div class="cart-header-row"><span>Medicine</span><span>Qty</span><span>Price</span></div>
                            <div id="cartContents" style="text-align: center; color: #a0aec0; padding-top: 30px; font-style: italic;">
                                Cart is empty. Add medicines from the left.
                            </div>
                        </div>
                    </div>

                    <form class="checkout-form" method="POST" id="posForm">
                        <input type="hidden" name="checkout" value="1">
                        <input type="hidden" name="cart_data" id="cartDataInput">

                        <div class="calc-row"><span>Total</span><span>UGX <span id="lblTotal">0</span></span></div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Cash Received</label>
                            <input type="number" id="txtReceived" value="0" oninput="calculateBalance()">
                        </div>

                        <div class="calc-row" style="margin-bottom: 10px;"><span>Balance</span><span>UGX <span id="lblBalance">0</span></span></div>

                        <button type="submit" class="submit-btn" id="btnSubmit" disabled>Complete Sale</button>
                    </form>
                </div>
            </div>

            <div class="footer-credit">© 2026 PIMS Pharmacy LTD. Authorized Personnel Only.</div>

            <script>
                let cart = [];

                function filterMedicines() {
                    let filter = document.getElementById('searchBar').value.toLowerCase();
                    let cards = document.getElementsByClassName('med-card');
                    for (let card of cards) {
                        let name = card.getElementsByClassName('med-name')[0].innerText.toLowerCase();
                        card.style.display = name.includes(filter) ? "" : "none";
                    }
                }

                function addToCart(id, name, price, maxQty) {
                    let existing = cart.find(item => item.id === id);
                    if (existing) {
                        if (existing.qty < maxQty) {
                            existing.qty++;
                        } else {
                            alert("Cannot exceed available warehouse stock bounds.");
                            return;
                        }
                    } else {
                        cart.push({
                            id: id,
                            name: name,
                            price: price,
                            qty: 1
                        });
                    }
                    renderCart();
                }

                function renderCart() {
                    let container = document.getElementById('cartContents');
                    if (cart.length === 0) {
                        container.innerHTML = `<div style="text-align: center; color: #a0aec0; padding-top: 30px; font-style: italic;">Cart is empty. Add medicines from the left.</div>`;
                        document.getElementById('btnSubmit').disabled = true;
                        document.getElementById('btnSubmit').classList.remove('ready');
                        return;
                    }

                    let html = '';
                    let total = 0;
                    cart.forEach(item => {
                        let subtotal = item.price * item.qty;
                        total += subtotal;
                        html += `<div class="cart-item-row">
            <span>${item.name}</span>
            <span>${item.qty}</span>
            <span>${subtotal.toLocaleString()}</span>
        </div>`;
                    });

                    container.innerHTML = html;
                    document.getElementById('lblTotal').innerText = total.toLocaleString();
                    document.getElementById('cartDataInput').value = JSON.stringify(cart);
                    document.getElementById('btnSubmit').disabled = false;
                    document.getElementById('btnSubmit').classList.add('ready');
                    calculateBalance();
                }

                function calculateBalance() {
                    let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
                    let received = parseFloat(document.getElementById('txtReceived').value) || 0;
                    let balance = received - total;
                    document.getElementById('lblBalance').innerText = balance >= 0 ? balance.toLocaleString() : 0;
                }
            </script>

        </main>
    </div>

</body>

</html>