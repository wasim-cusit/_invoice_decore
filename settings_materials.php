<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['edit_id'])) {
    $id = (int)$_POST['edit_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $company_id = (int)$_POST['company_id'];
    $length = $conn->real_escape_string($_POST['length']);
    $price_per_foot = isset($_POST['price_per_foot']) ? (float)$_POST['price_per_foot'] : 0;
    $bundle_quantity = isset($_POST['bundle_quantity']) ? (int)$_POST['bundle_quantity'] : 0;
    $bundle_price = isset($_POST['bundle_price']) ? (float)$_POST['bundle_price'] : 0;

    $sql = "UPDATE materials SET name='$name', company_id=$company_id, length='$length', price_per_foot=$price_per_foot, bundle_quantity=$bundle_quantity, bundle_price=$bundle_price WHERE id=$id";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
} 