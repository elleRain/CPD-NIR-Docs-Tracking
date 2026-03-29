<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
// Allow superadmin, admin, and staff
if (!in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'])) {
    header("Location: ../index.php");
    exit();
}

include '../plugins/conn.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get list of documents created by the user, ordered by most recent
$sql = "SELECT d.document_id, d.title, d.tracking_number, d.description, d.created_at, d.status_id, ds.status_name, 
               u.username as created_by_user,
               (SELECT COUNT(*) FROM document_activity_log WHERE document_id = d.document_id) as activity_count,
               (SELECT created_at FROM document_activity_log WHERE document_id = d.document_id ORDER BY created_at DESC LIMIT 1) as last_activity,
               (SELECT remark FROM document_remarks WHERE document_id = d.document_id AND remark LIKE 'Required Action:%' ORDER BY created_at DESC LIMIT 1) as required_action_remark
        FROM documents d
        LEFT JOIN document_status ds ON d.status_id = ds.status_id
        LEFT JOIN users u ON d.created_by = u.user_id
        WHERE d.created_by = ?
        ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$documents = [];

while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

$finish_documents = [];
$ongoing_documents = [];
foreach ($documents as $doc_row) {
    if ((int)($doc_row['status_id'] ?? 0) === 5) {
        $finish_documents[] = $doc_row;
    } else {
        $ongoing_documents[] = $doc_row;
    }
}

