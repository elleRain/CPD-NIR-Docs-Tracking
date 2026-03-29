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

$myDocumentsUrl = '../documents/my_documents.php';
$allDocumentsUrl = '../documents/document.php';
$sharedDocsLogUrl = '../docs_tracking/docs_tracking_log.php';
$documentReviewUrl = '../documents/document_review.php';
$forRevisionUrl = '../documents/for_revision.php';
$sharedFilesUrl = '../documents/my_documents.php?tab=designated';
$receivedFilesUrl  = '../documents/received_files.php';
$designatedToMeUrl = '../documents/action_required.php';
$analyticsUrl = $dashboardUrl;
$auditLogUrl = '../docs_tracking/docs_tracking_log.php';
$reportsUrl = '../docs_tracking/docs_tracking_log.php';
$usersRolesUrl = ($role === 'superadmin') ? '../superadmin/users.php' : $dashboardUrl;

$now = date('Y-m-d H:i:s');
if (!isset($_SESSION['sidebar_seen']) || !is_array($_SESSION['sidebar_seen'])) {
    $_SESSION['sidebar_seen'] = [
        'my_documents_all' => $now,
        'all_documents' => $now,
        'document_review' => $now,
        'action_required' => $now,
        'for_revision' => $now,
        'received_files' => $now,
    ];
}

if ($current_page === 'my_documents.php' && (!isset($_GET['tab']) || $_GET['tab'] !== 'designated')) {
    $_SESSION['sidebar_seen']['my_documents_all'] = $now;
}
if ($current_page === 'document.php' && (!isset($_GET['view']) || $_GET['view'] !== 'designated')) {
    $_SESSION['sidebar_seen']['all_documents'] = $now;
}
if ($current_page === 'document_review.php') {
    $_SESSION['sidebar_seen']['document_review'] = $now;
}
if ($current_page === 'for_revision.php') {
    $_SESSION['sidebar_seen']['for_revision'] = $now;
}
if ($current_page === 'received_files.php') {
    $_SESSION['sidebar_seen']['received_files'] = $now;
}

$myDocumentsCount = 0;
$allDocumentsCount = 0;
$documentReviewCount = 0;
$forRevisionCount = 0;
$actionRequiredCount = 0;
$sharedFilesCount = 0;
$receivedFilesCount = 0;

if (!isset($conn) || !($conn instanceof mysqli)) {
    $connFile = dirname(__DIR__) . '/plugins/conn.php';
    if (file_exists($connFile)) {
        include_once $connFile;
    }
}

if (isset($conn) && ($conn instanceof mysqli) && isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $seenMyDocs = $_SESSION['sidebar_seen']['my_documents_all'] ?? $now;
    $seenAllDocs = $_SESSION['sidebar_seen']['all_documents'] ?? $now;
    $seenReview = $_SESSION['sidebar_seen']['document_review'] ?? $now;
    $seenRevision = $_SESSION['sidebar_seen']['for_revision'] ?? $now;

    $countQuery = static function (mysqli $conn, string $sql, string $types = '', array $params = []): int {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        if ($types !== '' && !empty($params)) {
            $bind_args = [$types];
            foreach ($params as $k => $value) {
                $bind_args[] = &$params[$k];
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_args);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            return 0;
        }

        $result = $stmt->get_result();
        $count = 0;
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row && isset($row['total'])) {
                $count = (int)$row['total'];
            }
        }

        $stmt->close();
        return $count;
    };

    $myDocumentsCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM documents d
         WHERE d.created_by = ?
           AND d.created_at > ?
           AND NOT EXISTS (
               SELECT 1 FROM document_shares sh
               WHERE sh.document_id = d.document_id AND sh.shared_by = ?
           )",
        'isi',
        [$user_id, $seenMyDocs, $user_id]
    );

    $allDocumentsCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM documents d
         WHERE d.status_id = 5
           AND d.created_at > ?
           AND NOT EXISTS (
               SELECT 1 FROM document_shares ds
               WHERE ds.document_id = d.document_id
           )",
        's',
        [$seenAllDocs]
    );

    $documentReviewCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM documents d
                 WHERE d.status_id = 3
                     AND d.created_at > ?",
                's',
                [$seenReview]
    );

    $forRevisionCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM documents d
                 WHERE d.status_id = 4
                     AND d.created_at > ?
                     AND (
                             d.created_by = ?
                             OR EXISTS (
                                     SELECT 1 FROM document_shares sh
                                     WHERE sh.document_id = d.document_id
                                         AND sh.recipient_id = ?
                             )
                     )",
                'sii',
                [$seenRevision, $user_id, $user_id]
    );

    $actionRequiredCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM documents d
         WHERE EXISTS (
             SELECT 1 FROM document_shares ds2
             WHERE ds2.document_id = d.document_id AND ds2.recipient_id = ?
         )
           AND d.status_id = 1",
        'i',
        [$user_id]
    );

    // Shared Files badge: count submitted-by-recipient items waiting owner decision.
    // It disappears only after owner action sets status to Approved/For Revision/Rejected.
    $sharedFilesCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM document_shares ds
         INNER JOIN documents d ON d.document_id = ds.document_id
         WHERE ds.shared_by = ?
           AND d.status_id NOT IN (4, 5, 6)
           AND EXISTS (
               SELECT 1
               FROM document_activity_log dal
               WHERE dal.document_id = ds.document_id
                 AND dal.user_id = ds.recipient_id
                 AND dal.activity_type = 'finished'
           )",
        'i',
        [$user_id]
    );

    $seenReceived = $_SESSION['sidebar_seen']['received_files'] ?? $now;
    $receivedFilesCount = $countQuery(
        $conn,
        "SELECT COUNT(*) AS total
         FROM document_shares ds
         INNER JOIN documents d ON d.document_id = ds.document_id
         WHERE ds.recipient_id = ?
           AND d.status_id = 5
           AND d.created_at > ?",
        'is',
        [$user_id, $seenReceived]
    );
}
?>

