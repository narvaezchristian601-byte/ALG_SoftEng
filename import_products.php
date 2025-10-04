<?php
// import_products.php - Handles Inbound Delivery (Stock Increase)

include("db.php");
session_start();

// --- DEBUGGING BLOCK (Keep this active until the script works) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// -----------------------------------------------------------------

$message = "";

// 1. Re-fetch products and suppliers for initial setup and dynamic form generation
$products_res = $conn->query("SELECT Product_id, Name, Stock FROM Product ORDER BY Name ASC");
$allProducts = [];
if ($products_res) {
    while($row = $products_res->fetch_assoc()) {
        $allProducts[] = [
            'id' => $row['Product_id'],
            'name' => htmlspecialchars($row['Name']),
            'stock' => (int)$row['Stock']
        ];
    }
    // We don't need to rewind, as we'll use the JS array for form generation.
}

// 2. Fetch all suppliers
$suppliers_res = $conn->query("SELECT Supplier_id, Company_Name FROM Supplier ORDER BY Company_Name ASC");
// We clone the result set to be able to use it multiple times, but since we are just using the PHP result in one loop, we just need to ensure the query succeeded.

// 3. Handle form submission (Must be done before re-fetching suppliers for the dropdown)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start a transaction for safety: all updates must succeed or fail together.
    $conn->begin_transaction();
    $overallSuccess = true;
    $message = "";
    $newSupplierId = NULL;
    $supplierName = "No Supplier Assigned";
    $importDate = date('Y-m-d H:i:s');

    try {
        // A. Determine Supplier to use
        $addNewSupplier = ($_POST['supplier_toggle'] ?? 'existing') === 'new';

        if ($addNewSupplier) {
            // New Supplier Creation
            $companyName = trim($_POST['new_company_name'] ?? '');
            $contactPerson = trim($_POST['new_contact_person'] ?? '');
            $email = trim($_POST['new_email'] ?? '');
            $contactNum = trim($_POST['new_contact_num'] ?? '');

            if (empty($companyName)) {
                throw new Exception("New supplier company name is required.");
            }

            $stmt_new_supplier = $conn->prepare(
                "INSERT INTO Supplier (Company_Name, Contact_Person, Email, Contact_Num) VALUES (?, ?, ?, ?)"
            );
            $stmt_new_supplier->bind_param("ssss", $companyName, $contactPerson, $email, $contactNum);
            $stmt_new_supplier->execute();
            $newSupplierId = $conn->insert_id;
            $supplierName = htmlspecialchars($companyName);
            $message .= "✅ New supplier '$supplierName' registered. ";
            $stmt_new_supplier->close();

        } else {
            // Use Existing Supplier
            $existingSupplierId = intval($_POST['supplier_id'] ?? 0);
            if ($existingSupplierId > 0) {
                $newSupplierId = $existingSupplierId;
                // Fetch existing supplier name for message feedback
                $stmt_fetch_name = $conn->prepare("SELECT Company_Name FROM Supplier WHERE Supplier_id = ?");
                $stmt_fetch_name->bind_param("i", $newSupplierId);
                $stmt_fetch_name->execute();
                $result_name = $stmt_fetch_name->get_result();
                $supplierData = $result_name->fetch_assoc();
                $supplierName = htmlspecialchars($supplierData['Company_Name'] ?? 'Unknown Supplier');
                $stmt_fetch_name->close();
            }
            // If $newSupplierId is 0 or less, it remains NULL for the DB transaction (handled below)
        }
        
        // B. Handle Multiple Product Imports
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $importCount = 0;

        if (!is_array($productIds) || count($productIds) === 0) {
            throw new Exception("No products were added to the import list.");
        }

        // Prepare statements outside the loop
        $stmt_import = $conn->prepare(
            "INSERT INTO ProductImports (Product_id, Quantity, Supplier_id, ImportDate) VALUES (?, ?, ?, ?)"
        );
        $stmt_stock = $conn->prepare(
            "UPDATE Product SET Stock = Stock + ? WHERE Product_id = ?"
        );

        $totalUnits = 0;

        foreach ($productIds as $index => $productId) {
            $productId = intval($productId);
            $quantity = intval($quantities[$index] ?? 0);

            if ($productId > 0 && $quantity > 0) {
                // 1. Log the Import
                 // If no supplier was chosen, try to find the most recent supplier for this product
                if ($newSupplierId === NULL) {
                    $stmt_last_supplier = $conn->prepare("
                        SELECT Supplier_id 
                        FROM ProductImports 
                        WHERE Product_id = ? 
                        AND Supplier_id IS NOT NULL 
                        ORDER BY ImportDate DESC 
                        LIMIT 1
                    ");
                    $stmt_last_supplier->bind_param("i", $productId);
                    $stmt_last_supplier->execute();
                    $result_last_supplier = $stmt_last_supplier->get_result();
                    $row_last = $result_last_supplier->fetch_assoc();
                    $stmt_last_supplier->close();

                    if ($row_last && $row_last['Supplier_id'] > 0) {
                        $supplierBindId = $row_last['Supplier_id']; // fallback to existing supplier
                    } else {
                        $supplierBindId = NULL; // still allow null if no past supplier exists
                    }
                } else {
                    $supplierBindId = $newSupplierId;
                }
                $stmt_import->bind_param("iiis", $productId, $quantity, $supplierBindId, $importDate);
                $stmt_import->execute();

                // 2. Update the Stock
                $stmt_stock->bind_param("ii", $quantity, $productId);
                $stmt_stock->execute();

                $importCount++;
                $totalUnits += $quantity;
            }
        }

        $stmt_import->close();
        $stmt_stock->close();

        if ($importCount === 0) {
            throw new Exception("No valid product lines (Product ID > 0 and Quantity > 0) were processed.");
        }

        // Commit the transaction
        $conn->commit();
        $message .= "✅ Successfully recorded inbound delivery of $totalUnits units across $importCount product lines from **$supplierName**.";

    } catch (Exception $e) {
        // Rollback the transaction on failure
        $conn->rollback();
        $message = "Error recording delivery. All changes were reverted. Error: " . htmlspecialchars($e->getMessage());
        $overallSuccess = false;
    }
}

