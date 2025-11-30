<?php
// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$is_messaging_page = ($current_page === 'messaging.php');

// Logic: Show badge ONLY if NOT on messaging page AND count > 0
$show_badge = (!$is_messaging_page && isset($unread_count_display) && $unread_count_display > 0);
?>

<!-- layout preamble removed from here: included in HEAD via includes/layout_preamble.php -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="top-bar">
            <div class="logo-container">
                <i class='bx bxl-c-plus-plus logo' style="font-size: 2rem; color: #6366f1;"></i>
                <h2 class="logo-text">SmartStudy</h2>
                <button id="sidebarToggle" type="button" class="btn-toggle" aria-expanded="true" aria-controls="sidebar" role="button" aria-label="Toggle sidebar" title="Toggle sidebar">
                    <i class='bx bx-menu' aria-hidden="true"></i>
                    <span class="sr-only" aria-hidden="false">Toggle sidebar</span>
                </button>
            </div>
        </div>
        
        <!-- PROFILE CARD REMOVED — sidebar will not display the user profile here per request -->

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class='bx bxs-dashboard nav-icon'></i><span>Dashboard</span>
            </a>
            <a href="learning_resources.php" class="nav-item <?php echo ($current_page == 'learning_resources.php') ? 'active' : ''; ?>">
                <i class='bx bxs-book nav-icon'></i><span>Resources</span>
            </a>
            <a href="messaging.php" class="nav-item <?php echo $is_messaging_page ? 'active' : ''; ?>">
                <i class='bx bxs-message-dots nav-icon'></i><span>Messaging</span>
                
                <span id="sidebarMsgBadge" class="nav-badge" style="display: <?php echo $show_badge ? 'inline-block' : 'none'; ?>;">
                    <?php echo $unread_count_display ?? 0; ?>
                </span>
            </a>
            <a href="settings.php" class="nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                <i class='bx bxs-cog nav-icon'></i><span>Settings</span>
            </a>
            <?php
            // Show Admin Dashboard link when user is in ADMIN_USER_IDS
            if (file_exists(__DIR__ . '/admin_config.php')) include_once __DIR__ . '/admin_config.php';
            if (isset($_SESSION['user_id']) && defined('ADMIN_USER_IDS') && in_array((int)$_SESSION['user_id'], ADMIN_USER_IDS)):
            ?>
            <a href="admin_dashboard.php" class="nav-item <?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>" style="border-left: 3px solid var(--primary)">
                <i class='bx bxs-shield-alt nav-icon'></i><span>Admin</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <div class="sidebar-footer" style="margin-top: auto; display:flex; flex-direction:column; gap:8px; padding:12px;">
        <a href="faq.php" class="nav-item <?php echo ($current_page == 'faq.php') ? 'active' : ''; ?>" style="padding:8px 10px; border-radius:8px;">
            <i class='bx bxs-help-circle nav-icon' style="font-size:1.05rem"></i><span>FAQ</span>
        </a>
        <a href="#" class="nav-item logout openLogoutModal">
            <i class='bx bx-log-out nav-icon'></i><span>Logout</span>
        </a>
    </div>
</aside>
<!-- layout styles moved to css/layout.css -->