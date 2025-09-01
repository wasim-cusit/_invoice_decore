<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../quotation_handler.php');

// Get parameters from session (set by ajax_get_calculator.php)
$company_id = (int)($_SESSION['calculation_company_id'] ?? 0);
$product_type = $_SESSION['calculation_product_type'] ?? '';
$sub_type = $_SESSION['calculation_sub_type'] ?? '';
$client_id = (int)($_SESSION['calculation_client_id'] ?? 0);

// Validate parameters
if (!$company_id || !$client_id) {
    die('<div class="alert alert-danger">Missing required parameters</div>');
}

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
  <title>3PSL Window Calculator</title>
  <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      overflow: hidden;
      transition: all 0.3s ease;
      margin: 20px 0;
      max-width: 1600px;
      margin-left: auto;
      margin-right: auto;
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
    .input-group-unit {
      width: 90px;
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
    .quotation-buttons {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 20px;
    }
    
    /* Multi-input styles */
    .calculation-input-set {
      border: 1px solid #e9ecef;
      border-radius: 10px;
      padding: 20px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      position: relative;
    }
    
    .calculation-input-set:hover {
      border-color: #4b6cb7;
      box-shadow: 0 2px 8px rgba(75, 108, 183, 0.1);
    }
    
    .calculation-input-set:first-child {
      background: white;
      border-color: #4b6cb7;
    }
    
    .add-calculation, .remove-calculation {
      font-weight: 600;
      border-radius: 6px;
      transition: all 0.3s ease;
    }
    
    .add-calculation:hover {
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    }
    
    .remove-calculation:hover {
      transform: translateY(-1px);
      box-shadow: 0 3px 10px rgba(220, 53, 69, 0.2);
    }
    
    .add-calculation:active, .remove-calculation:active, .btn-calculate:active {
      transform: translateY(0);
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    
    .calculation-counter {
      position: absolute;
      top: -10px;
      right: -10px;
      background: #4b6cb7;
      color: white;
      border-radius: 50%;
      width: 24px;
      height: 24px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
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
            <h2 class="animate__animated animate__fadeInDown">3PSL Window Calculator</h2>
            <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs</p>
          </div>
          <div class="p-4">
            <form id="windowCalcForm" autocomplete="off">
              <div id="calculationInputs">
                <!-- First calculation input set -->
                <div class="calculation-input-set mb-4" data-set-id="1">
                  <div class="calculation-counter">1</div>
                  <div class="row g-3 align-items-end">
                    <div class="col-md-3 mb-3">
                      <label class="form-label"><i class="fa-solid fa-arrows-left-right me-1"></i>Width</label>
                      <div class="input-group">
                        <input type="number" class="form-control width-input" placeholder="Enter width" step="0.01" min="0.01">
                        <select class="form-select input-group-unit width-unit">
                          <option value="ft">feet</option>
                          <option value="in">inches</option>
                          <option value="cm">centimeters</option>
                          <option value="mm">millimeters</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-3 mb-3">
                      <label class="form-label"><i class="fa-solid fa-arrows-up-down me-1"></i>Height</label>
                      <div class="input-group">
                        <input type="number" class="form-control height-input" placeholder="Enter height" step="0.01" min="0.01">
                        <select class="form-select input-group-unit height-unit">
                          <option value="ft">feet</option>
                          <option value="in">inches</option>
                          <option value="cm">centimeters</option>
                          <option value="mm">millimeters</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-md-2 mb-3">
                      <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Quantity</label>
                      <input type="number" class="form-control quantity-input" placeholder="Enter quantity" min="1" value="1">
                    </div>
                    <div class="col-md-2 mb-3">
                      <button type="button" class="btn btn-danger btn-sm remove-calculation" style="display: none;">
                        <i class="fas fa-trash me-1"></i>Remove
                      </button>
                    </div>
                    <div class="col-md-2 mb-3">
                      <button type="button" class="btn btn-info btn-sm add-calculation">
                        <i class="fas fa-plus me-1"></i>Add New
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              
              <div class="row justify-content-center mt-4">
                <div class="col-md-6 d-grid">
                  <button type="button" id="calculateBtn" class="btn btn-calculate btn-lg">
                    <i class="fas fa-calculator me-2"></i>Calculate All
                  </button>
                </div>
              </div>
            </form>
            <div class="results-container mt-4" id="output" style="display: none;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Set client and company IDs from PHP
    window.currentClientId = <?php echo $client_id; ?>;
    window.currentCompanyId = <?php echo $company_id; ?>;
    
    // Database prices from PHP
    const prices = <?php echo json_encode($prices); ?>;
    const glassPricePerSqft = <?php echo $glass_price_per_sqft; ?>;
    
    console.log('Prices loaded:', prices);
    console.log('Glass price per sqft:', glassPricePerSqft);
    
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
      switch(unit) {
        case 'in': return `${value} in`;
        case 'cm': return `${value} cm`;
        case 'mm': return `${value} mm`;
        default: return `${value} ft`;
      }
    }
    
    // Add/Remove calculation input sets
    let calculationSetCounter = 2;
    
    function addCalculationSet() {
      console.log('=== ADD CALCULATION SET CALLED ===');
      
      try {
        const container = document.getElementById('calculationInputs');
        if (!container) {
          console.error('Calculation inputs container not found!');
          return;
        }
        
        const newSet = document.createElement('div');
        newSet.className = 'calculation-input-set mb-4';
        newSet.setAttribute('data-set-id', calculationSetCounter);
        
        newSet.innerHTML = `
          <div class="calculation-counter">${calculationSetCounter}</div>
          <div class="row g-3 align-items-end">
            <div class="col-md-3 mb-3">
              <label class="form-label"><i class="fa-solid fa-arrows-left-right me-1"></i>Width</label>
              <div class="input-group">
                <input type="number" class="form-control width-input" placeholder="Enter width" step="0.01" min="0.01">
                <select class="form-select input-group-unit width-unit">
                  <option value="ft">feet</option>
                  <option value="in">inches</option>
                  <option value="cm">centimeters</option>
                  <option value="mm">millimeters</option>
                </select>
              </div>
            </div>
            <div class="col-md-3 mb-3">
              <label class="form-label"><i class="fa-solid fa-arrows-up-down me-1"></i>Height</label>
              <div class="input-group">
                <input type="number" class="form-control height-input" placeholder="Enter height" step="0.01" min="0.01">
                <select class="form-select input-group-unit height-unit">
                  <option value="ft">feet</option>
                  <option value="in">inches</option>
                  <option value="cm">centimeters</option>
                  <option value="mm">millimeters</option>
                </select>
              </div>
            </div>
            <div class="col-md-2 mb-3">
              <label class="form-label"><i class="fa-solid fa-hashtag me-1"></i>Quantity</label>
              <input type="number" class="form-control quantity-input" placeholder="Enter quantity" min="1" value="1">
            </div>
            <div class="col-md-2 mb-3">
              <button type="button" class="btn btn-danger btn-sm remove-calculation">
                <i class="fas fa-trash me-1"></i>Remove
              </button>
            </div>
            <div class="col-md-2 mb-3">
              <button type="button" class="btn btn-info btn-sm add-calculation">
                <i class="fas fa-plus me-1"></i>Add New
              </button>
            </div>
          </div>
        `;
        
        container.appendChild(newSet);
        updateRemoveButtons();
        calculationSetCounter++;
        
        // Add visual feedback
        newSet.style.opacity = '0';
        newSet.style.transform = 'translateY(-10px)';
        setTimeout(() => {
          newSet.style.transition = 'all 0.3s ease';
          newSet.style.opacity = '1';
          newSet.style.transform = 'translateY(0)';
        }, 10);
        
        console.log('New calculation set added successfully');
      } catch (error) {
        console.error('Error adding calculation set:', error);
      }
    }
    
    function removeCalculationSet(setElement) {
      console.log('=== REMOVE CALCULATION SET CALLED ===');
      setElement.remove();
      updateRemoveButtons();
      updateCalculationCounters();
    }
    
    function updateRemoveButtons() {
      const sets = document.querySelectorAll('.calculation-input-set');
      sets.forEach((set, index) => {
        const removeBtn = set.querySelector('.remove-calculation');
        if (removeBtn) {
          removeBtn.style.display = index === 0 ? 'none' : 'block';
        }
      });
      updateCalculationCounters();
    }
    
    function updateCalculationCounters() {
      const sets = document.querySelectorAll('.calculation-input-set');
      sets.forEach((set, index) => {
        const counter = set.querySelector('.calculation-counter');
        if (counter) {
          counter.textContent = index + 1;
          set.setAttribute('data-set-id', index + 1);
        }
      });
      calculationSetCounter = sets.length + 1;
    }
    
    function getAllCalculationInputs() {
      const sets = document.querySelectorAll('.calculation-input-set');
      const inputs = [];
      
      console.log('Getting all calculation inputs from', sets.length, 'sets');
      
      sets.forEach((set, index) => {
        const widthInput = set.querySelector('.width-input');
        const heightInput = set.querySelector('.height-input');
        const quantityInput = set.querySelector('.quantity-input');
        const widthUnit = set.querySelector('.width-unit');
        const heightUnit = set.querySelector('.height-unit');
        
        console.log(`Set ${index + 1}:`, {
          width: widthInput?.value,
          height: heightInput?.value,
          quantity: quantityInput?.value,
          widthUnit: widthUnit?.value,
          heightUnit: heightUnit?.value
        });
        
        if (widthInput && heightInput && quantityInput && 
            widthInput.value && heightInput.value && quantityInput.value) {
          inputs.push({
            setId: index + 1,
            width: parseFloat(widthInput.value),
            height: parseFloat(heightInput.value),
            quantity: parseInt(quantityInput.value),
            widthUnit: widthUnit.value,
            heightUnit: heightUnit.value
          });
        }
      });
      
      console.log('Valid inputs found:', inputs.length);
      return inputs;
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
    
    // Main calculation function for 3PSL windows
    function calculate() {
      console.log('Calculate function called');
      
      // Get all calculation inputs
      const allInputs = getAllCalculationInputs();
      console.log('All inputs:', allInputs);
      
      if (allInputs.length === 0) {
        return showError("Please enter at least one set of dimensions");
      }
      
      let allCalculations = [];
      let grandTotalAll = 0;
      let totalAreaAll = 0;
      
      // Calculate for each input set
      allInputs.forEach((input, index) => {
        const heightValue = input.height;
        const heightUnit = input.heightUnit;
        const widthValue = input.width;
        const widthUnit = input.widthUnit;
        const quantity = input.quantity;
        
        // Validate inputs
        if (isNaN(heightValue) || heightValue <= 0) return showError(`Invalid height in calculation ${index + 1}`);
        if (isNaN(widthValue) || widthValue <= 0) return showError(`Invalid width in calculation ${index + 1}`);
        if (isNaN(quantity) || quantity < 1) return showError(`Invalid quantity in calculation ${index + 1}`);
        
        // Convert to feet for calculations
        const heightFt = convertToFeet(heightValue, heightUnit);
        const widthFt = convertToFeet(widthValue, widthUnit);
        
        if (heightFt <= 0 || widthFt <= 0) return showError(`Height and width must be positive values in calculation ${index + 1}`);
        
        // 3PSL specific calculations
        const perimeter = (heightFt + widthFt) * 2;
        const area = heightFt * widthFt;
        const totalArea = area * quantity;
        
        // Material lengths (3PSL specific)
        const frameLength = (perimeter / 19) * quantity;
        const sashLength = frameLength * 1.8;
        const netSashLength = sashLength / 2;
        const beadingLength = sashLength;
        const interlockLength = sashLength / 3;
        
        // Material requirements
        const steel = ((frameLength + sashLength + netSashLength) * 19) / 8;
        const net = (totalArea / 3) * 2;
        const netRubber = (netSashLength * 19) / 80;
        const burshi = (widthFt * 4 + heightFt * 7) * quantity;
        
        // Calculate costs
        const calculateCost = (length, material) => length * (prices.materials[material] || 0);
        
        const frameCost = calculateCost(frameLength * 19, 'Frame');
        const sashCost = calculateCost(sashLength * 19, 'Sash');
        const netSashCost = calculateCost(netSashLength * 19, 'Net Sash');
        const beadingCost = calculateCost(beadingLength * 19, 'Beading');
        const interlockCost = calculateCost(interlockLength * 19, 'Interlock');
        
        const steelCost = steel * (prices.additional['Steel'] || 0);
        const netCost = net * (prices.additional['Net'] || 0);
        const netRubberCost = netRubber * (prices.additional['Net Rubber'] || 0);
        const burshiCost = burshi * (prices.additional['Burshi'] || 0);
        
        // Hardware calculations for 3PSL
        const hardwareItems = {
          'Locks': quantity * 2,
          'Dummy': quantity * 2,
          'Boofer': quantity * 4,
          'Stopper': quantity * 4,
          'Double Wheel': quantity * 4,
          'Net Wheel': quantity * 4
        };
        
        // Screws and other items
        const otherHardware = {
          'Fitting Screw': quantity * 30,
          'Self Screw': quantity * 70,
          'Sada Screw': quantity * 40,
          'Rawal Plug': quantity * 30,
          'Silicon White': quantity * 2,
          'Hole Caps': quantity * 30,
          'Water Caps': quantity * 2
        };
        
        // Calculate hardware costs
        let totalHardwareCost = 0;
        const hardwareCosts = {};
        
        [...Object.entries(hardwareItems), ...Object.entries(otherHardware)].forEach(([name, qty]) => {
          const cost = qty * (prices.hardware[name] || 0);
          hardwareCosts[name] = cost;
          totalHardwareCost += cost;
        });
        
        // Glass calculation
        const glassCost = totalArea * glassPricePerSqft;
        
        // Calculate totals
        const totalMaterialCost = frameCost + sashCost + netSashCost + beadingCost + interlockCost + 
                                 steelCost + netCost + netRubberCost + burshiCost;
        const grandTotal = totalMaterialCost + totalHardwareCost + glassCost;
        
        // Store calculation data
        allCalculations.push({
          setId: input.setId,
          dimensions: {
            height: heightFt,
            width: widthFt,
            quantity: quantity,
            area: totalArea,
            original: {
              height: heightValue,
              width: widthValue,
              unit: heightUnit
            }
          },
          materials: {
            frame: { length: frameLength.toFixed(2), cost: frameCost },
            sash: { length: sashLength.toFixed(2), cost: sashCost },
            netSash: { length: netSashLength.toFixed(2), cost: netSashCost },
            beading: { length: beadingLength.toFixed(2), cost: beadingCost },
            interlock: { length: interlockLength.toFixed(2), cost: interlockCost },
            steel: { quantity: steel.toFixed(2), cost: steelCost },
            net: { area: net.toFixed(2), cost: netCost },
            netRubber: { quantity: netRubber.toFixed(2), cost: netRubberCost },
            burshi: { length: burshi.toFixed(2), cost: burshiCost }
          },
          hardware: {
            locks: { quantity: quantity * 2, cost: hardwareCosts['Locks'] || 0 },
            dummy: { quantity: quantity * 2, cost: hardwareCosts['Dummy'] || 0 },
            boofer: { quantity: quantity * 4, cost: hardwareCosts['Boofer'] || 0 },
            stopper: { quantity: quantity * 4, cost: hardwareCosts['Stopper'] || 0 },
            doubleWheel: { quantity: quantity * 4, cost: hardwareCosts['Double Wheel'] || 0 },
            netWheel: { quantity: quantity * 4, cost: hardwareCosts['Net Wheel'] || 0 },
            sadaScrew: { quantity: quantity * 40, cost: hardwareCosts['Sada Screw'] || 0 },
            fittingScrew: { quantity: quantity * 30, cost: hardwareCosts['Fitting Screw'] || 0 },
            selfScrew: { quantity: quantity * 70, cost: hardwareCosts['Self Screw'] || 0 },
            rawalPlug: { quantity: quantity * 30, cost: hardwareCosts['Rawal Plug'] || 0 },
            siliconWhite: { quantity: quantity * 2, cost: hardwareCosts['Silicon White'] || 0 },
            holeCaps: { quantity: quantity * 30, cost: hardwareCosts['Hole Caps'] || 0 },
            waterCaps: { quantity: quantity * 2, cost: hardwareCosts['Water Caps'] || 0 }
          },
          totals: {
            materials: totalMaterialCost,
            hardware: totalHardwareCost,
            glass: glassCost,
            grandTotal: grandTotal
          }
        });
        
        grandTotalAll += grandTotal;
        totalAreaAll += totalArea;
      });
      
      console.log('All calculations completed:', allCalculations);
      
      // Generate output HTML for multiple calculations
      let outputHTML = `
        <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>3PSL Window Calculation Results (${allCalculations.length} calculations)</h5>
        <div class="results-grid">
          <!-- Summary Card -->
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Overall Summary</h6>
              ${createResultRow('Total Calculations', allCalculations.length, '')}
              ${createResultRow('Total Area', `${totalAreaAll.toFixed(2)} sft`, '')}
              ${createResultRow('Grand Total', '', formatCurrency(grandTotalAll), true, 'grand-total')}
            </div>
          </div>
        </div>
      `;
      
      // Add individual calculation cards
      allCalculations.forEach((calc, index) => {
        outputHTML += `
          <div class="result-card">
            <div class="result-section">
              <h6 class="section-title">Calculation ${index + 1}</h6>
              ${createResultRow('Dimensions', `${calc.dimensions.original.width} × ${calc.dimensions.original.height} ${calc.dimensions.original.unit}`, '')}
              ${createResultRow('Quantity', calc.dimensions.quantity, '')}
              ${createResultRow('Area', `${calc.dimensions.area.toFixed(2)} sft`, '')}
              ${createResultRow('Frame', calc.materials.frame.length + ' lengths', formatCurrency(calc.materials.frame.cost))}
              ${createResultRow('Sash', calc.materials.sash.length + ' lengths', formatCurrency(calc.materials.sash.cost))}
              ${createResultRow('Net Sash', calc.materials.netSash.length + ' lengths', formatCurrency(calc.materials.netSash.cost))}
              ${createResultRow('Beading', calc.materials.beading.length + ' lengths', formatCurrency(calc.materials.beading.cost))}
              ${createResultRow('Interlock', calc.materials.interlock.length + ' lengths', formatCurrency(calc.materials.interlock.cost))}
              ${createResultRow('Steel', calc.materials.steel.quantity + ' kg', formatCurrency(calc.materials.steel.cost))}
              ${createResultRow('Net', calc.materials.net.area + ' sft', formatCurrency(calc.materials.net.cost))}
              ${createResultRow('Net Rubber', calc.materials.netRubber.quantity, formatCurrency(calc.materials.netRubber.cost))}
              ${createResultRow('Burshi', calc.materials.burshi.length + ' ft', formatCurrency(calc.materials.burshi.cost))}
              <div class="result-total">
                ${createResultRow('Sub Total', '', formatCurrency(calc.totals.grandTotal), true)}
              </div>
            </div>
          </div>
        `;
      });
      
      // Display results
      const output = document.getElementById("output");
      output.innerHTML = outputHTML;
      output.style.display = "block";
      
      // Create quotation button
      const quoteBtnContainer = document.createElement('div');
      quoteBtnContainer.className = 'quotation-buttons';
      
      const addButton = document.createElement('button');
      addButton.className = 'btn btn-success btn-lg';
      addButton.id = 'addToQuotationBtn';
      addButton.innerHTML = `<i class="fas fa-plus me-2"></i>Add to Quotation`;
      addButton.style.fontWeight = '600';
      addButton.style.padding = '12px 24px';
      
      // Create save button
      const saveButton = document.createElement('button');
      saveButton.className = 'btn btn-primary btn-lg ms-2';
      saveButton.id = 'saveCalculationBtn';
      saveButton.innerHTML = `<i class="fas fa-save me-2"></i>Save Calculations`;
      saveButton.style.fontWeight = '600';
      saveButton.style.padding = '12px 24px';
      
      function showToast(message, type = 'info') {
        alert(`${type.toUpperCase()}: ${message}`);
      }
      
      addButton.addEventListener('click', function() {
        const calcData = {
          calculations: allCalculations,
          totalArea: totalAreaAll,
          totalCost: grandTotalAll,
          _source: '3psl_calculator'
        };
        
        // Add each calculation to quotation
        calcData.calculations.forEach((calc, index) => {
          const quoteFormData = new FormData();
          quoteFormData.append('action', 'add_item');
          quoteFormData.append('window_type', '3PSL');
          quoteFormData.append('description', `3PSL Window ${index + 1} (${calc.dimensions.original.width}×${calc.dimensions.original.height} ${calc.dimensions.original.unit})`);
          quoteFormData.append('unit', 'Sft');
          quoteFormData.append('area', calc.dimensions.area);
          quoteFormData.append('rate', calc.totals.grandTotal / calc.dimensions.area);
          quoteFormData.append('amount', calc.totals.grandTotal);
          quoteFormData.append('quantity', calc.dimensions.quantity);
          quoteFormData.append('height', calc.dimensions.height);
          quoteFormData.append('width', calc.dimensions.width);
          quoteFormData.append('client_id', window.currentClientId);
          quoteFormData.append('height_original', calc.dimensions.original.height);
          quoteFormData.append('width_original', calc.dimensions.original.width);
          quoteFormData.append('unit_original', calc.dimensions.original.unit);
          
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
              console.log(`Added calculation ${index + 1} to quotation`);
            } else {
              console.error(`Error adding calculation ${index + 1}:`, data.error || 'Failed to add');
            }
          })
          .catch(error => {
            console.error(`Error adding calculation ${index + 1}:`, error);
          });
        });
        
        showToast(`Added ${calcData.calculations.length} calculations to quotation!`, 'success');
      });
      
      // Add save functionality
      saveButton.addEventListener('click', function() {
        const calcData = {
          calculations: allCalculations,
          totalArea: totalAreaAll,
          totalCost: grandTotalAll,
          _source: '3psl_calculator'
        };
        
        let savedCount = 0;
        const totalCalculations = calcData.calculations.length;
        
        if (totalCalculations === 0) {
          showToast('No calculations to save!', 'warning');
          return;
        }
        
        showToast(`Saving ${totalCalculations} calculations...`, 'info');
        
        // Save each calculation to database
        calcData.calculations.forEach((calc, index) => {
          const saveFormData = new FormData();
          saveFormData.append('action', 'save_calculation');
          saveFormData.append('client_id', window.currentClientId);
          saveFormData.append('company_id', window.currentCompanyId);
          saveFormData.append('window_type', '3PSL');
          saveFormData.append('height', calc.dimensions.height);
          saveFormData.append('width', calc.dimensions.width);
          saveFormData.append('quantity', calc.dimensions.quantity);
          saveFormData.append('total_area', calc.dimensions.area);
          saveFormData.append('frame_length', calc.materials.frame.length);
          saveFormData.append('sash_length', calc.materials.sash.length);
          saveFormData.append('net_sash_length', calc.materials.netSash.length);
          saveFormData.append('beading_length', calc.materials.beading.length);
          saveFormData.append('interlock_length', calc.materials.interlock.length);
          saveFormData.append('steel_quantity', calc.materials.steel.quantity);
          saveFormData.append('net_area', calc.materials.net.area);
          saveFormData.append('net_rubber_quantity', calc.materials.netRubber.quantity);
          saveFormData.append('burshi_length', calc.materials.burshi.length);
          saveFormData.append('locks', calc.hardware.locks.quantity);
          saveFormData.append('dummy', calc.hardware.dummy.quantity);
          saveFormData.append('boofer', calc.hardware.boofer.quantity);
          saveFormData.append('stopper', calc.hardware.stopper.quantity);
          saveFormData.append('double_wheel', calc.hardware.doubleWheel.quantity);
          saveFormData.append('net_wheel', calc.hardware.netWheel.quantity);
          saveFormData.append('sada_screw', calc.hardware.sadaScrew.quantity);
          saveFormData.append('fitting_screw', calc.hardware.fittingScrew.quantity);
          saveFormData.append('self_screw', calc.hardware.selfScrew.quantity);
          saveFormData.append('rawal_plug', calc.hardware.rawalPlug.quantity);
          saveFormData.append('silicon_white', calc.hardware.siliconWhite.quantity);
          saveFormData.append('hole_caps', calc.hardware.holeCaps.quantity);
          saveFormData.append('water_caps', calc.hardware.waterCaps.quantity);
          
          // Add cost values
          saveFormData.append('frame_cost', calc.materials.frame.cost);
          saveFormData.append('sash_cost', calc.materials.sash.cost);
          saveFormData.append('net_sash_cost', calc.materials.netSash.cost);
          saveFormData.append('beading_cost', calc.materials.beading.cost);
          saveFormData.append('interlock_cost', calc.materials.interlock.cost);
          saveFormData.append('steel_cost', calc.materials.steel.cost);
          saveFormData.append('net_cost', calc.materials.net.cost);
          saveFormData.append('net_rubber_cost', calc.materials.netRubber.cost);
          saveFormData.append('burshi_cost', calc.materials.burshi.cost);
          saveFormData.append('material_cost', calc.totals.materials);
          saveFormData.append('hardware_cost', calc.totals.hardware);
          saveFormData.append('glass_cost', calc.totals.glass);
          saveFormData.append('total_cost', calc.totals.grandTotal);
          
          console.log(`Saving calculation ${index + 1}...`);
          
          fetch('./Pages/save_window_calculation.php', {
            method: 'POST',
            body: saveFormData
          })
          .then(response => {
            if (!response.ok) {
              throw new Error(`HTTP error: ${response.status}`);
            }
            return response.json();
          })
          .then(data => {
            if (data.success) {
              savedCount++;
              console.log(`Calculation ${index + 1} saved successfully`);
              
              if (savedCount === totalCalculations) {
                showToast(`All ${totalCalculations} calculations saved successfully!`, 'success');
                // Optionally reload the page after successful save
                setTimeout(() => {
                  if (confirm('All calculations saved! Would you like to reload the page?')) {
                    window.location.reload();
                  }
                }, 1000);
              }
            } else {
              throw new Error(data.error || 'Save failed');
            }
          })
          .catch(error => {
            console.error(`Error saving calculation ${index + 1}:`, error.message);
            showToast(`Error saving calculation ${index + 1}: ${error.message}`, 'error');
          });
        });
      });
      
      quoteBtnContainer.appendChild(addButton);
      quoteBtnContainer.appendChild(saveButton);
      output.appendChild(quoteBtnContainer);
      
      // Scroll to results
      output.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Event listeners
    function initializeEventListeners() {
      console.log('=== INITIALIZING EVENT LISTENERS ===');
      
      // Calculate button - remove old listeners and add new one
      const calculateBtn = document.getElementById('calculateBtn');
      if (calculateBtn) {
        console.log('Calculate button found:', calculateBtn);
        // Clone the button to remove old event listeners
        const newCalculateBtn = calculateBtn.cloneNode(true);
        calculateBtn.parentNode.replaceChild(newCalculateBtn, calculateBtn);
        
        newCalculateBtn.addEventListener('click', function(e) {
          console.log('=== CALCULATE BUTTON CLICKED ===');
          e.preventDefault();
          e.stopPropagation();
          
          // Add visual feedback
          this.style.transform = 'scale(0.95)';
          setTimeout(() => {
            this.style.transform = '';
          }, 150);
          
          calculate();
        });
      } else {
        console.error('Calculate button not found!');
      }
      
      // Event delegation for add/remove buttons
      document.removeEventListener('click', handleButtonClicks);
      document.addEventListener('click', handleButtonClicks);
      
      updateRemoveButtons();
      console.log('=== EVENT LISTENERS INITIALIZED ===');
    }
    
    function handleButtonClicks(e) {
      try {
        console.log('Button click detected:', e.target);
        console.log('Target classes:', e.target.className);
        console.log('Closest add-calculation:', e.target.closest('.add-calculation'));
        console.log('Closest remove-calculation:', e.target.closest('.remove-calculation'));
        
        if (e.target.closest('.add-calculation')) {
          console.log('=== ADD BUTTON CLICKED ===');
          e.preventDefault();
          e.stopPropagation();
          
          // Add visual feedback
          const button = e.target.closest('.add-calculation');
          button.style.transform = 'scale(0.95)';
          setTimeout(() => {
            button.style.transform = '';
          }, 150);
          
          addCalculationSet();
        }
        
        if (e.target.closest('.remove-calculation')) {
          console.log('=== REMOVE BUTTON CLICKED ===');
          e.preventDefault();
          e.stopPropagation();
          
          // Add visual feedback
          const button = e.target.closest('.remove-calculation');
          button.style.transform = 'scale(0.95)';
          setTimeout(() => {
            button.style.transform = '';
          }, 150);
          
          const setElement = e.target.closest('.calculation-input-set');
          if (setElement) {
            removeCalculationSet(setElement);
          }
        }
      } catch (error) {
        console.error('Error in button click handler:', error);
      }
    }
    
    // Initialize event listeners when DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initializeEventListeners);
    } else {
      // DOM is already loaded
      initializeEventListeners();
    }
    
    // Also try after a short delay as a safeguard
    setTimeout(initializeEventListeners, 100);
    
    // Debug: Check if buttons exist
    setTimeout(() => {
      console.log('=== DEBUGGING BUTTONS ===');
      const addButtons = document.querySelectorAll('.add-calculation');
      const removeButtons = document.querySelectorAll('.remove-calculation');
      const calculateBtn = document.getElementById('calculateBtn');
      
      console.log('Add buttons found:', addButtons.length);
      console.log('Remove buttons found:', removeButtons.length);
      console.log('Calculate button found:', calculateBtn ? 'YES' : 'NO');
      
      addButtons.forEach((btn, index) => {
        console.log(`Add button ${index + 1}:`, btn);
      });
      
      removeButtons.forEach((btn, index) => {
        console.log(`Remove button ${index + 1}:`, btn);
      });
    }, 500);
  </script>
</body>
</html>
