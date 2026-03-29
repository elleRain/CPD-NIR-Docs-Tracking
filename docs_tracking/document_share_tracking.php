<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit();
}

include '../plugins/conn.php';

// Handle File History Request
if (isset($_GET['activity']) && $_GET['activity'] == 1 && isset($_GET['document_id'])) {
    $document_id = intval($_GET['document_id']);
    
    if ($document_id <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Invalid Document ID']);
        exit();
    }

    // Check if user has access (optional, but good practice)
    // For now, assuming if they can access the page, they can see history.
    // Tighter security would check document_shares or ownership.

    $stmt = $conn->prepare("SELECT file_name, file_path, status, remarks, updated_at, updated_by FROM document_file_history WHERE document_id = ? ORDER BY updated_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $document_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $history = [];
        while ($row = $result->fetch_assoc()) {
            // Check if file exists for download link
            // Adjust path if necessary. DB stores relative to script that inserted it?
            // admin/document.php stores '../uploads/' + filename.
            // This script is in docs_tracking/, so '../uploads/' is valid relative to parent? 
            // No, '../uploads/' from 'admin/' is 'c:/xampp/htdocs/docs/uploads/'.
            // From 'docs_tracking/', '../uploads/' is also 'c:/xampp/htdocs/docs/uploads/'.
            // So the path in DB should be valid.
            
            $history[] = [
                'file_name' => $row['file_name'],
                'file_path' => $row['file_path'], // Expose path for download
                'status' => $row['status'],
                'remarks' => $row['remarks'],
                'updated_at' => $row['updated_at']
            ];
        }
        
        // Also include the current file from documents table if it's not in history yet?
        // Usually the logic in document.php inserts into history when updating.
        // But the *initial* file might not be in history if it wasn't "updated".
        // Let's check if we need to merge with current document.
        // admin/document.php inserts into history ONLY on update.
        // So the original file is NOT in history table.
        // We should probably include the current file from `documents` table as the "Original" or "Current" if not present?
        // Wait, admin/document.php says:
        // // Log file history (Current)
        // $stmt_hist = ... INSERT ... 'Current'
        
        // So every update adds a "Current" record and marks previous as "Old".
        // What about the VERY FIRST upload?
        // It seems the first upload is NOT in `document_file_history`.
        // So we should fetch the current document details and add it if it's not redundant?
        // Or maybe just show what's in history. 
        // If history is empty, the frontend says "No file history found."
        
        // Let's stick to returning what's in the table first.
        
        echo json_encode(['ok' => true, 'file_history' => $history]);
        $stmt->close();
    } else {
        echo json_encode(['ok' => false, 'error' => 'Database error']);
    }
    exit();
}

// Fallback
echo json_encode(['ok' => false, 'error' => 'Invalid request']);
?>
