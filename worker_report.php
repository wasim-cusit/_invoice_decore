<?php
require_once 'db.php';

// Get parameters from URL
$quotation_number = isset($_GET['quotation_number']) ? $_GET['quotation_number'] : '';
$client_id = isset($_GET['client_id']) ? max(0, (int)$_GET['client_id']) : 0;
$company_id = isset($_GET['company_id']) ? max(0, (int)$_GET['company_id']) : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? $_GET['per_page'] : 5;
$show_all = ($per_page === '0' || $per_page === 'all');

// Fetch window types for this quotation
$window_types = [];
if (!empty($quotation_number)) {
    $window_sql = "SELECT window_types FROM quotations 
                  WHERE quotation_number = '$quotation_number' 
                  AND client_id = $client_id 
                  AND company_id = $company_id";
    $window_result = $conn->query($window_sql);
    if ($window_result && $window_result->num_rows > 0) {
        $row = $window_result->fetch_assoc();
        $window_types = !empty($row['window_types']) ? explode(',', $row['window_types']) : [];
    }
}

// Define window types with their images
$windowTypeImages = [
    '2psl' => ['label' => '2 Panel Sliding', 'img' => 'Pages/image/2psl.png'],
    '3psl' => ['label' => '3 Panel Sliding', 'img' => 'Pages/image/3psl.png'],
    'fix' => ['label' => 'Fixed Window', 'img' => 'Pages/image/fix.png'],
    'halfdoor' => ['label' => 'Half Window', 'img' => 'Pages/image/halfdoor.png'],
    'fulldoor' => ['label' => 'Full Door', 'img' => 'Pages/image/fulldoor.png'],
    'openable' => ['label' => 'Openable Window', 'img' => 'Pages/image/openable.png'],
    'tophung' => ['label' => 'Top Hung', 'img' => 'Pages/image/tophung.png'],
    'glass' => ['label' => 'Glass Door', 'img' => 'Pages/image/glass.png'],
];

// Fetch total count
$count_sql = "SELECT COUNT(*) as total FROM window_calculation_details 
             WHERE quotation_number = '$quotation_number' 
             AND client_id = $client_id 
             AND company_id = $company_id";
$count_result = $conn->query($count_sql);
$total_rows = $count_result->fetch_assoc()['total'];

// Calculate pagination
if (!$show_all) {
    $per_page = max(1, (int)$per_page);
    $total_pages = ceil($total_rows / $per_page);
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $per_page;
} else {
    $total_pages = 1;
    $page = 1;
}

// Fetch calculation details
$sql = "SELECT * FROM window_calculation_details 
        WHERE quotation_number = '$quotation_number' 
        AND client_id = $client_id 
        AND company_id = $company_id";

if (!$show_all) {
    $sql .= " LIMIT $offset, $per_page";
}

$result = $conn->query($sql);

$calculations = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $calculations[] = $row;
    }
}

