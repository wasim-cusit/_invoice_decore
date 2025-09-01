<?php
require_once 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $bundle_quantity = $_POST['bundle_quantity'];
    $bundle_price = $_POST['bundle_price'];
    $company_id = $_POST['company_id'];

    if (isset($_POST['edit_id'])) {
        // Update existing hardware
        $edit_id = $_POST['edit_id'];
        $stmt = $conn->prepare("UPDATE hardware SET name = ?, quantity = ?, price = ?, bundle_quantity = ?, bundle_price = ?, company_id = ? WHERE id = ?");
        $stmt->bind_param("sddddii", $name, $quantity, $price, $bundle_quantity, $bundle_price, $company_id, $edit_id);
        $stmt->execute();
        $success = "Hardware updated successfully!";
    } else {
        // Insert new hardware
        $stmt = $conn->prepare("INSERT INTO hardware (name, quantity, price, bundle_quantity, bundle_price, company_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sddddi", $name, $quantity, $price, $bundle_quantity, $bundle_price, $company_id);
        $stmt->execute();
        $success = "Hardware added successfully!";
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM hardware WHERE id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $success = "Hardware deleted successfully!";
}

// Fetch companies for dropdown
$companies = $conn->query("SELECT id, name FROM companies ORDER BY name");

// Get selected company from session or default to first
$selected_company_id = isset($_SESSION['selected_company_id']) ? $_SESSION['selected_company_id'] : 1;
if (isset($_GET['company_id'])) {
    $selected_company_id = $_GET['company_id'];
    $_SESSION['selected_company_id'] = $selected_company_id;
}

// Fetch hardware for selected company
$hardware = $conn->query("SELECT * FROM hardware WHERE company_id = $selected_company_id ORDER BY name");

