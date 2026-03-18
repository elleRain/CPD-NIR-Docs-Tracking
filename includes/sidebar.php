<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit();
}

$role = $_SESSION['role'];
$current_page = basename($_SERVER['PHP_SELF']);

$roleConfig = [
    'superadmin' => ['base' => '../dashboards/', 'dashboard' => 'superadmin_dashboard.php'],
    'admin' => ['base' => '../dashboards/', 'dashboard' => 'admin_dashboard.php'],
    'staff' => ['base' => '../dashboards/', 'dashboard' => 'staff_dashboard.php'],
];

$config = $roleConfig[$role];
$dashboardUrl = $config['base'] . $config['dashboard'];
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo $dashboardUrl; ?>" class="text-decoration-none d-flex align-items-center brand">
            <div class="brand-emblem">D</div>
            <div class="brand-text">
                <strong>CPD-NIR</strong>
                <span class="sidebar-subtitle">Document Tracking</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-menu">
        <div class="sidebar-group">Main</div>
        <a href="<?php echo $dashboardUrl; ?>" class="nav-link <?php echo ($current_page == basename($dashboardUrl)) ? 'active' : ''; ?>" data-label="Dashboard">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>
        <a href="../documents/my_documents.php" class="nav-link <?php echo ($current_page == 'my_documents.php') ? 'active' : ''; ?>" data-label="My Documents">
            <i class="bi bi-person-workspace"></i>
            <span>My Documents</span>
        </a>

        <div class="sidebar-group">Workflow</div>
        <a href="../documents/document.php" class="nav-link <?php echo ($current_page == 'document.php') ? 'active' : ''; ?>" data-label="Documents">
            <i class="bi bi-folder2-open"></i>
            <span>Documents</span>
        </a>
        <a href="../documents/document_review.php" class="nav-link <?php echo ($current_page == 'document_review.php') ? 'active' : ''; ?>" data-label="Document Review">
            <i class="bi bi-check-circle"></i>
            <span>Document Review</span>
        </a>
        <a href="../docs_tracking/docs_tracking_log.php" class="nav-link <?php echo ($current_page == 'docs_tracking_log.php') ? 'active' : ''; ?>" data-label="Shared Docs Log">
            <i class="bi bi-share"></i>
            <span>Shared Docs Log</span>
        </a>

        <div class="sidebar-group">Reports & Analytics</div>
        <a href="../docs_tracking/get_audit_log.php" class="nav-link" data-label="Audit Log">
            <i class="bi bi-graph-up"></i>
            <span>Audit Log</span>
        </a>

        <?php if ($role === 'superadmin'): ?>
        <div class="sidebar-group">Administration</div>
        <a href="../superadmin/users.php" class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" data-label="Users">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="storage-title">Storage Usage</div>
        <div class="storage-bar-wrap">
            <div class="storage-bar">
                <div class="storage-bar-fill" style="width: 35%;"></div>
            </div>
            <small>35% used</small>
        </div>

        <div class="quick-actions">
            <button class="qa-btn" title="Upload"><i class="bi bi-cloud-upload"></i><span>Upload</span></button>
            <button class="qa-btn" title="New"><i class="bi bi-file-earmark-plus"></i><span>New</span></button>
            <button class="qa-btn" title="Report"><i class="bi bi-bar-chart"></i><span>Reports</span></button>
            <button class="qa-btn" title="Help"><i class="bi bi-question-circle"></i><span>Help</span></button>
        </div>
    </div>
</aside>
