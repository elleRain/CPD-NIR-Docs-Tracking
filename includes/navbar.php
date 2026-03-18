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
        <div class="brand-emblem">D</div>
        <div class="brand-text">
            <strong>CPD-NIR</strong>
            <span>Document Tracking</span>
        </div>
        <button class="sidebar-toggle-btn" id="sidebarToggle" aria-label="Toggle sidebar">
            <span class="toggle-icon">☰</span>
        </button>
    </div>

    <div class="nav-center">
        <div class="nav-search">
            <i class="bi bi-search nav-search-icon"></i>
            <input type="text" class="form-control" placeholder="Search documents..." id="globalSearchInput" />
        </div>
        <div class="nav-date" id="liveDate"></div>
    </div>

    <div class="nav-right">
        <button class="btn-icon" title="Help"><i class="bi bi-question-circle"></i></button>
        <button class="btn-icon" title="Reports"><i class="bi bi-bar-chart"></i></button>
        <div class="dropdown">
            <button class="btn-icon position-relative" id="notifBtn" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
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
        <div class="divider"></div>
        <div class="avatar-chip">
            <div class="av"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></div>
            <div class="av-name">
                <div><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                <small><?php echo htmlspecialchars($roleDisplay); ?></small>
            </div>
        </div>
    </div>
</header>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const header = document.querySelector('.header');

        if (!sidebarToggle || !sidebar || !mainContent || !header) {
            return;
        }

        // Restore state from localStorage if available
        try {
            const saved = localStorage.getItem('sidebarCollapsed');
            if (saved === 'true') {
                document.body.classList.add('sidebar-collapsed');
                sidebar.classList.add('collapsed');
                mainContent.classList.add('sidebar-collapsed');
                header.classList.add('sidebar-collapsed');
            }
        } catch (err) {
            console.warn('sidebar state localStorage not available', err);
        }

        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();

            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('sidebar-collapsed');
            header.classList.toggle('sidebar-collapsed');
            document.body.classList.toggle('sidebar-collapsed');

            try {
                localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed') ? 'true' : 'false');
            } catch (err) {
                console.warn('sidebar state localStorage write failed', err);
            }
        });

        const liveDateEl = document.getElementById('liveDate');
        function updateLiveDate() {
            if (!liveDateEl) return;
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            liveDateEl.textContent = now.toLocaleString('en-US', options);
        }
        updateLiveDate();
        setInterval(updateLiveDate, 60000);
    });
</script>
