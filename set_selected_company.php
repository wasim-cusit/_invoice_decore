<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['company_id'])) {
    $_SESSION['selected_company_id'] = (int)$_POST['company_id'];
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false]);
