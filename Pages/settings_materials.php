<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $length = $_POST['length'];
    $price_per_foot = $_POST['price_per_foot'];
    $bundle_quantity = $_POST['bundle_quantity'];
    $bundle_price = $_POST['bundle_price'];
    $company_id = $_POST['company_id'];

    if (isset($_POST['edit_id'])) {
        // Update existing material
        $edit_id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE materials SET name = ?, length = ?, price_per_foot = ?, bundle_quantity = ?, bundle_price = ?, company_id = ? WHERE id = ?");
        $stmt->bind_param("sddddii", $name, $length, $price_per_foot, $bundle_quantity, $bundle_price, $company_id, $edit_id);
        $stmt->execute();
        $success = "Material updated successfully!";
    } else {
        // Insert new material
        $stmt = $conn->prepare("INSERT INTO materials (name, length, price_per_foot, bundle_quantity, bundle_price, company_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddddi", $name, $length, $price_per_foot, $bundle_quantity, $bundle_price, $company_id);
        $stmt->execute();
        $success = "Material added successfully!";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM materials WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $success = "Material deleted successfully!";
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name");

// Get selected company from session or default to first
$selected_company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : 1;
if (isset($_GET['company_id'])) {
    $selected_company_id = $_GET['company_id'];
    $_SESSION['selected_company_id'] = $selected_company_id;
}

// Fetch materials for selected company
$materials = $conn->query("SELECT * FROM materials WHERE company_id = $selected_company_id ORDER BY name");

// Handle edit
$edit_material = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM materials WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $edit_id, $selected_company_id);
    $stmt->execute();
    $edit_material = $stmt->get_result()->fetch_assoc();
}
?>

