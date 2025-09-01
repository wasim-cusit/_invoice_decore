<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db.php';

// Verify we have company and client
if (empty($_SESSION['calculation_company_id']) || empty($_GET['client_id'])) {
    header("Location: new_calculation.php");
    exit();
}

$client_id = intval($_GET['client_id']);
$company_id = $_SESSION['calculation_company_id'];

// Verify client belongs to company
$stmt = $conn->prepare("SELECT id FROM clients WHERE id = ? AND company_id = ?");
$stmt->bind_param("ii", $client_id, $company_id);
$stmt->execute();
if (!$stmt->get_result()->num_rows) {
    $_SESSION['message'] = "Client not found or doesn't belong to selected company";
    $_SESSION['message_type'] = "danger";
    header("Location: new_calculation.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Your head content -->
</head>
<body>
    <div class="container">
        <div class="step-container p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="step-title pb-2 mb-0">
                    <i class="fas fa-window-maximize me-2"></i>Select Window Type
                </h4>
                <div>
                    <span class="badge bg-primary me-2">
                        <i class="fas fa-building me-1"></i>
                        <?= htmlspecialchars($_SESSION['calculation_company_name']) ?>
                    </span>
                    <span class="badge bg-info">
                        <i class="fas fa-user me-1"></i>
                        Client #<?= $client_id ?>
                    </span>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="product-card p-3 text-center" 
                         onclick="window.location.href='2psl_window.php?client_id=<?= $client_id ?>'">
                        <i class="fas fa-window-restore fa-3x text-primary mb-3"></i>
                        <h5>2PSL Window</h5>
                    </div>
                </div>
                
                <!-- Other window types similarly -->
            </div>
            
            <div class="text-start mt-3">
                <a href="new_calculation.php?step=client&product=window" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
    </div>
</body>
</html>