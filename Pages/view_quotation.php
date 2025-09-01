<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: /login.php');
    exit();
}
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../db.php';

// Function to build query string
if (!function_exists('buildQueryString')) {
  function buildQueryString($params)
  {
    $query = [];
    foreach ($params as $key => $value) {
      if ($key != 'p' && $value !== '') {
        $query[] = "$key=" . urlencode($value);
      }
    }
    return implode('&', $query);
  }
}

// Handle deletion if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client_id'])) {
  $client_id = (int)$_POST['delete_client_id'];
  $company_id = (int)$_POST['company_id'];

  // Perform deletion
  $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
  $stmt->bind_param("i", $client_id);
  $stmt->execute();
  $stmt->close();

  // Redirect to same page to prevent form resubmission
  header("Location: index.php?page=report_quotation&company_id=" . $company_id);
  exit();
}

// Fetch all companies for dropdown
$companies = [];
$stmt = $conn->prepare("SELECT id, name FROM companies ORDER BY name");
if ($stmt) {
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $companies[] = $row;
  }
  $stmt->close();
}

// Get selected company - check GET first, then POST, then session, then default to first company
if (isset($_GET['company_id'])) {
  $company_id = (int)$_GET['company_id'];
} elseif (isset($_POST['company_id'])) {
  $company_id = (int)$_POST['company_id'];
} elseif (isset($_SESSION['current_company_id'])) {
  $company_id = (int)$_SESSION['current_company_id'];
} else {
  $company_id = $companies[0]['id'] ?? 0;
}

// Store company_id in session to persist across requests
$_SESSION['current_company_id'] = $company_id;

// Get search parameters
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
$offset = ($page - 1) * $records_per_page;

// Base query for counting and fetching
$base_query = "FROM clients c 
LEFT JOIN window_calculation_details wcd ON c.id = wcd.client_id AND c.company_id = wcd.company_id
WHERE c.company_id = ?";

$params = [$company_id];
$param_types = "i";

// Add search conditions
if (!empty($search_name)) {
  $base_query .= " AND c.name LIKE ?";
  $params[] = "%$search_name%";
  $param_types .= "s";
}

if (!empty($from_date) && !empty($to_date)) {
  $base_query .= " AND DATE(wcd.created_at) BETWEEN ? AND ?";
  $params[] = $from_date;
  $params[] = $to_date;
  $param_types .= "ss";
} elseif (!empty($from_date)) {
  $base_query .= " AND DATE(wcd.created_at) >= ?";
  $params[] = $from_date;
  $param_types .= "s";
} elseif (!empty($to_date)) {
  $base_query .= " AND DATE(wcd.created_at) <= ?";
  $params[] = $to_date;
  $param_types .= "s";
}

// Get total number of clients for the selected company with filters
$total_clients = 0;
$count_query = "SELECT COUNT(DISTINCT c.id) $base_query";
$stmt = $conn->prepare($count_query);
if ($stmt) {
  $stmt->bind_param($param_types, ...$params);
  $stmt->execute();
  $stmt->bind_result($total_clients);
  $stmt->fetch();
  $stmt->close();
}

// Calculate total pages for pagination
$total_pages = ceil($total_clients / $records_per_page);

// Fetch clients for the selected company with pagination and filters
$clients = [];
$select_query = "SELECT DISTINCT c.id, c.name, c.phone, c.address $base_query ORDER BY c.name LIMIT ? OFFSET ?";
$stmt = $conn->prepare($select_query);
if ($stmt) {
  // Add pagination parameters
  $params[] = $records_per_page;
  $params[] = $offset;
  $param_types .= "ii";

  $stmt->bind_param($param_types, ...$params);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $clients[] = $row;
  }
  $stmt->close();
}

// Build base query string for pagination
$queryParams = [
  'page' => 'report_quotation',
  'company_id' => $company_id,
  'search_name' => $search_name,
  'from_date' => $from_date,
  'to_date' => $to_date
];
$baseQueryString = buildQueryString($queryParams);

