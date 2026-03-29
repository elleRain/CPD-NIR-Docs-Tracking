<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit();
}

$role    = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];

include dirname(__DIR__) . '/plugins/conn.php';
include dirname(__DIR__) . '/notification/notification_helpers.php';

// Mark current page as seen (for sidebar badge reset)
$_SESSION['sidebar_seen']['received_files'] = date('Y-m-d H:i:s');

$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

// Fetch files shared TO the current user that are approved.
$sql = "SELECT d.document_id, d.title, d.tracking_number, d.description, d.status_id, d.created_at,
               dt.type_name,
               ds_status.status_name,
               a.file_name, a.file_path,
               sh.created_at AS shared_at,
               CONCAT(sender.first_name, ' ', sender.last_name) AS sender_full_name,
               sender.username AS sender_username,
               (SELECT remark FROM document_remarks
                WHERE document_id = d.document_id AND remark LIKE 'Required Action:%'
                ORDER BY created_at DESC LIMIT 1) AS required_action_remark,
               (SELECT COUNT(*) FROM document_activity_log dal
                WHERE dal.document_id = d.document_id
                  AND dal.user_id = sh.recipient_id
                  AND dal.activity_type = 'finished') AS i_finished,
               (SELECT created_at FROM document_remarks
                WHERE document_id = d.document_id AND remark LIKE 'Approval Remark:%'
                ORDER BY created_at DESC LIMIT 1) AS approved_at
        FROM documents d
        LEFT JOIN document_types dt       ON d.type_id   = dt.type_id
        LEFT JOIN document_status ds_status ON d.status_id = ds_status.status_id
        LEFT JOIN attachments a            ON d.document_id = a.document_id
        JOIN  document_shares sh           ON sh.document_id = d.document_id AND sh.recipient_id = ?
        LEFT JOIN users sender             ON sender.user_id = sh.shared_by
        WHERE d.status_id = 5";

    $params = [$user_id];
    $types  = 'i';

