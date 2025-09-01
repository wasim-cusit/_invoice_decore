<?php
?>
<div class="container-fluid mt-3">
    <div class="row">
        <div class="col-12 px-3 px-md-4"> <!-- Added horizontal padding -->
            <!-- Enhanced Nav tabs with proper spacing -->
            <ul class="nav nav-tabs mb-3 mb-md-4 nav-fill" id="purchaseTabs" role="tablist" style="border-bottom: 2px solid #e9ecef;">
                <li class="nav-item px-1" role="presentation"> <!-- Added item padding -->
                    <button class="nav-link active d-flex align-items-center justify-content-center py-2 px-2 px-md-3" id="supplier-tab" data-bs-toggle="tab" data-bs-target="#supplier-management-pane" type="button" role="tab">
                        <i class="fas fa-truck me-2"></i> Supplier Management
                    </button>
                </li>
                <li class="nav-item px-1" role="presentation">
                    <button class="nav-link d-flex align-items-center justify-content-center py-2 px-2 px-md-3" id="purchase-form-tab" data-bs-toggle="tab" data-bs-target="#purchase-form-pane" type="button" role="tab">
                        <i class="fas fa-file-invoice-dollar me-2"></i> Purchase Form
                    </button>
                </li>
                <li class="nav-item px-1" role="presentation">
                    <button class="nav-link d-flex align-items-center justify-content-center py-2 px-2 px-md-3" id="purchase-details-tab" data-bs-toggle="tab" data-bs-target="#purchase-details-pane" type="button" role="tab">
                        <i class="fas fa-clipboard-list me-2"></i> Purchase Details
                    </button>
                </li>
                <li class="nav-item px-1" role="presentation">
                    <button class="nav-link d-flex align-items-center justify-content-center py-2 px-2 px-md-3" id="supplier-ledger-tab" data-bs-toggle="tab" data-bs-target="#supplier-ledger-pane" type="button" role="tab">
                        <i class="fas fa-book me-2"></i> Supplier Ledger
                    </button>
                </li>
            </ul>

            <!-- Tab panes with consistent padding -->
            <div class="tab-content pt-2" id="purchaseTabContent"> <!-- Added top padding -->

                <!-- Supplier Management Tab -->
                <div class="tab-pane fade show active" id="supplier-management-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-primary text-white px-3 px-md-4 py-3"> <!-- Consistent padding -->
                            <h5 class="mb-0"><i class="fas fa-truck me-2"></i> Supplier Management</h5>
                        </div>
                        <div class="card-body p-3 p-md-4" id="supplier-management"> <!-- Responsive padding -->
                            <div class="table-responsive">
                                <!-- Content will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase Form Tab -->
                <div class="tab-pane fade" id="purchase-form-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white px-3 px-md-4 py-3">
                            <h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i> New Purchase</h5>
                        </div>
                        <div class="card-body p-3 p-md-4" id="purchase-form">
                            <div class="alert alert-info mb-3 mb-md-4"> <!-- Bottom margin -->
                                <i class="fas fa-info-circle me-2"></i> Please fill all required fields
                            </div>
                            <!-- Form will be loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Purchase Details Tab -->
                <div class="tab-pane fade" id="purchase-details-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-info text-white px-3 px-md-4 py-3 d-flex flex-column flex-md-row align-items-center">
                            <h5 class="mb-2 mb-md-0 me-md-3"><i class="fas fa-clipboard-list me-2"></i> Purchase History</h5>
                            <div class="input-group mt-2 mt-md-0 ms-md-auto" style="max-width: 300px;">
                                <input type="text" class="form-control form-control-sm" placeholder="Search...">
                                <button class="btn btn-light btn-sm" type="button">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0" id="purchase-details-table">
                            <div class="p-3 p-md-4"> <!-- Inner padding -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Supplier Ledger Tab -->
                <div class="tab-pane fade" id="supplier-ledger-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-purple text-white px-3 px-md-4 py-3">
                            <h5 class="mb-0"><i class="fas fa-book me-2"></i> Supplier Ledger</h5>
                        </div>
                        <div class="card-body p-3 p-md-4" id="supplier-ledger">
                            <div class="row g-3 mb-3 mb-md-4"> <!-- Grid gap -->
                                <div class="col-md-4">
                                    <select class="form-select form-select-sm">
                                        <option selected disabled>Select Supplier</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="date" class="form-control form-control-sm">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-purple w-100 btn-sm">
                                        <i class="fas fa-filter me-1"></i> Filter
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <div class="text-center py-4 py-md-5">
                                    <div class="spinner-border text-purple" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-3 mb-0 text-muted">Please select a supplier</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles with proper spacing */
    :root {
        --bs-body-line-height: 1.5;
    }

    .bg-purple {
        background-color: #6f42c1;
    }

    .btn-purple {
        background-color: #6f42c1;
        color: white;
        border: none;
    }

    .btn-purple:hover {
        background-color: #5e35b1;
        color: white;
    }

    .nav-tabs {
        border-bottom: 2px solid #e9ecef;
    }

    .nav-tabs .nav-link {
        color: #495057;
        font-weight: 500;
        border: none;
        padding: 0.75rem 1rem;
        margin: 0 0.25rem;
        border-radius: 0.375rem 0.375rem 0 0;
        transition: all 0.2s ease;
    }

    .nav-tabs .nav-link:hover {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.05);
    }

    .nav-tabs .nav-link.active {
        color: #0d6efd;
        background-color: transparent;
        border-bottom: 3px solid #0d6efd;
        font-weight: 600;
    }

    .card {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .card-body {
        padding: 1.25rem;
    }

    .table-responsive {
        min-height:163px;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .nav-tabs .nav-link {
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            margin: 0 0.125rem;
        }

        .nav-tabs .nav-link i {
            margin-right: 0.25rem;
        }

        .card-header,
        .card-body {
            padding: 0.75rem;
        }

        .input-group {
            width: 100% !important;
        }
    }
</style>

<?php include 'includes/footer_scripts.php'; ?>
<script src="js/purchase.js"></script>
<script>
    // Tab persistence with proper event delegation
    document.addEventListener('DOMContentLoaded', function() {
        // Set active tab from storage
        const lastTab = localStorage.getItem('purchaseActiveTab');
        if (lastTab && document.querySelector(lastTab)) {
            const tab = new bootstrap.Tab(document.querySelector(lastTab));
            tab.show();
        }

        // Update storage on tab change
        document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(e) {
                localStorage.setItem('purchaseActiveTab', '#' + e.target.id);
            });
        });

        // Remove initial loading content injection
        // loadTabContent(document.querySelector('.nav-tabs .nav-link.active').id);
    });

    // Remove loadTabContent function entirely
    // function loadTabContent(tabId) { ... }
</script>