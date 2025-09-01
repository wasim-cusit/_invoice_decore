<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// Handle delete
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM companies WHERE id = $delete_id");
    echo "<script>window.location.href='index.php?page=settings_companies';</script>";
    exit;
}

// Handle update
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_id'])) {
    $id = intval($_POST['update_id']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    $stmt = $conn->prepare("UPDATE companies SET name = ?, description = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $description, $id);
    $stmt->execute();
    $stmt->close();

    echo "<script>window.location.href='index.php?page=settings_companies';</script>";
    exit;
}

// Get companies
$result = $conn->query("SELECT * FROM companies ORDER BY id DESC");
?>

<div class="companies-management">
    <div class="header-section mb-4 d-flex justify-content-between align-items-center">
        <h2><i class="fas fa-building me-2"></i> Manage Companies</h2>
        <button class="btn btn-success rounded px-4 py-2 shadow-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
            <i class="fas fa-plus-circle"></i> Add Company
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Company Name</th>
                            <th>Description</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td class="align-middle">
                                    <strong><?= htmlspecialchars($row['name']) ?></strong>
                                </td>
                                <td class="align-middle">
                                    <div class="company-description">
                                        <?= nl2br(htmlspecialchars($row['description'])) ?>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary" onclick="showEditForm(<?= $row['id'] ?>)">
                                            <i class="fas fa-edit me-1"></i> Edit
                                        </button>
                                        <a href="index.php?page=settings_companies&delete_id=<?= $row['id'] ?>"
                                            onclick="return confirm('Are you sure you want to delete this company?')"
                                            class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash-alt me-1"></i> Delete
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <!-- Edit Form (Hidden by default) -->
                            <tr id="edit-form-<?= $row['id'] ?>" style="display: none;">
                                <td colspan="3" class="edit-form-container p-4 bg-light">
                                    <form method="POST" action="index.php?page=settings_companies">
                                        <input type="hidden" name="update_id" value="<?= $row['id'] ?>">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Company Name</label>
                                                <input type="text" name="name" class="form-control"
                                                    value="<?= htmlspecialchars($row['name']) ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($row['description']) ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-secondary" onclick="hideEditForm(<?= $row['id'] ?>)">
                                                        <i class="fas fa-times me-1"></i> Cancel
                                                    </button>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i> Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($result->num_rows === 0): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h4>No Companies Found</h4>
                    <p class="text-muted">You haven't added any companies yet.</p>
                    <a href="index.php?page=add_company" class="btn btn-primary">
                        <i class="fas fa-plus-circle me-2"></i> Add New Company
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Company Modal -->
<div class="modal fade" id="addCompanyModal" tabindex="-1" aria-labelledby="addCompanyModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCompanyModalLabel"><i class="fas fa-building me-2"></i> Add New Company</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addCompanyForm">
          <div class="mb-3">
            <label for="companyName" class="form-label required-field">Company Name</label>
            <input type="text" name="name" id="companyName" class="form-control" required maxlength="255" placeholder="Enter company name" />
          </div>
          <div class="mb-3">
            <label for="companyDescription" class="form-label">Company Description</label>
            <textarea name="description" id="companyDescription" class="form-control" rows="4" placeholder="Enter company description"></textarea>
          </div>
        </form>
        <div id="addCompanyMsg" class="mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="addCompanyForm" class="btn btn-primary">Add Company</button>
      </div>
    </div>
  </div>
</div>

<style>
    .companies-management {
        padding: 20px;
    }

    .header-section {
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
    }

    .company-description {
        max-height: 60px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .edit-form-container {
        border-left: 4px solid var(--accent-color);
        border-radius: 0 0 4px 4px;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(230, 126, 34, 0.05);
    }

    @media (max-width: 768px) {
        .table-responsive {
            border: 0;
        }

        .table thead {
            display: none;
        }

        .table tr {
            display: block;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .table td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .table td:before {
            content: attr(data-label);
            font-weight: bold;
            margin-right: 15px;
            color: var(--secondary-color);
        }

        .table td:last-child {
            border-bottom: 0;
        }

        .edit-form-container {
            padding: 15px !important;
        }
    }
</style>

<script>
    function showEditForm(id) {
        document.getElementById('edit-form-' + id).style.display = 'table-row';
        document.getElementById('edit-form-' + id).scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    }

    function hideEditForm(id) {
        document.getElementById('edit-form-' + id).style.display = 'none';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const table = document.querySelector('.table');
        if (window.innerWidth <= 768) {
            const headers = ['Company Name', 'Description', 'Actions'];
            const cells = document.querySelectorAll('.table td');

            cells.forEach((cell, index) => {
                const headerIndex = index % headers.length;
                cell.setAttribute('data-label', headers[headerIndex]);
            });
        }
    });
</script>