<aside class="sidebar">
    <nav class="sidebar-menu">
        <div class="sidebar-section">
            <span class="sidebar-label">Main</span>
            <a href="<?php echo $dashboardUrl; ?>" class="nav-link <?php echo ($current_page == basename($dashboardUrl)) ? 'active' : ''; ?>" data-label="Dashboard">
                <span class="nav-icon">⊞</span>
                <span class="nav-label">Dashboard</span>
            </a>
            <a href="<?php echo $myDocumentsUrl; ?>" class="nav-link <?php echo ($current_page == 'my_documents.php' && (!isset($_GET['tab']) || $_GET['tab'] !== 'designated')) ? 'active' : ''; ?>" data-label="My Documents">
                <span class="nav-icon">🗂</span>
                <span class="nav-label">My Documents</span>
                <?php if ($myDocumentsCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $myDocumentsCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $allDocumentsUrl; ?>" class="nav-link <?php echo ($current_page == 'document.php') ? 'active' : ''; ?>" data-label="All Documents">
                <span class="nav-icon">📁</span>
                <span class="nav-label">All Documents</span>
                <?php if ($allDocumentsCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $allDocumentsCount; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="sidebar-section">
            <span class="sidebar-label">Workflow</span>
            <a href="<?php echo $documentReviewUrl; ?>" class="nav-link <?php echo ($current_page == 'document_review.php') ? 'active' : ''; ?>" data-label="Document Review">
                <span class="nav-icon">✅</span>
                <span class="nav-label">Document Review</span>
                <?php if ($documentReviewCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $documentReviewCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $designatedToMeUrl; ?>" class="nav-link <?php echo (($current_page == 'action_required.php') || ($current_page == 'document.php' && isset($_GET['view']) && $_GET['view'] === 'designated')) ? 'active' : ''; ?>" data-label="Action Required">
                <span class="nav-icon">👤</span>
                <span class="nav-label">Action Required</span>
                <?php if ($actionRequiredCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $actionRequiredCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $forRevisionUrl; ?>" class="nav-link <?php echo ($current_page == 'for_revision.php') ? 'active' : ''; ?>" data-label="For Revision">
                <span class="nav-icon">↩</span>
                <span class="nav-label">For Revision</span>
                <?php if ($forRevisionCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $forRevisionCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo $receivedFilesUrl; ?>" class="nav-link <?php echo ($current_page == 'received_files.php') ? 'active' : ''; ?>" data-label="Shared Files">
                <span class="nav-icon">📂</span>
                <span class="nav-label">Shared Files</span>
                <?php if ($receivedFilesCount > 0): ?>
                <span class="nav-badge badge-blue"><?php echo $receivedFilesCount; ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div class="sidebar-section">
            <span class="sidebar-label">Reports & Analytics</span>
            <a href="<?php echo $sharedDocsLogUrl; ?>" class="nav-link <?php echo ($current_page == 'docs_tracking_log.php') ? 'active' : ''; ?>" data-label="Shared Docs Log">
                <span class="nav-icon">📤</span>
                <span class="nav-label">Shared Docs Log</span>
            </a>
        </div>

        <?php if ($role === 'superadmin'): ?>
        <div class="sidebar-section">
            <span class="sidebar-label">Administration</span>
            <a href="<?php echo $usersRolesUrl; ?>" class="nav-link <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" data-label="Users & Roles">
                <span class="nav-icon">👥</span>
                <span class="nav-label">Users & Roles</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-version">CPD-NIR Document Tracking System v1.0</div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
