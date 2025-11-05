<?php
include("../../db.php"); // Assuming this includes your database connection ($conn)
session_start();

// Initialize variables
$is_reorder = false;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$reorder_qty = isset($_GET['qty']) ? (int)$_GET['qty'] : null;

$po_data = [
    'po_number' => 'PO-' . date('Y') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT), // Mock PO Number
    'date_created' => date('Y-m-d'),
    'supplier_name' => '',
    'supplier_email' => '',
    'product_id' => $product_id,
    'product_name' => '',
    'product_description' => '',
    'quantity' => $reorder_qty,
    'unit_price' => 0.00,
    'total' => 0.00,
    'estimated_date' => date('Y-m-d', strtotime('+7 days')), // 7 days estimated ship time
];

// --- Logic for Reorder Pre-fill (Data Synchronization) ---
if ($product_id && $reorder_qty > 0) {
    $is_reorder = true;
    
    // 1. Fetch Product and Supplier details
    $product_query = "
        SELECT 
            p.Name, 
            p.Description, 
            p.Price, 
            s.Company_Name, 
            s.Email,
            s.Supplier_id
        FROM Product p
        JOIN Supplier s ON p.Supplier_id = s.Supplier_id
        WHERE p.Product_id = $product_id
    ";
    
    $product_result = $conn->query($product_query);

    if ($product_result && $product_result->num_rows > 0) {
        $row = $product_result->fetch_assoc();
        
        $po_data['supplier_id'] = $row['Supplier_id']; // Store ID to identify the supplier
        $po_data['supplier_name'] = htmlspecialchars($row['Company_Name']);
        $po_data['supplier_email'] = htmlspecialchars($row['Email']);
        $po_data['product_name'] = htmlspecialchars($row['Name']);
        $po_data['product_description'] = htmlspecialchars($row['Description']);
        $po_data['unit_price'] = (float)$row['Price'];
        $po_data['total'] = $po_data['unit_price'] * $po_data['quantity'];
    } else {
        // Handle case where product ID is invalid
        $is_reorder = false;
        $product_id = null;
        $reorder_qty = null;
    }
}

