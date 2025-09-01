<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';
$page = $_GET['page'] ?? 'welcome';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Furniture Admin</title>
    <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2980b9;
            --accent: #2c3e50;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f8fa;
        }
        
        .app-header {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 20px;
        }
        
        .sidebar {
            background: white;
            min-height: 100vh;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            position: fixed;
            width: 16.666667%;
        }
        
        .sidebar-item {
            color: var(--dark);
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar-item:hover, .sidebar-item.active {
            background: #e9f5ff;
            border-left: 3px solid var(--primary);
            color: var(--primary);
        }
        
        .main-content {
            background: white;
            min-height: 100vh;
            margin-left: 16.666667%;
            width: 83.333333%;
        }
        
        .dropdown-menu .dropdown-item {
            padding: 0.5rem 1.5rem;
        }
        
        .dropdown-menu .dropdown-item:hover {
            background-color: #e9f5ff;
        }
        
        .calculator-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.05);
            padding: 20px;
            margin: 20px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="app-header d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <img src="./logo/mod.jpg" alt="Logo" height="40" class="me-3">
        </div>
        <div class="text-center">
            <span class="fs-4 fw-bold text-accent">Modern <span class="text-primary">Furniture</span></span>
        </div>
        <div class="dropdown">
            <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-user-circle fa-2x me-2"></i>
                <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                <li><a class="dropdown-item" href="change_password.php"><i class="fa fa-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container-fluid">
        <div class="row">
            <!-- Fixed Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="p-3 sticky-top">
                    <div class="mb-4">
                        <h5 class="text-primary mb-3"><i class="fas fa-cog me-2"></i>Admin Panel</h5>
                        
                        <a href="?page=welcome" class="d-block py-2 px-3 mb-2 sidebar-item <?= $page === 'welcome' ? 'active' : '' ?>">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        
                        <a href="?page=new_calculation" class="d-block py-2 px-3 mb-2 sidebar-item <?= $page === 'new_calculation' ? 'active' : '' ?>">
                            <i class="fas fa-calculator me-2"></i> New Calculation
                        </a>
                        
                        <div class="dropdown">
                            <a class="d-block py-2 px-3 mb-2 sidebar-item dropdown-toggle <?= strpos($page, 'reports_') === 0 ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-file-invoice me-2"></i> Reports
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?= $page === 'reports_invoices' ? 'active' : '' ?>" href="?page=reports_invoices">Invoices</a></li>
                                <li><a class="dropdown-item <?= $page === 'reports_expenses' ? 'active' : '' ?>" href="?page=reports_expenses">Expenses</a></li>
                                <li><a class="dropdown-item <?= $page === 'reports_clients' ? 'active' : '' ?>" href="?page=reports_clients">Clients</a></li>
                                <li><a class="dropdown-item <?= $page === 'reports_materials' ? 'active' : '' ?>" href="?page=reports_materials">Materials</a></li>
                                <li><a class="dropdown-item <?= $page === 'reports_hardware' ? 'active' : '' ?>" href="?page=reports_hardware">Hardware</a></li>
                            </ul>
                        </div>
                        
                        <div class="dropdown">
                            <a class="d-block py-2 px-3 sidebar-item dropdown-toggle <?= strpos($page, 'settings_') === 0 ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-cogs me-2"></i> Settings
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item <?= $page === 'settings_materials' ? 'active' : '' ?>" href="?page=settings_materials">Materials</a></li>
                                <li><a class="dropdown-item <?= $page === 'settings_hardware' ? 'active' : '' ?>" href="?page=settings_hardware">Hardware</a></li>
                                <li><a class="dropdown-item <?= $page === 'settings_companies' ? 'active' : '' ?>" href="?page=settings_companies">Companies</a></li>
                                <li><a class="dropdown-item <?= $page === 'settings_add_company' ? 'active' : '' ?>" href="?page=settings_add_company">Add Company</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-md-10 p-0 main-content">
                <?php if ($page === 'welcome'): ?>
                    <!-- Dashboard Content -->
                    <div class="p-4">
                        <div class="mb-4">
                            <h3 class="mb-0">
                                <i class="fas fa-tachometer-alt text-primary me-2"></i>Dashboard
                            </h3>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card border-primary h-100">
                                    <div class="card-body text-center">
                                        <i class="fas fa-calculator fa-3x text-primary mb-3"></i>
                                        <h4>New Calculation</h4>
                                        <p>Start a new window or door calculation</p>
                                        <a href="?page=new_calculation" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i> Create
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Other dashboard cards -->
                        </div>
                    </div>
                
                <?php elseif ($page === 'new_calculation'): ?>
                    <!-- New Calculation Content -->
                    <div class="calculator-container">
                        <h4><i class="fas fa-calculator me-2"></i>New Calculation</h4>
                        
                        
                        <!-- Horizontal Selection Bar -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="form-label">Company</label>
                                <select class="form-select" id="companySelect">
                                    <option value="">-- Select Company --</option>
                                    <?php 
                                    $companies = $conn->query("SELECT id, name FROM companies ORDER BY name");
                                    while ($company = $companies->fetch_assoc()): ?>
                                        <option value="<?= $company['id'] ?>"><?= htmlspecialchars($company['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Product Type</label>
                                <select class="form-select" id="productTypeSelect" disabled>
                                    <option value="">-- Select --</option>
                                    <option value="window">Window</option>
                                    <option value="door">Door</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Type</label>
                                <select class="form-select" id="productSubTypeSelect" disabled>
                                    <option value="">-- Select --</option>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Client</label>
                                <select class="form-select" id="clientSelect" disabled>
                                    <option value="">-- Select Client --</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                    <i class="fas fa-plus me-1"></i> New Client
                                </button>
                            </div>
                        </div>
                        
                        <!-- Calculator will load here -->
                        <div id="calculatorDisplayArea">
                            <div class="alert alert-info">
                                Please select company, product type, and client to begin calculation.
                            </div>
                        </div>
                    </div>
                
                <?php elseif (strpos($page, 'reports_') === 0): ?>
                    <!-- Reports Content -->
                    <div class="p-4">
                        <h4><i class="fas fa-file-invoice me-2"></i>Reports</h4>
                        <!-- Report content based on selection -->
                    </div>
                
                <?php elseif (strpos($page, 'settings_') === 0): ?>
                    <!-- Settings Content -->
                    <div class="p-4">
                        <h4><i class="fas fa-cogs me-2"></i>Settings</h4>
                        <!-- Settings content based on selection -->
                    </div>
                
                <?php elseif ($page === 'logout'): ?>
                    <?php
                    session_destroy();
                    header("Location: login.php");
                    exit();
                    ?>
                    
                <?php else: ?>
                    <div class="p-4">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Select an option from the sidebar to get started.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addClientForm">
                        <input type="hidden" id="modalCompanyId" name="company_id">
                        <div class="mb-3">
                            <label class="form-label">Client Name*</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" form="addClientForm" class="btn btn-primary">Save Client</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Product subtypes data
        const productSubTypes = {
            window: ['2psl', '3psl', 'fixed', 'top_hung'],
            door: ['full_panel', 'half', 'openable', 'glass']
        };
        
        // Format display names
        function formatName(str) {
            return str.split('_').map(word => 
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');
        }
        
        // When company is selected
        $('#companySelect').change(function() {
            const companyId = $(this).val();
            
            if (companyId) {
                // Enable product type selection
                $('#productTypeSelect').prop('disabled', false);
                
                // Load clients for this company
                // Modify your existing AJAX call to include error handling
$.get('ajax_get_clients.php?company_id=' + companyId, function(clients) {
    const $clientSelect = $('#clientSelect');
    $clientSelect.empty().append('<option value="">-- Select Client --</option>');
    
    if (clients.length > 0) {
        clients.forEach(client => {
            $clientSelect.append(`<option value="${client.id}">${client.name}</option>`);
        });
    } else {
        $clientSelect.append('<option value="">No clients found</option>');
    }
    
    $clientSelect.prop('disabled', false);
    $('#modalCompanyId').val(companyId);
}).fail(function(jqXHR, textStatus, errorThrown) {
    console.error("Error fetching clients:", textStatus, errorThrown);
    alert('Error loading clients. Please try again.');
});
            } else {
                // Reset form if no company selected
                $('#productTypeSelect, #productSubTypeSelect, #clientSelect').val('').prop('disabled', true);
                $('#calculatorDisplayArea').html('<div class="alert alert-info">Please select company, product type, and client to begin calculation.</div>');
            }
        });
        
        // When product type is selected
        $('#productTypeSelect').change(function() {
            const productType = $(this).val();
            const $subTypeSelect = $('#productSubTypeSelect');
            
            $subTypeSelect.empty().append('<option value="">-- Select --</option>');
            
            if (productType) {
                // Populate subtype options
                productSubTypes[productType].forEach(subType => {
                    $subTypeSelect.append(`<option value="${subType}">${formatName(subType)}</option>`);
                });
                
                $subTypeSelect.prop('disabled', false);
            } else {
                $subTypeSelect.prop('disabled', true);
            }
        });
        
        // When all required selections are made
        $('#productSubTypeSelect, #clientSelect').change(function() {
            const companyId = $('#companySelect').val();
            const productType = $('#productTypeSelect').val();
            const subType = $('#productSubTypeSelect').val();
            const clientId = $('#clientSelect').val();
            
            if (companyId && productType && subType && clientId) {
                // Load the appropriate calculator
                $.get(`ajax_get_calculator.php?product_type=${productType}&sub_type=${subType}`, 
                    function(calculatorHtml) {
                        $('#calculatorDisplayArea').html(calculatorHtml);
                    });
            }
        });
        
        // Handle new client form submission
        $('#addClientForm').submit(function(e) {
            e.preventDefault();
            
            $.post('ajax_add_client.php', $(this).serialize(), function(response) {
                if (response.success) {
                    // Refresh client dropdown
                    const companyId = $('#companySelect').val();
                    $.get('ajax_get_clients.php?company_id=' + companyId, function(clients) {
                        const $clientSelect = $('#clientSelect');
                        $clientSelect.empty().append('<option value="">-- Select Client --</option>');
                        
                        clients.forEach(client => {
                            $clientSelect.append(`<option value="${client.id}">${client.name}</option>`);
                        });
                        
                        // Select the new client
                        $clientSelect.val(response.clientId).trigger('change');
                    });
                    
                    // Close modal
                    $('#addClientModal').modal('hide');
                    $('#addClientForm')[0].reset();
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
<?php ob_end_flush(); ?>