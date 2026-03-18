<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include '../plugins/conn.php';

$document_id = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
$user_id = $_SESSION['user_id'];

if ($document_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Document ID']);
    exit();
}

// Check access (Creator or Shared)
$check = $conn->prepare("SELECT created_by FROM documents WHERE document_id = ?");
$check->bind_param("i", $document_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Document not found']);
    exit();
}
$doc = $res->fetch_assoc();

if ($doc['created_by'] != $user_id) {
    // Basic check. Ideally check shares too.
    // For now, if not creator, deny.
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit();
}

// Fetch Detailed Audit Log
$sql = "SELECT log.*, 
               u.username as user_name, u.role as user_role,
               r.username as recipient_name, r.role as recipient_role
        FROM document_activity_log log
        LEFT JOIN users u ON log.user_id = u.user_id
        LEFT JOIN users r ON log.recipient_id = r.user_id
        WHERE log.document_id = ?
        ORDER BY log.created_at ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $document_id);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = [
        'created_at' => date('M d, Y H:i:s', strtotime($row['created_at'])),
        'user_name' => $row['user_name'] ?: 'Unknown',
        'user_role' => ucfirst($row['user_role'] ?: ''),
        'activity_type' => ucfirst($row['activity_type']),
        'details' => $row['details'],
        'recipient_name' => $row['recipient_name'] ? $row['recipient_name'] . ' (' . ucfirst($row['recipient_role']) . ')' : null
    ];
}

echo json_encode(['status' => 'success', 'data' => $data]);
?>