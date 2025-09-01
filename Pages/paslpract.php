    <?php
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once(__DIR__ . '/../db.php');


    require_once(__DIR__ . '/../quotation_handler.php');

    // Check if company is selected
    if (!isset($_SESSION['selected_company_id'])) {
        header("Location: dashboard.php");
        exit();
    }

    $company_id = $_SESSION['selected_company_id'];


    // Check if client is selected
    if (!isset($_GET['client_id'])) {
        header("Location: clients.php");
        exit();
    }

    $client_id = $_GET['client_id'];
    // Set client ID in JavaScript scope
    echo "<script>window.currentClientId = $client_id;</script>";

    $stmt = $conn->prepare("SELECT id, name FROM clients WHERE id = ? AND company_id = ?");
    $stmt->bind_param("ii", $client_id, $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $client = $result->fetch_assoc();
    $stmt->close();

    if (!$client) {
        $_SESSION['message'] = "Client not found or doesn't belong to your company";
        $_SESSION['message_type'] = "danger";
        header("Location: /modern/dashboard.php?page=clients");
        exit();
    }

    // Fetch all prices in optimized queries
    $prices = ['materials' => [], 'hardware' => [], 'additional' => []];

    // Fetch material prices
    $stmt = $conn->prepare("SELECT name, price_per_foot FROM materials WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $prices['materials'][$row['name']] = $row['price_per_foot'];
        $prices['additional'][$row['name']] = $row['price_per_foot']; // Reuse for additional materials
    }
    $stmt->close();

    // Fetch hardware prices
    $stmt = $conn->prepare("SELECT name, price FROM hardware WHERE company_id = ?");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $prices['hardware'][$row['name']] = $row['price'];
    }
    $stmt->close();

    // Default glass price (configurable per company)
    $glass_price_per_sqft = 200; // Can be fetched from database
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>2PSL Window Calculator</title>
    <link rel="icon" type="image/x-icon" href="./logo/mod.jpg">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"> -->
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
        padding: 12px 15px;
        border: 1px solid #ddd;
        transition: all 0.3s;
        }
        .form-control:focus, .form-select:focus {
        border-color: #4b6cb7;
        box-shadow: 0 0 0 0.25rem rgba(75, 108, 183, 0.25);
        }
        .btn-calculate {
        background: linear-gradient(to right, #4b6cb7, #182848);
        border: none;
        padding: 12px 30px;
        font-weight: 600;
        letter-spacing: 1px;
        transition: all 0.3s;
        }
        .btn-calculate:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .results-container {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        border-left: 5px solid #4b6cb7;
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
        }
        .input-group-unit {
        width: 100px;
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
        }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="calculator-container animate__animated animate__fadeIn">
            <div class="calculator-header text-center">
                <h2 class="animate__animated animate__fadeInDown">2PSL Window Calculator</h2>
                <p class="mb-0 animate__animated animate__fadeInUp animate__delay-1s">Calculate materials and costs</p>
            </div>
            
            <div class="p-4">
                <div class="row">
                    <!-- Width -->
                <div class="col-md-6 mb-3">
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
                <div class="col-md-6 mb-3">
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
                
              
                <!-- Quantity -->
                <div class="col-md-6 mb-3">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" class="form-control" id="quantity" placeholder="Enter quantity" min="1" value="1">
                </div>
                </div>
                
                <div class="text-center mt-4">
                <button id="calculateBtn" class="btn btn-calculate btn-lg">
                    <i class="fas fa-calculator me-2"></i>Calculate Materials & Costs
                </button>
                </div>
                
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
        const netRubber = ((netSashLength * 19) / 80).toFixed(2);  // Matches HTML formula
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
        
        // Generate output HTML
        const outputHTML = `
            <h5 class="mb-3"><i class="fas fa-calculator me-2"></i>Calculation Results</h5>
            
            <div class="result-item">
            <span class="result-label">Quantity:</span>
            <span class="result-value">${quantity}</span>
            </div>
            <div class="result-item">
            <span class="result-label">Height:</span>
            <span class="result-value">${heightValue} ${heightUnit} (${heightFt.toFixed(2)} ft)</span>
            </div>
            <div class="result-item">
            <span class="result-label">Width:</span>
            <span class="result-value">${widthValue} ${widthUnit} (${widthFt.toFixed(2)} ft)</span>
            </div>
            <div class="result-item">
            <span class="result-label">Total Area:</span>
            <span class="result-value">${totalArea.toFixed(2)} sft</span>
            </div>
            
            <h6 class="section-title mt-4"><i class="fas fa-ruler-combined me-2"></i>Main Materials</h6>
            ${createResultRow('Frame Length', (perimeter/19).toFixed(2), 'lengths', frameCost)}
            ${createResultRow('Sash Length', (perimeter/19*1.5).toFixed(2), 'lengths', sashCost)}
            ${createResultRow('Net Sash Length', (perimeter/19*0.75).toFixed(2), 'lengths', netSashCost)}
            ${createResultRow('Beading Length', (perimeter/19*1.5).toFixed(2), 'lengths', beadingCost)}
            ${createResultRow('Interlock Length', (perimeter/19*0.5).toFixed(2), 'lengths', interlockCost)}
            
            <h6 class="section-title mt-4"><i class="fas fa-boxes me-2"></i>Material Requirements</h6>
           ${createResultRow('Steel', (steel / 19).toFixed(2), '', steelCost)}
            ${createResultRow('Net', net, 'sft', netCost)}
            ${createResultRow('Net Rubber', netRubber, '', netRubberCost)}
            ${createResultRow('Burshi', burshi, 'ft', burshiCost)}
            
            <h6 class="section-title mt-4"><i class="fas fa-tools me-2"></i>Hardware Items</h6>
            ${Object.entries(hardwareItems).map(([name, qty]) => 
            createResultRow(name, qty, 'pcs', hardwareCosts[name])).join('')}
            ${Object.entries(otherHardware).map(([name, qty]) => 
            createResultRow(name, qty, name === 'Self Screw' ? 'kg' : 'pcs', hardwareCosts[name])).join('')}
            
            <h6 class="section-title mt-4"><i class="fas fa-window-maximize me-2"></i>Glass</h6>
            ${createResultRow('6mm Plain Glass', totalArea.toFixed(2), 'sft', glassCost)}
            
            <div class="result-total mt-4 p-3 bg-light rounded">
            ${createResultRow('Total Materials Cost', '', '', totalMaterialCost, true)}
            ${createResultRow('Total Hardware Cost', '', '', totalHardwareCost, true)}
            ${createResultRow('Glass Cost', '', '', glassCost, true)}
            ${createResultRow('Grand Total', '', '', grandTotal, true, 'fs-5')}
            </div>
        `;
        
        function createResultRow(label, value, unit, amount, isTotal = false, extraClass = '') {
            return `
            <div class="result-item ${extraClass}">
                <span class="result-label">${label}${value ? ` (${value} ${unit})` : ''}</span>
                <span class="result-value price-value">${formatCurrency(amount)}</span>
            </div>
            `;
        }
        
        // Display results
        const output = document.getElementById("output");
        output.innerHTML = outputHTML;
        output.style.display = "block";
        
        // Add quotation buttons
        const quoteBtnContainer = document.createElement('div');
        

        createQuotationButtons(quoteBtnContainer, '2PSL', () => {
        // Convert to feet first
        const heightValue = parseFloat(document.getElementById("height").value);
        const heightUnit = document.getElementById("heightUnit").value;
        const widthValue = parseFloat(document.getElementById("width").value);
        const widthUnit = document.getElementById("widthUnit").value;
        
        const heightFt = convertToFeet(heightValue, heightUnit);
        const widthFt = convertToFeet(widthValue, widthUnit);
        
        return {
            area: heightFt * widthFt * quantity,
            quantity: quantity,
            totalCost: grandTotal,
            height: heightFt,
            width: widthFt,
            unit: 'ft',
            _source: '3psl_calculator',
            // Include original values for debugging
            original: {
                height: heightValue,
                width: widthValue,
                unit: heightUnit // Assuming both dimensions use same unit
            }
        };
    });
        output.appendChild(quoteBtnContainer);
        
        // Generate window sketch
        generateWindowSketch(heightValue, heightUnit, widthValue, widthUnit);
        
        // Scroll to results
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
        document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('calculateBtn').addEventListener('click', calculate);
        
        // Allow pressing Enter in input fields to calculate
        ['height', 'width', 'quantity'].forEach(id => {
            document.getElementById(id).addEventListener('keypress', function(e) {
            if (e.key === 'Enter') calculate();
            });
        });
        });
    </script>


    <script src="quotation.js"></script>
    </body>
    </html>