<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../quotation_handler.php');
require_once(__DIR__ . '/../db.php');

// Debug output
echo "<!-- Session: " . print_r($_SESSION, true) . " -->";
echo "<!-- GET: " . print_r($_GET, true) . " -->";

// Check if required parameters are passed via GET
$required_params = ['company_id', 'product_type', 'sub_type', 'client_id'];
foreach ($required_params as $param) {
    if (!isset($_GET[$param])) {
        die('<div class="alert alert-danger">Missing required parameter: ' . $param . '</div>');
    }
}

$company_id = (int)$_GET['company_id'];
$product_type = $_GET['product_type'];
$sub_type = $_GET['sub_type'];
$client_id = (int)$_GET['client_id'];

// Set client ID in JavaScript scope
echo "<script>window.currentClientId = $client_id; window.currentCompanyId = $company_id;</script>";

// Database connection
try {
    // Verify client belongs to selected company
    $stmt = $conn->prepare("SELECT id, name FROM clients WHERE id = ? AND company_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ii", $client_id, $company_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();

    if (!$client) {
        throw new Exception("Client not found or doesn't belong to selected company");
    }

    // Fetch all prices
    $prices = ['materials' => [], 'hardware' => [], 'additional' => []];

    // Fetch material prices
    $stmt = $conn->prepare("SELECT name, price_per_foot FROM materials WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $prices['materials'][$row['name']] = $row['price_per_foot'];
                $prices['additional'][$row['name']] = $row['price_per_foot'];
            }
        }
        $stmt->close();
    }

    // Fetch hardware prices
    $stmt = $conn->prepare("SELECT name, price FROM hardware WHERE company_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $company_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $prices['hardware'][$row['name']] = $row['price'];
            }
        }
        $stmt->close();
    }

    // Default glass price
    $glass_price_per_sqft = 200;
    $conn->close();

} catch (Exception $e) {
    die('<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UPVC Door Material Calculator</title>
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }
    .calculator-container {
      background: white;
      max-width: 1600px;
      margin: 20px auto;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      overflow: hidden;
      transition: all 0.3s ease;
    }
    .calculator-container:hover {
      box-shadow: 0 15px 35px rgba(0,0,0,0.15);
      transform: translateY(-5px);
    }
    .calculator-header {
      background: linear-gradient(to right, #4b6cb7, #182848);
      color: white;
      padding: 20px;
      margin-bottom: 20px;
    }
    .form-control, .form-select {
      border-radius: 8px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      font-size: 0.98rem;
      transition: all 0.3s;
    }
    .form-control:focus, .form-select:focus {
      border-color: #4b6cb7;
      box-shadow: 0 0 0 0.25rem rgba(75, 108, 183, 0.25);
    }
    .btn-calculate {
      background: linear-gradient(to right, #4b6cb7, #182848);
      border: none;
      padding: 8px 18px;
      font-weight: 600;
      font-size: 1rem;
      letter-spacing: 1px;
      color: #fff !important;
      border-radius: 8px;
      transition: all 0.3s;
      min-width: 120px;
      width: 100%;
    }
    .btn-calculate:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.1);
      background: linear-gradient(to right, #182848, #4b6cb7);
      color: #fff !important;
    }
    .results-container {
      background: #f8f9fa;
      border-radius: 10px;
      padding: 20px;
      margin-top: 20px;
      border-left: 5px solid #4b6cb7;
      width: 100%;
      overflow-x: auto;
    }
    .results-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
    }
    .result-card {
      background: white;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .result-section {
      margin-bottom: 15px;
    }
    .section-title {
      color: #4b6cb7;
      border-bottom: 1px solid #4b6cb7;
      padding-bottom: 5px;
      margin-bottom: 10px;
      font-size: 16px;
    }
    .result-item {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 14px;
    }
    .result-label {
      font-weight: 600;
      color: #555;
    }
    .result-value {
      text-align: right;
    }
    .price-value {
      color: #28a745;
      font-weight: bold;
    }
    .result-total {
      background: #f0f7ff;
      padding: 10px;
      border-radius: 6px;
      margin-top: 15px;
    }
    .total-item {
      font-weight: bold;
    }
    .grand-total {
      font-size: 18px;
      color: #182848;
    }
    .sketch-container {
      margin-top: 30px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 10px;
      animation: fadeInUp 0.6s ease-out;
      position: relative;
      text-align: center;
    }
    .door-image {
      max-width: 100%;
      height: auto;
      border: 1px solid #ddd;
      border-radius: 8px;
    }
    .dimension-label {
      position: absolute;
      font-weight: bold;
      color: #182848;
      background: rgba(255,255,255,0.8);
      padding: 2px 5px;
      border-radius: 3px;
    }
    .width-label {
      top: -10px;
      left: 50%;
      transform: translateX(-50%);
    }
    .height-label {
      right: 10px;
      top: 50%;
      transform: translateY(-50%) rotate(-90deg);
      transform-origin: right center;
    }
    .result-note {
      font-size: 0.8rem;
      color: #666;
      font-style: italic;
    }
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .pulse {
      animation: pulse 2s infinite;
    }
    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
    }
    .no-length {
      color: #999;
    }
    .input-group-unit {
      width: 90px;
    }
    .alert-quotation {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      min-width: 300px;
    }
    .quotation-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    @media (max-width: 992px) {
      .results-grid {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 768px) {
      .calculator-container {
          margin: 10px;
      }
      .quotation-buttons {
          flex-direction: column;
          align-items: center;
      }
      .results-grid {
          grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12">
        <div class="calculator-container animate__animated animate__fadeIn">
          <div class="calculator-header text-center">
            <h2 class="animate__animated animate__fadeInDown">Full Door Material Calculator</h2>
            <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials for UPVC doors</p>
          </div>
          <div class="p-4">
            <form id="windowCalcForm" autocomplete="off">
              <div class="row g-3 align-items-end">
                <!-- Width -->
                <div class="col-md-4 mb-3">
                  <label for="width" class="form-label"><i class="fa-solid fa-arrows-left-right me-1"></i>Width</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="width" placeholder="Enter width" step="0.01" min="0.01">
                    <select class="form-select input-group-unit" id="widthUnit">
                      <option value="ft">feet</option>
                      <option value="in">inches</option>
                      <option value="cm">centimeters</option>
                      <option value="mm">millimeters</option>
                    </select>
                  </div>
                </div>
                <!-- Height -->
                <div class="col-md-4 mb-3">
                  <label for="height" class="form-label"><i class="fa-solid fa-arrows-up-down me-1"></i>Height</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="height" placeholder="Enter height" step="0.01" min="0.01">
                    <select class="form-select input-group-unit" id="heightUnit">
                      <option value="ft">feet</option>
                      <option value="in">inches</option>
                      <option value="cm">centimeters</option>
                      <option value="mm">millimeters</option>
                    </select>
                  </div>
                </div>
                
                <!-- Quantity -->
                <div class="col-md-2 mb-3">
                  <label for="quantity" class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Quantity</label>
                  <input type="number" class="form-control" id="quantity" placeholder="Enter quantity" min="1" value="1">
                </div>
                <div class="col-md-2 mb-3 d-grid">
                  <button type="button" id="calculateBtn" class="btn btn-calculate btn-lg mt-md-0 mt-3">
                    <i class="fas fa-calculator me-2"></i>Calculate
                  </button>
                </div>
              </div>
            </form>
            <div class="results-container mt-4" id="output" style="display: none;"></div>
            <div class="sketch-container" id="sketch" style="display: none;">
              <img src="Pages/image/fulldoor.jpg" alt="UPVC Door Diagram" class="door-image">
              <div class="dimension-label width-label" id="widthLabel"></div>
              <div class="dimension-label height-label" id="heightLabel"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Database prices from PHP
    const prices = <?php echo json_encode($prices); ?>;
    const glassPricePerSqft = <?php echo $glass_price_per_sqft; ?>;
    
    // Utility functions
    function convertToFeet(value, unit) {
      const conversions = {
        'in': 12,
        'cm': 30.48,
        'mm': 304.8,
        'ft': 1
      };
      return value / (conversions[unit] || 1);
    }
    
    function convertToInches(value, unit) {
      switch(unit) {
        case 'ft': return value * 12;
        case 'cm': return value / 2.54;
        case 'mm': return value / 25.4;
        default: return value;
      }
    }
    
    function formatCurrency(amount) {
      return 'Rs. ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }
    
    function formatUnit(value, unit) {
      switch(unit) {
        case 'in': return `${value} in`;
        case 'cm': return `${value} cm`;
        case 'mm': return `${value} mm`;
        default: return `${value} ft`;
      }
    }
    
    function showError(message) {
      const output = document.getElementById("output");
      output.innerHTML = `
        <div class="alert alert-danger" role="alert">
          <i class="fas fa-exclamation-circle me-2"></i>${message}
        </div>
      `;
      output.style.display = "block";
    }
    
    function createResultRow(label, value, amount, isTotal = false, extraClass = '') {
      const amountDisplay = amount ? `<span class=\"price-value ${extraClass}\">${amount}</span>` : '';
      return `
        <div class=\"result-item ${isTotal ? 'total-item' : ''}\">\n            <span class=\"result-label\">${label}</span>\n            <span class=\"result-value\">${value} ${amountDisplay}</span>\n          </div>\n        `;
    }

    function generateDoorSketch(height, heightUnit, width, widthUnit) {
      // Update door image with dimensions
      document.getElementById("widthLabel").textContent = `${width} ${widthUnit}`;
      document.getElementById("heightLabel").textContent = `${height} ${heightUnit}`;
      document.getElementById("sketch").style.display = "block";
    }
    
    function calculateDoor() {
      // Get input values
      const heightValue = parseFloat(document.getElementById("height").value);
      const heightUnit = document.getElementById("heightUnit").value;
      const widthValue = parseFloat(document.getElementById("width").value);
      const widthUnit = document.getElementById("widthUnit").value;
      const quantity = parseInt(document.getElementById("quantity").value);
      
      // Validate inputs
      if (isNaN(heightValue)) return showError("Please enter a valid height");
      if (isNaN(widthValue)) return showError("Please enter a valid width");
      if (isNaN(quantity) || quantity < 1) return showError("Please enter a valid quantity (minimum 1)");
      
      // Convert to feet and inches for calculations
      const heightFt = convertToFeet(heightValue, heightUnit);
      const widthFt = convertToFeet(widthValue, widthUnit);
      const heightIn = convertToInches(heightValue, heightUnit);
      
      if (heightFt <= 0 || widthFt <= 0) return showError("Height and width must be positive values");
      
      // Calculate perimeter
      const perimeter = (heightFt * 2) + (widthFt * 2);
      
      // Area and Glass calculations
      const area = heightFt * widthFt;
      const totalArea = area * quantity;
      const glass = totalArea / 2;
      
      // Main calculations
      const frameLength = ((heightFt * 2) + widthFt) * quantity;
      const doorSashLength = ((widthFt * 2) + (heightFt * 2)) * quantity;
      const beadingLength = doorSashLength * quantity;
      
      // Panel calculations
      let doorPanel;
      if (heightUnit === 'in') {
        doorPanel = (heightValue / 4) * widthFt;
      } else {
        doorPanel = (heightIn / 4) * widthFt;
      }
      doorPanel = doorPanel * quantity;
      
      // Steel calculation
      const steel = ((frameLength + doorSashLength) / 8);
      
      // Hardware calculations
      const hardwareItems = {
        'Fitting Screw': 15 * quantity,
        'Rawal Plug': 15 * quantity,
        'Silicon White': 2 * quantity,
        'Self Screw': 40 * quantity,
        'Sada Screw': 30 * quantity,
        'Hinges': 4 * quantity,
        'Locks': 1 * quantity
      };
      
      // Calculate costs
      const calculateCost = (length, material) => length * (prices.materials[material] || 0);
      
      const frameCost = calculateCost(frameLength, 'Frame');
      const doorSashCost = calculateCost(doorSashLength, 'Door Sash');
      const beadingCost = calculateCost(beadingLength, 'Beading');
      const steelCost = steel * (prices.additional['Steel'] || 0);
      
      const doorPanelCost = calculateCost(doorPanel, 'Door Panel');
      const glassCost = glass * glassPricePerSqft;
      
      // Calculate hardware costs
      let totalHardwareCost = 0;
      const hardwareCosts = {};
      
      Object.entries(hardwareItems).forEach(([name, qty]) => {
        const cost = qty * (prices.hardware[name] || 0);
        hardwareCosts[name] = cost;
        totalHardwareCost += cost;
      });
      
      // Calculate totals
      const totalMaterialCost = frameCost + doorSashCost + beadingCost + doorPanelCost + steelCost;
      const grandTotal = totalMaterialCost + totalHardwareCost + glassCost;
      
      // Generate output HTML (horizontal card layout)
      const outputHTML = `
        <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Calculation Results</h5>
        <div class="results-grid">
          <!-- Door Details Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Door Details</h6>
              ${createResultRow('Quantity', quantity, '')}
              ${createResultRow('Height', `${heightValue} ${heightUnit} (${heightFt.toFixed(2)} ft)`, '')}
              ${createResultRow('Width', `${widthValue} ${widthUnit} (${widthFt.toFixed(2)} ft)`, '')}
              ${createResultRow('Total Area', `${totalArea.toFixed(2)} sft`, '')}
            </div>
          </div>
          <!-- Main Materials Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Main Materials</h6>
              ${createResultRow('60mm Frame', (frameLength/19).toFixed(2) + ' lengths', formatCurrency(frameCost))}
              ${createResultRow('Door Sash', (doorSashLength/19).toFixed(2) + ' lengths', formatCurrency(doorSashCost))}
              ${createResultRow('Beading', (beadingLength/19).toFixed(2) + ' lengths', formatCurrency(beadingCost))}
              ${createResultRow('Door Panel', (doorPanel/19).toFixed(2) + ' lengths', formatCurrency(doorPanelCost))}
              ${createResultRow('Steel', steel.toFixed(2) + ' kg', formatCurrency(steelCost))}
              ${createResultRow('Glass', glass.toFixed(2) + ' sft', formatCurrency(glassCost))}
            </div>
          </div>
          <!-- Hardware Items Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Hardware Items</h6>
              ${Object.entries(hardwareItems).map(([name, qty]) => 
                createResultRow(name, qty + ' pcs', formatCurrency(hardwareCosts[name]))).join('')}
            </div>
          </div>
          <!-- Cost Summary Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Cost Summary</h6>
              <div class="result-total">
                ${createResultRow('Total Materials Cost', '', formatCurrency(totalMaterialCost), true)}
                ${createResultRow('Total Hardware Cost', '', formatCurrency(totalHardwareCost), true)}
                ${createResultRow('Glass Cost', '', formatCurrency(glassCost), true)}
                ${createResultRow('Grand Total', '', formatCurrency(grandTotal), true, 'grand-total')}
              </div>
            </div>
          </div>
        </div>
      `;
      
      // Display results
      function prepareFullCalculation(heightFt, widthFt, quantity) {
        return {
          dimensions: {
            height: heightFt,
            width: widthFt,
            quantity: quantity,
            area: heightFt * widthFt * quantity
          },
          materials: {
            frame: { length: (frameLength/19).toFixed(2), cost: frameCost },
            sash: { length: (doorSashLength/19).toFixed(2), cost: doorSashCost },
            beading: { length: (beadingLength/19).toFixed(2), cost: beadingCost },
            panel: { length: (doorPanel/19).toFixed(2), cost: doorPanelCost },
            steel: { quantity: steel.toFixed(2), cost: steelCost },
            glass: { area: glass.toFixed(2), cost: glassCost }
          },
          hardware: {
            items: hardwareItems,
            costs: hardwareCosts,
            total: totalHardwareCost
          },
          totals: {
            materials: totalMaterialCost,
            hardware: totalHardwareCost,
            glass: glassCost,
            grandTotal: grandTotal
          }
        };
      }

      const output = document.getElementById("output");
      output.innerHTML = outputHTML;
      output.style.display = "block";

      const quoteBtnContainer = document.createElement('div');
      quoteBtnContainer.className = 'quotation-buttons';

      const getCalculationData = () => {
        return {
          area: totalArea,
          quantity,
          totalCost: grandTotal,
          height: heightFt,
          width: widthFt,
          unit: 'ft',
          _source: 'door_calculator',
          original: {
            height: heightValue,
            width: widthValue,
            unit: heightUnit
          },
          fullData: prepareFullCalculation(heightFt, widthFt, quantity)
        };
      };

      const addButton = document.createElement('button');
      addButton.className = 'btn btn-success btn-lg';
      addButton.id = 'addToQuotationBtn';
      addButton.innerHTML = `<i class="fas fa-plus me-2"></i>Add to Quotation`;
      addButton.style.fontWeight = '600';
      addButton.style.padding = '12px 24px';

      function showToast(message, type = 'info') {
        alert(`${type.toUpperCase()}: ${message}`);
      }

      addButton.addEventListener('click', function() {
        const calcData = getCalculationData();
        const fullData = calcData.fullData;

        const quoteFormData = new FormData();
        quoteFormData.append('action', 'add_item');
        quoteFormData.append('window_type', 'Door');
        quoteFormData.append('description', 'UPVC Door');
        quoteFormData.append('unit', 'Sft');
        quoteFormData.append('area', calcData.area);
        quoteFormData.append('rate', calcData.totalCost / calcData.area);
        quoteFormData.append('amount', calcData.totalCost);
        quoteFormData.append('quantity', calcData.quantity);
        quoteFormData.append('height', calcData.height);
        quoteFormData.append('width', calcData.width);
        quoteFormData.append('client_id', window.currentClientId);
        // Save original values and unit
        quoteFormData.append('height_original', calcData.original.height);
        quoteFormData.append('width_original', calcData.original.width);
        quoteFormData.append('unit_original', calcData.original.unit);

        fetch('quotation_handler.php', {
          method: 'POST',
          body: quoteFormData
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('HTTP error: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            showToast('Added to quotation!', 'success');
          } else {
            showToast('Error: ' + (data.error || 'Failed to add'), 'danger');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          showToast('Error: ' + error.message, 'danger');
        });

        const saveFormData = new FormData();
        saveFormData.append('action', 'save_calculation');
        saveFormData.append('client_id', window.currentClientId);
        saveFormData.append('company_id', window.currentCompanyId);
        saveFormData.append('window_type', 'Door');
        saveFormData.append('height', fullData.dimensions.height);
        saveFormData.append('width', fullData.dimensions.width);
        saveFormData.append('quantity', fullData.dimensions.quantity);
        saveFormData.append('total_area', fullData.dimensions.area);
        saveFormData.append('frame_length', fullData.materials.frame.length);
        saveFormData.append('sash_length', fullData.materials.sash.length);
        saveFormData.append('beading_length', fullData.materials.beading.length);
        saveFormData.append('panel_length', fullData.materials.panel.length);
        saveFormData.append('steel_quantity', fullData.materials.steel.quantity);
        saveFormData.append('glass_area', fullData.materials.glass.area);
        saveFormData.append('material_cost', fullData.totals.materials);
        saveFormData.append('hardware_cost', fullData.totals.hardware);
        saveFormData.append('glass_cost', fullData.totals.glass);
        saveFormData.append('total_cost', fullData.totals.grandTotal);

        // Add individual cost values
        saveFormData.append('frame_cost', fullData.materials.frame.cost);
        saveFormData.append('sash_cost', fullData.materials.sash.cost);
        saveFormData.append('beading_cost', fullData.materials.beading.cost);
        saveFormData.append('panel_cost', fullData.materials.panel.cost);
        saveFormData.append('steel_cost', fullData.materials.steel.cost);
        saveFormData.append('glass_cost', fullData.materials.glass.cost);

        // Add hardware cost values
        Object.entries(fullData.hardware.items).forEach(([name, qty]) => {
          const key = name.toLowerCase().replace(/\s/g, '_');
          saveFormData.append(key, qty);
          saveFormData.append(key + '_cost', fullData.hardware.costs[name] || 0);
        });

        console.log('Saving data to save_window_calculation.php...', Object.fromEntries(saveFormData));
        console.log('Cost values being sent:', {
          frame_cost: fullData.materials.frame.cost,
          sash_cost: fullData.materials.sash.cost,
          beading_cost: fullData.materials.beading.cost,
          panel_cost: fullData.materials.panel.cost,
          steel_cost: fullData.materials.steel.cost,
          glass_cost: fullData.materials.glass.cost
        });

        fetch('./Pages/save_window_calculation.php', {
          method: 'POST',
          body: saveFormData
        })
        .then(res => {
          console.log('Response status:', res.status);
          console.log('Response headers:', res.headers);
          
          // First check if the response is JSON
          const contentType = res.headers.get('content-type');
          console.log('Content-Type:', contentType);
          
          if (!contentType || !contentType.includes('application/json')) {
            return res.text().then(text => {
              console.log('Non-JSON response:', text);
              throw new Error(`Invalid response: ${text}`);
            });
          }
          return res.json();
        })
        .then(data => {
    if (data.success) {
        alert("Saved successfully"); // Show success message
        window.location.reload();    // Refresh the page as-is (same URL & params)
    } else {
        throw new Error(data.error || 'Save failed');
    }
})
        .catch(error => {
          console.error("Error:", error.message);
          alert("Error: " + error.message);
        });
      });

      quoteBtnContainer.appendChild(addButton);
      output.appendChild(quoteBtnContainer);
      generateDoorSketch(heightValue, heightUnit, widthValue, widthUnit);
      output.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Event listeners
    document.getElementById('calculateBtn').addEventListener('click', calculateDoor);

    ['height', 'width', 'quantity'].forEach(id => {
      document.getElementById(id).addEventListener('keypress', function(e) {
        if (e.key === 'Enter') calculateDoor();
      });
    });
  </script>
</body>
</html>