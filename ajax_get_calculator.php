<?php
require_once 'db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$companyId = $_GET['company_id'] ?? '';
$productType = $_GET['product_type'] ?? '';
$subType = $_GET['sub_type'] ?? '';
$clientId = $_GET['client_id'] ?? '';

// Validate inputs
if (empty($companyId) || !in_array($productType, ['window', 'door']) || empty($subType) || empty($clientId)) {
    http_response_code(400);
    die('Invalid parameters');
}

// Store in session for the calculator to use
$_SESSION['calculation_company_id'] = $companyId;
$_SESSION['calculation_product_type'] = $productType;
$_SESSION['calculation_sub_type'] = $subType;
$_SESSION['calculation_client_id'] = $clientId;

// Map to the correct calculator file
$calculatorFiles = [
    'window' => [
        '2psl' => 'Pages/2pslwindow.php',
        '3psl' => 'Pages/3pslwindow.php',
        'fixed' => 'Pages/fixwindow.php',
        'top_hung' => 'Pages/hungwindow.php'
        
    ],
    'door' => [
        'full_panel' => 'Pages/full_door.php',
        'half'=> 'Pages/half_door.php',
        'openable' => 'Pages/openable_door.php',
        'glass' => 'Pages/glass_door.php'
    ]
];

if (isset($calculatorFiles[$productType][$subType])) {
    include($calculatorFiles[$productType][$subType]);
} else {
    http_response_code(404);
    echo 'Calculator not found for this product type';
}
?>