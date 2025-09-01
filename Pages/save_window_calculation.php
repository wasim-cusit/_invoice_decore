<?php
// Ensure no output before headers
ob_start();

// Disable error display for production (errors will still be logged)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/log.txt');
error_reporting(E_ALL);

// Suppress warnings and notices that might cause output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

require_once __DIR__ . '/../db.php';

// Clean any previous output and set JSON header immediately
ob_clean();
header('Content-Type: application/json');

// Check database connection
if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Debug: Log received data
    error_log("Received POST data: " . print_r($_POST, true));
    
    // Check action
    if (!isset($_POST['action'])) {
        throw new Exception("Invalid request: no action specified");
    }
    
    if ($_POST['action'] !== 'save_calculation') {
        throw new Exception("Invalid action");
    }

    // Required fields
    $required = [
        'client_id', 'company_id', 'window_type',
        'height', 'width', 'quantity', 'total_area',
        'material_cost', 'hardware_cost', 'glass_cost', 'total_cost'
    ];

    foreach ($required as $field) {
        if (!isset($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $conn->begin_transaction();

    // Check client & company
    $check = $conn->prepare("SELECT 1 FROM clients WHERE id = ? AND company_id = ?");
    $check->bind_param("ii", $_POST['client_id'], $_POST['company_id']);
    $check->execute();
    $check->store_result();
    if ($check->num_rows === 0) {
        throw new Exception("Client not found or doesn't belong to company");
    }
    $check->close();

    // Insert into window_calculation_details with only essential fields
    $stmt = $conn->prepare("
        INSERT INTO window_calculation_details (
            client_id, company_id, window_type,
            height, width, quantity, total_area,
            frame_length, sash_length, net_sash_length, beading_length, interlock_length,
            steel_quantity, net_area, net_rubber_quantity, burshi_length,
            locks, dummy, boofer, stopper, double_wheel, net_wheel,
            sada_screw, fitting_screw, self_screw, rawal_plug, silicon_white, hole_caps, water_caps,
            frame_cost, sash_cost, net_sash_cost, beading_cost, interlock_cost,
            steel_cost, net_cost, net_rubber_cost, burshi_cost,
            locks_cost, dummy_cost, boofer_cost, stopper_cost, double_wheel_cost, net_wheel_cost,
            sada_screw_cost, fitting_screw_cost, self_screw_cost, rawal_plug_cost, silicon_white_cost, hole_caps_cost, water_caps_cost,
            material_cost, hardware_cost, glass_cost, total_cost
        ) VALUES (
            ?, ?, ?, 
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?
        )
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Prepare all parameters with default values if not set
    $params = [
        (int)$_POST['client_id'],
        (int)$_POST['company_id'],
        $_POST['window_type'],
        floatval($_POST['height']),
        floatval($_POST['width']),
        (int)$_POST['quantity'],
        floatval($_POST['total_area']),
        floatval($_POST['frame_length'] ?? 0),
        floatval($_POST['sash_length'] ?? 0),
        floatval($_POST['net_sash_length'] ?? 0),
        floatval($_POST['beading_length'] ?? 0),
        floatval($_POST['interlock_length'] ?? 0),
        floatval($_POST['steel_quantity'] ?? 0),
        floatval($_POST['net_area'] ?? 0),
        floatval($_POST['net_rubber_quantity'] ?? 0),
        floatval($_POST['burshi_length'] ?? 0),
        (int)($_POST['locks'] ?? 0),
        (int)($_POST['dummy'] ?? 0),
        (int)($_POST['boofer'] ?? 0),
        (int)($_POST['stopper'] ?? 0),
        (int)($_POST['double_wheel'] ?? 0),
        (int)($_POST['net_wheel'] ?? 0),
        (int)($_POST['sada_screw'] ?? 0),
        (int)($_POST['fitting_screw'] ?? 0),
        floatval($_POST['self_screw'] ?? 0),
        (int)($_POST['rawal_plug'] ?? 0),
        (int)($_POST['silicon_white'] ?? 0),
        (int)($_POST['hole_caps'] ?? 0),
        (int)($_POST['water_caps'] ?? 0),
        floatval($_POST['frame_cost'] ?? 0),
        floatval($_POST['sash_cost'] ?? 0),
        floatval($_POST['net_sash_cost'] ?? 0),
        floatval($_POST['beading_cost'] ?? 0),
        floatval($_POST['interlock_cost'] ?? 0),
        floatval($_POST['steel_cost'] ?? 0),
        floatval($_POST['net_cost'] ?? 0),
        floatval($_POST['net_rubber_cost'] ?? 0),
        floatval($_POST['burshi_cost'] ?? 0),
        floatval($_POST['locks_cost'] ?? 0),
        floatval($_POST['dummy_cost'] ?? 0),
        floatval($_POST['boofer_cost'] ?? 0),
        floatval($_POST['stopper_cost'] ?? 0),
        floatval($_POST['double_wheel_cost'] ?? 0),
        floatval($_POST['net_wheel_cost'] ?? 0),
        floatval($_POST['sada_screw_cost'] ?? 0),
        floatval($_POST['fitting_screw_cost'] ?? 0),
        floatval($_POST['self_screw_cost'] ?? 0),
        floatval($_POST['rawal_plug_cost'] ?? 0),
        floatval($_POST['silicon_white_cost'] ?? 0),
        floatval($_POST['hole_caps_cost'] ?? 0),
        floatval($_POST['water_caps_cost'] ?? 0),
        floatval($_POST['material_cost']),
        floatval($_POST['hardware_cost']),
        floatval($_POST['glass_cost']),
        floatval($_POST['total_cost'])
    ];

    // Type string for bind_param (55 parameters)
    $type_string = "iisddddddddddiiiiiiiiidiiiiiddddddddddddddddddddddddddd";
    
    // Debug info
    error_log("Number of parameters: " . count($params));
    error_log("Type string length: " . strlen($type_string));
    error_log("Type string: " . $type_string);
    error_log("Parameters: " . print_r($params, true));
    
    // Verify parameter count matches type string
    if (count($params) !== strlen($type_string)) {
        throw new Exception("Parameter count mismatch: " . count($params) . " parameters vs " . strlen($type_string) . " type characters");
    }

    // Bind parameters using individual calls to avoid spread operator issues
    $stmt->bind_param($type_string, 
        $params[0], $params[1], $params[2], $params[3], $params[4], $params[5], $params[6], $params[7], $params[8], $params[9],
        $params[10], $params[11], $params[12], $params[13], $params[14], $params[15], $params[16], $params[17], $params[18], $params[19],
        $params[20], $params[21], $params[22], $params[23], $params[24], $params[25], $params[26], $params[27], $params[28], $params[29],
        $params[30], $params[31], $params[32], $params[33], $params[34], $params[35], $params[36], $params[37], $params[38], $params[39],
        $params[40], $params[41], $params[42], $params[43], $params[44], $params[45], $params[46], $params[47], $params[48], $params[49],
        $params[50], $params[51], $params[52], $params[53], $params[54]
    );

    if (!$stmt->execute()) {
        throw new Exception("Failed to save calculation: " . $stmt->error);
    }

    $insert_id = $conn->insert_id;
    $conn->commit();

    // Clean any output buffer before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'id' => $insert_id,
        'message' => 'Calculation saved successfully!'
    ]);

} catch (Exception $e) {
    // Clean any output buffer before sending JSON error
    ob_clean();
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
} catch (Error $e) {
    // Clean any output buffer before sending JSON error
    ob_clean();
    
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    exit;
}