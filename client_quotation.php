<?php
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once(__DIR__ . '/db.php');

if (!isset($_GET['client_id']) || !isset($_GET['company_id'])) {
    header("Location: view_quotation.php");
    exit();
}

$company_id = (int)$_GET['company_id'];
$client_id = (int)$_GET['client_id'];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_filter = '';

if (!empty($search_term)) {
    $search_filter = " AND (q.quotation_number LIKE ? OR qi.description LIKE ?)";
}

// Fetch client information
$client_info = [];
$stmt = $conn->prepare("SELECT name, phone, address FROM clients WHERE id = ? AND company_id = ?");
$stmt->bind_param("ii", $client_id, $company_id);
$stmt->execute();
$result = $stmt->get_result();
$client_info = $result->fetch_assoc();
$stmt->close();

// Fetch quotation data
$query = "
    SELECT 
        q.id AS quotation_id,
        q.quotation_number,
        q.date,
        q.total_amount,
        q.window_types,
        qi.description,
        qi.unit,
        qi.area,
        qi.rate_per_sft,
        qi.quantity
    FROM 
        quotations q
    JOIN 
        quotation_items qi ON q.id = qi.quotation_id
    WHERE 
        q.company_id = ? AND q.client_id = ?
        $search_filter
    ORDER BY 
        q.date DESC, q.quotation_number DESC
";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

if (!empty($search_term)) {
    $like = "%$search_term%";
    $stmt->bind_param("iiss", $company_id, $client_id, $like, $like);
} else {
    $stmt->bind_param("ii", $company_id, $client_id);
}

$stmt->execute();
$result = $stmt->get_result();

$quotations = [];

