<?php
// --- AJAX DELETE HANDLER ---
if (isset($_POST['ajax_delete']) && isset($_POST['id'])) {
    require_once 'db.php';
    $delete_id = (int)$_POST['id'];
    $delete_sql = "DELETE FROM quotations WHERE id = $delete_id";
    if ($conn->query($delete_sql)) {
        echo json_encode(['success' => true, 'message' => 'Quotation deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error deleting quotation']);
    }
    exit;
}

if (!defined('ALLOW_INCLUDE')) {
    die("Access denied");
}
// Use mysqli connection from db.php
require_once 'db.php';

// Pagination variables
$per_page = 10;
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = max(0, ($page_num - 1) * $per_page);

// Filter variables
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$qp_search = isset($_GET['qp_search']) ? $conn->real_escape_string($_GET['qp_search']) : '';
$company_filter = isset($_GET['company']) ? (int)$_GET['company'] : 0;
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Base SQL query
$sql = "
    SELECT 
        q.*, 
        c.name AS client_name, 
        co.name AS company_name,
        COUNT(*) OVER() AS total_count
    FROM quotations q
    INNER JOIN clients c ON q.client_id = c.id
    INNER JOIN companies co ON q.company_id = co.id
";

// Add conditions based on filters
$conditions = [];
if (!empty($search)) {
    $conditions[] = "(c.name LIKE '%$search%')";
}
if (!empty($qp_search)) {
    $conditions[] = "(q.quotation_number LIKE '%$qp_search%')";
}
if ($company_filter > 0) {
    $conditions[] = "q.company_id = $company_filter";
}
if (!empty($date_from) && !empty($date_to)) {
    $conditions[] = "q.date BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $conditions[] = "q.date >= '$date_from'";
} elseif (!empty($date_to)) {
    $conditions[] = "q.date <= '$date_to'";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

// Add sorting and pagination
$sql .= " ORDER BY q.created_at DESC LIMIT $offset, $per_page";

$result = $conn->query($sql);

// Fetch data into array and get total count
$quotations = [];
$total_count = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($total_count === 0) {
            $total_count = $row['total_count'];
        }
        unset($row['total_count']);
        $quotations[] = $row;
    }
} else {
    $quotations = [];
}

// Get companies for filter dropdown
$companies = [];
$company_result = $conn->query("SELECT id, name FROM companies");
if ($company_result && $company_result->num_rows > 0) {
    while ($row = $company_result->fetch_assoc()) {
        $companies[] = $row;
    }
}

