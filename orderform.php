<?php
require_once("db.php");
session_start();

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if a service is selected
    if (!isset($_POST['service']) || empty($_POST['service'])) {
        $message = "Please select a service.";
    } else {
        // Fetch service price first
        $stmt_service = $conn->prepare("SELECT Price FROM Services WHERE Services_id = ?");
        $stmt_service->bind_param("i", $_POST['service']);
        $stmt_service->execute();
        $stmt_service->bind_result($servicePrice);
        $stmt_service->fetch();
        $stmt_service->close();
        
        $totalAmount = $servicePrice; // Start with the service price
        $productsDetected = false;

        // Calculate total amount from products
        if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
            foreach ($_POST['product_id'] as $index => $productId) {
                $quantity = $_POST['quantity'][$index];
                if ($quantity > 0) {
                    $productsDetected = true;
                    $stmt_prod_price = $conn->prepare("SELECT Price FROM Product WHERE Product_id = ?");
                    $stmt_prod_price->bind_param("i", $productId);
                    $stmt_prod_price->execute();
                    $stmt_prod_price->bind_result($price);
                    $stmt_prod_price->fetch();
                    $stmt_prod_price->close();

                    $itemTotal = $price * $quantity;
                    $totalAmount += $itemTotal;
                }
            }
        }  

        try {
            $conn->begin_transaction();

            // Insert into Customers
            $stmt_cust = $conn->prepare("INSERT INTO Customers (Name, Email, PhoneNumber, Address) 
                                         VALUES (?, ?, ?, ?)");
            $stmt_cust->bind_param("ssss", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address']);
            $stmt_cust->execute();
            $customerId = $stmt_cust->insert_id;
            $stmt_cust->close();

            // Insert into Orders
            $stmt_order = $conn->prepare("INSERT INTO Orders (Customer_id, Services_id, total_amount, status, order_date, schedule_date) 
                                          VALUES (?, ?, ?, 'Pending', NOW(), ?)");
            $stmt_order->bind_param("iids", $customerId, $_POST['service'], $totalAmount, $_POST['schedule_date']);
            $stmt_order->execute();
            $orderId = $stmt_order->insert_id;
            $stmt_order->close();

            // Insert into OrderItems + Update stock
            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                foreach ($_POST['product_id'] as $index => $productId) {
                    $quantity = $_POST['quantity'][$index]; // Access quantity by index
                    $quantity = (int) $_POST['quantity'][$index]; 

                    if ($quantity > 0) {
                        // Get product stock and price
                        $stmt_stock = $conn->prepare("SELECT Stock, Price FROM Product WHERE Product_id = ?");
                        $stmt_stock->bind_param("i", $productId);
                        $stmt_stock->execute();
                        $stmt_stock->bind_result($stock, $price);
                        $stmt_stock->fetch();
                        $stmt_stock->close();

                        // Check if enough stock is available
                        if (($stock - $quantity) >= 0) {
                            $itemTotal = $price * $quantity;

                            // Insert into OrderItems
                            $stmt_order_items = $conn->prepare("INSERT INTO OrderItems (Orders_id, Product_id, Services_id, quantity, price) 
                                                                 VALUES (?, ?, ?, ?, ?)");
                            $stmt_order_items->bind_param("iiiid", $orderId, $productId, $_POST['service'], $quantity, $price);
                            $stmt_order_items->execute();
                            $stmt_order_items->close();

                            // Deduct stock from Product
                            $stmt_update_stock = $conn->prepare("UPDATE Product SET stock = stock - ? WHERE Product_id = ?");
                            $stmt_update_stock->bind_param("ii", $quantity, $productId);
                            if (!$stmt_update_stock->execute()) {
                                throw new Exception("Stock update failed for product ID $productId");
                            }
                            $stmt_update_stock->close();
                        } else {
                            // Not enough stock available
                            throw new Exception("Not enough stock for product ID $productId. Available: $stock, Requested: $quantity.");
                        }
                    }
                }
            }

            $conn->commit();
            echo "<script>alert('Order placed successfully!'); window.location='orders.php';</script>";
        } catch (mysqli_sql_exception $e) { // Use mysqli_sql_exception for specific DB errors
            $conn->rollback();
            $message = "Error placing order: " . $e->getMessage();
            echo "<script>alert('Failed to place order: " . $message . "');</script>";
        }
    }
}

// Gets products and services
$products = [];
$result = $conn->query("SELECT Product_id, Name, Price FROM Product ORDER BY Name ASC");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$services = [];
$res = $conn->query("SELECT Services_id, Name, Price FROM Services ORDER BY Name ASC");
while ($row = $res->fetch_assoc()) {
    $services[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ALG Roofing System - Create Order</title>
<style>
/* CSS styles here */
html, body {
    margin: 0;
    padding: 0;
    font-family: 'Arial', sans-serif;
    background: #f8f9fa;
    color: #333;
}

header, footer {
    background: #007bff;
    color: white;
    text-align: center;
    padding: 12px 0;
}

nav {
    background: #0056b3;
    text-align: center;
    padding: 10px 0;
}

nav a {
    color: white;
    text-decoration: none;
    margin: 0 15px;
    font-weight: bold;
}

nav a:hover {
    text-decoration: underline;
}

main {
    display: flex;
    justify-content: center;
    flex-direction: column;
    padding: 20px;
}

.card {
    max-width: 900px;
    width: 100%;
    margin: 20px auto;
    padding: 25px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.05);
}

.form-section {
    margin-bottom: 25px;
}

.form-section h3 {
    margin-bottom: 12px;
    color: #007bff;
    border-bottom: 1px solid #ddd;
    padding-bottom: 6px;
    font-size: 1.1rem;
}

label {
    display: block;
    margin-bottom: 10px;
    font-weight: 500;
}

input[type=text],
input[type=email],
input[type=number],
select {
    width: 100%;
    padding: 10px;
    margin-top: 4px;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-sizing: border-box;
    font-size: 0.95rem;
}

button {
    padding: 10px 18px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.3s ease;
}

button:hover {
    background: #0056b3;
}

#product-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    background: #fff;
}

#product-table th,
#product-table td {
    border: 1px solid #ccc;
    padding: 10px;
    text-align: left;
    font-size: 0.95rem;
}

