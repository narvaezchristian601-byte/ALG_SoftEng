<?php
// --- FILE: supplier.php ---

// 1. SYSTEM LIFE CYCLE & REQUIREMENTS: Database Connection
// Include the database connection file (assumed to define $conn and $db_type)
include("../../db.php");

// 2. APPLICATION DEVELOPMENT: Initialize status message variables
$message = '';
$status = '';

// 3. DATA SYNCHRONIZATION & STORAGE: Handle Supplier Creation/Update (Transactional)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['add', 'update'])) {
    // Sanitize and validate inputs
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $contact_person = filter_input(INPUT_POST, 'contact_person', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_SPECIAL_CHARS);
    $contact_num = filter_input(INPUT_POST, 'contact_num', FILTER_SANITIZE_SPECIAL_CHARS);

    if (!$company_name || !$contact_person || !$email || !$address || !$contact_num) {
        $message = "Validation Error: Please fill all required fields correctly.";
        $status = "error";
    } else {
        if ($conn->begin_transaction()) {
            try {
                if ($_POST['action'] === 'add') {
                    // INSERT NEW SUPPLIER
                    $stmt = $conn->prepare("INSERT INTO Supplier (Company_Name, Contact_Person, Email, Address, Contact_Num) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $company_name, $contact_person, $email, $address, $contact_num);

                    if ($stmt->execute()) {
                        $conn->commit();
                        $message = "New supplier successfully added: " . htmlspecialchars($company_name);
                        $status = "success";
                    } else {
                        throw new Exception("Insertion failed: " . $stmt->error);
                    }
                    $stmt->close();

                } elseif ($_POST['action'] === 'update' && $supplier_id) {
                    // UPDATE EXISTING SUPPLIER
                    $stmt = $conn->prepare("UPDATE Supplier SET Company_Name = ?, Contact_Person = ?, Email = ?, Address = ?, Contact_Num = ? WHERE Supplier_id = ?");
                    $stmt->bind_param("sssssi", $company_name, $contact_person, $email, $address, $contact_num, $supplier_id);

                    if ($stmt->execute()) {
                        $conn->commit();
                        $message = "Supplier ID {$supplier_id} successfully updated.";
                        $status = "success";
                    } else {
                        throw new Exception("Update failed: " . $stmt->error);
                    }
                    $stmt->close();
                }

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Transaction Error: " . $e->getMessage();
                $status = "error";
            }
        } else {
            $message = "Error: Could not start transaction for POST operation.";
            $status = "error";
        }
    }
}


// 4. DATA SYNCHRONIZATION & STORAGE: Handle Supplier Deletion (Transactional Safety)
if (isset($_GET['delete_id'])) {
    $delete_id = filter_var($_GET['delete_id'], FILTER_VALIDATE_INT);

    if ($delete_id) {
        if ($conn->begin_transaction()) {
            try {
                // Check for dependent products (Crucial for Data Integrity)
                $stmt_check = $conn->prepare("SELECT COUNT(*) FROM Product WHERE Supplier_id = ?");
                $stmt_check->bind_param("i", $delete_id);
                $stmt_check->execute();
                $result_count = $stmt_check->get_result()->fetch_row()[0];
                $stmt_check->close();

                if ($result_count > 0) {
                    throw new Exception("Cannot delete supplier: {$result_count} product(s) are currently associated with this supplier. Please update the products first.");
                }

                // Proceed with deletion
                $stmt_delete = $conn->prepare("DELETE FROM Supplier WHERE Supplier_id = ?");
                $stmt_delete->bind_param("i", $delete_id);

                if ($stmt_delete->execute()) {
                    $conn->commit();
                    $message = "Supplier successfully deleted.";
                    $status = "success";
                } else {
                    throw new Exception("Deletion failed: " . $stmt_delete->error);
                }
                $stmt_delete->close();

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error: " . $e->getMessage();
                $status = "error";
            }
        } else {
            $message = "Error: Could not start transaction.";
            $status = "error";
        }
    } else {
        $message = "Error: Invalid supplier ID provided for deletion.";
        $status = "error";
    }
}

// 5. DATA SYNCHRONIZATION & STORAGE: Fetch Supplier Data for Display
$suppliers = [];
// Select all necessary columns.
$query = "SELECT Supplier_id, Company_Name, Contact_Person, Email, Address, Contact_Num FROM Supplier ORDER BY Company_Name ASC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
} else {
    $message = "Database Error: Could not retrieve supplier data. Details: " . $conn->error;
    $status = "error";
}

