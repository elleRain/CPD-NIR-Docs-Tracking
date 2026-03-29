<?php
session_start();
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header("Location: ../index.php");
    exit();
}
$role = $_SESSION['role'];
include dirname(__DIR__) . '/plugins/conn.php';
include dirname(__DIR__) . '/notification/notification_helpers.php';
require_once dirname(__DIR__) . '/plugins/mailer.php';
$user_id = (int)$_SESSION['user_id'];
$is_action_required_route = basename($_SERVER['PHP_SELF']) === 'action_required.php';
$action_required_url = $is_action_required_route ? 'action_required.php' : 'document.php?view=designated';

function build_documents_absolute_url($relativePath) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $rootPath = rtrim((string)preg_replace('#/documents$#', '', $scriptDir), '/');

    return $scheme . $host . $rootPath . '/' . ltrim($relativePath, '/');
}

function build_public_file_url($filePath) {
    $raw = trim((string)$filePath);
    if ($raw === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $raw);
    while (strpos($normalized, '../') === 0) {
        $normalized = substr($normalized, 3);
    }

    return build_documents_absolute_url(ltrim($normalized, '/'));
}

// Handle Finish Document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finish_document'])) {
    $doc_id = isset($_POST['finish_document_id']) ? (int)$_POST['finish_document_id'] : 0;
    if ($doc_id > 0) {
        $remarks = isset($_POST['finish_remarks']) ? trim($_POST['finish_remarks']) : 'Finished working on document';
        if (empty($remarks)) {
            $remarks = 'Finished working on document';
        }
        // Log Activity
        $stmt_act = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'finished', ?, NOW())");
        if ($stmt_act) {
            $stmt_act->bind_param("iis", $doc_id, $user_id, $remarks);
            $stmt_act->execute();
            $stmt_act->close();
        }
        header("Location: " . $action_required_url . "?msg=finished");
        exit();
    }
}

$upload_error = '';
$upload_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_updated_file'])) {
    $document_id = isset($_POST['update_document_id']) ? (int)$_POST['update_document_id'] : 0;
    $remarks = isset($_POST['update_remarks']) ? trim($_POST['update_remarks']) : '';
    if ($document_id > 0 && isset($_FILES['updated_file']) && $_FILES['updated_file']['error'] === 0) {
        $file_name = $_FILES['updated_file']['name'];
        $file_tmp = $_FILES['updated_file']['tmp_name'];
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) {
            @mkdir($upload_dir, 0775, true);
        }
        $unique_name = time() . '_' . $file_name;
        $file_path = $upload_dir . $unique_name;
        if (move_uploaded_file($file_tmp, $file_path)) {
            $stmt_att_upd = $conn->prepare("UPDATE attachments SET file_name = ?, file_path = ? WHERE document_id = ?");
            if ($stmt_att_upd) {
                $stmt_att_upd->bind_param("ssi", $file_name, $file_path, $document_id);
                $stmt_att_upd->execute();
                if ($stmt_att_upd->affected_rows === 0) {
                    $stmt_att_ins = $conn->prepare("INSERT INTO attachments (document_id, file_name, file_path) VALUES (?, ?, ?)");
                    if ($stmt_att_ins) {
                        $stmt_att_ins->bind_param("iss", $document_id, $file_name, $file_path);
                        $stmt_att_ins->execute();
                        $stmt_att_ins->close();
                    }
                }
                $stmt_att_upd->close();
            }

            // Mark previous history as Old
            $stmt_old = $conn->prepare("UPDATE document_file_history SET status = 'Old' WHERE document_id = ?");
            if ($stmt_old) {
                $stmt_old->bind_param("i", $document_id);
                $stmt_old->execute();
                $stmt_old->close();
            }

            // Log file history
            $stmt_hist = $conn->prepare("INSERT INTO document_file_history (document_id, file_name, file_path, updated_by, remarks, status, updated_at) VALUES (?, ?, ?, ?, ?, 'Current', NOW())");
            if ($stmt_hist) {
                $stmt_hist->bind_param("issis", $document_id, $file_name, $file_path, $user_id, $remarks);
                $stmt_hist->execute();
                $stmt_hist->close();
            }

            // Log activity
            $stmt_act = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'upload', ?, NOW())");
            if ($stmt_act) {
                $upload_details = "Uploaded updated file: " . $file_name;
                $stmt_act->bind_param("iis", $document_id, $user_id, $upload_details);
                $stmt_act->execute();
                $stmt_act->close();
            }

            $action = "Uploaded updated file";
            $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
            if ($stmt_log) {
                $stmt_log->bind_param("isi", $user_id, $action, $document_id);
                $stmt_log->execute();
                $stmt_log->close();
            }
            $upload_success = 'File updated successfully';
        } else {
            $upload_error = 'Failed to upload file';
        }
    } else {
        $upload_error = 'Invalid document or file';
    }
}

$share_users = [];
$share_user_lookup = [];
$users_sql = "SELECT user_id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role 
              FROM users 
              WHERE user_id != ? 
              ORDER BY full_name ASC";
$stmt_users = $conn->prepare($users_sql);
if ($stmt_users) {
    $stmt_users->bind_param("i", $user_id);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $share_users[] = $row;
        $share_user_lookup[(int)$row['user_id']] = $row;
    }
    $stmt_users->close();
}

