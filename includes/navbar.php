<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit();
}

$role = $_SESSION['role'];
$roleDisplay = (
    $role === 'superadmin' ? 'Superadmin' :
    ($role === 'admin' ? 'Admin' : 'Staff')
);
$roleLabelClass = (
    $role === 'superadmin' ? 'bg-primary' :
    ($role === 'admin' ? 'bg-secondary' : 'bg-info')
);
?>
<!-- Header -->
<header class="header">
    <div class="nav-brand">
        <div class="brand-emblem">
            <img src="../assets/img/logo%20no%20bg.png" alt="CPD Logo" class="brand-logo-img">
        </div>
        <div class="brand-text">
            <strong>CPD-NIR</strong>
            <span>Document Tracking System</span>
        </div>
    </div>

    <button class="sidebar-toggle-btn" id="sidebarToggle" type="button" aria-label="Toggle sidebar" title="Toggle sidebar">
        <span class="toggle-icon">☰</span>
    </button>

    <div class="nav-center">
        <div class="nav-date" id="liveDate"></div>
    </div>

    <div class="nav-right">
        <div class="dropdown">
            <button class="nav-icon-btn position-relative" id="notifBtn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                <i class="bi bi-bell"></i>
                <span class="notif-badge">3</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                <li class="dropdown-item unread">New document shared with you</li>
                <li class="dropdown-item">Document approved</li>
                <li class="dropdown-item">Document review requested</li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../notification/notifications_page.php">View all notifications</a></li>
            </ul>
        </div>
        <div class="nav-divider"></div>
        <div class="dropdown">
            <button class="nav-user btn p-0" id="profileMenuBtn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Account options">
                <div class="nav-avatar"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
                <div class="nav-user-info">
                    <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
                    <span><?php echo htmlspecialchars($roleDisplay); ?></span>
                </div>
                <i class="bi bi-chevron-down ms-1"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileMenuBtn">
                <li>
                    <a class="dropdown-item" href="../user_profile/profile.php">
                        <i class="bi bi-person-circle me-2"></i>My Profile
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="../logout.php">
                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('sidebarToggle');
        if (!toggle) return;

        var MOBILE = 767;   // <= this = mobile drawer
        var TABLET = 1199;  // <= this (and > MOBILE) = tablet auto-collapse

        function isMobile()  { return window.innerWidth <= MOBILE; }
        function isTablet()  { return window.innerWidth > MOBILE && window.innerWidth <= TABLET; }

        function ls(k, v) { try { v === undefined ? null : localStorage.setItem(k, v); return localStorage.getItem(k); } catch(e) { return null; } }

        function initState() {
            document.body.classList.remove('sidebar-open');
            if (isMobile()) {
                // Mobile: sidebar hidden by default (CSS), no persistent state
                document.body.classList.remove('sidebar-collapsed', 'sidebar-expanded');
            } else if (isTablet()) {
                // Tablet: auto icon-strip unless user explicitly expanded
                document.body.classList.remove('sidebar-collapsed');
                document.body.classList.toggle('sidebar-expanded', ls('sidebarExpanded') === 'true');
            } else {
                // Desktop: restore collapsed preference
                document.body.classList.remove('sidebar-expanded');
                document.body.classList.toggle('sidebar-collapsed', ls('sidebarCollapsed') === 'true');
            }
        }

        initState();

        // Re-apply on resize (debounced)
        var resizeTimer;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(initState, 150);
        });

        // Toggle button click
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            if (isMobile()) {
                document.body.classList.toggle('sidebar-open');
            } else if (isTablet()) {
                var next = !document.body.classList.contains('sidebar-expanded');
                document.body.classList.toggle('sidebar-expanded', next);
                ls('sidebarExpanded', next ? 'true' : 'false');
            } else {
                var next = !document.body.classList.contains('sidebar-collapsed');
                document.body.classList.toggle('sidebar-collapsed', next);
                ls('sidebarCollapsed', next ? 'true' : 'false');
            }
        });

        // Close mobile drawer when backdrop is clicked
        document.addEventListener('click', function (e) {
            if (e.target && e.target.id === 'sidebarBackdrop') {
                document.body.classList.remove('sidebar-open');
            }
        });

        // Live date/time in navbar
        var liveDateEl = document.getElementById('liveDate');
        function updateLiveDate() {
            if (!liveDateEl) return;
            var now = new Date();
            liveDateEl.textContent = now.toLocaleString('en-US', {
                weekday: 'long', year: 'numeric', month: 'short',
                day: 'numeric', hour: '2-digit', minute: '2-digit'
            });
        }
        updateLiveDate();
        setInterval(updateLiveDate, 60000);
    });
</script>