// Helper to format currency
function formatCurrency($amount) {
    return number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ALG | Create Purchase Order</title>
    <style>
        /* --- Styles Reused from Previous Files --- */
        body { margin: 0; font-family: sans-serif; background: #e0e0e0; color: #333; }
        header { display: flex; align-items: center; justify-content: space-between; background: #3e3e3e; color: white; padding: 10px 30px; }
        header img { height: 40px; }
        nav a { color: white; text-decoration: none; font-weight: 600; padding: 6px 14px; border-radius: 20px; }
        nav a.active, nav a:hover { background: #d9d9d9; color: #000; }
        .container { display: flex; height: calc(100vh - 60px); }
        .sidebar { width: 250px; background: #d9d9d9; padding: 20px; box-shadow: 2px 0 6px rgba(0,0,0,0.1); }
        .sidebar h3 { margin-top: 0; font-size: 18px; }
        .sidebar a { display: block; margin: 10px 0; color: #333; text-decoration: none; font-weight: 600; }
        .content { flex: 1; padding: 30px; background: #f5f5f5; overflow-y: auto; }
        
        /* --- Invoice/Form Specific Styles (Matching image_5a0361.png) --- */
        .invoice-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 1200px; margin: 0 auto; }
        h1.po-title { margin-top: 0; text-align: center; }
        
        .header-section { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
        .header-section h2 { margin: 0; font-size: 2em; }
        
        .bill-ship-section { display: flex; gap: 40px; margin-bottom: 30px; }
        .bill-to, .shipment-info { flex: 1; border: 1px solid #ccc; padding: 15px; border-radius: 5px; }
        .bill-to h3, .shipment-info h3 { margin-top: 0; color: #444; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 10px; }
        
        .field-group { display: flex; align-items: center; margin-bottom: 8px; font-size: 0.9em; }
        .field-group label { width: 120px; font-weight: 600; }
        .field-group input { flex-grow: 1; padding: 5px; border: 1px solid #ccc; border-radius: 3px; }
        
        /* --- Items Table Styling --- */
        .items-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .items-table th, .items-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .items-table th { background: #f7f7f7; font-weight: bold; }
        .items-table td input { width: 95%; border: none; padding: 0; }
        .items-table .total-col { text-align: right; font-weight: 600; }
        
        .summary-totals { width: 300px; float: right; margin-top: 20px; }
        .summary-totals div { display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #eee; }
        .summary-totals .grand-total { font-weight: bold; border-top: 2px solid #333; margin-top: 5px; padding-top: 10px; }
        
        /* --- Actions Buttons --- */
        .actions-bar { text-align: center; margin-top: 50px; }
        .actions-bar button { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; margin: 0 10px; }
        .btn-send { background: #4f9bd2; color: white; }
        .btn-approve { background: #77c66c; color: white; }
        .btn-cancel { background: #dc3545; color: white; }
        .top-buttons button { background: #555; color: white; padding: 5px 10px; font-size: 0.9em; }

        /* Add dynamic class for highlighting reorder status */
        .reorder-notice { background-color: #fff3cd; color: #856404; padding: 10px; margin-bottom: 20px; border: 1px solid #ffeeba; border-radius: 5px; text-align: center; }

    </style>
</head>
<body>

<header>
    <img src="../../images/alg-logo-black.png" alt="ALG Logo" height="40">
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php" class="active">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
        <a href="orders.php">Orders</a>
        <a href="../logout.php">Logout</a>
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Inventory</h3>
        <a href="products.php">Products</a>
        <a href="purchases.php">Purchased</a>
        <a href="supplier.php">Supplier</a>
        <a href="#">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar" style="justify-content: flex-end;">
            <div class="top-buttons">
                <button onclick="window.location.href='reorder.php'">Return</button>
                <button onclick="window.print()">Print</button>
                <button>Export Exel</button>
            </div>
        </div>
        
        <?php if ($is_reorder): ?>
            <div class="reorder-notice">
                ⚠️ **REORDER ALERT:** This Purchase Order has been pre-filled from a Low Stock Alert. Please review details before sending.
            </div>
        <?php endif; ?>

        <div class="invoice-container">
            <div class="header-section">
                <div>
                    <img src="../../images/alg-logo-black.png" alt="ALG" height="30"><br>
                    <small>[Address]</small><br>
                    <small>[City, ST ZIP]</small><br>
                    <small>Phone:[000-000-0000]</small><br>
                    <small>Website: AlgRoofing.com</small>
                </div>
                <h2>INVOICE</h2>
                <div style="text-align: right; font-size: 0.9em;">
                    <div style="font-weight: bold;">Date Created: <?= htmlspecialchars($po_data['date_created']) ?></div>
                    <div>PO No.: **<?= htmlspecialchars($po_data['po_number']) ?>**</div>
                    <div>Supplier ID: <?= htmlspecialchars($po_data['supplier_id'] ?? 'N/A') ?></div>
                    <div>Estimated Date: <?= htmlspecialchars($po_data['estimated_date']) ?></div>
                </div>
            </div>

            <form method="POST" action="save_purchase_order.php">
                <div class="bill-ship-section">
                    <div class="bill-to">
                        <h3>BILL TO (Supplier)</h3>
                        <div class="field-group">
                            <label>PO Number:</label>
                            <input type="hidden" name="po_number" value="<?= htmlspecialchars($po_data['po_number']) ?>">
                            <span style="font-weight: bold;"><?= htmlspecialchars($po_data['po_number']) ?></span>
                        </div>
                        <div class="field-group">
                            <label for="supplier_name">Company Name:</label>
                            <input type="text" id="supplier_name" name="supplier_name" value="<?= $po_data['supplier_name'] ?>" <?= $is_reorder ? 'readonly' : '' ?>>
                        </div>
                        <div class="field-group">
                            <label for="supplier_email">Email:</label>
                            <input type="email" id="supplier_email" name="supplier_email" value="<?= $po_data['supplier_email'] ?>" <?= $is_reorder ? 'readonly' : '' ?>>
                        </div>
                        <div class="field-group">
                            <label for="contact_name">Contact Name:</label>
                            <input type="text" id="contact_name" name="contact_name">
                        </div>
                        <div class="field-group">
                            <label for="address">Address:</label>
                            <input type="text" id="address" name="address" value="[Supplier Address]">
                        </div>
                    </div>

                    <div class="shipment-info">
                        <h3>SHIPMENT INFORMATION</h3>
                        <div class="field-group"><label for="payment_terms">Payment Terms:</label><input type="text" id="payment_terms" name="payment_terms" value="Net 30"></div>
                        <div class="field-group"><label for="currency">Currency:</label><input type="text" id="currency" name="currency" value="PHP"></div>
                        <div class="field-group"><label for="est_ship_date">Est. Ship Date:</label><input type="date" id="est_ship_date" name="est_ship_date" value="<?= htmlspecialchars($po_data['estimated_date']) ?>"></div>
                        <div class="field-group"><label for="mode">Mode of Trans.:</label><input type="text" id="mode" name="mode" value="Truck/LCL"></div>
                        <div class="field-group"><label for="carrier">Carrier:</label><input type="text" id="carrier" name="carrier" value="[Carrier Name]"></div>
                        </div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 20%;">Item</th>
                            <th style="width: 35%;">Description</th>
                            <th style="width: 10%;">Quantity</th>
                            <th style="width: 15%;">Unit Price</th>
                            <th style="width: 15%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($is_reorder): ?>
                        <tr>
                            <td>1</td>
                            <td><input type="text" name="item_name[]" value="<?= $po_data['product_name'] ?>" readonly></td>
                            <td><input type="text" name="item_desc[]" value="<?= $po_data['product_description'] ?>" readonly></td>
                            <td><input type="number" name="item_qty[]" value="<?= $po_data['quantity'] ?>" onchange="calculateTotal(this)" data-price="<?= $po_data['unit_price'] ?>"></td>
                            <td><input type="number" name="item_price[]" value="<?= formatCurrency($po_data['unit_price']) ?>" readonly></td>
                            <td class="total-col" data-total-id="total_1"><?= formatCurrency($po_data['total']) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr>
                            <td>1</td>
                            <td><input type="text" name="item_name[]" value=""></td>
                            <td><input type="text" name="item_desc[]" value=""></td>
                            <td><input type="number" name="item_qty[]" value="" onchange="calculateTotal(this)"></td>
                            <td><input type="number" name="item_price[]" value=""></td>
                            <td class="total-col" data-total-id="total_1">0.00</td>
                        </tr>
                        <?php endif; ?>
                        <?php for ($i = $is_reorder ? 2 : 2; $i <= 6; $i++): ?>
                        <tr>
                            <td><?= $i ?></td>
                            <td><input type="text" name="item_name[]" value=""></td>
                            <td><input type="text" name="item_desc[]" value=""></td>
                            <td><input type="number" name="item_qty[]" value="" onchange="calculateTotal(this)"></td>
                            <td><input type="number" name="item_price[]" value="" onchange="calculateTotal(this)"></td>
                            <td class="total-col" data-total-id="total_<?= $i ?>">0.00</td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                
                <div class="summary-totals">
                    <div>
                        <span>SUBTOTAL:</span>
                        <span id="subtotal_display"><?= $is_reorder ? formatCurrency($po_data['total']) : '0.00' ?></span>
                        <input type="hidden" id="subtotal_input" name="subtotal" value="<?= $is_reorder ? $po_data['total'] : '0.00' ?>">
                    </div>
                    <div>
                        <span>DISCOUNT:</span>
                        <input type="number" id="discount_input" name="discount" value="0.00" style="width: 100px; text-align: right;" onchange="updateGrandTotal()">
                    </div>
                    <div class="grand-total">
                        <span>TOTAL:</span>
                        <span id="grand_total_display"><?= $is_reorder ? formatCurrency($po_data['total']) : '0.00' ?></span>
                        <input type="hidden" id="grand_total_input" name="grand_total" value="<?= $is_reorder ? $po_data['total'] : '0.00' ?>">
                    </div>
                </div>
                
                <div style="clear: both;"></div>

                <div class="actions-bar">
                    <button type="submit" name="action" value="approve" class="btn-approve">Approved</button>
                    <button type="submit" name="action" value="send" class="btn-send">Send to Supplier</button>
                    <button type="button" class="btn-cancel" onclick="window.location.href='reorder.php'">Cancel</button>
                </div>
            </form>
        </div>
    </section>
</div>

<script>
    function calculateTotal(inputElement) {
        const row = inputElement.closest('tr');
        const qtyInput = row.querySelector('input[name^="item_qty"]');
        const priceInput = row.querySelector('input[name^="item_price"]');
        const totalCell = row.querySelector('.total-col');

        // Check if data-price attribute exists (for reorder items)
        let price = parseFloat(priceInput.value) || parseFloat(priceInput.dataset.price) || 0;
        let qty = parseFloat(qtyInput.value) || 0;
        
        let rowTotal = price * qty;
        
        // Update the total cell display
        totalCell.innerText = rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        
        // Recalculate Subtotal and Grand Total
        updateGrandTotal();
    }

    function updateGrandTotal() {
        let subtotal = 0;
        document.querySelectorAll('.total-col').forEach(cell => {
            // Remove commas and parse float
            subtotal += parseFloat(cell.innerText.replace(/,/g, '')) || 0;
        });

        let discount = parseFloat(document.getElementById('discount_input').value) || 0;
        let grandTotal = subtotal - discount;

        // Update displays and hidden inputs
        document.getElementById('subtotal_display').innerText = subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('grand_total_display').innerText = grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        document.getElementById('subtotal_input').value = subtotal.toFixed(2);
        document.getElementById('grand_total_input').value = grandTotal.toFixed(2);
    }
    
    // Initial calculation on page load (important for reorder pre-fill)
    document.addEventListener('DOMContentLoaded', updateGrandTotal);
</script>
</body>
</html>