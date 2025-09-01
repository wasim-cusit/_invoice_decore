<?php


function storeCalculationData($type, $dimensions, $materials, $hardware, $totals) {
    if (!isset($_SESSION['calculation_storage'])) {
        $_SESSION['calculation_storage'] = [];
    }
    
    $_SESSION['calculation_storage'][] = [
        'type' => $type,
        'timestamp' => date('Y-m-d H:i:s'),
        'dimensions' => $dimensions,
        'materials' => $materials,
        'hardware' => $hardware,
        'totals' => $totals
    ];
    
    return true;
}

function getStoredCalculations() {
    return $_SESSION['calculation_storage'] ?? [];
}

function clearStoredCalculations() {
    unset($_SESSION['calculation_storage']);
}
?>