$status_options = [
    'ongoing' => 'Ongoing',
    'finish' => 'Finish',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Tracking Log - DTS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
    <style>
        .btn-track-history {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
        }

        .btn-track-history:hover,
        .btn-track-history:focus,
        .btn-track-history:active,
        .btn-track-history.active,
        .show > .btn-track-history.dropdown-toggle {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #fff !important;
            box-shadow: none !important;
        }
    </style>
</head>
<body>

    <?php 
    if ($role === 'superadmin') {
        include __DIR__ . '/../includes/sidebar.php';
    } elseif ($role === 'admin') {
        include __DIR__ . '/../includes/sidebar.php';
    } elseif ($role === 'staff') {
        include __DIR__ . '/../includes/sidebar.php';
    }
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php 
        if ($role === 'superadmin') {
            include __DIR__ . '/../includes/navbar.php';
        } elseif ($role === 'admin') {
            include __DIR__ . '/../includes/navbar.php';
        } elseif ($role === 'staff') {
            include __DIR__ . '/../includes/navbar.php';
        }

        $roleConfig = [
            'superadmin' => 'superadmin_dashboard.php',
            'admin' => 'admin_dashboard.php',
            'staff' => 'staff_dashboard.php',
        ];
        $dashboardUrl = '../dashboards/' . ($roleConfig[$role] ?? 'staff_dashboard.php');
        ?>

        <div class="content-topbar">
            <div class="breadcrumb">
                <a href="<?php echo htmlspecialchars($dashboardUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">Docs Tracking</span>
                <span class="breadcrumb-sep">&rsaquo;</span>
                <span class="breadcrumb-current">Shared Docs Log</span>
            </div>
            <div class="topbar-actions">
                <strong class="text-dark">Shared Docs Log</strong>
            </div>
        </div>

        <div class="content-scroll">
        <div class="card border-0 shadow-sm rounded-4 mb-3">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-lg-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-secondary"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="searchInput" placeholder="Search title, tracking, action...">
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <select id="statusFilter" class="form-select form-select-sm">
                            <option value="">All Status</option>
                            <?php foreach ($status_options as $status_value => $status_label): ?>
                                <option value="<?php echo htmlspecialchars($status_value, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <input type="date" id="dateFromFilter" class="form-control form-control-sm" title="From date">
                    </div>
                    <div class="col-lg-2">
                        <input type="date" id="dateToFilter" class="form-control form-control-sm" title="To date">
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-2 px-1">
            <small class="text-secondary" id="resultsSummary">Showing <?php echo count($documents); ?> document(s)</small>
            <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="clearFiltersBtn">Clear filters</button>
        </div>

        <?php
            function render_tracking_rows(array $rows, string $status_key): void {
                if (empty($rows)) {
                    echo '<tr class="section-empty-row"><td colspan="5" class="text-center text-muted py-3">No documents in this section.</td></tr>';
                    return;
                }

                foreach ($rows as $row) {
                    $raw_action = !empty($row['required_action_remark'])
                        ? preg_replace('/^Required Action:\s*/i', '', (string)$row['required_action_remark'])
                        : '';

                    $status_label = ($status_key === 'finish') ? 'Finish' : 'Ongoing';
                    $status_badge_class = ($status_key === 'finish') ? 'bg-success' : 'bg-warning text-dark';
                    $created_label = !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '—';
                    $created_value = !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : '';
                    $last_activity_label = !empty($row['last_activity']) ? date('M d, Y h:i A', strtotime($row['last_activity'])) : 'No activity yet';

                    $search_blob = strtolower(trim(implode(' ', [
                        (string)($row['title'] ?? ''),
                        (string)($row['tracking_number'] ?? ''),
                        (string)$status_label,
                        (string)$raw_action,
                        (string)($row['description'] ?? '')
                    ])));

                    echo '<tr data-doc-row="1" data-search="' . htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8') . '" data-status="' . htmlspecialchars($status_key, ENT_QUOTES, 'UTF-8') . '" data-created="' . htmlspecialchars($created_value, ENT_QUOTES, 'UTF-8') . '">';
                    echo '  <td class="ps-4">';
                    echo '      <div class="fw-bold text-dark text-truncate" style="max-width: 300px;" title="' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string)$row['title'], ENT_QUOTES, 'UTF-8') . '</div>';
                    echo '      <div class="small text-muted font-monospace">' . htmlspecialchars((string)$row['tracking_number'], ENT_QUOTES, 'UTF-8') . '</div>';
                    echo '      <div class="small text-secondary mt-1"><i class="bi bi-calendar3 me-1"></i>Created: ' . htmlspecialchars($created_label, ENT_QUOTES, 'UTF-8') . '</div>';
                    echo '  </td>';
                    echo '  <td><span class="badge ' . $status_badge_class . ' rounded-pill px-3 py-2">' . htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') . '</span></td>';
                    echo '  <td>';
                    if ($raw_action !== '') {
                        $action_badge = 'bg-secondary';
                        if ($raw_action === 'Review') $action_badge = 'bg-info text-dark';
                        elseif ($raw_action === 'Approval') $action_badge = 'bg-success';
                        elseif ($raw_action === 'To-Do') $action_badge = 'bg-warning text-dark';
                        echo '<span class="badge ' . $action_badge . ' rounded-pill px-3 py-2">' . htmlspecialchars($raw_action, ENT_QUOTES, 'UTF-8') . '</span>';
                    } else {
                        echo '<span class="text-muted small">-</span>';
                    }
                    echo '  </td>';
                    echo '  <td>';
                    echo '      <div class="small text-dark fw-semibold">' . htmlspecialchars($last_activity_label, ENT_QUOTES, 'UTF-8') . '</div>';
                    echo '      <div class="small text-muted"><i class="bi bi-activity me-1"></i>' . (int)($row['activity_count'] ?? 0) . ' total event(s)</div>';
                    echo '  </td>';
                    echo '  <td>';
                    echo '      <div class="d-flex gap-2">';
                    echo '          <button type="button" class="btn btn-track-history btn-sm shadow-sm" onclick="openActivityModal(' . (int)$row['document_id'] . ', \'' . addslashes((string)$row['title']) . '\', \'' . addslashes((string)$row['tracking_number']) . '\', \'' . addslashes((string)$row['created_by_user']) . '\', \'' . addslashes((string)$row['description']) . '\')"><i class="bi bi-clock-history me-1"></i> File History Log</button>';
                    echo '          <button type="button" class="btn btn-track-history btn-sm shadow-sm" onclick="openAuditModal(' . (int)$row['document_id'] . ', \'' . addslashes((string)$row['title']) . '\')"><i class="bi bi-journal-text me-1"></i> Activity Record</button>';
                    echo '      </div>';
                    echo '  </td>';
                    echo '</tr>';
                }
            }
        ?>

        <?php if (empty($documents)): ?>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                <div class="card-body text-center py-5 text-secondary">
                    <div class="mb-3">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-folder2-open display-4 text-secondary opacity-25"></i>
                        </div>
                    </div>
                    <h6 class="fw-bold text-dark">No documents found</h6>
                    <p class="small mb-0">Documents you create or share will appear here.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4" data-section="finish">
                <div class="card-header border-0 text-white d-flex justify-content-between align-items-center" style="background-color:#198754;">
                    <div class="fw-bold text-uppercase small"><i class="bi bi-check-circle-fill me-2"></i>Finish</div>
                    <span class="badge bg-light text-success"><?php echo count($finish_documents); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 doc-table">
                            <thead style="background-color: #e8f6ee;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase">Document Info</th>
                                    <th class="py-3 text-uppercase">Status</th>
                                    <th class="py-3 text-uppercase">Required Action</th>
                                    <th class="py-3 text-uppercase">Last Activity</th>
                                    <th class="py-3 text-uppercase">Tracking History</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php render_tracking_rows($finish_documents, 'finish'); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 overflow-hidden" data-section="ongoing">
                <div class="card-header border-0 text-dark d-flex justify-content-between align-items-center" style="background-color:#ffc107;">
                    <div class="fw-bold text-uppercase small"><i class="bi bi-hourglass-split me-2"></i>Ongoing</div>
                    <span class="badge bg-dark text-warning"><?php echo count($ongoing_documents); ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 doc-table">
                            <thead style="background-color: #fff7db;">
                                <tr>
                                    <th class="ps-4 py-3 text-uppercase">Document Info</th>
                                    <th class="py-3 text-uppercase">Status</th>
                                    <th class="py-3 text-uppercase">Required Action</th>
                                    <th class="py-3 text-uppercase">Last Activity</th>
                                    <th class="py-3 text-uppercase">Tracking History</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php render_tracking_rows($ongoing_documents, 'ongoing'); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- /content-scroll -->
    </main>

    <!-- Activity History Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg"> <!-- Large modal for timeline -->
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold" id="activityModalLabel">Activity History</h5>
                        <p class="text-secondary small mb-0" id="activityModalSubtitle">Loading...</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div id="modalLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-secondary small">Fetching history...</p>
                    </div>
                    
                    <div id="modalError" class="alert alert-danger d-none" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <span id="errorMsg">Failed to load data.</span>
                    </div>

                    <div id="timelineContainer" class="d-none">
                         <div class="d-flex justify-content-between align-items-center mb-3">
                             <div class="input-group input-group-sm" style="width: 250px;">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" id="activitySearch" class="form-control border-start-0" placeholder="Search activities...">
                             </div>
                             <div class="btn-group">
                                 <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportToCSV()"><i class="bi bi-download me-1"></i>Export</button>
                                 <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printActivityHistory()"><i class="bi bi-printer me-1"></i>Print</button>
                             </div>
                         </div>
                         
                         <div class="table-responsive border rounded-3">
                             <table class="table table-hover align-middle mb-0" id="activityTable">
                                 <thead class="bg-light">
                                     <tr>
                                         <th class="ps-4 py-3" style="width: 50%; cursor: pointer;" onclick="sortActivityTable(0)">Activity <i class="bi bi-arrow-down-up text-muted small ms-1"></i></th>
                                         <th class="py-3" style="width: 50%; cursor: pointer;" onclick="sortActivityTable(1)">Remarks <i class="bi bi-arrow-down-up text-muted small ms-1"></i></th>
                                     </tr>
                                 </thead>
                                 <tbody id="activityTableBody">
                                     <!-- Rows will be injected here -->
                                 </tbody>
                             </table>
                         </div>
                         <div class="d-flex justify-content-between align-items-center mt-3">
                             <small class="text-muted" id="activityCount">Showing 0 activities</small>
                             <nav aria-label="Activity pagination">
                                 <ul class="pagination pagination-sm mb-0" id="activityPagination">
                                     <!-- Pagination -->
                                 </ul>
                             </nav>
                         </div>
                    </div>
                    
                    <div id="emptyState" class="text-center py-5 d-none">
                        <i class="bi bi-clock-history display-1 text-light mb-3"></i>
                        <p class="text-secondary">No activity recorded for this document yet.</p>
                    </div>
                </div>
                <div class="modal-footer border-top-0 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-primary rounded-pill px-4 d-none" id="printHistoryBtn" onclick="printActivityHistory()">
                        <i class="bi bi-printer me-2"></i>Print History
                    </button>
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Audit Log Modal -->
    <div class="modal fade" id="auditModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header bg-white border-bottom pb-3">
                    <div>
                        <h5 class="modal-title fw-bold text-dark"><i class="bi bi-journal-richtext text-primary me-2"></i>Activity Audit Log</h5>
                        <p class="text-secondary small mb-0 mt-1" id="auditModalSubtitle">Loading...</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 bg-light">
                    <div id="auditLoading" class="text-center py-5">
                        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;"></div>
                        <p class="mt-3 text-secondary fw-medium">Retrieving audit trail...</p>
                    </div>
                    <div id="auditError" class="alert alert-danger m-4 d-none shadow-sm border-0">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-circle-fill fs-4 me-3"></i>
                            <div>
                                <h6 class="alert-heading fw-bold mb-1">Error Loading Data</h6>
                                <span id="auditErrorMsg"></span>
                            </div>
                        </div>
                    </div>
                    <div id="auditContainer" class="d-none">
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0 border-top-0">
                                <thead class="bg-white sticky-top shadow-sm">
                                    <tr>
                                        <th class="ps-4 py-3 text-uppercase text-secondary small fw-bold" style="width: 180px;">Timestamp</th>
                                        <th class="py-3 text-uppercase text-secondary small fw-bold" style="width: 250px;">User</th>
                                        <th class="py-3 text-uppercase text-secondary small fw-bold" style="width: 150px;">Action</th>
                                        <th class="py-3 text-uppercase text-secondary small fw-bold">Details</th>
                                    </tr>
                                </thead>
                                <tbody id="auditTableBody" class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top pt-3">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-track-history rounded-pill px-4" onclick="printAuditLog()">
                        <i class="bi bi-printer me-2"></i>Print Log
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Table filters (search + status)
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const dateFromFilter = document.getElementById('dateFromFilter');
        const dateToFilter = document.getElementById('dateToFilter');
        const clearFiltersBtn = document.getElementById('clearFiltersBtn');
        const resultsSummary = document.getElementById('resultsSummary');

        function applyDocFilters() {
            const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const status = (statusFilter ? statusFilter.value : '').toLowerCase().trim();
            const fromDate = dateFromFilter ? dateFromFilter.value : '';
            const toDate = dateToFilter ? dateToFilter.value : '';
            const rows = Array.from(document.querySelectorAll('tr[data-doc-row="1"]'));
            const sectionCards = Array.from(document.querySelectorAll('[data-section]'));
            let visibleCount = 0;

            rows.forEach(row => {
                const haystack = (row.getAttribute('data-search') || '').toLowerCase();
                const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
                const rowCreated = row.getAttribute('data-created') || '';
                const matchesQuery = query === '' || haystack.indexOf(query) !== -1;
                const matchesStatus = status === '' || rowStatus === status;
                const matchesFrom = fromDate === '' || (rowCreated !== '' && rowCreated >= fromDate);
                const matchesTo = toDate === '' || (rowCreated !== '' && rowCreated <= toDate);
                const visible = matchesQuery && matchesStatus && matchesFrom && matchesTo;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });

            sectionCards.forEach(section => {
                const sectionRows = Array.from(section.querySelectorAll('tr[data-doc-row="1"]'));
                const visibleRows = sectionRows.filter(r => r.style.display !== 'none').length;
                const emptyHelper = section.querySelector('.section-empty-row');

                if (emptyHelper) {
                    emptyHelper.style.display = visibleRows === 0 ? '' : 'none';
                }

                const sectionStatus = (section.getAttribute('data-section') || '').toLowerCase();
                const sectionMatchesStatus = status === '' || status === sectionStatus;
                section.style.display = (sectionMatchesStatus && (visibleRows > 0 || (emptyHelper && emptyHelper.style.display !== 'none'))) ? '' : 'none';
            });

            if (resultsSummary) {
                resultsSummary.textContent = 'Showing ' + visibleCount + ' document(s)';
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyDocFilters);
        }
        if (statusFilter) {
            statusFilter.addEventListener('change', applyDocFilters);
        }
        if (dateFromFilter) {
            dateFromFilter.addEventListener('change', applyDocFilters);
        }
        if (dateToFilter) {
            dateToFilter.addEventListener('change', applyDocFilters);
        }
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', function() {
                if (searchInput) searchInput.value = '';
                if (statusFilter) statusFilter.value = '';
                if (dateFromFilter) dateFromFilter.value = '';
                if (dateToFilter) dateToFilter.value = '';
                applyDocFilters();
            });
        }
        applyDocFilters();

        // Activity Modal Logic
        const activityModal = new bootstrap.Modal(document.getElementById('activityModal'));
        const modalSubtitle = document.getElementById('activityModalSubtitle');
        const modalLoading = document.getElementById('modalLoading');
        const modalError = document.getElementById('modalError');
        const errorMsg = document.getElementById('errorMsg');
        const timelineContainer = document.getElementById('timelineContainer');
        const emptyState = document.getElementById('emptyState');
        const printHistoryBtn = document.getElementById('printHistoryBtn');
        let currentAuditLogs = [];
        let currentAuditTitle = '';
        
        let sortColumn = 0; // 0: Activity, 1: Remarks
        let sortDirection = 'desc'; // desc = newest first
        let currentActivities = [];
        let currentDocInfo = { title: '', trackingNumber: '', creator: '', description: '' };
        let filteredActivities = [];
        let currentPage = 1;
        const itemsPerPage = 10;

        function openActivityModal(docId, title, trackingNumber, creator, description) {
            modalSubtitle.textContent = title;
            currentDocInfo = { title, trackingNumber, creator, description: description || '' };
            
            // Reset State
            modalLoading.classList.remove('d-none');
            timelineContainer.classList.add('d-none');
            modalError.classList.add('d-none');
            emptyState.classList.add('d-none');
            printHistoryBtn.classList.add('d-none'); // Hide print button initially
            
            // Reset filters
            document.getElementById('activitySearch').value = '';
            currentPage = 1;

            activityModal.show();

            // Fetch Data
            fetch('get_activity_history.php?document_id=' + docId)
                .then(response => response.json())
                .then(result => {
                    modalLoading.classList.add('d-none');
                    
                    if (result.status === 'success') {
                        currentActivities = result.data || [];
                        filteredActivities = [...currentActivities];
                        
                        if (currentActivities.length > 0) {
                            renderActivityTable();
                            timelineContainer.classList.remove('d-none');
                            printHistoryBtn.classList.remove('d-none'); // Show print button
                        } else {
                            emptyState.classList.remove('d-none');
                        }
                    } else {
                        showError(result.message || 'Unknown error occurred');
                    }
                })
                .catch(err => {
                    modalLoading.classList.add('d-none');
                    showError('Network error. Please try again.');
                    console.error(err);
                });
        }
        
        // Search functionality
        document.getElementById('activitySearch').addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            filteredActivities = currentActivities.filter(act => {
                const searchStr = (act.user_name + ' ' + act.activity_type + ' ' + act.details + ' ' + act.required_action).toLowerCase();
                return searchStr.includes(term);
            });
            currentPage = 1;
            renderActivityTable();
        });

        function sortActivityTable(colIndex) {
            if (sortColumn === colIndex) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = colIndex;
                sortDirection = 'asc';
            }
            renderActivityTable();
        }

        function renderActivityTable() {
            // Sort
            filteredActivities.sort((a, b) => {
                let valA, valB;
                if (sortColumn === 0) {
                    // Sort by Date (Activity column implies date order usually)
                    valA = new Date(a.created_at).getTime();
                    valB = new Date(b.created_at).getTime();
                } else {
                    // Sort by Remarks
                    valA = (a.details || '').toLowerCase();
                    valB = (b.details || '').toLowerCase();
                }
                
                if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });

            // Pagination
            const totalItems = filteredActivities.length;
            const totalPages = Math.ceil(totalItems / itemsPerPage);
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, totalItems);
            const pageItems = filteredActivities.slice(startIndex, endIndex);

            const tbody = document.getElementById('activityTableBody');
            tbody.innerHTML = '';
            
            if (pageItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="2" class="text-center py-4 text-muted">No activities found matching your search.</td></tr>';
            } else {
                pageItems.forEach(act => {
                    // Icon Logic
                    let icon = 'bi-activity';
                    let iconBg = 'bg-light';
                    let textClass = 'text-dark';
                    const type = act.activity_type.toLowerCase();
                    
                    if (type.includes('view')) { icon = 'bi-eye'; iconBg = 'bg-info-subtle'; textClass = 'text-info'; }
                    else if (type.includes('download')) { icon = 'bi-download'; iconBg = 'bg-success-subtle'; textClass = 'text-success'; }
                    else if (type.includes('upload')) { icon = 'bi-cloud-upload'; iconBg = 'bg-primary-subtle'; textClass = 'text-primary'; }
                    else if (type.includes('share') || type.includes('sent')) { icon = 'bi-send'; iconBg = 'bg-warning-subtle'; textClass = 'text-warning'; }
                    else if (type.includes('finish') || type.includes('complete')) { icon = 'bi-check-circle'; iconBg = 'bg-success-subtle'; textClass = 'text-success'; }

                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="ps-4">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle ${iconBg} ${textClass} d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; min-width: 40px;">
                                    <i class="bi ${icon} fs-5"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">${act.activity_type}</div>
                                    <div class="d-flex align-items-center text-secondary small mt-1">
                                        <i class="bi bi-person me-1"></i> ${act.user_name} <span class="badge bg-light text-secondary border ms-2">${act.user_role}</span>
                                    </div>
                                    <div class="text-muted xsmall mt-1"><i class="bi bi-clock me-1"></i> ${act.created_at}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                ${act.required_action ? `<span class="badge bg-warning text-dark mb-1 align-self-start">${act.required_action}</span>` : ''}
                                <span class="text-secondary text-break">${act.details || '-'}</span>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }

            // Update Counts
            document.getElementById('activityCount').textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalItems} activities`;

            // Update Pagination Controls
            const pagination = document.getElementById('activityPagination');
            pagination.innerHTML = '';
            
            if (totalPages > 1) {
                // Prev
                const prevLi = document.createElement('li');
                prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
                prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
                pagination.appendChild(prevLi);

                // Pages
                for (let i = 1; i <= totalPages; i++) {
                    const li = document.createElement('li');
                    li.className = `page-item ${currentPage === i ? 'active' : ''}`;
                    li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i}</a>`;
                    pagination.appendChild(li);
                }

                // Next
                const nextLi = document.createElement('li');
                nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
                nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>`;
                pagination.appendChild(nextLi);
            }
        }

        function changePage(page) {
            currentPage = page;
            renderActivityTable();
        }

        function exportToCSV() {
            if (!filteredActivities.length) return;
            
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Activity Type,User,Role,Date,Details,Required Action\n";
            
            filteredActivities.forEach(act => {
                const row = [
                    act.activity_type,
                    act.user_name,
                    act.user_role,
                    act.created_at,
                    `"${(act.details || '').replace(/"/g, '""')}"`,
                    act.required_action || ''
                ];
                csvContent += row.join(",") + "\n";
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `activity_history_${currentDocInfo.trackingNumber}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function printActivityHistory() {
            if (!currentActivities || currentActivities.length === 0) {
                alert('No activity to print.');
                return;
            }

            const printWindow = window.open('', '', 'height=800,width=1000');
            
            // Generate Rows
            let rowsHtml = '';
            // Filter activities: exclude 'view', 'download'
            const printableActivities = currentActivities.filter(a => {
                const type = a.activity_type.toLowerCase();
                return !type.includes('view') && !type.includes('download');
            });

            if (printableActivities.length === 0) {
                 rowsHtml = '<tr><td colspan="2" style="text-align:center;">No routing history available.</td></tr>';
            } else {
                printableActivities.forEach(act => {
                    const type = act.activity_type.toLowerCase();
                    let recipient = '';
                    
                    // Check if recipient name is explicitly provided (for new structure)
                    if (act.recipient_name) {
                        recipient = act.recipient_name;
                    } 
                    // Fallback: Attempt to parse recipient from details if 'sent' or 'share' (for legacy logs)
                    else if (type.includes('sent') || type.includes('share')) {
                        // Assuming detail format "Successfully sent the ... to [Name]"
                        const detailLower = act.details.toLowerCase();
                        const toIndex = detailLower.lastIndexOf(' to ');
                        if (toIndex !== -1) {
                            recipient = act.details.substring(toIndex + 4).trim();
                            // Clean up trailing punctuation if any
                            if (recipient.endsWith('.')) recipient = recipient.slice(0, -1);
                        }
                    }

                    const ra = (act.required_action || '').trim().toLowerCase();
                    const det = (act.details || '').trim();
                    const remarksText = det && det.toLowerCase() !== ra
                        ? det
                        : (currentDocInfo.description || det || '-');

                    rowsHtml += `
                        <tr>
                            <td class="left-col">
                                <div class="row-item"><span class="label">FOR:</span> ${recipient}</div>
                                <div class="row-item"><span class="label">FROM:</span> ${act.user_name}</div>
                                <div class="row-item"><span class="label">Received by:</span> ${recipient}</div>
                                <div class="row-item"><span class="label">Date/Time:</span> ${act.created_at}</div>
                            </td>
                            <td class="right-col">
                                <div class="row-item"><span class="label">Action Needed/Taken:</span> ${act.required_action || ''}</div>
                                <div class="row-item mt-2"><span class="label">Remarks:</span></div>
                                <div class="content" style="text-align:left;">
                                    ${remarksText}
                                </div>
                            </td>
                        </tr>
                    `;
                });
            }

            const html = `
                <html>
                <head>
                    <title>Document Routing Slip - ${currentDocInfo.title}</title>
                    <style>
                        body { font-family: 'Arial', sans-serif; font-size: 12px; margin: 20px; }
                        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                        .logo { position: absolute; left: 0; top: 0; }
                        .title h2 { margin: 0; font-size: 18px; text-transform: uppercase; font-weight: bold; }
                        .sub-header { font-size: 10px; margin-top: 5px; border: 1px solid #000; padding: 2px 5px; display: inline-block; background: #eee; }
                        .info-section { margin-bottom: 15px; }
                        .info-row { margin-bottom: 8px; }
                        .label { font-weight: bold; }
                        .value { border-bottom: 1px solid #000; padding-left: 5px; padding-right: 5px; display: inline-block; min-width: 150px; }
                        table { width: 100%; border-collapse: collapse; border: 1px solid #000; }
                        td { border: 1px solid #000; padding: 5px; vertical-align: top; }
                        .left-col { width: 40%; }
                        .right-col { width: 60%; }
                        .row-item { margin-bottom: 4px; }
                        .left-col .label { display: inline-block; width: 80px; }
                        .right-col .content { margin-top: 5px; white-space: pre-wrap; }
                        @media print {
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div style="position: relative; height: 60px; display: flex; align-items: center; justify-content: center;">
                            <div class="logo">
                                <!-- Placeholder for logo -->
                                <img src="../assets/img/logo.png" alt="Logo" style="height: 50px;" onerror="this.style.display='none'">
                            </div>
                            <div>
                                <h2>Document Routing Slip</h2>
                                <div class="sub-header">
                                    Form No. DRS-OED-FM016 | Version No. 07 | Effectivity Date: October 15, 2024
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-row">
                            <span class="label">Subject:</span> <span class="value" style="width: 80%; border-bottom: 1px solid #000;">${currentDocInfo.title}</span>
                        </div>
                        <div class="info-row">
                            <span class="label">Originator:</span> <span class="value">${currentDocInfo.creator}</span>
                            <span class="label" style="margin-left: 20px;">Control No.:</span> <span class="value">${currentDocInfo.trackingNumber}</span>
                            <span class="label" style="margin-left: 20px;">OED No.:</span> <span class="value" style="width: 100px;">&nbsp;</span>
                        </div>
                        <div class="info-row" style="margin-top: 10px;">
                            <span class="label">Description/Remarks:</span> <span class="value" style="width: 80%; border-bottom: 1px solid #000;">${currentDocInfo.description || ''}</span>
                        </div>
                    </div>

                    <table>
                        ${rowsHtml}
                        <!-- Empty row for manual entry -->
                        <tr style="height: 100px;">
                            <td class="left-col">
                                <div class="row-item"><span class="label">FOR:</span></div>
                                <div class="row-item"><span class="label">FROM:</span></div>
                                <div class="row-item"><span class="label">Received by:</span></div>
                                <div class="row-item"><span class="label">Date/Time:</span></div>
                            </td>
                            <td class="right-col">
                                <div class="row-item"><span class="label">Action Needed/Taken / Remarks:</span></div>
                            </td>
                        </tr>
                    </table>
                    
                    <script>
                        window.onload = function() { window.print(); }
                    ${'</' + 'script>'}
                </body>
                </html>
            `;
            
            printWindow.document.write(html);
            printWindow.document.close();
        }

        function escHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function buildAuditNarrative(log) {
            const actor = log.user_name || 'User';
            const action = (log.activity_type || 'activity').toLowerCase();

            if (action.includes('sent')) return actor + ' sent this document';
            if (action.includes('shared')) return actor + ' shared this document';
            if (action.includes('upload')) return actor + ' uploaded a file';
            if (action.includes('download')) return actor + ' downloaded a file';
            if (action.includes('view')) return actor + ' viewed this document';
            if (action.includes('delete')) return actor + ' deleted a document item';

            return actor + ' performed: ' + action;
        }

        function openAuditModal(docId, title) {
            const auditModal = new bootstrap.Modal(document.getElementById('auditModal'));
            document.getElementById('auditModalSubtitle').textContent = title;
            const tbody = document.getElementById('auditTableBody');
            currentAuditTitle = title || 'Activity Audit Log';
            currentAuditLogs = [];
            
            document.getElementById('auditLoading').classList.remove('d-none');
            document.getElementById('auditContainer').classList.add('d-none');
            document.getElementById('auditError').classList.add('d-none');
            
            auditModal.show();
            
            fetch('get_audit_log.php?document_id=' + docId)
                .then(r => r.json())
                .then(res => {
                    document.getElementById('auditLoading').classList.add('d-none');
                    if (res.status === 'success') {
                        currentAuditLogs = Array.isArray(res.data) ? res.data : [];
                        document.getElementById('auditContainer').classList.remove('d-none');
                        tbody.innerHTML = '';
                        if (currentAuditLogs.length > 0) {
                            currentAuditLogs.forEach(log => {
                                let badgeClass = 'bg-secondary';
                                const type = log.activity_type.toLowerCase();
                                if (type.includes('upload')) badgeClass = 'bg-primary';
                                else if (type.includes('download')) badgeClass = 'bg-success';
                                else if (type.includes('view')) badgeClass = 'bg-info text-dark';
                                else if (type.includes('sent')) badgeClass = 'bg-warning text-dark';
                                else if (type.includes('shared')) badgeClass = 'bg-warning text-dark';
                                else if (type.includes('delete')) badgeClass = 'bg-danger';

                                const narrative = buildAuditNarrative(log);
                                const remarkText = (log.details || '').trim();
                                const recipientBlock = log.recipient_name
                                    ? '<div class="mt-1 p-2 bg-light rounded border-start border-3 border-primary"><i class="bi bi-arrow-right-short text-primary me-1"></i> Sent to: <span class="fw-medium text-dark">' + escHtml(log.recipient_name) + '</span></div>'
                                    : '';
                                const sharedAllBlock = (String(log.activity_type || '').toLowerCase() === 'shared' && !log.recipient_name)
                                    ? '<div class="mt-1 p-2 bg-light rounded border-start border-3 border-info"><i class="bi bi-people-fill text-info me-1"></i> Shared with: <span class="fw-medium text-dark">All Users</span></div>'
                                    : '';

                                const row = `
                                    <tr>
                                        <td class="ps-4 text-secondary small font-monospace">${escHtml(log.created_at)}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2 border" style="width: 32px; height: 32px;">
                                                    <i class="bi bi-person text-secondary"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark small">${escHtml(log.user_name)}</div>
                                                    <div class="text-muted xsmall" style="font-size: 0.75rem;">${escHtml(log.user_role)}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge ${badgeClass} rounded-pill px-2 fw-normal">${escHtml(log.activity_type)}</span>
                                        </td>
                                        <td class="text-secondary small">
                                            <div class="fw-semibold text-dark mb-1">${escHtml(narrative)}</div>
                                            <div><span class="fw-semibold text-secondary">Remark:</span> ${escHtml(remarkText || '-')}</div>
                                            ${recipientBlock}
                                            ${sharedAllBlock}
                                        </td>
                                    </tr>
                                `;
                                tbody.innerHTML += row;
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-journal-x display-4 opacity-25 mb-3 d-block"></i>No audit records found.</td></tr>';
                        }
                    } else {
                        document.getElementById('auditError').classList.remove('d-none');
                        document.getElementById('auditErrorMsg').textContent = res.message;
                    }
                })
                .catch(e => {
                    console.error(e);
                    document.getElementById('auditLoading').classList.add('d-none');
                    document.getElementById('auditError').classList.remove('d-none');
                    document.getElementById('auditErrorMsg').textContent = 'Network error.';
                });
        }

        function printAuditLog() {
            if (!currentAuditLogs || currentAuditLogs.length === 0) {
                alert('No audit logs to print.');
                return;
            }

            const esc = (value) => String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

            const generatedAt = new Date().toLocaleString('en-US', {
                year: 'numeric', month: 'short', day: '2-digit',
                hour: '2-digit', minute: '2-digit'
            });

            const actionCounts = {};
            currentAuditLogs.forEach(log => {
                const action = String(log.activity_type || 'unknown').toLowerCase();
                actionCounts[action] = (actionCounts[action] || 0) + 1;
            });

            let summaryHtml = '';
            Object.keys(actionCounts).sort().forEach(action => {
                summaryHtml += `<span class="chip">${esc(action)}: <strong>${actionCounts[action]}</strong></span>`;
            });

            let rowsHtml = '';
            currentAuditLogs.forEach((log, idx) => {
                const narrative = buildAuditNarrative(log);
                const remarkText = (log.details || '').trim();
                const recipient = log.recipient_name
                    ? `<div class="recipient">Sent to: ${esc(log.recipient_name)}</div>`
                    : '';
                const sharedAll = (String(log.activity_type || '').toLowerCase() === 'shared' && !log.recipient_name)
                    ? `<div class="recipient">Shared with: All Users</div>`
                    : '';

                rowsHtml += `
                    <tr>
                        <td class="num">${idx + 1}</td>
                        <td>${esc(log.created_at)}</td>
                        <td>
                            <div class="user-name">${esc(log.user_name)}</div>
                            <div class="user-role">${esc(log.user_role)}</div>
                        </td>
                        <td><span class="action-pill">${esc(log.activity_type)}</span></td>
                        <td>
                            <div class="narrative">${esc(narrative)}</div>
                            <div><span class="remark-label">Remark:</span> ${esc(remarkText || '-')}</div>
                            ${recipient}
                            ${sharedAll}
                        </td>
                    </tr>
                `;
            });

            const html = `
                <html>
                <head>
                    <title>Activity Audit Log - ${esc(currentAuditTitle)}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 24px; color: #1f2937; }
                        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 12px; margin-bottom: 16px; }
                        .title { font-size: 22px; font-weight: 700; color: #0d1b2a; margin: 0; }
                        .subtitle { margin-top: 6px; color: #6b7280; font-size: 13px; }
                        .meta { display: flex; justify-content: space-between; margin-top: 12px; font-size: 12px; color: #4b5563; }
                        .summary { margin: 14px 0 18px; display: flex; flex-wrap: wrap; gap: 8px; }
                        .chip { border: 1px solid #bfdbfe; background: #eff6ff; color: #1e3a8a; padding: 6px 10px; border-radius: 999px; font-size: 12px; }
                        table { width: 100%; border-collapse: collapse; border: 1px solid #d1d5db; }
                        th, td { border: 1px solid #e5e7eb; padding: 10px; vertical-align: top; font-size: 12px; }
                        th { background: #f3f4f6; text-transform: uppercase; font-size: 11px; letter-spacing: 0.03em; text-align: left; }
                        .num { width: 36px; text-align: center; color: #6b7280; }
                        .user-name { font-weight: 700; }
                        .user-role { font-size: 11px; color: #6b7280; margin-top: 2px; }
                        .action-pill { display: inline-block; background: #dbeafe; color: #1d4ed8; border-radius: 999px; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
                        .narrative { font-weight: 700; color: #111827; margin-bottom: 4px; }
                        .remark-label { font-weight: 700; color: #4b5563; }
                        .recipient { margin-top: 6px; padding: 6px 8px; border-left: 3px solid #60a5fa; background: #f8fafc; color: #1f2937; font-size: 11px; }
                        @media print { body { margin: 12px; } }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1 class="title">Activity Audit Log Report</h1>
                        <div class="subtitle">${esc(currentAuditTitle)}</div>
                        <div class="meta">
                            <div>Total Records: <strong>${currentAuditLogs.length}</strong></div>
                            <div>Generated: ${esc(generatedAt)}</div>
                        </div>
                    </div>
                    <div class="summary">${summaryHtml}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <script>window.onload = function () { window.print(); };${'</' + 'script>'}
                </body>
                </html>
            `;

            const printWindow = window.open('', '', 'height=900,width=1200');
            printWindow.document.write(html);
            printWindow.document.close();
        }
    </script>
</body>
</html>

