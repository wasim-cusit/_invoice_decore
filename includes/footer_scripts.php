<!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<style>
#toast-container .toast {
  border-radius: 2em !important;
  box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}
</style>

<script>
  // Configure toastr with proper top-right positioning
  toastr.options = {
    "positionClass": "toast-top-right",
    "closeButton": true,
    "progressBar": true,
    "timeOut": "4000",
    "preventDuplicates": true,
    "newestOnTop": true,
    "showEasing": "swing",
    "hideEasing": "linear",
    "showMethod": "fadeIn",
    "hideMethod": "fadeOut"
  };

  // Override toastr CSS to ensure top-right positioning
  $(document).ready(function() {
    // Remove any conflicting position classes
    $('#toast-container').removeClass(
      'toast-bottom-full-width toast-top-full-width ' +
      'toast-bottom-left toast-bottom-right ' +
      'toast-top-left toast-bottom-center toast-top-center'
    ).addClass('toast-top-right');
    
    // Force position with CSS
    $('<style>')
      .prop('type', 'text/css')
      .html(
        '#toast-container.toast-top-right {' +
        '  top: 65px;' +
        '  right: 12px;' +
        '}' +
        '#toast-container {' +
        '  z-index: 999999;' +
        '}'
      )
      .appendTo('head');
  });

  // Initialize all Bootstrap dropdowns
  $(document).ready(function() {
    $('.dropdown-toggle').dropdown();
    
    // Highlight active dropdown parent when child is active
    $('.dropdown-item.active').each(function() {
      $(this).closest('.dropdown').find('.dropdown-toggle').addClass('active');
    });

    // Load dashboard stats when on the dashboard page
    if (window.location.search.includes('page=welcome') || window.location.pathname.endsWith('/')) {
      loadDashboardStats();
      // Refresh stats every 60 seconds
      setInterval(loadDashboardStats, 60000);
    }
    
    // Add active class to dropdown items
    $('.dropdown-item').each(function() {
      if (window.location.search.includes($(this).attr('href').split('=')[1])) {
        $(this).addClass('active');
      }
    });
  });

  function loadDashboardStats() {
    // Show loading state
    $('.stat-card h2').html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>');
    
    $.ajax({
      url: 'ajax_get_summary.php',
      type: 'GET',
      dataType: 'json',
      success: function(data) {
        if (data.status === 'success') {
          // Update the counts
          $('#quotationCount').text(data.quotations);
          $('#companyCount').text(data.companies);
          $('#expenseTotal').html('<span class="text-rupee">Rs</span>' + data.expenses_total);
          $('#clientCount').text(data.clients);
          
          // Update the timestamp
          const now = new Date();
          $('.stat-card small').text('Updated at ' + now.toLocaleTimeString());
        } else {
          showDashboardError(data.message || 'Unknown error occurred');
        }
      },
      error: function(xhr, status, error) {
        showDashboardError('Failed to connect to server');
      }
    });
  }

  function showDashboardError(message) {
    $('.stat-card h2').html('<span class="text-danger">Error</span>');
    $('.stat-card small').text(message);
    console.error('Dashboard error:', message);
  }
</script>