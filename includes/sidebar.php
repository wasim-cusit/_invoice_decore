 <style>
.sidebar {
  background: #fff;
  min-height: 100vh;
  box-shadow: 2px 0 10px rgba(0,0,0,0.05);
  position: fixed;
  width: 250px;
  z-index: 100;
  transition: all 0.3s ease;
  left: 0;
  top: 0;
}

.sidebar.collapsed {
  width: 80px;
  overflow: hidden;
  padding-right: 10px;
}

.sidebar.collapsed .sidebar-text {
  display: none;
}

.sidebar.collapsed .sidebar-chevron {
  display: none;
}

.sidebar.collapsed .sidebar-logo-text {
  display: none;
}

.sidebar.collapsed .sidebar-children {
  position: absolute;
  left: 60px;
  top: 0;
  width: 200px;
  background: #fff;
  box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  border-radius: 0 8px 8px 0;
  display: none;
}

.sidebar.collapsed .sidebar-children.show {
  display: block;
}

.sidebar-toggle {
  position: relative;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 8px;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
  transition: all 0.3s ease;
  font-size: 16px;
  color: white;
  margin-right: 15px;
  left: 14px;
}

.sidebar-toggle:hover {
  background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.sidebar-toggle:active {
  transform: translateY(0);
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.sidebar-toggle i {
  transition: transform 0.3s ease;
}

.sidebar.collapsed ~ .sidebar-toggle {
  /* left: 75px; */
  left: 10px;
}

.sidebar.collapsed ~ .sidebar-toggle i {
  transform: rotate(180deg);
}

.main-content {
  margin-left: 250px;
  transition: margin-left 0.3s ease;
}

.main-content.sidebar-collapsed {
  margin-left: 60px;
}

@media (max-width: 991.98px) {
  .sidebar {
    position: static !important;
    width: 100% !important;
    min-height: auto !important;
    box-shadow: none !important;
  }
  
  .sidebar.collapsed {
    width: 100% !important;
  }
  
  .sidebar-toggle {
    display: none !important;
  }
  
  .main-content {
    margin-left: 0 !important;
  }
  
  .main-content.sidebar-collapsed {
    margin-left: 0 !important;
  }
}

@media (max-width: 991.98px) {
  .sidebar {
    position: fixed !important;
    left: -250px;
    top: 0;
    width: 250px !important;
    height: 100vh;
    z-index: 2000;
    transition: left 0.3s;
    box-shadow: 2px 0 10px rgba(0,0,0,0.15);
  }
  .sidebar.mobile-open {
    left: 0 !important;
  }
  .sidebar-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.25);
    z-index: 1999;
  }
  .sidebar-backdrop.show {
    display: block;
  }
  .sidebar-toggle-mobile {
    display: flex !important;
    position: fixed;
    top: 18px;
    left: 18px;
    z-index: 2100;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    border-radius: 8px;
    width: 40px;
    height: 40px;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    font-size: 20px;
    color: white;
  }
}

.sidebar-logo {
  padding: 25px 15px;
  border-bottom: 1px solid #e9ecef;
  text-align: center;
  background: transparent;
  color: #333;
  position: relative;
  overflow: hidden;
}

.sidebar-logo img {
  height: 70px;
  width: auto;
  max-width: 100%;
  display: block;
  margin: 0 auto;
}

.sidebar-logo img {
  height: 70px;
  width: auto;
  max-width: 100%;
  display: block;
  margin: 0 auto;
}

.sidebar-logo-text {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0;
}

.sidebar.collapsed .sidebar-logo {
  padding: 10px 0;
  text-align: center;
}
.sidebar.collapsed .sidebar-logo img {
  height: 40px;
  width: auto;
  margin: 0 auto;
  display: block;
}

.sidebar .sidebar-item {
  color: #343a40;
  border-left: 3px solid transparent;
  transition: all 0.3s;
  border-radius: 4px;
  font-size: 0.97rem;
  text-decoration: none;
  display: flex;
  align-items: center;
  padding: 12px 15px;
  margin-bottom: 2px;
  white-space: nowrap;
}

.sidebar .sidebar-item:hover,
.sidebar .sidebar-item.active {
  background: #e9f5ff;
  border-left: 3px solid #3498db;
  color: #3498db;
  text-decoration: none;
}

.sidebar-section {
  cursor: pointer;
  user-select: none;
  transition: background 0.2s;
  border-radius: 4px;
  font-size: 0.97rem;
  margin-bottom: 2px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 15px;
  white-space: nowrap;
}

.sidebar-section.active, .sidebar-section:hover {
  background: #f0f4fa;
}

.sidebar-children {
  display: none;
  margin-left: 18px;
  margin-bottom: 6px;
}

.sidebar-children.show {
  display: block;
}

