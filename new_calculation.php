<?php
// Start session and initialize variables
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize session variables
if (!isset($_SESSION['quotation_items'])) {
    $_SESSION['quotation_items'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['start_new_calculation'])) {
        $_SESSION['calculation_started'] = true;
        $_SESSION['quotation_items'] = [];
        $_SESSION['current_order_id'] = uniqid('order_');
        echo json_encode(['success' => true, 'message' => 'New calculation session started']);
        exit;
    }
    
    if (isset($_POST['add_to_quotation'])) {
        $calculationData = json_decode($_POST['calculation_data'], true);
        if (!empty($calculationData['client_id'])) {
            $_SESSION['selected_client_id'] = $calculationData['client_id'];
        }
        $_SESSION['quotation_items'][] = $calculationData;
        echo json_encode([
            'success' => true, 
            'message' => 'Added to quotation!',
            'quotation_count' => count($_SESSION['quotation_items']),
            'item_html' => generateQuotationItemHtml(end($_SESSION['quotation_items']), count($_SESSION['quotation_items']) - 1),
            'total_cost' => calculateTotalCost()
        ]);
        exit;
    }
    
    if (isset($_POST['remove_quotation_item'])) {
        $index = (int)$_POST['index'];
        if (isset($_SESSION['quotation_items'][$index])) {
            unset($_SESSION['quotation_items'][$index]);
            $_SESSION['quotation_items'] = array_values($_SESSION['quotation_items']);
            echo json_encode([
                'success' => true, 
                'message' => 'Item removed from quotation',
                'quotation_count' => count($_SESSION['quotation_items']),
                'total_cost' => calculateTotalCost()
            ]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    if (isset($_POST['clear_quotation'])) {
        $_SESSION['quotation_items'] = [];
        unset($_SESSION['calculation_started']);
        unset($_SESSION['current_order_id']);
        echo json_encode(['success' => true, 'message' => 'Quotation cleared']);
        exit;
    }
    
    if (isset($_POST['go_to_quotation'])) {
        if (!empty($_SESSION['quotation_items'])) {
            echo json_encode(['success' => true, 'redirect' => '/decore/Pages/quotations.php']);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'No items in quotation']);
        exit;
    }
}

// Helper functions
function generateQuotationItemHtml($item, $index) {
    ob_start();
    ?>
    <div class="quotation-item" data-index="<?= $index ?>">
        <div class="quotation-header">
            <div class="quotation-title">
                <?= htmlspecialchars($item['window_type'] ?? 'Unknown') ?> - 
                <?= htmlspecialchars($item['description'] ?? '') ?>
            </div>
            <div class="d-flex align-items-center">
                <span class="quotation-cost me-3">
                    RS<?= number_format($item['amount'] ?? 0, 2) ?>
                </span>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeQuotationItem(<?= $index ?>)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="quotation-details">
            <small class="text-muted">
                Area: <?= $item['area'] ?? 'N/A' ?> sq ft | 
                Rate: RS<?= number_format($item['rate'] ?? 0, 2) ?> | 
                Quantity: <?= $item['quantity'] ?? 1 ?>
                <?php if (isset($item['width']) && isset($item['height'])): ?>
                    | Dimensions: <?= $item['width'] ?> × <?= $item['height'] ?> ft
                <?php endif; ?>
            </small>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function calculateTotalCost() {
    $total = 0;
    foreach ($_SESSION['quotation_items'] as $item) {
        $total += $item['amount'] ?? 0;
    }
    return number_format($total, 2);
}

require_once 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Product Calculation</title>
    <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .toast {
            opacity: 1 !important;
        }
        .bg-success {
            background-color: rgb(141, 196, 231) !important;
        }
        .bg-danger {
            background-color: #dc3545 !important;
        }
        .client-search-container {
            position: relative;
        }
        .client-search-input {
            padding-right: 35px;
        }
        .client-search-clear {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .client-search-clear:hover {
            color: #dc3545;
        }
        .client-dropdown-container {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            display: none;
            position: absolute;
            width: 100%;
            z-index: 1000;
            background: white;
        }
        .client-dropdown-container.show {
            display: block;
        }
        .client-dropdown-item {
            padding: 8px 16px;
            cursor: pointer;
        }
        .client-dropdown-item:hover {
            background-color: #f8f9fa;
        }
        .quotation-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .quotation-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border: 1px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .quotation-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .quotation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .quotation-title {
            font-weight: 600;
            color: #495057;
        }
        .quotation-cost {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1em;
        }
        .quotation-summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            height: fit-content;
            position: sticky;
            top: 20px;
        }
        .quotation-summary-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .quotation-summary-header h6 {
            margin: 0;
            color: white;
            font-weight: 600;
        }
        .quotation-summary-content > div {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .quotation-summary-content > div:last-child {
            border-bottom: none;
        }
        .cost-label, .number-label, .count-label {
            font-weight: 500;
            opacity: 0.9;
        }
        .cost-amount {
            font-size: 1.8em;
            font-weight: bold;
            color: #00ff88;
            text-shadow: 0 0 15px rgba(0, 255, 136, 0.5);
            background: linear-gradient(45deg, #00ff88, #00cc6a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: glow 2s ease-in-out infinite alternate;
        }
        .number-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #ffd700;
            text-shadow: 0 0 12px rgba(255, 215, 0, 0.6);
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .count-value {
            font-size: 1.3em;
            font-weight: bold;
            color: #00d4ff;
            text-shadow: 0 0 12px rgba(0, 212, 255, 0.6);
            background: linear-gradient(45deg, #00d4ff, #0099cc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        @keyframes glow {
            from {
                text-shadow: 0 0 15px rgba(0, 255, 136, 0.5);
            }
            to {
                text-shadow: 0 0 25px rgba(0, 255, 136, 0.8), 0 0 35px rgba(0, 255, 136, 0.4);
            }
        }
        .quotation-actions {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        .total-quotation-cost {
            font-size: 1.5em;
            font-weight: bold;
            color: #28a745;
            margin: 10px 0;
        }
        .calculator-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        .calculator-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .add-new-calculation-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .add-new-calculation-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            color: white;
        }
        .calculation-container {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            background: #f8f9fa;
        }
        .calculation-header {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .calculation-number {
            font-weight: bold;
            font-size: 16px;
        }
        .remove-calculation-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
        }
        .remove-calculation-btn:hover {
            background: #c82333;
        }
        .session-popup {
            animation: fadeInDown 0.4s ease-out;
        }
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        /* Custom popup animation */
        @keyframes slideInRightFade {
          from { opacity: 0; transform: translateX(60px); }
          to   { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideOutRightFade {
          from { opacity: 1; transform: translateX(0); }
          to   { opacity: 0; transform: translateX(60px); }
        }
        .session-popup.custom-popup-animation {
          animation: slideInRightFade 0.5s cubic-bezier(0.4,0,0.2,1);
        }
        .session-popup.custom-popup-hide {
          animation: slideOutRightFade 0.7s cubic-bezier(0.4,0,0.2,1) forwards;
        }
        .summary-attractive-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 2px 12px rgba(52,152,219,0.08);
            padding: 18px 24px;
            margin-bottom: 18px;
            transition: box-shadow 0.2s;
        }
        .summary-attractive-card:hover {
            box-shadow: 0 6px 24px rgba(52,152,219,0.12);
        }
        .summary-row-flex {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            align-items: center;
            justify-content: space-between;
        }
        .summary-label {
            color: #7b8a99;
            font-weight: 500;
            margin-left: 8px;
        }
        .summary-value {
            font-weight: bold;
            font-size: 1.2em;
            margin-left: 8px;
        }
        .cost-green {
            color: #27ae60;
        }
        .quote-gold {
            color: #e1b200;
        }
        .calc-blue {
            color: #2980b9;
        }
        .items-purple {
            color: #8e44ad;
        }
        .summary-icon {
            font-size: 1.2em;
            opacity: 0.8;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <h3 class="mb-4"><i class="fas fa-calculator text-primary me-2"></i>Multi-Product Calculation</h3>

    <?php if (isset($_SESSION['calculation_started'])): ?>
        <div class="alert alert-success text-center alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>
            Order session started (ID: <?= $_SESSION['current_order_id'] ?>). Add calculations to build your quotation.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Heading and Buttons -->
    <div class="row g-3 mb-4 align-items-end">
        <!-- New Order Button -->
        <div class="col-md-2">
            <button type="button" id="startNewCalculation" class="btn btn-primary w-100">
                <i class="fas fa-plus"></i> New Order Session
            </button>
        </div>

        <div class="col-md-2" id="goToQuotationBtn" style="<?= empty($_SESSION['quotation_items']) ? 'display: none;' : '' ?>">
            <button type="button" class="btn btn-success w-100" onclick="submitQuotationForm()">
                <i class="fas fa-shopping-cart"></i> Go to Quotation
            </button>
        </div>
        <div class="col-md-2" id="clearQuotationBtn" style="<?= empty($_SESSION['quotation_items']) ? 'display: none;' : '' ?>">
            <button type="button" class="btn btn-outline-danger w-100" onclick="clearQuotation()">
                <i class="fas fa-trash"></i> Clear All
            </button>
        </div>
    </div>

    <!-- Quotation Summary -->
    <div class="quotation-summary" id="quotationSummary" style="<?= empty($_SESSION['quotation_items']) ? 'display: none;' : '' ?>">
        <h5><i class="fas fa-list me-2"></i>Current Quotation Items</h5>
        
        <!-- Quotation Summary Info -->
        <div class="summary-attractive-card">
            <div class="summary-row-flex">
                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                    <i class="fas fa-money-bill-wave summary-icon cost-green"></i>
                    <span class="summary-label">Total Cost:</span>
                    <span class="summary-value cost-green" id="totalQuotationCost"><?= calculateTotalCost() ?> RS</span>
                </div>
                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                    <i class="fas fa-receipt summary-icon quote-gold"></i>
                    <span class="summary-label">Quotation #:</span>
                    <span class="summary-value quote-gold" id="quotationNumber"><?= $_SESSION['current_order_id'] ?? 'N/A' ?></span>
                </div>
                <div class="d-flex align-items-center flex-wrap mb-2 mb-md-0">
                    <i class="fas fa-calculator summary-icon calc-blue"></i>
                    <span class="summary-label">Calculation #:</span>
                    <span class="summary-value calc-blue" id="calculationNumber"><?= count($_SESSION['quotation_items']) ?></span>
                </div>
                <div class="d-flex align-items-center flex-wrap">
                    <i class="fas fa-list-ol summary-icon items-purple"></i>
                    <span class="summary-label">Total Quotation Value:</span>
                    <span class="summary-value items-purple" id="quotationCount"><?= count($_SESSION['quotation_items']) ?> item(s)</span>
                </div>
            </div>
        </div>
        
        <div id="quotationList">
            <?php 
            foreach ($_SESSION['quotation_items'] as $index => $item): 
                echo generateQuotationItemHtml($item, $index);
            endforeach; 
            ?>
        </div>
    </div>

    <!-- Calculator Section -->
    <div class="calculator-section">
        <div class="calculator-header">
            <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Calculator</h5>
            <?php if (isset($_SESSION['calculation_started'])): ?>
            <button type="button" class="add-new-calculation-btn" onclick="addNewCalculation()">
                <i class="fas fa-plus me-1"></i>Add New Calculation
            </button>
            <?php endif; ?>
        </div>

        <!-- Calculator Form (initially hidden if session started) -->
        <div id="calculatorForm" style="<?= isset($_SESSION['calculation_started']) ? 'display: none;' : '' ?>">
            <!-- Horizontal Selection -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label">Company</label>
                    <select class="form-select" id="companySelect" <?= isset($_SESSION['calculation_started']) ? '' : 'disabled' ?>>
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
                    <div class="client-search-container">
                        <input type="text" class="form-control client-search-input" id="clientSearchInput" placeholder="Search clients..." disabled>
                        <i class="fas fa-times client-search-clear" id="clientSearchClear" style="display: none;"></i>
                        <select class="form-select" id="clientSelect" style="display: none;">
                            <option value="">-- Select Client --</option>
                        </select>
                        <div class="client-dropdown-container" id="clientDropdown">
                            <!-- Client options will be populated here -->
                        </div>
                    </div>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        <i class="fas fa-plus me-1"></i> New Client
                    </button>
                </div>
            </div>

            <!-- Calculator Result -->
            <div id="calculatorDisplayArea" class="mt-4">
                <div class="alert alert-info">
                    Please select company, product type, and client to begin calculation.
                </div>
            </div>
        </div>

        <!-- Calculations Container -->
        <div id="calculationsContainer">
            <!-- Dynamic calculations will be added here -->
        </div>
    </div>
</div>

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="addClientForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
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
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Save Client</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    const productSubTypes = {
        window: ['2psl', '3psl', 'fixed', 'top_hung'],
        door: ['full_panel', 'half', 'openable', 'glass']
    };

    let allClients = [];
    let calculationCounter = 0;

    function formatName(str) {
        return str.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
    }

    // Initialize the system
    function init() {
        setupClientSearch();
        setupEventListeners();
        
        // Show calculator form if no calculations exist
        if ($('#calculationsContainer .calculation-container').length === 0 && $('#quotationList .quotation-item').length === 0) {
            $('#calculatorForm').show();
        }
    }

    // Setup all event listeners
    function setupEventListeners() {
        // Start new calculation session
        $('#startNewCalculation').click(function() {
            $.ajax({
                url: 'new_calculation.php',
                type: 'POST',
                data: { start_new_calculation: true },
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success) {
                    showSessionPopup(response.message);
                    // Reset and enable all form fields
                    $('#companySelect').val('').prop('disabled', false);
                    $('#productTypeSelect').val('').prop('disabled', true);
                    $('#productSubTypeSelect').val('').prop('disabled', true);
                    $('#clientSearchInput').val('').prop('disabled', true);
                    $('#clientSelect').val('');
                    $('#calculatorDisplayArea').html('<div class="alert alert-info">Please select company, product type, and client to begin calculation.</div>');
                    $('#calculationsContainer').empty();
                    $('#calculatorForm').show();
                    $('.alert-success').show();
                    $('#goToQuotationBtn, #clearQuotationBtn').hide();
                    $('#quotationSummary').hide();
                }
            })
            .fail(function() {
                showToast('Error starting new calculation', 'danger');
            });
        });

        // Company select change
        $('#companySelect').change(function () {
            const companyId = $(this).val();
            if (companyId) {
                $.post('set_selected_company.php', { company_id: companyId });
                $('#productTypeSelect').prop('disabled', false);
                
                $.get('ajax_get_clients.php?company_id=' + companyId, function (clients) {
                    const $clientSelect = $('#clientSelect');
                    $clientSelect.empty().append('<option value="">-- Select Client --</option>');
                    
                    allClients = clients;
                    if (clients.length > 0) {
                        clients.forEach(client => {
                            $clientSelect.append(`<option value="${client.id}">${client.name}</option>`);
                        });
                        $('#clientSearchInput').prop('disabled', false);
                    } else {
                        $clientSelect.append('<option value="">No clients found</option>');
                        $('#clientSearchInput').prop('disabled', true);
                    }
                    
                    $('#modalCompanyId').val(companyId);
                }, 'json');
            } else {
                $('#productTypeSelect, #productSubTypeSelect').val('').prop('disabled', true);
                $('#clientSearchInput').val('').prop('disabled', true);
                $('#clientSelect').val('');
                $('#calculatorDisplayArea').html('<div class="alert alert-info">Please select company, product type, and client to begin calculation.</div>');
            }
        });

        // Product type select change
        $('#productTypeSelect').change(function () {
            const type = $(this).val();
            const $subType = $('#productSubTypeSelect');
            $subType.empty().append('<option value="">-- Select --</option>');
            if (type && productSubTypes[type]) {
                productSubTypes[type].forEach(t => {
                    $subType.append(`<option value="${t}">${formatName(t)}</option>`);
                });
                $subType.prop('disabled', false);
            } else {
                $subType.prop('disabled', true);
            }
        });

        // Product subtype and client select change
        $('#productSubTypeSelect, #clientSelect').change(function () {
            const companyId = $('#companySelect').val();
            const productType = $('#productTypeSelect').val();
            const subType = $('#productSubTypeSelect').val();
            const clientId = $('#clientSelect').val();

            if (companyId && productType && subType && clientId) {
                $('#calculatorDisplayArea').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin"></i> Loading calculator...</div>');
                $.get(`ajax_get_calculator.php?company_id=${companyId}&product_type=${productType}&sub_type=${subType}&client_id=${clientId}`, 
                    function (calculatorHtml) {
                        try {
                            $('#calculatorDisplayArea').html(calculatorHtml);
                            setupAddToQuotationListener();
                        } catch (e) {
                            console.error("Error injecting calculatorHtml:", e);
                            $('#calculatorDisplayArea').html('<div class="alert alert-danger">There was a problem rendering the calculator.</div>');
                        }
                    }
                ).fail(function () {
                    $('#calculatorDisplayArea').html('<div class="alert alert-danger">Error loading calculator. Please try again.</div>');
                });

                sessionStorage.setItem('selected_client_id', clientId);
            }
        });

        // Add client form submission
        $('#addClientForm').submit(function (e) {
            e.preventDefault();
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            const originalBtnText = $submitBtn.html();
            
            $submitBtn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');

            $.ajax({
                url: 'ajax_add_client.php',
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json'
            })
            .done(function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    const companyId = $('#companySelect').val();
                    
                    $.get('ajax_get_clients.php?company_id=' + companyId, function(clients) {
                        const $clientSelect = $('#clientSelect');
                        $clientSelect.empty().append('<option value="">-- Select Client --</option>');
                        
                        allClients = clients;
                        if (clients.length > 0) {
                            clients.forEach(client => {
                                $clientSelect.append(`<option value="${client.id}">${client.name}</option>`);
                            });
                            $clientSelect.val(response.clientId).trigger('change');
                            $('#clientSearchInput').val(clients.find(c => c.id == response.clientId).name);
                        }
                        
                        $('#addClientModal').modal('hide');
                        $form[0].reset();
                    }, 'json');
                } else {
                    showToast(response.message || 'Error adding client', 'danger');
                }
            })
            .fail(function(xhr, status, error) {
                showToast('Server error: ' + error, 'danger');
                console.error('Error:', error);
            })
            .always(function() {
                $submitBtn.prop('disabled', false).html(originalBtnText);
            });
        });
    }

    // Setup client search functionality
    function setupClientSearch() {
        const $searchInput = $('#clientSearchInput');
        const $searchClear = $('#clientSearchClear');
        const $clientDropdown = $('#clientDropdown');
        const $clientSelect = $('#clientSelect');

        $searchInput.on('focus', function() {
            if ($clientSelect.find('option').length > 1) {
                $clientDropdown.addClass('show');
            }
        });

        $searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $searchClear.toggle(searchTerm.length > 0);
            
            if (searchTerm.length > 0) {
                const filteredClients = allClients.filter(client => 
                    client.name.toLowerCase().includes(searchTerm))
                    .slice(0, 10);
                    
                renderClientDropdown(filteredClients);
                $clientDropdown.addClass('show');
            } else {
                renderClientDropdown(allClients.slice(0, 10));
                $clientDropdown.addClass('show');
            }
        });

        $searchClear.on('click', function() {
            $searchInput.val('').focus();
            $searchClear.hide();
            renderClientDropdown(allClients.slice(0, 10));
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.client-search-container').length) {
                $clientDropdown.removeClass('show');
            }
        });

        function renderClientDropdown(clients) {
            $clientDropdown.empty();
            if (clients.length > 0) {
                clients.forEach(client => {
                    $clientDropdown.append(
                        `<div class="client-dropdown-item" data-id="${client.id}">${client.name}</div>`
                    );
                });
            } else {
                $clientDropdown.append(
                    '<div class="client-dropdown-item text-muted">No clients found</div>'
                );
            }
        }

        // Handle client selection from dropdown
        $clientDropdown.on('click', '.client-dropdown-item', function() {
            const clientId = $(this).data('id');
            if (clientId) {
                const selectedClient = allClients.find(c => c.id == clientId);
                $searchInput.val(selectedClient.name);
                $clientSelect.val(clientId).trigger('change');
                $clientDropdown.removeClass('show');
                $searchClear.show();
            }
        });
    }

    // Setup listener for Add to Quotation button
    function setupAddToQuotationListener() {
        const addToQuotationBtn = document.getElementById('addToQuotationBtn');
        if (addToQuotationBtn) {
            addToQuotationBtn.innerHTML = '<i class="fas fa-plus me-1"></i> Add to Quotation';
            addToQuotationBtn.className = 'btn btn-success btn-lg';
            addToQuotationBtn.onclick = function(e) {
                e.preventDefault();
                addCalculationToQuotation();
            };
        }
    }

    // Add calculation to quotation
    function addCalculationToQuotation() {
        const clientId = $('#clientSelect').val();
        if (!clientId) {
            showToast('Please select a client before adding to quotation.', 'danger');
            return;
        }
        const calcData = getCalculationData();
        if (!calcData) {
            showToast('Error: Could not get calculation data', 'danger');
            return;
        }

        $.ajax({
            url: 'new_calculation.php',
            type: 'POST',
            data: {
                add_to_quotation: true,
                calculation_data: JSON.stringify(calcData)
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                showToast(response.message, 'success');
                createCalculationContainer();
                resetCalculatorForm();
                
                // Update quotation UI
                $('#quotationList').append(response.item_html);
                $('#quotationSummary, #goToQuotationBtn, #clearQuotationBtn').show();
                $('#quotationCount .count-value').text(response.quotation_count + ' item(s)');
                $('#totalQuotationCost .cost-amount').text(response.total_cost + ' RS');
            } else {
                showToast(response.message || 'Error adding to quotation', 'danger');
            }
        })
        .fail(function() {
            showToast('Error adding calculation to quotation', 'danger');
        });
    }

    // Get calculation data from form
    function getCalculationData() {
        const formData = new FormData();
        
        // Get basic form data
        const inputs = document.querySelectorAll('#calculatorDisplayArea input, #calculatorDisplayArea select');
        inputs.forEach(input => {
            if (input.name && input.value) {
                formData.append(input.name, input.value);
            }
        });

        // Get total cost from the calculator
        const totalCostElement = document.querySelector('#calculatorDisplayArea .total-cost, #calculatorDisplayArea .grand-total');
        const totalCost = totalCostElement ? parseFloat(totalCostElement.textContent.replace(/[^\d.]/g, '')) : 0;

        // Get window type and description
        const windowType = $('#productSubTypeSelect').val();
        const description = `Calculation for ${formatName(windowType)}`;

        return {
            window_type: formatName(windowType),
            description: description,
            amount: totalCost,
            area: formData.get('area') || 0,
            rate: totalCost / (formData.get('area') || 1),
            quantity: formData.get('quantity') || 1,
            width: formData.get('width') || 0,
            height: formData.get('height') || 0,
            client_id: $('#clientSelect').val(),
            calculation_html: $('#calculatorDisplayArea').html()
        };
    }

    // Create calculation container
    function createCalculationContainer() {
        calculationCounter++;
        const calcId = `calc_${calculationCounter}`;
        const productType = $('#productSubTypeSelect').val();
        const calculationContent = $('#calculatorDisplayArea').html();
        
        const calcContainer = $(`
            <div class="calculation-container" id="${calcId}">
                <div class="calculation-header">
                    <div class="calculation-number">Calculation ${calculationCounter} - ${formatName(productType)}</div>
                    <button type="button" class="remove-calculation-btn" onclick="removeCalculation('${calcId}')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="calculation-content">
                    ${calculationContent}
                </div>
            </div>
        `);
        
        $('#calculationsContainer').append(calcContainer);
        $('#calculatorForm').hide();
    }

    // Reset calculator form
    function resetCalculatorForm() {
        $('#productSubTypeSelect').val('').prop('disabled', true);
        $('#calculatorDisplayArea').html('<div class="alert alert-info">Please select product type to begin next calculation.</div>');
    }

    // Show toast notification
    function showToast(message, type = 'success') {
        const toast = $(`
            <div class="toast-container position-fixed top-0 end-0 p-3">
                <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            </div>
        `);

        $('body').append(toast);
        const bsToast = new bootstrap.Toast(toast.find('.toast')[0]);
        bsToast.show();

        setTimeout(() => {
            toast.fadeOut(500, () => toast.remove());
        }, 3000);
    }

    // Initialize the system
    init();
});

