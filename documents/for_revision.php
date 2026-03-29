<?php
session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header('Location: ../index.php');
    exit();
}

include dirname(__DIR__) . '/plugins/conn.php';

$user_id = (int)$_SESSION['user_id'];
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;

$types = [];
$types_result = $conn->query("SELECT type_id, type_name FROM document_types ORDER BY type_name ASC");
if ($types_result) {
    while ($row = $types_result->fetch_assoc()) {
        $types[] = $row;
    }
}

$where = [
    "d.status_id = 4",
    "EXISTS (SELECT 1 FROM document_shares sh WHERE sh.document_id = d.document_id AND sh.recipient_id = ?)"
];
$params = [$user_id];
$types_string = 'i';

if ($search !== '') {
    $where[] = "(d.title LIKE ? OR d.tracking_number LIKE ?)";
    $search_like = '%' . $search . '%';
    $params[] = $search_like;
    $params[] = $search_like;
    $types_string .= 'ss';
}

if ($type_filter > 0) {
    $where[] = "d.type_id = ?";
    $params[] = $type_filter;
    $types_string .= 'i';
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT d.document_id, d.tracking_number, d.title, d.created_at,
               d.type_id, dt.type_name, ds.status_name,
               CASE
                   WHEN d.created_by = {$user_id} THEN 'Own Document'
                   ELSE COALESCE(
                       (
                           SELECT CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name)
                           FROM document_shares sh
                           INNER JOIN users u ON u.user_id = sh.shared_by
                           WHERE sh.document_id = d.document_id
                             AND sh.recipient_id = {$user_id}
                           ORDER BY sh.created_at DESC
                           LIMIT 1
                       ),
                       '-'
                   )
               END AS sent_by_name,
               (SELECT a.file_name FROM attachments a WHERE a.document_id = d.document_id LIMIT 1) AS file_name,
               (SELECT a.file_path FROM attachments a WHERE a.document_id = d.document_id LIMIT 1) AS file_path,
                             COALESCE(
                                     (
                                             SELECT NULLIF(TRIM(dal.details), '')
                                             FROM document_activity_log dal
                                             WHERE dal.document_id = d.document_id
                                                 AND dal.activity_type IN ('revision_requested', '')
                                                 AND (
                                                         dal.recipient_id = {$user_id}
                                                         OR dal.recipient_id IS NULL
                                                 )
                                                 AND dal.details NOT LIKE 'Shared document with all users%'
                                                 AND dal.details NOT LIKE 'Finished working on document%'
                                             ORDER BY dal.created_at DESC
                                             LIMIT 1
                                     ),
                                     (
                                             SELECT NULLIF(TRIM(dr.remark), '')
                                             FROM document_remarks dr
                                             WHERE dr.document_id = d.document_id
                                                 AND dr.remark NOT LIKE 'Required Action:%'
                                             ORDER BY dr.created_at DESC
                                             LIMIT 1
                                     ),
                                     'No revision reason provided.'
                             ) AS latest_remark
        FROM documents d
        LEFT JOIN document_types dt ON dt.type_id = d.type_id
        LEFT JOIN document_status ds ON ds.status_id = d.status_id
        $where_sql
    ORDER BY d.created_at DESC";