if ($search !== '') {
    $sql   .= " AND (d.title LIKE ? OR d.tracking_number LIKE ?)";
    $like   = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

$sql .= " ORDER BY sh.created_at DESC";

$documents = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Files - DTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<main class="main-content">
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="content-topbar">
        <div class="breadcrumb">
            <?php
            $roleConfig  = ['superadmin' => 'superadmin_dashboard.php', 'admin' => 'admin_dashboard.php', 'staff' => 'staff_dashboard.php'];
            $dashboardUrl = '../dashboards/' . ($roleConfig[$role] ?? 'staff_dashboard.php');
            ?>
            <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
            <span class="breadcrumb-sep">&rsaquo;</span>
            <span class="breadcrumb-current">Shared Files</span>
        </div>
    </div>

    <div class="content-scroll">
        <?php if (isset($_GET['msg']) && $_GET['msg'] !== ''): ?>
        <div class="alert alert-info alert-dismissible fade show mb-3" role="alert">
            <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars((string)$_GET['msg'], ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="dashboard-hero mb-3">
            <div>
                <h1>Shared Files</h1>
                <p>Documents shared with you that are finished or approved.</p>
            </div>
            <div class="dashboard-hero-meta">
                <span class="meta-label">Total</span>
                <strong><?php echo count($documents); ?> file(s)</strong>
            </div>
        </div>

        <!-- Search -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-3">
                <form method="GET" action="" class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 ps-0"
                                   placeholder="Search by title or tracking number..."
                                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if ($search !== ''): ?>
                        <a href="received_files.php" class="btn btn-outline-secondary ms-2">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Table -->
        <div class="document-table mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 py-3">Tracking #</th>
                            <th class="py-3">Title</th>
                            <th class="py-3">Type</th>
                            <th class="py-3">Shared By</th>
                            <th class="py-3">Required Action</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Date Shared</th>
                            <th class="py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($documents)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-secondary">
                                <i class="bi bi-folder2-open display-4 d-block mb-3 opacity-25"></i>
                                No finished or approved shared files found.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                        <?php
                            // Build required action badge text
                            $raw_action = trim((string)($doc['required_action_remark'] ?? ''));
                            $action_label = '';
                            if ($raw_action !== '') {
                                $action_label = preg_replace('/^required action:\s*/i', '', $raw_action);
                            }

                            // Status badge
                            $status_map = [
                                1 => ['label' => 'Pending',      'class' => 'bg-secondary'],
                                2 => ['label' => 'Submitted',    'class' => 'bg-primary'],
                                3 => ['label' => 'In Review',    'class' => 'bg-info text-dark'],
                                4 => ['label' => 'For Revision', 'class' => 'bg-warning text-dark'],
                                5 => ['label' => 'Approved',     'class' => 'bg-success'],
                                6 => ['label' => 'Rejected',     'class' => 'bg-danger'],
                            ];
                            $sid   = (int)($doc['status_id'] ?? 0);
                            $s_cfg = $status_map[$sid] ?? ['label' => htmlspecialchars((string)($doc['status_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'), 'class' => 'bg-secondary'];

                            $json_doc = json_encode([
                                'document_id'    => (int)$doc['document_id'],
                                'title'          => $doc['title'],
                                'tracking_number'=> $doc['tracking_number'],
                                'type_name'      => $doc['type_name'] ?? '',
                                'description'    => $doc['description'] ?? '',
                                'status_id'      => $doc['status_id'],
                                'status_name'    => $doc['status_name'] ?? '',
                                'file_path'      => $doc['file_path'] ?? '',
                                'file_name'      => $doc['file_name'] ?? '',
                                'sender_name'    => $doc['sender_full_name'] ?? ($doc['sender_username'] ?? ''),
                                'shared_at'      => $doc['shared_at'] ?? '',
                                'approved_at'    => $doc['approved_at'] ?? '',
                                'required_action'=> $action_label,
                                'i_finished'     => (int)($doc['i_finished'] ?? 0),
                            ]);
                        ?>
                        <tr>
                            <td class="ps-4 font-monospace text-primary fw-semibold">
                                <?php echo htmlspecialchars((string)$doc['tracking_number'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="fw-medium">
                                <?php echo htmlspecialchars((string)$doc['title'], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?php echo htmlspecialchars((string)($doc['type_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-secondary">
                                <?php echo htmlspecialchars((string)($doc['sender_full_name'] ?? $doc['sender_username'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <?php if ($action_label !== ''): ?>
                                <span class="badge bg-danger text-white">
                                    <?php echo htmlspecialchars($action_label, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $s_cfg['class']; ?>">
                                    <?php echo $s_cfg['label']; ?>
                                </span>
                            </td>
                            <td class="text-secondary">
                                <?php echo $doc['shared_at'] ? htmlspecialchars(date('M d, Y', strtotime($doc['shared_at'])), ENT_QUOTES, 'UTF-8') : '—'; ?>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        onclick='openViewModal(<?php echo htmlspecialchars($json_doc, ENT_QUOTES, 'UTF-8'); ?>)'>
                                    <i class="bi bi-eye me-1"></i>View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /content-scroll -->
</main>

<!-- View Document Modal -->
<div class="modal" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div>
                    <h5 class="modal-title fw-bold mb-0">
                        <i class="bi bi-file-earmark-text me-2"></i>Document Details
                    </h5>
                    <div id="view_modal_subtitle" class="small opacity-75 mt-1"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left Sidebar: Visuals & Actions -->
                    <div class="col-md-4 bg-light p-4 text-center border-end d-flex flex-column justify-content-between">
                        <div class="mt-3">
                            <div id="view_icon_container" class="mb-4 transform-scale-12">
                                <!-- Icon injected by JS -->
                            </div>
                            <div class="bg-white rounded-4 border shadow-sm p-3 text-start">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing:.5px;">Status</div>
                                    <i class="bi bi-info-circle text-secondary"></i>
                                </div>
                                <div id="view_status_container" class="mb-3"></div>
                                <div class="border-top pt-3">
                                    <div class="text-secondary small fw-bold text-uppercase mb-1" style="letter-spacing:.5px;">File</div>
                                    <div id="view_file_name" class="text-dark fw-semibold text-truncate"></div>
                                </div>
                                <div class="d-grid gap-2 mt-3">
                                    <a href="#" id="view_online_btn" target="_blank" rel="noopener noreferrer"
                                       class="btn btn-outline-info btn-sm shadow-sm bg-white w-100">
                                        <i class="bi bi-eye me-1"></i>View Online
                                    </a>
                                    <a href="#" id="view_download_btn" download
                                       class="btn btn-outline-secondary btn-sm shadow-sm bg-white text-dark w-100">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Content: Details -->
                    <div class="col-md-8 p-0">
                        <div class="bg-white p-4 border-bottom">
                            <label class="text-uppercase text-secondary small fw-bold mb-1">Document Title</label>
                            <h4 id="view_title" class="fw-bold text-dark mb-0 display-6" style="font-size:1.75rem;">Document Title</h4>
                        </div>
                        <div class="p-4">
                            <div class="mb-4">
                                <label class="text-secondary text-uppercase small fw-bold mb-2">
                                    <i class="bi bi-card-text me-1"></i>Description / Remarks
                                </label>
                                <div class="bg-light-subtle p-3 rounded border border-secondary-subtle">
                                    <p id="view_desc" class="text-dark mb-0" style="white-space:pre-wrap;font-size:1rem;line-height:1.6;">No description available.</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Tracking Number</label>
                                        <p id="view_tracking" class="font-monospace fw-semibold text-primary mb-0" style="font-size:0.95rem;">—</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Type</label>
                                        <p id="view_type" class="fw-semibold mb-0 text-dark" style="font-size:0.95rem;">—</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Date Shared</label>
                                        <p id="view_shared_date_main" class="text-dark mb-0 fw-semibold" style="font-size:0.95rem;">—</p>
                                        <p id="view_shared_time" class="text-secondary mb-0 small"></p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Shared By</label>
                                        <div class="d-flex align-items-center mt-1">
                                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm"
                                                 style="width:32px;height:32px;font-size:14px;" id="view_sender_avatar">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                            <span id="view_sender" class="text-dark fw-semibold" style="font-size:0.95rem;">—</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Approved / Finalized</label>
                                        <p id="view_approved_date_main" class="text-dark mb-0 fw-semibold" style="font-size:0.95rem;">—</p>
                                        <p id="view_approved_time" class="text-secondary mb-0 small"></p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-3 border rounded h-100">
                                        <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Required Action</label>
                                        <div id="view_required_action" class="mt-1">—</div>
                                    </div>
                                </div>
                            </div>
                            <!-- Duration badge -->
                            <div class="mt-3" id="view_duration_wrap" style="display:none!important;">
                                <span class="badge bg-light text-secondary border px-3 py-2" style="font-size:0.8rem;">
                                    <i class="bi bi-clock-history me-1"></i>
                                    <span id="view_duration_text"></span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openViewModal(data) {
    document.getElementById('view_title').textContent    = data.title || '—';
    document.getElementById('view_tracking').textContent = data.tracking_number || '—';
    document.getElementById('view_type').textContent     = data.type_name || '—';
    document.getElementById('view_desc').textContent     = data.description || 'No description provided.';

    const subtitleEl = document.getElementById('view_modal_subtitle');
    if (subtitleEl) {
        const tracking = data.tracking_number ? 'Tracking: ' + data.tracking_number : '';
        const type     = data.type_name ? 'Type: ' + data.type_name : '';
        subtitleEl.textContent = [tracking, type].filter(Boolean).join(' • ');
    }

    // File name
    const fileNameEl = document.getElementById('view_file_name');
    if (fileNameEl) fileNameEl.textContent = data.file_name || '—';

    // Sender avatar + name
    document.getElementById('view_sender').textContent = data.sender_name || '—';
    const av = document.getElementById('view_sender_avatar');
    if (av) {
        if (data.sender_name) {
            av.innerHTML = '';
            av.textContent = data.sender_name.charAt(0).toUpperCase();
        } else {
            av.innerHTML = '<i class="bi bi-person-fill"></i>';
        }
    }

    // Required action
    const raDiv = document.getElementById('view_required_action');
    if (raDiv) {
        raDiv.innerHTML = data.required_action
            ? `<span class="badge bg-danger text-white px-2 py-1">${data.required_action}</span>`
            : '<span class="text-secondary">—</span>';
    }

    // Status badge (matches my_documents.php status-badge classes)
    const statusDiv = document.getElementById('view_status_container');
    if (statusDiv) {
        let badgeClass = 'status-draft';
        switch (parseInt(data.status_id)) {
            case 2: badgeClass = 'status-submitted'; break;
            case 3: badgeClass = 'status-received';  break;
            case 4: badgeClass = 'status-forwarded'; break;
            case 5: badgeClass = 'status-approved';  break;
            case 6: badgeClass = 'status-rejected';  break;
        }
        statusDiv.innerHTML = `<span class="status-badge ${badgeClass}">${data.status_name || '—'}</span>`;
    }

    // File type icon
    const iconDiv = document.getElementById('view_icon_container');
    if (iconDiv) {
        let iconClass = 'bi-file-earmark';
        let iconColor = 'text-secondary';
        const fileExt = data.file_name ? data.file_name.split('.').pop().toLowerCase() : '';
        if (fileExt) {
            switch (fileExt) {
                case 'pdf':  iconClass = 'bi-file-earmark-pdf';   iconColor = 'text-danger';  break;
                case 'doc': case 'docx': iconClass = 'bi-file-earmark-word';  iconColor = 'text-primary'; break;
                case 'xls': case 'xlsx': case 'csv': iconClass = 'bi-file-earmark-excel'; iconColor = 'text-success'; break;
                case 'ppt': case 'pptx': iconClass = 'bi-file-earmark-ppt';   iconColor = 'text-warning'; break;
                case 'jpg': case 'jpeg': case 'png': case 'gif': iconClass = 'bi-file-earmark-image'; iconColor = 'text-info'; break;
                case 'zip': case 'rar': iconClass = 'bi-file-earmark-zip'; iconColor = 'text-dark'; break;
            }
        } else {
            const typeLower = (data.type_name || '').toLowerCase();
            if (typeLower.includes('pdf'))  { iconClass = 'bi-file-earmark-pdf';   iconColor = 'text-danger'; }
            else if (typeLower.includes('word') || typeLower.includes('doc')) { iconClass = 'bi-file-earmark-word'; iconColor = 'text-primary'; }
            else if (typeLower.includes('excel') || typeLower.includes('sheet') || typeLower.includes('csv')) { iconClass = 'bi-file-earmark-excel'; iconColor = 'text-success'; }
            else if (typeLower.includes('image') || typeLower.includes('photo')) { iconClass = 'bi-file-earmark-image'; iconColor = 'text-info'; }
            else if (typeLower.includes('powerpoint') || typeLower.includes('ppt')) { iconClass = 'bi-file-earmark-ppt'; iconColor = 'text-warning'; }
        }
        iconDiv.innerHTML = `<i class="bi ${iconClass} display-1 ${iconColor}"></i>`;
    }

    // Date formatting helper
    function fmtDatetime(raw) {
        if (!raw) return null;
        const d = new Date(raw.replace(' ', 'T'));
        if (isNaN(d.getTime())) return null;
        return {
            date: d.toLocaleDateString('en-US', {year:'numeric', month:'short', day:'numeric'}),
            time: d.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit', hour12:true}),
            ts:   d.getTime()
        };
    }

    const sharedFmt   = fmtDatetime(data.shared_at);
    const approvedFmt = fmtDatetime(data.approved_at);

    document.getElementById('view_shared_date_main').textContent   = sharedFmt   ? sharedFmt.date   : '—';
    document.getElementById('view_shared_time').textContent        = sharedFmt   ? sharedFmt.time   : '';
    document.getElementById('view_approved_date_main').textContent = approvedFmt ? approvedFmt.date : 'Pending approval';
    document.getElementById('view_approved_time').textContent      = approvedFmt ? approvedFmt.time : '';

    // Duration badge
    const durWrap = document.getElementById('view_duration_wrap');
    const durText = document.getElementById('view_duration_text');
    if (durWrap && durText && sharedFmt && approvedFmt) {
        const diffMs   = approvedFmt.ts - sharedFmt.ts;
        const diffDays = Math.floor(diffMs / 86400000);
        const diffHrs  = Math.floor((diffMs % 86400000) / 3600000);
        const diffMins = Math.floor((diffMs % 3600000) / 60000);
        let durStr = diffDays > 0 ? diffDays + ' day' + (diffDays > 1 ? 's' : '')
                   : diffHrs > 0  ? diffHrs  + ' hr'  + (diffHrs  > 1 ? 's' : '')
                   : diffMins > 0 ? diffMins + ' min' + (diffMins > 1 ? 's' : '')
                   : 'Less than a minute';
        durText.textContent = 'Time to approval: ' + durStr;
        durWrap.style.removeProperty('display');
    } else if (durWrap) {
        durWrap.style.setProperty('display', 'none', 'important');
    }

    // File buttons
    const downloadBtn   = document.getElementById('view_download_btn');
    const viewOnlineBtn = document.getElementById('view_online_btn');
    if (data.file_path) {
        downloadBtn.setAttribute('href', '../docs_tracking/download_file.php?id=' + encodeURIComponent(data.document_id));
        downloadBtn.classList.remove('disabled');
        viewOnlineBtn.setAttribute('href', data.file_path);
        viewOnlineBtn.classList.remove('disabled');
    } else {
        downloadBtn.setAttribute('href', '#');
        downloadBtn.classList.add('disabled');
        viewOnlineBtn.setAttribute('href', '#');
        viewOnlineBtn.classList.add('disabled');
    }

    // Log view activity
    if (data.document_id) {
        fetch('log_activity.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'document_id=' + data.document_id + '&activity_type=view'
        }).catch(() => {});
    }

    // Clear stale backdrop
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
    document.body.style.paddingRight = '';

    const viewModalEl = document.getElementById('viewModal');
    document.querySelectorAll('.modal.show').forEach(modalEl => {
        if (modalEl !== viewModalEl) {
            const existing = bootstrap.Modal.getInstance(modalEl);
            if (existing) existing.hide();
            modalEl.classList.remove('show');
        }
    });

    new bootstrap.Modal(viewModalEl, {backdrop: true, keyboard: true}).show();
}
</script>
</body>
</html>