// 4. Re-fetch products after submission
$products_res = $conn->query("SELECT Product_id, Name, Stock FROM Product ORDER BY Name ASC");
$allProducts = [];
if ($products_res) {
    while($row = $products_res->fetch_assoc()) {
        $allProducts[] = [
            'id' => (int)$row['Product_id'],
            'name' => htmlspecialchars($row['Name']),
            'stock' => (int)$row['Stock']
        ];
    }
}

// 4.1. Fetch all suppliers and store in an array for JS filtering
$suppliers_res = $conn->query("SELECT Supplier_id, Company_Name FROM Supplier ORDER BY Company_Name ASC");
$allSuppliers = [];
if ($suppliers_res) {
    while($row = $suppliers_res->fetch_assoc()) {
        $allSuppliers[] = [
            'id' => (int)$row['Supplier_id'],
            'name' => htmlspecialchars($row['Company_Name'])
        ];
    }
}

// 4.2. Fetch Product-Supplier Relationships from past imports (NEW BLOCK)
// Map: { Product_id: [Supplier_id, Supplier_id, ...], ... }
$productSupplierMap = [];
$rel_query = "
    SELECT DISTINCT 
        Product_id, 
        Supplier_id 
    FROM ProductImports 
    WHERE Supplier_id IS NOT NULL AND Supplier_id > 0
