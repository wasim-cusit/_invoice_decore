<?php
require_once 'db.php';
header('Content-Type: application/json');

// Initialize response array
$response = [
    'status' => 'success',
    'quotation_count' => 0,
    'company_count' => 0,
    'expense_total' => 0,
    'client_count' => 0,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    // Get quotations count
    $result = $conn->query("SELECT COUNT(*) AS total FROM quotations");
    if ($result) {
        $response['quotation_count'] = (int)$result->fetch_assoc()['total'];
    } else {
        throw new Exception("Failed to get quotations count: " . $conn->error);
    }

    // Get companies count
    $result = $conn->query("SELECT COUNT(*) AS total FROM companies");
    if ($result) {
        $response['company_count'] = (int)$result->fetch_assoc()['total'];
    } else {
        throw new Exception("Failed to get companies count: " . $conn->error);
    }

    // Get expenses total amount in PKR
    $result = $conn->query("SELECT SUM(total_amount) AS total FROM expenses");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['expense_total'] = $row['total'] ? number_format($row['total'], 2) : '0.00';
    } else {
        throw new Exception("Failed to get expenses total: " . $conn->error);
    }

    // Get clients count
    $result = $conn->query("SELECT COUNT(*) AS total FROM clients");
    if ($result) {
        $response['client_count'] = (int)$result->fetch_assoc()['total'];
    } else {
        throw new Exception("Failed to get clients count: " . $conn->error);
    }

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
} finally {
    $conn->close();
}

echo json_encode($response);