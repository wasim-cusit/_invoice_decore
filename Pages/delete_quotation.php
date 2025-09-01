<?php
// delete_quotation.php
session_start();
require_once __DIR__ . '/../db.php';

if (isset($_GET['client_id']) && isset($_GET['company_id'])) {
    $client_id = (int)$_GET['client_id'];
    $company_id = (int)$_GET['company_id'];
    
    // Perform deletion logic here
    $stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $stmt->close();
    
    // Redirect back to the same page
    header("Location: index.php?page=report_quotation&company_id=".$company_id);
    exit();
}
?>