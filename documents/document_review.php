<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'])) {
    header('Location: ../index.php');
    exit();
}

$role = $_SESSION['role'];
include '../plugins/conn.php';
include '../notification/notification_helpers.php';

// Handle Review Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['document_id'])) {
    $doc_id = intval($_POST['document_id']);
    $action = $_POST['action'];
    $user_id = $_SESSION['user_id'];

    $new_status = 0;
    $action_text = '';

    switch ($action) {
        case 'approve':
            $new_status = 5; // Approved
            $action_text = 'Approved document';
            break;
        case 'reject':
            $new_status = 6; // Rejected
            $action_text = 'Rejected document';
            break;
        case 'revision':
            $new_status = 4; // For Revision
            $action_text = 'Requested revision for document';
            break;
    }

    if ($new_status > 0) {
        // Update Status
        $update_stmt = $conn->prepare('UPDATE documents SET status_id = ? WHERE document_id = ?');
        $update_stmt->bind_param('ii', $new_status, $doc_id);

        if ($update_stmt->execute()) {
            // Log Action
            $log_stmt = $conn->prepare('INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)');
            $log_stmt->bind_param('isi', $user_id, $action_text, $doc_id);
            $log_stmt->execute();

            // Insert Revision Remark if applicable
            if ($action === 'revision' && !empty($_POST['revision_remark'])) {
                $remark = trim($_POST['revision_remark']);
                $remark_stmt = $conn->prepare('INSERT INTO document_remarks (document_id, user_id, remark) VALUES (?, ?, ?)');
                $remark_stmt->bind_param('iis', $doc_id, $user_id, $remark);
                $remark_stmt->execute();
            }

            // Notify document owner
            $doc_owner = 0;
            $doc_title = '';
            $owner_stmt = $conn->prepare('SELECT created_by, title FROM documents WHERE document_id = ?');
            if ($owner_stmt) {
                $owner_stmt->bind_param('i', $doc_id);
                $owner_stmt->execute();
                $owner_stmt->bind_result($doc_owner, $doc_title);
                $owner_stmt->fetch();
                $owner_stmt->close();

                if ($doc_owner > 0 && $doc_owner !== $user_id) {
                    createNotification(
                        $conn,
                        $doc_owner,
                        $action_text,
                        "Your document '" . ($doc_title ?: 'Document') . "' has been " . strtolower(str_replace('document', '', $action_text)),
                        '../documents/document.php?view=all',
                        'info',
                        $user_id,
                        $doc_id
                    );
                }
            }

            header('Location: document_review.php?msg=' . urlencode($action_text . ' successfully'));
            exit();
        }
    }
}

// Filter Logic for In Review Documents (Status ID 3)
$where_clauses = ['d.status_id = 3'];
$params = [];
$types_string = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_clauses[] = '(d.title LIKE ? OR d.tracking_number LIKE ?)';
    $params[] = $search;
    $params[] = $search;
    $types_string .= 'ss';
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

// Fetch In Review Documents
$query = "SELECT d.*, dt.type_name, s.status_name, u.username as created_by_user,
          (SELECT file_path FROM attachments WHERE document_id = d.document_id LIMIT 1) as file_path,
          (SELECT file_name FROM attachments WHERE document_id = d.document_id LIMIT 1) as file_name
          FROM documents d
          LEFT JOIN document_types dt ON d.type_id = dt.type_id
          LEFT JOIN document_status s ON d.status_id = s.status_id
          LEFT JOIN users u ON d.created_by = u.user_id
          $where_sql
          ORDER BY d.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types_string, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$documents = [];
