<?php
session_start();
require('db.php');

if (isset($_POST['client_id'])) {
    $_SESSION['selected_client_id'] = (int)$_POST['client_id'];
    echo json_encode(['status' => 'success']);
    exit();
}

echo json_encode(['status' => 'error']);
?>