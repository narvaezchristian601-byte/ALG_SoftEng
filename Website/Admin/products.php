<?php
include("../../db.php");
session_start();

// Fetch categories
$categoryQuery = $conn->query("SELECT Category_id, Name FROM Category ORDER BY Name ASC");
$categories = $categoryQuery->fetch_all(MYSQLI_ASSOC);

// Fetch suppliers
$supplierQuery = $conn->query("SELECT Supplier_id, Company_Name FROM Supplier ORDER BY Company_Name ASC");
$suppliers = $supplierQuery->fetch_all(MYSQLI_ASSOC);

$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : "";
$like = "%{$searchTerm}%";

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Sorting
$validColumns = [
    "Product_id" => "p.Product_id",
    "Name" => "p.Name",
    "CategoryName" => "c.Name",
    "Price" => "p.Price",
    "Stock" => "p.Stock"
];
$requestedCol = $_GET['sort'] ?? 'Name';
$requestedDir = strtoupper($_GET['dir'] ?? 'ASC');
$orderBy = $validColumns[$requestedCol] ?? "p.Name";
$orderDir = $requestedDir === 'DESC' ? 'DESC' : 'ASC';

// Fetch products
$stmt = $conn->prepare("
    SELECT p.Product_id, p.Name, c.Name AS CategoryName, p.Price, p.Stock, p.Reorder_Level
    FROM Product p
    LEFT JOIN Category c ON p.Category_id = c.Category_id
    WHERE p.Name LIKE ? OR c.Name LIKE ?
    ORDER BY $orderBy $orderDir
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ssii", $like, $like, $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

// Count total
$countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM Product p
    LEFT JOIN Category c ON p.Category_id = c.Category_id
    WHERE p.Name LIKE ? OR c.Name LIKE ?
");
$countStmt->bind_param("ss", $like, $like);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

function stockStatus($stock, $reorder) {
    if ($stock <= 0) return "<span class='status out'>Stock Out</span>";
    elseif ($stock <= $reorder) return "<span class='status low'>Low Stock</span>";
    else return "<span class='status in'>In Stock</span>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ALG | Product Inventory</title>
<style>
    body {
        margin: 0;
        font-family: 'Inter', sans-serif;
        background: #e0e0e0;
        color: #333;
    }
    header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #3e3e3e;
        color: white;
        padding: 10px 30px;
    }
    header img { height: 40px; }
    nav {
        display: flex;
        gap: 25px;
    }
    nav a {
        color: white;
        text-decoration: none;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 20px;
    }
    nav a.active, nav a:hover {
        background: #d9d9d9;
        color: #000;
    }

    .container {
        display: flex;
        height: calc(100vh - 60px);
    }
    .sidebar {
        width: 250px;
        background: #d9d9d9;
        padding: 20px;
        box-shadow: 2px 0 6px rgba(0,0,0,0.1);
    }
    .sidebar h3 { margin-top: 0; font-size: 18px; }
    .sidebar a {
        display: block;
        margin: 10px 0;
        color: #333;
        text-decoration: none;
        font-weight: 600;
    }
    .sidebar a:hover { text-decoration: underline; }
    .sidebar a.active, .sidebar a:hover {
        background: #d9d9d9;
        color: #000;
        text-decoration: underline;
    }

    .content {
        flex: 1;
        padding: 30px;
        background: #f5f5f5;
        overflow-y: auto;
    }

    .top-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .top-bar input {
        padding: 8px 12px;
        width: 250px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .status-legend {
        display: flex;
        gap: 15px;
        align-items: center;
        font-size: 0.9rem;
    }
    .legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 5px;
    }
    .legend-in { background: #00b050; }
    .legend-low { background: #ffc000; }
    .legend-out { background: #ff0000; }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    }
    th, td {
        padding: 12px 16px;
        border-bottom: 1px solid #ddd;
        text-align: left;
    }
    th { background: #efefef; font-weight: bold; }
    .status {
        padding: 6px 10px;
        border-radius: 6px;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        min-width: 80px;
    }
    .status.in { background: #00b050; color: white; }
    .status.low { background: #ffc000; color: #000; }
    .status.out { background: #ff0000; color: white; }

    .pagination {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }
    .pagination button {
        background: #4f9bd2;
        color: white;
        border: none;
        border-radius: 5px;
        padding: 6px 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .pagination button:disabled {
        background: #bbb;
        cursor: default;
    }

    .add-btn {
        background: #4f9bd2;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 8px 14px;
        font-weight: 600;
        cursor: pointer;
    }
    .remove-btn {
        background: #d9534f;
        color: white;
        border: none;
        border-radius: 6px;
        padding: 6px 10px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }
    .remove-btn:hover { background: #c9302c; }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        justify-content: center;
        align-items: center;
    }
    .modal-content {
        background: white;
        padding: 25px;
        border-radius: 8px;
        width: 400px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.3);
    }
    .modal-content h3 {
        margin-top: 0;
        margin-bottom: 15px;
        text-align: center;
    }
    .modal-content label {
        display: block;
        font-weight: 600;
        margin-top: 10px;
    }
    .modal-content input, .modal-content select, .modal-content textarea {
        width: 100%;
        padding: 8px;
        border-radius: 5px;
        border: 1px solid #ccc;
        margin-top: 5px;
    }
    .modal-content button {
        margin-top: 15px;
        width: 48%;
        padding: 8px 0;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }
    .btn-save { background: #4f9bd2; color: white; }
    .btn-cancel { background: #aaa; color: white; }
    .logoimage{width: auto; height: 70px;}
</style>
</head>
<body>      

<header>
    <img class="logoimage" src="../../images/alg-logo-black.png" alt="ALG Logo">
    <nav>
        <a href="home.php">Home</a>
        <a href="products.php" class="active">Inventory</a>
        <a href="projects.php">Projects</a>
        <a href="staff.php">Staff</a>
    </nav>
</header>

<div class="container">
    <aside class="sidebar">
        <h3>Products</h3>
        <a href="products.php" class="active">Products</a>
        <a href="purchase.php">Purchase</a>
        <a href="supplier.php">Supplier</a>
        <a href="sales_report.php">Sales Report</a>
    </aside>

    <section class="content">
        <div class="top-bar">
            <form method="get" action="products.php">
                <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($searchTerm) ?>">
            </form>
            <button class="add-btn" id="openModal">+ Add Product</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Reorder Level</th>
                    <th>Quantity</th>
                    <th>Price (₱)</th>
                    <th>Stock Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['Product_id']) ?></td>
                        <td><?= htmlspecialchars($row['Name']) ?></td>
                        <td><?= htmlspecialchars($row['CategoryName'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['Reorder_Level']) ?></td>
                        <td><?= htmlspecialchars($row['Stock']) ?></td>
                        <td><?= number_format($row['Price'], 2) ?></td>
                        <td><?= stockStatus($row['Stock'], $row['Reorder_Level']) ?></td>
                        <td>
                            <button 
                                class="remove-btn" 
                                data-id="<?= $row['Product_id'] ?>" 
                                data-name="<?= htmlspecialchars($row['Name']) ?>">
                                Remove
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">No products found</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <form method="get" style="display:flex;gap:10px;">
                <button type="submit" name="page" value="<?= max(1, $page - 1) ?>" <?= $page <= 1 ? 'disabled' : '' ?>>← Previous</button>
                <button type="submit" name="page" value="<?= $page + 1 ?>" <?= $page >= $totalPages ? 'disabled' : '' ?>>Next →</button>
            </form>
        </div>
    </section>
</div>

<!-- Add Product Modal -->
<div class="modal" id="productModal">
    <div class="modal-content">
        <h3>Add New Product</h3>
        <form method="POST" action="add_product.php">
            <label>Product Name</label>
            <input type="text" name="name" required>

            <label>Category</label>
            <select name="category_id" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['Category_id'] ?>"><?= htmlspecialchars($cat['Name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Supplier</label>
            <select name="supplier_id" required>
                <option value="">Select Supplier</option>
                <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['Supplier_id'] ?>"><?= htmlspecialchars($sup['Company_Name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label>Price (₱)</label>
            <input type="number" step="0.01" name="price" required>

            <label>Stock</label>
            <input type="number" name="stock" required>

            <label>Reorder Level (Minimum level before reorder)</label>
            <input type="number" name="reorder_level" required>

            <label>Description</label>
            <textarea name="description" rows="3"></textarea>

            <div style="display:flex;justify-content:space-between;">
                <button type="submit" class="btn-save">Save</button>
                <button type="button" class="btn-cancel" id="closeModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Remove Product Modal -->
<div class="modal" id="removeModal">
  <div class="modal-content">
    <h3>Confirm Removal</h3>
    <p id="removeText">Are you sure you want to remove this product?</p>
    <form method="POST" action="delete_product.php">
      <input type="hidden" name="product_id" id="removeProductId">
      <div style="display:flex;justify-content:space-between;">
        <button type="submit" class="btn-save" style="background:#d9534f;">Yes, Remove</button>
        <button type="button" class="btn-cancel" id="closeRemoveModal">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
const modal = document.getElementById('productModal');
document.getElementById('openModal').onclick = () => modal.style.display = 'flex';
document.getElementById('closeModal').onclick = () => modal.style.display = 'none';
window.onclick = e => { if (e.target === modal) modal.style.display = 'none'; };

// Remove modal logic
const removeModal = document.getElementById('removeModal');
const closeRemoveModal = document.getElementById('closeRemoveModal');
const removeText = document.getElementById('removeText');
const removeInput = document.getElementById('removeProductId');

document.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        const name = btn.dataset.name;
        removeInput.value = id;
        removeText.textContent = `Are you sure you want to remove "${name}"?`;
        removeModal.style.display = 'flex';
    });
});
closeRemoveModal.onclick = () => removeModal.style.display = 'none';
window.onclick = e => { if (e.target === removeModal) removeModal.style.display = 'none'; };
</script>

</body>
</html>

