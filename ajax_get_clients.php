<?php
require_once 'db.php';

$company_id = intval($_GET['company_id'] ?? 0);
$clients = [];

if ($company_id > 0) {
    $stmt = $conn->prepare("SELECT id, name FROM clients WHERE company_id = ? ORDER BY name");
    $stmt->bind_param("i", $company_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $clients[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($clients);
?>
