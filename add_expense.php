<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$isEdit = false;
$expense = [
    'expense_date' => '',
    'company_id' => '',
    'description' => '',
    'total_amount' => '',
];
$items = [];

if (isset($_GET['edit_id'])) {
    $isEdit = true;
    $edit_id = (int)$_GET['edit_id'];
    // Fetch expense
    $stmt = $conn->prepare("SELECT * FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense = $result->fetch_assoc();

    // Fetch items
    $stmt = $conn->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_date = $_POST['expense_date'];
    $company_id = $_POST['company_id'];
    $description = $_POST['description'];
    $total_amount = $_POST['total_amount'];
    $item_names = $_POST['item_name'];
    $amounts = $_POST['amount'];
    $edit_id = isset($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;

    if ($edit_id) {
        // Update expense
        $stmt = $conn->prepare("UPDATE expenses SET expense_date=?, company_id=?, description=?, total_amount=? WHERE id=?");
        $stmt->bind_param("sissd", $expense_date, $company_id, $description, $total_amount, $edit_id);
        $stmt->execute();

        // Delete old items
        $stmt = $conn->prepare("DELETE FROM expense_items WHERE expense_id=?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();

        // Insert new items
        $stmt = $conn->prepare("INSERT INTO expense_items (expense_id, item_name, amount) VALUES (?, ?, ?)");
        foreach ($item_names as $idx => $name) {
            $amt = $amounts[$idx];
            $stmt->bind_param("isd", $edit_id, $name, $amt);
            $stmt->execute();
        }
        $success = 'Expense updated successfully.';
    } else {
        // Insert new expense
        $stmt = $conn->prepare("INSERT INTO expenses (expense_date, company_id, description, total_amount, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sisd", $expense_date, $company_id, $description, $total_amount);
        $stmt->execute();
        $expense_id = $stmt->insert_id;

        // Insert items
        $stmt = $conn->prepare("INSERT INTO expense_items (expense_id, item_name, amount) VALUES (?, ?, ?)");
        foreach ($item_names as $idx => $name) {
            $amt = $amounts[$idx];
            $stmt->bind_param("isd", $expense_id, $name, $amt);
            $stmt->execute();
        }
        $success = 'Expense added successfully.';
    }
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name ASC");
?>

<div class="page-header d-flex justify-content-between align-items-center">
    <h2 class="mb-0 text-gray-800 font-weight-bold">
        <i class="fas fa-receipt me-2 text-primary"></i>
        <?= $isEdit ? 'Edit Expense' : 'Add New Expense' ?>
    </h2>
    <a href="index.php?page=reports_expenses" class="btn btn-outline-primary ms-2">
        <i class="fas fa-list me-1"></i> View All Expenses
    </a>
</div>

<div class="card shadow-sm border-0 rounded-lg mt-4">
    <div class="card-header bg-light py-3 border-bottom">
        <h5 class="mb-0 text-gray-700">
            <i class="fas fa-info-circle me-2"></i>Expense Details
        </h5>
    </div>
    <div class="card-body p-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4">
                <i class="fas fa-check-circle me-2"></i>
                <?= $success ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" autocomplete="off">
            <?php if ($isEdit): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
            <?php endif; ?>
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-medium text-gray-700">Company <span class="text-danger">*</span></label>
                    <select name="company_id" class="form-select border-2 py-2" required>
                        <option value="">Select Company</option>
                        <?php while ($company = $companies->fetch_assoc()): ?>
                            <option value="<?= $company['id'] ?>" <?= ($expense['company_id'] == $company['id']) ? 'selected' : '' ?>>
                                <?= $company['name'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium text-gray-700">Date <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" class="form-control border-2 py-2" 
                           value="<?= htmlspecialchars($expense['expense_date']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-medium text-gray-700">Description</label>
                    <textarea name="description" class="form-control border-2 py-2" rows="1"><?= htmlspecialchars($expense['description']) ?></textarea>
                </div>
            </div>
            
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 text-gray-700 fw-medium">
                        <i class="fas fa-list-ul me-2"></i>Expense Items
                    </h5>
                    <button type="button" id="add-item" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i> Add Item
                    </button>
                </div>
                
                <div id="items-container" class="border rounded">
                    <?php if (empty($items)): ?>
                        <div class="item-row p-3 border-bottom">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label small text-muted">Item Name</label>
                                    <input type="text" name="item_name[]" class="form-control border-1 py-2" placeholder="e.g. Office Supplies" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted">Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">PKR</span>
                                        <input type="number" name="amount[]" class="form-control border-1 py-2 item-amount" placeholder="0.00" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex">
                                    <button type="button" class="remove-item btn btn-outline-danger btn-sm ms-auto" disabled>
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <div class="item-row p-3 border-bottom">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted">Item Name</label>
                                        <input type="text" name="item_name[]" class="form-control border-1 py-2" value="<?= htmlspecialchars($item['item_name']) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small text-muted">Amount</label>
                                        <div class="input-group">
                                            <span class="input-group-text">PKR</span>
                                            <input type="number" name="amount[]" class="form-control border-1 py-2 item-amount" value="<?= htmlspecialchars($item['amount']) ?>" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-2 d-flex">
                                        <button type="button" class="remove-item btn btn-outline-danger btn-sm ms-auto">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="total-amount bg-light p-3 rounded d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0 text-gray-700 fw-medium">Total Amount:</h5>
                <div class="input-group w-auto">
                    <span class="input-group-text bg-white">PKR</span>
                    <input type="number" name="total_amount" class="form-control border-1 py-2 fw-bold fs-5 text-end" 
                           value="<?= htmlspecialchars($expense['total_amount']) ?>" step="0.01" required readonly>
                </div>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-success px-4 py-2 fw-medium">
                    <i class="fas fa-save me-2"></i>
                    <?= $isEdit ? 'Update Expense' : 'Save Expense' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    $(document).ready(function() {
        let itemCount = <?= !empty($items) ? count($items) : 1 ?>;
        
        // Add new item row
        $('#add-item').click(function() {
            const newRow = `
                <div class="item-row p-3 border-bottom" id="item-row-${itemCount}">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label small text-muted">Item Name</label>
                            <input type="text" name="item_name[]" class="form-control border-1 py-2" placeholder="e.g. Office Supplies" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small text-muted">Amount</label>
                            <div class="input-group">
                                <span class="input-group-text">PKR</span>
                                <input type="number" name="amount[]" class="form-control border-1 py-2 item-amount" placeholder="0.00" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-2 d-flex">
                            <button type="button" class="remove-item btn btn-outline-danger btn-sm ms-auto">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#items-container').append(newRow);
            itemCount++;
            
            // Enable remove buttons if there's more than one item
            if ($('.item-row').length > 1) {
                $('.remove-item').prop('disabled', false);
            }
        });
        
        // Remove item row
        $(document).on('click', '.remove-item', function() {
            $(this).closest('.item-row').remove();
            calculateTotal();
            
            // Disable remove button if only one item left
            if ($('.item-row').length === 1) {
                $('.remove-item').prop('disabled', true);
            }
        });
        
        // Calculate total amount when item amounts change
        $(document).on('input', '.item-amount', function() {
            calculateTotal();
        });

        function calculateTotal() {
            let total = 0;
            $('.item-amount').each(function() {
                const val = parseFloat($(this).val()) || 0;
                total += val;
            });
            $('input[name="total_amount"]').val(total.toFixed(2));
        }
        
        // Initialize total calculation
        calculateTotal();
    });
</script>

<style>
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #e9ecef;
    }
    
    .card {
        border: none;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .card-header {
        background-color: #f8f9fa;
    }
    
    .form-control, .form-select {
        border-radius: 0.375rem;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .item-row {
        transition: all 0.2s ease;
    }
    
    .item-row:hover {
        background-color: #f8f9fa;
    }
    
    .remove-item {
        transition: all 0.2s ease;
    }
    
    .remove-item:hover {
        background-color: #dc3545;
        color: white;
    }
    
    .total-amount {
        border: 1px solid #dee2e6;
    }
    
    .btn-success {
        background-color: #198754;
        border-color: #198754;
        transition: all 0.2s ease;
    }
    
    .btn-success:hover {
        background-color: #157347;
        border-color: #146c43;
    }
    
    .btn-primary {
        background-color: #0d6efd;
        border-color: #0d6efd;
        transition: all 0.2s ease;
    }
    
    .btn-primary:hover {
        background-color: #0b5ed7;
        border-color: #0a58ca;
    }
    
    .text-gray-700 {
        color: #495057;
    }
    
    .text-gray-800 {
        color: #343a40;
    }
    
    .fw-medium {
        font-weight: 500;
    }
</style>