while ($doc = $result->fetch_assoc()) {
    $documents[] = $doc;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Review - DTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/custom.css" rel="stylesheet">
</head>
<body>

<?php
if ($role === 'superadmin') {
    include __DIR__ . '/../includes/sidebar.php';
    include __DIR__ . '/../includes/navbar.php';
} elseif ($role === 'admin') {
    include __DIR__ . '/../includes/sidebar.php';
    include __DIR__ . '/../includes/navbar.php';
} else {
    include __DIR__ . '/../includes/sidebar.php';
    include __DIR__ . '/../includes/navbar.php';
}
?>

<main class="main-content">
    <?php // navbar already included above ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by title or tracking number..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2 justify-content-end">
                        <div class="btn-group" role="group" aria-label="View Toggle">
                            <button type="button" class="btn btn-outline-secondary active" id="listViewBtn" onclick="toggleView('list')" title="List View"><i class="bi bi-list-ul"></i></button>
                            <button type="button" class="btn btn-outline-secondary" id="gridViewBtn" onclick="toggleView('grid')" title="Grid View"><i class="bi bi-grid-fill"></i></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="listViewContainer" class="document-table mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4 py-3">Tracking Number</th>
                        <th class="py-3">Title</th>
                        <th class="py-3">Type</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Created By</th>
                        <th class="py-3">Date</th>
                        <th class="py-3 text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-secondary">No documents found in this folder.</td></tr>
                    <?php else: ?>
                        <?php foreach ($documents as $doc):
                            $status_class = 'status-draft';
                            switch ($doc['status_id']) {
                                case 2: $status_class = 'status-submitted'; break;
                                case 3: $status_class = 'status-received'; break;
                                case 4: $status_class = 'status-forwarded'; break;
                                case 5: $status_class = 'status-approved'; break;
                                case 6: $status_class = 'status-rejected'; break;
                            }

                            $icon_class = 'bi-file-earmark';
                            $icon_color = 'text-secondary';
                            $file_ext = !empty($doc['file_name']) ? strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)) : '';

                            if ($file_ext) {
                                switch ($file_ext) {
                                    case 'pdf': $icon_class='bi-file-earmark-pdf'; $icon_color='text-danger'; break;
                                    case 'doc': case 'docx': $icon_class='bi-file-earmark-word'; $icon_color='text-primary'; break;
                                    case 'xls': case 'xlsx': case 'csv': $icon_class='bi-file-earmark-excel'; $icon_color='text-success'; break;
                                    case 'ppt': case 'pptx': $icon_class='bi-file-earmark-ppt'; $icon_color='text-warning'; break;
                                    case 'jpg': case 'jpeg': case 'png': case 'gif': $icon_class='bi-file-earmark-image'; $icon_color='text-info'; break;
                                    case 'zip': case 'rar': $icon_class='bi-file-earmark-zip'; $icon_color='text-dark'; break;
                                    case 'txt': $icon_class='bi-file-earmark-text'; $icon_color='text-secondary'; break;
                                }
                            } else {
                                $type_lower = strtolower($doc['type_name']);
                                if (strpos($type_lower, 'pdf') !== false) { $icon_class='bi-file-earmark-pdf'; $icon_color='text-danger'; }
                                elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) { $icon_class='bi-file-earmark-word'; $icon_color='text-primary'; }
                                elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'spreadsheet') !== false) { $icon_class='bi-file-earmark-excel'; $icon_color='text-success'; }
                                elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'jpg') !== false || strpos($type_lower, 'png') !== false) { $icon_class='bi-file-earmark-image'; $icon_color='text-info'; }
                                elseif (strpos($type_lower, 'presentation') !== false || strpos($type_lower, 'ppt') !== false) { $icon_class='bi-file-earmark-ppt'; $icon_color='text-warning'; }
                                elseif (strpos($type_lower, 'zip') !== false || strpos($type_lower, 'rar') !== false || strpos($type_lower, 'compressed') !== false) { $icon_class='bi-file-earmark-zip'; $icon_color='text-dark'; }
                                elseif (strpos($type_lower, 'text') !== false || strpos($type_lower, 'txt') !== false) { $icon_class='bi-file-earmark-text'; $icon_color='text-secondary'; }
                            }
                        ?>
                        <tr style="cursor:pointer;">
                            <td class="ps-4 fw-medium text-primary"><?php echo htmlspecialchars($doc['tracking_number']); ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <i class="bi <?php echo $icon_class; ?> <?php echo $icon_color; ?> me-2 fs-5"></i>
                                    <span><?php echo htmlspecialchars($doc['title']); ?></span>
                                </div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['type_name']); ?></span></td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($doc['status_name']); ?></span></td>
                            <td><?php echo htmlspecialchars($doc['created_by_user']); ?></td>
                            <td class="text-secondary"><?php echo date('M d, Y', strtotime($doc['created_at'])); ?></td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary" onclick="openReviewModal(<?php echo htmlspecialchars(json_encode($doc), ENT_QUOTES, 'UTF-8'); ?>)">Review</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleView(type){
    var listView = document.getElementById('listViewContainer');
    if(type === 'list'){
        listView.style.display = 'block';
    } else {
        listView.style.display = 'none';
    }
}
function openReviewModal(data){
    // existing modal logic here or keep as per old file context
    console.log('openReviewModal', data);
}
</script>
</body>
</html>