.sidebar-chevron {
  transition: transform 0.2s;
  font-size: 0.9em;
}

.sidebar-chevron.rotate {
  transform: rotate(90deg);
}

.sidebar-icon {
  width: 20px;
  text-align: center;
  margin-right: 10px;
}

.sidebar-text {
  flex: 1;
}

/* Tooltip for collapsed sidebar */
.sidebar.collapsed .sidebar-item,
.sidebar.collapsed .sidebar-section {
  position: relative;
}

.sidebar.collapsed .sidebar-item:hover::after,
.sidebar.collapsed .sidebar-section:hover::after {
  content: attr(data-title);
  position: absolute;
  left: 70px;
  top: 50%;
  transform: translateY(-50%);
  background: #333;
  color: white;
  padding: 5px 10px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.sidebar.collapsed .sidebar-item:hover::before,
.sidebar.collapsed .sidebar-section:hover::before {
  content: '';
  position: absolute;
  left: 65px;
  top: 50%;
  transform: translateY(-50%);
  border: 5px solid transparent;
  border-right-color: #333;
  z-index: 1000;
}
</style>

<!-- Mobile Sidebar Toggle Button -->
<button class="sidebar-toggle-mobile d-none" id="sidebarToggleMobile" aria-label="Open Sidebar">
  <i class="fas fa-bars"></i>
</button>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="sidebar p-0" id="sidebar">
  <!-- Logo Section -->
  <div class="sidebar-logo">
    <img src="./logo/mod.jpg" alt="Logo" class="sidebar-logo-img">
  </div>
  
  <div class="p-3">
    <a href="?page=welcome" class="sidebar-item <?= $page === 'welcome' ? 'active' : '' ?>" data-title="Dashboard">
      <i class="fas fa-tachometer-alt sidebar-icon"></i>
      <span class="sidebar-text">Dashboard</span>
    </a>
    
    <a href="?page=new_calculation" class="sidebar-item <?= $page === 'new_calculation' ? 'active' : '' ?>" data-title="New Calculation">
      <i class="fas fa-calculator sidebar-icon"></i>
      <span class="sidebar-text">New Calculation</span>
    </a>
    
    <a href="?page=quotation" class="sidebar-item <?= $page === 'quotation' ? 'active' : '' ?>" data-title="View Quotation">
      <i class="fas fa-eye sidebar-icon"></i>
      <span class="sidebar-text">View Quotation</span>
    </a>

    <a href="?page=purchase" class="sidebar-item <?= $page === 'purchase' ? 'active' : '' ?>" data-title="purchase">
      <i class="fas fa-shopping-cart sidebar-icon"></i>
      <span class="sidebar-text">Purchase</span>
    </a>
    
    <!-- Reports Expandable -->
    <div class="sidebar-section<?= strpos($page, 'reports_') === 0 ? ' active' : '' ?>" id="sidebarReportsBtn" data-title="Reports">
      <div class="d-flex align-items-center">
        <i class="fas fa-file-invoice sidebar-icon"></i>
        <span class="sidebar-text">Reports</span>
      </div>
      <i class="fas fa-chevron-right sidebar-chevron<?= strpos($page, 'reports_') === 0 ? ' rotate' : '' ?>"></i>
    </div>
    <div class="sidebar-children<?= strpos($page, 'reports_') === 0 ? ' show' : '' ?>" id="sidebarReportsChildren">
    <a class="sidebar-item<?= $page === 'report_quotation' ? ' active' : '' ?>" href="?page=report_quotation" data-title="Quotation Report">
        <i class="fas fa-file-alt sidebar-icon"></i>
        <span class="sidebar-text">Quotation Report</span>
      </a>
      <a class="sidebar-item<?= $page === 'reports_invoices' ? ' active' : '' ?>" href="?page=reports_invoices" data-title="Invoices Report">
        <i class="fas fa-file-invoice sidebar-icon"></i>
        <span class="sidebar-text">Invoices Report</span>
      </a>
      <a class="sidebar-item<?= $page === 'reports_expenses' ? ' active' : '' ?>" href="?page=reports_expenses" data-title="Expenses Report">
        <i class="fas fa-money-bill sidebar-icon"></i>
        <span class="sidebar-text">Expenses Report</span>
      </a>
      <a class="sidebar-item<?= $page === 'reports_clients' ? ' active' : '' ?>" href="?page=reports_clients" data-title="Clients Report">
        <i class="fas fa-users sidebar-icon"></i>
        <span class="sidebar-text">Clients Report</span>
      </a>
      <a class="sidebar-item<?= $page === 'reports_materials' ? ' active' : '' ?>" href="?page=reports_materials" data-title="Materials Report">
        <i class="fas fa-boxes sidebar-icon"></i>
        <span class="sidebar-text">Materials Report</span>
      </a>
      <a class="sidebar-item<?= $page === 'reports_hardware' ? ' active' : '' ?>" href="?page=reports_hardware" data-title="Hardware Report">
        <i class="fas fa-tools sidebar-icon"></i>
        <span class="sidebar-text">Hardware Report</span>
      </a>
      
    </div>
    
    <!-- Settings Expandable -->
    <div class="sidebar-section<?= strpos($page, 'settings_') === 0 ? ' active' : '' ?>" id="sidebarSettingsBtn" data-title="Settings">
      <div class="d-flex align-items-center">
        <i class="fas fa-cogs sidebar-icon"></i>
        <span class="sidebar-text">Settings</span>
      </div>
      <i class="fas fa-chevron-right sidebar-chevron<?= strpos($page, 'settings_') === 0 ? ' rotate' : '' ?>"></i>
    </div>
    <div class="sidebar-children<?= strpos($page, 'settings_') === 0 ? ' show' : '' ?>" id="sidebarSettingsChildren">
      <a class="sidebar-item<?= $page === 'settings_materials' ? ' active' : '' ?>" href="?page=settings_materials" data-title="Add Materials">
        <i class="fas fa-box sidebar-icon"></i>
        <span class="sidebar-text">Add Materials</span>
      </a>
      <a class="sidebar-item<?= $page === 'settings_hardware' ? ' active' : '' ?>" href="?page=settings_hardware" data-title="Add Hardware">
        <i class="fas fa-wrench sidebar-icon"></i>
        <span class="sidebar-text">Add Hardware</span>
      </a>
      <a class="sidebar-item<?= $page === 'add_expense' ? ' active' : '' ?>" href="?page=add_expense" data-title="Add Expense">
        <i class="fas fa-plus-circle sidebar-icon"></i>
        <span class="sidebar-text">Add Expense</span>
      </a>
      <a class="sidebar-item<?= $page === 'settings_companies' ? ' active' : '' ?>" href="?page=settings_companies" data-title="Companies">
        <i class="fas fa-building sidebar-icon"></i>
        <span class="sidebar-text">Add Companies</span>
      </a>
      <!-- <a class="sidebar-item<?= $page === 'settings_add_company' ? ' active' : '' ?>" href="?page=settings_add_company" data-title="Add Company">
        <i class="fas fa-plus sidebar-icon"></i>
        <span class="sidebar-text">Add Company</span>
      </a> -->
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const mainContent = document.querySelector('.main-content');
  const appHeader = document.querySelector('.app-header');
  
  // Remove this block:
  // const isDashboard = window.location.search.includes('page=welcome') || window.location.search === '';
  // const savedState = localStorage.getItem('sidebarCollapsed');
  // if (isDashboard && savedState === null) {
  //   sidebar.classList.add('collapsed');
  //   mainContent.classList.add('sidebar-collapsed');
  //   appHeader.classList.add('sidebar-collapsed');
  //   localStorage.setItem('sidebarCollapsed', 'true');
  // } else if (savedState === 'true') {
  //   sidebar.classList.add('collapsed');
  //   mainContent.classList.add('sidebar-collapsed');
  //   appHeader.classList.add('sidebar-collapsed');
  // }
  // Instead, always start open (do not add 'collapsed' by default)
  
  // Toggle sidebar
  sidebarToggle.addEventListener('click', function() {
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('sidebar-collapsed');
    appHeader.classList.toggle('sidebar-collapsed');
    
    // Save state
    const isCollapsed = sidebar.classList.contains('collapsed');
    localStorage.setItem('sidebarCollapsed', isCollapsed.toString());
  });
  
  // Function to save sidebar state to session storage
  function saveSidebarState() {
    const openSections = [];
    document.querySelectorAll('.sidebar-children.show').forEach(el => {
      openSections.push(el.id);
    });
    sessionStorage.setItem('sidebarOpenSections', JSON.stringify(openSections));
  }

  // Function to restore sidebar state from session storage
  function restoreSidebarState() {
    const savedState = sessionStorage.getItem('sidebarOpenSections');
    if (savedState) {
      const openSections = JSON.parse(savedState);
      openSections.forEach(sectionId => {
        const section = document.getElementById(sectionId);
        const btnId = sectionId.replace('Children', 'Btn');
        const btn = document.getElementById(btnId);
        const chevron = btn.querySelector('.sidebar-chevron');
        
        if (section && btn) {
          section.classList.add('show');
          btn.classList.add('active');
          chevron.classList.add('rotate');
        }
      });
    }
  }

  // Function to close all sections except the specified one
  function closeOtherSections(keepOpenId) {
    document.querySelectorAll('.sidebar-children').forEach(el => {
      if (el.id !== keepOpenId) {
        el.classList.remove('show');
      }
    });
    document.querySelectorAll('.sidebar-section').forEach(el => {
      if (el.id !== keepOpenId.replace('Children', 'Btn')) {
        el.classList.remove('active');
      }
    });
    document.querySelectorAll('.sidebar-chevron').forEach(el => {
      const parentBtn = el.closest('.sidebar-section');
      if (parentBtn && parentBtn.id !== keepOpenId.replace('Children', 'Btn')) {
        el.classList.remove('rotate');
      }
    });
  }

  // Function to open a specific section
  function openSection(btnId, childrenId) {
    const btn = document.getElementById(btnId);
    const children = document.getElementById(childrenId);
    const chevron = btn.querySelector('.sidebar-chevron');
    
    children.classList.add('show');
    btn.classList.add('active');
    chevron.classList.add('rotate');
  }

  // Restore sidebar state on page load
  restoreSidebarState();

  // Handle section header clicks (Reports, Settings)
  document.querySelectorAll('.sidebar-section').forEach(section => {
    section.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const childrenId = this.id.replace('Btn', 'Children');
      const children = document.getElementById(childrenId);
      const chevron = this.querySelector('.sidebar-chevron');
      
      const isOpen = children.classList.contains('show');
      
      if (isOpen) {
        // Close this section
        children.classList.remove('show');
        this.classList.remove('active');
        chevron.classList.remove('rotate');
      } else {
        // Close other sections and open this one
        closeOtherSections(childrenId);
        openSection(this.id, childrenId);
      }
      
      // Save state after change
      saveSidebarState();
    });
  });

  // Handle child link clicks (Add Expense, Quotation Report, etc.)
  document.querySelectorAll('.sidebar-children .sidebar-item').forEach(link => {
    link.addEventListener('click', function(e) {
      e.stopPropagation();
      
      // Find which section this link belongs to
      const parentSection = this.closest('.sidebar-children');
      const parentBtnId = parentSection.id.replace('Children', 'Btn');
      
      // Keep the parent section open and close others
      closeOtherSections(parentSection.id);
      openSection(parentBtnId, parentSection.id);
      
      // Save state before navigation
      saveSidebarState();
      
      // Allow the link to work normally (navigation)
      // The link will navigate to the new page
    });
  });

  // Handle main navigation links (Dashboard, New Calculation, View Quotation)
  document.querySelectorAll('.sidebar-item').forEach(link => {
    link.addEventListener('click', function(e) {
      // Check if this is a main navigation link (not a child link)
      if (!this.closest('.sidebar-children')) {
        // Close all sections when navigating to main pages
        document.querySelectorAll('.sidebar-children').forEach(el => {
          el.classList.remove('show');
        });
        document.querySelectorAll('.sidebar-section').forEach(el => {
          el.classList.remove('active');
        });
        document.querySelectorAll('.sidebar-chevron').forEach(el => {
          el.classList.remove('rotate');
        });
        
        // Clear saved state
        sessionStorage.removeItem('sidebarOpenSections');
      }
    });
  });
  
  // Auto-expand sidebar when hovering over collapsed sections
  document.querySelectorAll('.sidebar-section').forEach(section => {
    section.addEventListener('mouseenter', function() {
      if (sidebar.classList.contains('collapsed')) {
        const childrenId = this.id.replace('Btn', 'Children');
        const children = document.getElementById(childrenId);
        if (children) {
          children.classList.add('show');
        }
      }
    });
    
    section.addEventListener('mouseleave', function() {
      if (sidebar.classList.contains('collapsed')) {
        const childrenId = this.id.replace('Btn', 'Children');
        const children = document.getElementById(childrenId);
        if (children) {
          children.classList.remove('show');
        }
      }
    });
  });

  // Mobile sidebar toggle
  const sidebarToggleMobile = document.getElementById('sidebarToggleMobile');
  const sidebarBackdrop = document.getElementById('sidebarBackdrop');
  function openSidebarMobile() {
    sidebar.classList.add('mobile-open');
    sidebarBackdrop.classList.add('show');
    document.body.style.overflow = 'hidden';
  }
  function closeSidebarMobile() {
    sidebar.classList.remove('mobile-open');
    sidebarBackdrop.classList.remove('show');
    document.body.style.overflow = '';
  }
  sidebarToggleMobile.addEventListener('click', openSidebarMobile);
  sidebarBackdrop.addEventListener('click', closeSidebarMobile);
  // Show toggle button on mobile
  function handleResize() {
    if (window.innerWidth <= 991) {
      sidebarToggleMobile.classList.remove('d-none');
    } else {
      sidebarToggleMobile.classList.add('d-none');
      closeSidebarMobile();
    }
  }
  window.addEventListener('resize', handleResize);
  handleResize();
});
</script>