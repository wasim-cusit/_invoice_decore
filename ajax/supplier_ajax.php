<?php
session_start();
header('Content-Type: application/json');
require_once '../db.php'; // adjust path as needed

$action = isset($_POST['action']) ? $_POST['action'] : '';
$response = ['status' => 'error', 'message' => 'Invalid request'];

switch ($action) {
    case 'add':
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name === '' || $phone === '' || $address === '' || $email === '') {
            $response = ['status' => 'error', 'message' => 'All fields are required.'];
            break;
        }
        // Check unique phone
        if ($phone !== '') {
            $stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE phone = ?");
            $stmt->bind_param('s', $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $response = ['status' => 'error', 'message' => 'A supplier with this mobile number already exists. Please use a unique number.'];
                $stmt->close();
                break;
            }
            $stmt->close();
        }
        $stmt = $conn->prepare("INSERT INTO suppliers (name, phone, address, email) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $name, $phone, $address, $email);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Supplier added successfully.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to add supplier.'];
        }
        $stmt->close();
        break;
    case 'edit':
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($supplier_id <= 0 || $name === '' || $phone === '' || $address === '' || $email === '') {
            $response = ['status' => 'error', 'message' => 'All fields are required.'];
            break;
        }
        // Check unique phone (exclude current supplier)
        if ($phone !== '') {
            $stmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE phone = ? AND supplier_id != ?");
            $stmt->bind_param('si', $phone, $supplier_id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $response = ['status' => 'error', 'message' => 'Mobile number already exists.'];
                $stmt->close();
                break;
            }
            $stmt->close();
        }
        $stmt = $conn->prepare("UPDATE suppliers SET name=?, phone=?, address=?, email=? WHERE supplier_id=?");
        $stmt->bind_param('ssssi', $name, $phone, $address, $email, $supplier_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Supplier updated successfully.'];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to update supplier.'];
        }
        $stmt->close();
        break;
    case 'delete':
        $supplier_id = intval($_POST['supplier_id'] ?? 0);
        if ($supplier_id <= 0) {
            $response = ['status' => 'error', 'message' => 'Invalid supplier.'];
            break;
        }
        $stmt = $conn->prepare("DELETE FROM suppliers WHERE supplier_id=?");
        $stmt->bind_param('i', $supplier_id);
        if ($stmt->execute()) {
            $response = ['status' => 'success', 'message' => 'Supplier deleted successfully.'];
        } else {
            // Check for foreign key constraint error
            if ($conn->errno == 1451) {
                $response = ['status' => 'error', 'message' => 'Cannot delete supplier: referenced in other records.'];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to delete supplier.'];
            }
        }
        $stmt->close();
        break;
    case 'search':
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $where = $search ? "WHERE name LIKE '%$search%' OR phone LIKE '%$search%' OR email LIKE '%$search%'" : '';
        $sql = "SELECT * FROM suppliers $where ORDER BY supplier_id DESC";
        $result = $conn->query($sql);
        ob_start();
        ?>
        <form id="addSupplierForm" class="mb-3 row g-2">
          <input type="hidden" name="supplier_id">
          <input type="hidden" name="edit_mode">
          <div class="col-md-2"><input type="text" name="name" class="form-control" placeholder="Name" required></div>
          <div class="col-md-2"><input type="text" name="phone" class="form-control" placeholder="Phone"></div>
          <div class="col-md-3"><input type="text" name="address" class="form-control" placeholder="Address"></div>
          <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email"></div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary" id="addSupplierBtn">Add Supplier</button>
            <button type="button" class="btn btn-secondary" id="cancelEditSupplier">Cancel</button>
          </div>
        </form>
        <input type="text" id="supplierSearch" class="form-control mb-2" placeholder="Search suppliers..." value="<?= htmlspecialchars($search) ?>">
        <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="thead-light">
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>Address</th>
              <th>Email</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
              <tr data-id="<?= $row['supplier_id'] ?>">
                <td class="supplier-name"><?= htmlspecialchars($row['name']) ?></td>
                <td class="supplier-phone"><?= htmlspecialchars($row['phone']) ?></td>
                <td class="supplier-address"><?= htmlspecialchars($row['address']) ?></td>
                <td class="supplier-email"><?= htmlspecialchars($row['email']) ?></td>
                <td>
                  <button class="btn btn-sm btn-info edit-supplier-btn">Edit</button>
                  <button class="btn btn-sm btn-danger delete-supplier-btn" data-id="<?= $row['supplier_id'] ?>">Delete</button>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="5" class="text-center">No suppliers found.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
        </div>
        <?php
        $response = [
            'status' => 'success',
            'html' => ob_get_clean()
        ];
        break;
}
echo json_encode($response); 