// Calculate total pages
$total_pages = ceil($total_count / $per_page);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotations Management</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            background-color: #f9f9f9;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h3 {
            color: #444;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
            font-weight: 600;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .actions {
            white-space: nowrap;
        }

        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 2px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }



        .search-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-filter input,
        .search-filter select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
        }

        .search-filter button {
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 8px 15px;
        }

        .search-filter .reset-btn {
            background: linear-gradient(135deg, #f44336, #e53935);
            /* Vibrant gradient */
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.5px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(244, 67, 54, 0.4);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .search-filter .reset-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -75%;
            width: 50%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: skewX(-20deg);
            transition: left 0.5s ease;
            z-index: 0;
        }

        .search-filter .reset-btn:hover::before {
            left: 125%;
            /* Slide shine effect */
        }

        .search-filter .reset-btn:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: 0 6px 20px rgba(244, 67, 54, 0.6);
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.02);
            }

            100% {
                transform: scale(1);
            }
        }

        .search-filter .reset-btn {
            animation: pulse 2.5s infinite ease-in-out;
        }


        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 16px;
            margin: 0 2px;
            min-width: 36px;
            display: inline-block;
            text-align: center;
        }

        .pagination a:hover {
            background-color: #f2f2f2;
        }

        .pagination .current {
            background-color: #4caf50;
            color: white;
            border-color: #4caf50;
            font-weight: bold;
        }

        .total-quotations {
            margin-bottom: 15px;
            font-style: italic;
            color: #666;
        }

        button,
        input,
        optgroup,
        select,
        textarea {
            margin: 0;
            font-family: inherit;
            line-height: inherit;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
        }

        .quotations-table {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
            width: 100%;
            background: #fff;
            margin-bottom: 24px;
        }

        .quotations-table th {
            background: linear-gradient(90deg, #e3f2fd 0%, #f8f9fa 100%);
            color: #1976d2;
            font-weight: 700;
            font-size: 1rem;
            text-align: left;
            vertical-align: middle;
            padding: 12px 10px;
            border: 1px solid #dee2e6;
        }

        .quotations-table td {
            font-size: 0.97rem;
            color: #333;
            text-align: left;
            vertical-align: middle;
            padding: 10px 10px;
            border: 1px solid #dee2e6;
            background: #fff;
        }

        .quotations-table tbody tr:hover {
            background: #f1f8ff;
            transition: background 0.2s;
        }

        .quotations-table .actions {
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd; max-width:1200px; margin:30px auto;">
        <div class="d-flex align-items-center bg-light rounded shadow-sm pt-2 pb-3 px-3 mb-4" style="gap: 12px;">
            <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
                <i class="fas fa-file-invoice-dollar"></i>
            </span>
            <div>
                <h2 class="mb-0" style="font-weight: 600; letter-spacing: 1px;">Invoice Report</h2>
                <small class="text-muted">Overview of all quotations in the system</small>
            </div>
        </div>
        <h3>Quotation Management (<?= $total_count ?> quotations)</h3>

        <form method="get" action="index.php" id="filterForm">
            <input type="hidden" name="page" value="reports_invoices">
            <div class="search-filter">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="Client name" value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="filter-group">
                    <input type="text" name="qp_search" placeholder="QP number" value="<?= htmlspecialchars($qp_search) ?>">
                </div>

                <div class="filter-group">
                    <label>From:</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()">
                </div>

                <div class="filter-group">
                    <label>To:</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()">
                </div>

                <select name="company" onchange="this.form.submit()">
                    <option value="0">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= $company['id'] ?>" <?= $company_filter == $company['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Search</button>
                <a href="index.php?page=reports_invoices" class="reset-btn">Reset</a>
            </div>
        </form>

        <table class="quotations-table">
            <thead>
                <tr>
                    <th>Quotation #</th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Company</th>
                    <th>Amount</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($quotations)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No quotations found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotations as $quote): ?>
                        <tr>
                            <td><?= htmlspecialchars($quote['quotation_number']) ?></td>
                            <td><?= htmlspecialchars($quote['date']) ?></td>
                            <td><?= htmlspecialchars($quote['client_name']) ?></td>
                            <td><?= htmlspecialchars($quote['company_name']) ?></td>
                            <td><?= number_format($quote['total_amount'] ?? 0, 2) ?></td>
                            <td><?= date('M d, Y', strtotime($quote['created_at'])) ?></td>
                            <td class="actions">
                                <a href="index.php?page=reports_invoices&delete=<?= $quote['id'] ?>" class="btn btn-danger btn-sm d-inline-flex align-items-center gap-1" data-id="<?= $quote['id'] ?>" onclick="return confirm('Are you sure you want to delete this quotation?')">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                                <a href="Pages/quotations.php?quotation_id=<?= $quote['id'] ?>" class="btn btn-info" target="_blank" style="margin-left: 5px;">View/Print</a>
                                <a href="Pages/view_quotation.php?quotation_id=<?= $quote['id'] ?>" class="btn btn-success btn-calc" target="_blank" style="margin-left: 5px;">View Calculation</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="pagination pagination-sm justify-content-center mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <?php if ($page_num > 1): ?>
                        <li class="page-item"><a class="page-link" href="index.php?page=reports_invoices&p=1&search=<?= urlencode($search) ?>&qp_search=<?= urlencode($qp_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">First</a></li>
                        <li class="page-item"><a class="page-link" href="index.php?page=reports_invoices&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&qp_search=<?= urlencode($qp_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Prev</a></li>
                    <?php endif; ?>

                    <?php
                    // Show page numbers
                    $start = max(1, $page_num - 2);
                    $end = min($total_pages, $page_num + 2);

                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i == $page_num): ?>
                            <li class="page-item"><span class="page-link current"><?= $i ?></span></li>
                        <?php else: ?>
                            <li class="page-item"><a class="page-link" href="index.php?page=reports_invoices&p=<?= $i ?>&search=<?= urlencode($search) ?>&qp_search=<?= urlencode($qp_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page_num < $total_pages): ?>
                        <li class="page-item"><a class="page-link" href="index.php?page=reports_invoices&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&qp_search=<?= urlencode($qp_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Next</a></li>
                        <li class="page-item"><a class="page-link" href="index.php?page=reports_invoices&p=<?= $total_pages ?>&search=<?= urlencode($search) ?>&qp_search=<?= urlencode($qp_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">Last</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <?php
    // Handle delete action
    if (isset($_GET['delete'])) {
        $delete_id = (int)$_GET['delete'];
        $delete_sql = "DELETE FROM quotations WHERE id = $delete_id";
        if ($conn->query($delete_sql)) {
            echo "<script>alert('Quotation deleted successfully'); window.location.href='index.php?page=reports_invoices';</script>";
        } else {
            echo "<script>alert('Error deleting quotation'); window.location.href='index.php?page=reports_invoices';</script>";
        }
    }
    ?>
</body>

</html>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // AJAX delete for quotations
        document.querySelectorAll('.btn-delete').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to delete this quotation?')) return;
                const row = btn.closest('tr');
                const id = btn.getAttribute('data-id') || btn.href.match(/delete=(\d+)/)?.[1];
                if (!id) return;
                fetch('report_invoices.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'ajax_delete=1&id=' + encodeURIComponent(id)
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            row.remove();
                            showDeleteToast('Quotation deleted successfully');
                        } else {
                            alert(data.message || 'Error deleting quotation');
                        }
                    })
                    .catch(() => alert('Error deleting quotation'));
            });
        });

        // Toast for delete
        window.showDeleteToast = function(msg) {
            let toast = document.createElement('div');
            toast.className = 'alert alert-success';
            toast.style.position = 'fixed';
            toast.style.top = '30px';
            toast.style.right = '30px';
            toast.style.zIndex = 9999;
            toast.innerHTML = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 2000);
        };
    });
</script>