while ($row = $result->fetch_assoc()) {
    $id = $row['quotation_id'];
    if (!isset($quotations[$id])) {
        $quotations[$id] = [
            'quotation_number' => $row['quotation_number'],
            'date' => $row['date'],
            'total_amount' => $row['total_amount'],
            'window_types' => $row['window_types'],
            'items' => []
        ];
    }

    $quotations[$id]['items'][] = [
        'description' => $row['description'],
        'unit' => $row['unit'],
        'area' => $row['area'],
        'rate_per_sft' => $row['rate_per_sft'],
        'quantity' => $row['quantity']
    ];
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Quotations</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            padding: 15px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 15px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 8px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        h1 {
            color: #2c3e50;
            font-size: 1.4rem;
            margin: 0;
        }
        
        .info-section {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .client-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .client-detail {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .quotation-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            margin-left: auto;
        }
        
        .quotation-detail {
            font-size: 0.9rem;
        }
        
        .search-container {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .search-container input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            flex-grow: 1;
            max-width: 200px;
            font-size: 0.9rem;
        }
        
        .search-container button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            gap: 5px;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .quotation-header {
            background-color: #f8f9fa;
            margin: 15px 0;
            padding: 10px 15px;
            border-radius: 5px;
            border-left: 4px solid #6c757d;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px 10px;
            text-align: left;
        }
        
        th {
            background-color: #3498db;
            color: white;
            font-weight: 500;
        }
        
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        tr:hover {
            background-color: #e9ecef;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }
        
        .window-gallery {
            margin: 15px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .window-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .window-image-container {
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            background-color: white;
        }
        
        .window-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 5px;
        }
        
        .window-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: rgba(52, 152, 219, 0.9);
            color: white;
            text-align: center;
            padding: 3px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .info-section {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .quotation-info {
                margin-left: 0;
            }
            
            .search-container {
                flex-direction: column;
            }
            
            .search-container input {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Client Quotations</h1>
        <div style="display: flex; gap: 10px;">
            <a href="index.php?page=report_quotation" class="btn btn-secondary no-print">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <button onclick="window.print()" class="btn no-print">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <div class="info-section">
        <?php if (!empty($client_info)): ?>
            <div class="client-info">
                <div class="client-detail">
                    <strong><?= htmlspecialchars($client_info['name']) ?></strong>
                </div>
                <div class="client-detail">
                    <i class="fas fa-phone"></i> <?= htmlspecialchars($client_info['phone']) ?>
                </div>
                <div class="client-detail">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($client_info['address']) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($quotations)): ?>
            <?php $first_quotation = reset($quotations); ?>
            <div class="quotation-info">
                <div class="quotation-detail">
                    <strong>Quotation #:</strong> <?= htmlspecialchars($first_quotation['quotation_number']) ?>
                </div>
                <div class="quotation-detail">
                    <strong>Date:</strong> <?= htmlspecialchars($first_quotation['date']) ?>
                </div>
                <div class="quotation-detail">
                    <strong>Total:</strong> Rs <?= number_format($first_quotation['total_amount'], 2) ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="search-container no-print">
        <form method="get" style="display: flex; gap: 10px; align-items: center; width: 100%;">
            <input type="hidden" name="company_id" value="<?= $company_id ?>">
            <input type="hidden" name="client_id" value="<?= $client_id ?>">
            <input type="text" name="search" placeholder="Search quotations..." value="<?= htmlspecialchars($search_term) ?>" style="flex-grow: 1;">
            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if (!empty($search_term)): ?>
                <a href="?client_id=<?= $client_id ?>&company_id=<?= $company_id ?>" class="btn btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-times"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($quotations)): ?>
        <div class="no-data">
            <p>No quotations found <?= !empty($search_term) ? 'matching your search' : 'for this client' ?>.</p>
        </div>
    <?php else: ?>
        <?php foreach ($quotations as $quotation): ?>
            <?php if (count($quotations) > 1): ?>
                <div class="quotation-header">
                    <h3>Quotation #<?= htmlspecialchars($quotation['quotation_number']) ?></h3>
                    <p><strong>Date:</strong> <?= htmlspecialchars($quotation['date']) ?></p>
                    <p><strong>Total:</strong> Rs <?= number_format($quotation['total_amount'], 2) ?></p>
                </div>
            <?php endif; ?>

            <?php
            // Fetch calculation summary data for this quotation
            $window_calcs = [];
            $calc_stmt = $conn->prepare("SELECT window_type, width, height, quantity, total_area, total_cost FROM window_calculation_details WHERE quotation_number = ?");
            $calc_stmt->bind_param("s", $quotation['quotation_number']);
            $calc_stmt->execute();
            $calc_result = $calc_stmt->get_result();
            while ($row = $calc_result->fetch_assoc()) {
                $window_calcs[] = $row;
            }
            $calc_stmt->close();
            ?>
            <?php if (!empty($window_calcs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Window Type</th>
                            <th>Width (ft)</th>
                            <th>Height (ft)</th>
                            <th>Area (sq ft)</th>
                            <th>Quantity</th>
                            <th>Rate (Rs/sq ft)</th>
                            <th>Amount (Rs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($window_calcs as $i => $win): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($win['window_type']) ?></td>
                                <td><?= number_format($win['width'], 2) ?></td>
                                <td><?= number_format($win['height'], 2) ?></td>
                                <td><?= number_format($win['total_area'], 2) ?></td>
                                <td><?= number_format($win['quantity'], 2) ?></td>
                                <td><?= ($win['total_area'] > 0 ? number_format($win['total_cost'] / $win['total_area'], 2) : '0.00') ?></td>
                                <td><?= number_format($win['total_cost'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php if (!empty($quotation['window_types'])): ?>
                <div class="window-gallery">
                    <h4>Window Types</h4>
                    <div class="window-images">
                        <?php 
                        $windowTypes = explode(',', $quotation['window_types']);
                        foreach ($windowTypes as $type): 
                            $type = trim($type);
                            $imagePath = "Pages/image/{$type}.png";
                            $imagePath = file_exists($imagePath) ? $imagePath : "Pages/image/default.png";
                        ?>
                            <div class="window-image-container">
                                <img src="<?= htmlspecialchars($imagePath) ?>" class="window-image" alt="<?= htmlspecialchars($type) ?>">
                                <div class="window-label"><?= htmlspecialchars($type) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Unit</th>
                        <th>Area (Sq.ft)</th>
                        <th>Rate per Sq.ft</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotation['items'] as $item): 
                        $amount = ($item['area'] ?? 0) * ($item['rate_per_sft'] ?? 0) * ($item['quantity'] ?? 1);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($item['description']) ?></td>
                            <td><?= htmlspecialchars($item['unit']) ?></td>
                            <td><?= $item['area'] ?></td>
                            <td>Rs <?= number_format($item['rate_per_sft'] ?? 0, 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>Rs <?= number_format($amount, 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="font-weight: bold;">
                        <td colspan="5" style="text-align: right;">Total Amount:</td>
                        <td>Rs <?= number_format($quotation['total_amount'], 2) ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
<?php ob_end_flush(); ?>