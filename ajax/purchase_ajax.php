<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php';

$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['status' => 'error', 'message' => 'Invalid request'];

switch ($action) {
    case 'get_suppliers':
        $suppliers = [];
        $result = $conn->query("SELECT supplier_id, name, phone FROM suppliers ORDER BY name ASC");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $suppliers[] = $row;
            }
        }
        $response = [
            'status' => 'success',
            'suppliers' => $suppliers
        ];
        break;
        
    case 'get_invoice_no':
        // Generate dynamic invoice number (year + sequential number)
        $current_year = date('Y');
        $result = $conn->query("SELECT MAX(invoice_no) as last_invoice FROM purchases WHERE invoice_no LIKE 'INV-$current_year-%'");
        $last_invoice = $result->fetch_assoc()['last_invoice'];
        
        if ($last_invoice) {
            $last_number = intval(substr($last_invoice, -3));
            $next_number = str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $next_number = '001';
        }
        
        $invoice_no = "INV-$current_year-$next_number";
        $response = [
            'status' => 'success',
            'invoice_no' => $invoice_no
        ];
        break;
        
    case 'save':
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $purchase_date = trim($_POST['purchase_date'] ?? '');
        $total_amount = floatval($_POST['total_amount'] ?? 0);
        $paid_amount = floatval($_POST['paid_amount'] ?? 0);
        $due_amount = floatval($_POST['due_amount'] ?? 0);
        $product_names = $_POST['product_name'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $unit_prices = $_POST['unit_price'] ?? [];

        if ($supplier_id <= 0 || !$invoice_no || !$purchase_date || $total_amount <= 0 || empty($product_names)) {
            $response = ['status' => 'error', 'message' => 'Please fill all required fields and add at least one product.'];
            break;
        }

        $conn->begin_transaction();
        try {
            // Insert into purchases
            $stmt = $conn->prepare("INSERT INTO purchases (invoice_no, supplier_id, purchase_date, total_amount, paid_amount, due_amount) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sissdd', $invoice_no, $supplier_id, $purchase_date, $total_amount, $paid_amount, $due_amount);
            if (!$stmt->execute()) throw new Exception('Failed to save purchase.');
            $purchase_id = $stmt->insert_id;
            $stmt->close();

            // Insert purchase details
            $stmt = $conn->prepare("INSERT INTO purchase_details (purchase_id, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($product_names); $i++) {
                $name = trim($product_names[$i]);
                $qty = intval($quantities[$i] ?? 0);
                $price = floatval($unit_prices[$i] ?? 0);
                $line_total = $qty * $price;
                if ($name && $qty > 0 && $price >= 0) {
                    $stmt->bind_param('isidd', $purchase_id, $name, $qty, $price, $line_total);
                    if (!$stmt->execute()) throw new Exception('Failed to save product row.');
                }
            }
            $stmt->close();
            $conn->commit();
            
            // Insert into supplier_ledger
            $description = 'Purchase Invoice #' . $invoice_no;
            $debit = $total_amount; // The amount owed to supplier
            $credit = 0;
            $balance = 0; // Will calculate below
            // Get last balance for this supplier
            $balRes = $conn->query("SELECT balance FROM supplier_ledger WHERE supplier_id = $supplier_id ORDER BY ledger_id DESC LIMIT 1");
            if ($balRes && $balRes->num_rows > 0) {
                $last = $balRes->fetch_assoc();
                $balance = $last['balance'] + $debit - $credit;
            } else {
                $balance = $debit - $credit;
            }
            $stmt_ledger = $conn->prepare("INSERT INTO supplier_ledger (supplier_id, date, description, debit, credit, balance, related_purchase_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt_ledger->bind_param('issddds', $supplier_id, $purchase_date, $description, $debit, $credit, $balance, $purchase_id);
            $stmt_ledger->execute();
            $stmt_ledger->close();
            
            // Generate next invoice number for immediate reuse
            $current_year = date('Y');
            $result = $conn->query("SELECT MAX(invoice_no) as last_invoice FROM purchases WHERE invoice_no LIKE 'INV-$current_year-%'");
            $last_invoice = $result->fetch_assoc()['last_invoice'];
            $last_number = intval(substr($last_invoice, -3));
            $next_invoice = "INV-$current_year-" . str_pad($last_number + 1, 3, '0', STR_PAD_LEFT);
            
            $response = [
                'status' => 'success', 
                'message' => 'Purchase saved successfully.',
                'next_invoice' => $next_invoice,
                'purchase_id' => $purchase_id
            ];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['status' => 'error', 'message' => $e->getMessage()];
        }
        break;
        
    case 'get_purchases':
        $purchases = [];
        $sql = "SELECT p.purchase_id, p.invoice_no, s.name AS supplier, p.purchase_date, p.total_amount, p.paid_amount, p.due_amount 
                FROM purchases p 
                JOIN suppliers s ON p.supplier_id = s.supplier_id 
                ORDER BY p.purchase_date DESC, p.purchase_id DESC";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['purchase_date'] = date('d M Y', strtotime($row['purchase_date']));
                $row['total_amount'] = number_format($row['total_amount'], 2);
                $row['paid_amount'] = number_format($row['paid_amount'], 2);
                $row['due_amount'] = number_format($row['due_amount'], 2);
                $purchases[] = $row;
            }
        }
        $response = [
            'status' => 'success',
            'purchases' => $purchases
        ];
        break;
        
    case 'delete':
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        if ($purchase_id <= 0) {
            $response = ['status' => 'error', 'message' => 'Invalid purchase.'];
            break;
        }
        
        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM purchase_details WHERE purchase_id=$purchase_id");
            $conn->query("DELETE FROM purchases WHERE purchase_id=$purchase_id");
            $conn->commit();
            $response = ['status' => 'success', 'message' => 'Purchase deleted successfully.'];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ['status' => 'error', 'message' => 'Failed to delete purchase.'];
        }
        break;
        
    case 'view_invoice':
        $purchase_id = intval($_POST['purchase_id'] ?? 0);
        if ($purchase_id <= 0) {
            $response = ['status' => 'error', 'html' => '<div class="text-danger">Invalid purchase.</div>'];
            break;
        }
        
        // Fetch purchase with supplier details
        $sql = "SELECT p.*, s.name AS supplier_name, s.phone AS supplier_phone, 
                s.address AS supplier_address, s.email AS supplier_email 
                FROM purchases p 
                JOIN suppliers s ON p.supplier_id = s.supplier_id 
                WHERE p.purchase_id = $purchase_id";
        $purchase = $conn->query($sql)->fetch_assoc();
        
        if (!$purchase) {
            $response = ['status' => 'error', 'html' => '<div class="text-danger">Purchase not found.</div>'];
            break;
        }
        
        // Format dates and amounts
        $purchase['purchase_date'] = date('d M Y', strtotime($purchase['purchase_date']));
        $purchase['total_amount'] = number_format($purchase['total_amount'], 2);
        $purchase['paid_amount'] = number_format($purchase['paid_amount'], 2);
        $purchase['due_amount'] = number_format($purchase['due_amount'], 2);
        
        // Fetch products
        $products = [];
        $result = $conn->query("SELECT * FROM purchase_details WHERE purchase_id = $purchase_id");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['unit_price'] = number_format($row['unit_price'], 2);
                $row['line_total'] = number_format($row['line_total'], 2);
                $products[] = $row;
            }
        }
        
        ob_start();
        ?>
        <div id="printable-invoice" style="background:#fff; border-radius:10px; box-shadow:0 2px 12px rgba(0,0,0,0.08); padding:32px; max-width:900px; margin:40px auto; display: flex; flex-direction: column; align-items: center;">
          <div style="width:100%;">
          <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 print-header">
            <div class="d-flex align-items-center">
              <img src="logo/mod.jpg" alt="Logo" style="height:70px;width:auto;margin-right:18px;">
              <div>
                <h2 class="mb-0" style="font-weight:700; color:#3498db;">Modern Home <span style='color:#222'>Decore</span></h2>
                <div style="font-size:16px; color:#888;">Purchase Invoice</div>
              </div>
            </div>
            <button class="btn btn-outline-primary d-print-none" onclick="printInvoice()">
              <i class="fas fa-print"></i> Print
            </button>
          </div>

          <!-- Enhanced Supplier/Client Details -->
          <div class="row mb-3">
            <div class="col-12">
              <div style="background:linear-gradient(90deg,#f8f9fa 60%,#e3eafc 100%); border:1.5px solid #3498db; border-radius:10px; box-shadow:0 2px 6px rgba(52,152,219,0.06); padding:10px 14px; max-width:820px; margin:auto;">
                <div class="row align-items-center">
                  <div class="col-md-6 mb-2 mb-md-0" style="border-right:1px solid #e0e7ef;">
                    <div style="font-size:14.5px; font-weight:600; color:#3498db; margin-bottom:5px; letter-spacing:0.2px;"><span class='fa fa-user-tie me-2'></span>Supplier</div>
                    <div style="font-size:12px; color:#222; margin-bottom:1px;"><span class='fa fa-user me-1'></span> <strong><?= htmlspecialchars($purchase['supplier_name']) ?></strong></div>
                    <div style="font-size:11.5px; color:#444; margin-bottom:1px;"><span class='fa fa-phone me-1'></span> <?= htmlspecialchars($purchase['supplier_phone']) ?></div>
                    <div style="font-size:11.5px; color:#444; margin-bottom:1px;"><span class='fa fa-map-marker-alt me-1'></span> <?= htmlspecialchars($purchase['supplier_address']) ?></div>
                    <div style="font-size:11.5px; color:#444;"><span class='fa fa-envelope me-1'></span> <?= htmlspecialchars($purchase['supplier_email']) ?></div>
                  </div>
                  <div class="col-md-6 ps-md-3 pt-2 pt-md-0 text-md-end text-start">
                    <div style="font-size:14.5px; font-weight:600; color:#3498db; margin-bottom:5px; letter-spacing:0.2px;"><span class='fa fa-file-invoice me-2'></span>Invoice</div>
                    <div style="font-size:12px; color:#222; margin-bottom:1px;"><span class='fa fa-hashtag me-1'></span> <strong><?= htmlspecialchars($purchase['invoice_no']) ?></strong></div>
                    <div style="font-size:11.5px; color:#444; margin-bottom:1px;"><span class='fa fa-calendar-alt me-1'></span> <?= htmlspecialchars($purchase['purchase_date']) ?></div>
                    <div style="font-size:11.5px; color:#444;"><span class='fa fa-id-badge me-1'></span> Reference ID: #<?= $purchase_id ?></div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-bordered invoice-table" style="background:#fafbfc;">
              <thead class="table-light">
                <tr style="background:#f4f8fb;">
                  <th style="width:40px;">#</th>
                  <th>Product Name</th>
                  <th>Quantity</th>
                  <th>Unit Price</th>
                  <th>Line Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($products as $i => $prod): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($prod['product_name']) ?></td>
                    <td><?= $prod['quantity'] ?></td>
                    <td>Rs <?= $prod['unit_price'] ?></td>
                    <td>Rs <?= $prod['line_total'] ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <div class="row g-3 justify-content-end">
            <div class="col-md-5">
              <table class="table table-borderless mb-0 invoice-totals">
                <tr><th style="width:50%">Total Amount:</th><td>Rs <?= $purchase['total_amount'] ?></td></tr>
                <tr><th>Paid Amount:</th><td>Rs <?= $purchase['paid_amount'] ?></td></tr>
                <tr><th>Due Amount:</th><td>Rs <?= $purchase['due_amount'] ?></td></tr>
              </table>
            </div>
          </div>
          
          <div class="text-center mt-4" style="font-size:15px;color:#888;">
            Thank you for your business!<br>
            <small>Invoice generated on <?= date('d M Y h:i A') ?></small>
          </div>
          </div>
        </div>
        
        <style>
        @media print {
          @page {
            size: A4;
            margin: 20mm 20mm 20mm 20mm;
          }
          body { 
            background: #fff !important; 
            padding: 0 !important;
            margin: 0 !important;
            font-size: 12pt;
          }
          #invoiceModal .modal-content { 
            box-shadow: none !important; 
            border: none !important; 
            padding: 0 !important;
            margin: 0 !important;
          }
          .d-print-none { display: none !important; }
          #printable-invoice { 
            box-shadow: none !important; 
            border: none !important; 
            margin: 0 auto !important; 
            padding: 20px 30px 20px 30px !important;
            max-width: 800px !important; 
            width: 100% !important;
            display: flex;
            flex-direction: column;
            align-items: center;
          }
          .print-header { border-bottom: 2px solid #3498db !important; }
          .invoice-table th, .invoice-table td { 
            font-size: 10pt !important; 
            padding: 6px 4px !important; 
          }
          .invoice-totals th, .invoice-totals td { 
            font-size: 10pt !important; 
            padding: 6px 4px !important;
          }
          .invoice-table { 
            border: 1px solid #ddd !important; 
            width: 100% !important;
            margin-bottom: 10pt !important;
          }
        }
        #printable-invoice { 
          font-family: 'Segoe UI', Arial, sans-serif; 
          margin: 40px auto;
          padding: 32px;
          max-width: 900px;
          display: flex;
          flex-direction: column;
          align-items: center;
        }
        .invoice-table th, .invoice-table td { 
          font-size: 14px; 
        }
        .invoice-totals th, .invoice-totals td { 
          font-size: 14px; 
        }
        .print-header { 
          border-bottom: 2px solid #3498db; 
        }
        .invoice-table { 
          border: 1.5px solid #3498db; 
        }
        </style>
        
        <script>
        function printInvoice() {
          var printContents = document.getElementById('printable-invoice').innerHTML;
          var printWindow = window.open('', '_blank', 'width=900,height=650');
          
          printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
              <title>Invoice <?= $purchase['invoice_no'] ?></title>
              <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
              <style>
                @page { size: A4; margin: 20mm 20mm 20mm 20mm; }
                body { font-family: Arial, sans-serif; font-size: 12pt; background: #fff; margin: 0; padding: 0; }
                #printable-invoice {
                  background: #fff;
                  border-radius: 10px;
                  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
                  padding: 32px;
                  max-width: 900px;
                  margin: 40px auto;
                  display: flex;
                  flex-direction: column;
                  align-items: center;
                }
                .print-header { border-bottom: 2px solid #3498db; }
                .invoice-table { border: 1.5px solid #3498db; width: 100%; margin-bottom: 10pt; }
                .invoice-table th, .invoice-table td { font-size: 14px; padding: 6px 4px; }
                .invoice-totals th, .invoice-totals td { font-size: 14px; padding: 6px 4px; }
                .d-print-none { display: none !important; }
              </style>
            </head>
            <body>
              <div id="printable-invoice">
                ${printContents}
              </div>
              <script>
                setTimeout(function() {
                  window.print();
                  window.close();
                }, 200);
              <\/script>
            </body>
            </html>
          `);
          
          printWindow.document.close();
        }
        </script>
        <?php
        $response = [
            'status' => 'success',
            'html' => ob_get_clean()
        ];
        break;
        
    default:
        $response['message'] = 'Unknown action';
}

echo json_encode($response);
?>