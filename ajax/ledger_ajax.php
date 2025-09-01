<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php'; // Add DB connection
// TODO: Include DB connection and CSRF check

$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['status' => 'error', 'message' => 'Invalid request'];

switch ($action) {
    case 'get_ledger':
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        if ($supplier_id <= 0) {
            $response = ['status' => 'error', 'message' => 'Invalid supplier.'];
            break;
        }
        $ledger = [];
        $result = $conn->query("SELECT date, description, debit, credit, balance, related_purchase_id, created_at FROM supplier_ledger WHERE supplier_id = $supplier_id ORDER BY date ASC, ledger_id ASC");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $ledger[] = $row;
            }
        }
        $response = [
            'status' => 'success',
            'ledger' => $ledger
        ];
        break;
    case 'export_pdf':
        // TODO: Export ledger to PDF
        break;
    case 'export_excel':
        // TODO: Export ledger to Excel
        break;
    default:
        $response['message'] = 'Unknown action';
}
echo json_encode($response); 