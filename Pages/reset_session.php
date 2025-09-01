<?php
session_start();

// Clear relevant session values
unset($_SESSION['selected_company_id']);
unset($_SESSION['selected_client_id']);
unset($_SESSION['quotation_items']);
unset($_SESSION['message']);
unset($_SESSION['error']);

// Optional: redirect clean without client_id in URL
header("Location: index.php?page=quotations");
exit();
?>
