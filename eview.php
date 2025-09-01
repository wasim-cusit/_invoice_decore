<?php
// Database connection
$db = new mysqli('localhost', 'root', '', 'u742242489_decore');

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$expense_id = intval($_GET['id']);

// Fetch expense header
$expense = $db->query("
    SELECT e.*, c.name as company_name 
    FROM expenses e
    JOIN companies c ON e.company_id = c.id
    WHERE e.id = $expense_id
")->fetch_assoc();

// Fetch expense items
$items = $db->query("
    SELECT * FROM expense_items 
    WHERE expense_id = $expense_id
    ORDER BY id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Details</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .detail-container { max-width: 800px; margin: 0 auto; }
        .header-info { margin-bottom: 30px; }
        .header-info p { margin: 5px 0; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .detail-table th, .detail-table td { border: 1px solid #ddd; padding: 10px; }
        .detail-table th { background-color: #f2f2f2; text-align: left; }
        .total-row { font-weight: bold; }
        .back-btn { 
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .back-btn:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <div class="detail-container">
        <h1>Expense Details</h1>
        
        <div class="header-info">
            <p><strong>Company:</strong> <?= htmlspecialchars($expense['company_name']) ?></p>
            <p><strong>Date:</strong> <?= htmlspecialchars($expense['expense_date']) ?></p>
            <p><strong>Description:</strong> <?= htmlspecialchars($expense['description']) ?></p>
            <p><strong>Created At:</strong> <?= htmlspecialchars($expense['created_at']) ?></p>
        </div>
        
        <h2>Expense Items</h2>
        <table class="detail-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= number_format($item['amount'], 2) ?></td>
                    </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td>Total Amount</td>
                    <td><?= number_format($expense['total_amount'], 2) ?></td>
                </tr>
            </tbody>
        </table>
        
        <a href="index.php?page=reports_expenses" class="back-btn">Back to Expenses Report</a>
    </div>
</body>
</html>