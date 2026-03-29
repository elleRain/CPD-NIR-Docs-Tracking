<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'])) {
    http_response_code(403);
    exit('Unauthorized');
}

include '../plugins/conn.php';
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;

if ($type_id <= 0) {
    echo '<tr><td colspan="6" class="text-center py-5 text-secondary">Invalid folder.</td></tr>';
    exit;
}

$status_condition = ($role === 'superadmin') ? 'd.status_id != 1' : 'd.status_id = 5';

$doc_stmt = $conn->prepare("SELECT d.*, dt.type_name, s.status_name, u.username as created_by_user,
                            (SELECT file_name FROM attachments WHERE document_id = d.document_id LIMIT 1) as file_name
                            FROM documents d
                            LEFT JOIN document_types dt ON d.type_id = dt.type_id
                            LEFT JOIN document_status s ON d.status_id = s.status_id
                            LEFT JOIN users u ON d.created_by = u.user_id
                            WHERE d.type_id = ?
                              AND $status_condition
                              AND (NOT EXISTS (SELECT 1 FROM document_shares ds WHERE ds.document_id = d.document_id)
                                   OR EXISTS (SELECT 1 FROM document_shares ds2 WHERE ds2.document_id = d.document_id AND ds2.recipient_id = ?))
                            ORDER BY d.created_at DESC");
$doc_stmt->bind_param("ii", $type_id, $user_id);
$doc_stmt->execute();
$documents_result = $doc_stmt->get_result();

if ($documents_result && $documents_result->num_rows > 0) {
    while ($doc = $documents_result->fetch_assoc()) {
        $status_class = 'status-draft';
        switch ($doc['status_id']) {
            case 2:
                $status_class = 'status-submitted';
                break;
            case 3:
                $status_class = 'status-received';
                break;
            case 4:
                $status_class = 'status-forwarded';
                break;
            case 5:
                $status_class = 'status-approved';
                break;
            case 6:
                $status_class = 'status-rejected';
                break;
        }

        $icon_class = 'bi-file-earmark';
        $icon_color = 'text-secondary';

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

        echo '<tr>';
        echo '<td class="ps-4 fw-medium text-primary">' . htmlspecialchars($doc['tracking_number']) . '</td>';
        echo '<td><div class="d-flex align-items-center">';
        echo '<i class="bi ' . $icon_class . ' ' . $icon_color . ' me-2 fs-5"></i>';
        echo '<span>' . htmlspecialchars($doc['title']) . '</span>';
        echo '</div></td>';
        echo '<td><span class="status-badge ' . $status_class . '">' . htmlspecialchars($doc['status_name']) . '</span></td>';
        echo '<td>' . htmlspecialchars($doc['created_by_user']) . '</td>';
        echo '<td class="text-secondary">' . date('M d, Y', strtotime($doc['created_at'])) . '</td>';
        echo '<td class="pe-4 text-end"><button class="btn btn-sm btn-light border" title="View Details"><i class="bi bi-eye"></i></button></td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6" class="text-center py-5 text-secondary">No documents found in this folder.</td></tr>';
}
?>