// Global functions for quotation management
function removeQuotationItem(index) {
    if (confirm('Are you sure you want to remove this item from the quotation?')) {
        $.ajax({
            url: 'new_calculation.php',
            type: 'POST',
            data: {
                remove_quotation_item: true,
                index: index
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                // Remove the item from UI
                $('.quotation-item[data-index="' + index + '"]').remove();
                
                // Update quotation count
                $('#quotationCount .count-value').text(response.quotation_count + ' item(s)');
                
                // Update total cost
                $('#totalQuotationCost .cost-amount').text(response.total_cost + ' RS');
                
                // Hide quotation summary if empty
                if (response.quotation_count === 0) {
                    $('#quotationSummary, #goToQuotationBtn, #clearQuotationBtn').hide();
                }
                
                showToast(response.message, 'success');
            } else {
                showToast('Error removing item', 'danger');
            }
        })
        .fail(function() {
            showToast('Error removing item', 'danger');
        });
    }
}

function clearQuotation() {
    if (confirm('Are you sure you want to clear the entire quotation? This will remove all items.')) {
        $.ajax({
            url: 'new_calculation.php',
            type: 'POST',
            data: {
                clear_quotation: true
            },
            dataType: 'json'
        })
        .done(function(response) {
            if (response.success) {
                $('#quotationList').empty();
                $('#quotationSummary, #goToQuotationBtn, #clearQuotationBtn').hide();
                showToast(response.message, 'success');
            }
        })
        .fail(function() {
            showToast('Error clearing quotation', 'danger');
        });
    }
}

