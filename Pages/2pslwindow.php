<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../quotation_handler.php');
require_once(__DIR__ . '/../db.php');

// Debug output
error_log("Session: " . print_r($_SESSION, true));
error_log("GET: " . print_r($_GET, true));

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
echo "<script>
window.currentClientId = " . json_encode($client_id) . ";
window.currentCompanyId = " . json_encode($company_id) . ";
</script>";

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
<title>2PSL Window Calculator</title>
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
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: all 0.3s ease;
    margin: 20px 0;
    /* You can increase or decrease the max-width below to control the form width */
    max-width: 1600px;
    margin-left: auto;
    margin-right: auto;
    }
    .calculator-container:hover {
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
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
    .input-group-unit {
    width: 90px;
    }
    .quotation-buttons {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
    }
    .sketch-container {
    margin-top: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    }
    .alert-quotation {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    min-width: 300px;
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
            <h2 class="animate__animated animate__fadeInDown">2PSL Window Calculator</h2>
            <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs</p>
        </div>
        
        <div class="p-4">
            <form id="windowCalcForm" autocomplete="off">
                <div class="row g-3 align-items-end">
                    <!-- Width -->
                <div class="col-md-4 mb-3">
                        <label for="width" class="form-label">Width</label>
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
                        <label for="height" class="form-label">Height</label>
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
                    
                    <div class="col-md-2 mb-3">
                        <label for="quantity" class="form-label">Quantity</label>
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
            <div class="sketch-container text-center" id="sketch" style="display: none;"></div>
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
    
    function showError(message) {
    const output = document.getElementById("output");
    output.innerHTML = `
        <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>${message}
        </div>
    `;
    output.style.display = "block";
    }
    
    // Main calculation function
    function calculate() {
        console.log("Calculate triggered! ✅");
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
        const perimeter = (heightFt + widthFt) * 2;
        const area = heightFt * widthFt;
        const totalArea = area * quantity;
        
        // Material lengths
        const frameLength = perimeter * quantity;
        const sashLength = frameLength * 1.5;
        const netSashLength = sashLength * 0.5;
        const beadingLength = sashLength;
        const interlockLength = frameLength * 0.5;
        
        // Material requirements (19ft lengths)
        const steel = ((frameLength + sashLength + netSashLength) * 19 / 8).toFixed(2);
        const netRubber = ((netSashLength * 19) / 80).toFixed(2);
        const net = (totalArea / 2).toFixed(2);
        const burshi = (heightFt * 7 * quantity).toFixed(2);
        
        // Calculate costs
        const calculateCost = (length, material) => length * (prices.materials[material] || 0);
        
        const frameCost = calculateCost(frameLength, 'Frame');
        const sashCost = calculateCost(sashLength, 'Sash');
        const netSashCost = calculateCost(netSashLength, 'Net Sash');
        const beadingCost = calculateCost(beadingLength, 'Beading');
        const interlockCost = calculateCost(interlockLength, 'Interlock');
        
        const steelCost = steel * (prices.additional['Steel'] || 0);
        const netCost = net * (prices.additional['Net'] || 0);
        const netRubberCost = netRubber * (prices.additional['Net Rubber'] || 0);
        const burshiCost = burshi * (prices.additional['Burshi'] || 0);
        
        // Hardware calculations
        const hardwareItems = {
            'Locks': quantity,
            'Dummy': quantity * 2,
            'Boofer': quantity * 2,
            'Stopper': quantity * 2,
            'Double Wheel': quantity * 2,
            'Net Wheel': quantity * 2
        };
    
        // Screws calculation based on window size
        const windowSize = Math.min(heightFt, widthFt);
        let fittingScrew, selfScrew;
        
        if (windowSize <= 2) [fittingScrew, selfScrew] = [8, 25];
        else if (windowSize <= 3) [fittingScrew, selfScrew] = [10, 50];
        else if (windowSize <= 4) [fittingScrew, selfScrew] = [12, 75];
        else if (windowSize <= 5) [fittingScrew, selfScrew] = [16, 100];
        else [fittingScrew, selfScrew] = [20, 125];
        
        // Adjust for quantity
        fittingScrew *= quantity;
        selfScrew *= quantity;
        
        // Other hardware
        const otherHardware = {
            'Sada Screw': 20 * quantity,
            'Fitting Screw': fittingScrew,
            'Self Screw': selfScrew,
            'Rawal Plug': fittingScrew,
            'Silicon White': quantity * 2,
            'Hole Caps': fittingScrew,
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
        
        // Generate output HTML with horizontal layout
        const outputHTML = `
            <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Calculation Results</h5>
            
            <div class="results-grid">
                <!-- Basic Info Card -->
                <div class="result-card">
                    <div class="result-section">
                        <h6 class="section-title">Window Details</h6>
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
                        ${createResultRow('Frame', (perimeter/19).toFixed(2) + ' lengths', formatCurrency(frameCost))}
                        ${createResultRow('Sash', (perimeter/19*1.5).toFixed(2) + ' lengths', formatCurrency(sashCost))}
                        ${createResultRow('Net Sash', (perimeter/19*0.75).toFixed(2) + ' lengths', formatCurrency(netSashCost))}
                        ${createResultRow('Beading', (perimeter/19*1.5).toFixed(2) + ' lengths', formatCurrency(beadingCost))}
                        ${createResultRow('Interlock', (perimeter/19*0.5).toFixed(2) + ' lengths', formatCurrency(interlockCost))}
                    </div>
                </div>
                
                <!-- Material Requirements Card -->
                <div class="result-card">
                    <div class="result-section">
                        <h6 class="section-title">Material Requirements</h6>
                        ${createResultRow('Steel', (steel / 19).toFixed(2) + ' lengths', formatCurrency(steelCost))}
                        ${createResultRow('Net', net + ' sft', formatCurrency(netCost))}
                        ${createResultRow('Net Rubber', netRubber, formatCurrency(netRubberCost))}
                        ${createResultRow('Burshi', burshi + ' ft', formatCurrency(burshiCost))}
                    </div>
                </div>
                
                <!-- Hardware Card -->
                <div class="result-card">
                    <div class="result-section">
                        <h6 class="section-title">Hardware Items</h6>
                        ${Object.entries(hardwareItems).map(([name, qty]) => 
                            createResultRow(name, qty + ' pcs', formatCurrency(hardwareCosts[name]))).join('')}
                        ${Object.entries(otherHardware).map(([name, qty]) => 
                            createResultRow(name, qty + (name === 'Self Screw' ? ' kg' : ' pcs'), formatCurrency(hardwareCosts[name]))).join('')}
                    </div>
                </div>
                
                <!-- Glass Card -->
                <div class="result-card">
                    <div class="result-section">
                        <h6 class="section-title">Glass</h6>
                        ${createResultRow('6mm Plain Glass', totalArea.toFixed(2) + ' sft', formatCurrency(glassCost))}
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
    
        function createResultRow(label, value, amount, isTotal = false, extraClass = '') {
            const amountDisplay = amount ? `<span class="price-value ${extraClass}">${amount}</span>` : '';
            return `
            <div class="result-item ${isTotal ? 'total-item' : ''}">
                <span class="result-label">${label}</span>
                <span class="result-value">${value} ${amountDisplay}</span>
            </div>
            `;
        }
    
        function prepareFullCalculation(heightFt, widthFt, area) {
            return {
                dimensions: {
                    height: heightFt,
                    width: widthFt,
                    quantity: quantity,
                    area: heightFt * widthFt * quantity
                },
                materials: {
                    frame: { length: (perimeter / 19).toFixed(2), cost: frameCost },
                    sash: { length: (perimeter / 19 * 1.5).toFixed(2), cost: sashCost },
                    netSash: { length: (perimeter / 19 * 0.75).toFixed(2), cost: netSashCost },
                    beading: { length: (perimeter / 19 * 1.5).toFixed(2), cost: beadingCost },
                    interlock: { length: (perimeter / 19 * 0.5).toFixed(2), cost: interlockCost },
                    steel: { quantity: (steel / 19).toFixed(2), cost: steelCost },
                    net: { area: net, cost: netCost },
                    netRubber: { quantity: netRubber, cost: netRubberCost },
                    burshi: { length: burshi, cost: burshiCost }
                },
                hardware: {
                    locks: { quantity: quantity, cost: hardwareCosts['Locks'] || 0 },
                    dummy: { quantity: quantity * 2, cost: hardwareCosts['Dummy'] || 0 },
                    boofer: { quantity: quantity * 2, cost: hardwareCosts['Boofer'] || 0 },
                    stopper: { quantity: quantity * 2, cost: hardwareCosts['Stopper'] || 0 },
                    doubleWheel: { quantity: quantity * 2, cost: hardwareCosts['Double Wheel'] || 0 },
                    netWheel: { quantity: quantity * 2, cost: hardwareCosts['Net Wheel'] || 0 },
                    sadaScrew: { quantity: 20 * quantity, cost: hardwareCosts['Sada Screw'] || 0 },
                    fittingScrew: { quantity: fittingScrew, cost: hardwareCosts['Fitting Screw'] || 0 },
                    selfScrew: { quantity: selfScrew, cost: hardwareCosts['Self Screw'] || 0 },
                    rawalPlug: { quantity: fittingScrew, cost: hardwareCosts['Rawal Plug'] || 0 },
                    siliconWhite: { quantity: quantity * 2, cost: hardwareCosts['Silicon White'] || 0 },
                    holeCaps: { quantity: fittingScrew, cost: hardwareCosts['Hole Caps'] || 0 },
                    waterCaps: { quantity: quantity * 2, cost: hardwareCosts['Water Caps'] || 0 }
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
            const heightValue = parseFloat(document.getElementById("height").value);
            const heightUnit = document.getElementById("heightUnit").value;
            const widthValue = parseFloat(document.getElementById("width").value);
            const widthUnit = document.getElementById("widthUnit").value;

            let heightFt = convertToFeet(heightValue, heightUnit);
            let widthFt = convertToFeet(widthValue, widthUnit);
            let area = heightFt * widthFt * quantity;

            return {
                area,
                quantity,
                totalCost: grandTotal,
                height: heightFt,
                width: widthFt,
                unit: 'ft',
                _source: '2psl_calculator',
                original: {
                    height: heightValue,
                    width: widthValue,
                    unit: heightUnit
                },
                fullData: prepareFullCalculation(heightFt, widthFt, area)
            };
        };

        const addButton = document.createElement('button');
        addButton.className = 'btn btn-success';
        addButton.id = 'addToQuotationBtn';
        addButton.innerHTML = `<i class="fas fa-plus"></i> Add to Quotation`;

        function showToast(message, type = 'info') {
            alert(`${type.toUpperCase()}: ${message}`);
        }

        addButton.addEventListener('click', function() {
            const calcData = getCalculationData();
            const fullData = calcData.fullData;

            const quoteFormData = new FormData();
            quoteFormData.append('action', 'add_item');
            quoteFormData.append('window_type', '2PSL');
            quoteFormData.append('description', '2PSL Window');
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
            });

            const saveFormData = new FormData();
            saveFormData.append('action', 'save_calculation');
            saveFormData.append('client_id', window.currentClientId);
            saveFormData.append('company_id', window.currentCompanyId);
            saveFormData.append('window_type', '2PSL');
            saveFormData.append('height', fullData.dimensions.height);
            saveFormData.append('width', fullData.dimensions.width);
            saveFormData.append('quantity', fullData.dimensions.quantity);
            saveFormData.append('total_area', fullData.dimensions.area);
            saveFormData.append('frame_length', fullData.materials.frame.length);
            saveFormData.append('sash_length', fullData.materials.sash.length);
            saveFormData.append('net_sash_length', fullData.materials.netSash.length);
            saveFormData.append('beading_length', fullData.materials.beading.length);
            saveFormData.append('interlock_length', fullData.materials.interlock.length);
            saveFormData.append('steel_quantity', fullData.materials.steel.quantity);
            saveFormData.append('net_area', fullData.materials.net.area);
            saveFormData.append('net_rubber_quantity', fullData.materials.netRubber.quantity);
            saveFormData.append('burshi_length', fullData.materials.burshi.length);
            saveFormData.append('locks', fullData.hardware.locks.quantity);
            saveFormData.append('dummy', fullData.hardware.dummy.quantity);
            saveFormData.append('boofer', fullData.hardware.boofer.quantity);
            saveFormData.append('stopper', fullData.hardware.stopper.quantity);
            saveFormData.append('double_wheel', fullData.hardware.doubleWheel.quantity);
            saveFormData.append('net_wheel', fullData.hardware.netWheel.quantity);
            saveFormData.append('sada_screw', fullData.hardware.sadaScrew.quantity);
            saveFormData.append('fitting_screw', fullData.hardware.fittingScrew.quantity);
            saveFormData.append('self_screw', fullData.hardware.selfScrew.quantity);
            saveFormData.append('rawal_plug', fullData.hardware.rawalPlug.quantity);
            saveFormData.append('silicon_white', fullData.hardware.siliconWhite.quantity);
            saveFormData.append('hole_caps', fullData.hardware.holeCaps.quantity);
            saveFormData.append('water_caps', fullData.hardware.waterCaps.quantity);
            // Add individual cost values
            saveFormData.append('frame_cost', fullData.materials.frame.cost);
            saveFormData.append('sash_cost', fullData.materials.sash.cost);
            saveFormData.append('net_sash_cost', fullData.materials.netSash.cost);
            saveFormData.append('beading_cost', fullData.materials.beading.cost);
            saveFormData.append('interlock_cost', fullData.materials.interlock.cost);
            saveFormData.append('steel_cost', fullData.materials.steel.cost);
            saveFormData.append('net_cost', fullData.materials.net.cost);
            saveFormData.append('net_rubber_cost', fullData.materials.netRubber.cost);
            saveFormData.append('burshi_cost', fullData.materials.burshi.cost);
            saveFormData.append('locks_cost', fullData.hardware.locks.cost);
            saveFormData.append('dummy_cost', fullData.hardware.dummy.cost);
            saveFormData.append('boofer_cost', fullData.hardware.boofer.cost);
            saveFormData.append('stopper_cost', fullData.hardware.stopper.cost);
            saveFormData.append('double_wheel_cost', fullData.hardware.doubleWheel.cost);
            saveFormData.append('net_wheel_cost', fullData.hardware.netWheel.cost);
            saveFormData.append('sada_screw_cost', fullData.hardware.sadaScrew.cost);
            saveFormData.append('fitting_screw_cost', fullData.hardware.fittingScrew.cost);
            saveFormData.append('self_screw_cost', fullData.hardware.selfScrew.cost);
            saveFormData.append('rawal_plug_cost', fullData.hardware.rawalPlug.cost);
            saveFormData.append('silicon_white_cost', fullData.hardware.siliconWhite.cost);
            saveFormData.append('hole_caps_cost', fullData.hardware.holeCaps.cost);
            saveFormData.append('water_caps_cost', fullData.hardware.waterCaps.cost);
            saveFormData.append('material_cost', fullData.totals.materials);
            saveFormData.append('hardware_cost', fullData.totals.hardware);
            saveFormData.append('glass_cost', fullData.totals.glass);
            saveFormData.append('total_cost', fullData.totals.grandTotal);
            console.log('Saving data to save_window_calculation.php...', Object.fromEntries(saveFormData));
            console.log('Cost values being sent:', {
                frame_cost: fullData.materials.frame.cost,
                sash_cost: fullData.materials.sash.cost,
                net_sash_cost: fullData.materials.netSash.cost,
                beading_cost: fullData.materials.beading.cost,
                interlock_cost: fullData.materials.interlock.cost,
                steel_cost: fullData.materials.steel.cost,
                net_cost: fullData.materials.net.cost,
                net_rubber_cost: fullData.materials.netRubber.cost,
                burshi_cost: fullData.materials.burshi.cost,
                locks_cost: fullData.hardware.locks.cost,
                dummy_cost: fullData.hardware.dummy.cost,
                boofer_cost: fullData.hardware.boofer.cost,
                stopper_cost: fullData.hardware.stopper.cost,
                double_wheel_cost: fullData.hardware.doubleWheel.cost,
                net_wheel_cost: fullData.hardware.netWheel.cost,
                sada_screw_cost: fullData.hardware.sadaScrew.cost,
                fitting_screw_cost: fullData.hardware.fittingScrew.cost,
                self_screw_cost: fullData.hardware.selfScrew.cost,
                rawal_plug_cost: fullData.hardware.rawalPlug.cost,
                silicon_white_cost: fullData.hardware.siliconWhite.cost,
                hole_caps_cost: fullData.hardware.holeCaps.cost,
                water_caps_cost: fullData.hardware.waterCaps.cost
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
        generateWindowSketch(heightFt, heightUnit, widthFt, widthUnit);
        output.scrollIntoView({ behavior: 'smooth' });
    }
    
    // Generate window sketch SVG
    function generateWindowSketch(height, heightUnit, width, widthUnit) {
        const aspectRatio = height / width;
        const frameWidth = 300;
        const frameHeight = frameWidth * aspectRatio;
        
        let inputWidth = document.getElementById("width").value;
        let inputHeight = document.getElementById("height").value;

        let svg = `<svg width="${frameWidth + 60}" height="${frameHeight + 60}" style="border:1px solid #ddd; background: white; border-radius: 8px;" xmlns="http://www.w3.org/2000/svg">
        <g transform="translate(30,30)">
            <rect x="0" y="0" width="${frameWidth}" height="${frameHeight}" fill="none" stroke="#4b6cb7" stroke-width="2"/>
            <line x1="${frameWidth / 2}" y1="0" x2="${frameWidth / 2}" y2="${frameHeight}" stroke="black" stroke-width="2"/>
            <text x="${frameWidth / 2 - 10}" y="-10" font-size="12" fill="#4b6cb7">Width: ${inputWidth} </text>
            <text x="-25" y="${frameHeight / 2}" font-size="12" fill="#4b6cb7" transform="rotate(-88 -10, ${frameHeight / 2})">Height: ${inputHeight} </text>
            <text x="${frameWidth/4 - 10}" y="${frameHeight/2 + 5}" font-size="16" fill="#182848">→</text>
            <text x="${frameWidth/4 - 20}" y="${frameHeight/2 - 10}" font-size="10" fill="#666">Move</text>
            <text x="${(frameWidth * 3) / 4 - 10}" y="${frameHeight / 2}" font-size="12" fill="red">FIX</text>
        </g>
        </svg>`;
        
        document.getElementById("sketch").innerHTML = svg;
        document.getElementById("sketch").style.display = "block";
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