$sender_name = trim((string)($_SESSION['username'] ?? ''));
$sender_stmt = $conn->prepare("SELECT CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name FROM users WHERE user_id = ? LIMIT 1");
if ($sender_stmt) {
    $sender_stmt->bind_param('i', $user_id);
    $sender_stmt->execute();
    $sender_stmt->bind_result($sender_full_name);
    if ($sender_stmt->fetch() && trim((string)$sender_full_name) !== '') {
        $sender_name = trim((string)$sender_full_name);
    }
    $sender_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['share_document'])) {
    $document_id = isset($_POST['share_document_id']) ? (int)$_POST['share_document_id'] : 0;
    $selected_users = isset($_POST['share_user_ids']) && is_array($_POST['share_user_ids']) ? $_POST['share_user_ids'] : [];
    $doc_title = 'Document';
    $email_document = [
        'title' => 'Document',
        'tracking_number' => '',
        'description' => '',
        'file_name' => '',
        'type_name' => '',
        'required_action' => '',
        'shared_at' => date('M d, Y h:i A'),
    ];
    $email_attachments = [];
    if ($document_id > 0) {
        $doc_title_stmt = $conn->prepare("SELECT d.title, d.tracking_number, d.description, dt.type_name,
                                                 a.file_name, a.file_path,
                                                 (SELECT remark FROM document_remarks dr WHERE dr.document_id = d.document_id AND dr.remark LIKE 'Required Action:%' ORDER BY dr.created_at DESC LIMIT 1) AS required_action_remark
                                          FROM documents d
                                          LEFT JOIN document_types dt ON dt.type_id = d.type_id
                                          LEFT JOIN attachments a ON a.document_id = d.document_id
                                          WHERE d.document_id = ?
                                          LIMIT 1");
        if ($doc_title_stmt) {
            $doc_title_stmt->bind_param('i', $document_id);
            $doc_title_stmt->execute();
            $doc_result = $doc_title_stmt->get_result();
            if ($doc_result && ($doc_row = $doc_result->fetch_assoc())) {
                $doc_title = $doc_row['title'] ?: 'Document';
                $required_action = trim((string)($doc_row['required_action_remark'] ?? ''));
                if ($required_action !== '' && stripos($required_action, 'Required Action:') === 0) {
                    $required_action = trim(substr($required_action, strlen('Required Action:')));
                }
                $email_document = [
                    'title' => $doc_title,
                    'tracking_number' => (string)($doc_row['tracking_number'] ?? ''),
                    'description' => (string)($doc_row['description'] ?? ''),
                    'file_name' => (string)($doc_row['file_name'] ?? ''),
                    'file_url' => build_public_file_url((string)($doc_row['file_path'] ?? '')),
                    'type_name' => (string)($doc_row['type_name'] ?? ''),
                    'required_action' => $required_action,
                    'shared_at' => date('M d, Y h:i A'),
                ];
                if (!empty($doc_row['file_path']) && !empty($doc_row['file_name'])) {
                    $email_attachments[] = [
                        'path' => (string)$doc_row['file_path'],
                        'name' => (string)$doc_row['file_name'],
                    ];
                }
            }
            $doc_title_stmt->close();
        }
    }

    if ($document_id > 0 && !empty($selected_users)) {
        $stmt_share = $conn->prepare("INSERT INTO document_shares (document_id, recipient_id, shared_by, created_at) VALUES (?, ?, ?, NOW())");
        if ($stmt_share) {
            foreach ($selected_users as $recipient_id) {
                $recipient_id = (int)$recipient_id;
                if ($recipient_id > 0) {
                    $stmt_share->bind_param("iii", $document_id, $recipient_id, $user_id);
                    $stmt_share->execute();
                }
            }
            $stmt_share->close();
            
            // Log Activity
            $share_count = count($selected_users);
            $share_details = "Shared document with $share_count user" . ($share_count > 1 ? 's' : '');
            
            // Iterate over selected users to log individual share events
            $share_destination_url = !empty($email_document['required_action'])
                ? build_documents_absolute_url('documents/action_required.php')
                : build_documents_absolute_url('documents/received_files.php');
            $login_url = build_documents_absolute_url('index.php');
            $logo_url = build_documents_absolute_url('assets/img/logo%20no%20bg.png');
            $email_subject = 'CPD-NIR File Shared: ' . $doc_title;
            if (!empty($email_document['required_action'])) {
                $email_subject = 'CPD-NIR ' . $email_document['required_action'] . ' File Shared: ' . $doc_title;
            }
            foreach ($selected_users as $recipient_id) {
                $recipient_id = intval($recipient_id);
                // Fetch recipient name for details
                $res_name = $conn->query("SELECT CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as full_name FROM users WHERE user_id = $recipient_id");
                $recipient_name = ($res_name && $row_name = $res_name->fetch_assoc()) ? $row_name['full_name'] : 'User';
                
                $single_share_details = "Successfully shared the document with " . $recipient_name;
                
                $stmt_act = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, recipient_id, created_at) VALUES (?, ?, 'shared', ?, ?, NOW())");
                if ($stmt_act) {
                    $stmt_act->bind_param("iisi", $document_id, $user_id, $single_share_details, $recipient_id);
                    $stmt_act->execute();
                    $stmt_act->close();

                    // Create notification for recipient
                    createNotification(
                        $conn,
                        $recipient_id,
                        'Document shared with you',
                        "A document has been shared with you: " . $doc_title,
                        '../documents/action_required.php',
                        'info',
                        $user_id,
                        $document_id
                    );

                    if (isset($share_user_lookup[$recipient_id])) {
                        $recipient_email = trim((string)($share_user_lookup[$recipient_id]['email'] ?? ''));
                        if ($recipient_email !== '') {
                            $recipient_display_name = trim((string)($share_user_lookup[$recipient_id]['full_name'] ?? $recipient_name));
                            $inline_email_assets = [];
                            list($share_html, $share_plain) = build_document_share_email(
                                $recipient_display_name,
                                $sender_name,
                                $email_document,
                                $share_destination_url,
                                $login_url,
                                $logo_url,
                                $inline_email_assets
                            );
                            $email_payload_attachments = array_merge($inline_email_assets, $email_attachments);
                            $mail_error = '';
                            send_app_email(
                                $recipient_email,
                                $recipient_display_name,
                                $email_subject,
                                $share_html,
                                $share_plain,
                                $mail_error,
                                $email_payload_attachments
                            );
                        }
                    }
                }
            }
        }
    }
    if ($is_action_required_route || (isset($_GET['view']) && $_GET['view'] === 'designated')) {
        header("Location: " . $action_required_url);
    } else {
        header("Location: document.php?view=all");
    }
    exit();
}

// Fetch document types
$types_query = "SELECT dt.type_id, dt.type_name, COUNT(d.document_id) as file_count 
                FROM document_types dt 
                LEFT JOIN documents d ON dt.type_id = d.type_id AND d.status_id = 5
                GROUP BY dt.type_id, dt.type_name";
$types_result = $conn->query($types_query);
$types = [];
while($row = $types_result->fetch_assoc()) {
    $types[] = $row;
}

// Fetch document statuses
$status_query = "SELECT * FROM document_status WHERE status_id = 5";
$status_result = $conn->query($status_query);
$statuses = [];
while($row = $status_result->fetch_assoc()) {
    $statuses[] = $row;
}

// Filter Logic
$view = (isset($_GET['view']) && in_array($_GET['view'], ['designated'])) ? $_GET['view'] : 'all';
$required_filter = isset($_GET['required']) ? strtolower(trim($_GET['required'])) : 'all';
$allowed_required_filters = ['all', 'approval', 'review', 'pending'];
if (!in_array($required_filter, $allowed_required_filters, true)) {
    $required_filter = 'all';
}

$where_clauses = [];
$params = [];
$types_string = "";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = "%" . $_GET['search'] . "%";
    $where_clauses[] = "(d.title LIKE ? OR d.tracking_number LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types_string .= "ss";
}

if (isset($_GET['type']) && !empty($_GET['type'])) {
    $where_clauses[] = "d.type_id = ?";
    $params[] = $_GET['type'];
    $types_string .= "i";
}

if ($view === 'designated') {
    $where_clauses[] = "EXISTS (SELECT 1 FROM document_shares ds2 WHERE ds2.document_id = d.document_id AND ds2.recipient_id = ?)";
    // Action Required should only list actionable shared files.
    $where_clauses[] = "d.status_id = 1";
    $params[] = $user_id;
    $types_string .= "i";

    if ($required_filter === 'approval') {
        $where_clauses[] = "d.status_id = 1";
        $where_clauses[] = "EXISTS (SELECT 1 FROM document_remarks drf WHERE drf.document_id = d.document_id AND drf.remark LIKE 'Required Action:%Approval%' )";
    } elseif ($required_filter === 'review') {
        $where_clauses[] = "d.status_id = 1";
        $where_clauses[] = "EXISTS (SELECT 1 FROM document_remarks drf WHERE drf.document_id = d.document_id AND drf.remark LIKE 'Required Action:%Review%' )";
    } elseif ($required_filter === 'pending') {
        $where_clauses[] = "d.status_id = 1";
        $where_clauses[] = "NOT EXISTS (SELECT 1 FROM document_remarks drf WHERE drf.document_id = d.document_id AND (drf.remark LIKE 'Required Action:%Approval%' OR drf.remark LIKE 'Required Action:%Review%' OR drf.remark LIKE 'Required Action:%To-Do%'))";
    }
} else {
    $where_clauses[] = "d.status_id = 5";
    $where_clauses[] = "NOT EXISTS (SELECT 1 FROM document_shares ds WHERE ds.document_id = d.document_id)";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(" AND ", $where_clauses);
}

// Fetch All Documents (with filters)
$order_sql = ($view === 'designated')
    ? "ORDER BY CASE WHEN d.status_id = 1 THEN 0 ELSE 1 END, d.created_at ASC"
    : "ORDER BY d.created_at DESC";

$all_docs_query = "SELECT d.*, dt.type_name, s.status_name, u.username as created_by_user,
                   (SELECT file_path FROM attachments WHERE document_id = d.document_id LIMIT 1) as file_path,
                   (SELECT file_name FROM attachments WHERE document_id = d.document_id LIMIT 1) as file_name,
                   (SELECT COUNT(*) FROM document_activity_log dal WHERE dal.document_id = d.document_id AND dal.user_id = ? AND dal.activity_type = 'finished') as is_finished,
                   (SELECT remark FROM document_remarks dr WHERE dr.document_id = d.document_id AND dr.remark LIKE 'Required Action:%' ORDER BY dr.created_at DESC LIMIT 1) as required_action_remark
                   FROM documents d 
                   LEFT JOIN document_types dt ON d.type_id = dt.type_id 
                   LEFT JOIN document_status s ON d.status_id = s.status_id 
                   LEFT JOIN users u ON d.created_by = u.user_id 
                   $where_sql 
                   $order_sql";

