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
// Optional filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$role_filter = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : null; // sender|receiver|any
$status_filter = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : null; // submitted|pending|any

if ($document_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Document ID']);
    exit();
}

// Check if user has access to this document (Created by user OR Shared with user)
// For "Shared Docs Log", we primarily care about documents created by the user (outgoing)
// But for safety, we check if they are the creator.
$check = $conn->prepare("SELECT created_by, description FROM documents WHERE document_id = ?");
$check->bind_param("i", $document_id);
$check->execute();
$res = $check->get_result();
if ($res->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Document not found']);
    exit();
}
$doc = $res->fetch_assoc();

if ($doc['created_by'] != $user_id) {
    // Check if it's shared WITH the user (optional, if we want to support incoming docs tracking too)
    // For now, let's stick to strict ownership for the "Shared Docs Log" context
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit();
}

// Validate date filters
$date_from_ts = null;
$date_to_ts = null;
if ($date_from) {
    $df = strtotime($date_from);
    if ($df !== false) $date_from_ts = $df;
}
if ($date_to) {
    $dt = strtotime($date_to);
    if ($dt !== false) $date_to_ts = $dt;
}

// Fetch transmission shares (sender -> receiver)
$shares_sql = "SELECT sh.document_id, sh.recipient_id, sh.shared_by, sh.created_at
               FROM document_shares sh
               WHERE sh.document_id = ?
               ORDER BY sh.created_at ASC";
$stmt_sh = $conn->prepare($shares_sql);
$stmt_sh->bind_param("i", $document_id);
$stmt_sh->execute();
$res_sh = $stmt_sh->get_result();

