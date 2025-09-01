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
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title> Glass Door </title>
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <style>
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }

    .calculator-container {
      background: white;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      transition: all 0.3s ease;
      margin: 20px 0;
      /* You can increase or decrease the max-width below to control the form width */
      max-width: 1600px;
      margin-left: auto;
      margin-right: auto;
    }

    .calculator-container:hover {
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .calculator-header {
      background: linear-gradient(to right, #4b6cb7, #182848);
      color: white;
      padding: 20px;
      margin-bottom: 20px;
    }

    .form-control,
    .form-select {
      border-radius: 8px;
      padding: 8px 12px;
      border: 1px solid #ddd;
      font-size: 0.98rem;
      transition: all 0.3s;
    }

    .form-control:focus,
    .form-select:focus {
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
    }

    .btn-calculate:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

    .result-item {
      padding: 8px 0;
      border-bottom: 1px solid #eee;
      display: flex;
      justify-content: space-between;
    }

    .result-item:last-child {
      border-bottom: none;
    }

    .result-label {
      font-weight: 600;
      color: #182848;
    }

    .result-value {
      text-align: right;
    }

    .price-value {
      color: #28a745;
      font-weight: bold;
    }

    .section-title {
      color: #4b6cb7;
      border-bottom: 2px solid #4b6cb7;
      padding-bottom: 5px;
      margin-top: 20px;
      margin-bottom: 10px;
      font-size: 16px;
    }

    .input-group-unit {
      width: 90px;
    }

    .sketch-container {
      position: relative;
      margin-top: 30px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 10px;
      text-align: center;
    }

    .result-note {
      font-size: 0.8rem;
      color: #666;
      font-style: italic;
    }

    .dimension-label {
      position: absolute;
      font-weight: bold;
      color: #182848;
      background: rgba(255, 255, 255, 0.8);
      padding: 2px 5px;
      border-radius: 3px;
    }

    .width-label {
      top: -25px;
      left: 50%;
      transform: translateX(-50%);
      white-space: nowrap;
    }

    .height-label {
      left: -20px;
      top: 50%;
      transform: translateY(-50%);
      transform-origin: left center;
      white-space: nowrap;
    }

    .door-image {
      max-width: 100%;
      height: auto;
      border: 1px solid #ddd;
      border-radius: 8px;
    }

    .quotation-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 20px;
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
            <h2 class="animate__animated animate__fadeInDown">Glass Door Calculator</h2>
            <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs for Glass Door</p>
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
                      <option value="feet" selected>feet</option>
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
                      <option value="feet" selected>feet</option>
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
              <img src="Pages/image/glassdoor.jpg" alt="Glass Door Diagram" class="door-image">
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

    function formatCurrency(amount) {
      return 'Rs. ' + amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    function formatUnit(value, unit) {
      switch (unit) {
        case 'in':
          return `${value} in`;
        case 'cm':
          return `${value} cm`;
        case 'mm':
          return `${value} mm`;
        default:
          return `${value} ft`;
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
      const amountDisplay = amount ? `<span class="price-value ${extraClass}">${amount}</span>` : '';
      return `
        <div class="result-item ${isTotal ? 'total-item' : ''}">
          <span class="result-label">${label}</span>
          <span class="result-value">${value} ${amountDisplay}</span>
        </div>
      `;
    }

    // Main calculation function
    function calculate() {
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

      // Convert to feet for calculations
      const heightFt = convertToFeet(heightValue, heightUnit);
      const widthFt = convertToFeet(widthValue, widthUnit);

      if (heightFt <= 0 || widthFt <= 0) return showError("Height and width must be positive values");

      // Calculate dimensions
      const perimeter = (heightFt * 2) + widthFt;
      const area = heightFt * widthFt;
      const totalArea = area * quantity;

      // Material lengths
      const frameLength = perimeter * quantity;
      const doorSashLength = ((widthFt * 2) + (heightFt * 4)) * quantity;
      const mullionLength = heightFt * quantity;
      const beadingLength = doorSashLength;
      const steelLength = (((frameLength + mullionLength) * 19) / 8);

      // Full and half panel calculations
      const fullPanelTemp = (heightFt / 4) * widthFt;
      const fullPanel = (fullPanelTemp / 19) * quantity;
      const halfPanel = (fullPanelTemp / 19 / 2) * quantity;

      // Calculate costs
      const calculateCost = (length, material) => length * (prices.materials[material] || 0);

      const frameCost = calculateCost(frameLength, 'Frame');
      const doorSashCost = calculateCost(doorSashLength, 'Door Sash');
      const mullionCost = calculateCost(mullionLength, 'Mullion');
      const beadingCost = calculateCost(beadingLength, 'Beading');
      const steelCost = steelLength * (prices.additional['Steel'] || 0);
      const fullPanelCost = fullPanel * (prices.additional['Full Panel'] || 0);
      const halfPanelCost = halfPanel * (prices.additional['Half Panel'] || 0);

      // Glass calculation
      const glassCost = (totalArea / 2) * glassPricePerSqft;

      // Hardware calculations
      const hardwareItems = {
        'Fitting Screw': 15 * quantity,
        'Rawal Plug': 15 * quantity,
        'Silicon': 2 * quantity,
        'Self Screw': 60 * quantity,
        'Hinges': 8 * quantity,
        'Locks': 1 * quantity,
        'Tower Bolt': 1 * quantity
      };

      // Calculate hardware costs
      let totalHardwareCost = 0;
      const hardwareCosts = {};

      Object.entries(hardwareItems).forEach(([name, qty]) => {
        const cost = qty * (prices.hardware[name] || 0);
        hardwareCosts[name] = cost;
        totalHardwareCost += cost;
      });

      // Calculate totals
      const totalMaterialCost = frameCost + doorSashCost + mullionCost + beadingCost +
        steelCost + fullPanelCost + halfPanelCost;
      const grandTotal = totalMaterialCost + totalHardwareCost + glassCost;

      // Generate output HTML with horizontal grid layout
      const outputHTML = `
        <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Calculation Results</h5>
        
        <div class="results-grid">
          <!-- Basic Info Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Door Details</h6>
              ${createResultRow('Quantity', quantity, '')}
              ${createResultRow('Height', `${heightValue} ${heightUnit} (${heightFt.toFixed(2)} ft)`)}
              ${createResultRow('Width', `${widthValue} ${widthUnit} (${widthFt.toFixed(2)} ft)`)}
              ${createResultRow('Total Area', `${totalArea.toFixed(2)} sft`)}
            </div>
          </div>
          
          <!-- Main Materials Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Main Materials</h6>
              ${createResultRow('Frame Length', (frameLength/19).toFixed(2) + ' lengths', formatCurrency(frameCost))}
              ${createResultRow('Door Sash Length', (doorSashLength/19).toFixed(2) + ' lengths', formatCurrency(doorSashCost))}
              ${createResultRow('Mullion Length', (mullionLength/19).toFixed(2) + ' lengths', formatCurrency(mullionCost))}
              ${createResultRow('Beading Length', (beadingLength/19).toFixed(2) + ' lengths', formatCurrency(beadingCost))}
              ${createResultRow('Steel Length', (steelLength/19).toFixed(2) + ' lengths', formatCurrency(steelCost))}
            </div>
          </div>
          
          <!-- Panels Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Panels</h6>
              ${createResultRow('Full Panel', fullPanel.toFixed(2) + ' lengths', formatCurrency(fullPanelCost))}
              ${createResultRow('Half Panel', halfPanel.toFixed(2) + ' lengths', formatCurrency(halfPanelCost))}
            </div>
          </div>
          
          <!-- Hardware Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Hardware Items</h6>
              ${Object.entries(hardwareItems).map(([name, qty]) => 
                createResultRow(name, qty + ' pcs', formatCurrency(hardwareCosts[name]))).join('')}
            </div>
          </div>
          
          <!-- Glass Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Glass</h6>
              ${createResultRow('6mm Plain Glass', (totalArea/2).toFixed(2) + ' sft', formatCurrency(glassCost))}
            </div>
          </div>
          
          <!-- Totals Card -->
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
      function prepareFullCalculation(heightFt, widthFt, areaSqft) {
        return {
          dimensions: {
            height: heightFt,
            width: widthFt,
            quantity: quantity,
            area: areaSqft
          },
          materials: {
            frame: {
              length: (frameLength / 19).toFixed(2),
              cost: frameCost
            },
            sash: {
              length: (doorSashLength / 19).toFixed(2),
              cost: doorSashCost
            },
            mullion: {
              length: (mullionLength / 19).toFixed(2),
              cost: mullionCost
            },
            beading: {
              length: (beadingLength / 19).toFixed(2),
              cost: beadingCost
            },
            steel: {
              quantity: steelLength.toFixed(2),
              cost: steelCost
            },
            full_panel: {
              quantity: fullPanel.toFixed(2),
              cost: fullPanelCost
            },
            half_panel: {
              quantity: halfPanel.toFixed(2),
              cost: halfPanelCost
            }
          },
          hardware: {
            ...Object.fromEntries(Object.entries(hardwareItems).map(([name, qty]) => [name.toLowerCase().replace(/\s/g, '_'), {
              quantity: qty,
              cost: hardwareCosts[name]
            }]))
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
          area: totalArea,
          quantity,
          totalCost: grandTotal,
          height: heightFt,
          width: widthFt,
          unit: heightUnit,
          _source: 'glass_door',
          original: {
            height: heightValue,
            width: widthValue,
            unit: heightUnit
          },
          fullData: prepareFullCalculation(heightFt, widthFt, totalArea)
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
        quoteFormData.append('window_type', 'Glass Door');
        quoteFormData.append('description', 'Glass Door');
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
        quoteFormData.append('calculation_data', JSON.stringify(fullData));

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

        // Save separately
        const saveFormData = new FormData();
        saveFormData.append('action', 'save_calculation');
        saveFormData.append('client_id', window.currentClientId);
        saveFormData.append('company_id', window.currentCompanyId);
        saveFormData.append('window_type', 'Glass Door');
        saveFormData.append('height', fullData.dimensions.height);
        saveFormData.append('width', fullData.dimensions.width);
        saveFormData.append('quantity', fullData.dimensions.quantity);
        saveFormData.append('total_area', fullData.dimensions.area);
        saveFormData.append('frame_length', fullData.materials.frame.length);
        saveFormData.append('sash_length', fullData.materials.sash.length);
        saveFormData.append('beading_length', fullData.materials.beading.length);
        saveFormData.append('steel_quantity', fullData.materials.steel.quantity);
        saveFormData.append('full_panel_quantity', fullData.materials.full_panel.quantity); // Not used in PHP, but keep for reference
        saveFormData.append('half_panel_quantity', fullData.materials.half_panel.quantity); // Not used in PHP, but keep for reference
        // The following are not used for glass door, send as 0
        saveFormData.append('net_sash_length', 0);
        saveFormData.append('interlock_length', 0);
        saveFormData.append('net_area', 0);
        saveFormData.append('net_rubber_quantity', 0);
        saveFormData.append('burshi_length', 0);

        // --- HARDWARE ---
        // Map JS keys to PHP keys
        const hardwareMap = {
          fitting_screw: 'fitting_screw',
          rawal_plug: 'rawal_plug',
          silicon: 'silicon_white',
          self_screw: 'self_screw',
          locks: 'locks',
          // The following are not used for glass door, send as 0
          dummy: 'dummy',
          boofer: 'boofer',
          stopper: 'stopper',
          double_wheel: 'double_wheel',
          net_wheel: 'net_wheel',
          sada_screw: 'sada_screw',
          hole_caps: 'hole_caps',
          water_caps: 'water_caps',
        };
        // Set actual hardware values
        Object.entries(hardwareMap).forEach(([jsKey, phpKey]) => {
          if (fullData.hardware[jsKey]) {
            saveFormData.append(phpKey, fullData.hardware[jsKey].quantity);
            saveFormData.append(phpKey + '_cost', fullData.hardware[jsKey].cost);
          } else {
            saveFormData.append(phpKey, 0);
            saveFormData.append(phpKey + '_cost', 0);
          }
        });

        // --- COSTS ---
        saveFormData.append('material_cost', fullData.totals.materials);
        saveFormData.append('hardware_cost', fullData.totals.hardware);
        saveFormData.append('glass_cost', fullData.totals.glass);
        saveFormData.append('total_cost', fullData.totals.grandTotal);

        // Add individual cost values for materials
        saveFormData.append('frame_cost', fullData.materials.frame.cost);
        saveFormData.append('sash_cost', fullData.materials.sash.cost);
        saveFormData.append('beading_cost', fullData.materials.beading.cost);
        saveFormData.append('steel_cost', fullData.materials.steel.cost);
        saveFormData.append('full_panel_cost', fullData.materials.full_panel.cost); // Not used in PHP, but keep for reference
        saveFormData.append('half_panel_cost', fullData.materials.half_panel.cost); // Not used in PHP, but keep for reference
        // The following are not used for glass door, send as 0
        saveFormData.append('net_sash_cost', 0);
        saveFormData.append('interlock_cost', 0);
        saveFormData.append('net_cost', 0);
        saveFormData.append('net_rubber_cost', 0);
        saveFormData.append('burshi_cost', 0);

        console.log('Saving data to save_window_calculation.php...', Object.fromEntries(saveFormData));
        console.log('Cost values being sent:', {
          frame_cost: fullData.materials.frame.cost,
          sash_cost: fullData.materials.sash.cost,
          mullion_cost: fullData.materials.mullion.cost,
          beading_cost: fullData.materials.beading.cost,
          steel_cost: fullData.materials.steel.cost,
          full_panel_cost: fullData.materials.full_panel.cost,
          half_panel_cost: fullData.materials.half_panel.cost,
          glass_cost: fullData.totals.glass
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
              window.location.reload(); // Refresh the page as-is (same URL & params)
            } else {
              throw new Error(data.error || 'Save failed');
            }
          })
          .catch(error => {
            console.error("Error:", error.message);
            alert("Error: " + error.message);
          });
      });

      ;


      // Update door image with dimensions
      document.getElementById("widthLabel").textContent = `${widthValue} ${widthUnit}`;
      document.getElementById("heightLabel").textContent = `${heightValue} ${heightUnit}`;


      document.getElementById("sketch").style.display = "block";


      // Scroll to results
      quoteBtnContainer.appendChild(addButton);
      output.innerHTML = outputHTML;
      output.style.display = "block";
      output.appendChild(quoteBtnContainer);
      document.getElementById("sketch").style.display = "block";
      output.scrollIntoView({
        behavior: 'smooth'
      });

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