<?php
// define('ALLOW_INCLUDE', true);
// ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
// Get selected client from session
$selected_client_id = $_SESSION['selected_client_id'] ?? null;
$selected_client = null;

if ($selected_client_id) {
  $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
  $stmt->bind_param("i", $selected_client_id);
  $stmt->execute();
  $selected_client = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}
// if (!isset($_SESSION['admin'])) {
//     header("Location: login.php");
//     exit();
// }

require_once __DIR__ . '/../db.php';

// Check company selection
// // // Check company selection
// //// $company_id = (int)$_SESSION['selected_company_id'];
// // if (!isset($_SESSION['selected_company_id'])) {
// //   header("Location: index.php?page=new_calculation");
// //   exit();
// // }
// // $company_id = (int)$_SESSION['selected_company_id'];

// // Set company_id if available, otherwise null
// $company_id = isset($_SESSION['selected_company_id']) ? (int)$_SESSION['selected_company_id'] : null;
if (isset($_GET['quotation_id'])) {
  // Fetch company_id from the quotation record
  $stmt = $conn->prepare("SELECT company_id FROM quotations WHERE id = ?");
  $stmt->bind_param("i", $_GET['quotation_id']);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result->fetch_assoc();
  $company_id = $row ? (int)$row['company_id'] : null;
  $stmt->close();
} else {
  // For all other actions, require company selection
  if (!isset($_SESSION['selected_company_id'])) {
      header("Location: index.php?page=new_calculation");
      exit();
  }
  $company_id = (int)$_SESSION['selected_company_id'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Debug: Log POST data
  error_log("POST data received: " . print_r($_POST, true));

  // Reset quotation - Updated to clear all calculation-related session data
  if (isset($_POST['reset_quotation'])) {
    unset($_SESSION['quotation_items']);
    unset($_SESSION['selected_client_id']);
    unset($_SESSION['calculation_started']);
    header("Location: index.php?page=new_calculation");
    exit();
  }

  // Save new client - No changes needed here
  if (isset($_POST['save_client'])) {
    error_log("Attempting to save client");

    $name = $conn->real_escape_string($_POST['client_name'] ?? '');
    $phone = $conn->real_escape_string($_POST['client_phone'] ?? '');
    $address = $conn->real_escape_string($_POST['client_address'] ?? '');

    // Debug: Log client data
    error_log("Client data: Name=$name, Phone=$phone, Address=$address");

    if (empty($name) || empty($phone)) {
      $_SESSION['error'] = "Client name and phone are required";
      header("Location: index.php?page=quotation");
      exit();
    }

    $stmt = $conn->prepare("INSERT INTO clients (company_id, name, phone, address) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
      error_log("Prepare failed: " . $conn->error);
      $_SESSION['error'] = "Database error: " . $conn->error;
      header("Location: index.php?page=quotation");
      exit();
    }

    $stmt->bind_param("isss", $company_id, $name, $phone, $address);

    if ($stmt->execute()) {
      $client_id = $stmt->insert_id;
      error_log("Client saved successfully with ID: $client_id");
      $_SESSION['message'] = "Client added successfully!";
      header("Location: index.php?page=quotation&client_id=" . $client_id);
      exit();
    } else {
      error_log("Failed to save client: " . $stmt->error);
      $_SESSION['error'] = "Failed to add client: " . $stmt->error;
      header("Location: index.php?page=quotation");
      exit();
    }
    $stmt->close();
  }

  // Save quotation - Updated to clear session after successful save
  if (isset($_POST['save_quotation'])) {
    // if (empty($_POST['client_id'])) {
    //     $_SESSION['error'] = "Please select a client";
    //     header("Location: index.php?page=report_quotation");
    //     exit();
    // }

    $client_id = (int)$_POST['client_id'];
    $quotation_number = 'QP-' . date('YmdHis');
    $date = date('Y-m-d');
    $total_amount = (float)$_POST['total_amount'];
    $notes = $conn->real_escape_string($_POST['notes'] ?? '');
    $terms = $conn->real_escape_string($_POST['terms'] ?? '');
    $window_types = $conn->real_escape_string($_POST['window_types'] ?? '');

    $conn->begin_transaction();

    try {
      // Insert quotation
      $stmt = $conn->prepare("INSERT INTO quotations (company_id, client_id, quotation_number, date, total_amount, notes, terms, window_types) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("iissssss", $company_id, $client_id, $quotation_number, $date, $total_amount, $notes, $terms, $window_types);

      if (!$stmt->execute()) {
        throw new Exception("Failed to create quotation: " . $conn->error);
      }

      $quotation_id = $stmt->insert_id;
      $stmt->close();

      // Insert items
      foreach ($_POST['items'] as $item) {
        $description = $conn->real_escape_string($item['description'] ?? '');
        $unit = $conn->real_escape_string($item['unit'] ?? '');
        $area = isset($item['area']) ? (float)$item['area'] : NULL;
        $rate = isset($item['rate']) ? (float)$item['rate'] : NULL;
        $quantity = isset($item['quantity']) ? (float)$item['quantity'] : NULL;
        $amount = (float)$item['amount'];

        $stmt = $conn->prepare("INSERT INTO quotation_items 
                                      (quotation_id, description, unit, area, rate_per_sft, amount, quantity) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssddi", $quotation_id, $description, $unit, $area, $rate, $amount, $quantity);

        if (!$stmt->execute()) {
          throw new Exception("Failed to add items: " . $conn->error);
        }
        $stmt->close();
      }

      // Update window_calculation_details with quotation_number for this session's calculations
      if (isset($_SESSION['quotation_items']) && !empty($_SESSION['quotation_items'])) {
        foreach ($_SESSION['quotation_items'] as $window_item) {
          if (isset($window_item['window_type'], $window_item['height'], $window_item['width'])) {
            // Find matching calculations in window_calculation_details
            $update_stmt = $conn->prepare("UPDATE window_calculation_details 
                                                    SET quotation_number = ?,
                                                        client_id = ?,
                                                        company_id = ?
                                                    WHERE window_type = ?
                                                    AND height = ?
                                                    AND width = ?
                                                    AND quotation_number IS NULL
                                                    ORDER BY created_at DESC
                                                    LIMIT 1");

            $update_stmt->bind_param(
              "siisdd",
              $quotation_number,
              $client_id,
              $company_id,
              $window_item['window_type'],
              $window_item['height'],
              $window_item['width']
            );

            if (!$update_stmt->execute()) {
              throw new Exception("Failed to update window calculations: " . $conn->error);
            }
            $update_stmt->close();
          }
        }
      }

      $conn->commit();

      // Clear all calculation-related session data after successful save
      unset($_SESSION['quotation_items']);
      unset($_SESSION['selected_client_id']);
      unset($_SESSION['calculation_started']);

      header("Location: index.php?page=new_calculation");
      exit();
    } catch (Exception $e) {
      $conn->rollback();
      $_SESSION['error'] = $e->getMessage();
      header("Location: index.php?page=quotation");
      exit();
    }
  }
}

// Fetch clients
$clients = [];
$stmt = $conn->prepare("SELECT id, name, phone, address FROM clients WHERE company_id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $clients[] = $row;
}
$stmt->close();

// Automatically select the client if only one exists for the company
if (count($clients) === 1) {
  $_SESSION['selected_client_id'] = $clients[0]['id'];
}

// Get company info
$stmt = $conn->prepare("SELECT name, description FROM companies WHERE id = ?");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$result = $stmt->get_result();
$company = $result->fetch_assoc();
$stmt->close();

// ✅ Set selected client ID from GET or SESSION
if (isset($_GET['client_id'])) {
  $selected_client_id = $_GET['client_id'];
  $_SESSION['selected_client_id'] = $selected_client_id; // store for later use
} else {
  $selected_client_id = $_SESSION['selected_client_id'] ?? '';
}

// Fetch selected client details
$selected_client = null;
if (!empty($selected_client_id)) {
  $stmt = $conn->prepare("SELECT id, name, phone, address FROM clients WHERE id = ? AND company_id = ?");
  $stmt->bind_param("ii", $selected_client_id, $company_id);
  $stmt->execute();
  $selected_client = $stmt->get_result()->fetch_assoc();
  $stmt->close();
}

// Calculate totals from session
$total_area = array_reduce($_SESSION['quotation_items'] ?? [], function ($carry, $item) {
  return $carry + (($item['unit'] ?? '') === 'Sft' ? ($item['area'] ?? 0) : 0);
}, 0);

$window_quantity = array_reduce($_SESSION['quotation_items'] ?? [], function ($carry, $item) {
  return $carry + (isset($item['window_type']) ? ($item['quantity'] ?? 0) : 0);
}, 0);

$grand_total = array_reduce($_SESSION['quotation_items'] ?? [], function ($carry, $item) {
  return $carry + ($item['amount'] ?? 0);
}, 0);

$window_rate = $total_area > 0 ? $grand_total / $total_area : 0;
$final_total = $grand_total;

// Set selected client if coming from client save
if (isset($_GET['client_id'])) {
  $_SESSION['selected_client_id'] = $_GET['client_id'];
}
$selected_client_id = $_SESSION['selected_client_id'] ?? '';

// --- Single Quotation View Logic ---
if (isset($_GET['quotation_id']) && is_numeric($_GET['quotation_id'])) {
  $qid = (int)$_GET['quotation_id'];
  // Fetch quotation
  $stmt = $conn->prepare("SELECT q.*, c.name AS client_name, c.phone AS client_phone, c.address AS client_address, co.name AS company_name FROM quotations q INNER JOIN clients c ON q.client_id = c.id INNER JOIN companies co ON q.company_id = co.id WHERE q.id = ?");
  $stmt->bind_param("i", $qid);
  $stmt->execute();
  $quotation = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  // Fetch items
  $items = [];
  $stmt = $conn->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
  $stmt->bind_param("i", $qid);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($row = $result->fetch_assoc()) {
    $items[] = $row;
  }
  $stmt->close();
  // Use the same print layout as index.php?page=quotation
?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Report</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
    <style>
      body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f8f9fa;
      }

      .quotation-container {
        max-width: 900px;
        margin: 30px auto;
        background: #fff;
        border: 1px solid #ddd;
        box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        padding: 30px;
      }

      .quotation-header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #2c3e50;
        padding-bottom: 20px;
      }

      .quotation-logo {
        width: 90px;
        height: 90px;
        object-fit: contain;
        margin-bottom: 10px;
      }

      .quotation-title {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
        margin-bottom: 5px;
        letter-spacing: 2px;
        text-transform: uppercase;
      }

      .quotation-meta {
        font-size: 15px;
        color: #444;
        margin-bottom: 8px;
      }

      .quotation-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        flex-wrap: wrap;
        border-bottom: 1px solid #eee;
        padding-bottom: 15px;
      }

      .client-info,
      .company-info {
        width: 48%;
        min-width: 300px;
      }

      .quotation-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
        background: #fff;
      }

      .quotation-table th {
        background-color: #2c3e50;
        color: white;
        padding: 10px;
        text-align: left;
        font-size: 15px;
      }

      .quotation-table td {
        padding: 10px;
        border-bottom: 1px solid #ddd;
        font-size: 14px;
      }

      .text-right {
        text-align: right;
      }

      .terms-conditions {
        margin-top: 30px;
        font-size: 14px;
        line-height: 1.6;
        border-top: 1px solid #eee;
        padding-top: 15px;
      }

      .print-btn {
        margin-bottom: 20px;
      }

      @media print {
        .print-btn {
          display: none;
        }
      }
    </style>
  </head>

  <body>
    <div class="quotation-container">
      <button class="print-btn btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
      <div class="quotation-header">
        <img src="../logo/mod.jpg" alt="Company Logo" class="quotation-logo" />
        <div class="quotation-title">MODERN WINDOWS & DOORS</div>
        <div class="quotation-meta"><?= htmlspecialchars($quotation['quotation_number']) ?> &nbsp; | &nbsp; <?= date('d/M/Y', strtotime($quotation['date'])) ?></div>
        <div style="font-size: 24px; letter-spacing: 5px; color: #2c3e50;">QUOTATION</div>
      </div>
      <div class="quotation-info">
        <div class="client-info">
          <div><strong>Name:</strong> <?= htmlspecialchars($quotation['client_name']) ?></div>
          <div><strong>Address:</strong> <?= htmlspecialchars($quotation['client_address']) ?></div>
          <div><strong>Phone:</strong> <?= htmlspecialchars($quotation['client_phone']) ?></div>
        </div>
        <div class="company-info">
          <div><strong>Company:</strong> <?= htmlspecialchars($quotation['company_name']) ?></div>
        </div>
      </div>
      <table class="quotation-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Description</th>
            <th>Unit</th>
            <th>Area</th>
            <th>Qty</th>
            <th>Rate</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $i => $item): ?>
            <?php
              // Only output if at least one key field is not empty or zero
              if (
                (!isset($item['description']) || trim($item['description']) === '') &&
                (!isset($item['unit']) || trim($item['unit']) === '') &&
                (!isset($item['area']) || $item['area'] === '' || $item['area'] == 0) &&
                (!isset($item['quantity']) || $item['quantity'] === '' || $item['quantity'] == 0) &&
                (!isset($item['rate_per_sft']) || $item['rate_per_sft'] === '' || $item['rate_per_sft'] == 0) &&
                (!isset($item['amount']) || $item['amount'] === '' || $item['amount'] == 0)
              ) {
                continue;
              }
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
              <td><?= htmlspecialchars($item['unit'] ?? '') ?></td>
              <td><?= number_format((float)($item['area'] ?? 0), 2, '.', '') ?></td>
              <td><?= htmlspecialchars($item['quantity'] ?? '') ?></td>
              <td><?= number_format((float)($item['rate_per_sft'] ?? 0), 2, '.', '') ?></td>
              <td><?= number_format((float)($item['amount'] ?? 0), 2, '.', '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="text-right"><strong>Total: Rs <?= number_format((float)$quotation['total_amount'], 2, '.', '') ?></strong></div>
      <div class="terms-conditions">
        <strong>Terms & Conditions:</strong><br>
        <u>Payment Terms:</u><br>
        - Advance: 80%<br>
        - After Delivery of Frames: 15%<br>
        - After Installation: 5%<br><br>
        <u>General Conditions:</u><br>
        1. The company is not responsible for any design changes after this agreement is signed.<br>
        2. Orders will be delivered within 25 days after final measurements are confirmed.<br>
        3. Gaps up to 5mm will be sealed with silicone by the company; larger gaps are the client's responsibility.<br>
        4. Provision of electricity and scaffolding is the client's responsibility.<br>
        5. Quoted prices are valid for 10 days from the date of this quotation.
      </div>
    </div>
  </body>

  </html>
<?php
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quotation System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f8f9fa;
    }

    .quotation-container {
      max-width: 1200px;
      margin: 30px auto;
      padding: 30px;
      background: white;
      border: 1px solid #ddd;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
    }

    .quotation-header {
      text-align: center;
      margin-bottom: 30px;
      padding-bottom: 20px;
      border-bottom: 2px solid #2c3e50;
      position: relative;
    }

    .quotation-logo {
      width: 90px;
      height: 90px;
      object-fit: contain;
      margin-bottom: 10px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    .quotation-title {
      font-size: 32px;
      font-weight: bold;
      color: #2c3e50;
      margin-bottom: 5px;
      letter-spacing: 2px;
      text-transform: uppercase;
    }

    .quotation-meta {
      font-size: 15px;
      color: #444;
      margin-bottom: 8px;
    }

    .quotation-info {
      display: flex;
      justify-content: space-between;
      margin-bottom: 30px;
      flex-wrap: wrap;
      border-bottom: 1px solid #eee;
      padding-bottom: 15px;
    }

    .client-info,
    .company-info {
      width: 48%;
      min-width: 300px;
    }

    .quotation-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 30px;
      background: #fff;
    }

    .quotation-table th {
      background-color: #2c3e50;
      color: white;
      padding: 10px;
      text-align: left;
      font-size: 15px;
    }

    .quotation-table td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
      font-size: 14px;
    }

    .text-right {
      text-align: right;
    }

    .terms-conditions {
      margin-top: 30px;
      font-size: 14px;
      line-height: 1.6;
      border-top: 1px solid #eee;
      padding-top: 15px;
    }

    .no-print {
      display: block;
    }

    .print-only {
      display: none;
    }

    @media print {
      body {
        background: #fff !important;
        color: #000 !important;
      }

      .quotation-container {
        box-shadow: none !important;
        border: none !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
      }

      .quotation-header {
        border-bottom: 2px solid #2c3e50 !important;
        margin-bottom: 20px !important;
        padding-bottom: 10px !important;
      }

      .print-actions,
      .no-print,
      .btn,
      .btn-group,
      .btn-container,
      .alert-message,
      .form-control,
      .reset-btn,
      .pagination,
      nav,
      form,
      .window-btn,
      #newClientForm,
      #clientSection {
        display: none !important;
      }

      .window-types-section {
        background: none !important;
        padding: 0 !important;
        margin-top: 20px !important;
        border: none !important;
      }

      .window-types-title {
        font-size: 17px !important;
        margin-bottom: 8px !important;
      }

      .window-types-grid-print {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
        gap: 12px;
        margin-top: 10px;
      }

      .window-type-print {
        display: inline-block !important;
        background: #fff !important;
        box-shadow: none !important;
        border: 1px solid #bbb !important;
        padding: 8px !important;
        margin: 8px !important;
        width: 120px !important;
      }

      .window-type-print img {
        max-width: 80px !important;
        max-height: 80px !important;
        border: 1px solid #bbb !important;
        border-radius: 4px !important;
        margin-bottom: 4px !important;
      }

      .window-type-print p {
        font-size: 12px !important;
        color: #222 !important;
      }

      .quotation-table th,
      .quotation-table td {
        border: 1px solid #000 !important;
        color: #000 !important;
        background: #fff !important;
        padding: 6px !important;
      }

      .quotation-table th {
        background: #eaeaea !important;
        font-weight: bold !important;
      }

      tr,
      th,
      td {
        page-break-inside: avoid !important;
      }

      .terms-conditions {
        margin-top: 20px !important;
        font-size: 13px !important;
        border-top: 1px solid #bbb !important;
        padding-top: 10px !important;
      }
    }

    .form-control-sm {
      height: calc(1.5em + 0.5rem + 2px);
      padding: 0.25rem 0.5rem;
      font-size: 0.875rem;
    }

    .btn-container {
      display: flex;
      justify-content: space-between;
      margin-bottom: 20px;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-group {
      display: flex;
      gap: 10px;
    }

    .alert-message {
      margin-bottom: 20px;
    }

    .editable {
      border-bottom: 1px dashed #999;
      min-width: 80px;
      display: inline-block;
    }

    [contenteditable="true"]:focus {
      outline: none;
      border-bottom: 2px solid #4b6cb7;
      background-color: #f8f9fa;
    }



    .total-row {
      font-weight: bold;
      background-color: #f8f9fa !important;
    }

    @media print {
      .total-row {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
      }
    }

    input[type="number"] {
      width: 100%;
      text-align: right;
      box-sizing: border-box;
    }

    .yu {
      padding: 4px 8px;
      border: 1px solid #ced4da;
      border-radius: 0.25rem;
      width: 100%;
      text-align: right;
      box-sizing: border-box;
      font-size: 0.875rem;
    }

    .amount-input {
      width: 100%;
      text-align: right;
      border: 1px solid #ced4da;
      border-radius: 0.25rem;
      padding: 0.375rem 0.75rem;
    }

    .print-actions {
      margin-bottom: 20px;
    }

    #newClientForm {
      padding: 15px;
      background: #f8f9fa;
      border-radius: 5px;
      margin-top: 10px;
    }

    .window-types-section {
      margin-top: 40px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 5px;
    }

    .window-btn {
      padding: 8px 15px;
      margin: 5px;
      border: 1px solid #ddd;
      background: #fff;
      cursor: pointer;
      border-radius: 4px;
      transition: all 0.3s;
    }

    .window-btn:hover {
      background: #e9ecef;
    }

    .window-btn.selected {
      background: #28a745;
      color: white;
    }

    .window-img-container {
      display: inline-block;
      margin: 10px;
      text-align: center;
      vertical-align: top;
    }

    .window-img {
      max-width: 150px;
      max-height: 150px;
      border: 1px solid #ddd;
      margin-bottom: 5px;
    }

    .window-type-label {
      display: block;
      font-weight: bold;
    }

    .window-types-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
      gap: 15px;
    }

    .window-type-print {
      display: none;
    }

    @media print {
      .window-type-print {
        display: block;
        page-break-inside: avoid;
      }

      .window-type-print img {
        max-width: 100px;
        height: auto;
      }
    }

    /* Window Summary Table Styles */
    .window-summary-section {
      margin: 30px 0;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 10px;
      border-left: 5px solid #4b6cb7;
    }

    .window-summary-section h4 {
      color: #2c3e50;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid #ddd;
    }

    .window-summary-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
    }

    .window-summary-table th {
      background-color: #4b6cb7;
      color: white;
      padding: 12px 15px;
      text-align: left;
    }

    .window-summary-table td {
      padding: 10px 15px;
      border-bottom: 1px solid #eee;
    }

    .window-summary-table tbody tr:nth-child(even) {
      background-color: #f9f9f9;
    }

    .window-summary-table tbody tr:hover {
      background-color: #f1f5ff;
    }

    .summary-total-row {
      font-weight: bold;
      background-color: #f0f7ff !important;
    }

    .summary-total-row td {
      border-top: 2px solid #4b6cb7;
      border-bottom: none;
    }

    /* Print styles */
    @media print {
      .window-summary-section {
        page-break-inside: avoid;
        border-left: none;
        background: none;
      }

      .window-summary-table th {
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
        background-color: #4b6cb7 !important;
      }

      .window-summary-table tr {
        page-break-inside: avoid;
      }

      .summary-total-row {
        background-color: #f0f7ff !important;
        -webkit-print-color-adjust: exact;
        color-adjust: exact;
      }
    }

    @media print {
      .window-types-section {
        page-break-inside: avoid;
        margin-top: 30px;
      }

      .window-types-grid-print {
        display: grid !important;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 15px;
      }

      .window-type-print {
        display: inline-block !important;
        text-align: center;
        margin: 10px;
        page-break-inside: avoid;
      }

      .window-type-print img {
        max-width: 120px !important;
        height: auto;
        border: 1px solid #ddd;
      }
    }

    /* Hide the entire Recent Calculations block */
    /* Hide using direct parent-child targeting */
    /* Hide the ENTIRE Recent Calculations section */
    form#clientForm>div.mb-4 {
      display: none !important;
    }

    /* Modern window types grid: 3 per row, centered using CSS grid for print */
    .window-types-grid-print-modern {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 32px 24px;
      margin: 0 auto 0 auto;
      justify-items: center;
      max-width: 700px;
    }

    .window-type-print-modern {
      display: flex;
      flex-direction: column;
      align-items: center;
      background: #f8f9fa;
      box-shadow: 0 2px 8px #0001;
      border: 1px solid #bbb;
      border-radius: 10px;
      padding: 18px 18px 12px 18px;
      margin-bottom: 0;
      width: 180px;
      min-height: 210px;
      max-width: 220px;
      margin-top: 0;
    }

    .window-type-print-modern img {
      max-width: 120px;
      max-height: 120px;
      border: 1px solid #bbb;
      border-radius: 6px;
      margin-bottom: 10px;
      background: #fff;
      box-shadow: 0 1px 4px #0001;
    }

    .window-type-label-modern {
      font-size: 15px;
      font-weight: bold;
      color: #2c3e50;
      margin-top: 8px;
      text-align: center;
      letter-spacing: 1px;
    }

    @media print {
      body {
        background: #fff !important;
        color: #000 !important;
      }

      .print-main-container {
        width: 800px;
        margin: 0 auto;
        background: #fff;
        border: 1px solid #e0e0e0;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.07);
        padding: 40px 32px 32px 32px;
        page-break-after: avoid;
        min-height: 100vh;
      }

      .window-types-grid-print-modern {
        max-width: 100% !important;
        gap: 24px 16px !important;
        grid-template-columns: repeat(3, 1fr) !important;
        justify-items: center !important;
      }

      .window-type-print-modern {
        width: 180px !important;
        min-height: 210px !important;
        max-width: 220px !important;
        padding: 14px 10px 10px 10px !important;
        flex: 0 1 calc(33.333% - 16px) !important;
      }

      .window-type-print-modern img {
        max-width: 110px !important;
        max-height: 110px !important;
      }
    }
  </style>