$transactions = [];
while ($sh = $res_sh->fetch_assoc()) {
    $sender_id = (int)$sh['shared_by'];
    $receiver_id = (int)$sh['recipient_id'];
    $sent_at = strtotime($sh['created_at']);
    if ($date_from_ts && $sent_at < $date_from_ts) continue;
    if ($date_to_ts && $sent_at > $date_to_ts) continue;
    
    // Sender
    $sender_q = $conn->prepare("SELECT user_id, username, first_name, last_name, role FROM users WHERE user_id = ?");
    $sender_q->bind_param("i", $sender_id);
    $sender_q->execute();
    $sender = $sender_q->get_result()->fetch_assoc();
    $sender_name = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));
    if (!$sender_name) $sender_name = $sender['username'] ?? 'Unknown';
    $sender_role = ucfirst($sender['role'] ?? 'User');
    
    // Receiver
    $receiver_q = $conn->prepare("SELECT user_id, username, first_name, last_name, role FROM users WHERE user_id = ?");
    $receiver_q->bind_param("i", $receiver_id);
    $receiver_q->execute();
    $receiver = $receiver_q->get_result()->fetch_assoc();
    $receiver_name = trim(($receiver['first_name'] ?? '') . ' ' . ($receiver['last_name'] ?? ''));
    if (!$receiver_name) $receiver_name = $receiver['username'] ?? 'Unknown';
    $receiver_role = ucfirst($receiver['role'] ?? 'User');
    
    // Role filter
    if ($role_filter === 'sender' && strtolower($sender_role) !== 'admin' && strtolower($sender_role) !== 'superadmin') continue;
    if ($role_filter === 'receiver' && strtolower($receiver_role) !== 'admin' && strtolower($receiver_role) !== 'superadmin') continue;
    
    // Required Action remark by sender
    $req_stmt = $conn->prepare("SELECT remark, created_at FROM document_remarks WHERE document_id = ? AND user_id = ? AND remark LIKE 'Required Action:%' ORDER BY created_at DESC LIMIT 1");
    $req_stmt->bind_param("ii", $document_id, $sender_id);
    $req_stmt->execute();
    $req_res = $req_stmt->get_result()->fetch_assoc();
    $required_action_remark = $req_res['remark'] ?? '';
    $required_action_label = '';
    if (!empty($required_action_remark)) {
        $required_action_label = str_replace('Required Action: ', '', $required_action_remark);
    }

    // Latest non-required remarks by sender (e.g., instructions, notes)
    $sender_remark_stmt = $conn->prepare("SELECT remark FROM document_remarks WHERE document_id = ? AND user_id = ? AND remark NOT LIKE 'Required Action:%' ORDER BY created_at DESC LIMIT 1");
    $sender_remark_stmt->bind_param("ii", $document_id, $sender_id);
    $sender_remark_stmt->execute();
    $sender_remark_row = $sender_remark_stmt->get_result()->fetch_assoc();
    $sender_latest_remark = $sender_remark_row['remark'] ?? '';
    if (empty($sender_latest_remark)) {
        // Fallback to document description if available
        $sender_latest_remark = $doc['description'] ?? '';
    }
    // Receiver submission (finished) if any
    $fin_stmt = $conn->prepare("SELECT details, created_at FROM document_activity_log WHERE document_id = ? AND user_id = ? AND activity_type = 'finished' ORDER BY created_at DESC LIMIT 1");
    $fin_stmt->bind_param("ii", $document_id, $receiver_id);
    $fin_stmt->execute();
    $fin = $fin_stmt->get_result()->fetch_assoc();
    
    $receiver_submitted = false;
    $receiver_submitted_at = null;
    $receiver_remarks = '';
    if ($fin) {
        $receiver_submitted = true;
        $receiver_submitted_at = strtotime($fin['created_at']);
        $receiver_remarks = substr(strip_tags($fin['details'] ?? ''), 0, 500);
    }
    // Status filter
    if ($status_filter === 'submitted' && !$receiver_submitted) continue;
    if ($status_filter === 'pending' && $receiver_submitted) continue;
    
    // Compose two timeline entries: Transmission and (optional) Receiver Submission
    $transactions[] = [
        'activity_type' => 'Sent',
        'details' => !empty($sender_latest_remark) ? $sender_latest_remark : 'No remarks',
        'created_at' => date('Y-m-d H:i:s', $sent_at),
        'user_name' => $sender_name,
        'user_role' => $sender_role,
        'user_initials' => strtoupper(substr($sender_name, 0, 1)),
        'required_action' => $required_action_label,
        'recipient_name' => $receiver_name
    ];
    
    if ($receiver_submitted) {
        $transactions[] = [
            'activity_type' => 'Receiver Submission',
            'details' => $receiver_remarks ?: 'No remarks',
            'created_at' => date('Y-m-d H:i:s', $receiver_submitted_at),
            'user_name' => $receiver_name,
            'user_role' => $receiver_role,
            'user_initials' => strtoupper(substr($receiver_name, 0, 1)),
            'required_action' => '',
            'recipient_name' => $sender_name
        ];
    }
}

// If no shares recorded, fall back to raw activity log for completeness
if (empty($transactions)) {
    $sql = "SELECT log.*, u.username, u.first_name, u.last_name, u.role,
            (SELECT remark FROM document_remarks WHERE document_id = log.document_id AND user_id = log.user_id AND remark LIKE 'Required Action:%' ORDER BY created_at DESC LIMIT 1) as required_action_remark
            FROM document_activity_log log
            LEFT JOIN users u ON log.user_id = u.user_id
            WHERE log.document_id = ?
            ORDER BY log.created_at ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $full_name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if (!$full_name) $full_name = $row['username'] ?? 'Unknown';
        $initials = strtoupper(substr($full_name, 0, 1));
        $required_action = '';
        if (!empty($row['required_action_remark'])) {
            $required_action = str_replace('Required Action: ', '', $row['required_action_remark']);
        }
        $transactions[] = [
            'activity_type' => ucfirst($row['activity_type']),
            'details' => substr(strip_tags($row['details'] ?? ''), 0, 500),
            'created_at' => date('Y-m-d H:i:s', strtotime($row['created_at'])),
            'user_name' => $full_name,
            'user_role' => ucfirst($row['role'] ?? 'User'),
            'user_initials' => $initials,
            'required_action' => $required_action
        ];
    }
}

echo json_encode(['status' => 'success', 'data' => $transactions]);
?>
