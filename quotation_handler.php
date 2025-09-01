<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db.php';
require_once './Pages/calculation_storage.php';

// Initialize session arrays if not exists
if (!isset($_SESSION['quotation_items'])) {
    $_SESSION['quotation_items'] = [];
}

if (!isset($_SESSION['calculation_storage'])) {
    $_SESSION['calculation_storage'] = [];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $response = ['success' => false];
    $company_id = $_SESSION['selected_company_id'] ?? null;

    try {
        switch ($action) {
            case 'add_item':
                // Validate required fields
                $required = ['description', 'area', 'amount', 'window_type', 'quantity', 'client_id'];
                foreach ($required as $field) {
                    if (empty($_POST[$field])) {
                        throw new Exception("Missing required field: $field");
                    }
                }

                // Prepare calculation data
                $calculation_data = !empty($_POST['calculation_data']) ? 
                    json_decode($_POST['calculation_data'], true) : null;

                // Store in calculation_records table
                $calc_id = storeCalculationRecord(
                    (int)$_POST['client_id'],
                    $company_id,
                    $_POST['window_type'],
                    (float)$_POST['height'],
                    (float)$_POST['width'],
                    (int)$_POST['quantity'],
                    (float)$_POST['area'],
                    (float)$_POST['amount'],
                    json_encode($calculation_data)
                );

                // Add to session quotation items (simplified view)
                $item = [
                    'description' => $_POST['description'] ?? '-',
                    'unit' => $_POST['unit'] ?? 'Sft',
                    'area' => isset($_POST['area']) ? (float)$_POST['area'] : 0,
                    'rate' => isset($_POST['rate']) ? (float)$_POST['rate'] : 0,
                    'amount' => isset($_POST['amount']) ? (float)$_POST['amount'] : 0,
                    'window_type' => $_POST['window_type'] ?? '-',
                    'quantity' => isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1,
                    'height' => isset($_POST['height']) ? (float)$_POST['height'] : 0,
                    'width' => isset($_POST['width']) ? (float)$_POST['width'] : 0,
                    'height_original' => $_POST['height_original'] ?? '',
                    'width_original' => $_POST['width_original'] ?? '',
                    'unit_original' => $_POST['unit_original'] ?? '',
                    'client_id' => isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0,
                    'calculation_data' => $_POST['calculation_data'] ?? '',
                ];
                $_SESSION['quotation_items'][] = $item;

                // Also store in calculation_storage session
                if ($calculation_data) {
                    storeCalculationData(
                        $_POST['window_type'],
                        [
                            'height' => (float)$_POST['height'],
                            'width' => (float)$_POST['width'],
                            'quantity' => (int)$_POST['quantity'],
                            'area' => (float)$_POST['area']
                        ],
                        $calculation_data['materials'] ?? [],
                        $calculation_data['hardware'] ?? [],
                        $calculation_data['totals'] ?? []
                    );
                }

                $response = [
                    'success' => true,
                    'item' => $item,
                    'calculations' => getStoredCalculations()
                ];
                break;

            case 'get_items':
                $response = [
                    'success' => true,
                    'items' => $_SESSION['quotation_items'],
                    'calculations' => getStoredCalculations()
                ];
                break;

            case 'clear_items':
                $_SESSION['quotation_items'] = [];
                clearStoredCalculations();
                $response = ['success' => true];
                break;

            case 'save_quotation':
                if (empty($_POST['client_id'])) {
                    throw new Exception("Please select a client");
                }

                $client_id = (int)$_POST['client_id'];
                $quotation_number = 'QP-' . date('YmdHis');
                $date = date('Y-m-d');
                $total_amount = (float)$_POST['total_amount'];
                $notes = $conn->real_escape_string($_POST['notes'] ?? '');
                $terms = $conn->real_escape_string($_POST['terms'] ?? '');
                $window_types = implode(',', array_unique(array_column($_SESSION['quotation_items'], 'window_type')));

                $conn->begin_transaction();

                try {
                    // Insert quotation header
                    $stmt = $conn->prepare("INSERT INTO quotations 
                                          (company_id, client_id, quotation_number, date, 
                                          total_amount, notes, terms, window_types)
                                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("iissssss", 
                        $company_id, $client_id, $quotation_number, $date, 
                        $total_amount, $notes, $terms, $window_types);
                    $stmt->execute();
                    $quotation_id = $conn->insert_id;
                    $stmt->close();

                    // Insert items
                    foreach ($_SESSION['quotation_items'] as $item) {
                        $stmt = $conn->prepare("INSERT INTO quotation_items 
                                              (quotation_id, description, unit, area, 
                                              rate_per_sft, amount, quantity, height, width, 
                                              window_type, calculation_id)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("isssddiddsi",
                            $quotation_id,
                            $item['description'],
                            $item['unit'],
                            $item['area'],
                            $item['rate'],
                            $item['amount'],
                            $item['quantity'],
                            $item['height'],
                            $item['width'],
                            $item['window_type'],
                            $item['calculation_id']
                        );
                        $stmt->execute();
                        $stmt->close();

                        // Link calculation to quotation
                        if (!empty($item['calculation_id'])) {
                            $stmt = $conn->prepare("INSERT INTO quotation_calculation_link 
                                                  (quotation_id, calculation_id)
                                                  VALUES (?, ?)");
                            $stmt->bind_param("ii", $quotation_id, $item['calculation_id']);
                            $stmt->execute();
                            $stmt->close();
                        }
                    }

                    $conn->commit();

                    // Clear session data
                    $_SESSION['quotation_items'] = [];
                    clearStoredCalculations();

                    $response = [
                        'success' => true,
                        'quotation_id' => $quotation_id,
                        'quotation_number' => $quotation_number
                    ];

                } catch (Exception $e) {
                    $conn->rollback();
                    throw $e;
                }
                break;

            case 'get_calculations':
                $client_id = (int)$_POST['client_id'];
                $stmt = $conn->prepare("SELECT * FROM calculation_records 
                                      WHERE client_id = ? AND company_id = ?
                                      ORDER BY created_at DESC LIMIT 10");
                $stmt->bind_param("ii", $client_id, $company_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $calculations = [];

                while ($row = $result->fetch_assoc()) {
                    $row['calculation_data'] = json_decode($row['calculation_data'], true);
                    $calculations[] = $row;
                }

                $response = [
                    'success' => true,
                    'calculations' => $calculations
                ];
                break;

            default:
                $response['error'] = 'Invalid action';
        }

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Helper function to store calculation record
function storeCalculationRecord($client_id, $company_id, $window_type, $height, $width, 
                              $quantity, $total_area, $total_cost, $calculation_data) {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO calculation_records 
                          (client_id, company_id, window_type, height, width, 
                          quantity, total_area, total_cost, calculation_data)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissddids", 
        $client_id, $company_id, $window_type, $height, $width,
        $quantity, $total_area, $total_cost, $calculation_data
    );
    
    if ($stmt->execute()) {
        return $stmt->insert_id;
    }
    throw new Exception("Failed to save calculation record: " . $stmt->error);
}