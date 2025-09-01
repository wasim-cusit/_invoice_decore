<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset(
    $_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
        header('Location: login.php');
        exit();
    }
}

require_once 'db.php';
$page = $_GET['page'] ?? 'welcome';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Modern Home Decore</title>
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <?php if (isset($_GET['page']) && $_GET['page'] === 'quotation'): ?>
    <link rel="stylesheet" href="/decore/assets/css/quotation.css">
  <?php endif; ?>
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
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      padding: 15px 20px;
      margin-left: 250px;
      transition: margin-left 0.3s ease;
    }
    .app-header.sidebar-collapsed {
      margin-left: 60px;
    }
    .main-content {
      background: white;
      min-height: 100vh;
      margin-left: 250px;
      transition: margin-left 0.3s ease;
    }
    .main-content.sidebar-collapsed {
      margin-left: 60px;
    }
    @media (max-width: 991.98px) {
      .app-header {
        margin-left: 0;
      }
      .app-header.sidebar-collapsed {
        margin-left: 0;
      }
      .main-content {
        margin-left: 0;
      }
      .main-content.sidebar-collapsed {
        margin-left: 0;
      }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>
  
  <!-- Header -->
  <header class="app-header d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
      <div class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
      </div>
    </div>
    <div class="flex-grow-1 text-center">
      <span class="fs-4 fw-bold text-accent">Modern Home <span class="text-primary">Decore</span></span>
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
  
  <!-- Main Content -->
  <div class="main-content">
    <?php
    // Main page routing logic (unchanged)
    if ($page === 'welcome'): ?>
      <div class="p-4">
        <h3><i class="fas fa-tachometer-alt text-primary me-2"></i>Dashboard Overview</h3>
        <div class="row mt-4">
          <!-- Quotations Card -->
          <div class="col-md-3 mb-4">
            <div class="card border-primary stat-card">
              <div class="card-body text-center">
                <i class="fas fa-file-invoice fa-3x text-primary mb-3"></i>
                <h5 class="card-title">Total Quotations</h5>
                <h2 class="mb-0" id="quotationCount">
                  <div class="spinner-border text-primary spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </h2>
                <small class="text-muted">Updated just now</small>
              </div>
            </div>
          </div>
          <!-- Companies Card -->
          <div class="col-md-3 mb-4">
            <div class="card border-success stat-card">
              <div class="card-body text-center">
                <i class="fas fa-building fa-3x text-success mb-3"></i>
                <h5 class="card-title">Companies</h5>
                <h2 class="mb-0" id="companyCount">
                  <div class="spinner-border text-success spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </h2>
                <small class="text-muted">Updated just now</small>
              </div>
            </div>
          </div>
          <!-- Expenses Card -->
          <div class="col-md-3 mb-4">
            <div class="card border-danger stat-card">
              <div class="card-body text-center">
                <i class="fas fa-money-bill-wave fa-3x text-danger mb-3"></i>
                <h5 class="card-title">Total Expenses</h5>
                <h2 class="mb-0" id="expenseTotal">
                  <div class="spinner-border text-danger spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </h2>
                <small class="text-muted">Updated just now</small>
              </div>
            </div>
          </div>
          <!-- Clients Card -->
          <div class="col-md-3 mb-4">
            <div class="card border-warning stat-card">
              <div class="card-body text-center">
                <i class="fas fa-users fa-3x text-warning mb-3"></i>
                <h5 class="card-title">Total Clients</h5>
                <h2 class="mb-0" id="clientCount">
                  <div class="spinner-border text-warning spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </h2>
                <small class="text-muted">Updated just now</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php elseif ($page === 'new_calculation'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'new_calculation.php'; ?>
      </div>
    <?php elseif ($page === 'quotation'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/quotations.php'; ?>
      </div>
    <?php elseif ($page === 'report_quotation'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/view_quotation.php'; ?>
      </div>
    <?php elseif ($page === 'reports_invoices'): ?>
      <div class="p-4">
        <!-- <h4><i class="fas fa-file-invoice me-2"></i>Reports</h4> -->
        <?php define('ALLOW_INCLUDE', true); include 'report_invoices.php'; ?>
      </div>
    <?php elseif ($page === 'reports_clients'): ?>
      <div class="p-4">
        <!-- <h4><i class="fas fa-file-invoice me-2"></i>Client Reports</h4> -->
        <?php define('ALLOW_INCLUDE', true); include 'client_report.php'; ?>
      </div>
    <?php elseif ($page === 'reports_worker'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'worker_report.php'; ?>
      </div>
    <?php elseif ($page === 'reports_views'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'eview.php'; ?>
      </div>
    <?php elseif ($page === 'add_expense'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'add_expense.php'; ?>
      </div>
    <?php elseif ($page === 'reports_expenses'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'reports_expenses.php'; ?>
      </div>
    <?php elseif ($page === 'reports_materials'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'reports_materials.php'; ?>
      </div>
    <?php elseif ($page === 'reports_hardware'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'reports_hardware.php'; ?>
      </div>
    <?php elseif ($page === 'settings_materials'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/settings_materials.php'; ?>
      </div>
    <?php elseif ($page === 'settings_hardware'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/settings_hardware.php'; ?>
      </div>
    <?php elseif ($page === 'settings_companies'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/settings_companies.php'; ?>
      </div>
    <?php elseif ($page === 'settings_add_company'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/settings_add_company.php'; ?>
      </div>
    <?php elseif ($page === 'clients'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'clients.php'; ?>
      </div>
    <?php elseif ($page === 'add_client'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'add_client.php'; ?>
      </div>
    <?php elseif ($page === 'select_window_type'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'select_window_type.php'; ?>
      </div>
    <?php elseif ($page === '2pslwindow'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/2pslwindow.php'; ?>
      </div>
    <?php elseif ($page === '3pslwindow'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/3pslwindow.php'; ?>
      </div>
    <?php elseif ($page === 'fixwindow'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/fixwindow.php'; ?>
      </div>
    <?php elseif ($page === 'hungwindow'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/hungwindow.php'; ?>
      </div>
    <?php elseif ($page === 'glass_door'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/glass_door.php'; ?>
      </div>
    <?php elseif ($page === 'half_door'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/half_door.php'; ?>
      </div>
    <?php elseif ($page === 'full_door'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/full_door.php'; ?>
      </div>
    <?php elseif ($page === 'openable_door'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'Pages/openable_door.php'; ?>
      </div>
    <?php elseif ($page === 'purchase'): ?>
      <div class="p-4">
        <?php define('ALLOW_INCLUDE', true); include 'purchase.php'; ?>
      </div>
    <?php elseif ($page === 'logout'): ?>
      <?php session_destroy(); header("Location: login.php"); exit(); ?>
    <?php else: ?>
      <div class="p-4">
        <h3>Page Not Found</h3>
        <p>The requested page could not be found.</p>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Dashboard statistics loading
    $(document).ready(function() {
      // Load quotation count
      $.get('ajax_get_summary.php', function(data) {
        $('#quotationCount').text(data.quotation_count || 0);
      });
      
      // Load company count
      $.get('ajax_get_summary.php', function(data) {
        $('#companyCount').text(data.company_count || 0);
      });
      
      // Load expense total
      $.get('ajax_get_summary.php', function(data) {
        $('#expenseTotal').text('₨' + (data.expense_total || 0));
      });
      
      // Load client count
      $.get('ajax_get_summary.php', function(data) {
        $('#clientCount').text(data.client_count || 0);
      });
    });
  </script>
  <?php if (isset($_GET['page']) && $_GET['page'] === 'quotation'): ?>
    <script src="/decore/assets/js/quotation.js"></script>
  <?php endif; ?>
</body>
</html>

<?php ob_end_flush(); ?>