<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_id = intval($_POST['company_id']);
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone'] ?? '');
    $address = $conn->real_escape_string($_POST['address'] ?? '');
    
    $stmt = $conn->prepare("INSERT INTO clients (company_id, name, phone, address) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $company_id, $name, $phone, $address);
    
    if ($stmt->execute()) {
        $response = [
            'success' => true,
            'clientId' => $stmt->insert_id
        ];
    } else {
        $response['error'] = "Error adding client: " . $conn->error;
    }
}

echo json_encode($response);