</head>

<body>
  <div class="container-fluid">
    <div class="row">
      <div class="col-12 p-0">
        <!-- Main content starts here -->
        <div class="container">
          <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-message alert-dismissible fade show">
              <?= htmlspecialchars($_SESSION['message']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['message']); ?>
          <?php endif; ?>

          <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-message alert-dismissible fade show">
              <?= htmlspecialchars($_SESSION['error']) ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
          <?php endif; ?>

          <div class="quotation-container">
            <div class="quotation-header">
              <img src="logo/mod.jpg" alt="Company Logo" class="quotation-logo" />
              <div class="quotation-title">MODERN WINDOWS & DOORS</div>
              <div class="quotation-meta">QP-<?= date('YmdHis') ?> &nbsp; | &nbsp; <?= date('d/M/Y') ?></div>
              <div class="mt-3" style="font-size: 24px; letter-spacing: 5px; color: #2c3e50;">QUOTATION</div>
            </div>

            <div class="quotation-info">
              <div class="client-info">
                <div id="clientSection" class="no-print">

                  <form id="clientForm" method="post">
                    <div class="mb-3">
                      <label class="form-label fw-bold">Client Information</label>
                      <select class="form-select" id="clientSelect" name="client_id" required>
                        <option value="">Select a client</option>
                        <?php foreach ($clients as $client): ?>
                          <option value="<?= htmlspecialchars($client['id']) ?>"
                            <?= ($selected_client_id == $client['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['name']) ?> - <?= htmlspecialchars($client['phone']) ?>
                          </option>
                        <?php endforeach; ?>
                        <option value="new">+ Add New Client</option>
                      </select>
                    </div>





                    <?php if (!empty($selected_client_id)): ?>
                      <div class="mb-4">
                        <h5>Recent Calculations</h5>
                        <?php
                        $stmt = $conn->prepare("SELECT * FROM calculation_records 
                                              WHERE client_id = ? AND company_id = ?
                                              ORDER BY created_at DESC LIMIT 3");
                        $stmt->bind_param("ii", $selected_client_id, $company_id);
                        $stmt->execute();
                        $calc_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt->close();
                        ?>

                        <?php if (!empty($calc_history)): ?>
                          <div class="table-responsive">
                            <table class="table table-sm table-striped">
                              <thead>
                                <tr>
                                  <th>Date</th>
                                  <th>Type</th>
                                  <th>Dimensions</th>
                                  <th>Amount</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($calc_history as $calc): ?>
                                  <tr>
                                    <td><?= date('d/m/y', strtotime($calc['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($calc['window_type']) ?></td>
                                    <td><?= round($calc['width'], 2) ?>×<?= round($calc['height'], 2) ?>ft</td>
                                    <td>Rs. <?= number_format($calc['total_cost'], 2, '.', '') ?></td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                          <a href="client_calculations.php?client_id=<?= $selected_client_id ?>" class="btn btn-sm btn-outline-primary">
                            View All Calculations
                          </a>
                        <?php else: ?>
                          <p class="text-muted">No calculation history found for this client</p>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>

                    <div id="newClientForm" style="display: none;">
                      <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" placeholder="Client Name" name="client_name" required>
                      </div>
                      <div class="mb-2">
                        <input type="text" class="form-control form-control-sm" placeholder="Phone" name="client_phone" required>
                      </div>
                      <div class="mb-2">
                        <textarea class="form-control form-control-sm" placeholder="Address" name="client_address" rows="2" required></textarea>
                      </div>
                      <button type="submit" name="save_client" class="btn btn-primary btn-sm">Save Client</button>
                    </div>
                  </form>
                </div>


                <div id="clientDisplay" class="print-only">
                  <div><strong>Name:</strong> <span id="clientNameDisplay" class="editable"><?= htmlspecialchars($selected_client['name'] ?? 'Client Name') ?></span></div>
                  <div><strong>Address:</strong> <span id="clientAddressDisplay" class="editable"><?= htmlspecialchars($selected_client['address'] ?? 'Client Address') ?></span></div>
                  <div><strong>Phone:</strong> <span id="clientPhoneDisplay" class="editable"><?= htmlspecialchars($selected_client['phone'] ?? 'Phone Number') ?></span></div>
                </div>
              </div>

              <div class="company-info print-only">
                <div><strong>Company:</strong> <?= htmlspecialchars($company['name'] ?? 'Modern Windows & Doors') ?></div>
                <div><strong>Contact Person:</strong> Ejaz Khan</div>
                <div><strong>Contact Number:</strong> 0300-9000726</div>
                <div><strong>Office Contact:</strong> 091-3049435</div>
              </div>
            </div>

            <form id="quotationForm" method="post">
              <input type="hidden" name="client_id" id="selectedClientId" value="<?= htmlspecialchars($selected_client_id) ?>">
              <input type="hidden" name="window_types" id="windowTypesInput" value="">

              <div class="print-actions no-print">
                <div class="btn-container">
                  <div class="btn-group">
                    <button type="button" class="btn btn-primary btn-sm" onclick="printQuotation()">
                      <i class="fas fa-print"></i> Print
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="addRowBtn">
                      <i class="fas fa-plus"></i> Add Item
                    </button>
                    <!-- <a href="Pages/view_quotation.php" class="btn btn-info btn-sm">
                      <i class="fas fa-list"></i> View Quotations
                    </a> -->
                    <a href="index.php?page=report_quotation" class="btn btn-warning btn-sm">
                      <i class="fas fa-file-alt"></i> Report Quotation
                    </a>
                  </div>
                  <div class="btn-group">
                    <button type="submit" name="reset_quotation" class="btn btn-danger btn-sm reset-btn">
                      <i class="fas fa-trash"></i> Reset
                    </button>
                    <button type="submit" class="btn btn-success btn-sm" name="save_quotation">
                      <i class="fas fa-save"></i> Save
                    </button>
                  </div>
                </div>
              </div>

              <table class="quotation-table">
                <thead>
                  <tr>
                    <th width="5%">#</th>
                    <th width="35%">Description</th>
                    <th width="10%">Unit</th>
                    <th width="10%">Area</th>
                    <th width="10%">Qty</th>
                    <th width="15%">Rate</th>
                    <th width="15%">Amount</th>
                  </tr>
                </thead>
                <tbody id="quotationItems">
                  <!-- Predefined rows with amount calculation -->
                  <?php
                  $predefined_items = [
                    [
                      'description' => 'Total Area of Windows',
                      'unit' => 'Sft',
                      'area' => $total_area,
                      'quantity' => $window_quantity,
                      'rate' => $window_rate,
                      'amount' => $grand_total
                    ],
                    [
                      'description' => 'Total Area of 6mm Plain Glass',
                      'unit' => 'Sft',
                      'area' => $total_area,
                      'quantity' => 0,
                      'rate' => 0,
                      'amount' => 0
                    ],
                    [
                      'description' => 'Total Area of Double Glaze with 6mm Plain Glass',
                      'unit' => 'Sft',
                      'area' => $total_area,
                      'quantity' => 0,
                      'rate' => 0,
                      'amount' => 0
                    ],
                    [
                      'description' => 'Installation',
                      'unit' => 'Sft',
                      'area' => $total_area,
                      'quantity' => 0,
                      'rate' => 0,
                      'amount' => 0
                    ],
                    [
                      'description' => 'Transportation (As per Actual)',
                      'unit' => 'Ls',
                      'area' => 1,
                      'quantity' => 0,
                      'rate' => 0,  
                      'amount' => 0
                    ],
                    [
                      'description' => 'Quantity of Windows',
                      'unit' => 'Ls',
                      'area' => 0,
                      'quantity' => $window_quantity,
                      'rate' => 0,
                      'amount' => 0
                    ]
                  ];

                  foreach ($predefined_items as $index => $item): ?>
                    <tr>
                      <td><?= $index + 1 ?></td>
                      <td>
                        <input type="text" class="form-control form-control-sm no-print"
                          name="items[<?= $index ?>][description]" value="<?= htmlspecialchars($item['description']) ?>" <?= $index === 0 ? 'readonly' : '' ?>>
                        <span class="print-only"><?= htmlspecialchars($item['description']) ?></span>
                      </td>
                      <td><?= htmlspecialchars($item['unit']) ?></td>
                      <td>
                        <input type="number" class="form-control form-control-sm no-print area-field"
                          name="items[<?= $index ?>][area]" value="<?= isset($item['area']) ? number_format((float)$item['area'], 2, '.', '') : '0.00' ?>" step="0.01" <?= $index === 5 ? 'readonly' : '' ?>>
                        <span class="print-only"><?= number_format($item['area'], 2, '.', '') ?></span>
                      </td>
                      <td>
                        <input type="number" class="form-control form-control-sm no-print qty-field"
                          name="items[<?= $index ?>][quantity]" value="<?= isset($item['quantity']) ? number_format((float)$item['quantity'], 2, '.', '') : '0.00' ?>" <?= $index !== 5 ? 'readonly' : '' ?>>
                        <span class="print-only"><?= $item['quantity'] ?></span>
                      </td>
                      <td>
                        <input type="number" class="form-control form-control-sm no-print rate-field"
                          name="items[<?= $index ?>][rate]" value="<?= isset($item['rate']) ? number_format((float)$item['rate'], 2, '.', '') : '0.00' ?>" step="0.01" <?= $index === 5 ? 'disabled' : '' ?>>
                        <span class="print-only"><?= $index === 5 ? '-' : number_format($item['rate'], 2, '.', '') ?></span>
                      </td>
                      <td>
                        <input type="number" class="form-control form-control-sm no-print amount-field"
                          name="items[<?= $index ?>][amount]" readonly value="<?= isset($item['amount']) ? number_format((float)$item['amount'], 2, '.', '') : '0.00' ?>">
                        <span class="print-only"><?= $index === 5 ? '-' : number_format($item['amount'], 2, '.', '') ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php
                  // Add detailed window items from session (if any)
                  $rowOffset = count($predefined_items);
                  $i = 0;
                  if (!empty($_SESSION['quotation_items'])) {
                    foreach ($_SESSION['quotation_items'] as $item) {
                      // Only output if at least one of the key fields is not empty
                      if (
                        (!isset($item['window_type']) || empty($item['window_type'])) &&
                        (!isset($item['unit']) || empty($item['unit'])) &&
                        (!isset($item['area']) || $item['area'] === '' || $item['area'] === 0) &&
                        (!isset($item['quantity']) || $item['quantity'] === '' || $item['quantity'] === 0) &&
                        (!isset($item['rate']) || $item['rate'] === '' || $item['rate'] === 0) &&
                        (!isset($item['amount']) || $item['amount'] === '' || $item['amount'] === 0)
                      ) {
                        continue;
                      }
                      $i++;
                  ?>
                      <tr>
                        <td><b><?= $rowOffset + $i ?></b></td>
                        <td><input type="text" class="form-control form-control-sm no-print" name="items[<?= $rowOffset + $i ?>][window_type]" value="<?= htmlspecialchars($item['window_type'] ?? '') ?>" required><b><span class="print-only">Window: <?= htmlspecialchars($item['window_type'] ?? '') ?></span></b></td>
                        <td><b><?= htmlspecialchars($item['unit'] ?? 'Sft') ?></b></td>
                        <td><b><?= isset($item['area']) ? number_format($item['area'], 2, '.', '') : '' ?></b></td>
                        <td><b><?= isset($item['quantity']) ? number_format($item['quantity'], 2, '.', '') : '' ?></b></td>
                        <td><b><?= isset($item['rate']) ? number_format($item['rate'], 2, '.', '') : '' ?></b></td>
                        <td><b><?= isset($item['amount']) ? number_format((float)$item['amount'], 2, '.', '') : '' ?></b></td>
                      </tr>
                  <?php
                    }
                  }
                  ?>
                </tbody>
                <tfoot>
                  <tr class="total-row">
                    <td colspan="6" class="text-right"><strong>Total Amount (Rs)</strong></td>
                    <td>
                      <span id="quotationTotal"><?= number_format($final_total, 2, '.', '') ?></span>
                    </td>
                  </tr>
                </tfoot>
              </table>

              <!-- Add this after the window-details-section but before the terms-conditions -->
              <div class="window-specs-section mt-4 no-print">
                <h4><i class="fas fa-ruler-combined me-2"></i>Window Specifications</h4>
                <div class="table-responsive">
                  <table class="table table-bordered">
                    <thead class="table-primary">
                      <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Width (ft)</th>
                        <th>Height (ft)</th>
                        <th>Area (sft)</th>
                        <th>Qty</th>
                        <th>Rate (Rs/sft)</th>
                        <th>Amount (Rs)</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                      $windowCount = 0;
                      foreach ($_SESSION['quotation_items'] ?? [] as $item):
                        if (!isset($item['window_type'])) continue;
                        $windowCount++;
                        // Debug output as comment
                        echo "<!-- DEBUG ITEM: " . print_r($item, true) . " -->";
                      ?>
                        <tr>
                          <td><?= $windowCount ?></td>
                          <td><?= htmlspecialchars($item['window_type']) ?></td>
                          <td><?= isset($item['width']) ? number_format($item['width'], 2, '.', '') : '' ?></td>
                          <td><?= isset($item['height']) ? number_format($item['height'], 2, '.', '') : '' ?></td>
                          <td><?= isset($item['area']) ? number_format($item['area'], 2, '.', '') : '' ?></td>
                          <td><?= isset($item['quantity']) ? number_format($item['quantity'], 2, '.', '') : '' ?></td>
                          <td><?= isset($item['rate']) ? number_format($item['rate'], 2, '.', '') : '' ?></td>
                          <td><?= isset($item['amount']) ? number_format((float)$item['amount'], 2, '.', '') : '' ?></td>
                        </tr>
                      <?php endforeach; ?>

                      <?php if ($windowCount === 0): ?>
                        <tr>
                          <td colspan="8" class="text-center text-muted py-3">
                            <i class="fas fa-info-circle me-2"></i>
                            No window specifications available yet. Add calculations to see details here.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Calculation Breakdown Section -->
              <!--
              <div class="calculation-breakdown-section mt-4">
                <h4><i class="fas fa-calculator me-2"></i>Calculation Breakdown</h4>
                <div class="row">
                  <?php
                  $calcCount = 0;
                  foreach ($_SESSION['quotation_items'] ?? [] as $item):
                    if (!isset($item['calculation_id'])) continue;
                    $calcCount++;

                    // Fetch calculation details
                    $stmt = $conn->prepare("SELECT * FROM calculation_records WHERE id = ?");
                    $stmt->bind_param("i", $item['calculation_id']);
                    $stmt->execute();
                    $calc = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($calc):
                      $calcData = json_decode($calc['calculation_data'], true);
                  ?>
                    <div class="col-md-6 mb-3">
                      <div class="card">
                        <div class="card-header">
                          <h6 class="mb-0"><?= htmlspecialchars($item['window_type']) ?></h6>
                        </div>
                        <div class="card-body">
                          <div class="row">
                            <div class="col-6">
                              <strong>Dimensions:</strong><br>
                              <?= number_format($calc['width'], 2) ?>ft × <?= number_format($calc['height'], 2) ?>ft<br>
                              <strong>Quantity:</strong> <?= $calc['quantity'] ?><br>
                              <strong>Total Area:</strong> <?= number_format($calc['total_area'], 2) ?> sft
                            </div>
                            <div class="col-6">
                              <strong>Total Cost:</strong><br>
                              Rs. <?= number_format($calc['total_cost'], 2) ?><br>
                              <strong>Rate:</strong> Rs. <?= number_format($calc['total_cost'] / $calc['total_area'], 2) ?>/sft
                            </div>
                          </div>
                          <?php if ($calcData && isset($calcData['materials'])): ?>
                          <hr>
                          <div class="mt-2">
                            <strong>Materials:</strong><br>
                            <?php foreach ($calcData['materials'] as $material => $data): ?>
                              <?= htmlspecialchars($material) ?>: <?= number_format($data['length'] ?? $data['quantity'] ?? 0, 2) ?> 
                              (Rs. <?= number_format($data['cost'] ?? 0, 2) ?>)<br>
                            <?php endforeach; ?>
                          </div>
                          <?php endif; ?>
                          <?php if ($calcData && isset($calcData['hardware'])): ?>
                          <div class="mt-2">
                            <strong>Hardware:</strong><br>
                            <?php foreach ($calcData['hardware'] as $hardware => $data): ?>
                              <?= htmlspecialchars($hardware) ?>: <?= $data['quantity'] ?? 0 ?> pcs
                              (Rs. <?= number_format($data['cost'] ?? 0, 2) ?>)<br>
                            <?php endforeach; ?>
                          </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php
                    endif;
                  endforeach;

                  if ($calcCount === 0): ?>
                    <div class="col-12">
                      <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No detailed calculation breakdown available. Add items from calculators to see detailed cost breakdowns.
                      </div>
                    </div>
                  <?php endif; ?>
                </div>
              </div>
              -->

              <div class="terms-conditions">
                <div class="mb-3">
                  <strong>Note:</strong> All profile warranties are subject to company policy. Please review your quotation carefully before confirming your order.
                </div>
                <div>
                  <strong class="no-print">Terms & Conditions:</strong>
                  <textarea class="form-control no-print" name="terms" rows="6" style="width: 100%;">Payment Terms:
- Advance: 80%
- After Delivery of Frames: 15%
- After Installation: 5%

General Conditions:
1. The company is not responsible for any design changes after this agreement is signed.
2. Orders will be delivered within 25 days after final measurements are confirmed.
3. Gaps up to 5mm will be sealed with silicone by the company; larger gaps are the client's responsibility.
4. Provision of electricity and scaffolding is the client's responsibility.
5. Quoted prices are valid for 10 days from the date of this quotation.</textarea>
                  <div class="print-only">
                    <strong>Terms & Conditions:</strong><br>
                    <u>Payment Terms:</u><br>
                    - Advance: 80%<br>
                    - After Delivery of Frames: 15%<br>
                    - After Installation: 5%<br><br>
                    <u>General Conditions:</u><br>
                    1. The company is not responsible for any design changes after this agreement is signed.<br>
                    2. Orders will be delivered within 25 days after final measurements are confirmed.<br>
                    3. Gaps up to 5mm will be sealed with silicone by the company; larger gaps are the client's responsibility.<br>
                    4. Provision of electricity and scaffolding is the client's responsibility.<br>
                    5. Quoted prices are valid for 10 days from the date of this quotation.
                  </div>
                </div>
                <div class="mt-4 no-print">
                  <label class="form-label fw-bold">Additional Notes</label>
                  <textarea class="form-control" name="notes" rows="3" placeholder="Any special instructions or notes..."></textarea>
                </div>
              </div>

              <div class="window-types-section">
                <div class="window-types-title print-only">Selected Window Types</div>
                <div class="window-types-grid-print">
                  <?php
                  $windowTypes = [
                    ['id' => '2psl', 'label' => '2 Panel Sliding', 'img' => 'Pages/image/2psl.png'],
                    ['id' => '3psl', 'label' => '3 Panel Sliding', 'img' => 'Pages/image/3psl.png'],
                    ['id' => 'fix', 'label' => 'Fixed Window', 'img' => 'Pages/image/fix.png'],
                    ['id' => 'halfdoor', 'label' => 'Half Window', 'img' => 'Pages/image/halfdoor.png'],
                    ['id' => 'fulldoor', 'label' => 'Full Door', 'img' => 'Pages/image/fulldoor.png'],
                    ['id' => 'openable', 'label' => 'Openable Window', 'img' => 'Pages/image/openable.png '],
                    ['id' => 'tophung', 'label' => 'Top Hung', 'img' => 'Pages/image/tophung.png'],
                    ['id' => 'glass', 'label' => 'Glass Door', 'img' => 'Pages/image/glass.png'],
                  ];

                  foreach ($windowTypes as $win): ?>
                    <div class="window-img-container">
                      <button type="button" class="window-btn" data-id="<?= $win['id'] ?>">
                        <?= $win['label'] ?>
                      </button>
                      <img src="<?= $win['img'] ?>" id="img-<?= $win['id'] ?>" class="window-img" style="display:none;">
                      <div class="window-type-print" id="print-<?= $win['id'] ?>">

                        <p><?= $win['label'] ?></p>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>



              <input type="hidden" name="total_amount" id="totalAmountInput" value="<?= number_format($final_total, 2, '.', '') ?>">
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      $('.dropdown-toggle').each(function() {
        new bootstrap.Dropdown(this);
      });

      $(document).on('click', '#changeClientBtn', function() {
        // Replace current display with client select dropdown
        $(this).closest('.client-info').html(`
        <div class="mb-3">
            <label class="form-label fw-bold">Client Information</label>
            <select class="form-select form-select-sm" id="clientSelect" name="client_id" required>
                <option value="">Select a client</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?= htmlspecialchars($client['id']) ?>" <?= ($selected_client_id == $client['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($client['name']) ?> - <?= htmlspecialchars($client['phone']) ?>
                    </option>
                <?php endforeach; ?>
                <option value="new">+ Add New Client</option>
            </select>
        </div>
    `);
      });

      // Initialize client select if coming from session
      <?php if ($selected_client_id): ?>
        $(document).ready(function() {
          $('#clientSelect').val(<?= $selected_client_id ?>);
        });
      <?php endif; ?>

      // Client selection
      const clientSelect = document.getElementById('clientSelect');
      const newClientForm = document.getElementById('newClientForm');
      const clientForm = document.getElementById('clientForm');
      const selectedClientId = document.getElementById('selectedClientId');

      if (clientSelect) {
        clientSelect.addEventListener('change', function() {
          if (this.value === 'new') {
            newClientForm.style.display = 'block';
            document.getElementById('clientNameDisplay').textContent = '';
            document.getElementById('clientAddressDisplay').textContent = '';
            document.getElementById('clientPhoneDisplay').textContent = '';
            selectedClientId.value = '';
          } else {
            newClientForm.style.display = 'none';
            selectedClientId.value = this.value;
            const selectedClient = <?= json_encode($clients) ?>.find(c => c.id == this.value);
            if (selectedClient) {
              document.getElementById('clientNameDisplay').textContent = selectedClient.name;
              document.getElementById('clientAddressDisplay').textContent = selectedClient.address || '';
              document.getElementById('clientPhoneDisplay').textContent = selectedClient.phone || '';
            }
          }
        });

        if (clientSelect.value && clientSelect.value !== 'new') {
          const selectedClient = <?= json_encode($clients) ?>.find(c => c.id == clientSelect.value);
          if (selectedClient) {
            document.getElementById('clientNameDisplay').textContent = selectedClient.name;
            document.getElementById('clientAddressDisplay').textContent = selectedClient.address || '';
            document.getElementById('clientPhoneDisplay').textContent = selectedClient.phone || '';
          }
        }
      }

      clientForm.addEventListener('submit', function(e) {
        if (e.submitter && e.submitter.name === 'save_client') {
          e.preventDefault();
          const name = document.querySelector('input[name="client_name"]').value;
          const phone = document.querySelector('input[name="client_phone"]').value;
          const address = document.querySelector('textarea[name="client_address"]').value;

          if (!name || !phone) {
            alert('Please fill all required client details (name and phone)');
            return false;
          }

          const formData = new FormData();
          formData.append('save_client', '1');
          formData.append('client_name', name);
          formData.append('client_phone', phone);
          formData.append('client_address', address);

          fetch(window.location.href, {
            method: 'POST',
            body: formData
          }).then(response => {
            if (response.redirected) {
              window.location.href = response.url;
            } else {
              return response.text().then(text => {
                console.error('Unexpected response:', text);
                alert('An error occurred while saving the client');
              });
            }
          }).catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the client');
          });

          return false;
        }
      });

      // Add row functionality
      let rowCount = <?= count($predefined_items) ?>;
      document.getElementById('addRowBtn').addEventListener('click', function() {
        const predefinedCount = 6; // Number of predefined items
        const tbody = document.getElementById('quotationItems');
        const customRows = tbody.querySelectorAll('tr').length - predefinedCount;
        const serial = predefinedCount + customRows + 1;
        rowCount++;
        const newRow = document.createElement('tr');
        newRow.innerHTML = `
        <td><b>${serial}</b></td>
        <td><input type="text" class="form-control form-control-sm no-print" name="items[${serial}][window_type]" value="" required><b><span class="print-only">Window: </span></b></td>
        <td>
          <select class="form-select form-select-sm no-print" name="items[${serial}][unit]" required>
            <option value="Sft">Sft</option>
            <option value="Pcs">Pcs</option>
            <option value="Ls">Ls</option>
          </select>
          <span class="print-only">Sft</span>
        </td>
        <td>
          <input type="number" class="form-control form-control-sm no-print area-field" 
                name="items[${serial}][area]" step="0.01" value="0.00" required>
          <span class="print-only">0.00</span>
        </td>
        <td>
          <input type="number" class="form-control form-control-sm no-print qty-field" 
                name="items[${serial}][quantity]" value="0">
          <span class="print-only">0</span>
        </td>
        <td>
          <input type="number" class="form-control form-control-sm no-print rate-field" 
                name="items[${serial}][rate]" step="0.01" value="0.00" required>
          <span class="print-only">0.00</span>
        </td>
        <td>
          <input type="number" class="form-control form-control-sm no-print amount-field" 
                name="items[${serial}][amount]" readonly value="0.00">
          <span class="print-only">0.00</span>
        </td>
      `;
        // Insert the new row after the 6 predefined items (as row 7, 8, ...)
        const insertIndex = predefinedCount;
        if (tbody.rows.length > insertIndex) {
          tbody.insertBefore(newRow, tbody.rows[insertIndex]);
        } else {
          tbody.appendChild(newRow);
        }
        setupRowCalculations(newRow);
        // Update serial numbers for all custom item rows
        const rows = tbody.querySelectorAll('tr');
        for (let i = predefinedCount; i < rows.length; i++) {
          const serialCell = rows[i].querySelector('td:first-child b');
          if (serialCell) {
            serialCell.textContent = i + 1;
          }
        }
      });

      function setupRowCalculations(row) {
        const areaInput = row.querySelector('.area-field');
        const qtyInput = row.querySelector('.qty-field');
        const rateInput = row.querySelector('.rate-field');
        const amountInput = row.querySelector('.amount-field');
        const unitSelect = row.querySelector('select[name*="unit"]');

        function calculateAmount() {
          const areaVal = parseFloat(areaInput.value) || 0;
          const rateVal = parseFloat(rateInput.value) || 0;
          const qtyVal = parseFloat(qtyInput.value) || 0;
          let amount = 0;
          const unit = unitSelect ? unitSelect.value : 'Sft';

          if (unit === 'Sft') amount = areaVal * rateVal;
          else if (unit === 'Pcs') amount = qtyVal * rateVal;
          else if (unit === 'Ls') amount = rateVal;

          amountInput.value = amount.toFixed(2);
          updatePrintDisplay(row);
          calculateTotal();
        }

        if (areaInput) areaInput.addEventListener('input', calculateAmount);
        if (rateInput) rateInput.addEventListener('input', calculateAmount);
        if (qtyInput) qtyInput.addEventListener('input', calculateAmount);
        if (unitSelect) unitSelect.addEventListener('change', calculateAmount);
      }

      function updatePrintDisplay(row) {
        const inputs = row.querySelectorAll('input[type="text"], input[type="number"], select');
        const printSpans = row.querySelectorAll('.print-only');

        inputs.forEach((input, index) => {
          if (index < printSpans.length) {
            if (input.type === 'number') {
              printSpans[index].textContent = parseFloat(input.value).toFixed(2);
            } else {
              printSpans[index].textContent = input.value;
            }
          }
        });
      }

      document.querySelectorAll('#quotationItems tr').forEach(row => {
        setupRowCalculations(row);
      });

      function calculateTotal() {
        let total = 0;
        document.querySelectorAll('.amount-field').forEach(input => {
          const row = input.closest('tr');
          const description = row.querySelector('input[name*="description"]');
          if (!description || description.value !== 'Quantity of Windows') {
            total += parseFloat(input.value) || 0;
          }
        });
        document.getElementById('quotationTotal').textContent = total.toFixed(2);
        document.getElementById('totalAmountInput').value = total.toFixed(2);
      }

      document.querySelectorAll('.rate-field').forEach(rateInput => {
        const row = rateInput.closest('tr');
        const areaInput = row.querySelector('input[name*="area"]');
        const amountInput = row.querySelector('input[name*="amount"]');
        const areaVal = parseFloat(areaInput.value) || 0;
        const rateVal = parseFloat(rateInput.value) || 0;
        amountInput.value = (areaVal * rateVal).toFixed(2);
      });

      calculateTotal();

      const windowTypesInput = document.getElementById('windowTypesInput');
      const selectedWindowTypes = new Set();

      function handleWindowTypeSelection(btn) {
        const windowId = btn.dataset.id;
        const img = document.getElementById(`img-${windowId}`);
        const printElement = document.getElementById(`print-${windowId}`);

        if (selectedWindowTypes.has(windowId)) {
          selectedWindowTypes.delete(windowId);
          btn.classList.remove('selected');
          if (img) img.style.display = 'none';
          if (printElement) printElement.style.display = 'none';
        } else {
          selectedWindowTypes.add(windowId);
          btn.classList.add('selected');
          if (img) img.style.display = 'block';
          if (printElement) printElement.style.display = 'block';
        }

        windowTypesInput.value = Array.from(selectedWindowTypes).join(',');
      }

      // Initialize button event listeners
      document.querySelectorAll('.window-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          handleWindowTypeSelection(this);
        });
      });

      // [Rest of your existing code...]

      // Add validation for quotation save
      const quotationForm = document.getElementById('quotationForm');
      if (quotationForm) {
        quotationForm.addEventListener('submit', function(e) {
          // Only validate client selection if the save_quotation button was clicked
          if (e.submitter && e.submitter.name === 'save_quotation') {
            const clientSelect = document.getElementById('clientSelect');
            if (clientSelect && !clientSelect.value) {
              e.preventDefault();
              // Remove any previous alert
              document.querySelectorAll('.client-alert').forEach(el => el.remove());
              // Insert alert above the form
              const alert = document.createElement('div');
              alert.className = 'alert alert-danger client-alert';
              alert.innerHTML = '<strong>Error:</strong> Please select a client before saving the quotation.';
              quotationForm.parentNode.insertBefore(alert, quotationForm);
              clientSelect.focus();
            }
          }
        });
      }
    });

    // REVISED PRINT FUNCTION
    function printQuotation() {
      // Use the quotation number from the DOM, not a new one
      const quotationNumber = document.querySelector('.quotation-meta').textContent.split("|")[0].trim();
      const printContents = document.querySelector('.quotation-container').cloneNode(true);

      printContents.querySelectorAll('.no-print').forEach(el => el.remove());

      const windowTypesContainer = printContents.querySelector('.window-types-section');
      if (windowTypesContainer) {
        let selectedBtns = Array.from(document.querySelectorAll('.window-btn.selected'));
        let gridHTML = '<h3 style="text-align:center; margin-bottom:18px; font-size:20px; letter-spacing:2px; color:#2c3e50;">Selected Window Types</h3>';
        if (selectedBtns.length > 0) {
          gridHTML += '<div class="window-types-grid-print-modern">';
          selectedBtns.forEach((btn, idx) => {
            const windowId = btn.dataset.id;
            let imgSrc = document.querySelector(`#img-${windowId}`).src;
            if (imgSrc && !imgSrc.startsWith('http')) {
              imgSrc = window.location.origin + '/' + imgSrc.replace(/^\//, '');
            }
            const label = btn.textContent.trim();
            gridHTML += `
              <div class="window-type-print-modern">
                  <img src="${imgSrc}" alt="${label}">
                  <div class="window-type-label-modern">${label}</div>
              </div>
            `;
          });
          gridHTML += '</div>';
        } else {
          gridHTML += '<div style="text-align:center; color:#888;">No window types selected.</div>';
        }
        windowTypesContainer.innerHTML = gridHTML;
      }

      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="en">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>${quotationNumber} - Quotation</title>
          <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
          <style>
            body {
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
              background: #fff;
              margin: 0;
              padding: 0;
              color: #222;
            }
            .print-main-container {
              width: 800px;
              margin: 0 auto;
              background: #fff;
              border: 1px solid #e0e0e0;
              box-shadow: 0 0 10px rgba(0,0,0,0.07);
              padding: 40px 32px 32px 32px;
              page-break-after: avoid;
              min-height: 100vh;
            }
            .quotation-header {
              text-align: center;
              margin-bottom: 32px;
              border-bottom: 2px solid #2c3e50;
              padding-bottom: 18px;
            }
            .quotation-logo {
              width: 80px;
              height: 80px;
              object-fit: contain;
              margin-bottom: 8px;
            }
            .quotation-title {
              font-size: 32px;
              font-weight: bold;
              color: #2c3e50;
              margin-bottom: 2px;
              letter-spacing: 2px;
              text-transform: uppercase;
            }
            .quotation-meta {
              font-size: 16px;
              color: #444;
              margin-bottom: 4px;
            }
            .quotation-type-label {
              font-size: 20px;
              letter-spacing: 4px;
              color: #2c3e50;
              margin-bottom: 0;
            }
            .quotation-table, .window-summary-table, .table-bordered {
              width: 100%;
              border-collapse: collapse;
              margin-bottom: 24px;
              background: #fff;
            }
            .quotation-table th, .window-summary-table th, .table-bordered th {
              background: #2c3e50 !important;
              color: #fff !important;
              padding: 12px 14px;
              text-align: left;
              font-size: 16px;
              border: 1px solid #e0e0e0;
            }
            .quotation-table td, .window-summary-table td, .table-bordered td {
              padding: 12px 14px;
              border: 1px solid #e0e0e0;
              font-size: 15px;
              background: #fcfcfc;
            }
            .quotation-table tbody tr:nth-child(even), .window-summary-table tbody tr:nth-child(even), .table-bordered tbody tr:nth-child(even) {
              background: #f5f8fa;
            }
            .total-row, .summary-total-row {
              font-weight: bold;
              background: #eaf4ff !important;
            }
            .text-right {
              text-align: right;
            }
            .window-specs-section h4 {
              color: #2c3e50;
              margin-bottom: 10px;
              font-size: 18px;
            }
            .terms-conditions, .note-section {
              margin-top: 24px;
              font-size: 15px;
              line-height: 1.7;
              border-top: 1px solid #eee;
              padding-top: 14px;
            }
            .note-section {
              border: none;
              color: #2c3e50;
              font-weight: 500;
              margin-bottom: 0;
            }
            /* Modern window types grid: 3 per row, centered */
            .window-types-grid-print-modern {
              display: flex;
              flex-wrap: wrap;
              gap: 32px 24px;
              margin: 0 auto 0 auto;
              justify-content: center;
              max-width: 700px;
            }
            .window-type-print-modern {
              display: flex;
              flex-direction: column;
              align-items: center;
              background: #f8f9fa;
              box-shadow: 0 2px 8px #0001;
              border: 1px solid #bbb;
              border-radius: 10px;
              padding: 18px 18px 12px 18px;
              margin-bottom: 0;
              width: 180px;
              min-height: 210px;
              max-width: 220px;
              margin-top: 0;
              flex: 0 1 calc(33.333% - 24px); /* 3 per row, minus gap */
            }
            .window-type-print-modern img {
              max-width: 120px;
              max-height: 120px;
              border: 1px solid #bbb;
              border-radius: 6px;
              margin-bottom: 10px;
              background: #fff;
              box-shadow: 0 1px 4px #0001;
            }
            .window-type-label-modern {
              font-size: 15px;
              font-weight: bold;
              color: #2c3e50;
              margin-top: 8px;
              text-align: center;
              letter-spacing: 1px;
            }
            @media print {
              @page {
                size: A4;
                margin: 12mm;
              }
              body {
                background: #fff !important;
                color: #000 !important;
              }
              .print-main-container {
                box-shadow: none !important;
                border: none !important;
                margin: 0 auto !important;
                padding: 32px 32px 32px 32px !important;
                width: 800px !important;
                min-width: 0 !important;
                max-width: 100% !important;
              }
              .window-types-grid-print-modern {
                max-width: 100% !important;
                gap: 24px 16px !important;
                grid-template-columns: repeat(3, 1fr) !important;
                justify-items: center !important;
              }
              .window-type-print-modern {
                width: 180px !important;
                min-height: 210px !important;
                max-width: 220px !important;
                padding: 14px 10px 10px 10px !important;
                flex: 0 1 calc(33.333% - 16px) !important;
              }
              .window-type-print-modern img {
                max-width: 110px !important;
                max-height: 110px !important;
              }
            }
          </style>
        </head>
        <body>
          <div class="print-main-container">
            ${printContents.innerHTML}
          </div>
        </body>
        </html>
      `);
      printWindow.document.close(); // Ensure the document is finished writing
      printWindow.focus();
      printWindow.onload = function() {
        printWindow.print();
        printWindow.onafterprint = function() { printWindow.close(); };
      };

      // Fix client/company info: two columns, side by side
      const origInfo = printContents.querySelector('.quotation-info');
      if (origInfo) {
        // Get client info
        const clientName = printContents.querySelector('#clientNameDisplay')?.textContent || 'Client Name';
        const clientAddress = printContents.querySelector('#clientAddressDisplay')?.textContent || 'Client Address';
        const clientPhone = printContents.querySelector('#clientPhoneDisplay')?.textContent || 'Phone Number';
        // Get company info fields
        let companyName = 'Company';
        let contactPerson = 'Contact Person';
        let contactNumber = 'Contact Number';
        let officeContact = 'Office Contact';
        const companyInfoDiv = printContents.querySelector('.company-info.print-only');
        if (companyInfoDiv) {
          const lines = companyInfoDiv.innerText.split('\n');
          if (lines[0]) companyName = lines[0].replace('Company:', '').trim();
          if (lines[1]) contactPerson = lines[1].replace('Contact Person:', '').trim();
          if (lines[2]) contactNumber = lines[2].replace('Contact Number:', '').trim();
          if (lines[3]) officeContact = lines[3].replace('Office Contact:', '').trim();
        }
        // Build new HTML with requested design
        const infoHTML = `
          <div style="display: flex; justify-content: space-between; gap: 40px; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
            <div style="width: 48%; min-width: 220px; font-size: 15px;">
              <div style="margin-bottom: 8px;"><strong>Name:</strong> <span style="font-weight: normal;">${clientName}</span></div>
              <div style="margin-bottom: 8px;"><strong>Address:</strong> <span style="font-weight: normal;">${clientAddress}</span></div>
              <div><strong>Phone:</strong> <span style="font-weight: normal;">${clientPhone}</span></div>
            </div>
            <div style="width: 48%; min-width: 220px; font-size: 15px;">
              <div style="margin-bottom: 8px;"><strong>Company:</strong> <span style="font-weight: normal;">${companyName}</span></div>
              <div style="margin-bottom: 8px;"><strong>Contact Person:</strong> <span style="font-weight: normal;">${contactPerson}</span></div>
              <div style="margin-bottom: 8px;"><strong>Contact Number:</strong> <span style="font-weight: normal;">${contactNumber}</span></div>
              <div><strong>Office Contact:</strong> <span style="font-weight: normal;">${officeContact}</span></div>
            </div>
          </div>
        `;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = infoHTML;
        origInfo.parentNode.replaceChild(tempDiv.firstElementChild, origInfo);
      }
    }
  </script>
</body>

</html>