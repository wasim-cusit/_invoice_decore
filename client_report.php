<?php
// define('ALLOW_INCLUDE', true);
require_once 'db.php';

// Pagination
$per_page = 10;
$page_num = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset = max(0, ($page_num - 1) * $per_page);

// Filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$phone_search = isset($_GET['phone_search']) ? $conn->real_escape_string($_GET['phone_search']) : '';
$company_filter = isset($_GET['company']) ? (int)$_GET['company'] : 0;
$date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

// Base query
$sql = "
    SELECT 
        c.*, 
        co.name AS company_name,
        COUNT(*) OVER() AS total_count
    FROM clients c
    LEFT JOIN companies co ON c.company_id = co.id
";

$conditions = [];
if (!empty($search)) {
    $conditions[] = "(c.name LIKE '%$search%' OR c.address LIKE '%$search%')";
}
if (!empty($phone_search)) {
    $conditions[] = "(c.phone LIKE '%$phone_search%')";
}
if ($company_filter > 0) {
    $conditions[] = "c.company_id = $company_filter";
}
if (!empty($date_from) && !empty($date_to)) {
    $conditions[] = "c.created_at BETWEEN '$date_from' AND '$date_to'";
} elseif (!empty($date_from)) {
    $conditions[] = "c.created_at >= '$date_from'";
} elseif (!empty($date_to)) {
    $conditions[] = "c.created_at <= '$date_to'";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

$sql .= " ORDER BY c.created_at DESC LIMIT $offset, $per_page";
$result = $conn->query($sql);

$clients = [];
$total_count = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($total_count === 0) {
            $total_count = $row['total_count'];
        }
        unset($row['total_count']);
        $clients[] = $row;
    }
}

$companies = [];
$company_result = $conn->query("SELECT id, name FROM companies");
if ($company_result && $company_result->num_rows > 0) {
    while ($row = $company_result->fetch_assoc()) {
        $companies[] = $row;
    }
}

$total_pages = ceil($total_count / $per_page);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Clients Management</title>
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
            padding: 4px 7px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background-color: #f2f2f2;
        }

        .pagination .current {
            background-color: #4caf50;
            color: white;
            border-color: #4caf50;
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
    </style>
</head>

<body>
    <div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd; max-width:1200px; margin:30px auto;">
        <div class="d-flex align-items-center bg-light rounded shadow-sm pt-2 pb-3 px-3 mb-4" style="gap: 14px;">
            <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
                <i class="fas fa-users"></i>
            </span>
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 1.5rem; font-weight: 600; color: #1976d2; letter-spacing: 1px;">Clients Report</span>
                <small class="text-muted" style="font-size: 1rem;">Overview of all clients in the system</small>
            </div>
        </div>

        <form method="get" action="index.php" id="filterForm">
            <input type="hidden" name="page" value="reports_clients">
            <div class="search-filter">
                <input type="text" name="search" placeholder="Client name/address" value="<?= htmlspecialchars($search) ?>">
                <input type="text" name="phone_search" placeholder="Phone number" value="<?= htmlspecialchars($phone_search) ?>">
                <label>From: <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" onchange="this.form.submit()"></label>
                <label>To: <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" onchange="this.form.submit()"></label>
                <select name="company" onchange="this.form.submit()">
                    <option value="0">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= $company['id'] ?>" <?= $company_filter == $company['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($company['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Search</button>
                <a href="index.php?page=reports_clients" class="btn btn-secondary btn-sm">Reset</a>
            </div>
        </form>

        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Phone</th>
                    <th>Address</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No clients found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?= htmlspecialchars($client['id']) ?></td>
                            <td><?= htmlspecialchars($client['name']) ?></td>
                            <td><?= htmlspecialchars($client['company_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($client['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars(substr($client['address'] ?? '', 0, 50)) ?><?= strlen($client['address'] ?? '') > 50 ? '...' : '' ?></td>
                            <td><?= date('M d, Y', strtotime($client['created_at'])) ?></td>
                            <td class="actions">
                                <div class="btn-group" role="group">
                                    <a href="client_quotation.php?client_id=<?= $client['id'] ?>&company_id=<?= $client['company_id'] ?? 0 ?>" class="btn btn-info btn-sm me-1">
                                        <i class="fas fa-eye"></i> View Report
                                    </a>
                                    <a href="#" class="btn btn-success btn-sm" onclick='openEditClientModal({
  id: "<?= $client['id'] ?>",
  name: "<?= htmlspecialchars($client['name'], ENT_QUOTES) ?>",
  phone: "<?= htmlspecialchars($client['phone'] ?? '', ENT_QUOTES) ?>",
  address: "<?= htmlspecialchars($client['address'] ?? '', ENT_QUOTES) ?>",
  company_id: "<?= $client['company_id'] ?? 0 ?>"
}); return false;'>
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                </div>
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
                    <a href="index.php?page=reports_clients&p=1&search=<?= urlencode($search) ?>&phone_search=<?= urlencode($phone_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-primary btn-lg">First</a>
                    <a href="index.php?page=reports_clients&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&phone_search=<?= urlencode($phone_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-primary btn-lg">Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page_num - 2);
                $end = min($total_pages, $page_num + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page_num): ?>
                        <span class="btn btn-primary btn-lg active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="index.php?page=reports_clients&p=<?= $i ?>&search=<?= urlencode($search) ?>&phone_search=<?= urlencode($phone_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-primary btn-lg"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page_num < $total_pages): ?>
                    <a href="index.php?page=reports_clients&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&phone_search=<?= urlencode($phone_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-primary btn-lg">Next</a>
                    <a href="index.php?page=reports_clients&p=<?= $total_pages ?>&search=<?= urlencode($search) ?>&phone_search=<?= urlencode($phone_search) ?>&company=<?= $company_filter ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>" class="btn btn-outline-primary btn-lg">Last</a>
                <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Add Bootstrap modal for editing client -->
    <div class="modal fade" id="editClientModal" tabindex="-1" aria-labelledby="editClientModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editClientForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editClientModalLabel">Edit Client</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Company</label>
                            <select name="company_id" id="edit_company_id" class="form-select" required>
                                <?php foreach ($companies as $comp): ?>
                                    <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openEditClientModal(client) {
            document.getElementById('edit_id').value = client.id;
            document.getElementById('edit_name').value = client.name;
            document.getElementById('edit_phone').value = client.phone || '';
            document.getElementById('edit_address').value = client.address || '';
            document.getElementById('edit_company_id').value = client.company_id;
            var modal = new bootstrap.Modal(document.getElementById('editClientModal'));
            modal.show();
        }

        document.getElementById('editClientForm').onsubmit = function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('ajax', '1');
            fetch('add_client.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to update client.');
                    }
                });
        };

        function deleteClient(clientId, btn) {
            if (!confirm('Are you sure you want to delete this client?')) return;
            fetch('client_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'ajax_delete=1&delete_client=' + encodeURIComponent(clientId)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the row from the table
                        var row = btn.closest('tr');
                        row.parentNode.removeChild(row);
                    } else {
                        alert('Error deleting client: ' + (data.error || 'Unknown error'));
                    }
                });
        }
    </script>
    <?php
    // Handle AJAX delete
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete']) && isset($_POST['delete_client'])) {
        $delete_id = (int)$_POST['delete_client'];
        $delete_sql = "DELETE FROM clients WHERE id = $delete_id";
        if ($conn->query($delete_sql)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit;
    }
    ?>
</body>

</html>