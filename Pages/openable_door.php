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
  <title>Sliding Window Calculator</title>
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      padding: 30px 0;
    }
    .calculator-container {
      background: white;
      /* You can increase or decrease the max-width below to control the form width */
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
    .btn-calculate.pulse {
      animation: pulse 2s infinite;
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
      grid-template-columns: repeat(3, 1fr);
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
    .door-image, .window-image {
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
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
    }
    .height-label {
      right: 50px;
      top: 50%;
      transform: translateY(-50%) rotate(-90deg);
      transform-origin: right center;
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
    .quotation-buttons {
      margin-top: 20px;
      display: flex;
      gap: 10px;
      justify-content: center;
    }
    @media (max-width: 992px) {
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
            <h2 class="animate__animated animate__fadeInDown">Openable Door Calculator</h2>
            <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs for Openable Door</p>
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
                      <option value="mm">mm</option>
                      <option value="cm">cm</option>
                      <option value="inches">inches</option>
                      <option value="feet">feet</option>
                    </select>
                  </div>
                </div>
                <!-- Height -->
                <div class="col-md-4 mb-3">
                  <label for="height" class="form-label"><i class="fa-solid fa-arrows-up-down me-1"></i>Height</label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="height" placeholder="Enter height" step="0.01" min="0.01">
                    <select class="form-select input-group-unit" id="heightUnit">
                      <option value="mm">mm</option>
                      <option value="cm">cm</option>
                      <option value="inches">inches</option>
                      <option value="feet">feet</option>
                    </select>
                  </div>
                </div>
                <!-- Quantity -->
                <div class="col-md-2 mb-3">
                  <label for="quantity" class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Quantity</label>
                  <input type="number" class="form-control" id="quantity" placeholder="Enter quantity" min="1" value="1">
                </div>
                <div class="col-md-2 mb-3 d-grid">
                  <button type="button" id="calculateBtn" class="btn btn-calculate btn-lg pulse mt-md-0 mt-3">
                    <i class="fas fa-calculator me-2"></i>Calculate
                  </button>
                </div>
              </div>
            </form>
            <div class="results-container mt-4" id="output" style="display: none;"></div>
            <div class="sketch-container" id="sketch" style="display: none;">
              <img src="Pages/image/slide.jpg" alt="Sliding window diagram" class="window-image">
              <div class="dimension-label width-label" id="widthLabel"></div>
              <div class="dimension-label height-label" id="heightLabel"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Database prices from PHP
    const prices = <?php echo json_encode($prices); ?>;
    const glassPricePerSqft = <?php echo $glass_price_per_sqft; ?>;
    
    // Utility functions
    function convertToMM(value, unit) {
      switch(unit) {
        case 'mm': return value;
        case 'cm': return value * 10;
        case 'inches': return value * 25.4;
        case 'feet': return value * 304.8;
        default: return value;
      }
    }
    
    function convertToFeet(mm) {
      return mm / 304.8;
    }
    
    function formatCurrency(amount) {
      return 'Rs. ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
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

    function calculate() {
      // Get input values
      const heightInput = document.getElementById("height");
      const heightValue = parseFloat(heightInput.value);
      const heightUnit = document.getElementById("heightUnit").value;
      const widthInput = document.getElementById("width");
      const widthValue = parseFloat(widthInput.value);
      const widthUnit = document.getElementById("widthUnit").value;
      const quantityInput = document.getElementById("quantity");
      const quantity = parseInt(quantityInput.value) || 1;

      // Validate inputs
      if (isNaN(heightValue)) {
        heightInput.focus();
        return showError("Please enter a valid height");
      }
      if (isNaN(widthValue)) {
        widthInput.focus();
        return showError("Please enter a valid width");
      }
      if (isNaN(quantity) || quantity < 1) {
        quantityInput.focus();
        return showError("Please enter a valid quantity (minimum 1)");
      }

      // Convert to mm for calculations
      const heightMM = convertToMM(heightValue, heightUnit);
      const widthMM = convertToMM(widthValue, widthUnit);
      const heightFt = convertToFeet(heightMM);
      const widthFt = convertToFeet(widthMM);

      // Calculations
      const frame = (widthMM * 2 + heightMM * 2) * quantity;
      const sash = (widthMM * 2 + heightMM * 8) * quantity;
      const netSash = (widthMM / 2 + heightMM * 4) * quantity;
      const beading = sash;
      const interlock = (heightMM * 4) * quantity;
      const coupling = (heightMM * 1) * quantity;
      const steel = (frame + sash + netSash) * 19 / 8;
      const areaSqft = (widthFt * heightFt * quantity).toFixed(2);
      const glass = areaSqft;

      // Hardware items
      const lock = 2 * quantity;
      const doubleWheel = 4 * quantity;
      const netWheel = 4 * quantity;
      const dummy = 4 * quantity;
      const boofer = 4 * quantity;
      const stoper = 4 * quantity;
      const fittingScrew = 30 * quantity;
      const rawalplug = 30 * quantity;
      const salicon = 2 * quantity;
      const selfScrew = 120 * quantity;
      const sadaScrew = 50 * quantity;

      // Calculate costs
      const calculateCost = (length, material) => {
        let price = prices.materials[material];
        // Fallback for Coupling/Coupler spelling
        if (price === undefined && material === 'Coupling') {
          price = prices.materials['Coupler'];
        }
        if (price === undefined) {
          console.warn(`Price not found for material: ${material}`);
          return 0;
        }
        return length * price;
      };
      
      const frameCost = calculateCost(convertToFeet(frame), 'Frame');
      const sashCost = calculateCost(convertToFeet(sash), 'Sash');
      const netSashCost = calculateCost(convertToFeet(netSash), 'Net Sash');
      const beadingCost = calculateCost(convertToFeet(beading), 'Beading');
      const interlockCost = calculateCost(convertToFeet(interlock), 'Interlock');
      const couplingCost = calculateCost(convertToFeet(coupling), 'Coupling');
      const steelCost = steel * (prices.additional['Steel'] || 0);
      
      // Hardware costs
      const hardwareCosts = {
        'Lock': lock * (prices.hardware['Locks'] || 0),
        'Double Wheel': doubleWheel * (prices.hardware['Double Wheel'] || 0),
        'Net Wheel': netWheel * (prices.hardware['Net Wheel'] || 0),
        'Dummy': dummy * (prices.hardware['Dummy'] || 0),
        'Boofer': boofer * (prices.hardware['Boofer'] || 0),
        'Stoper': stoper * (prices.hardware['Stopper'] || 0),
        'Fitting Screw': fittingScrew * (prices.hardware['Fitting Screw'] || 0),
        'Rawal Plug': rawalplug * (prices.hardware['Rawal Plug'] || 0),
        'Silicon': salicon * (prices.hardware['Silicon White'] || 0),
        'Self Screw': selfScrew * (prices.hardware['Self Screw'] || 0),
        'Sada Screw': sadaScrew * (prices.hardware['Sada Screw'] || 0)
      };
      
      const totalHardwareCost = Object.values(hardwareCosts).reduce((a, b) => a + b, 0);
      
      // Glass calculation
      const glassCost = glass * glassPricePerSqft;
      
      // Calculate totals
      const totalMaterialCost = frameCost + sashCost + netSashCost + beadingCost + interlockCost + couplingCost + steelCost;
      const grandTotal = totalMaterialCost + totalHardwareCost + glassCost;

      // Update image labels
      document.getElementById("heightLabel").textContent = `${heightValue}${heightUnit}`;
      document.getElementById("widthLabel").textContent = `${widthValue}${widthUnit}`;

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
              ${createResultRow('Total Area', `${areaSqft} sft`, '')}
            </div>
          </div>
          <!-- Main Materials Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Main Materials</h6>
              ${createResultRow('Frame', (frame/1000).toFixed(2) + ' m', formatCurrency(frameCost))}
              ${createResultRow('Sash', (sash/1000).toFixed(2) + ' m', formatCurrency(sashCost))}
              ${createResultRow('Net Sash', (netSash/1000).toFixed(2) + ' m', formatCurrency(netSashCost))}
              ${createResultRow('Beading', (beading/1000).toFixed(2) + ' m', formatCurrency(beadingCost))}
              ${createResultRow('Interlock', (interlock/1000).toFixed(2) + ' m', formatCurrency(interlockCost))}
              ${createResultRow('Coupling', (coupling/1000).toFixed(2) + ' m', formatCurrency(couplingCost))}
              ${createResultRow('Steel', steel.toFixed(2) + ' kg', formatCurrency(steelCost))}
              ${createResultRow('Glass', glass + ' sft', formatCurrency(glassCost))}
            </div>
          </div>
          <!-- Hardware Items Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Hardware Items</h6>
              ${Object.entries(hardwareCosts).map(([name, cost]) => 
                createResultRow(name, '', formatCurrency(cost))).join('')}
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
            // Build extra calculation data
      const frameLength = frame;
      const doorSashLength = sash;
      const mullionLength = interlock;
      const beadingLength = beading;

      const hardwareItems = {
        'Lock': lock,
        'Double Wheel': doubleWheel,
        'Net Wheel': netWheel,
        'Dummy': dummy,
        'Boofer': boofer,
        'Stoper': stoper,
        'Fitting Screw': fittingScrew,
        'Rawal Plug': rawalplug,
        'Silicon': salicon,
        'Self Screw': selfScrew,
        'Sada Screw': sadaScrew
      };

      function prepareFullCalculation(heightFt, widthFt, area) {
        return {
          dimensions: {
            height: heightFt,
            width: widthFt,
            quantity: quantity,
            area: heightFt * widthFt * quantity
          },
          materials: {
            frame: { length: (frameLength/19).toFixed(2), cost: frameCost },
            sash: { length: (doorSashLength/19).toFixed(2), cost: sashCost },
            mullion: { length: (mullionLength/19).toFixed(2), cost: interlockCost },
            beading: { length: (beadingLength/19).toFixed(2), cost: beadingCost },
            steel: { quantity: steel.toFixed(2), cost: steelCost }
          },
          hardware: {
            ...Object.fromEntries(Object.entries(hardwareItems).map(([name, qty]) => 
              [name.toLowerCase().replace(/\s/g, '_'), { quantity: qty, cost: hardwareCosts[name] }]))
          },
          totals: {
            materials: totalMaterialCost,
            hardware: totalHardwareCost,
            glass: glassCost,
            grandTotal: grandTotal
          }
        };
      }

      const quoteBtnContainer = document.createElement('div');
      quoteBtnContainer.className = 'quotation-buttons';

      const getCalculationData = () => {
        return {
          area: areaSqft,
          quantity,
          totalCost: grandTotal,
          height: heightFt,
          width: widthFt,
          unit: heightUnit,
          _source: 'openable_door',
          original: {
            height: heightValue,
            width: widthValue,
            unit: heightUnit
          },
          fullData: prepareFullCalculation(heightFt, widthFt, areaSqft)
        };
      };

      const addButton = document.createElement('button');
      addButton.className = 'btn btn-success btn-lg';
      addButton.id = 'addToQuotationBtn';
      addButton.innerHTML = `<i class="fas fa-plus me-1"></i> Add to Quotation`;
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
        quoteFormData.append('window_type', 'Openable Door');
        quoteFormData.append('description', 'Openable Door');
        quoteFormData.append('area', calcData.area);
        quoteFormData.append('rate', calcData.totalCost / calcData.area);
        quoteFormData.append('amount', calcData.totalCost);
        quoteFormData.append('quantity', calcData.quantity);
        quoteFormData.append('height', calcData.height);
        quoteFormData.append('width', calcData.width);
        quoteFormData.append('client_id', window.currentClientId);
        quoteFormData.append('calculation_data', JSON.stringify(fullData));

        fetch('quotation_handler.php', {
          method: 'POST',
          body: quoteFormData
        })
        .then(response => {
          if (!response.ok) {
            return response.text().then(text => { throw new Error('HTTP error: ' + response.status + '\n' + text); });
          }
          const contentType = response.headers.get('content-type');
          if (contentType && contentType.includes('application/json')) {
            return response.json();
          } else {
            return response.text().then(text => { throw new Error('Invalid response type.\n' + text); });
          }
        })
        .then(data => {
          if (data.success) {
            showToast('Added to quotation!', 'success');
          } else {
            showToast('Error: ' + (data.error || 'Failed to add'), 'danger');
          }
        })
        .catch(error => {
          console.error('Error adding to quotation:', error);
          showToast('Error: ' + error.message, 'danger');
        });

        // Save separately
        const saveFormData = new FormData();
        saveFormData.append('action', 'save_calculation');
        saveFormData.append('client_id', window.currentClientId);
        saveFormData.append('company_id', window.currentCompanyId);
        saveFormData.append('window_type', 'Openable Door');
        saveFormData.append('height', fullData.dimensions.height);
        saveFormData.append('width', fullData.dimensions.width);
        saveFormData.append('quantity', fullData.dimensions.quantity);
        saveFormData.append('total_area', fullData.dimensions.area);
        saveFormData.append('frame_length', fullData.materials.frame.length);
        saveFormData.append('sash_length', fullData.materials.sash.length);
        saveFormData.append('mullion_length', fullData.materials.mullion.length);
        saveFormData.append('beading_length', fullData.materials.beading.length);
        saveFormData.append('steel_quantity', fullData.materials.steel.quantity);
        // Add all hardware quantities
        Object.entries(fullData.hardware).forEach(([key, val]) => {
          saveFormData.append(key, val.quantity);
        });
        saveFormData.append('material_cost', fullData.totals.materials);
        saveFormData.append('hardware_cost', fullData.totals.hardware);
        saveFormData.append('glass_cost', fullData.totals.glass);
        saveFormData.append('total_cost', fullData.totals.grandTotal);
        // Add individual material costs
        saveFormData.append('frame_cost', fullData.materials.frame.cost);
        saveFormData.append('sash_cost', fullData.materials.sash.cost);
        saveFormData.append('mullion_cost', fullData.materials.mullion.cost);
        saveFormData.append('beading_cost', fullData.materials.beading.cost);
        saveFormData.append('steel_cost', fullData.materials.steel.cost);
        // Add hardware cost values
        Object.entries(fullData.hardware).forEach(([key, val]) => {
          saveFormData.append(key + '_cost', val.cost);
        });
        // Debug logging
        console.log('Saving data to save_window_calculation.php...', Object.fromEntries(saveFormData));
        console.log('Cost values being sent:', {
          frame_cost: fullData.materials.frame.cost,
          sash_cost: fullData.materials.sash.cost,
          mullion_cost: fullData.materials.mullion.cost,
          beading_cost: fullData.materials.beading.cost,
          steel_cost: fullData.materials.steel.cost,
          hardware_costs: Object.fromEntries(Object.entries(fullData.hardware).map(([k, v]) => [k + '_cost', v.cost]))
        });
        fetch('./Pages/save_window_calculation.php', {
          method: 'POST',
          body: saveFormData
        })
        .then(res => {
          const contentType = res.headers.get('content-type');
          if (contentType && contentType.includes('application/json')) {
            return res.json();
          } else {
            throw new Error('Invalid response type');
          }
        })
        .then(data => {
          if (data.success) {
            alert('Saved successfully');
            window.location.reload();
          } else {
            throw new Error(data.error || 'Save failed');
          }
        })
        .catch(error => {
          console.error('Error saving calculation:', error);
          alert('Error: ' + error.message);
        });
      });

    output.innerHTML = outputHTML;
output.style.display = "block";

quoteBtnContainer.appendChild(addButton);
output.appendChild(quoteBtnContainer);

document.getElementById("sketch").style.display = "block";
output.scrollIntoView({ behavior: 'smooth' });

    }
    
    // Event listeners
     document.getElementById('calculateBtn').addEventListener('click', calculate);

    ['height', 'width', 'quantity'].forEach(id => {
      document.getElementById(id).addEventListener('keypress', function(e) {
        if (e.key === 'Enter') calculate();
      });
    });
  </script>
  
  <script src="quotation.js"></script>
</body>
</html>