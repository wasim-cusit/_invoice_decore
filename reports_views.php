<?php
require_once 'db.php';
if (!isset($_GET['id'])) {
    echo '<div class="alert alert-danger">No expense ID provided.</div>';
    exit;
}
$expense_id = (int)$_GET['id'];
$stmt = $conn->prepare("SELECT e.*, c.name as company_name FROM expenses e LEFT JOIN companies c ON e.company_id = c.id WHERE e.id = ?");
$stmt->bind_param("i", $expense_id);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$expense) {
    echo '<div class="alert alert-danger">Expense not found.</div>';
    exit;
}
$items = [];
$stmt = $conn->prepare("SELECT * FROM expense_items WHERE expense_id = ?");
$stmt->bind_param("i", $expense_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();
?>
<div class="container mt-4">
    <div class="card p-4">
        <h3 class="mb-3">Expense Details</h3>
        <div class="mb-2"><strong>Company:</strong> <?= htmlspecialchars($expense['company_name']) ?></div>
        <div class="mb-2"><strong>Date:</strong> <?= htmlspecialchars($expense['expense_date']) ?></div>
        <div class="mb-2"><strong>Description:</strong> <?= htmlspecialchars($expense['description']) ?></div>
        <div class="mb-2"><strong>Total Amount:</strong> Rs <?= number_format($expense['total_amount'], 2) ?></div>
        <h5 class="mt-4">Items</h5>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td>Rs <?= number_format($item['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="index.php?page=reports_expenses" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-1"></i> Back to Expenses</a>
    </div>
</div> 