// Close the database connection
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Supplier Management</title>
    <!-- Load Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom Styles for Fixed Layout and Appearance */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f7f9fc; }

        .app-shell { display: grid; grid-template-rows: auto 1fr; height: 100vh; }
        .primary-nav { background-color: #374151; color: #ffffff; z-index: 10; }
        .secondary-nav { background-color: #ffffff; width: 280px; min-height: calc(100vh - 64px); box-shadow: 2px 0 5px rgba(0,0,0,0.05); padding: 1.5rem 0; flex-shrink: 0; }
        .secondary-nav a.active { background-color: #e5e7eb; color: #1f2937; font-weight: 600; }
        .main-content-wrapper { background-color: #f7f9fc; padding: 1.5rem; overflow-y: auto; }
        .content-card { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); padding: 2rem; }
        .table-wrapper { overflow-x: auto; }

        /* Modal specific styles */
        .modal {
            transition: opacity 0.3s ease-in-out;
            visibility: hidden;
            opacity: 0;
        }
        .modal.open {
            visibility: visible;
            opacity: 1;
        }
    </style>
    <script>
        // --- Custom Modal Functions ---

        // Use a simple custom modal for confirmation instead of the forbidden alert/confirm.
        function showCustomModal(title, message, onConfirm) {
            const modalHtml = `
                <div id="custom-confirm-modal" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 p-4">
                    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm">
                        <h3 class="text-xl font-semibold text-gray-900 mb-4">${title}</h3>
                        <p class="text-gray-700 mb-6">${message}</p>
                        <div class="flex justify-end space-x-3">
                            <button id="modal-cancel" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                            <button id="modal-confirm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">Delete</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            const modal = document.getElementById('custom-confirm-modal');
            const confirmBtn = document.getElementById('modal-confirm');
            const cancelBtn = document.getElementById('modal-cancel');

            const closeModal = () => modal.remove();

            confirmBtn.onclick = () => { closeModal(); onConfirm(); };
            cancelBtn.onclick = closeModal;
        }

        // Function to handle the delete action with confirmation
        function handleDelete(id) {
            const row = document.getElementById(`row-${id}`);
            const supplierName = row ? row.dataset.company : `ID ${id}`;

            showCustomModal(
                'Confirm Deletion',
                `Are you sure you want to permanently delete the supplier "${supplierName}"? This action cannot be undone if no products are associated.`,
                () => {
                    // Navigate to the delete URL after confirmation
                    window.location.href = `supplier.php?delete_id=${id}`;
                }
            );
        }

        // --- Main Supplier Modal (Create/View/Edit) ---

        function openSupplierModal(id) {
            const modal = document.getElementById('supplier-modal');
            const form = document.getElementById('supplier-form');
            const titleElement = document.getElementById('modal-title');

            // Form Fields
            const supplierIdField = document.getElementById('supplier_id');
            const actionField = document.getElementById('action');
            const companyNameField = document.getElementById('company_name');
            const contactPersonField = document.getElementById('contact_person');
            const emailField = document.getElementById('email');
            const addressField = document.getElementById('address');
            const contactNumField = document.getElementById('contact_num');
            const saveButton = document.getElementById('modal-save-btn');
            
            // Utility function to set field state
            const setFieldEditability = (readOnly) => {
                [companyNameField, contactPersonField, emailField, addressField, contactNumField].forEach(field => {
                    field.readOnly = readOnly;
                    if (readOnly) {
                        field.classList.add('bg-gray-100');
                    } else {
                        field.classList.remove('bg-gray-100');
                    }
                });
            };

            if (id === null) {
                // --- NEW SUPPLIER MODE ---
                titleElement.textContent = 'New Supplier Registration';
                actionField.value = 'add';
                supplierIdField.value = ''; // Clear ID for new entry
                form.reset();
                saveButton.textContent = 'Register Supplier';
                saveButton.classList.remove('hidden', 'bg-green-600');
                saveButton.classList.add('bg-blue-600');
                
                setFieldEditability(false);
                // Reset click handler to submit form directly
                saveButton.onclick = () => form.submit();


            } else {
                // --- VIEW/EDIT SUPPLIER MODE ---
                const row = document.getElementById(`row-${id}`);
                if (!row) return console.error('Row data not found for ID:', id);

                titleElement.textContent = `View Supplier (ID: ${id})`;
                actionField.value = 'update';
                supplierIdField.value = id;

                // Populate fields from data attributes
                companyNameField.value = row.dataset.company;
                contactPersonField.value = row.dataset.contactPerson;
                emailField.value = row.dataset.email;
                addressField.value = row.dataset.address;
                contactNumField.value = row.dataset.contactNum;

                // Start in View mode (read-only)
                setFieldEditability(true);
                
                saveButton.textContent = 'Edit Mode';
                saveButton.classList.remove('hidden', 'bg-green-600');
                saveButton.classList.add('bg-blue-600');
                
                // Change save button to trigger edit mode first
                saveButton.onclick = (e) => {
                    e.preventDefault();
                    if (saveButton.textContent === 'Edit Mode') {
                        // Switch to Edit mode
                        titleElement.textContent = `Editing Supplier (ID: ${id})`;
                        saveButton.textContent = 'Save Changes';
                        setFieldEditability(false);
                        
                        saveButton.classList.remove('bg-blue-600');
                        saveButton.classList.add('bg-green-600');
                        saveButton.onclick = () => form.submit(); // Next click submits the form
                    } else {
                        form.submit();
                    }
                };
            }

            modal.classList.add('open');
        }

        function closeModal() {
            const modal = document.getElementById('supplier-modal');
            const saveButton = document.getElementById('modal-save-btn');
            
            // Reset button behavior to direct form submission (for New Supplier mode)
            saveButton.classList.remove('bg-green-600');
            saveButton.classList.add('bg-blue-600');
            saveButton.onclick = () => {
                 document.getElementById('supplier-form').submit();
            };

            modal.classList.remove('open');
        }

        // Initialize listeners
        document.addEventListener('DOMContentLoaded', () => {
            const backdrop = document.getElementById('modal-backdrop');
            const closeBtn = document.getElementById('modal-close-btn');
            
            if (backdrop) backdrop.onclick = closeModal;
            if (closeBtn) closeBtn.onclick = closeModal;

            // Handle URL parameters after form submission to reopen modal if validation failed (optional, but good UX)
            // if (<?php echo json_encode($status); ?> === 'error' && <?php echo isset($_POST['action']) ? 'true' : 'false'; ?>) {
            //     // Logic to try and restore modal state after error if needed. Omitted for simplicity.
            // }
        });

    </script>
</head>
<body class="app-shell">

    <!-- PRIMARY NAVIGATION (TOP BAR) - Fixed Navigation -->
    <header class="primary-nav flex justify-between items-center h-16 px-6 shadow-md">
        <div class="flex items-center space-x-4">
            <button class="text-white hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
            </button>
            <span class="text-xl font-bold">ALG Enterprises</span>
        </div>

        <!-- Navigation Links (MAIN MODULES) -->
        <nav class="hidden md:flex space-x-6">
            <a href="home.php" class="text-gray-300 hover:text-white transition duration-150 py-2">Home</a>
            <a href="inventory.php" class="text-white bg-gray-700 px-3 py-2 rounded-lg font-semibold shadow-inner">Inventory</a>
            <a href="projects.php" class="text-gray-300 hover:text-white transition duration-150 py-2">Projects</a>
            <a href="staff.php" class="text-gray-300 hover:text-white transition duration-150 py-2">Staff</a>
        </nav>

        <!-- User Profile/Logout -->
        <div class="flex items-center space-x-4">
             <div class="w-10 h-10 bg-gray-600 rounded-full flex items-center justify-center text-white cursor-pointer hover:bg-gray-500 transition">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
             </div>
             <a href="logout.php" class="text-gray-300 hover:text-white transition duration-150">
                 <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
             </a>
        </div>
    </header>

    <!-- CONTENT AREA (Sidebar and Main Content) -->
    <main class="flex">
        <!-- SECONDARY NAVIGATION (SIDEBAR) - Sub-Modules for Inventory -->
        <nav class="secondary-nav">
            <h2 class="text-lg font-semibold text-gray-500 uppercase tracking-wider px-6 mb-4">Inventory</h2>
            <ul class="space-y-1">
                <li>
                    <a href="products.php" class="block py-3 px-6 text-gray-700 hover:bg-gray-100 transition duration-150">Products</a>
                </li>
                <li>
                    <a href="Purchase.php" class="block py-3 px-6 text-gray-700 hover:bg-gray-100 transition duration-150">Purchased</a>
                </li>
                <li>
                    <!-- ACTIVE PAGE: Supplier -->
                    <a href="supplier.php" class="active block py-3 px-6 text-gray-700 transition duration-150 border-l-4 border-blue-600">Supplier</a>
                </li>
                <li>
                    <a href="sales_report.php" class="block py-3 px-6 text-gray-700 hover:bg-gray-100 transition duration-150">Sales Report</a>
                </li>
            </ul>
        </nav>

        <!-- MAIN CONTENT AREA -->
        <div class="main-content-wrapper flex-grow">
            <h1 class="text-3xl font-extrabold text-gray-900 mb-6">Supplier Management</h1>

            <!-- Status Message Display -->
            <?php if ($message): ?>
                <div id="status-message" class="p-4 mb-6 rounded-lg <?php echo $status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
                    <span class="font-medium"><?php echo htmlspecialchars($message); ?></span>
                </div>
                <script>
                    // Remove message after 5 seconds
                    setTimeout(() => {
                        const msg = document.getElementById('status-message');
                        if (msg) msg.remove();
                    }, 5000);
                </script>
            <?php endif; ?>

            <div class="content-card">
                <!-- Action Bar -->
                <div class="flex justify-end space-x-3 mb-6">
                    <!-- New Supplier button now opens the modal -->
                    <button onclick="openSupplierModal(null)" class="flex items-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition duration-150">
                        <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        New Supplier
                    </button>
                </div>

                <!-- Filters and Search -->
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="text-gray-600 font-medium">Filters:</span>
                        <select class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option>Product Type</option>
                            <option>Roofing</option>
                            <option>Insulation</option>
                        </select>
                    </div>
                    <div class="relative">
                        <input type="text" placeholder="Search suppliers..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 w-full sm:w-64">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                </div>

                <!-- Supplier Table -->
                <div class="table-wrapper">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Number</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (count($suppliers) > 0): ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <!-- Embed all data as data-attributes for quick JS retrieval into the modal -->
                                    <tr id="row-<?php echo htmlspecialchars($supplier['Supplier_id']); ?>"
                                        data-id="<?php echo htmlspecialchars($supplier['Supplier_id']); ?>"
                                        data-company="<?php echo htmlspecialchars($supplier['Company_Name']); ?>"
                                        data-contact-person="<?php echo htmlspecialchars($supplier['Contact_Person']); ?>"
                                        data-email="<?php echo htmlspecialchars($supplier['Email']); ?>"
                                        data-address="<?php echo htmlspecialchars($supplier['Address']); ?>"
                                        data-contact-num="<?php echo htmlspecialchars($supplier['Contact_Num']); ?>">
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 supplier-name"><?php echo htmlspecialchars($supplier['Company_Name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($supplier['Contact_Person']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($supplier['Address']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?php echo htmlspecialchars($supplier['Contact_Num']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 truncate"><?php echo htmlspecialchars($supplier['Email']); ?></td>
                                        
                                        <td class="px-6 py-4 whitespace-nowrap text-left text-sm font-medium space-x-3">
                                            <!-- 'View' button opens the modal for view/edit -->
                                            <button onclick="openSupplierModal(<?php echo htmlspecialchars($supplier['Supplier_id']); ?>)" class="text-indigo-600 hover:text-indigo-900 transition font-semibold">View/Edit</button>
                                            <button onclick="handleDelete(<?php echo htmlspecialchars($supplier['Supplier_id']); ?>)" class="text-red-600 hover:text-red-900 transition font-semibold">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No suppliers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </main>

    <!-- SUPPLIER MODAL (CREATE / VIEW / EDIT) -->
    <div id="supplier-modal" class="modal fixed inset-0 z-40 overflow-y-auto">
        <!-- Backdrop -->
        <div id="modal-backdrop" class="fixed inset-0 bg-gray-900 bg-opacity-50 transition-opacity"></div>
        
        <!-- Modal Content -->
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div class="bg-white rounded-xl shadow-3xl w-full max-w-2xl transform transition-all relative">
                
                <!-- Modal Header -->
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 id="modal-title" class="text-2xl font-bold text-gray-800">New Supplier Registration</h3>
                    <button type="button" id="modal-close-btn" class="text-gray-400 hover:text-gray-600 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Modal Body (Form) -->
                <form id="supplier-form" method="POST" action="supplier.php" class="p-6 space-y-4">
                    <!-- Hidden fields for logic -->
                    <input type="hidden" id="supplier_id" name="supplier_id" value="">
                    <input type="hidden" id="action" name="action" value="add">
                    
                    <div>
                        <label for="company_name" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                        <input type="text" id="company_name" name="company_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                    </div>
                    
                    <div>
                        <label for="contact_person" class="block text-sm font-medium text-gray-700 mb-1">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                        </div>
                        <div>
                            <label for="contact_num" class="block text-sm font-medium text-gray-700 mb-1">Contact Number</label>
                            <input type="text" id="contact_num" name="contact_num" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150">
                        </div>
                    </div>
                    
                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="address" name="address" rows="3" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition duration-150"></textarea>
                    </div>

                    <!-- Modal Footer (Action Buttons) -->
                    <div class="pt-4 flex justify-end space-x-3 border-t border-gray-100">
                        <button type="button" onclick="closeModal()" class="px-5 py-2 text-sm font-semibold text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition duration-150">
                            Cancel
                        </button>
                        <!-- Button text changes between 'Register Supplier', 'Edit Mode', and 'Save Changes' via JS -->
                        <button type="submit" id="modal-save-btn" class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg shadow-md hover:bg-blue-700 transition duration-150">
                            Register Supplier
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

</body>
</html>
