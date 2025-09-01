<?php
require_once 'calculation_storage.php';
require_once 'db.php';

header('Content-Type: application/json');

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    
    // Store in session
    storeCalculationData(
        $data['type'],
        $data['dimensions'],
        $data['materials'],
        $data['hardware'],
        $data['totals']
    );
    
    // Optional: Store in database immediately
    $stmt = $conn->prepare("INSERT INTO calculation_records 
                          (type, dimensions, materials, hardware, totals, created_at)
                          VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss",
        $data['type'],
        json_encode($data['dimensions']),
        json_encode($data['materials']),
        json_encode($data['hardware']),
        json_encode($data['totals'])
    );
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>