function submitQuotationForm() {
    $.ajax({
        url: 'new_calculation.php',
        type: 'POST',
        data: {
            go_to_quotation: true
        },
        dataType: 'json'
    })
    .done(function(response) {
        if (response.success && response.redirect) {
            // Change to go to the main app route
            window.location.href = 'index.php?page=quotation';
        } else {
            showToast(response.message || 'Cannot proceed to quotation', 'danger');
        }
    })
    .fail(function() {
        showToast('Error processing request', 'danger');
    });
}

function addNewCalculation() {
    $('#calculatorForm').show();
    $('#productSubTypeSelect').val('').prop('disabled', true);
    $('#calculatorDisplayArea').html('<div class="alert alert-info">Please select product type to begin next calculation.</div>');
}

function removeCalculation(calcId) {
    if (confirm('Are you sure you want to remove this calculation?')) {
        $(`#${calcId}`).remove();
        // Show calculator form if no calculations left
        if ($('#calculationsContainer .calculation-container').length === 0) {
            $('#calculatorForm').show();
        }
    }
}

// Auto-dismiss success alert after 3 seconds
setTimeout(() => {
    document.querySelector('.alert-success')?.remove();
}, 3000);

function showSessionPopup(message) {
    const popup = $(
        `<div class="session-popup custom-popup-animation" style="position:fixed;top:70px;right:30px;z-index:2000;background:#007bff;color:#fff;padding:10px 20px;border-radius:6px;box-shadow:0 4px 16px rgba(0,0,0,0.15);font-size:1em;display:none;">
            <i class='fas fa-check-circle me-2'></i> ${message}
        </div>`
    );
    $('body').append(popup);
    popup.fadeIn(200);
    setTimeout(() => {
        popup.addClass('custom-popup-hide');
        setTimeout(() => popup.remove(), 700);
    }, 2200);
}
</script>
</body>
</html>
<?php ob_end_flush(); ?>