#product-table th {
    background: #007bff;
    color: white;
}

#product-table td input[type=number] {
    width: 60px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

/* Totals */
.total-row td {
    font-weight: bold;
    text-align: right;
    background: #f1f1f1;
    font-size: 1rem;
}

.message {
    padding: 12px;
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    border-radius: 5px;
    margin-bottom: 20px;
    font-weight: 500;
}

.product-item-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}
</style>
</head>
<body>
    <header>
        <h1>ALG Roofing Products & Services System</h1>
    </header>

    <nav>
        <a href="index.php">Home</a>
        <a href="orders.php">Orders</a>
        <a href="products.php">Product Search</a>
        <a href="supplier_list.php">Supplier List</a>
        <a href="price_list.php">Price List</a>
        <a href="orderform.php">Order Form</a>
        <a href="calendar.php">Schedule Calendar</a>
        <a href="reports.php">Reports</a>
        <a href="import_products.php">Import Products</a>
    </nav>

    <main>
        <div class="card">
            <h2>Create New Order</h2>
            <?php if (!empty($message)) echo "<div class='message'>$message</div>"; ?>

            <form method="POST" id="orderForm">
                <div class="form-section">
                    <h3>Customer Info</h3>
                    <label>Name: <input type="text" name="name" required></label>
                    <label>Email: <input type="email" name="email" required></label>
                    <label>Phone: <input type="text" name="phone" required></label>
                    <label>Address: <input type="text" name="address" required></label>
                </div>

                <div class="form-section">
                    <h3>Service</h3>
                    <select name="service" required>
                        <option value="">-- Select a Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['Services_id']; ?>" data-price="<?php echo $service['Price']; ?>">
                                <?php echo htmlspecialchars($service['Name']) . " (₱" . number_format($service['Price'],2) . ")"; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Preferred Schedule Date:</label>

                    <input type="datetime-local" 
                           name="schedule_date" 
                           required
                           step="1800"
                           min="<?php echo date('Y-m-d').'T08:00'; ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+90 days')).'T18:00'; ?>" 
                           value="<?php echo date('Y-m-d').'T08:00'; ?>">
                    <small>Allowed time: 8:00 AM to 6:00 PM</small>
                </div>

                <div class="form-section">
                    <h3>Products</h3>
                    <div class="product-item-wrapper">
                        <label>Select Product:
                            <select id="productSelect">
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['Product_id']; ?>" data-price="<?php echo $product['Price']; ?>">
                                        <?php echo htmlspecialchars($product['Name']) . " (₱" . number_format($product['Price'],2) . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Qty: <input type="number" id="productQty" value="1" min="1"></label>
                        <label>Total: <span id="productTotal">₱0.00</span></label>
                        <button type="button" id="addProductBtn">Add Product</button>
                    </div>

                    <table id="product-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3">Total</td>
                                <td id="grandTotal">₱0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <button type="submit">Place Order</button>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>

    <script>
        // Existing product total display and add to table logic
        const productSelect = document.getElementById('productSelect');
        const productQty = document.getElementById('productQty');
        const productTotal = document.getElementById('productTotal');
        const addProductBtn = document.getElementById('addProductBtn');
        const tableBody = document.querySelector('#product-table tbody');
        const grandTotalEl = document.getElementById('grandTotal');
        const serviceSelect = document.querySelector('select[name="service"]');

        function updateProductTotal() {
            const price = parseFloat(productSelect.selectedOptions[0].dataset.price);
            const qty = parseInt(productQty.value) || 0;
            productTotal.textContent = "₱" + (price * qty).toFixed(2);
        }

        productSelect.addEventListener('change', updateProductTotal);
        productQty.addEventListener('input', updateProductTotal);
        updateProductTotal();

        let grandTotal = 0;
        
        // Function to calculate and update grand total
        function updateGrandTotal() {
            let total = 0;
            // Add service price
            const serviceOption = serviceSelect.selectedOptions[0];
            if (serviceOption && serviceOption.value) {
                const servicePrice = parseFloat(serviceOption.dataset.price || 0);
                total += servicePrice;
            }

            // Add product subtotals
            const subtotals = document.querySelectorAll('tr[data-subtotal]');
            subtotals.forEach(row => {
                total += parseFloat(row.dataset.subtotal);
            });

            grandTotalEl.textContent = "₱" + total.toFixed(2);
            grandTotal = total; // Update the global variable
        }
        
        // Add product to table
        addProductBtn.addEventListener('click', () => {
            const prodId = productSelect.value;
            const prodName = productSelect.selectedOptions[0].text;
            const price = parseFloat(productSelect.selectedOptions[0].dataset.price);
            const qty = parseInt(productQty.value);
            const subtotal = price * qty;

            const row = document.createElement('tr');
            row.dataset.subtotal = subtotal;
            row.dataset.prodId = prodId; // Add a data attribute for the product ID
            row.innerHTML = `
                <td>${prodName}</td>
                <td>${qty}</td>
                <td>₱${price.toFixed(2)}</td>
                <td>₱${subtotal.toFixed(2)}</td>
                <td><button type="button" class="removeBtn">Remove</button></td>
            `;

            // Add the hidden inputs directly to the form
            const hiddenInputsContainer = document.createElement('div');
            hiddenInputsContainer.dataset.prodId = prodId; // Link container to the row
            hiddenInputsContainer.innerHTML = `
                <input type="hidden" name="product_id[]" value="${prodId}">
                <input type="hidden" name="quantity[]" value="${qty}">
            `;
            document.getElementById('orderForm').appendChild(hiddenInputsContainer);
            
            tableBody.appendChild(row);
            updateGrandTotal();

            row.querySelector('.removeBtn').addEventListener('click', () => {
                row.remove();
                // Remove the hidden inputs container
                const container = document.querySelector(`#orderForm div[data-prod-id="${prodId}"]`);
                if (container) {
                    container.remove();
                }
                updateGrandTotal();
            });
        });

        serviceSelect.addEventListener('change', updateGrandTotal);

        // Schedule date validation 
        document.querySelector('input[name="schedule_date"]').addEventListener('input', function(e) {
            const dt = new Date(this.value);
            const hour = dt.getHours();
            if (hour < 8 || hour > 18) {
                alert('Please select a time between 8:00 AM and 6:00 PM.');
                this.value = '';
                return;
            }
            // AJAX check for booked slot
            const serviceId = document.querySelector('select[name="service"]').value;
            if (!serviceId) return;
            fetch('check_schedule.php?service_id=' + serviceId + '&schedule_date=' + encodeURIComponent(this.value))
                .then(res => res.json())
                .then(data => {
                    if (data.booked) {
                        alert('This time slot is already booked. Please choose another.');
                        e.target.value = '';
                    }
                });
        });
    </script>
</body>
</html>