// Handle edit
$edit_hardware = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM hardware WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $edit_id, $selected_company_id);
    $stmt->execute();
    $edit_hardware = $stmt->get_result()->fetch_assoc();
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
        margin-top: 12px;
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
        margin: 20px 0 10px 0;
        padding: 15px; 
        /* padding-bottom:  7px; */
        background: #f2f2f2;
        border-radius: 4px;
        max-width: 100%;
        overflow-x: auto;
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
        box-sizing: border-box;
    }

    .form-group input[name="name"] {
        width: 260px;
    }

    .form-group input[name="quantity"],
    .form-group input[name="price"],
    .form-group input[name="bundle_quantity"],
    .form-group input[name="bundle_price"] {
        width: 120px;
    }

    .professional-form {
        display: flex;
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

    @media (max-width: 900px) {
        .professional-form {
            flex-direction: column;
            gap: 15px;
        }

        .form-group-grouped {
            flex-direction: column;
            gap: 10px;
        }

        .form-group input[name="name"] {
            width: 100%;
        }

        .form-group input[name="quantity"],
        .form-group input[name="price"],
        .form-group input[name="bundle_quantity"],
        .form-group input[name="bundle_price"] {
            width: 100%;
        }

        .professional-form .action-buttons {
            margin-left: 0;
        }
    }

    .company-selector {
        margin-bottom: 15px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }
    .search-bar-hardware {
        background: #f8f9fa;
        border-radius: 1rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        padding: 0;
        margin: 0;
        max-width: 400px;
        display: flex;
        align-items: center;
    }
    .search-bar-hardware .form-control {
        border-radius: 2rem 0 0 2rem;
        border-right: 0;
        background: #fff;
    }
    .search-bar-hardware .btn {
        border-radius: 0 2rem 2rem 0;
    }
</style>

<div class="container">
    <div class="d-flex align-items-center mb-4 gap-3">
        <h2 class="mb-0"><i class="fas fa-tools me-2"></i>Hardware Settings</h2>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Company Selector and Add Button Row -->
    <div class="d-flex align-items-center mb-4 gap-3">
        <div class="company-selector mb-0 d-flex align-items-center gap-2">
            <label for="company-select" class="mb-0">Select Company:</label>
            <select id="company-select" class="form-select" style="width:auto; min-width:180px;" onchange="window.location.href='?page=settings_hardware&company_id=' + this.value">
                <?php while ($company = $companies->fetch_assoc()): ?>
                    <option value="<?= $company['id'] ?>" <?= $company['id'] == $selected_company_id ? 'selected' : '' ?>>
                        <?= htmlspecialchars($company['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <!-- Add/Edit Form -->
    <div class="form-container">
        <h3><?= $edit_hardware ? 'Edit Hardware' : 'Add New Hardware' ?></h3>
        <form method="POST" class="row g-3 align-items-end flex-wrap">
            <?php if ($edit_hardware): ?>
                <input type="hidden" name="edit_id" value="<?= $edit_hardware['id'] ?>">
            <?php endif; ?>
            
            <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
            
            <div class="col-md-2">
                <label for="name" class="form-label">Hardware Name:</label>
                <input type="text" id="name" name="name" class="form-control" value="<?= $edit_hardware ? htmlspecialchars($edit_hardware['name']) : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="quantity" class="form-label">Quantity:</label>
                <input type="number" id="quantity" name="quantity" class="form-control" value="<?= $edit_hardware ? $edit_hardware['quantity'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="price" class="form-label">Price:</label>
                <input type="number" id="price" name="price" class="form-control" step="0.01" value="<?= $edit_hardware ? $edit_hardware['price'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="bundle_quantity" class="form-label">Bundle Quantity:</label>
                <input type="number" id="bundle_quantity" name="bundle_quantity" class="form-control" value="<?= $edit_hardware ? $edit_hardware['bundle_quantity'] : '' ?>" required>
            </div>
            <div class="col-md-2">
                <label for="bundle_price" class="form-label">Bundle Price:</label>
                <input type="number" id="bundle_price" name="bundle_price" class="form-control" step="0.01" value="<?= $edit_hardware ? $edit_hardware['bundle_price'] : '' ?>" required>
            </div>
            
            <div class="col-md-auto d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-success d-flex align-items-center" style="gap:6px;">
                    <i class="fas fa-save"></i> <?= $edit_hardware ? 'Update Hardware' : 'Add Hardware' ?>
                </button>
                <?php if ($edit_hardware): ?>
                    <a href="?page=settings_hardware&company_id=<?= $selected_company_id ?>" class="btn btn-secondary d-flex align-items-center" style="gap:6px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search Box -->
    <div class="search-bar-hardware ">
        <form method="get" class="d-flex " style="gap: 0;" id="hardwareSearchForm" autocomplete="off">
            <input type="hidden" name="page" value="settings_hardware">
            <input type="text" class="form-control" name="search" id="hardwareSearchInput" placeholder="Search hardware..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <button class="btn btn-primary px-4" type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('hardwareSearchInput');
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                const params = new URLSearchParams(window.location.search);
                params.set('page', 'settings_hardware');
                params.set('search', searchInput.value);
                window.location.search = params.toString();
            }, 350);
        });
    });
    </script>

    <!-- Hardware Table -->
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Quantity</th>
                <th>Price</th>
                <th>Bundle Quantity</th>
                <th>Bundle Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
            while ($hw = $hardware->fetch_assoc()):
                if ($search && strpos(strtolower($hw['name']), $search) === false) continue;
            ?>
                <tr>
                    <td><?= htmlspecialchars($hw['name']) ?></td>
                    <td><?= $hw['quantity'] ?></td>
                    <td>Rs <?= number_format($hw['price'] ?? 0, 2) ?></td>
                    <td><?= $hw['bundle_quantity'] ?></td>
                    <td>Rs <?= number_format($hw['bundle_price'] ?? 0, 2) ?></td>
                    <td>
                        <button type="button" class="btn btn-warning btn-sm me-1 d-inline-flex align-items-center" style="gap:4px;" data-bs-toggle="modal" data-bs-target="#editHardwareModal" data-id="<?= $hw['id'] ?>" data-name="<?= htmlspecialchars($hw['name']) ?>" data-quantity="<?= $hw['quantity'] ?>" data-price="<?= $hw['price'] ?>" data-bundle_quantity="<?= $hw['bundle_quantity'] ?>" data-bundle_price="<?= $hw['bundle_price'] ?>">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <a href="?page=settings_hardware&company_id=<?= $selected_company_id ?>&delete=<?= $hw['id'] ?>" class="btn btn-danger btn-sm d-inline-flex align-items-center" style="gap:4px;" onclick="return confirm('Are you sure you want to delete this hardware?')"><i class="fas fa-trash"></i> Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Edit Hardware Modal -->
    <div class="modal fade" id="editHardwareModal" tabindex="-1" aria-labelledby="editHardwareModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST">
            <div class="modal-header">
              <h5 class="modal-title" id="editHardwareModalLabel">Edit Hardware</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body row g-3">
              <input type="hidden" name="edit_id" id="edit_id_modal">
              <input type="hidden" name="company_id" value="<?= $selected_company_id ?>">
              <div class="col-12">
                <label for="edit_name_modal" class="form-label">Hardware Name:</label>
                <input type="text" id="edit_name_modal" name="name" class="form-control" required>
              </div>
              <div class="col-6">
                <label for="edit_quantity_modal" class="form-label">Quantity:</label>
                <input type="number" id="edit_quantity_modal" name="quantity" class="form-control" required>
              </div>
              <div class="col-6">
                <label for="edit_price_modal" class="form-label">Price:</label>
                <input type="number" id="edit_price_modal" name="price" class="form-control" step="0.01" required>
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
              <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Update Hardware</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var editModal = document.getElementById('editHardwareModal');
      if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
          var button = event.relatedTarget;
          document.getElementById('edit_id_modal').value = button.getAttribute('data-id');
          document.getElementById('edit_name_modal').value = button.getAttribute('data-name');
          document.getElementById('edit_quantity_modal').value = button.getAttribute('data-quantity');
          document.getElementById('edit_price_modal').value = button.getAttribute('data-price');
          document.getElementById('edit_bundle_quantity_modal').value = button.getAttribute('data-bundle_quantity');
          document.getElementById('edit_bundle_price_modal').value = button.getAttribute('data-bundle_price');
        });
      }
    });
    </script>
</div>