$stmt = $conn->prepare($all_docs_query);
if ($stmt === false) {
    die('Database error: ' . $conn->error);
}
if (!empty($params)) {
    // Add user_id for the subquery param at the beginning
    array_unshift($params, $user_id);
    $types_string = "i" . $types_string;
    $stmt->bind_param($types_string, ...$params);
} else {
    // If no params, we still need to bind user_id
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$all_docs_result = $stmt->get_result();

// Store documents in an array for multiple view rendering
$documents_data = [];
if ($all_docs_result && $all_docs_result->num_rows > 0) {
    while ($doc = $all_docs_result->fetch_assoc()) {
        $documents_data[] = $doc;
    }
}

// KPI counters for action-required queue
$action_stats = [
    'pending' => 0,
    'approval' => 0,
    'review' => 0,
];

if ($view === 'designated') {
    foreach ($documents_data as $doc) {
        $remark = strtolower((string)($doc['required_action_remark'] ?? ''));
        if (strpos($remark, 'approval') !== false) {
            $action_stats['approval']++;
        } elseif (strpos($remark, 'review') !== false) {
            $action_stats['review']++;
        } else {
            $action_stats['pending']++;
        }
    }
}

$action_pending_docs = [];
$action_submitted_docs = [];
if ($view === 'designated') {
    foreach ($documents_data as $doc) {
        if (isset($doc['is_finished']) && (int)$doc['is_finished'] > 0) {
            $action_submitted_docs[] = $doc;
        } else {
            $action_pending_docs[] = $doc;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Storage - DTS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>


    <?php
    include __DIR__ . '/../includes/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-topbar">
            <div class="breadcrumb">
                <a href="<?php echo htmlspecialchars($dashboardUrl ?? '../dashboards/staff_dashboard.php'); ?>">Home</a>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">Documents</span>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current"><?php echo ($view === 'designated') ? 'Action Required' : 'All Documents'; ?></span>
            </div>
            <div class="topbar-actions">
                <strong class="text-dark"><?php echo ($view === 'designated') ? 'Action Required' : 'All Documents'; ?></strong>
            </div>
        </div>

        <div class="content-scroll">
        <?php if ($view === 'designated'): ?>
        <div class="dashboard-hero mb-3">
            <div>
                <h1>Action Required</h1>
                <p>Files requiring your review, approval, or follow-up actions.</p>
            </div>
            <div class="dashboard-hero-meta">
                <span class="meta-label">Queue Summary</span>
                <strong><?php echo count($documents_data); ?> item(s)</strong>
            </div>
        </div>

        <div class="row g-2 mb-3 dashboard-kpi-row">
            <div class="col-6 col-md-4"><div class="stat-card p-3"><p class="mb-1 text-secondary">Pending</p><h4 class="mb-0 fw-bold"><?php echo $action_stats['pending']; ?></h4></div></div>
            <div class="col-6 col-md-4"><div class="stat-card p-3"><p class="mb-1 text-secondary">For Approval</p><h4 class="mb-0 fw-bold"><?php echo $action_stats['approval']; ?></h4></div></div>
            <div class="col-6 col-md-4"><div class="stat-card p-3"><p class="mb-1 text-secondary">For Review</p><h4 class="mb-0 fw-bold"><?php echo $action_stats['review']; ?></h4></div></div>
        </div>
        <?php endif; ?>

        <?php if ($view !== 'designated'): ?>
        <!-- Folder View -->
        <div class="row g-3 mb-5">
            <?php 
            if (count($types) > 0) {
                foreach ($types as $type) {
            ?>
            <div class="col-md-2 col-6">
                <a href="#" class="folder-card view-folder-btn" data-type-id="<?php echo $type['type_id']; ?>" data-type-name="<?php echo htmlspecialchars($type['type_name']); ?>">
                    <div class="folder-icon"><i class="bi bi-folder-fill"></i></div>
                    <h6 class="fw-bold mb-0 text-truncate"><?php echo htmlspecialchars($type['type_name']); ?></h6>
                    <small class="text-secondary"><?php echo $type['file_count']; ?> files</small>
                </a>
            </div>
            <?php 
                }
            } else {
                echo '<div class="col-12"><p class="text-center text-secondary">No document types found.</p></div>';
            }
            ?>
        </div>
        <?php endif; ?>



        <!-- Filter Bar -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" action="" class="row g-3 align-items-center">
                    <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
                    <div class="<?php echo ($view === 'designated') ? 'col-md-4' : 'col-md-6'; ?>">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" onchange="this.form.submit()">
                        </div>
                    </div>
                    <div class="<?php echo ($view === 'designated') ? 'col-md-2' : 'col-md-3'; ?>">
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>" <?php echo (isset($_GET['type']) && $_GET['type'] == $type['type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($view === 'designated'): ?>
                    <div class="col-md-2">
                        <select name="required" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo ($required_filter === 'all') ? 'selected' : ''; ?>>All Actions</option>
                            <option value="approval" <?php echo ($required_filter === 'approval') ? 'selected' : ''; ?>>For Approval</option>
                            <option value="review" <?php echo ($required_filter === 'review') ? 'selected' : ''; ?>>For Review</option>
                            <option value="pending" <?php echo ($required_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="<?php echo ($view === 'designated') ? 'col-md-4' : 'col-md-3'; ?>">
                        <div class="d-flex justify-content-end align-items-center gap-2">
                            <?php if ($view !== 'designated'): ?>
                            <button type="button" class="btn btn-upload-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#uploadFromAllDocsModal">
                                <i class="bi bi-plus-lg"></i>
                                <span class="fw-semibold">Upload New Document</span>
                            </button>
                            <?php endif; ?>
                            <div class="btn-group" role="group" aria-label="View Toggle">
                                <button type="button" class="btn btn-outline-secondary active" id="listViewBtn" onclick="toggleView('list')" title="List View">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="gridViewBtn" onclick="toggleView('grid')" title="Grid View">
                                    <i class="bi bi-grid-fill"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Documents Table (List View) -->
        <div id="listViewContainer" class="document-table mb-4">
            <?php if ($view === 'designated'): ?>
                <?php
                $action_groups = [
                    [
                        'label' => 'Pending',
                        'icon' => 'bi-clock',
                        'color' => 'text-secondary',
                        'bg' => 'bg-secondary-subtle',
                        'docs' => $action_pending_docs,
                    ],
                    [
                        'label' => 'In Review',
                        'icon' => 'bi-hourglass-split',
                        'color' => 'text-info',
                        'bg' => 'bg-info-subtle',
                        'docs' => $action_submitted_docs,
                    ],
                ];

                $has_action_docs = false;
                foreach ($action_groups as $action_group) {
                    if (!empty($action_group['docs'])) {
                        $has_action_docs = true;
                        break;
                    }
                }
                ?>

                <?php if (!$has_action_docs): ?>
                    <div class="text-center py-5 text-secondary">
                        <i class="bi bi-folder2-open display-4 d-block mb-3 opacity-25"></i>
                        <p class="mb-0 fs-5">No documents found matching your filters.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($action_groups as $action_group): ?>
                        <?php if (empty($action_group['docs'])) continue; ?>
                        <div class="status-group-list mb-4">
                            <div class="d-flex align-items-center mb-3 px-1">
                                <h6 class="mb-0 fw-bold <?php echo $action_group['color']; ?> text-uppercase small ls-1">
                                    <i class="bi <?php echo $action_group['icon']; ?> me-2"></i><?php echo $action_group['label']; ?>
                                </h6>
                                <span class="badge <?php echo $action_group['bg']; ?> <?php echo $action_group['color']; ?> ms-2 rounded-pill"><?php echo count($action_group['docs']); ?></span>
                            </div>

                            <div class="card border-0 shadow-sm overflow-hidden rounded-4">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">
                                        <thead style="background-color: skyblue !important;" class="text-dark">
                                            <tr>
                                                <th class="ps-4 py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 18%;">Tracking No.</th>
                                                <th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 42%;">Document</th>
                                                <th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 18%;">Action Required</th>
                                                <th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 14%;">Date</th>
                                                <th class="pe-4 py-3 text-end text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 8%;">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($action_group['docs'] as $doc): ?>
                                                <?php
                                                $file_ext = strtolower(pathinfo((string)($doc['file_name'] ?? ''), PATHINFO_EXTENSION));
                                                $icon_class = 'bi-file-earmark';
                                                $icon_color = 'text-secondary';

                                                switch ($file_ext) {
                                                    case 'pdf': $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; break;
                                                    case 'doc':
                                                    case 'docx': $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; break;
                                                    case 'xls':
                                                    case 'xlsx':
                                                    case 'csv': $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; break;
                                                    case 'ppt':
                                                    case 'pptx': $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; break;
                                                    case 'jpg':
                                                    case 'jpeg':
                                                    case 'png':
                                                    case 'gif': $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; break;
                                                    case 'zip':
                                                    case 'rar': $icon_class = 'bi-file-earmark-zip'; $icon_color = 'text-dark'; break;
                                                    default:
                                                        $type_lower = strtolower((string)($doc['type_name'] ?? ''));
                                                        if (strpos($type_lower, 'pdf') !== false) { $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; }
                                                        elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) { $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; }
                                                        elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'csv') !== false) { $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; }
                                                        elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'photo') !== false) { $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; }
                                                        elseif (strpos($type_lower, 'powerpoint') !== false || strpos($type_lower, 'ppt') !== false) { $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; }
                                                        break;
                                                }

                                                $status_badge = '<span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-medium border border-danger">No Action Set</span>';
                                                $required_action_label = trim((string)($doc['required_action_remark'] ?? ''));
                                                if ($required_action_label !== '' && stripos($required_action_label, 'Required Action:') === 0) {
                                                    $required_action_label = trim(substr($required_action_label, strlen('Required Action:')));
                                                }
                                                if ($required_action_label !== '') {
                                                    if (stripos($required_action_label, 'review') !== false) {
                                                        $required_action_label = 'For Review';
                                                    } elseif (stripos($required_action_label, 'approval') !== false) {
                                                        $required_action_label = 'For Approval';
                                                    } elseif (stripos($required_action_label, 'to-do') !== false || stripos($required_action_label, 'todo') !== false) {
                                                        $required_action_label = 'To-Do';
                                                    }
                                                    $status_badge = '<span class="badge bg-danger text-white rounded-pill px-3 py-2 fw-medium border border-danger">' . htmlspecialchars($required_action_label, ENT_QUOTES, 'UTF-8') . '</span>';
                                                }

                                                $json_doc = json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT);
                                                ?>
                                                <tr class="align-middle position-relative hover-shadow transition-all" style="cursor: pointer; transition: all 0.2s ease;" onclick='openDocumentModal(<?php echo $json_doc; ?>)'>
                                                    <td class="ps-4 border-bottom-0">
                                                        <span class="font-monospace text-secondary fw-semibold"><?php echo htmlspecialchars($doc['tracking_number']); ?></span>
                                                    </td>
                                                    <td class="border-bottom-0">
                                                        <div class="d-flex align-items-center py-2">
                                                            <div class="rounded-3 bg-light d-flex align-items-center justify-content-center shadow-sm border" style="width: 48px; height: 48px; min-width: 48px;">
                                                                <i class="bi <?php echo $icon_class; ?> fs-4 <?php echo $icon_color; ?>"></i>
                                                            </div>
                                                            <div class="ms-3" style="min-width: 0;">
                                                                <div class="fw-bold text-dark text-truncate mb-1" style="max-width: 300px; font-size: 1rem;"><?php echo htmlspecialchars($doc['title']); ?></div>
                                                                <div class="small text-muted d-flex align-items-center">
                                                                    <i class="bi bi-file-earmark-text me-1"></i>
                                                                    <?php echo htmlspecialchars($doc['type_name']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="border-bottom-0"><?php echo $status_badge; ?></td>
                                                    <td class="text-secondary fw-medium border-bottom-0"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                                    <td class="pe-4 text-end border-bottom-0" onclick="event.stopPropagation()">
                                                        <div class="d-flex justify-content-end gap-2">
                                                            <button class="btn btn-light btn-sm text-primary rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" title="View Details" onclick='event.stopPropagation(); openDocumentModal(<?php echo $json_doc; ?>)'>
                                                                <i class="bi bi-eye"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Tracking Number</th>
                                <th class="py-3">Title</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Status</th>
                                <th class="py-3">Sent By</th>
                                <th class="py-3">Age</th>
                                <th class="py-3">Date</th>
                                <th class="py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (count($documents_data) > 0) {
                                foreach ($documents_data as $doc) {
                                    $status_class = 'status-draft';
                                    switch($doc['status_id']) {
                                        case 2: $status_class = 'status-submitted'; break;
                                        case 3: $status_class = 'status-received'; break;
                                        case 4: $status_class = 'status-forwarded'; break;
                                        case 5: $status_class = 'status-approved'; break;
                                        case 6: $status_class = 'status-rejected'; break;
                                    }

                                    $status_display = '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($doc['status_name']) . '</span>';

                                    $icon_class = 'bi-file-earmark';
                                    $icon_color = 'text-secondary';
                                    $file_ext = '';
                                    if (!empty($doc['file_name'])) {
                                        $file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                    }

                                    if (!empty($file_ext)) {
                                        switch ($file_ext) {
                                            case 'pdf': $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; break;
                                            case 'doc':
                                            case 'docx': $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; break;
                                            case 'xls':
                                            case 'xlsx':
                                            case 'csv': $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; break;
                                            case 'ppt':
                                            case 'pptx': $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; break;
                                            case 'jpg':
                                            case 'jpeg':
                                            case 'png':
                                            case 'gif': $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; break;
                                            case 'zip':
                                            case 'rar': $icon_class = 'bi-file-earmark-zip'; $icon_color = 'text-dark'; break;
                                            case 'txt': $icon_class = 'bi-file-earmark-text'; $icon_color = 'text-secondary'; break;
                                        }
                                    } else {
                                        $type_lower = strtolower((string)$doc['type_name']);
                                        if (strpos($type_lower, 'pdf') !== false) {
                                            $icon_class = 'bi-file-earmark-pdf';
                                            $icon_color = 'text-danger';
                                        } elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) {
                                            $icon_class = 'bi-file-earmark-word';
                                            $icon_color = 'text-primary';
                                        } elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'spreadsheet') !== false) {
                                            $icon_class = 'bi-file-earmark-excel';
                                            $icon_color = 'text-success';
                                        } elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'jpg') !== false || strpos($type_lower, 'png') !== false) {
                                            $icon_class = 'bi-file-earmark-image';
                                            $icon_color = 'text-info';
                                        } elseif (strpos($type_lower, 'presentation') !== false || strpos($type_lower, 'ppt') !== false) {
                                            $icon_class = 'bi-file-earmark-ppt';
                                            $icon_color = 'text-warning';
                                        } elseif (strpos($type_lower, 'zip') !== false || strpos($type_lower, 'rar') !== false || strpos($type_lower, 'compressed') !== false) {
                                            $icon_class = 'bi-file-earmark-zip';
                                            $icon_color = 'text-dark';
                                        } elseif (strpos($type_lower, 'text') !== false || strpos($type_lower, 'txt') !== false) {
                                            $icon_class = 'bi-file-earmark-text';
                                            $icon_color = 'text-secondary';
                                        }
                                    }
                            ?>
                            <tr>
                                <td class="ps-4 fw-medium text-primary"><?php echo htmlspecialchars($doc['tracking_number']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="bi <?php echo $icon_class; ?> <?php echo $icon_color; ?> me-2 fs-5"></i>
                                        <span><?php echo htmlspecialchars($doc['title']); ?></span>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['type_name']); ?></span></td>
                                <td><?php echo $status_display; ?></td>
                                <td><?php echo htmlspecialchars($doc['created_by_user']); ?></td>
                                <td class="text-secondary"><?php echo max(0, (int)floor((time() - strtotime($doc['created_at'])) / 86400)); ?> day(s)</td>
                                <td class="text-secondary"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-light border" title="View Details" onclick='openDocumentModal(<?php echo json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center py-5 text-secondary">No documents found matching your filters.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Documents Grid (Grid View) -->
        <div id="gridViewContainer" class="row g-3 mb-4" style="display: none;">
            <?php 
            if (count($documents_data) > 0) {
                $grid_docs = ($view === 'designated') ? array_merge($action_pending_docs, $action_submitted_docs) : $documents_data;
                $submitted_grid_header_shown = false;

                if ($view === 'designated' && count($action_pending_docs) > 0) {
                    echo '<div class="col-12">'
                        . '<div class="d-flex align-items-center mb-3 pb-2 border-bottom">'
                        . '<i class="bi bi-clock text-secondary me-2 fs-4"></i>'
                        . '<h5 class="mb-0 fw-bold text-secondary">Pending</h5>'
                        . '<span class="badge bg-secondary-subtle text-secondary ms-2">' . count($action_pending_docs) . '</span>'
                        . '</div>'
                        . '</div>';
                }

                foreach ($grid_docs as $doc) {
                    if ($view === 'designated' && !$submitted_grid_header_shown && isset($doc['is_finished']) && (int)$doc['is_finished'] > 0) {
                        echo '<div class="col-12 mt-2">'
                            . '<div class="d-flex align-items-center mb-3 pb-2 border-bottom">'
                            . '<i class="bi bi-hourglass-split text-info me-2 fs-4"></i>'
                            . '<h5 class="mb-0 fw-bold text-info">In Review</h5>'
                            . '<span class="badge bg-secondary-subtle text-secondary ms-2">' . count($action_submitted_docs) . '</span>'
                            . '</div>'
                            . '</div>';
                        $submitted_grid_header_shown = true;
                    }

                    $status_class = 'status-draft';
                    switch($doc['status_id']) {
                        case 2: $status_class = 'status-submitted'; break;
                        case 3: $status_class = 'status-received'; break;
                        case 4: $status_class = 'status-forwarded'; break;
                        case 5: $status_class = 'status-approved'; break;
                        case 6: $status_class = 'status-rejected'; break;
                    }
                    
                    // Status Display Logic
                    $status_display = '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($doc['status_name']) . '</span>';

                    // Custom Logic for Designated View
                    if ($view === 'designated') {
                        if ($doc['status_id'] == 5) {
                            $status_display = '';
                        } else {
                            $action_text = 'No Action Set';
                            $action_bg = 'bg-danger text-white';

                            if (!empty($doc['required_action_remark'])) {
                                $remark = trim((string)$doc['required_action_remark']);
                                if (stripos($remark, 'Required Action:') === 0) {
                                    $remark = trim(substr($remark, strlen('Required Action:')));
                                }
                                if (stripos($remark, 'Review') !== false) {
                                    $action_text = 'For Review';
                                    $action_bg = 'bg-danger text-white';
                                } elseif (stripos($remark, 'Approval') !== false) {
                                    $action_text = 'For Approval';
                                    $action_bg = 'bg-danger text-white';
                                } elseif (stripos($remark, 'To-Do') !== false) {
                                    $action_text = 'To-Do';
                                    $action_bg = 'bg-danger text-white';
                                } elseif ($remark !== '') {
                                    $action_text = $remark;
                                }
                            }

                            $status_display = '<span class="status-badge ' . $action_bg . '">' . htmlspecialchars($action_text) . '</span>';
                        }
                    }

                    // Determine Icon based on File Extension (matching my_documents.php)
                    $icon_class = 'bi-file-earmark'; // Default
                    $icon_color = 'text-secondary'; // Default
                    
                    // Use file extension if available, otherwise fall back to type name or default
                    $file_ext = '';
                    if (!empty($doc['file_name'])) {
                        $file_ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    }
                    
                    if (!empty($file_ext)) {
                        switch ($file_ext) {
                            case 'pdf':
                                $icon_class = 'bi-file-earmark-pdf';
                                $icon_color = 'text-danger';
                                break;
                            case 'doc':
                            case 'docx':
                                $icon_class = 'bi-file-earmark-word';
                                $icon_color = 'text-primary';
                                break;
                            case 'xls':
                            case 'xlsx':
                            case 'csv':
                                $icon_class = 'bi-file-earmark-excel';
                                $icon_color = 'text-success';
                                break;
                            case 'ppt':
                            case 'pptx':
                                $icon_class = 'bi-file-earmark-ppt';
                                $icon_color = 'text-warning';
                                break;
                            case 'jpg':
                            case 'jpeg':
                            case 'png':
                            case 'gif':
                                $icon_class = 'bi-file-earmark-image';
                                $icon_color = 'text-info';
                                break;
                            case 'zip':
                            case 'rar':
                                $icon_class = 'bi-file-earmark-zip';
                                $icon_color = 'text-dark';
                                break;
                            case 'txt':
                                $icon_class = 'bi-file-earmark-text';
                                $icon_color = 'text-secondary';
                                break;
                        }
                    } else {
                        // Fallback to type name if no file extension
                        $type_lower = strtolower($doc['type_name']);
                        if (strpos($type_lower, 'pdf') !== false) {
                            $icon_class = 'bi-file-earmark-pdf';
                            $icon_color = 'text-danger';
                        } elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) {
                            $icon_class = 'bi-file-earmark-word';
                            $icon_color = 'text-primary';
                        } elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'spreadsheet') !== false) {
                            $icon_class = 'bi-file-earmark-excel';
                            $icon_color = 'text-success';
                        } elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'jpg') !== false || strpos($type_lower, 'png') !== false) {
                            $icon_class = 'bi-file-earmark-image';
                            $icon_color = 'text-info'; 
                        } elseif (strpos($type_lower, 'presentation') !== false || strpos($type_lower, 'ppt') !== false) {
                            $icon_class = 'bi-file-earmark-ppt';
                            $icon_color = 'text-warning';
                        } elseif (strpos($type_lower, 'zip') !== false || strpos($type_lower, 'rar') !== false || strpos($type_lower, 'compressed') !== false) {
                            $icon_class = 'bi-file-earmark-zip';
                            $icon_color = 'text-dark';
                        } elseif (strpos($type_lower, 'text') !== false || strpos($type_lower, 'txt') !== false) {
                            $icon_class = 'bi-file-earmark-text';
                            $icon_color = 'text-secondary';
                        }
                    }
            ?>
            <div class="<?php echo ($view === 'designated') ? 'col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2 grid-item' : 'col-md-4 col-lg-3'; ?>">
                <div class="card h-100 shadow-sm border-0 position-relative hover-shadow transition-all" style="cursor: pointer;" onclick='openDocumentModal(<?php echo json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                    <!-- Badges -->
                    <div class="position-absolute top-0 start-0 m-2">
                        <?php if ($view === 'designated'): ?>
                        <input type="checkbox" class="form-check-input select-checkbox-grid" onclick="event.stopPropagation()">
                        <?php else: ?>
                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['type_name']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="position-absolute top-0 end-0 m-2">
                        <?php echo $status_display; ?>
                    </div>

                    <div class="card-body text-center <?php echo ($view === 'designated') ? 'p-2' : 'pt-5 pb-4'; ?>">
                        <!-- Big Icon -->
                        <div class="<?php echo ($view === 'designated') ? 'mt-1 mb-1' : 'mb-3 mt-2'; ?>">
                            <i class="bi <?php echo $icon_class; ?> <?php echo ($view === 'designated') ? 'display-4' : 'display-1'; ?> <?php echo $icon_color; ?>"></i>
                        </div>
                        
                        <!-- Title -->
                        <h6 class="card-title fw-bold text-truncate <?php echo ($view === 'designated') ? 'mb-1' : 'px-2 mb-3'; ?>" title="<?php echo htmlspecialchars($doc['title']); ?>">
                            <?php echo htmlspecialchars($doc['title']); ?>
                        </h6>

                        <small class="text-secondary d-block <?php echo ($view === 'designated') ? 'mb-2' : 'mb-2'; ?>"><?php echo htmlspecialchars($doc['tracking_number']); ?></small>
                        
                        <!-- Meta Data -->
                        <div class="text-secondary small <?php echo ($view === 'designated') ? 'd-none' : ''; ?>">
                            <div class="d-flex justify-content-center align-items-center mb-1">
                                <i class="bi bi-person me-2"></i>
                                <span>Sent By: <?php echo htmlspecialchars($doc['created_by_user']); ?></span>
                            </div>
                            <div class="d-flex justify-content-center align-items-center">
                                <i class="bi bi-calendar3 me-2"></i>
                                <span><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></span>
                            </div>
                        </div>

                        <?php if ($view === 'designated'): ?>
                        <div class="d-flex justify-content-center gap-1 mt-2">
                            <a href="../docs_tracking/download_file.php?id=<?php echo urlencode((string)$doc['document_id']); ?>" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation()" title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); openDocumentModal(<?php echo json_encode($doc, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)" title="View Details">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php 
                }
            } else {
                echo '<div class="col-12"><p class="text-center text-secondary py-5">No documents found matching your filters.</p></div>';
            }
            ?>
        </div>

        <!-- Document Details Modal -->
        <div class="modal" id="documentDetailsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg overflow-hidden">
                    <div class="modal-header bg-primary text-white py-3 px-4">
                        <h5 class="modal-title fw-bold" id="docModalTitle">
                            <i class="bi bi-file-earmark-text me-2"></i>Document Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div class="row g-0">
                            <!-- Left Sidebar: Visuals & Actions -->
                            <div class="col-md-4 bg-light p-4 text-center border-end d-flex flex-column justify-content-center">
                                <div id="docModalIcon" class="mb-4">
                                    <!-- Icon injected here -->
                                </div>
                                
                                <div class="text-secondary small fw-bold text-uppercase mb-2" id="docModalStatusLabel">Status</div>
                                <div id="docModalStatus" class="mb-4">
                                    <!-- Status badge injected here -->
                                </div>
                                
                                <div class="d-grid gap-2 w-100 mt-2">
                                    <button type="button" id="docModalHistoryBtn" class="btn btn-outline-info btn-sm shadow-sm px-3 d-none" data-bs-toggle="modal" data-bs-target="#fileHistoryModal">
                                        <i class="bi bi-clock-history me-1"></i>File History Log
                                    </button>
                                    <button type="button" id="docModalUploadBtn" class="btn btn-outline-primary btn-sm shadow-sm px-3 d-none" data-bs-toggle="modal" data-bs-target="#uploadUpdatedFileModal">
                                        <i class="bi bi-cloud-upload me-1"></i>Upload Updated File
                                    </button>
                                </div>
                            </div>

                            <!-- Right Content: Details -->
                            <div class="col-md-8 p-4">
                                <h6 class="text-secondary text-uppercase small fw-bold mb-1">Document Title</h6>
                                <h4 id="docModalName" class="fw-bold text-dark mb-4 text-break">Document Title</h4>

                                <div class="mb-4">
                                    <label class="text-secondary text-uppercase small fw-bold mb-2">
                                        <i class="bi bi-card-text me-1"></i>Description / Remarks
                                    </label>
                                    <div class="bg-light-subtle p-3 rounded border border-secondary-subtle">
                                        <p id="docModalDescription" class="text-dark mb-0" style="white-space: pre-wrap; font-size: 1rem; line-height: 1.6;">No description available.</p>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1">Tracking Number</label>
                                            <p id="docModalTracking" class="font-monospace fs-5 fw-medium text-primary mb-0 text-break">TRACK-000</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1">Type</label>
                                            <p id="docModalType" class="fs-5 fw-medium mb-0 text-dark">Report</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1">Date Created</label>
                                            <p id="docModalDate" class="fs-5 text-dark mb-0">Jan 01, 2024</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1" id="docModalCreatedByLabel">Sent By</label>
                                            <div class="d-flex align-items-center mt-1">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <i class="bi bi-person-fill"></i>
                                                </div>
                                                <span id="docModalCreatedBy" class="text-dark fw-medium fs-5">Admin</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 d-none" id="docModalRecipientContainer">
                                        <div class="p-3 border rounded h-100 bg-light-subtle">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1"><i class="bi bi-send me-1"></i>Sent To</label>
                                            <div class="d-flex align-items-center mt-1">
                                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <i class="bi bi-person-check-fill"></i>
                                                </div>
                                                <span id="docModalRecipient" class="text-dark fw-medium fs-5">Recipient Name</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12 mt-3">
                                        <div class="d-flex justify-content-between align-items-center w-100">
                                            <div class="d-flex gap-2">
                                                <a href="#" id="docModalDownloadBtn" download class="btn btn-outline-secondary btn-sm shadow-sm bg-white text-dark px-3">
                                                    <i class="bi bi-download me-1"></i>Download
                                                </a>
                                                <button type="button" id="docModalShareBtn" class="btn btn-outline-primary btn-sm shadow-sm px-3 d-none" data-bs-toggle="modal" data-bs-target="#docShareModal">
                                                    <i class="bi bi-share me-1"></i>Share
                                                </button>
                                            </div>
                                            <button type="button" id="docModalFinishBtn" class="btn btn-success btn-sm shadow-sm px-3 d-none">
                                                <i class="bi bi-send me-1"></i>Submit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="docShareModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom-0 py-3 px-4">
                        <h5 class="modal-title fw-bold text-primary">
                            <i class="bi bi-share me-2"></i>Share Document
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="share_document_id" id="share_document_id">
                        <div class="modal-body px-4 pb-4 pt-3">
                            <?php if (!empty($share_users)): ?>
                                <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                                    <table class="table align-middle mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th style="width: 40px;"></th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($share_users as $su): ?>
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input class="form-check-input share-user-checkbox" type="checkbox" name="share_user_ids[]" value="<?php echo (int)$su['user_id']; ?>">
                                                        </div>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($su['full_name'] ?: $su['role'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($su['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars(ucfirst($su['role'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No other users are available to share with.</p>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer bg-light border-top-0 px-4 py-3">
                            <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="share_document" class="btn btn-primary px-4">
                                <i class="bi bi-send me-2"></i>Share
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Professional Modal -->
        <div class="modal fade" id="folderModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light border-bottom-0 py-3 px-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-white p-2 rounded shadow-sm me-3 text-warning">
                                <i class="bi bi-folder-fill fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-primary mb-0" id="folderModalTitle">Document Folder</h5>
                                <small class="text-secondary">Manage documents in this category</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-0">
                        <div id="modalLoader" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-secondary">Loading documents...</p>
                        </div>
                        <div class="table-responsive" id="modalContent" style="display: none;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th class="ps-4 py-3">Tracking Number</th>
                                        <th class="py-3">Title</th>
                                        <th class="py-3">Status</th>
                                        <th class="py-3">Created By</th>
                                        <th class="py-3">Date</th>
                                        <th class="pe-4 py-3 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="documentsTableBody">
                                    <!-- Content will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 px-4 py-3">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- File History Modal -->
        <div class="modal fade" id="fileHistoryModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 1rem; overflow: hidden;">
                    <!-- Modern Header -->
                    <div class="modal-header border-bottom-0 py-4 px-4 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary-subtle text-primary p-3 rounded-circle me-3">
                                <i class="bi bi-clock-history fs-4"></i>
                            </div>
                            <div>
                                <h5 class="modal-title fw-bold text-dark mb-0">Version History</h5>
                                <p class="text-secondary small mb-0">Track changes and previous versions of this file.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-0 bg-light">
                        <div id="historyLoader" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-secondary fw-medium">Loading history...</p>
                        </div>
                        
                        <div id="historyContent" style="display: none;">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="bg-white border-bottom">
                                         <tr>
                                             <th class="ps-4 py-3 text-uppercase text-secondary text-xs font-weight-bolder opacity-7" style="font-size: 0.75rem; letter-spacing: 0.5px;">File</th>
                                             <th class="py-3 text-uppercase text-secondary text-xs font-weight-bolder opacity-7" style="font-size: 0.75rem; letter-spacing: 0.5px;">Date Modified</th>
                                             <th class="py-3 text-uppercase text-secondary text-xs font-weight-bolder opacity-7" style="font-size: 0.75rem; letter-spacing: 0.5px;">Status</th>
                                             <th class="py-3 text-uppercase text-secondary text-xs font-weight-bolder opacity-7" style="font-size: 0.75rem; letter-spacing: 0.5px;">Remarks</th>
                                             <th class="pe-4 py-3 text-end text-uppercase text-secondary text-xs font-weight-bolder opacity-7" style="font-size: 0.75rem; letter-spacing: 0.5px;">Action</th>
                                         </tr>
                                     </thead>
                                    <tbody id="fileHistoryBody" class="bg-white">
                                        <!-- Content loaded via JS -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div id="historyError" class="alert alert-danger m-4 border-0 shadow-sm" style="display: none;">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-circle-fill fs-4 me-3"></i>
                                <div>
                                    <h6 class="alert-heading fw-bold mb-1">Error Loading History</h6>
                                    <p class="mb-0 small">Unable to retrieve file version history. Please try again later.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-top-0 px-4 py-3 bg-white">
                        <button type="button" class="btn btn-light px-4 fw-medium text-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="uploadUpdatedFileModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-cloud-upload me-2"></i>Upload Updated File
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="modal-body p-4">
                            <?php if (!empty($upload_error)): ?>
                                <div class="alert alert-danger"><?php echo htmlspecialchars($upload_error); ?></div>
                            <?php elseif (!empty($upload_success)): ?>
                                <div class="alert alert-success"><?php echo htmlspecialchars($upload_success); ?></div>
                            <?php endif; ?>
                            <input type="hidden" name="upload_updated_file" value="1">
                            <input type="hidden" name="update_document_id" id="update_document_id">
                            <input type="hidden" name="preserve_original_filename" id="preserve_original_filename" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Select New File</label>
                                <input type="file" class="form-control" name="updated_file" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    The original filename will be preserved automatically.
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Remarks / Reason for Update</label>
                                <textarea class="form-control" name="update_remarks" rows="3" placeholder="Enter reason for update..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="upload_updated_file" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i>Upload Update
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="uploadFromAllDocsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered upload-flow-dialog">
                <div class="modal-content upload-flow-modal">
                    <div class="modal-header upload-modal-header">
                        <div class="upload-header-content">
                            <h5 class="modal-title upload-flow-title">Upload New Document</h5>
                            <p class="upload-flow-subtitle mb-0">Add a new document without leaving this page.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="../documents/my_documents.php" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" name="share_scope" value="all">

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Document File(s)</label>
                                <input type="file" name="document_file[]" class="form-control" required multiple>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Document Type</label>
                                <select name="type_id" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <?php foreach ($types as $type): ?>
                                    <option value="<?php echo (int)$type['type_id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-1">
                                <label class="form-label fw-semibold">Remarks</label>
                                <textarea name="description" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_document" class="btn btn-upload-primary d-flex align-items-center gap-2">
                                <i class="bi bi-cloud-upload"></i>
                                <span class="fw-semibold">Upload Document</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Submit Remarks Modal -->
        <div class="modal fade" id="submitRemarksModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-send-check me-2"></i>Submit Document
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body p-4">
                            <input type="hidden" name="finish_document" value="1">
                            <input type="hidden" name="finish_document_id" id="submit_document_id">
                            
                            <p class="text-secondary mb-3">You are about to submit this document back to the sender. Please provide any remarks or notes regarding your action.</p>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Remarks</label>
                                <textarea class="form-control" name="finish_remarks" rows="4" placeholder="Enter your remarks here..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-send me-1"></i>Submit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        </div><!-- /content-scroll -->
    </main>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const isDesignatedView = <?php echo ($view === 'designated') ? 'true' : 'false'; ?>;

            // View Toggle Logic
            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const listViewContainer = document.getElementById('listViewContainer');
            const gridViewContainer = document.getElementById('gridViewContainer');

            // Document Modal Logic
            const documentDetailsModal = new bootstrap.Modal(document.getElementById('documentDetailsModal'));
            const docModalShareBtn = document.getElementById('docModalShareBtn');
            const shareDocumentIdInput = document.getElementById('share_document_id');
            const docModalUploadBtn = document.getElementById('docModalUploadBtn');
            const docModalFinishBtn = document.getElementById('docModalFinishBtn');

            // Submit Modal Logic
            const submitRemarksModal = new bootstrap.Modal(document.getElementById('submitRemarksModal'));

            window.confirmFinish = function(id) {
                document.getElementById('submit_document_id').value = id;
                // Close details modal if open
                const detailsModalEl = document.getElementById('documentDetailsModal');
                const detailsModal = bootstrap.Modal.getInstance(detailsModalEl);
                if (detailsModal) {
                    detailsModal.hide();
                }
                submitRemarksModal.show();
            };
            const docModalHistoryBtn = document.getElementById('docModalHistoryBtn');
            const uploadUpdatedFileModal = document.getElementById('uploadUpdatedFileModal');
            
            // History Modal Logic
            const fileHistoryModal = document.getElementById('fileHistoryModal');
            let currentHistoryDocId = null;
            if (fileHistoryModal) {
                fileHistoryModal.addEventListener('show.bs.modal', function () {
                    if (!currentHistoryDocId) return;

                    const loader = document.getElementById('historyLoader');
                    const content = document.getElementById('historyContent');
                    const error = document.getElementById('historyError');
                    const historyBody = document.getElementById('fileHistoryBody');

                    if (loader) loader.style.display = 'block';
                    if (content) content.style.display = 'none';
                    if (error) error.style.display = 'none';
                    if (historyBody) historyBody.innerHTML = '';

                    fetch(`../docs_tracking/document_share_tracking.php?activity=1&document_id=${currentHistoryDocId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (loader) loader.style.display = 'none';
                            if (data.ok) {
                                if (content) content.style.display = 'block';
                                
                                if (data.file_history && data.file_history.length > 0) {
                                    let rows = '';
                                    data.file_history.forEach(item => {
                                        let statusBadge = item.status === 'Current' 
                                            ? '<span class="badge bg-success">Current</span>' 
                                            : '<span class="badge bg-secondary">Old</span>';
                                        
                                        let downloadBtn = item.file_path 
                                            ? `<a href="${item.file_path}" download class="btn btn-sm btn-outline-primary" title="Download" onclick="logDownload(${currentHistoryDocId}, 'Downloaded history file version')"><i class="bi bi-download"></i></a>` 
                                            : '<span class="text-muted">-</span>';

                                        rows += `
                                            <tr>
                                                <td>${item.file_name}</td>
                                                <td>${new Date(item.updated_at).toLocaleString()}</td>
                                                <td>${statusBadge}</td>
                                                <td>${item.remarks}</td>
                                                <td>${downloadBtn}</td>
                                            </tr>
                                        `;
                                    });
                                    if (historyBody) historyBody.innerHTML = rows;
                                } else {
                                    if (historyBody) historyBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No file history found.</td></tr>';
                                }
                            } else {
                                if (error) {
                                    error.textContent = data.error || 'Failed to load history.';
                                    error.style.display = 'block';
                                }
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            if (loader) loader.style.display = 'none';
                            if (error) {
                                error.textContent = 'An error occurred while loading history.';
                                error.style.display = 'block';
                            }
                        });
                });
            }

            if (uploadUpdatedFileModal) {
                uploadUpdatedFileModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    if (button) {
                        const id = button.getAttribute('data-id');
                        // Use existing global doc data if available or fetch
                        // In this context, we rely on the button attribute or current state
                        
                        // Try to find the document in documents_data to get the original filename
                        // Since documents_data is PHP-side, we might need to pass it via data attributes on the button
                        // But the button is in the modal which is static... wait.
                        // The button that triggers this is inside the "documentDetailsModal".
                        // So we need to set the filename when opening the details modal.
                        
                        const hiddenInput = document.getElementById('update_document_id');
                        if (hiddenInput && id) hiddenInput.value = id;
                        
                        // Set preservation flag if needed
                        const preserveInput = document.getElementById('preserve_original_filename');
                        if (preserveInput) preserveInput.value = '1';
                    }
                });
            }
            
            window.openDocumentModal = function(doc) {
                const finishBtn = document.getElementById('docModalFinishBtn');
                if (finishBtn) {
                    if (isDesignatedView) {
                        finishBtn.classList.remove('d-none');
                        finishBtn.setAttribute('onclick', `confirmFinish(${doc.document_id})`);
                        // Hide if already finished
                        if (doc.is_finished > 0) {
                            finishBtn.classList.add('d-none');
                        }
                    } else {
                        finishBtn.classList.add('d-none');
                    }
                }

                // Log View (Triggered when modal opens)
                if (doc.document_id) {
                    fetch('../docs_tracking/log_activity.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'document_id=' + doc.document_id + '&activity_type=view&details=Previewed document'
                    }).catch(error => console.error('Error logging view:', error));
                }

                currentHistoryDocId = doc.document_id;
                // Populate Modal Fields
                document.getElementById('docModalName').textContent = doc.title;
                document.getElementById('docModalTracking').textContent = doc.tracking_number;
                document.getElementById('docModalType').textContent = doc.type_name;
                document.getElementById('docModalCreatedBy').textContent = doc.created_by_user;
                
                // Adjust Created By Label for Designated View
                document.getElementById('docModalCreatedByLabel').textContent = 'Sent By';
                
                // Format Date
                const date = new Date(doc.created_at);
                document.getElementById('docModalDate').textContent = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                
                // Description
                const desc = doc.description || 'No description available.';
                document.getElementById('docModalDescription').textContent = desc;

                // Show/Hide Recipient
                if (doc.recipient_full_name) {
                    document.getElementById('docModalRecipientContainer').classList.remove('d-none');
                    document.getElementById('docModalRecipient').textContent = doc.recipient_full_name;
                } else {
                    document.getElementById('docModalRecipientContainer').classList.add('d-none');
                }
                
                // Status / Action Required Badge
                const statusDiv = document.getElementById('docModalStatus');
                const statusLabelDiv = document.getElementById('docModalStatusLabel');
                let statusClass = 'status-draft';
                switch(parseInt(doc.status_id)) {
                    case 2: statusClass = 'status-submitted'; break;
                    case 3: statusClass = 'status-received'; break;
                    case 4: statusClass = 'status-forwarded'; break;
                    case 5: statusClass = 'status-approved'; break;
                    case 6: statusClass = 'status-rejected'; break;
                }
                
                if (isDesignatedView) {
                    if (statusLabelDiv) statusLabelDiv.textContent = 'Action Required';
                    let actionText = 'No Action Set';
                    const actionBg = 'bg-danger text-white';

                    if (doc.required_action_remark) {
                        const rawRemark = doc.required_action_remark.toString().trim();
                        const cleanedRemark = rawRemark.toLowerCase().startsWith('required action:')
                            ? rawRemark.substring('required action:'.length).trim()
                            : rawRemark;
                        const remark = cleanedRemark.toLowerCase();
                        if (remark.includes('review')) {
                            actionText = 'For Review';
                        } else if (remark.includes('approval')) {
                            actionText = 'For Approval';
                        } else if (remark.includes('to-do')) {
                            actionText = 'To-Do';
                        } else if (cleanedRemark) {
                            actionText = cleanedRemark;
                        }
                    }
                    statusDiv.innerHTML = `<span class="status-badge ${actionBg}">${actionText}</span>`;
                } else {
                    if (statusLabelDiv) statusLabelDiv.textContent = 'Status';
                    statusDiv.innerHTML = `<span class="status-badge ${statusClass}">${doc.status_name}</span>`;
                }
                
                // Icon Logic
                const iconDiv = document.getElementById('docModalIcon');
                let iconClass = 'bi-file-earmark'; // Default
                let iconColor = 'text-secondary'; // Default
                
                let fileExt = '';
                if (doc.file_name) {
                    fileExt = doc.file_name.split('.').pop().toLowerCase();
                }
                
                if (fileExt) {
                    switch (fileExt) {
                        case 'pdf':
                            iconClass = 'bi-file-earmark-pdf';
                            iconColor = 'text-danger';
                            break;
                        case 'doc':
                        case 'docx':
                            iconClass = 'bi-file-earmark-word';
                            iconColor = 'text-primary';
                            break;
                        case 'xls':
                        case 'xlsx':
                        case 'csv':
                            iconClass = 'bi-file-earmark-excel';
                            iconColor = 'text-success';
                            break;
                        case 'ppt':
                        case 'pptx':
                            iconClass = 'bi-file-earmark-ppt';
                            iconColor = 'text-warning';
                            break;
                        case 'jpg':
                        case 'jpeg':
                        case 'png':
                        case 'gif':
                            iconClass = 'bi-file-earmark-image';
                            iconColor = 'text-info';
                            break;
                        case 'zip':
                        case 'rar':
                            iconClass = 'bi-file-earmark-zip';
                            iconColor = 'text-dark';
                            break;
                        case 'txt':
                            iconClass = 'bi-file-earmark-text';
                            iconColor = 'text-secondary';
                            break;
                    }
                } else {
                    // Fallback to type name logic
                    const typeLower = doc.type_name.toLowerCase();
                    if (typeLower.includes('pdf')) {
                        iconClass = 'bi-file-earmark-pdf';
                        iconColor = 'text-danger';
                    } else if (typeLower.includes('word') || typeLower.includes('doc')) {
                        iconClass = 'bi-file-earmark-word';
                        iconColor = 'text-primary';
                    } else if (typeLower.includes('excel') || typeLower.includes('sheet') || typeLower.includes('spreadsheet')) {
                        iconClass = 'bi-file-earmark-excel';
                        iconColor = 'text-success';
                    } else if (typeLower.includes('image') || typeLower.includes('jpg') || typeLower.includes('png')) {
                        iconClass = 'bi-file-earmark-image';
                        iconColor = 'text-info'; 
                    } else if (typeLower.includes('presentation') || typeLower.includes('ppt')) {
                        iconClass = 'bi-file-earmark-ppt';
                        iconColor = 'text-warning';
                    } else if (typeLower.includes('zip') || typeLower.includes('rar') || typeLower.includes('compressed')) {
                        iconClass = 'bi-file-earmark-zip';
                        iconColor = 'text-dark';
                    } else if (typeLower.includes('text') || typeLower.includes('txt')) {
                        iconClass = 'bi-file-earmark-text';
                        iconColor = 'text-secondary';
                    }
                }
                iconDiv.innerHTML = `<i class="bi ${iconClass} display-1 ${iconColor}"></i>`;
                
                // Show recipient if available (Designated Files tab)
                if (doc.recipient_full_name) {
                    document.getElementById('docModalRecipientContainer').classList.remove('d-none');
                    document.getElementById('docModalRecipient').textContent = doc.recipient_full_name;
                } else {
                    document.getElementById('docModalRecipientContainer').classList.add('d-none');
                }

                // File Links
                const downloadBtn = document.getElementById('docModalDownloadBtn');
                
                if (doc.file_path) {
                    downloadBtn.href = '../docs_tracking/download_file.php?id=' + doc.document_id;
                    downloadBtn.classList.remove('disabled');
                    downloadBtn.removeAttribute('onclick');
                } else {
                    downloadBtn.href = '#';
                    downloadBtn.classList.add('disabled');
                    downloadBtn.removeAttribute('onclick');
                }

                if (shareDocumentIdInput) {
                    shareDocumentIdInput.value = doc.document_id;
                }
                if (docModalShareBtn) {
                    if (isDesignatedView) {
                        docModalShareBtn.classList.remove('d-none');
                    } else {
                        docModalShareBtn.classList.add('d-none');
                    }
                }
                if (docModalUploadBtn) {
                    if (isDesignatedView) {
                        docModalUploadBtn.classList.remove('d-none');
                        docModalUploadBtn.setAttribute('data-id', doc.document_id);
                    } else {
                        docModalUploadBtn.classList.add('d-none');
                        docModalUploadBtn.removeAttribute('data-id');
                    }
                }
                
                if (docModalHistoryBtn) {
                    if (isDesignatedView) {
                        docModalHistoryBtn.classList.remove('d-none');
                    } else {
                        docModalHistoryBtn.classList.add('d-none');
                    }
                }

                // Clear any residual backdrops and stuck modal states
                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';

                const detailsModalEl = document.getElementById('documentDetailsModal');
                if (detailsModalEl && detailsModalEl.parentElement !== document.body) {
                    document.body.appendChild(detailsModalEl);
                }

                document.querySelectorAll('.modal.show').forEach(modalEl => {
                    if (modalEl !== detailsModalEl) {
                        const existing = bootstrap.Modal.getInstance(modalEl);
                        if (existing) existing.hide();
                        modalEl.classList.remove('show');
                    }
                });

                var documentDetailsModalInstance = new bootstrap.Modal(detailsModalEl, {
                    backdrop: true,
                    keyboard: true
                });
                documentDetailsModalInstance.show();
        };



        // Check local storage for preference
            const currentView = isDesignatedView ? 'grid' : (localStorage.getItem('documentViewPreference') || 'list');
            applyView(currentView);

            window.toggleView = function(view) {
                applyView(view);
                localStorage.setItem('documentViewPreference', view);
            };

            function applyView(view) {
                if (view === 'grid') {
                    listViewContainer.style.display = 'none';
                    gridViewContainer.style.display = 'flex';
                    listViewBtn.classList.remove('active');
                    gridViewBtn.classList.add('active');
                } else {
                    listViewContainer.style.display = 'block';
                    gridViewContainer.style.display = 'none';
                    listViewBtn.classList.add('active');
                    gridViewBtn.classList.remove('active');
                }
            }

            // Modal Logic
            const folderModal = new bootstrap.Modal(document.getElementById('folderModal'));
            const modalTitle = document.getElementById('folderModalTitle');
            const modalLoader = document.getElementById('modalLoader');
            const modalContent = document.getElementById('modalContent');
            const documentsTableBody = document.getElementById('documentsTableBody');

            document.querySelectorAll('.view-folder-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const typeId = this.getAttribute('data-type-id');
                    const typeName = this.getAttribute('data-type-name');
                    
                    // Update Modal Title
                    modalTitle.textContent = typeName;
                    
                    // Show Loader, Hide Content
                    modalLoader.style.display = 'block';
                    modalContent.style.display = 'none';
                    
                    // Show Modal
                    folderModal.show();
                    
                    // Fetch Data
                    fetch('get_documents.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'type_id=' + typeId
                    })
                    .then(response => response.text())
                    .then(html => {
                        documentsTableBody.innerHTML = html;
                        modalLoader.style.display = 'none';
                        modalContent.style.display = 'block';
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        documentsTableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">Error loading documents. Please try again.</td></tr>';
                        modalLoader.style.display = 'none';
                        modalContent.style.display = 'block';
                    });
                });
            });
        });
    </script>
</body>
</html>
