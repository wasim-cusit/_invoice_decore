<?php
require_once 'db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => '', 'clientId' => 0];

try {
    // Validate input
    if (empty($_POST['company_id']) || empty($_POST['name'])) {
        throw new Exception('Company ID and client name are required');
    }

    $company_id = intval($_POST['company_id']);
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

    // Insert new client
    $stmt = $conn->prepare("INSERT INTO clients (company_id, name, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $company_id, $name, $phone, $address);
    
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['clientId'] = $stmt->insert_id;
        $response['message'] = 'Client added successfully';
    } else {
        throw new Exception('Failed to add client: ' . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);