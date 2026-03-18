<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: ../index.php");
    exit();
}
include '../plugins/conn.php';

if (isset($_GET['id'])) {
    $document_id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // Get file path
    $stmt = $conn->prepare("SELECT d.document_id, d.title, a.file_path, a.file_name 
                            FROM documents d 
                            JOIN attachments a ON d.document_id = a.document_id 
                            WHERE d.document_id = ?");
    $stmt->bind_param("i", $document_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $file_path = $row['file_path'];
        $file_name = $row['file_name'];

        if (file_exists($file_path)) {
            // Log Activity
            $log_stmt = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'download', ?, NOW())");
            $details = "Downloaded file: " . $file_name;
            $log_stmt->bind_param("iis", $document_id, $user_id, $details);
            $log_stmt->execute();

            // Serve File
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="'.basename($file_name).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else {
            echo "File not found on server.";
        }
    } else {
        echo "Document not found.";
    }
} else {
    echo "Invalid request.";
}
?>
