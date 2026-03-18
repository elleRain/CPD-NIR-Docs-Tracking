<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include '../plugins/conn.php';

$user_id = $_SESSION['user_id'];
$document_id = isset($_POST['document_id']) ? intval($_POST['document_id']) : 0;
$activity_type = isset($_POST['activity_type']) ? $_POST['activity_type'] : '';
$details = isset($_POST['details']) ? $_POST['details'] : '';

if ($document_id > 0 && !empty($activity_type)) {
    // Check if view already logged for this user and document
    if ($activity_type === 'view') {
        $check_stmt = $conn->prepare("SELECT 1 FROM document_activity_log WHERE document_id = ? AND user_id = ? AND activity_type = 'view'");
        $check_stmt->bind_param("ii", $document_id, $user_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        if ($check_stmt->num_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Already logged']);
            $check_stmt->close();
            exit();
        }
        $check_stmt->close();
        
        // Fetch user full name for detailed message
        $user_stmt = $conn->prepare("SELECT CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as full_name FROM users WHERE user_id = ?");
        $user_stmt->bind_param("i", $user_id);
        $user_stmt->execute();
        $res = $user_stmt->get_result();
        $user_name = 'User';
        if ($row = $res->fetch_assoc()) {
            $user_name = $row['full_name'];
        }
        $user_stmt->close();
        
        $current_time = date('F j, Y g:i A');
        $details = "$user_name viewed the file at $current_time";
    }

    $stmt = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiss", $document_id, $user_id, $activity_type, $details);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
}
?>
