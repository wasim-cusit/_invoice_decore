<?php
if (!defined('ALLOW_INCLUDE')) {
    die("Access denied");
}
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
        m.*, 
        co.name AS company_name,
        COUNT(*) OVER() AS total_count
    FROM materials m
    LEFT JOIN companies co ON m.company_id = co.id
";

// Add conditions based on filters
$conditions = [];
if (!empty($search)) {
    $conditions[] = "(m.name LIKE '%$search%' OR m.length LIKE '%$search%')";
}
if ($company_filter > 0) {
    $conditions[] = "m.company_id = $company_filter";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(' AND ', $conditions);
}

// Add sorting and pagination
$sql .= " ORDER BY m.created_at DESC LIMIT $offset, $per_page";

$result = $conn->query($sql);

// Fetch data into array and get total count
$materials = [];
$total_count = 0;
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($total_count === 0) {
            $total_count = $row['total_count'];
        }
        unset($row['total_count']);
        $materials[] = $row;
    }
} else {
    $materials = [];
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
    <title>Materials Management</title>
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

        .search-filter input,
        .search-filter select {
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
                <i class="fas fa-boxes"></i>
            </span>
            <div style="display: flex; flex-direction: column;">
                <span style="font-size: 1.5rem; font-weight: 600; color: #1976d2; letter-spacing: 1px;">Materials Report</span>
                <small class="text-muted" style="font-size: 1rem;">Overview of all materials in the system</small>
            </div>
        </div>
        <h3>Materials Management (<?= $total_count ?> materials)</h3>
        <form method="get" action="">
            <input type="hidden" name="page" value="reports_materials">
            <div class="row g-2 align-items-center mb-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" style="height:38px;font-size:15px;" placeholder="Search by name or length..." value="<?= !empty($search) ? htmlspecialchars($search) : '' ?>">
                </div>
                <div class="col-md-2">
                    <select name="company" class="form-select form-select-sm" style="height:38px;">
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
                    <a href="index.php?page=reports_materials" class="btn btn-secondary btn-sm">Reset</a>
                </div>
            </div>
        </form>

        <table class="report-table">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Company</th>
                    <th>Length</th>
                    <th>Price/Foot</th>
                    <th>Bundle Qty</th>
                    <th>Bundle Price</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($materials)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center;">No materials found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($materials as $material): ?>
                        <tr>
                            <td><?= htmlspecialchars($material['id'] ?? '') ?></td>
                            <td><?= htmlspecialchars($material['name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($material['company_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($material['length'] ?? '') ?></td>
                            <td>Rs<?= isset($material['price_per_foot']) ? number_format((float)$material['price_per_foot'], 2) : '0.00' ?></td>
                            <td><?= htmlspecialchars($material['bundle_quantity'] ?? '') ?></td>
                            <td>Rs<?= isset($material['bundle_price']) ? number_format((float)$material['bundle_price'], 2) : '0.00' ?></td>
                            <td><?= !empty($material['created_at']) ? date('M d, Y', strtotime($material['created_at'])) : '' ?></td>
                            <td class="actions">
                                <a href="#" class="btn btn-success btn-sm me-1" onclick="openEditMaterialModal({
                                    id: '<?= $material['id'] ?>',
                                    name: '<?= htmlspecialchars($material['name'], ENT_QUOTES) ?>',
                                    company_id: '<?= $material['company_id'] ?>',
                                    length: '<?= htmlspecialchars($material['length'], ENT_QUOTES) ?>',
                                    price_per_foot: '<?= $material['price_per_foot'] ?>',
                                    bundle_quantity: '<?= $material['bundle_quantity'] ?>',
                                    bundle_price: '<?= $material['bundle_price'] ?>'
                                }); return false;">
                                    <i class="fas fa-edit"></i> Update
                                </a>
                                <a href="index.php?page=reports_materials&delete=<?= $material['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this material?')">Delete</a>
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
                    <a href="index.php?page=reports_materials&p=1&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">First</a>
                    <a href="index.php?page=reports_materials&p=<?= $page_num - 1 ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Prev</a>
                <?php endif; ?>

                <?php
                $start = max(1, $page_num - 2);
                $end = min($total_pages, $page_num + 2);

                for ($i = $start; $i <= $end; $i++): ?>
                    <?php if ($i == $page_num): ?>
                        <span class="btn btn-primary btn-lg active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="index.php?page=reports_materials&p=<?= $i ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page_num < $total_pages): ?>
                    <a href="index.php?page=reports_materials&p=<?= $page_num + 1 ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Next</a>
                    <a href="index.php?page=reports_materials&p=<?= $total_pages ?>&search=<?= urlencode($search) ?>&company=<?= $company_filter ?>" class="btn btn-outline-primary btn-lg">Last</a>
                <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Material Modal -->
    <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <form id="editMaterialForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editMaterialModalLabel">Edit Material</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="edit_id" id="edit_material_id">
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" id="edit_material_name" class="form-control" style="height:38px;font-size:15px;" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Company</label>
                                <select name="company_id" id="edit_material_company_id" class="form-select" style="height:38px;font-size:15px;" required>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?= $comp['id'] ?>"><?= htmlspecialchars($comp['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Length</label>
                                <input type="text" name="length" id="edit_material_length" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Price per Foot</label>
                                <input type="number" step="0.01" name="price_per_foot" id="edit_material_price_per_foot" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Bundle Quantity</label>
                                <input type="number" name="bundle_quantity" id="edit_material_bundle_quantity" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Bundle Price</label>
                                <input type="number" step="0.01" name="bundle_price" id="edit_material_bundle_price" class="form-control" style="height:38px;font-size:15px;">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php
    if (isset($_GET['delete'])) {
        $delete_id = (int)$_GET['delete'];
        $delete_sql = "DELETE FROM materials WHERE id = $delete_id";
        if ($conn->query($delete_sql)) {
            echo "<script>alert('Material deleted successfully'); window.location.href='index.php?page=reports_materials';</script>";
        } else {
            echo "<script>alert('Error deleting material'); window.location.href='index.php?page=reports_materials';</script>";
        }
    }
    ?>

    <script>
    function openEditMaterialModal(material) {
        document.getElementById('edit_material_id').value = material.id;
        document.getElementById('edit_material_name').value = material.name;
        document.getElementById('edit_material_company_id').value = material.company_id;
        document.getElementById('edit_material_length').value = material.length;
        document.getElementById('edit_material_price_per_foot').value = material.price_per_foot;
        document.getElementById('edit_material_bundle_quantity').value = material.bundle_quantity;
        document.getElementById('edit_material_bundle_price').value = material.bundle_price;
        var modal = new bootstrap.Modal(document.getElementById('editMaterialModal'));
        modal.show();
    }

    document.getElementById('editMaterialForm').onsubmit = function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append('ajax', '1');
        fetch('settings_materials.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to update material.');
            }
        });
    };
    </script>
</body>

</html>