<style>
    h2 {
        color: #444;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-top: 0;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        /* margin-top: 20px; */
    }

    th,
    td {
        padding: 10px;
        border: 1px solid #ddd;
        text-align: left;
    }

    th {
        background-color: #333;
        color: white;
    }

    tr:hover {
        background-color: #f5f5f5;
    }

    .btn {
        display: inline-block;
        padding: 6px 12px;
        margin: 0 2px;
        text-decoration: none;
        border-radius: 4px;
        font-size: 14px;
    }



    .form-container {
        margin: 20px 0 0 0;
        padding: 15px;
        /* padding-bottom:  7px; */
        background: #f2f2f2;
        border-radius: 4px;
        max-width: 100%;
        overflow-x: auto;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 10px;
        align-items: center;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
        min-width: 120px;
    }

    label {
        font-weight: 600;
        white-space: nowrap;
        margin-bottom: 2px;
    }

    input[type="text"],
    input[type="number"],
    select {
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        width: 180px;
        box-sizing: border-box;
    }

    .company-selector {
        margin-bottom: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    .single-line-form {
        flex-wrap: nowrap;
        gap: 20px;
    }

    .single-line-form .form-group {
        margin-bottom: 0;
    }

    .single-line-form .action-buttons {
        margin-left: 10px;
    }

    .professional-form {
        flex-wrap: wrap;
        gap: 30px;
        align-items: flex-end;
        max-width: 100%;
    }

    .form-group-grouped {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
    }

    .form-group-grouped .form-group {
        margin-bottom: 0;
    }

    .professional-form .action-buttons {
        margin-left: 10px;
        align-items: flex-end;
    }

    .form-group input[name="name"] {
        width: 260px;
    }

    .form-group input[name="length"],
    .form-group input[name="price_per_foot"],
    .form-group input[name="bundle_quantity"],
    .form-group input[name="bundle_price"] {
        width: 120px;
    }

    @media (max-width: 900px) {
        .professional-form {
            flex-direction: column;
            gap: 15px;
        }

        .form-group-grouped {
            flex-direction: column;
            gap: 10px;
        }
    }
    .search-bar-materials {
        background: #f8f9fa;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 0;
        margin: 0;
        max-width: 400px;
        display: flex;
        align-items: center;
    }
    .search-bar-materials .form-control {
        border-radius: 2rem 0 0 2rem;
        border-right: 0;
        background: #fff;
    }
    .search-bar-materials .btn {
        border-radius: 0 2rem 2rem 0;
    }
</style>

<div class="container">
<div class="d-flex align-items-center mb-4 gap-3">
        <h2 class="mb-0"><i class="fas fa-boxes me-2"></i>Materials Settings</h2>
    </div>
    <div class="d-flex align-items-center justify-content-between mb-4 gap-3">
        <div class="company-selector mb-0 d-flex align-items-center gap-2">
            <label for="company-select" class="mb-0">Select Company:</label>
            <select id="company-select" class="form-select" style="width:auto; min-width:180px;" onchange="window.location.href='?page=settings_materials&company_id=' + this.value">
                <?php while ($company = $companies->fetch_assoc()): ?>
                    <option value="<?= $company['id'] ?>" <?= $company['id'] == $selected_company_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($company['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
      
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h3><?= $edit_material ? 'Edit Material' : 'Add New Material' ?></h3>
        <form method="POST" class="row g-3 align-items-end flex-wrap">
            <?php if ($edit_material): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_material['id'] ?>">
            <?php endif; ?>
            
            <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
            
            <div class="col-md-2">
                <label for="name" class="form-label">Material Name:</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= $edit_material ? htmlspecialchars($edit_material['name']) : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="length" class="form-label">Length (ft):</label>
                <input type="number" id="length" name="length" class="form-control" step="0.01" value="<?= $edit_material ? $edit_material['length'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="price_per_foot" class="form-label">Price per Foot:</label>
                <input type="number" id="price_per_foot" name="price_per_foot" class="form-control" step="0.01" value="<?= $edit_material ? $edit_material['price_per_foot'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="bundle_quantity" class="form-label">Bundle Quantity:</label>
                <input type="number" id="bundle_quantity" name="bundle_quantity" class="form-control" value="<?= $edit_material ? $edit_material['bundle_quantity'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="bundle_price" class="form-label">Bundle Price:</label>
                <input type="number" id="bundle_price" name="bundle_price" class="form-control" step="0.01" value="<?= $edit_material ? $edit_material['bundle_price'] : '' ?>" required>
            </div>
            
            <div class="col-md-auto d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-success d-flex align-items-center" style="gap:6px;">
                    <i class="fas fa-save"></i> <?= $edit_material ? 'Update Material' : 'Add Material' ?>
                </button>
                <?php if ($edit_material): ?>
                    <a href="?page=settings_materials&company_id=<?= $selected_company_id ?>" class="btn btn-secondary d-flex align-items-center" style="gap:6px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search Box -->
    <style>
    .search-bar-materials {
        background: #f8f9fa;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 0.75rem 0.25rem;
        /* margin-bottom: 1.5rem; */
        max-width: 400px;
        display: flex;
        align-items: center;
    }
    .search-bar-materials .form-control {
        border-radius: 2rem 0 0 2rem;
        border-right: 0;
        background: #fff;
    }
    .search-bar-materials .btn {
        border-radius: 0 2rem 2rem 0;
    }
    </style>
    <div class="search-bar-materials">
        <form method="get" class="d-flex w-100" style="gap: 0;" id="materialsSearchForm" autocomplete="off">
            <input type="hidden" name="page" value="settings_materials">
            <input type="text" class="form-control" name="search" id="materialsSearchInput" placeholder="Search materials..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <button class="btn btn-primary px-4" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('materialsSearchInput');
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                const params = new URLSearchParams(window.location.search);
                params.set('page', 'settings_materials');
                params.set('search', searchInput.value);
                window.location.search = params.toString();
            }, 350);
        });
    });
    </script>
    <!-- Materials Table -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Length (ft)</th>
                <th>Price per Foot</th>
                <th>Bundle Quantity</th>
                <th>Bundle Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Filter materials if search is set
            $search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
            while ($material = $materials->fetch_assoc()):
                if ($search && strpos(strtolower($material['name']), $search) === false) continue;
            ?>
                <tr>
                    <td><?= htmlspecialchars($material['name']) ?></td>
                    <td><?= $material['length'] ?></td>
                    <td>Rs <?= number_format($material['price_per_foot'] ?? 0, 2) ?></td>
                    <td><?= $material['bundle_quantity'] ?></td>
                    <td>Rs <?= number_format($material['bundle_price'] ?? 0, 2) ?></td>
                    <td>
                        <button type="button" class="btn btn-warning btn-sm me-1 d-inline-flex align-items-center" style="gap:4px;" data-bs-toggle="modal" data-bs-target="#editMaterialModal" data-id="<?= $material['id'] ?>" data-name="<?= htmlspecialchars($material['name']) ?>" data-length="<?= $material['length'] ?>" data-price_per_foot="<?= $material['price_per_foot'] ?>" data-bundle_quantity="<?= $material['bundle_quantity'] ?>" data-bundle_price="<?= $material['bundle_price'] ?>">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="?page=settings_materials&company_id=<?= $selected_company_id ?>&delete=<?= $material['id'] ?>" class="btn btn-danger btn-sm d-inline-flex align-items-center" style="gap:4px;" onclick="return confirm('Are you sure you want to delete this material?')"><i class="fas fa-trash"></i> Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Edit Material Modal -->
    <div class="modal fade" id="editMaterialModal" tabindex="-1" aria-labelledby="editMaterialModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="editMaterialModalLabel">Edit Material</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
              <input type="hidden" name="edit_id" id="edit_id_modal">
              <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
              <div class="col-12">
                <label for="edit_name_modal" class="form-label">Material Name:</label>
                <input type="text" id="edit_name_modal" name="name" class="form-control" required>
              </div>
              <div class="col-6">
                <label for="edit_length_modal" class="form-label">Length (ft):</label>
                <input type="number" id="edit_length_modal" name="length" class="form-control" step="0.01" required>
              </div>
              <div class="col-6">
                <label for="edit_price_per_foot_modal" class="form-label">Price per Foot:</label>
                <input type="number" id="edit_price_per_foot_modal" name="price_per_foot" class="form-control" step="0.01" required>
              </div>
              <div class="col-6">
                <label for="edit_bundle_quantity_modal" class="form-label">Bundle Quantity:</label>
                <input type="number" id="edit_bundle_quantity_modal" name="bundle_quantity" class="form-control" required>
              </div>
              <div class="col-6">
                <label for="edit_bundle_price_modal" class="form-label">Bundle Price:</label>
                <input type="number" id="edit_bundle_price_modal" name="bundle_price" class="form-control" step="0.01" required>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Material</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var editModal = document.getElementById('editMaterialModal');
      if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
          var button = event.relatedTarget;
          document.getElementById('edit_id_modal').value = button.getAttribute('data-id');
          document.getElementById('edit_name_modal').value = button.getAttribute('data-name');
          document.getElementById('edit_length_modal').value = button.getAttribute('data-length');
          document.getElementById('edit_price_per_foot_modal').value = button.getAttribute('data-price_per_foot');
          document.getElementById('edit_bundle_quantity_modal').value = button.getAttribute('data-bundle_quantity');
          document.getElementById('edit_bundle_price_modal').value = button.getAttribute('data-bundle_price');
        });
      }
    });
    </script>
</div>