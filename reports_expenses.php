<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle deletion
if (isset($_GET['delete'])) {
    $expense_id = (int)$_GET['delete'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete expense items first
        $stmt = $conn->prepare("DELETE FROM expense_items WHERE expense_id = ?");
        $stmt->bind_param("i", $expense_id);
        $stmt->execute();
        
        // Delete expense header
        $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
        $stmt->bind_param("i", $expense_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to prevent form resubmission
        header("Location: index.php?page=reports_expenses");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error deleting expense: " . $e->getMessage();
    }
}

// Fetch expenses with company names
$expenses = $conn->query("
    SELECT e.*, c.name as company_name 
    FROM expenses e 
    LEFT JOIN companies c ON e.company_id = c.id 
    ORDER BY e.expense_date DESC
");
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .card {
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.07);
        border-radius: 12px;
    }

    .table th,
    .table td {
        vertical-align: middle;
    }



    .actions .btn {
        margin-right: 6px;
    }

    .actions .btn:last-child {
        margin-right: 0;
    }

    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
    }

    .report-table {
        border:1px solid #dee2e6;
        border-radius:12px;
        overflow:hidden;
        width:100%;
        background:#fff;
        margin-bottom: 24px;
    }
    .report-table th {
        background: linear-gradient(90deg, #e3f2fd 0%, #f8f9fa 100%);
        color: #1976d2;
        font-weight: 700;
        font-size: 1rem;
        text-align: left;
        vertical-align: middle;
        padding: 12px 10px;
        border:1px solid #dee2e6;
    }
    .report-table td {
        font-size: 0.97rem;
        color: #333;
        text-align: left;
        vertical-align: middle;
        padding: 10px 10px;
        border:1px solid #dee2e6;
        background: #fff;
    }
    .report-table tbody tr:hover {
        background: #f1f8ff;
        transition: background 0.2s;
    }
    .report-table .actions {
        white-space: nowrap;
    }
</style>

<div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd; max-width:1200px; margin:30px auto;">
    <div class="d-flex align-items-center bg-light rounded shadow-sm p-3 mb-4 justify-content-between" style="gap: 12px;">
        <div class="d-flex align-items-center" style="gap: 12px;">
            <span class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
                <i class="fas fa-money-bill-wave"></i>
            </span>
            <div>
                <h2 class="mb-0" style="font-weight: 600; letter-spacing: 1px; color: #198754;">Expenses Report</h2>
                <small class="text-muted">Overview of all expenses in the system</small>
            </div>
        </div>
        <button type="button" class="btn btn-primary" id="openAddExpenseModal"><i class="fas fa-plus me-1"></i> Add New Expense</button>
    </div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-labelledby="addExpenseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExpenseModalLabel">Add New Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="addExpenseModalBody">
                <div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
            </div>
        </div>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="report-table">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Company</th>
                    <th>Description</th>
                    <th>Total Amount</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($expense = $expenses->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($expense['expense_date']) ?></td>
                        <td><?= htmlspecialchars($expense['company_name']) ?></td>
                        <td><?= htmlspecialchars($expense['description']) ?></td>
                        <td><span class="badge bg-primary">Rs <?= number_format($expense['total_amount'] ?? 0, 2) ?></span></td>
                        <td><?= htmlspecialchars($expense['created_at']) ?></td>
                        <td class="actions">
                            <button type="button" class="btn btn-sm btn-warning openEditExpenseModal" data-expense-id="<?= $expense['id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                            <a href="index.php?page=reports_views&id=<?= $expense['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                            <a href="index.php?page=reports_expenses&delete=<?= $expense['id'] ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this expense?')"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add New Expense Modal
    document.getElementById('openAddExpenseModal').addEventListener('click', function() {
        // Set modal title for Add
        document.getElementById('addExpenseModalLabel').textContent = 'Add New Expense';
        var modal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
        var modalBody = document.getElementById('addExpenseModalBody');
        modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
        fetch('add_expense.php')
            .then(res => res.text())
            .then(html => {
                var temp = document.createElement('div');
                temp.innerHTML = html;
                var form = temp.querySelector('form');
                if (form) {
                    modalBody.innerHTML = form.outerHTML;
                } else {
                    modalBody.innerHTML = html;
                }
                if (typeof initAddExpenseModalJS === 'function') {
                    initAddExpenseModalJS();
                }
                modal.show();
            });
    });

    // Edit expense modal functionality
    document.querySelectorAll('.openEditExpenseModal').forEach(btn => {
        btn.addEventListener('click', function() {
            // Set modal title for Edit
            document.getElementById('addExpenseModalLabel').textContent = 'Edit Expense';
            const expenseId = this.getAttribute('data-expense-id');
            var modal = new bootstrap.Modal(document.getElementById('addExpenseModal'));
            var modalBody = document.getElementById('addExpenseModalBody');
            modalBody.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
            fetch('add_expense.php?edit_id=' + encodeURIComponent(expenseId))
                .then(res => res.text())
                .then(html => {
                    var temp = document.createElement('div');
                    temp.innerHTML = html;
                    var form = temp.querySelector('form');
                    if (form) {
                        modalBody.innerHTML = form.outerHTML;
                    } else {
                        modalBody.innerHTML = html;
                    }
                    if (typeof initAddExpenseModalJS === 'function') {
                        initAddExpenseModalJS();
                    }
                    modal.show();
                });
        });
    });
});