// If AJAX request, return only the table body
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
  ob_clean();
?>
  <tbody id="clients-table-body">
    <?php if (empty($clients)): ?>
      <tr>
        <td colspan="5" class="text-center py-2">No clients found</td>
      </tr>
    <?php else: ?>
      <?php foreach ($clients as $index => $client): ?>
        <?php
        $quotation_number = '';
        $stmt_quote = $conn->prepare("SELECT quotation_number FROM window_calculation_details WHERE client_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
        if ($stmt_quote) {
          $stmt_quote->bind_param("ii", $client['id'], $company_id);
          $stmt_quote->execute();
          $stmt_quote->bind_result($quotation_number);
          $stmt_quote->fetch();
          $stmt_quote->close();
        }
        ?>
        <tr>
          <td><?= $offset + $index + 1 ?></td>
          <td><?= htmlspecialchars($client['name']) ?></td>
          <td><?= htmlspecialchars($client['phone']) ?></td>
          <td><?= htmlspecialchars($client['address']) ?></td>
          <td>
            <?php if ($quotation_number): ?>
              <a href="index.php?page=reports_worker&quotation_number=<?= urlencode($quotation_number) ?>&client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-edit"></i> Worker
              </a>
            <?php else: ?>
              <span class="text-muted">No Quotation</span>
            <?php endif; ?>

            <a href="client_quotation.php?client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-info">
              <i class="fas fa-eye"></i> View
            </a>

            <form method="post" action="index.php?page=report_quotation&company_id=<?= $company_id ?>" style="display:inline;">
              <input type="hidden" name="delete_client_id" value="<?= $client['id'] ?>">
              <input type="hidden" name="company_id" value="<?= $company_id ?>">
              <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this?')">
                <i class="fas fa-trash"></i> Delete
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
<?php
  exit();
}

