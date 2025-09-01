<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['client_id'])) {
    $_SESSION['selected_client_id'] = (int)$_POST['client_id'];
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