function initAddExpenseModalJS() {
    var modalBody = document.getElementById('addExpenseModalBody');
    var form = modalBody.querySelector('form');
    if (!form) return;

    let itemCount = modalBody.querySelectorAll('.item-row').length;

    // Add new item row
    var addItemBtn = modalBody.querySelector('#add-item');
    var itemsContainer = modalBody.querySelector('#items-container');
    if (addItemBtn && itemsContainer) {
        addItemBtn.onclick = function() {
            const newRow = document.createElement('div');
            newRow.className = 'row item-row mb-2';
            newRow.id = 'item-row-' + itemCount;
            newRow.innerHTML = `
                <div class="col-md-6 mb-2">
                    <input type="text" name="item_name[]" class="form-control" placeholder="Item name" required>
                </div>
                <div class="col-md-4 mb-2">
                    <input type="number" name="amount[]" class="form-control item-amount" placeholder="Amount" step="0.01" required>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-center">
                    <button type="button" class="remove-item btn btn-danger btn-sm" data-row="item-row-${itemCount}"><i class="fas fa-trash"></i></button>
                </div>
            `;
            itemsContainer.appendChild(newRow);
            newRow.querySelector('.remove-item').onclick = function() {
                newRow.remove();
                updateTotal();
            };
            newRow.querySelector('.item-amount').oninput = updateTotal;
            itemCount++;
        };
    }

    // Remove item logic for existing rows
    itemsContainer.querySelectorAll('.remove-item').forEach(btn => {
        btn.onclick = function() {
            var row = btn.closest('.item-row');
            if (row) row.remove();
            updateTotal();
        };
    });

    // Total calculation
    function updateTotal() {
        let total = 0;
        itemsContainer.querySelectorAll('.item-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        var totalInput = modalBody.querySelector('input[name="total_amount"]');
        if (totalInput) totalInput.value = total.toFixed(2);
    }
    itemsContainer.querySelectorAll('.item-amount').forEach(input => {
        input.oninput = updateTotal;
    });
    updateTotal();

    // AJAX form submit
    form.onsubmit = function(e) {
        e.preventDefault();
        const formData = new FormData(form);
        fetch('add_expense.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.text())
        .then(html => {
            if (html.includes('Expense added successfully')) {
                var modal = bootstrap.Modal.getInstance(document.getElementById('addExpenseModal'));
                modal.hide();
                setTimeout(() => location.reload(), 500);
            } else {
                modalBody.innerHTML = html;
                initAddExpenseModalJS();
            }
        });
    };
}
</script>