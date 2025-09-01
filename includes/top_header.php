<!-- jQuery (required for Bootstrap) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<!-- Toastr CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<?php if (isset($_GET['page']) && $_GET['page'] === 'quotation'): ?>
<link rel="stylesheet" href="/decore/assets/css/quotation.css">
<?php endif; ?>

<header class="app-header d-flex justify-content-between align-items-center">
  <div class="d-flex align-items-center">
    <img src="/decore/logo/mod.jpg" alt="Logo" height="40" class="me-3">
  </div>
  <div class="text-center">
    <span class="fs-4 fw-bold text-accent">Modern Home <span class="text-primary">Decore</span></span>
  </div>
  <div class="dropdown">
    <a href="#" class="d-flex align-items-center text-dark text-decoration-none dropdown-toggle" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
      <i class="fa fa-user-circle fa-2x me-2"></i>
      <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
      <li><a class="dropdown-item" href="/decore/change_password.php"><i class="fa fa-key me-2"></i>Change Password</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item" href="/decore/logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
    </ul>
  </div>
</header>

<script>
// Initialize all dropdowns
document.addEventListener('DOMContentLoaded', function() {
    var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
    var dropdownList = dropdownElementList.map(function(element) {
        return new bootstrap.Dropdown(element);
    });
});
</script>

<style>
.app-header {
    background: white;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    padding: 15px 20px;
    position: relative;
    z-index: 1000;
}

.dropdown-menu {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown-item:active {
    background-color: #3498db;
}

/* Ensure dropdowns work on mobile */
@media (max-width: 768px) {
    .dropdown-menu {
        position: absolute !important;
        transform: none !important;
        top: 100% !important;
        right: 0 !important;
    }
}
</style>