if (isset($_GET['quotation_id'])) {
  $quotation_id = (int)$_GET['quotation_id'];
  // Fetch all calculation details for this quotation
  $stmt = $conn->prepare("SELECT * FROM window_calculation_details WHERE quotation_number = (SELECT quotation_number FROM quotations WHERE id = ? LIMIT 1)");
  $stmt->bind_param("i", $quotation_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $fields = [
    'id', 'calculation_id', 'quotation_id', 'quotation_number', 'client_id', 'company_id', 'window_type', 'height', 'width', 'quantity', 'total_area',
    'frame_length', 'sash_length', 'net_sash_length', 'beading_length', 'interlock_length', 'steel_quantity', 'net_area', 'net_rubber_quantity',
    'burshi_length', 'locks', 'dummy', 'boofer', 'stopper', 'double_wheel', 'net_wheel', 'sada_screw', 'fitting_screw', 'self_screw', 'rawal_plug',
    'silicon_white', 'hole_caps', 'water_caps', 'material_cost', 'hardware_cost', 'glass_cost', 'total_cost', 'created_at', 'updated_at'
  ];
  // Fetch client and company info for header
  $stmt = $conn->prepare("SELECT q.quotation_number, q.date, q.created_at, c.name AS client_name, c.phone AS client_phone, c.address AS client_address, co.name AS company_name FROM quotations q INNER JOIN clients c ON q.client_id = c.id INNER JOIN companies co ON q.company_id = co.id WHERE q.id = ? LIMIT 1");
  $stmt->bind_param("i", $quotation_id);
  $stmt->execute();
  $stmt->bind_result($quotation_number, $quotation_date, $created_at, $client_name, $client_phone, $client_address, $company_name);
  $stmt->fetch();
  $stmt->close();
?>
  <div class="container quotation-container" style="max-width:1000px; margin:30px auto; background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,0.10); padding:40px 32px; border:1px solid #e8f4fd; font-family:'Segoe UI',sans-serif;">
    <!-- Header -->
    <div style="display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #e3eaf1; padding-bottom:10px; margin-bottom:14px;">
      <div style="display:flex; align-items:center;">
        <img src="../logo/mod.jpg" alt="Company Logo" style="height:48px; margin-right:12px; border-radius:8px; border:1px solid #e8f4fd; box-shadow:0 2px 8px rgba(25,118,210,0.10);">
        <div>
          <div style="font-size:20px; font-weight:700; color:#1976d2; letter-spacing:1px; margin-bottom:1px;">MODERN WINDOWS &amp; DOORS</div>
        </div>
      </div>
      <div style="text-align:right; background:#f8f9fa; padding:8px; border-radius:8px; border:1px solid #e3eaf1;">
        <div style="font-size:13px; color:#1976d2; font-weight:700; margin-bottom:1px;">#<?= htmlspecialchars($quotation_number ?? '') ?></div>
        <div style="font-size:10px; color:#999; font-weight:400;">Created: <?= $created_at ? date('d/M/Y H:i', strtotime($created_at)) : '' ?></div>
      </div>
    </div>
    <!-- Info Cards -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:14px;">
      <div style="background:#f8f9fa; border-radius:6px; padding:8px; border:1px solid #e3eaf1; font-size:12px;">
        <b>Client:</b> <?= htmlspecialchars($client_name ?? '') ?><br>
        <b>Phone:</b> <?= htmlspecialchars($client_phone ?? '') ?><br>
        <b>Address:</b> <?= htmlspecialchars($client_address ?? '') ?>
      </div>
      <div style="background:#f8f9fa; border-radius:6px; padding:8px; border:1px solid #e3eaf1; font-size:12px;">
        <b>Date:</b> <?= $quotation_date ? date('d/M/Y', strtotime($quotation_date)) : '' ?><br>
        <b>Status:</b> <span style="color:#4caf50; font-weight:600;">Active</span>
      </div>
    </div>
    <!-- Calculation Tables Side by Side -->
    <div class="calc-table-row" style="display: flex; gap: 24px; flex-wrap: nowrap; justify-content: center; margin-bottom:18px;">
      <?php
      $result->data_seek(0);
      $all_calculations = [];
      while ($row = $result->fetch_assoc()) {
        $all_calculations[] = $row;
      }
      foreach ($all_calculations as $calc):
      ?>
      <table class="calc-table" style="width:100%; max-width:520px; min-width:320px; margin:0 auto; border-collapse:collapse; font-size:11px; background:#1976d2; border:2px solid #1976d2; border-radius:10px; box-shadow:0 2px 8px rgba(25,118,210,0.08); overflow:hidden;">
        <thead>
          <tr style="background:#f8f9fa; border-bottom:1px solid rgb(12, 35, 211); height:18px;">
            <th style="padding:2px 6px; text-align:left; font-weight:700; color:#1976d2; border-right:1px solid #e3eaf1;">Field</th>
            <th style="padding:2px 6px; text-align:right; font-weight:700; color:#1976d2; border-right:1px solid #e3eaf1;">Value</th>
            <th style="padding:2px 6px; text-align:center; font-weight:700; color:#1976d2;">Unit</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $rowCount = 0;
          foreach ($fields as $field):
            if ($field === 'id' || $field === 'calculation_id' || $field === 'quotation_id' || $field === 'quotation_number' || $field === 'client_id' || $field === 'company_id' || $field === 'created_at' || $field === 'updated_at') continue;
            $value = $calc[$field] ?? '';
            $unit = '';
            switch ($field) {
              case 'height':
              case 'width':
              case 'frame_length':
              case 'sash_length':
              case 'net_sash_length':
              case 'beading_length':
              case 'interlock_length':
              case 'burshi_length':
                $unit = 'mm';
                break;
              case 'total_area':
              case 'net_area':
                $unit = 'sq ft';
                break;
              case 'material_cost':
                $unit = 'Rs';
                break;
              case 'hardware_cost':
                $unit = 'Rs';
                break;
              case 'glass_cost':
                $unit = 'Rs';
                break;
              case 'total_cost':
                $unit = 'Rs';
                break;
              case 'quantity':
              case 'steel_quantity':
              case 'net_rubber_quantity':
              case 'locks':
              case 'dummy':
              case 'boofer':
              case 'stopper':
              case 'double_wheel':
              case 'net_wheel':
              case 'sada_screw':
              case 'fitting_screw':
              case 'self_screw':
              case 'rawal_plug':
              case 'silicon_white':
              case 'hole_caps':
              case 'water_caps':
                $unit = 'pcs';
                break;
            }
            $is_numeric = is_numeric($value) && $value !== '';
          ?>
            <tr style="border-bottom:1px solid #f0f0f0; height:14px; background:<?= $rowCount % 2 == 0 ? '#f8fafd' : '#fff' ?>;">
              <td style="padding:2px 6px; font-weight:500; color:#333; border-right:1px solid #e3eaf1; text-transform:capitalize; background:inherit; text-align:left;"> <?= str_replace('_', ' ', $field) ?> </td>
              <td style="padding:2px 6px; color:#1976d2; border-right:1px solid #e3eaf1; background:inherit; text-align:<?= $is_numeric ? 'right' : 'left' ?>;"> <?= htmlspecialchars($value) ?> </td>
              <td style="padding:2px 6px; text-align:center; color:#999; font-size:11px; background:inherit;"> <?= $unit ?> </td>
            </tr>
          <?php $rowCount++; endforeach; ?>
        </tbody>
      </table>
      <?php endforeach; ?>
    </div>
    <style>
      @media print {
        .quotation-container {
          width: 800px !important;
          margin: 0 auto !important;
          padding: 0 !important;
        }
        .calc-table-row {
          display: block !important;
          width: 100% !important;
          max-width: 800px !important;
          page-break-inside: auto !important;
          break-inside: auto !important;
          margin: 0 !important;
        }
        .calc-table-row table,
        .calc-table {
          width: 380px !important;
          min-width: 0 !important;
          max-width: 380px !important;
          page-break-inside: avoid !important;
          break-inside: avoid !important;
          margin: 24px 0 24px 0 !important;
          border:2px solid #1976d2 !important;
          border-radius:10px !important;
          box-shadow:0 2px 8px rgba(25,118,210,0.08) !important;
        }
      }
      @media (max-width: 1100px) {
        .calc-table-row { flex-wrap: wrap !important; }
      }
    </style>
    <!-- Totals Card -->
    <?php
    // Initialize totals
    $material_cost = 0.0;
    $hardware_cost = 0.0;
    $glass_cost = 0.0;
    $total_cost = 0.0;
    foreach ($all_calculations as $calc) {
      $material_cost += isset($calc['material_cost']) ? (float)$calc['material_cost'] : 0.0;
      $hardware_cost += isset($calc['hardware_cost']) ? (float)$calc['hardware_cost'] : 0.0;
      $glass_cost += isset($calc['glass_cost']) ? (float)$calc['glass_cost'] : 0.0;
      $total_cost += isset($calc['total_cost']) ? (float)$calc['total_cost'] : 0.0;
    }
    ?>
    <div style="display:flex; justify-content:center; margin-top:10px;">
      <div style="background:linear-gradient(135deg,#e3f2fd 0%,#fff 100%); border-radius:6px; padding:10px 32px; border:1px solid #e3eaf1; box-shadow:0 1px 4px #1976d210; min-width:220px; max-width:320px; width:100%; text-align:left;">
        <div style="font-size:13px; color:#1976d2; font-weight:700; margin-bottom:4px; text-align:center;">Summary</div>
        <div style="font-size:12px; color:#333; margin-bottom:2px;"><b>Material Cost:</b> <span style="float:right;">Rs <?= number_format((float)$material_cost,2) ?></span></div>
        <div style="font-size:12px; color:#333; margin-bottom:2px;"><b>Hardware Cost:</b> <span style="float:right;">Rs <?= number_format((float)$hardware_cost,2) ?></span></div>
        <div style="font-size:12px; color:#333; margin-bottom:2px;"><b>Glass Cost:</b> <span style="float:right;">Rs <?= number_format((float)$glass_cost,2) ?></span></div>
        <div style="font-size:13px; color:#1976d2; font-weight:800; margin-top:4px; text-align:center;"><b>Total Cost:</b> Rs <?= number_format((float)$total_cost,2) ?></div>
      </div>
    </div>
    <!-- Action Buttons -->
    <div style="display:flex; justify-content:center; gap:10px; margin-top:16px; padding-top:10px; border-top:1px solid #e3eaf1;">
      <button onclick="window.print()" style="background:linear-gradient(135deg,#1976d2 0%,#1565c0 100%); color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; box-shadow:0 2px 6px rgba(25,118,210,0.15); transition:all 0.3s; font-size:12px;">
        <i class="fas fa-print me-2"></i>Print Quotation
      </button>
      <a href="../index.php?page=report_quotation&company_id=<?= $company_id ?>" style="background:linear-gradient(135deg,#6c757d 0%,#5a6268 100%); color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; box-shadow:0 2px 6px rgba(108,117,125,0.15); transition:all 0.3s; text-decoration:none; display:inline-flex; align-items:center; font-size:12px;">
        <i class="fas fa-arrow-left me-2"></i>Back to List
      </a>
      <a href="../index.php?page=reports_invoices" style="background:linear-gradient(135deg,#17a2b8 0%,#138496 100%); color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; box-shadow:0 2px 6px rgba(23,162,184,0.15); transition:all 0.3s; text-decoration:none; display:inline-flex; align-items:center; font-size:12px; margin-left:6px;">
        <i class="fas fa-file-invoice me-2"></i>Back to Invoices
      </a>
    </div>
  </div>
  <style>
    @media print {
      body * {
        visibility: hidden;
      }
      .quotation-container,
      .quotation-container * {
        visibility: visible;
      }
      .quotation-container {
        position: relative;
        left: 0;
        top: 0;
        width: 800px !important;
        min-width: 0;
        max-width: 100vw;
        margin: 0 auto !important;
        box-shadow: none !important;
        border: none !important;
        background: #fff !important;
        padding: 24px 16px !important;
      }
      .btn, button, a[href], .action-buttons, .no-print {
        display: none !important;
      }
      .calc-table-row {
        display: flex !important;
        flex-wrap: nowrap !important;
        gap: 24px !important;
        justify-content: center !important;
      }
      .calc-table-row table {
        page-break-inside: avoid !important;
      }
    }
    @media (max-width: 1100px) {
      .calc-table-row { flex-wrap: wrap !important; }
    }
  </style>
<?php
}
?>

<style>
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

<?php if (!isset($_GET['quotation_id'])): ?>
<div class="container" style="background:#fff; border-radius:20px; box-shadow:0 8px 32px rgba(0,0,0,0.08); padding:24px; border:1px solid #e8f4fd; max-width:1200px; margin:30px auto;">
    <div class="d-flex align-items-center bg-light rounded shadow-sm p-3 mb-4" style="gap: 14px;">
      <span class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; font-size: 2rem;">
        <i class="fas fa-file-signature"></i>
      </span>
      <div style="display: flex; flex-direction: column;">
        <span style="font-size: 1.5rem; font-weight: 600; color: #1976d2; letter-spacing: 1px;">Quotation Report</span>
        <small class="text-muted" style="font-size: 1rem;">Overview of all quotations for the selected company</small>
      </div>
    </div>

    <div class="card-body p-2">
      <form method="get" class="mb-2">
          <input type="hidden" name="page" value="report_quotation">
          <div class="d-flex align-items-center gap-2" style="overflow-x: auto; white-space: nowrap;">
              <!-- Company Dropdown (Slightly Smaller) -->
              <select name="company_id" class="form-select" style="height: 38px; width: 180px;" onchange="this.form.submit()">
                  <?php foreach ($companies as $comp): ?>
                      <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $company_id ? 'selected' : '' ?>>
                          <?= htmlspecialchars($comp['name']) ?>
                      </option>
                  <?php endforeach; ?>
              </select>

              <!-- Search Input (Slightly Smaller) -->
              <input type="text" name="search_name" class="form-control" style="height: 38px; width: 220px;"
                  placeholder="Search client" value="<?= htmlspecialchars($search_name) ?>">

              <!-- Date Range (Compact) -->
              <div class="d-flex align-items-center gap-1">
                  <span class="fw-medium" style="font-size: 0.9rem;">From:</span>
                  <input type="date" name="from_date" class="form-control" style="height: 38px; width: 140px;"
                      value="<?= htmlspecialchars($from_date) ?>" onchange="this.form.submit()">
                  <span class="fw-medium" style="font-size: 0.9rem;">To:</span>
                  <input type="date" name="to_date" class="form-control" style="height: 38px; width: 140px;"
                      value="<?= htmlspecialchars($to_date) ?>" onchange="this.form.submit()">
              </div>

              <!-- Buttons (Compact) -->
              <div class="d-flex gap-2">
                  <?php if (!empty($search_name) || !empty($from_date) || !empty($to_date)): ?>
                      <a href="index.php?page=report_quotation&company_id=<?= $company_id ?>" class="btn btn-secondary btn-sm">
                          Clear
                      </a>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-success btn-sm" style="height: 38px; padding: 0 16px;">
                      Search
                  </button>
              </div>
          </div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="report-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Client Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="clients-table-body">
          <?php if (empty($clients)): ?>
            <tr>
              <td colspan="5" class="text-center py-2">No clients found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($clients as $index => $client): ?>
              <?php
              // Fetch latest quotation_number for this client
              $quotation_number = '';
              $stmt_quote = $conn->prepare("SELECT quotation_number FROM window_calculation_details WHERE client_id = ? AND company_id = ? ORDER BY id DESC LIMIT 1");
              if ($stmt_quote) {
                $stmt_quote->bind_param("ii", $client['id'], $company_id);
                $stmt_quote->execute();
                $stmt_quote->bind_result($quotation_number);
                $stmt_quote->fetch();
                $stmt_quote->close();
              }
              ?>
              <tr>
                <td><?= $offset + $index + 1 ?></td>
                <td><?= htmlspecialchars($client['name']) ?></td>
                <td><?= htmlspecialchars($client['phone']) ?></td>
                <td><?= htmlspecialchars($client['address']) ?></td>
                <td>
                  <?php if ($quotation_number): ?>
                    <div class="d-flex flex-wrap gap-2">
                      <a href="index.php?page=reports_worker&quotation_number=<?= urlencode($quotation_number) ?>&client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit"></i> Worker
                      </a>
                      <a href="client_quotation.php?client_id=<?= $client['id'] ?>&company_id=<?= $company_id ?>" class="btn btn-sm btn-info">
                        <i class="fas fa-eye"></i> View
                      </a>
                      <form method="post" action="index.php?page=report_quotation&company_id=<?= $company_id ?>" style="display:inline;">
                        <input type="hidden" name="delete_client_id" value="<?= $client['id'] ?>">
                        <input type="hidden" name="company_id" value="<?= $company_id ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this?')">
                          <i class="fas fa-trash"></i> Delete
                        </button>
                      </form>
                    </div>
                  <?php else: ?>
                    <span class="text-muted">No Quotation</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
      <nav aria-label="Page navigation" class="pagination pagination-sm justify-content-center mt-3">
        <ul class="pagination pagination-sm justify-content-center">
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $page - 1 ?>">Previous</a>
            </li>
          <?php endif; ?>

          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $i ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($page < $total_pages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= $baseQueryString ?>&p=<?= $page + 1 ?>">Next</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>