";
$rel_res = $conn->query($rel_query);
if ($rel_res) {
    while($row = $rel_res->fetch_assoc()) {
        $product_id = (int)$row['Product_id'];
        $supplier_id = (int)$row['Supplier_id'];
        
        // Use string keys for JS compatibility
        if (!isset($productSupplierMap[(string)$product_id])) {
            $productSupplierMap[(string)$product_id] = [];
        }
        $productSupplierMap[(string)$product_id][] = $supplier_id;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background:#f8f9fa; }
        header { background:#007bff; color:white; padding:15px; text-align:center; }
        nav { background:#0056b3; padding:10px; text-align:center; }
        nav a { color:white; text-decoration:none; margin:0 15px; font-weight:bold; }
        nav a:hover { text-decoration:underline; }
        main { min-height:70vh; display:flex; justify-content:center; align-items:flex-start; padding:20px; }
        .card { 
            background:white; 
            padding:25px; 
            border-radius:8px; 
            box-shadow:0px 4px 8px rgba(0,0,0,0.1); 
            max-width:850px; /* Increased max-width for the table */
            width:90%; 
        }
        form div { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        
        /* Form elements styling */
        input[type="number"], select, input[type="text"], input[type="email"] { 
            width: 100%; 
            padding: 8px 10px; 
            box-sizing: border-box; 
            border: 1px solid #ccc; 
            border-radius: 4px; 
        }

        /* Submit Button */
        button[type="submit"] { 
            background: #28a745; 
            color: white; 
            padding: 12px 20px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-size: 16px; 
            margin-top: 20px; 
            transition: background 0.3s;
        }
        button[type="submit"]:hover { background: #218838; }

        /* Add Row Button */
        #addRowBtn {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: background 0.3s;
        }
        #addRowBtn:hover { background: #0056b3; }

        /* Messages */
        .message-success { color: green; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; font-weight: bold; }
        .message-error { color: red; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; font-weight: bold; }

        /* Table Styling */
        .product-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .product-table th, .product-table td { border: 1px solid #eee; padding: 10px; text-align: left; }
        .product-table th { background-color: #f4f4f4; }
        .product-table tr:nth-child(even) { background-color: #f9f9f9; }
        .delete-btn { background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; }
        
        /* Supplier Toggle Section */
        .supplier-toggle-group { display: flex; gap: 20px; margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .supplier-toggle-group label { display: inline-block; font-weight: normal; margin-bottom: 0; cursor: pointer; }
        .new-supplier-fields { 
            border: 1px dashed #007bff; 
            padding: 15px; 
            border-radius: 4px; 
            margin-top: 15px;
            background-color: #eaf4ff;
        }
        footer {
            background: #007bff;
            color: white;
            text-align: center;
            padding: 10px;
            position: relative;
            bottom: 0;
            width: 100%;
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
            <h2>Record Inbound Delivery (Stock Increase)</h2>
            
            <?php if ($message): ?>
                <p class="<?php echo strpos($message, '✅') !== false ? 'message-success' : 'message-error'; ?>">
                    <?php echo $message; ?>
                </p>
            <?php endif; ?>
            
            <form method="POST" id="importForm">
                
                <fieldset>
                    <legend style="font-weight: bold; margin-bottom: 10px;">Supplier Information</legend>
                    
                    <div class="supplier-toggle-group">
                        <input type="radio" id="toggleExisting" name="supplier_toggle" value="existing" checked onclick="toggleSupplierForm(false)">
                        <label for="toggleExisting">Select Existing Supplier</label>

                        <input type="radio" id="toggleNew" name="supplier_toggle" value="new" onclick="toggleSupplierForm(true)">
                        <label for="toggleNew">Add New Supplier</label>
                    </div>

                    <div id="existingSupplierDiv">
                        <div style="margin-bottom: 15px;">
                            <label for="filter_product_id">Filter Suppliers by Product:</label>
                            <select id="filter_product_id" onchange="filterSuppliers(this.value)">
                                <option value="0">-- Show All Products --</option>
                                </select>
                            <p style="font-size: 0.8em; color: #555; margin-top: 5px;">* Select a product to filter the list of suppliers who have provided it in the past.</p>
                        </div>
                        
                        <div>
                            <label for="supplier_id">Select Supplier (Optional):</label>
                            <select name="supplier_id" id="supplier_id">
                                <option value="0">-- No Supplier / Unknown --</option>
                                </select>
                        </div>
                    </div>

                    <div id="newSupplierDiv" class="new-supplier-fields" style="display: none;">
                        <label>New Supplier Details:</label>
                        <div>
                            <input type="text" name="new_company_name" placeholder="* Company Name" id="new_company_name">
                        </div>
                        <div>
                            <input type="text" name="new_contact_person" placeholder="Contact Person">
                        </div>
                        <div>
                            <input type="email" name="new_email" placeholder="Email">
                        </div>
                        <div>
                            <input type="text" name="new_contact_num" placeholder="Contact Number">
                        </div>
                    </div>
                </fieldset>
                
                <h3 style="margin-top: 30px;">Products Received</h3>
                
                <table class="product-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Product Name (Current Stock)</th>
                            <th style="width: 25%;">Quantity Received</th>
                            <th style="width: 15%;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="productTableBody">
                        </tbody>
                </table>

                <button type="button" id="addRowBtn">➕ Add Product Line</button>
                
                <button type="submit">✅ Record Full Delivery</button>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; 2025 ALG Roofing System. All Rights Reserved.</p>
    </footer>

    <script>
        // Data for dynamic dropdown generation
        const ALL_PRODUCTS = <?php echo json_encode($allProducts); ?>;
        const ALL_SUPPLIERS = <?php echo json_encode($allSuppliers); ?>;
        // Map: { "Product_id": [Supplier_id, Supplier_id, ...], ... }
        const PRODUCT_SUPPLIER_MAP = <?php echo json_encode($productSupplierMap); ?>;
        let rowCounter = 0;

        /**
         * Generates the HTML for a single product selection dropdown.
         * @param {number} selectedId - The Product_id to select by default.
         * @returns {string} HTML string for the select element.
         */
        function generateProductSelect(selectedId = 0) {
            let options = '<option value="">-- Select Product --</option>';
            ALL_PRODUCTS.forEach(product => {
                const selected = product.id == selectedId ? 'selected' : '';
                options += `<option value="${product.id}" data-stock="${product.stock}" ${selected}>
                    ${product.name} (Stock: ${product.stock})
                </option>`;
            });
            return `
                <select name="product_id[]" required onchange="updateRowDetails(this)">
                    ${options}
                </select>
            `;
        }

        /**
         * Updates the stock display or other details in a row when a product is selected.
         * (Currently not fully implemented as stock is already in the option text, but this is a placeholder for future updates).
         */
        function updateRowDetails(selectElement) {
            // Optional: Re-display product stock if needed, but for now it's in the option text.
        }

        /**
         * Adds a new product row to the table.
         */
        function addProductRow() {
            const tableBody = document.getElementById('productTableBody');
            const newRow = tableBody.insertRow();
            newRow.id = `row-${rowCounter++}`;

            // Cell 1: Product Select
            const cell1 = newRow.insertCell(0);
            cell1.innerHTML = generateProductSelect();

            // Cell 2: Quantity Input
            const cell2 = newRow.insertCell(1);
            cell2.innerHTML = `<input type="number" name="quantity[]" min="1" placeholder="Qty" required style="width: 100px;">`;

            // Cell 3: Delete Button
            const cell3 = newRow.insertCell(2);
            cell3.innerHTML = `<button type="button" class="delete-btn" onclick="removeProductRow('${newRow.id}')">Remove</button>`;
        }

        /**
         * Removes a product row from the table.
         * @param {string} rowId - The ID of the row to remove.
         */
        function removeProductRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) {
                row.remove();
            }
            // Ensure at least one row remains if all were deleted
            if (document.getElementById('productTableBody').rows.length === 0) {
                addProductRow();
            }
        }
        
        /**
         * Populates the filter_product_id dropdown with products from ALL_PRODUCTS.
         */
        function populateProductFilter() {
            const select = document.getElementById('filter_product_id');
            // Skip the first option (Show All Products)
            
            ALL_PRODUCTS.forEach(product => {
                const option = document.createElement('option');
                option.value = product.id;
                option.textContent = product.name;
                select.appendChild(option);
            });
        }

        /**
         * Populates the supplier dropdown with a subset of suppliers.
         * @param {Array<Object>} suppliersArray - Array of supplier objects {id, name}.
         */
        function populateSupplierSelect(suppliersArray) {
            const select = document.getElementById('supplier_id');
            select.innerHTML = '<option value="0">-- No Supplier / Unknown --</option>';

            suppliersArray.forEach(supplier => {
                const option = document.createElement('option');
                option.value = supplier.id;
                option.textContent = supplier.name;
                select.appendChild(option);
            });
        }
        
        /**
         * Filters the supplier dropdown based on the selected Product ID.
         * @param {string} productId - The ID of the product selected for filtering (or '0' for all).
         */
        function filterSuppliers(productId) {
            const selectedProductId = parseInt(productId, 10);
            
            if (selectedProductId === 0) {
                // Show all suppliers
                populateSupplierSelect(ALL_SUPPLIERS);
                return;
            }

            // Get the list of supplier IDs associated with this product
            // Use String(selectedProductId) because JS object keys (from PHP json_encode) are strings
            const allowedSupplierIds = PRODUCT_SUPPLIER_MAP[String(selectedProductId)] || [];

            // Filter ALL_SUPPLIERS
            const filteredSuppliers = ALL_SUPPLIERS.filter(supplier => {
                // supplier.id is integer, allowedSupplierIds contains integers
                return allowedSupplierIds.includes(supplier.id);
            });

            populateSupplierSelect(filteredSuppliers);
        }

        /**
         * Toggles between selecting an existing supplier and adding a new one.
         * @param {boolean} isNew - True to show new supplier fields, false to show existing dropdown.
         */
        function toggleSupplierForm(isNew) {
            const existingDiv = document.getElementById('existingSupplierDiv');
            const newDiv = document.getElementById('newSupplierDiv');
            const newCompanyNameInput = document.getElementById('new_company_name');
            const supplierSelect = document.getElementById('supplier_id');

            if (isNew) {
                existingDiv.style.display = 'none';
                newDiv.style.display = 'block';
                newCompanyNameInput.setAttribute('required', 'required');
                supplierSelect.removeAttribute('required');
            } else {
                existingDiv.style.display = 'block';
                newDiv.style.display = 'none';
                newCompanyNameInput.removeAttribute('required');
            }
        }

        // --- Initialization ---
        document.addEventListener('DOMContentLoaded', () => {
            // 1. Initialize with at least one product row
            if (document.getElementById('productTableBody').rows.length === 0) {
                addProductRow();
            }

            // 2. Attach event listener to the Add Row button
            document.getElementById('addRowBtn').addEventListener('click', addProductRow);
            
            // 3. Populate product filter and initial supplier list
            populateProductFilter();
            populateSupplierSelect(ALL_SUPPLIERS); // Initially show all suppliers

            // 4. Set default state for supplier toggle
            toggleSupplierForm(document.getElementById('toggleNew').checked);

            // 5. Form validation for products
            document.getElementById('importForm').addEventListener('submit', function(e) {
                const rows = document.getElementById('productTableBody').rows.length;
                if (rows === 0) {
                    // Using console/simple message instead of alert
                    console.error("Please add at least one product line.");
                    e.preventDefault();
                    // Optionally display error message in HTML
                }
            });
        });
    </script>
</body>
</html>