<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['edit_id'])) {
    require_once 'db.php';
    $id = (int)$_POST['edit_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $company_id = (int)$_POST['company_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $bundle_quantity = isset($_POST['bundle_quantity']) ? (int)$_POST['bundle_quantity'] : 0;
    $bundle_price = isset($_POST['bundle_price']) ? (float)$_POST['bundle_price'] : 0;

    $sql = "UPDATE hardware SET 
                name='$name', 
                company_id=$company_id, 
                quantity=$quantity, 
                price=$price, 
                bundle_quantity=$bundle_quantity, 
                bundle_price=$bundle_price 
            WHERE id=$id";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
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
$company_filter = isset($_GET['company']) ? (int)$_GET['company'] : 0;

// Base SQL query
$sql = "
    SELECT 
        h.*, 
        co.name AS company_name,
        COUNT(*) OVER() AS total_count
    FROM hardware h
    LEFT JOIN companies co ON h.company_id = co.id
";

// Add conditions based on filters
$conditions = [];
if (!empty($search)) {
    $conditions[] = "(h.name LIKE '%$search%' OR h.quantity LIKE '%$search%')";
}
if ($company_filter > 0) {
    $conditions[] = "h.company_id = $company_filter";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

// Add sorting and pagination
$sql .= " ORDER BY h.created_at DESC LIMIT $offset, $per_page";

$result = $conn->query($sql);

// Fetch data into array and get total count
$hardware = [];
$total_count = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($total_count === 0) {
            $total_count = $row['total_count'];
        }
        unset($row['total_count']);
        $hardware[] = $row;
    }
} else {
    $hardware = [];
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
    <title>Hardware Management</title>
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
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
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
        
        th, td {
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
        
        .btn-delete {
            background-color: #f44336;
            color: white;
        }
        
        .btn-edit {
            background-color: #2196F3;
            color: white;
        }
        
        .search-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .search-filter input, .search-filter select {
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-filter button {
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
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
        
        .total-items {
            margin-bottom: 15px;
            font-style: italic;
            color: #666;
        }
        
        a.d-block.py-2.px-3.mb-2.sidebar-item {
            font-size: smaller;
        }
        
        input[type="text"] {
            font-size: 10px;
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
</head>
<body>
    <div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd;">
        <div class="d-flex align-items-center bg-light rounded shadow-sm pt-2 pb-3 px-3 mb-4" style="gap: 14px;">
            <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
                <i class="fas fa-tools"></i>
            </span>
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 1.5rem; font-weight: 600; color: #1976d2; letter-spacing: 1px;">Hardware Report</span>
                <small class="text-muted" style="font-size: 1rem;">Overview of all hardware items in the system</small>
            </div>
        </div>
        <h3>Hardware Management (<?= $total_count ?> items)</h3>
        
        <form method="get" action="">
            <input type="hidden" name="page" value="reports_hardware">
            <div class="row g-2 align-items-center mb-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" style="height:38px;font-size:15px;" placeholder="Search by name or quantity..." value="<?= !empty($search) ? htmlspecialchars($search) : '' ?>">
                </div>
                <div class="col-md-2">
                    <select name="company" class="form-select form-select-sm" style="height:38px;font-size:15px;">
                        <option value="0">All Companies</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= $company['id'] ?>" <?= $company_filter == $company['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                </div>
                <div class="col-md-auto">
                    <a href="index.php?page=reports_hardware" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>

        <table class="report-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Bundle Qty</th>
                    <th>Bundle Price</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($hardware)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No hardware items found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($hardware as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['company_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($item['quantity'] ?? '') ?></td>
                        <td>Rs<?= isset($item['price']) ? number_format((float)$item['price'], 2) : '0.00' ?></td>
                        <td><?= htmlspecialchars($item['bundle_quantity'] ?? '') ?></td>
                        <td>Rs<?= isset($item['bundle_price']) ? number_format((float)$item['bundle_price'], 2) : '0.00' ?></td>
                        <td><?= !empty($item['created_at']) ? date('M d, Y', strtotime($item['created_at'])) : '' ?></td>
                        <td class="actions">
                            <a href="#" class="btn btn-success btn-sm me-1" onclick="openEditHardwareModal({
                                id: '<?= $item['id'] ?>',
                                name: '<?= htmlspecialchars($item['name'], ENT_QUOTES) ?>',
                                company_id: '<?= $item['company_id'] ?>',
                                quantity: '<?= $item['quantity'] ?>',
                                price: '<?= $item['price'] ?>',
                                bundle_quantity: '<?= $item['bundle_quantity'] ?>',
                                bundle_price: '<?= $item['bundle_price'] ?>'
                            }); return false;">
                                <i class="fas fa-edit"></i> Update
                            </a>
                            <a href="index.php?page=reports_hardware&delete=<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this hardware item?')">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination d-flex justify-content-center">
            <div class="btn-group" role="group" aria-label="Pagination">
            <?php if ($page_num > 1): ?>
                <a href="index.php?page=reports_hardware&p=1&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">First</a>
                <a href="index.php?page=reports_hardware&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Prev</a>
            <?php endif; ?>
            <?php 
            $start = max(1, $page_num - 2);
            $end = min($total_pages, $page_num + 2);
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $page_num): ?>
                    <span class="btn btn-primary btn-lg active"><?= $i ?></span>
                <?php else: ?>
                    <a href="index.php?page=reports_hardware&p=<?= $i ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page_num < $total_pages): ?>
                <a href="index.php?page=reports_hardware&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Next</a>
                <a href="index.php?page=reports_hardware&p=<?= $total_pages ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Last</a>
            <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Edit Hardware Modal -->
    <div class="modal fade" id="editHardwareModal" tabindex="-1" aria-labelledby="editHardwareModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <form id="editHardwareForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editHardwareModalLabel">Edit Hardware</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_hardware_id">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" id="edit_hardware_name" class="form-control" style="height:38px;font-size:15px;" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Company</label>
                                <select name="company_id" id="edit_hardware_company_id" class="form-select" style="height:38px;font-size:15px;" required>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?= $comp['id'] ?>" style="font-size:15px;"><?= htmlspecialchars($comp['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" id="edit_hardware_quantity" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" name="price" id="edit_hardware_price" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Bundle Quantity</label>
                                <input type="number" name="bundle_quantity" id="edit_hardware_bundle_quantity" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Bundle Price</label>
                                <input type="number" step="0.01" name="bundle_price" id="edit_hardware_bundle_price" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Hardware</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['delete'])) {
        $delete_id = (int)$_GET['delete'];
        $delete_sql = "DELETE FROM hardware WHERE id = $delete_id";
        if ($conn->query($delete_sql)) {
            echo "<script>alert('Hardware item deleted successfully'); window.location.href='index.php?page=reports_hardware';</script>";
        } else {
            echo "<script>alert('Error deleting hardware item'); window.location.href='index.php?page=reports_hardware';</script>";
        }
    }
    ?>

<script>
function openEditHardwareModal(item) {
    document.getElementById('edit_hardware_id').value = item.id;
    document.getElementById('edit_hardware_name').value = item.name;
    document.getElementById('edit_hardware_company_id').value = item.company_id;
    document.getElementById('edit_hardware_quantity').value = item.quantity;
    document.getElementById('edit_hardware_price').value = item.price;
    document.getElementById('edit_hardware_bundle_quantity').value = item.bundle_quantity;
    document.getElementById('edit_hardware_bundle_price').value = item.bundle_price;
    var modal = new bootstrap.Modal(document.getElementById('editHardwareModal'));
    modal.show();
}

document.getElementById('editHardwareForm').onsubmit = function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('ajax', '1');
    fetch('reports_hardware.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update hardware.');
        }
    });
};
</script>
</body>
</html>