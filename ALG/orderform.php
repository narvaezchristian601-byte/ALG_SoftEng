<?php
include("db.php");
session_start();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For customers table
    $stmt = $conn->prepare("INSERT INTO Customers (Name, Email, PhoneNumber, Address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $_POST['name'], $_POST['email'], $_POST['phone'], $_POST['address']);
    $stmt->execute();
    $customerId = $stmt->insert_id;
    $stmt->close();
    // For orders table
    $stmt = $conn->prepare("INSERT INTO Orders (Customer_id, Services_id, total_amount, status, order_date) VALUES (?, ?, 0, 'Pending', NOW())");
    $stmt->bind_param("ii", $customerId, $_POST['service']);
    $stmt->execute();
    $orderId = $stmt->insert_id;
    $stmt->close();

    // Insert schedule linked to order
    $stmt = $conn->prepare("INSERT INTO service_sched (Services_id, ScheduleDate, Orders_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $_POST['service'], $_POST['schedule_date'], $orderId);
    $stmt->execute();
    $stmt->close();


    // For service_sched table
    if (!empty($_POST['schedule_date'])) {
        if ($_POST['schedule_date'] != 'SELECT ScheduleDate FROM service_sched') {
            $stmt = $conn->prepare("INSERT INTO service_sched (Services_id, ScheduleDate) VALUES (?, ?)");
            $stmt->bind_param("is", $_POST['service'], $_POST['schedule_date']);
        if ($stmt->execute()) {
            echo "Schedule added successfully.";
        } else {
            echo "Error: " . $stmt->error;
        }
        $stmt->close();
        }
    }
    // Set total amount to 0 so no errors
    $totalAmount = 0;

    // For order items table
    if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
        foreach ($_POST['product_id'] as $index => $productId) {
            $quantity = $_POST['quantity'][$index];
            if ($quantity > 0) {
                $stmt = $conn->prepare("SELECT Price FROM Product WHERE Product_id = ?");
                $stmt->bind_param("i", $productId);
                $stmt->execute();
                $stmt->bind_result($price);
                $stmt->fetch();
                $stmt->close();

                $itemTotal = $price * $quantity;
                $totalAmount += $itemTotal;

                $stmt = $conn->prepare("INSERT INTO OrderItems (Orders_id, Product_id, Services_id, quantity, price) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiii", $orderId, $productId, $_POST['service'], $quantity, $itemTotal);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    // To update total amount in orders table
    $stmt = $conn->prepare("UPDATE Orders SET total_amount = ? WHERE Orders_id = ?");
    $stmt->bind_param("di", $totalAmount, $orderId);
    $stmt->execute();
    $stmt->close();

    $message = "Order placed successfully! Order ID: $orderId";
}

// Gets products
$products = [];
$result = $conn->query("SELECT Product_id, Name, Price FROM Product ORDER BY Name ASC");
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

// Gets services
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
                    <option value="<?php echo $service['Services_id']; ?>">
                    <?php echo htmlspecialchars($service['Name']) . " (₱" . number_format($service['Price'],2) . ")"; ?>
                    </option>
                <?php endforeach; ?>
                </select>

                <label>Preferred Schedule Date:</label>

                <input type="datetime-local" name="schedule_date" required
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
    // Product total display
    const productSelect = document.getElementById('productSelect');
    const productQty = document.getElementById('productQty');
    const productTotal = document.getElementById('productTotal');
    const addProductBtn = document.getElementById('addProductBtn');
    const tableBody = document.querySelector('#product-table tbody');
    const grandTotalEl = document.getElementById('grandTotal');

    function updateProductTotal() {
        const price = parseFloat(productSelect.selectedOptions[0].dataset.price);
        const qty = parseInt(productQty.value) || 0;
        productTotal.textContent = "₱" + (price * qty).toFixed(2);
    }

    productSelect.addEventListener('change', updateProductTotal);
    productQty.addEventListener('input', updateProductTotal);
    updateProductTotal();

    // Add product to table
    let grandTotal = 0;
    addProductBtn.addEventListener('click', () => {
        const prodId = productSelect.value;
        const prodName = productSelect.selectedOptions[0].text;
        const price = parseFloat(productSelect.selectedOptions[0].dataset.price);
        const qty = parseInt(productQty.value);
        const subtotal = price * qty;
        grandTotal += subtotal;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${prodName}<input type="hidden" name="product_id[]" value="${prodId}"></td>
            <td>${qty}<input type="hidden" name="quantity[]" value="${qty}"></td>
            <td>₱${price.toFixed(2)}</td>
            <td>₱${subtotal.toFixed(2)}</td>
            <td><button type="button" class="removeBtn">Remove</button></td>
        `;
        if(grandTotalEl.textContent === "₱0.00") {
            return;
        } else {
            tableBody.appendChild(row);
            grandTotalEl.textContent = "₱" + grandTotal.toFixed(2);

            row.querySelector('.removeBtn').addEventListener('click', () => {
                grandTotal -= subtotal;
                grandTotalEl.textContent = "₱" + grandTotal.toFixed(2);
                row.remove();
            });
        }
    });

        // Restrict time selection to 8am-6pm and check for booked slots via AJAX
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