// Fetch client and company info if available
$client_info = [];
$company_info = [];
if (!empty($calculations)) {
    $first_calc = $calculations[0];
    
    // Get client info
    $client_sql = "SELECT name, phone FROM clients WHERE id = " . $first_calc['client_id'];
    $client_result = $conn->query($client_sql);
    if ($client_result && $client_result->num_rows > 0) {
        $client_info = $client_result->fetch_assoc();
    }
    
    // Get company info
    $company_sql = "SELECT name FROM companies WHERE id = " . $first_calc['company_id'];
    $company_result = $conn->query($company_sql);
    if ($company_result && $company_result->num_rows > 0) {
        $company_info = $company_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Window Calculation Report</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 7px;
            background-color: #fff;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            padding: 7px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        
        .report-header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 7px;
        }
        
        .report-title {
            color: #0066cc;
            margin-bottom: 5px;
            font-size: 20px;
            font-weight: bold;
        }
        
        .report-subtitle {
            font-size: 14px;
            color: #555;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            flex-wrap: wrap;
            font-size: 13px;
        }
        
        .info-box {
            margin-bottom: 7px;
            padding: 7px;
            background-color: #f5f9ff;
            border-radius: 3px;
            flex: 1;
            min-width: 180px;
            margin: 0 7px 7px 7px;
            border: 1px solid #e0e9ff;
        }
        
        .info-box strong {
            color: #0066cc;
            font-size: 13px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: auto;
            font-size: 12px;
        }
        
        th, td {
            padding: 7px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        th {
            background-color: #0066cc;
            color: white;
            font-weight: 600;
            font-size: 13px;
        }
        
        tr:nth-child(even) {
            background-color: #f5f9ff;
        }
        
        .no-print {
            display: block;
        }
        
        /* Window Gallery Styles */
        .window-gallery {
            margin: 20px 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .window-item {
            text-align: center;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 5px;
            background: white;
        }
        
        .window-img {
            max-width: 120px;
            max-height: 120px;
            margin-bottom: 5px;
            border: 1px solid #eee;
        }
        
        .window-label {
            font-weight: bold;
            font-size: 13px;
        }
        
        @media print {
            body {
                padding: 0;
                font-size: 12px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .container {
                box-shadow: none;
                padding: 0;
                width: 100%;
            }
            
            table {
                font-size: 11px;
            }
            
            th, td {
                padding: 5px;
            }
            
            .report-header {
                border-bottom: 2px solid #0066cc;
            }
            
            .pagination {
                display: none;
            }
            
            .window-gallery {
                page-break-inside: avoid;
            }
            
            .window-item {
                display: inline-block;
                vertical-align: top;
                margin: 5px;
                page-break-inside: avoid;
            }
            
            .window-img {
                max-width: 100px;
            }
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        
        .btn {
            display: inline-block;
            padding: 7px 14px;
            margin: 0 5px 5px 0;
            text-decoration: none;
            border-radius: 3px;
            font-size: 13px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            border: 1px solid transparent;
        }
        
        .btn-print {
            background-color: #0066cc;
            color: white;
        }
        
        .btn-print:hover {
            background-color: #0055aa;
        }
        
        .btn-back {
            background-color: #555;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #444;
        }
        
        .btn-page {
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-page:hover {
            background-color: #e0e0e0;
        }
        
        .btn-page.active {
            background-color: #0066cc;
            color: white;
            border-color: #0055aa;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #0066cc;
            margin: 15px 0 7px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #e0e9ff;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        
        .print-controls {
            background-color: #f5f9ff;
            padding: 7px;
            border-radius: 3px;
            margin-bottom: 10px;
            border: 1px solid #e0e9ff;
        }
        
        .print-controls label {
            font-size: 13px;
            margin-right: 10px;
        }
        
        .print-controls select {
            padding: 5px;
            border-radius: 3px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="report-header">
            <h1 class="report-title">WINDOW CALCULATION REPORT</h1>
            <?php if (!empty($calculations)): ?>
                <p class="report-subtitle">QUOTATION #: <?= htmlspecialchars($calculations[0]['quotation_number']) ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($calculations)): ?>
            <div class="report-info">
                <div class="info-box">
                    <strong>CLIENT:</strong> <?= htmlspecialchars($client_info['name'] ?? 'N/A') ?><br>
                    <strong>PHONE:</strong> <?= htmlspecialchars($client_info['phone'] ?? 'N/A') ?>
                </div>
                <div class="info-box">
                    <strong>COMPANY:</strong> <?= htmlspecialchars($company_info['name'] ?? 'N/A') ?><br>
                    <strong>DATE:</strong> <?= date('M d, Y') ?>
                </div>
            </div>
            
            <!-- Display Selected Window Types -->
            <?php if (!empty($window_types)): ?>
                <div class="section-title">SELECTED WINDOW TYPES</div>
                <div class="window-gallery">
                    <?php foreach ($window_types as $type): 
                        $type = trim($type);
                        if (isset($windowTypeImages[$type])) {
                            $window = $windowTypeImages[$type];
                            $imagePath = file_exists($window['img']) ? $window['img'] : "Pages/image/default.png";
                    ?>
                    <div class="window-item">
                        <img src="<?= htmlspecialchars($imagePath) ?>" class="window-img" alt="<?= htmlspecialchars($window['label']) ?>">
                        <div class="window-label"><?= htmlspecialchars($window['label']) ?></div>
                    </div>
                    <?php } endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="print-controls no-print">
                <label for="per_page">Items per page:</label>
                <select id="per_page" onchange="updatePerPage(this.value)">
                    <option value="5" <?= $per_page == 5 ? 'selected' : '' ?>>5</option>
                    <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                    <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                    <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                    <option value="0" <?= $show_all ? 'selected' : '' ?>>All</option>
                </select>
            </div>
            
            <div class="section-title">WINDOW MEASUREMENTS</div>
            <table>
                <thead>
                    <tr>
                        <th>Window Type</th>
                        <th>Height</th>
                        <th>Width</th>
                        <th>Qty</th>
                        <th>Total Area</th>
                        <th>Frame Length</th>
                        <th>Sash Length</th>
                        <th>Net Sash</th>
                        <th>Beading</th>
                        <th>Interlock</th>
                        <th>Steel Qty</th>
                        <th>Net Area</th>
                        <th>Net Rubber</th>
                        <th>Burshi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculations as $calc): ?>
                    <tr>
                        <td><?= htmlspecialchars($calc['window_type']) ?></td>
                        <td><?= htmlspecialchars($calc['height']) ?></td>
                        <td><?= htmlspecialchars($calc['width']) ?></td>
                        <td><?= htmlspecialchars($calc['quantity']) ?></td>
                        <td><?= htmlspecialchars($calc['total_area']) ?></td>
                        <td><?= htmlspecialchars($calc['frame_length']) ?></td>
                        <td><?= htmlspecialchars($calc['sash_length']) ?></td>
                        <td><?= htmlspecialchars($calc['net_sash_length']) ?></td>
                        <td><?= htmlspecialchars($calc['beading_length']) ?></td>
                        <td><?= htmlspecialchars($calc['interlock_length']) ?></td>
                        <td><?= htmlspecialchars($calc['steel_quantity']) ?></td>
                        <td><?= htmlspecialchars($calc['net_area']) ?></td>
                        <td><?= htmlspecialchars($calc['net_rubber_quantity']) ?></td>
                        <td><?= htmlspecialchars($calc['burshi_length']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="section-title">FITTINGS AND COMPONENTS</div>
            <table>
                <thead>
                    <tr>
                        <th>Locks</th>
                        <th>Dummy</th>
                        <th>Boofer</th>
                        <th>Stopper</th>
                        <th>Double Wheel</th>
                        <th>Net Wheel</th>
                        <th>Sada Screw</th>
                        <th>Fitting Screw</th>
                        <th>Self Screw</th>
                        <th>Rawal Plug</th>
                        <th>Silicon White</th>
                        <th>Hole Caps</th>
                        <th>Water Caps</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($calculations as $calc): ?>
                    <tr>
                        <td><?= htmlspecialchars($calc['locks']) ?></td>
                        <td><?= htmlspecialchars($calc['dummy']) ?></td>
                        <td><?= htmlspecialchars($calc['boofer']) ?></td>
                        <td><?= htmlspecialchars($calc['stopper']) ?></td>
                        <td><?= htmlspecialchars($calc['double_wheel']) ?></td>
                        <td><?= htmlspecialchars($calc['net_wheel']) ?></td>
                        <td><?= htmlspecialchars($calc['sada_screw']) ?></td>
                        <td><?= htmlspecialchars($calc['fitting_screw']) ?></td>
                        <td><?= htmlspecialchars($calc['self_screw']) ?></td>
                        <td><?= htmlspecialchars($calc['rawal_plug']) ?></td>
                        <td><?= htmlspecialchars($calc['silicon_white']) ?></td>
                        <td><?= htmlspecialchars($calc['hole_caps']) ?></td>
                        <td><?= htmlspecialchars($calc['water_caps']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (!$show_all && $total_pages > 1): ?>
            <div class="pagination no-print">
                <?php if ($page > 1): ?>
                    <a href="?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&page=1&per_page=<?= $per_page ?>" class="btn btn-page">First</a>
                    <a href="?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&page=<?= $page-1 ?>&per_page=<?= $per_page ?>" class="btn btn-page">Previous</a>
                <?php endif; ?>
                
                <?php 
                // Show page numbers
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&page=<?= $i ?>&per_page=<?= $per_page ?>" class="btn btn-page <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&page=<?= $page+1 ?>&per_page=<?= $per_page ?>" class="btn btn-page">Next</a>
                    <a href="?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&page=<?= $total_pages ?>&per_page=<?= $per_page ?>" class="btn btn-page">Last</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="action-bar no-print">
                <div>
                    <a href="javascript:history.back()" class="btn btn-back">Go Back</a>
                </div>
                <div>
                    <button onclick="printReport()" class="btn btn-print">Print Report</button>
                </div>
            </div>
            
        <?php else: ?>
            <p>No calculation details found for the specified ID.</p>
            <div class="action-bar no-print">
                <a href="javascript:history.back()" class="btn btn-back">Go Back</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function updatePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        }
        
        function printReport() {
            const url = new URL(window.location.href);
            const page = url.searchParams.get('page') || 1;
            const per_page = url.searchParams.get('per_page') || 5;
            
            if (confirm('Print all pages? Click OK for all pages, Cancel for current page only.')) {
                window.location.href = `?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&print=true&per_page=0`;
            } else {
                window.location.href = `?quotation_number=<?= $quotation_number ?>&client_id=<?= $client_id ?>&company_id=<?= $company_id ?>&print=true&page=${page}&per_page=${per_page}`;
            }
        }
        
        // Auto-print if print parameter is set
        if (window.location.search.includes('print=true')) {
            window.print();
            
            // Close the window after printing if not in preview
            window.onafterprint = function() {
                setTimeout(() => {
                    if (!window.matchMedia('print').matches) {
                        window.close();
                    }
                }, 500);
            };
        }
    </script>
</body>
</html>