$documents = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    $bind_args = [$types_string];
    foreach ($params as $k => $value) {
        $bind_args[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
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
    <title>For Revision - DTS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        .revision-grid-card {
            border-radius: 12px;
            cursor: pointer;
            background: #ffffff;
            border: 1px solid #e5eaf2 !important;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.08) !important;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .revision-grid-card .card-body {
            height: 100%;
            overflow: hidden;
            position: relative;
            z-index: 1;
        }



        .revision-grid-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, 0.14) !important;
            border-color: #cbd5e1 !important;
        }

        .revision-status-pill {
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .revision-file-icon-wrap {
            width: 42px;
            height: 42px;
            min-width: 42px;
            border-radius: 10px;
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            border: 1px solid #dbe2ea;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .revision-meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #334155;
            border-radius: 999px;
            padding: 0.18rem 0.52rem;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .revision-remark-box {
            min-height: 62px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.52rem 0.72rem;
            font-size: 0.79rem;
            color: #475569;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .revision-date-row {
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 500;
        }

        .revision-open-btn {
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.82rem;
            padding-top: 0.44rem;
            padding-bottom: 0.44rem;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <div class="content-topbar">
            <div class="breadcrumb">
                <a href="<?php echo htmlspecialchars($dashboardUrl ?? '../dashboards/staff_dashboard.php'); ?>">Home</a>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">Documents</span>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">For Revision</span>
            </div>
            <div class="topbar-actions">
                <strong class="text-dark">For Revision</strong>
            </div>
        </div>

        <div class="content-scroll">
            <div class="dashboard-hero mb-3">
                <div>
                    <h1>For Revision</h1>
                    <p>Documents returned for updates and corrections.</p>
                </div>
                <div class="dashboard-hero-meta">
                    <span class="meta-label">Revision Queue</span>
                    <strong><?php echo count($documents); ?> item(s)</strong>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body p-3">
                    <form method="GET" action="" class="row g-3 align-items-center">
                        <div class="col-md-7">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Search by title or tracking number..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="type" class="form-select" onchange="this.form.submit()">
                                <option value="0">All Types</option>
                                <?php foreach ($types as $type): ?>
                                <option value="<?php echo (int)$type['type_id']; ?>" <?php echo ($type_filter === (int)$type['type_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="d-flex justify-content-end">
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

            <div id="listViewContainer" class="document-table mb-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3">Tracking Number</th>
                                <th class="py-3">Title</th>
                                <th class="py-3">Type</th>
                                <th class="py-3">Revision Needed / Reason *</th>
                                <th class="py-3 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">No revision files found.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($documents as $doc): ?>
                            <?php
                                $display_reason = trim((string)($doc['latest_remark'] ?? ''));
                                if ($display_reason === '') {
                                    $display_reason = 'No revision reason provided.';
                                }
                                $doc_payload = [
                                    'document_id' => (int)$doc['document_id'],
                                    'tracking_number' => (string)($doc['tracking_number'] ?? ''),
                                    'title' => (string)($doc['title'] ?? ''),
                                    'type_name' => (string)($doc['type_name'] ?? ''),
                                    'latest_remark' => $display_reason,
                                    'file_name' => (string)($doc['file_name'] ?? ''),
                                    'file_path' => (string)($doc['file_path'] ?? ''),
                                    'created_at' => (string)($doc['created_at'] ?? ''),
                                ];
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-primary"><?php echo htmlspecialchars($doc['tracking_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($doc['type_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                <td class="text-secondary"><?php echo htmlspecialchars($display_reason, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick='openRevisionDocModal(<?php echo htmlspecialchars(json_encode($doc_payload), ENT_QUOTES, "UTF-8"); ?>)'>
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

            <div id="gridViewContainer" class="row g-3 mb-4" style="display: none;">
                <?php if (empty($documents)): ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5 text-secondary">No revision files found.</div>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                <?php
                    $display_reason = trim((string)($doc['latest_remark'] ?? ''));
                    if ($display_reason === '') {
                        $display_reason = 'No revision reason provided.';
                    }
                    $file_name = (string)($doc['file_name'] ?? '');
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $icon_class = 'bi-file-earmark-text';
                    $icon_color = 'text-secondary';
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
                        case 'webp':
                            $icon_class = 'bi-file-earmark-image';
                            $icon_color = 'text-info';
                            break;
                        case 'zip':
                        case 'rar':
                        case '7z':
                            $icon_class = 'bi-file-earmark-zip';
                            $icon_color = 'text-dark';
                            break;
                    }
                    $doc_payload = [
                        'document_id' => (int)$doc['document_id'],
                        'tracking_number' => (string)($doc['tracking_number'] ?? ''),
                        'title' => (string)($doc['title'] ?? ''),
                        'type_name' => (string)($doc['type_name'] ?? ''),
                        'latest_remark' => $display_reason,
                        'file_name' => (string)($doc['file_name'] ?? ''),
                        'file_path' => (string)($doc['file_path'] ?? ''),
                        'created_at' => (string)($doc['created_at'] ?? ''),
                    ];
                ?>
                <div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2">
                    <div class="card h-100 border-0 shadow-sm position-relative revision-grid-card" onclick='openRevisionDocModal(<?php echo htmlspecialchars(json_encode($doc_payload), ENT_QUOTES, "UTF-8"); ?>)'>
                        <div class="card-body d-flex flex-column gap-2 p-3">
                            <div class="position-absolute top-0 end-0 m-3">
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2 py-1 revision-status-pill"><?php echo htmlspecialchars($doc['status_name'] ?? 'For Revision', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <div class="d-flex align-items-center gap-2 pt-1">
                                <div class="revision-file-icon-wrap">
                                    <i class="bi <?php echo htmlspecialchars($icon_class, ENT_QUOTES, 'UTF-8'); ?> fs-5 <?php echo htmlspecialchars($icon_color, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <div class="min-w-0" style="min-width: 0; padding-right: 92px;">
                                    <div class="text-primary fw-semibold small"><?php echo htmlspecialchars($doc['tracking_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <h6 class="mb-0 fw-bold" style="font-size: 0.95rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;" title="<?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                </div>
                            </div>

                            <div>
                                <span class="revision-meta-chip"><i class="bi bi-file-earmark-text"></i><?php echo htmlspecialchars($doc['type_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <div class="revision-remark-box">
                                <strong>Remark:</strong> <?php echo htmlspecialchars($display_reason, ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <div class="revision-date-row d-flex align-items-center gap-2">
                                <i class="bi bi-calendar-event"></i>
                                <span><?php echo date('M d, Y h:i A', strtotime($doc['created_at'])); ?></span>
                            </div>

                            <div class="mt-auto pt-1">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100 revision-open-btn" onclick='event.stopPropagation(); openRevisionDocModal(<?php echo htmlspecialchars(json_encode($doc_payload), ENT_QUOTES, "UTF-8"); ?>)'>
                                    <i class="bi bi-eye me-1"></i>View Document
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div class="modal fade" id="revisionViewModal" tabindex="-1" aria-labelledby="revisionViewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">

                <!-- Header -->
                <div class="modal-header border-0 px-4 pt-4 pb-3 align-items-start" style="background: linear-gradient(135deg, #fff7ed 0%, #ffffff 100%); border-bottom: 1px solid #ffe4b5 !important;">
                    <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="d-flex align-items-center justify-content-center rounded-3 flex-shrink-0" style="width: 48px; height: 48px; background: linear-gradient(135deg, #f97316 0%, #fb923c 100%);">
                            <i class="bi bi-pencil-square text-white" style="font-size: 1.3rem;"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="revisionViewModalLabel" style="font-size: 1.1rem;">Revision Document</h5>
                            <div class="text-muted" style="font-size: 0.8rem;">This document has been returned for revision</div>
                        </div>
                    </div>
                    <span class="badge rounded-pill px-3 py-2 mx-3 flex-shrink-0" style="background: #fff3e0; color: #c2410c; font-size: 0.75rem; font-weight: 700; border: 1px solid #fed7aa; letter-spacing: 0.04em;">FOR REVISION</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Body -->
                <div class="modal-body px-4 py-4">

                    <!-- Info cards row -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-hash text-primary" style="font-size: 0.85rem;"></i>
                                    <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Tracking Number</span>
                                </div>
                                <div class="fw-bold text-primary" id="revision_modal_tracking" style="font-size: 0.9rem;">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-file-earmark-text text-secondary" style="font-size: 0.85rem;"></i>
                                    <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Document Type</span>
                                </div>
                                <div class="fw-semibold text-dark" id="revision_modal_type" style="font-size: 0.9rem;">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 rounded-3 h-100" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <i class="bi bi-calendar-event text-secondary" style="font-size: 0.85rem;"></i>
                                    <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Date Submitted</span>
                                </div>
                                <div class="fw-semibold text-dark" id="revision_modal_date" style="font-size: 0.9rem;">-</div>
                            </div>
                        </div>
                    </div>

                    <!-- Title -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-card-heading text-secondary" style="font-size: 0.85rem;"></i>
                            <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Document Title</span>
                        </div>
                        <div class="fw-bold text-dark" id="revision_modal_title" style="font-size: 1.05rem; line-height: 1.45;">-</div>
                    </div>

                    <!-- Revision reason -->
                    <div class="mb-4">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 0.85rem;"></i>
                            <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Revision Needed / Reason</span>
                        </div>
                        <div class="p-3 rounded-3" id="revision_modal_reason" style="background: #fffbeb; border: 1px solid #fde68a; color: #78350f; font-size: 0.88rem; line-height: 1.65; white-space: pre-wrap; min-height: 72px;">-</div>
                    </div>

                    <!-- Attached file -->
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-paperclip text-secondary" style="font-size: 0.85rem;"></i>
                            <span class="fw-bold text-uppercase" style="font-size: 0.67rem; letter-spacing: 0.05em; color: #64748b;">Attached File</span>
                        </div>
                        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                            <i class="bi bi-file-earmark fs-4 text-muted flex-shrink-0"></i>
                            <span class="fw-semibold text-dark" id="revision_modal_file" style="font-size: 0.88rem; word-break: break-all;">-</span>
                        </div>
                    </div>

                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 px-4 pb-4 pt-0 gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; padding: 9px 22px;">
                        <i class="bi bi-x-lg me-1"></i>Close
                    </button>
                    <a href="#" id="revision_modal_download" class="btn btn-outline-secondary" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; padding: 9px 22px;">
                        <i class="bi bi-download me-1"></i>Download
                    </a>
                    <a href="#" id="revision_modal_view_online" target="_blank" rel="noopener noreferrer" class="btn btn-primary" style="border-radius: 10px; font-weight: 600; font-size: 0.85rem; padding: 9px 22px;">
                        <i class="bi bi-eye me-1"></i>View Online
                    </a>
                </div>

            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleView(view) {
            var listContainer = document.getElementById('listViewContainer');
            var gridContainer = document.getElementById('gridViewContainer');
            var listBtn = document.getElementById('listViewBtn');
            var gridBtn = document.getElementById('gridViewBtn');

            if (!listContainer || !gridContainer || !listBtn || !gridBtn) {
                return;
            }

            if (view === 'grid') {
                listContainer.style.display = 'none';
                gridContainer.style.display = 'flex';
                listBtn.classList.remove('active');
                gridBtn.classList.add('active');
            } else {
                listContainer.style.display = 'block';
                gridContainer.style.display = 'none';
                gridBtn.classList.remove('active');
                listBtn.classList.add('active');
            }
        }

        function openRevisionDocModal(data) {
            if (!data) return;

            var trackingEl = document.getElementById('revision_modal_tracking');
            var titleEl = document.getElementById('revision_modal_title');
            var typeEl = document.getElementById('revision_modal_type');
            var reasonEl = document.getElementById('revision_modal_reason');
            var dateEl = document.getElementById('revision_modal_date');
            var fileEl = document.getElementById('revision_modal_file');
            var viewOnlineEl = document.getElementById('revision_modal_view_online');
            var downloadEl = document.getElementById('revision_modal_download');

            if (trackingEl) trackingEl.textContent = data.tracking_number || '-';
            if (titleEl) titleEl.textContent = data.title || '-';
            if (typeEl) typeEl.textContent = data.type_name || '-';
            if (reasonEl) reasonEl.textContent = data.latest_remark || '-';
            if (dateEl) dateEl.textContent = data.created_at ? new Date(data.created_at).toLocaleString() : '-';
            if (fileEl) fileEl.textContent = data.file_name || '-';

            if (viewOnlineEl) {
                if (data.file_path) {
                    viewOnlineEl.href = data.file_path;
                    viewOnlineEl.classList.remove('disabled');
                } else {
                    viewOnlineEl.href = '#';
                    viewOnlineEl.classList.add('disabled');
                }
            }

            if (downloadEl) {
                if (data.document_id) {
                    downloadEl.href = '../docs_tracking/download_file.php?id=' + encodeURIComponent(data.document_id);
                    downloadEl.classList.remove('disabled');
                } else {
                    downloadEl.href = '#';
                    downloadEl.classList.add('disabled');
                }
            }

            var modalEl = document.getElementById('revisionViewModal');
            if (!modalEl) return;
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    </script>
</body>
</html>
