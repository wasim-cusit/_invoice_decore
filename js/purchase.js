$(document).ready(function() {
    // Load Supplier Management
    loadSupplierTable();

    // Add Supplier
    $(document).on('submit', '#addSupplierForm', function(e) {
        e.preventDefault();
        var editMode = $('[name=edit_mode]').val();
        var action = editMode ? 'edit' : 'add';
        var formData = $(this).serialize() + '&action=' + action;
        $.post('ajax/supplier_ajax.php', formData, function(res) {
            if(res.status === 'success') {
                toastr.success(res.message);
                loadSupplierTable();
                $('#addSupplierForm')[0].reset();
                $('[name=edit_mode]').val('');
                $('#addSupplierBtn').text('Add Supplier');
            } else {
                toastr.error(res.message);
            }
        }, 'json');
    });

    // Ensure modal HTML is present only once
    if ($('#editSupplierModal').length === 0) {
      $('body').append(`
        <div class="modal fade" id="editSupplierModal" tabindex="-1" aria-labelledby="editSupplierModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <form id="editSupplierForm">
                <div class="modal-header">
                  <h5 class="modal-title" id="editSupplierModalLabel">Edit Supplier</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                  <input type="hidden" name="supplier_id">
                  <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" required>
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      `);
    }

    // Edit Supplier (show in modal)
    $(document).on('click', '.edit-supplier-btn', function() {
        var row = $(this).closest('tr');
        var id = row.data('id');
        var modal = $('#editSupplierModal');
        modal.find('[name=supplier_id]').val(id);
        modal.find('[name=name]').val(row.find('.supplier-name').text());
        modal.find('[name=phone]').val(row.find('.supplier-phone').text());
        modal.find('[name=address]').val(row.find('.supplier-address').text());
        modal.find('[name=email]').val(row.find('.supplier-email').text());
        var bsModal = bootstrap.Modal.getOrCreateInstance(modal[0]);
        bsModal.show();
    });

    // Handle edit supplier form submit
    $(document).on('submit', '#editSupplierForm', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=edit';
        $.post('ajax/supplier_ajax.php', formData, function(res) {
            if(res.status === 'success') {
                toastr.success(res.message);
                loadSupplierTable();
                var modal = bootstrap.Modal.getInstance($('#editSupplierModal')[0]);
                modal.hide();
            } else {
                toastr.error(res.message);
            }
        }, 'json');
    });

    // Delete Supplier (event delegation)
    $(document).on('click', '.delete-supplier-btn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the supplier!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if(result.isConfirmed) {
                $.post('ajax/supplier_ajax.php', {action: 'delete', supplier_id: id}, function(res) {
                    if(res.status === 'success') {
                        toastr.success(res.message);
                        loadSupplierTable();
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        });
    });

    // Search Supplier
    $(document).on('input', '#supplierSearch', function() {
        loadSupplierTable($(this).val());
    });

    // Reset form on cancel
    $(document).on('click', '#cancelEditSupplier', function() {
        $('#addSupplierForm')[0].reset();
        $('#addSupplierForm [name=edit_mode]').val('');
        $('#addSupplierBtn').text('Add Supplier');
    });

    // Load supplier table function
    function loadSupplierTable(search = '') {
        $.post('ajax/supplier_ajax.php', {action: 'search', search: search}, function(res) {
            $('#supplier-management').html(res.html);
        }, 'json');
    }

    // Load Purchase Form on tab show
    $(document).on('shown.bs.tab', 'button[data-bs-target="#purchase-form-pane"]', function() {
        loadPurchaseForm();
    });

    // Also load on page ready if tab is active
    if ($('#purchase-form-pane').hasClass('show')) {
        loadPurchaseForm();
    }

    function loadPurchaseForm() {
        // Fetch suppliers and invoice number in parallel
        $.when(
            $.post('ajax/purchase_ajax.php', {action: 'get_suppliers'}),
            $.post('ajax/purchase_ajax.php', {action: 'get_invoice_no'})
        ).done(function(suppliersRes, invoiceRes) {
            var suppliers = suppliersRes[0].suppliers || [];
            var invoiceNo = invoiceRes[0].invoice_no || '';
            var today = new Date().toISOString().slice(0, 10);
            var supplierOptions = suppliers.map(s => `<option value="${s.supplier_id}">${s.name} (${s.phone})</option>`).join('');
            var formHtml = `
        <form id="purchaseForm" autocomplete="off">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label">Supplier</label>
              <select class="form-select" name="supplier_id" required>
                <option value="">Select supplier...</option>
                ${supplierOptions}
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Invoice No</label>
              <input type="text" class="form-control" name="invoice_no" value="${invoiceNo}" readonly>
            </div>
            <div class="col-md-2">
              <label class="form-label">Purchase Date</label>
              <input type="date" class="form-control" name="purchase_date" value="${today}" required>
            </div>
          </div>
          <div class="table-responsive mb-3">
            <table class="table table-bordered align-middle" id="productTable">
              <thead class="table-light">
                <tr>
                  <th style="width:28%">Product Name</th>
                  <th style="width:14%">Quantity</th>
                  <th style="width:18%">Unit Price</th>
                  <th style="width:18%">Line Total</th>
                  <th style="width:10%"></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td><input type="text" name="product_name[]" class="form-control" required></td>
                  <td><input type="number" name="quantity[]" class="form-control qty-input" min="1" value="1" required></td>
                  <td><input type="number" name="unit_price[]" class="form-control price-input" min="0" step="0.01" value="0" required></td>
                  <td><input type="text" class="form-control line-total" value="0.00" readonly></td>
                  <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button></td>
                </tr>
              </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addProductRow"><i class="fas fa-plus"></i> Add Product</button>
          </div>
          <div class="row g-3 mb-3 align-items-end">
            <div class="col-md-2 ms-auto">
              <label class="form-label"><b>Total Amount</b></label>
              <input type="text" class="form-control" name="total_amount" id="totalAmount" value="0.00" readonly>
            </div>
            <div class="col-md-2">
              <label class="form-label"><b>Paid Amount</b></label>
              <input type="number" class="form-control" name="paid_amount" id="paidAmount" min="0" step="0.01" value="0">
            </div>
            <div class="col-md-2">
              <label class="form-label"><b>Due Amount</b></label>
              <input type="text" class="form-control" name="due_amount" id="dueAmount" value="0.00" readonly>
            </div>
          </div>
          <div class="text-end">
            <button type="submit" class="btn btn-success px-4">Save Purchase</button>
          </div>
        </form>`;
            $('#purchase-form').html(formHtml);
        });
    }

    // Dynamic product row logic
    $(document).on('click', '#addProductRow', function() {
        var rowHtml = `<tr>
      <td><input type="text" name="product_name[]" class="form-control" required></td>
      <td><input type="number" name="quantity[]" class="form-control qty-input" min="1" value="1" required></td>
      <td><input type="number" name="unit_price[]" class="form-control price-input" min="0" step="0.01" value="0" required></td>
      <td><input type="text" class="form-control line-total" value="0.00" readonly></td>
      <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`;
        $('#productTable tbody').append(rowHtml);
    });

    $(document).on('click', '.remove-row', function() {
        if ($('#productTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            updateTotals();
        }
    });

    // Real-time calculation
    $(document).on('input', '.qty-input, .price-input', function() {
        var row = $(this).closest('tr');
        var qty = parseFloat(row.find('.qty-input').val()) || 0;
        var price = parseFloat(row.find('.price-input').val()) || 0;
        var lineTotal = (qty * price).toFixed(2);
        row.find('.line-total').val(lineTotal);
        updateTotals();
    });

    $(document).on('input', '#paidAmount', function() {
        updateTotals();
    });

    function updateTotals() {
        var total = 0;
        $('#productTable tbody tr').each(function() {
            var line = parseFloat($(this).find('.line-total').val()) || 0;
            total += line;
        });
        $('#totalAmount').val(total.toFixed(2));
        var paid = parseFloat($('#paidAmount').val()) || 0;
        var due = total - paid;
        $('#dueAmount').val(due.toFixed(2));
    }

    // Save purchase
    $(document).on('submit', '#purchaseForm', function(e) {
        e.preventDefault();
        var formData = $(this).serialize() + '&action=save';
        $.post('ajax/purchase_ajax.php', formData, function(res) {
            if(res.status === 'success') {
                toastr.success(res.message);
                loadPurchaseForm();
            } else {
                toastr.error(res.message || 'Failed to save purchase.');
            }
        }, 'json');
    });

    // Load Purchase Details Table on tab show
    $(document).on('shown.bs.tab', 'button[data-bs-target="#purchase-details-pane"]', function() {
        loadPurchaseDetailsTable();
    });

    // Also load on page ready if tab is active
    if ($('#purchase-details-pane').hasClass('show')) {
        loadPurchaseDetailsTable();
    }

    function loadPurchaseDetailsTable() {
        $.post('ajax/purchase_ajax.php', {action: 'get_purchases'}, function(res) {
            var rows = '';
            if(res.purchases && res.purchases.length) {
                rows = res.purchases.map(p => `
                    <tr>
                      <td>${p.invoice_no}</td>
                      <td>${p.supplier}</td>
                      <td>${p.purchase_date}</td>
                      <td>${parseFloat(p.total_amount).toFixed(2)}</td>
                      <td>${parseFloat(p.paid_amount).toFixed(2)}</td>
                      <td>${parseFloat(p.due_amount).toFixed(2)}</td>
                      <td>
                        <button class="btn btn-info btn-sm view-invoice-btn" data-id="${p.purchase_id}"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-danger btn-sm delete-purchase-btn" data-id="${p.purchase_id}"><i class="fas fa-trash"></i></button>
                      </td>
                    </tr>
                `).join('');
            } else {
                rows = '<tr><td colspan="7" class="text-center">No purchases found.</td></tr>';
            }
            var tableHtml = `
            <div class="table-responsive">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Invoice No</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Paid</th>
                    <th>Due</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows}
                </tbody>
              </table>
            </div>
            `;
            $('#purchase-details-table').html(tableHtml);
        }, 'json');
    }

    // Add invoice modal HTML if not present
    if ($('#invoiceModal').length === 0) {
      $('body').append(`
        <div class="modal fade" id="invoiceModal" tabindex="-1" aria-labelledby="invoiceModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="invoiceModalLabel">Purchase Invoice</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body" id="invoiceModalBody">
                <!-- Invoice content here -->
              </div>
            </div>
          </div>
        </div>
      `);
    }

    // View Invoice (show modal)
    $(document).on('click', '.view-invoice-btn', function() {
        var id = $(this).data('id');
        $.post('ajax/purchase_ajax.php', {action: 'view_invoice', purchase_id: id}, function(res) {
            if(res.status === 'success') {
                $('#invoiceModalBody').html(res.html);
                var modal = bootstrap.Modal.getOrCreateInstance($('#invoiceModal')[0]);
                modal.show();
            } else {
                toastr.error(res.message || 'Failed to load invoice.');
            }
        }, 'json');
    });

    // Delete Purchase
    $(document).on('click', '.delete-purchase-btn', function() {
        var id = $(this).data('id');
        Swal.fire({
            title: 'Are you sure?',
            text: 'This will delete the purchase!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if(result.isConfirmed) {
                $.post('ajax/purchase_ajax.php', {action: 'delete', purchase_id: id}, function(res) {
                    if(res.status === 'success') {
                        toastr.success(res.message);
                        loadPurchaseDetailsTable();
                    } else {
                        toastr.error(res.message);
                    }
                }, 'json');
            }
        });
    });

    // Add search/filter for Purchase Details
    $(document).on('input', '#purchase-details-pane .form-control-sm', function() {
        var search = $(this).val().toLowerCase();
        $('#purchase-details-table tbody tr').each(function() {
            var rowText = $(this).text().toLowerCase();
            if (rowText.indexOf(search) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Load Supplier Ledger tab on show
    $(document).on('shown.bs.tab', 'button[data-bs-target="#supplier-ledger-pane"]', function() {
        loadSupplierLedgerUI();
    });
    if ($('#supplier-ledger-pane').hasClass('show')) {
        loadSupplierLedgerUI();
    }

    function loadSupplierLedgerUI() {
        // Fetch suppliers for dropdown
        $.post('ajax/purchase_ajax.php', {action: 'get_suppliers'}, function(res) {
            var suppliers = res.suppliers || [];
            var options = suppliers.map(s => `<option value="${s.supplier_id}">${s.name} (${s.phone})</option>`).join('');
            var html = `
        <div class="row mb-3">
          <div class="col-md-4">
            <label class="form-label">Select Supplier</label>
            <select class="form-select" id="ledgerSupplierSelect">
              <option value="">Choose supplier...</option>
              ${options}
            </select>
          </div>
          <!--<div class="col-md-6 text-end align-self-end">
            <button class="btn btn-outline-secondary btn-sm me-2" id="exportLedgerPDF"><i class="fas fa-file-pdf"></i> Export PDF</button>
            <button class="btn btn-outline-success btn-sm" id="exportLedgerExcel"><i class="fas fa-file-excel"></i> Export Excel</button>
          </div>-->
        </div>
        <div id="supplier-ledger-table"></div>
        `;
            $('#supplier-ledger').html(html);
        }, 'json');
    }

    // Fetch and render ledger when supplier selected
    $(document).on('change', '#ledgerSupplierSelect', function() {
        var supplier_id = $(this).val();
        if (!supplier_id) {
            $('#supplier-ledger-table').html('');
            return;
        }
        $.post('ajax/ledger_ajax.php', {action: 'get_ledger', supplier_id: supplier_id}, function(res) {
            if (res.status === 'success') {
                var rows = '';
                if (res.ledger && res.ledger.length) {
                    rows = res.ledger.map(l => `
                  <tr>
                    <td>${l.date}</td>
                    <td>${l.description}</td>
                    <td>${l.debit > 0 ? parseFloat(l.debit).toFixed(2) : ''}</td>
                    <td>${l.credit > 0 ? parseFloat(l.credit).toFixed(2) : ''}</td>
                    <td>${parseFloat(l.balance).toFixed(2)}</td>
                  </tr>
                `).join('');
                } else {
                    rows = '<tr><td colspan="5" class="text-center">No ledger entries found.</td></tr>';
                }
                var tableHtml = `
            <div class="table-responsive mt-3">
              <table class="table table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                  </tr>
                </thead>
                <tbody>
                  ${rows}
                </tbody>
              </table>
            </div>
            `;
                $('#supplier-ledger-table').html(tableHtml);
            } else {
                $('#supplier-ledger-table').html('<div class="text-danger">' + (res.message || 'Failed to load ledger.') + '</div>');
            }
        }, 'json');
    });

    // Export buttons (placeholders)
    $(document).on('click', '#exportLedgerPDF', function() {
        toastr.info('PDF export coming soon!');
    });
    $(document).on('click', '#exportLedgerExcel', function() {
        toastr.info('Excel export coming soon!');
    });

    // Load the correct tab content on first page load
    var $activeTab = $('.nav-tabs .nav-link.active');
    if ($activeTab.length) {
        var target = $activeTab.attr('data-bs-target');
        if (target === '#supplier-management-pane') {
            loadSupplierTable();
        } else if (target === '#purchase-form-pane') {
            loadPurchaseForm();
        } else if (target === '#purchase-details-pane') {
            loadPurchaseDetailsTable();
        } else if (target === '#supplier-ledger-pane') {
            loadSupplierLedgerUI();
        }
    }
}); 