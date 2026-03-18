<?php
session_start();
if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
    header("Location: ../index.php");
    exit();
}
$role = $_SESSION['role'];
include dirname(__DIR__) . '/plugins/conn.php';
include dirname(__DIR__) . '/notification/notification_helpers.php';
$user_id = (int)$_SESSION['user_id'];

// Helper: delete document files + DB records safely.
function delete_document_by_id($conn, $document_id) {
    $deleted = false;
    $document_id = (int)$document_id;
    if ($document_id <= 0) {
        return false;
    }

    $stmt_att = $conn->prepare("SELECT file_path FROM attachments WHERE document_id = ?");
    if ($stmt_att) {
        $stmt_att->bind_param("i", $document_id);
        $stmt_att->execute();
        $res = $stmt_att->get_result();
        while ($att = $res->fetch_assoc()) {
            if (!empty($att['file_path']) && file_exists($att['file_path'])) {
                @unlink($att['file_path']);
            }
        }
        $stmt_att->close();
    }

    $stmt_del_att = $conn->prepare("DELETE FROM attachments WHERE document_id = ?");
    $stmt_del_doc = $conn->prepare("DELETE FROM documents WHERE document_id = ?");

    if ($stmt_del_att && $stmt_del_doc) {
        $stmt_del_att->bind_param("i", $document_id);
        $stmt_del_doc->bind_param("i", $document_id);

        $stmt_del_att->execute();
        $deleted = $stmt_del_doc->execute();

        $stmt_del_att->close();
        $stmt_del_doc->close();
    }

    return $deleted;
}

// Fetch users for sharing (exclude current user)
$share_users = [];
$users_sql = "SELECT user_id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) AS full_name, email, role 
              FROM users 
              WHERE user_id != ? 
              ORDER BY full_name ASC";
$stmt_users = $conn->prepare($users_sql);
if ($stmt_users) {
    $stmt_users->bind_param("i", $user_id);
    $stmt_users->execute();
    $result_users = $stmt_users->get_result();
    while ($row = $result_users->fetch_assoc()) {
        $share_users[] = $row;
    }
    $stmt_users->close();
}

// Fetch document types
$types_query = "SELECT * FROM document_types";
$types_result = $conn->query($types_query);

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['designated_review'], $_POST['action'], $_POST['document_id'], $_POST['recipient_id'])) {
        $doc_id = (int)$_POST['document_id'];
        $recipient_id = (int)$_POST['recipient_id'];
        $action = $_POST['action'];

        $new_status = 0;
        $action_text = '';
        if ($action === 'approve') {
            $new_status = 5;
            $action_text = 'Approved designated document';
        } elseif ($action === 'revision') {
            $new_status = 4;
            $action_text = 'Requested revision for designated document';
        }

        if ($doc_id > 0 && $recipient_id > 0 && $new_status > 0) {
            $share_stmt = $conn->prepare("SELECT 1 FROM document_shares WHERE document_id = ? AND shared_by = ? AND recipient_id = ? LIMIT 1");
            if ($share_stmt) {
                $share_stmt->bind_param("iii", $doc_id, $user_id, $recipient_id);
                $share_stmt->execute();
                $share_res = $share_stmt->get_result();
                $is_owner_of_share = $share_res && $share_res->num_rows > 0;
                $share_stmt->close();

                if ($is_owner_of_share) {
                    $finished_stmt = $conn->prepare("SELECT COUNT(*) as c FROM document_activity_log WHERE document_id = ? AND user_id = ? AND activity_type = 'finished'");
                    if ($finished_stmt) {
                        $finished_stmt->bind_param("ii", $doc_id, $recipient_id);
                        $finished_stmt->execute();
                        $finished_res = $finished_stmt->get_result();
                        $finished_row = $finished_res ? $finished_res->fetch_assoc() : null;
                        $is_finished = $finished_row && (int)$finished_row['c'] > 0;
                        $finished_stmt->close();

                        if ($is_finished) {
                            $doc_stmt = $conn->prepare("SELECT title, tracking_number FROM documents WHERE document_id = ?");
                            $doc = null;
                            if ($doc_stmt) {
                                $doc_stmt->bind_param("i", $doc_id);
                                $doc_stmt->execute();
                                $doc_res = $doc_stmt->get_result();
                                $doc = $doc_res ? $doc_res->fetch_assoc() : null;
                                $doc_stmt->close();
                            }

                            $update_stmt = $conn->prepare("UPDATE documents SET status_id = ? WHERE document_id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param("ii", $new_status, $doc_id);
                                if ($update_stmt->execute()) {
                                    $action_msg = $action_text;
                                    if ($doc) {
                                        $action_msg .= ': ' . $doc['title'] . ' (' . $doc['tracking_number'] . ')';
                                    }
                                    $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
                                    if ($log_stmt) {
                                        $log_stmt->bind_param("isi", $user_id, $action_msg, $doc_id);
                                        $log_stmt->execute();
                                        $log_stmt->close();
                                    }
                                }
                                $update_stmt->close();
                            }
                        }
                    }
                }
            }
        }

        header("Location: my_documents.php?tab=designated");
        exit();
    }

    // Add Document
    if (isset($_POST['add_document']) || isset($_POST['save_draft'])) {
        $type_id = $_POST['type_id'];

        // Handle Custom Document Type
        if ($type_id == 'other' && !empty($_POST['custom_type'])) {
            $custom_type = trim($_POST['custom_type']);
            // Check if type exists
            $stmt_check = $conn->prepare("SELECT type_id FROM document_types WHERE type_name = ?");
            $stmt_check->bind_param("s", $custom_type);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();

            if ($result_check->num_rows > 0) {
                $type_id = $result_check->fetch_assoc()['type_id'];
            } else {
                // Insert new type
                $stmt_type = $conn->prepare("INSERT INTO document_types (type_name) VALUES (?)");
                $stmt_type->bind_param("s", $custom_type);
                if ($stmt_type->execute()) {
                    $type_id = $conn->insert_id;
                }
            }
        }

        $is_draft = isset($_POST['save_draft']);
        // If it's a draft, status_id=1. If uploaded (but not designated), typically Approved (5) or Submitted (2)?
        // The user request: "when uploading a file it should be pending status not approve"
        // This implies non-draft uploads should start as Pending (1) or maybe Submitted (2)?
        // Or if they mean designated uploads?
        // If share_scope is individual (designated), it starts as Pending for the recipient, but what is the document status?
        // If share_scope is 'all' (public), it usually auto-approves for admins.
        // Let's assume they mean generally, status_id should be 1 (Draft/Pending) instead of 5 (Approved) initially.
        // Or maybe 2 (Submitted) if it's not a draft.
        // Let's set it to 1 (Pending/Draft) for now as requested, or 2 (Submitted) if they consider "Pending" as "Waiting for approval".
        // BUT, status_id 1 is usually "Draft". Status_id 2 is "Submitted".
        // If the user says "pending status", they might mean status_id 1 or a new status.
        // Given existing code uses status_id 5 (Approved) for non-drafts:
        // $status_id = $is_draft ? 1 : 5;
        // We should change 5 to 1 (Pending/Draft) or 2 (Submitted).
        // Let's try changing it to 1 (Draft/Pending) if that's what they mean by "Pending".
        // Or if they want a distinct "Pending" status that is not "Draft", we might need to know what ID that is.
        // Usually 1=Draft, 2=Submitted, 3=In Review, 4=For Revision, 5=Approved, 6=Rejected.
        // If they want "Pending", maybe they mean "Submitted" (2)?
        // However, "Draft" (1) is often used for "Pending" upload.
        // Let's assume they want status_id = 1 (Draft/Pending) for ALL uploads initially.
        
        $status_id = 1; // Always start as Pending/Draft (1)

        $upload_dir = '../uploads/';
        
        $share_scope = isset($_POST['share_scope']) ? $_POST['share_scope'] : 'all';

        // Handle Multiple File Upload
        if (isset($_FILES['document_file'])) {
            // Normalize files array structure
            $files = $_FILES['document_file'];
            $file_count = is_array($files['name']) ? count($files['name']) : 1;
            
            for ($i = 0; $i < $file_count; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp_name = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                
                if ($error == 0) {
                    $title = pathinfo($name, PATHINFO_FILENAME);
                    $description = isset($_POST['description']) ? trim($_POST['description']) : "";
                    $tracking_number = 'DTS-' . date('Ymd') . '-' . rand(1000, 9999) . rand(10, 99);
                    
                    // Insert Document
                    $stmt = $conn->prepare("INSERT INTO documents (tracking_number, title, description, type_id, status_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssiii", $tracking_number, $title, $description, $type_id, $status_id, $user_id);
                    
                    if ($stmt->execute()) {
                        $document_id = $conn->insert_id;
                        $file_path = $upload_dir . time() . '_' . $i . '_' . $name;
                        
                        if (move_uploaded_file($tmp_name, $file_path)) {
                            $stmt_att = $conn->prepare("INSERT INTO attachments (document_id, file_name, file_path) VALUES (?, ?, ?)");
                            $stmt_att->bind_param("iss", $document_id, $name, $file_path);
                            $stmt_att->execute();
                            
                            // Log Activity (Initial Upload)
                            if ($share_scope !== 'individual') {
                                $stmt_act_init = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'upload', ?, NOW())");
                                if ($stmt_act_init) {
                                    $init_details = "Uploaded original file: $name";
                                    $stmt_act_init->bind_param("iis", $document_id, $user_id, $init_details);
                                    $stmt_act_init->execute();
                                    $stmt_act_init->close();
                                }
                                
                                // Log Shared Activity for 'All'
                                if ($share_scope === 'all') {
                                    $stmt_act_share = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'shared', ?, NOW())");
                                    if ($stmt_act_share) {
                                        $share_details = "Shared document with all users";
                                        $stmt_act_share->bind_param("iis", $document_id, $user_id, $share_details);
                                        $stmt_act_share->execute();
                                        $stmt_act_share->close();
                                    }
                                }
                            }
                        }

                        if ($share_scope === 'individual' && isset($_POST['share_user_ids']) && is_array($_POST['share_user_ids'])) {
                            $share_users = $_POST['share_user_ids'];
                            $stmt_share = $conn->prepare("INSERT INTO document_shares (document_id, recipient_id, shared_by, created_at) VALUES (?, ?, ?, NOW())");
                            if ($stmt_share) {
                                foreach ($share_users as $recipient_id) {
                                    $recipient_id = (int)$recipient_id;
                                    if ($recipient_id > 0) {
                                        $stmt_share->bind_param("iii", $document_id, $recipient_id, $user_id);
                                        $stmt_share->execute();
                                    }
                                }
                                $stmt_share->close();
                            }
                            if (!empty($_POST['required_action'])) {
                                $ra = trim($_POST['required_action']);
                                if ($ra !== '') {
                                    $label = htmlspecialchars($ra, ENT_QUOTES, 'UTF-8');
                                    $remark_text = 'Required Action: ' . $label;
                                    $remark_stmt = $conn->prepare("INSERT INTO document_remarks (document_id, user_id, remark) VALUES (?, ?, ?)");
                                    if ($remark_stmt) {
                                        $remark_stmt->bind_param("iis", $document_id, $user_id, $remark_text);
                                        $remark_stmt->execute();
                                        $remark_stmt->close();
                                    }
                                }
                            }
                            
                            // Log Share Activity and create designated notifications
                            if (!empty($share_users)) {
                                $ids = array_map('intval', $share_users);
                                $id_list = implode(',', $ids);
                                $users_sql = "SELECT user_id, CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name) as full_name FROM users WHERE user_id IN ($id_list)";
                                $res_names = $conn->query($users_sql);

                                if ($res_names) {
                                    $stmt_act = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, recipient_id, created_at) VALUES (?, ?, 'sent', ?, ?, NOW())");
                                    while ($u_row = $res_names->fetch_assoc()) {
                                        $share_details = "Successfully sent the " . $name . " to " . $u_row['full_name'];
                                        $rid = (int)$u_row['user_id'];

                                        if ($stmt_act) {
                                            $stmt_act->bind_param("iisi", $document_id, $user_id, $share_details, $rid);
                                            $stmt_act->execute();
                                        }

                                        // Create notification for recipient
                                        createNotification(
                                            $conn,
                                            $rid,
                                            'Document shared with you',
                                            'A document has been shared with you: ' . $title,
                                            '../documents/my_documents.php?tab=designated',
                                            'info',
                                            $user_id,
                                            $document_id
                                        );
                                    }

                                    if ($stmt_act) {
                                        $stmt_act->close();
                                    }
                                }
                            }
                        }
                    }
                }
            }
            $redirect_tab = ($share_scope === 'individual') ? 'designated' : 'all';
            header("Location: my_documents.php?msg=added&tab=" . $redirect_tab);
            exit();
        }
    }

    // Edit Document
    if (isset($_POST['edit_document'])) {
        $document_id = (int)$_POST['document_id'];
        $title = $_POST['title'];
        $type_id = $_POST['type_id'];
        $description = $_POST['description'];

        if ($role === 'staff') {
            $check = $conn->prepare("SELECT created_by, status_id FROM documents WHERE document_id = ?");
            $check->bind_param("i", $document_id);
            $check->execute();
            $result = $check->get_result();
            $doc = $result ? $result->fetch_assoc() : null;

            if ($doc && (int)$doc['created_by'] === $user_id && ((int)$doc['status_id'] === 1 || (int)$doc['status_id'] === 4)) {
                $new_status = ((int)$doc['status_id'] === 4) ? 3 : 1;
                $stmt = $conn->prepare("UPDATE documents SET title = ?, type_id = ?, description = ?, status_id = ? WHERE document_id = ?");
                $stmt->bind_param("sisii", $title, $type_id, $description, $new_status, $document_id);
                $stmt->execute();

                if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
                    $file_name = $_FILES['document_file']['name'];
                    $file_tmp = $_FILES['document_file']['tmp_name'];
                    $upload_dir = '../uploads/';
                    $unique_name = time() . '_' . $file_name;
                    $file_path = $upload_dir . $unique_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $stmt_att = $conn->prepare("UPDATE attachments SET file_name = ?, file_path = ? WHERE document_id = ?");
                        $stmt_att->bind_param("ssi", $file_name, $file_path, $document_id);
                        $stmt_att->execute();

                        if ($stmt_att->affected_rows == 0) {
                            $stmt_ins = $conn->prepare("INSERT INTO attachments (document_id, file_name, file_path) VALUES (?, ?, ?)");
                            $stmt_ins->bind_param("iss", $document_id, $file_name, $file_path);
                            $stmt_ins->execute();
                        }
                    }
                }

                $action = "Edited document: $title";
                $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
                $stmt_log->bind_param("isi", $user_id, $action, $document_id);
                $stmt_log->execute();

                header("Location: my_documents.php?msg=updated");
                exit();
            }
        } else {
            $check = $conn->prepare("SELECT created_by FROM documents WHERE document_id = ?");
            $check->bind_param("i", $document_id);
            $check->execute();
            $result = $check->get_result();

            if ($result && (int)$result->fetch_assoc()['created_by'] === $user_id) {
                $stmt = $conn->prepare("UPDATE documents SET title = ?, type_id = ?, description = ? WHERE document_id = ?");
                $stmt->bind_param("sisi", $title, $type_id, $description, $document_id);
                $stmt->execute();

                if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
                    $file_name = $_FILES['document_file']['name'];
                    $file_tmp = $_FILES['document_file']['tmp_name'];
                    $upload_dir = '../uploads/';
                    $file_path = $upload_dir . time() . '_' . $file_name;

                    if (move_uploaded_file($file_tmp, $file_path)) {
                        $stmt_get_att = $conn->prepare("SELECT file_path FROM attachments WHERE document_id = ?");
                        $stmt_get_att->bind_param("i", $document_id);
                        $stmt_get_att->execute();
                        $att_res = $stmt_get_att->get_result();
                        while ($att = $att_res->fetch_assoc()) {
                            if (file_exists($att['file_path'])) {
                                unlink($att['file_path']);
                            }
                        }
                        $conn->query("DELETE FROM attachments WHERE document_id = $document_id");

                        $stmt_att = $conn->prepare("INSERT INTO attachments (document_id, file_name, file_path) VALUES (?, ?, ?)");
                        $stmt_att->bind_param("iss", $document_id, $file_name, $file_path);
                        $stmt_att->execute();

                        $stmt_act_upd = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, created_at) VALUES (?, ?, 'upload', ?, NOW())");
                        if ($stmt_act_upd) {
                            $upd_details = "Updated file (replaced): $file_name";
                            $stmt_act_upd->bind_param("iis", $document_id, $user_id, $upd_details);
                            $stmt_act_upd->execute();
                            $stmt_act_upd->close();
                        }
                    }
                }

                header("Location: my_documents.php?msg=updated");
                exit();
            }
        }
    }

    // Delete Document
    if (isset($_POST['delete_document'])) {
        $document_id = (int)$_POST['document_id'];
        
        // Verify ownership
        $check = $conn->prepare("SELECT created_by, title FROM documents WHERE document_id = ? LIMIT 1");
        if ($check) {
            $check->bind_param("i", $document_id);
            $check->execute();
            $result = $check->get_result();
            $row = $result ? $result->fetch_assoc() : null;
            $check->close();

            if ($row && (int)$row['created_by'] === $user_id) {
                if (delete_document_by_id($conn, $document_id)) {
                    $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
                    if ($stmt_log) {
                        $action = "Deleted document: " . $row['title'];
                        $stmt_log->bind_param("isi", $user_id, $action, $document_id);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                }

                header("Location: my_documents.php?msg=deleted");
                exit();
            }
        }
    }

    // Bulk Submit
    if (isset($_POST['bulk_submit']) && isset($_POST['selected_documents'])) {
        $selected_ids = $_POST['selected_documents'];
        $count = 0;
        
        $bulk_status_id = ($role === 'superadmin') ? 5 : 2;
        $stmt_check = $conn->prepare("SELECT created_by, status_id, title FROM documents WHERE document_id = ?");
        $stmt_update = $conn->prepare("UPDATE documents SET status_id = ? WHERE document_id = ?");
        $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");

        foreach ($selected_ids as $doc_id) {
            $stmt_check->bind_param("i", $doc_id);
            $stmt_check->execute();
            $res = $stmt_check->get_result();
            $doc = $res->fetch_assoc();

            if ($doc && $doc['created_by'] == $user_id && $doc['status_id'] == 1) {
                $stmt_update->bind_param("ii", $bulk_status_id, $doc_id);
                if ($stmt_update->execute()) {
                    $action = ($bulk_status_id === 5 ? "Bulk submitted (approved) document: " : "Bulk submitted document: ") . $doc['title'];
                    $stmt_log->bind_param("isi", $user_id, $action, $doc_id);
                    $stmt_log->execute();
                    $count++;
                }
            }
        }
        header("Location: my_documents.php?msg=bulk_submitted&count=$count");
        exit();
    }

    // Bulk Delete
    if (isset($_POST['bulk_delete']) && isset($_POST['selected_documents'])) {
        $selected_ids = $_POST['selected_documents'];
        $count = 0;

        $stmt_check = $conn->prepare("SELECT created_by, status_id, title FROM documents WHERE document_id = ?");
        $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action) VALUES (?, ?)");

        foreach ($selected_ids as $doc_id) {
            $doc_id = (int)$doc_id;
            if ($doc_id <= 0) {
                continue;
            }

            if (!$stmt_check) {
                continue;
            }

            $stmt_check->bind_param("i", $doc_id);
            $stmt_check->execute();
            $res = $stmt_check->get_result();
            $doc = $res ? $res->fetch_assoc() : null;

            if ($doc && (int)$doc['created_by'] === $user_id && (int)$doc['status_id'] === 1) {
                if (delete_document_by_id($conn, $doc_id)) {
                    if ($stmt_log) {
                        $action = "Bulk deleted document: " . $doc['title'];
                        $stmt_log->bind_param("is", $user_id, $action);
                        $stmt_log->execute();
                    }
                    $count++;
                }
            }
        }

        if ($stmt_check) {
            $stmt_check->close();
        }
        if ($stmt_log) {
            $stmt_log->close();
        }

        header("Location: my_documents.php?msg=bulk_deleted&count=$count");
        exit();
    }

    // Submit Document
    if (isset($_POST['submit_document'])) {
        $document_id = (int)$_POST['document_id'];

        if ($role === 'staff') {
            $check = $conn->prepare("SELECT created_by, status_id, title, tracking_number FROM documents WHERE document_id = ?");
            $check->bind_param("i", $document_id);
            $check->execute();
            $result = $check->get_result();
            $doc = $result ? $result->fetch_assoc() : null;

            if ($doc && (int)$doc['created_by'] === $user_id && (int)$doc['status_id'] === 1) {
                $stmt = $conn->prepare("UPDATE documents SET status_id = 3 WHERE document_id = ?");
                $stmt->bind_param("i", $document_id);
                $stmt->execute();

                $action = "Submitted document for review: " . $doc['title'] . " (" . $doc['tracking_number'] . ")";
                $stmt_log = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
                $stmt_log->bind_param("isi", $user_id, $action, $document_id);
                $stmt_log->execute();

                header("Location: my_documents.php?msg=submitted");
                exit();
            }
        } else {
            $check = $conn->prepare("SELECT created_by, status_id FROM documents WHERE document_id = ?");
            $check->bind_param("i", $document_id);
            $check->execute();
            $result = $check->get_result();
            $row = $result ? $result->fetch_assoc() : null;

            if ($row && (int)$row['created_by'] === $user_id && (int)$row['status_id'] === 1) {
                $new_status = 5;

                $stmt = $conn->prepare("UPDATE documents SET status_id = ? WHERE document_id = ?");
                $stmt->bind_param("ii", $new_status, $document_id);
                $stmt->execute();

                $msg = ($new_status == 5) ? "approved" : "submitted";
                header("Location: my_documents.php?msg=" . $msg);
                exit();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - DTS</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/custom.css">
</head>
<body>

    <?php
    include __DIR__ . '/../includes/sidebar.php';
    ?>

    <!-- Main Content -->
    <main class="main-content">
        <?php include __DIR__ . '/../includes/navbar.php'; ?>

        <?php
        $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
        $user_id = $_SESSION['user_id'];
        ?>

        <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show mx-4 mt-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php
            if ($_GET['msg'] == 'added') echo "Document uploaded successfully!";
            elseif ($_GET['msg'] == 'updated') echo "Document updated successfully!";
            elseif ($_GET['msg'] == 'deleted') echo "Document deleted successfully!";
            elseif ($_GET['msg'] == 'submitted') echo "Document submitted successfully!";
            elseif ($_GET['msg'] == 'approved') echo "Document approved successfully!";
            elseif ($_GET['msg'] == 'bulk_submitted') echo "Selected documents submitted successfully!";
            elseif ($_GET['msg'] == 'bulk_deleted') echo "Selected documents deleted successfully!";
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Controls Card -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0 fw-bold"><?php echo ($current_tab === 'designated') ? 'Designated Files' : 'My Documents'; ?></h5>
                    <small class="text-muted">Showing <?php echo ($current_tab === 'designated') ? 'files you shared' : 'your own files'; ?></small>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Tabs Navigation -->
                    <ul class="nav nav-pills nav-fill bg-light p-1 rounded-pill mb-0" style="max-width: 400px; font-size: 0.85rem;">
                        <li class="nav-item">
                            <a class="nav-link rounded-pill py-1 <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'all') ? 'active' : ''; ?>" href="?tab=all">
                                <i class="bi bi-folder2-open me-1"></i>My Documents
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link rounded-pill py-1 <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'designated') ? 'active' : ''; ?>" href="?tab=designated">
                                <i class="bi bi-person-check me-1"></i>Designated Files
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex gap-2 justify-content-center flex-wrap my-doc-actions">
                        <div class="input-group input-group-sm" style="width: 250px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-secondary"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" placeholder="Search my files..." id="searchInput">
                        </div>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm active" id="listViewBtn" onclick="toggleView('list')" title="List View">
                                <i class="bi bi-list-ul"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="gridViewBtn" onclick="toggleView('grid')" title="Grid View">
                                <i class="bi bi-grid-fill"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm d-flex align-items-center" id="selectMultipleBtn" onclick="toggleSelectionMode()">
                            <i class="bi bi-check2-square me-2"></i>
                            <span>Select Multiple</span>
                        </button>
                        <button class="btn btn-primary btn-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#shareScopeModal">
                            <i class="bi bi-cloud-upload me-2"></i>
                            <span>Upload Document</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Topbar -->
        <section class="secondary-topbar mb-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="breadcrumb-wrapper">
                    <span class="breadcrumb-item">Home</span>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-item">Documents</span>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-item active"><?php echo ($current_tab === 'designated') ? 'Designated Files' : 'My Documents'; ?></span>
                </div>
                <div class="topbar-action-buttons d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="toggleSelectionMode()">Select Multiple</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm">Export</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#shareScopeModal">Upload Document</button>
                    <button type="button" class="btn btn-warning btn-sm text-white">+ New Document</button>
                </div>
            </div>
        </section>

        <?php
        // Base Query
        $query = "SELECT d.*, dt.type_name, ds.status_name, a.file_name, a.file_path, u.username as created_by_user,
                  (SELECT remark FROM document_remarks WHERE document_id = d.document_id ORDER BY created_at DESC LIMIT 1) as latest_remark
                  FROM documents d 
                  LEFT JOIN document_types dt ON d.type_id = dt.type_id 
                  LEFT JOIN document_status ds ON d.status_id = ds.status_id 
                  LEFT JOIN attachments a ON d.document_id = a.document_id 
                  LEFT JOIN users u ON d.created_by = u.user_id";

        if ($current_tab == 'designated') {
            // Designated Files (Files shared BY current user - Outgoing)
            $query = "SELECT d.*, dt.type_name, ds.status_name, a.file_name, a.file_path, u.username as created_by_user,
                      sh.recipient_id as recipient_id,
                      CONCAT(recipient.first_name, ' ', recipient.last_name) as recipient_full_name,
                      sh.created_at as shared_at,
                      (SELECT remark FROM document_remarks WHERE document_id = d.document_id ORDER BY created_at DESC LIMIT 1) as latest_remark,
                      (SELECT COUNT(*) FROM document_activity_log dal WHERE dal.document_id = d.document_id AND dal.user_id = sh.recipient_id AND dal.activity_type = 'finished') as is_finished_by_recipient,
                      (SELECT dal2.details FROM document_activity_log dal2 WHERE dal2.document_id = d.document_id AND dal2.user_id = sh.recipient_id AND dal2.activity_type = 'finished' ORDER BY dal2.created_at DESC LIMIT 1) as review_remarks,
                      (SELECT dal3.created_at FROM document_activity_log dal3 WHERE dal3.document_id = d.document_id AND dal3.user_id = sh.recipient_id AND dal3.activity_type = 'finished' ORDER BY dal3.created_at DESC LIMIT 1) as review_at
                      FROM documents d 
                      LEFT JOIN document_types dt ON d.type_id = dt.type_id 
                      LEFT JOIN document_status ds ON d.status_id = ds.status_id 
                      LEFT JOIN attachments a ON d.document_id = a.document_id 
                      LEFT JOIN users u ON d.created_by = u.user_id
                      JOIN document_shares sh ON d.document_id = sh.document_id 
                      LEFT JOIN users recipient ON sh.recipient_id = recipient.user_id
                      WHERE sh.shared_by = ? ORDER BY sh.created_at DESC";
        } else {
            // Default: All My Documents (exclude individually shared uploads)
            $query .= " WHERE d.created_by = ?
                        AND NOT EXISTS (
                            SELECT 1 FROM document_shares sh 
                            WHERE sh.document_id = d.document_id AND sh.shared_by = ?
                        )
                        ORDER BY d.created_at DESC";
        }

        $stmt = $conn->prepare($query);
        if (!$stmt) {
            die("Prepare failed: " . $conn->error);
        }
        if ($current_tab == 'designated') {
            $stmt->bind_param("i", $user_id);
        } else {
            $stmt->bind_param("ii", $user_id, $user_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $grouped_docs = [];
        $has_docs = false;
        if ($result->num_rows > 0) {
            $has_docs = true;
            while ($row = $result->fetch_assoc()) {
                if ($current_tab == 'designated') {
                    // For designated files, use custom status logic for grouping
                    if ((int)$row['status_id'] === 4) {
                        // For Revision
                        $grouped_docs[4][] = $row;
                    } elseif ((int)$row['status_id'] === 5) {
                        $grouped_docs[5][] = $row; // Approved
                    } elseif ((int)$row['status_id'] === 6) {
                        $grouped_docs[6][] = $row; // Rejected
                    } elseif (isset($row['is_finished_by_recipient']) && $row['is_finished_by_recipient'] > 0) {
                        // In Review (finished by recipient)
                        $grouped_docs[3][] = $row;
                    } else {
                        // Pending
                        $grouped_docs[10][] = $row;
                    }
                } else {
                    $grouped_docs[$row['status_id']][] = $row;
                }
            }
        }

        // Define Status Groups Order & Styling
        $status_groups = [
            4 => ['label' => 'Returned for Revision', 'icon' => 'bi-exclamation-circle', 'color' => 'text-warning', 'bg' => 'bg-warning-subtle'],
            1 => ['label' => 'Drafts', 'icon' => 'bi-pencil-square', 'color' => 'text-secondary', 'bg' => 'bg-light'],
            2 => ['label' => 'Submitted', 'icon' => 'bi-send', 'color' => 'text-primary', 'bg' => 'bg-primary-subtle'],
            3 => ['label' => 'In Review', 'icon' => 'bi-hourglass-split', 'color' => 'text-info', 'bg' => 'bg-info-subtle'],
            5 => ['label' => 'Approved', 'icon' => 'bi-check-circle', 'color' => 'text-success', 'bg' => 'bg-success-subtle'],
            6 => ['label' => 'Rejected', 'icon' => 'bi-x-circle', 'color' => 'text-danger', 'bg' => 'bg-danger-subtle'],
            // Virtual groups for designated
            10 => ['label' => 'Pending', 'icon' => 'bi-clock', 'color' => 'text-secondary', 'bg' => 'bg-secondary-subtle'],
            // 11 (In Review) maps to 3 visually, but we can separate if needed. 
            // The user asked to separate based on badge.
            // "In Review" badge matches group 3.
            // "For Revision" badge matches group 4.
            // "Approved" badge matches group 5.
            // "Pending" badge is new.
        ];
        ?>

        <!-- List View -->
        <div id="listViewContainer">
            <?php
            if (!$has_docs) {
                echo '<div class="text-center py-5 text-secondary"><i class="bi bi-folder2-open display-4 d-block mb-3 opacity-25"></i><p class="mb-0 fs-5">You haven\'t uploaded any documents yet.</p></div>';
            } else {
                foreach ($status_groups as $status_id => $group) {
                    if (empty($grouped_docs[$status_id])) continue;
                    
                    echo '<div class="status-group-list mb-4">';
                    echo '<div class="d-flex align-items-center mb-3 px-1">';
                    echo '<h6 class="mb-0 fw-bold ' . $group['color'] . ' text-uppercase small ls-1"><i class="bi ' . $group['icon'] . ' me-2"></i>' . $group['label'] . '</h6>';
                    echo '<span class="badge ' . $group['bg'] . ' ' . $group['color'] . ' ms-2 rounded-pill">' . count($grouped_docs[$status_id]) . '</span>';
                    echo '</div>';
                    
                    echo '<div class="card border-0 shadow-sm overflow-hidden rounded-4">';
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-hover align-middle mb-0" style="border-collapse: separate; border-spacing: 0;">';
                    echo '<thead style="background-color: skyblue !important;" class="text-dark">';
                    echo '<tr>';
                    echo '<th class="ps-4 py-3 select-checkbox-col d-none border-bottom" style="width: 40px;">';
                    if ($status_id == 1) {
                         echo '<input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)">';
                    }
                    echo '</th>';
                    echo '<th class="ps-4 py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 15%;">Tracking No.</th>';
                    echo '<th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 35%;">Document</th>';
                    echo '<th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 15%;">Status</th>';
                    echo '<th class="py-3 text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 15%;">Date</th>';
                    echo '<th class="pe-4 py-3 text-end text-uppercase fw-bold small border-bottom" style="letter-spacing: 1px; width: 20%;">Actions</th>';
                    echo '</tr></thead><tbody>';
                    
                    foreach ($grouped_docs[$status_id] as $row) {
                        $file_ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                        $icon_class = 'bi-file-earmark';
                        $icon_color = 'text-secondary';
                        
                        switch ($file_ext) {
                            case 'pdf': $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; break;
                            case 'doc': case 'docx': $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; break;
                            case 'xls': case 'xlsx': case 'csv': $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; break;
                            case 'ppt': case 'pptx': $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; break;
                            case 'jpg': case 'jpeg': case 'png': case 'gif': $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; break;
                            case 'zip': case 'rar': $icon_class = 'bi-file-earmark-zip'; $icon_color = 'text-dark'; break;
                            default:
                                // Fallback to type name check
                                $type_lower = strtolower($row['type_name'] ?? '');
                                if (strpos($type_lower, 'pdf') !== false) { $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; }
                                elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) { $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; }
                                elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'csv') !== false) { $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; }
                                elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'photo') !== false) { $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; }
                                elseif (strpos($type_lower, 'powerpoint') !== false || strpos($type_lower, 'ppt') !== false) { $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; }
                                break;
                        }

                        $status_badge = '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-medium border border-secondary-subtle">Draft</span>';
                        if ($row['status_id'] == 2) $status_badge = '<span class="badge bg-primary-subtle text-primary rounded-pill px-3 py-2 fw-medium border border-primary-subtle">Submitted</span>';
                        elseif ($row['status_id'] == 3) $status_badge = '<span class="badge bg-info-subtle text-info rounded-pill px-3 py-2 fw-medium border border-info-subtle">In Review</span>';
                        elseif ($row['status_id'] == 4) $status_badge = '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2 fw-medium border border-warning-subtle">For Revision</span>';
                        
                        if ($current_tab == 'designated') {
                            if ((int)$row['status_id'] === 4) {
                                $status_badge = '<span class="badge bg-warning-subtle text-warning rounded-pill px-3 py-2 fw-medium border border-warning-subtle">For Revision</span>';
                            } elseif ((int)$row['status_id'] === 5) {
                                $status_badge = '<span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-medium border border-success-subtle">Approved</span>';
                            } elseif (isset($row['is_finished_by_recipient']) && $row['is_finished_by_recipient'] > 0) {
                                $status_badge = '<span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-medium border border-warning-subtle">In Review</span>';
                            } else {
                                $status_badge = '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2 fw-medium border border-secondary-subtle">Pending</span>';
                            }
                        } elseif ($row['status_id'] == 5) {
                            $status_badge = '<span class="badge bg-success-subtle text-success rounded-pill px-3 py-2 fw-medium border border-success-subtle">Approved</span>';
                        }
                        
                        if ($row['status_id'] == 6) $status_badge = '<span class="badge bg-danger-subtle text-danger rounded-pill px-3 py-2 fw-medium border border-danger-subtle">Rejected</span>';

                        $date_uploaded = date('M d, Y', strtotime($row['created_at']));
                        $view_data = [
                            'document_id' => $row['document_id'],
                            'tracking_number' => $row['tracking_number'],
                            'title' => $row['title'],
                            'type_name' => $row['type_name'],
                            'status_name' => $row['status_name'],
                            'status_id' => $row['status_id'],
                            'description' => $row['description'],
                            'date_uploaded' => $date_uploaded,
                            'file_name' => $row['file_name'],
                            'file_path' => $row['file_path'],
                            'created_by_user' => $row['created_by_user'] ?? 'Unknown',
                            'recipient_full_name' => $row['recipient_full_name'] ?? null,
                            'recipient_id' => $row['recipient_id'] ?? null,
                            'is_finished_by_recipient' => $row['is_finished_by_recipient'] ?? 0,
                            'shared_at' => $row['shared_at'] ?? null,
                            'review_remarks' => $row['review_remarks'] ?? null,
                            'review_at' => $row['review_at'] ?? null
                        ];
                        $json_data = json_encode($view_data, JSON_HEX_APOS | JSON_HEX_QUOT);

                        echo '<tr class="align-middle position-relative hover-shadow transition-all" style="cursor: pointer; transition: all 0.2s ease;">';
                        echo '<td class="ps-4 select-checkbox-col d-none border-bottom-0" onclick="event.stopPropagation()">';
                        if ($row['status_id'] == 1) {
                            echo '<input type="checkbox" class="form-check-input doc-checkbox rounded-circle" style="width: 1.2em; height: 1.2em;" value="'.$row['document_id'].'" onchange="updateSelectedCount()">';
                        }
                        echo '</td>';
                        echo '<td onclick=\'openViewModal('.$json_data.')\' class="ps-4 border-bottom-0"><span class="font-monospace text-secondary fw-semibold">'.htmlspecialchars($row['tracking_number']).'</span></td>';
                        echo '<td onclick=\'openViewModal('.$json_data.')\' class="border-bottom-0">
                                <div class="d-flex align-items-center py-2">
                                    <div class="rounded-3 bg-light d-flex align-items-center justify-content-center shadow-sm border" style="width: 48px; height: 48px; min-width: 48px;">
                                        <i class="bi '.$icon_class.' fs-4 '.$icon_color.'"></i>
                                    </div>
                                    <div class="ms-3" style="min-width: 0;">
                                        <div class="fw-bold text-dark text-truncate mb-1" style="max-width: 300px; font-size: 1rem;">'.htmlspecialchars($row['title']).'</div>';

                        if ($current_tab == 'designated' && !empty($row['recipient_full_name'])) {
                            echo '<div class="text-muted small fw-normal mb-1"><i class="bi bi-arrow-right-short"></i> Sent to: ' . htmlspecialchars($row['recipient_full_name']) . '</div>';
                        }

                        echo '          <div class="small text-muted d-flex align-items-center">
                                            <i class="bi bi-file-earmark-text me-1"></i>
                                            '.htmlspecialchars($row['type_name']).'
                                        </div>
                                    </div>
                                </div>
                              </td>';
                        echo '<td onclick=\'openViewModal('.$json_data.')\' class="border-bottom-0">'.$status_badge.'</td>';
                        echo '<td onclick=\'openViewModal('.$json_data.')\' class="text-secondary fw-medium border-bottom-0">'.$date_uploaded.'</td>';
                        echo '<td class="pe-4 text-end border-bottom-0" onclick="event.stopPropagation()">
                                <div class="d-flex justify-content-end gap-2">';
                        
                        echo '<button class="btn btn-light btn-sm text-primary rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" title="Download" data-bs-toggle="tooltip" onclick="event.stopPropagation(); confirmDownload('.$row['document_id'].')"><i class="bi bi-download"></i></button>';
                        
                        if ($current_tab != 'designated' && $row['created_by'] == $user_id && $row['status_id'] == 1) {
                            echo '<button class="btn btn-light btn-sm text-success rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" onclick="confirmSubmit('.$row['document_id'].')" title="Submit" data-bs-toggle="tooltip"><i class="bi bi-send-fill"></i></button>';
                            echo '<button class="btn btn-light btn-sm text-secondary rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" data-bs-toggle="modal" data-bs-target="#editModal" data-id="'.$row['document_id'].'" data-title="'.htmlspecialchars($row['title']).'" data-type="'.$row['type_id'].'" data-desc="'.htmlspecialchars($row['description']).'" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil-fill"></i></button>';
                            echo '<button class="btn btn-light btn-sm text-danger rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" onclick="confirmDelete('.$row['document_id'].')" title="Delete" data-bs-toggle="tooltip"><i class="bi bi-trash-fill"></i></button>';
                        } elseif ($current_tab == 'designated' && $row['created_by'] == $user_id) {
                             // Only show delete button for designated files if needed, or nothing. 
                             // User asked to remove edit and submit buttons.
                             // Assuming delete button is still allowed or should be removed too?
                             // "at designated files remove the edit and submit button"
                             // Let's keep Delete button if status allows, or maybe just remove edit/submit as requested.
                             // For designated files, they might want to delete if sent by mistake.
                             if ($row['status_id'] == 1) {
                                echo '<button class="btn btn-light btn-sm text-danger rounded-circle shadow-sm hover-scale" style="width: 32px; height: 32px;" onclick="confirmDelete('.$row['document_id'].')" title="Delete" data-bs-toggle="tooltip"><i class="bi bi-trash-fill"></i></button>';
                             }
                        }
                        
                        echo '</div></td></tr>';
                    }
                    echo '</tbody></table></div></div></div>';
                }
            }
            ?>
        </div>

        <!-- Grid View -->
        <div id="gridViewContainer" class="d-none">
            <?php
            if ($has_docs) {
                foreach ($status_groups as $status_id => $group) {
                    if (empty($grouped_docs[$status_id])) continue;
                    
                    echo '<div class="status-group-grid mb-4">';
                    echo '<div class="d-flex align-items-center mb-3 pb-2 border-bottom">';
                    echo '<i class="bi ' . $group['icon'] . ' ' . $group['color'] . ' me-2 fs-4"></i>';
                    echo '<h5 class="mb-0 fw-bold ' . $group['color'] . '">' . $group['label'] . '</h5>';
                    echo '<span class="badge ' . $group['bg'] . ' ' . $group['color'] . ' ms-2">' . count($grouped_docs[$status_id]) . '</span>';
                    if ($status_id == 1) {
                            echo '<div class="ms-auto select-checkbox-grid d-none position-static transform-none"><div class="form-check"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"><label class="form-check-label small ms-1">Select All</label></div></div>';
                    }
                    echo '</div>';
                    
                    echo '<div class="row">';
                    foreach ($grouped_docs[$status_id] as $row) {
                        $icon_class = 'bi-file-earmark'; $icon_color = 'text-secondary';
                        $file_ext = strtolower(pathinfo($row['file_name'], PATHINFO_EXTENSION));
                        switch ($file_ext) {
                            case 'pdf': $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; break;
                            case 'doc': case 'docx': $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; break;
                            case 'xls': case 'xlsx': case 'csv': $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; break;
                            case 'ppt': case 'pptx': $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; break;
                            case 'jpg': case 'jpeg': case 'png': case 'gif': $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; break;
                            case 'zip': case 'rar': $icon_class = 'bi-file-earmark-zip'; $icon_color = 'text-dark'; break;
                            default:
                                // Fallback to type name check
                                $type_lower = strtolower($row['type_name'] ?? '');
                                if (strpos($type_lower, 'pdf') !== false) { $icon_class = 'bi-file-earmark-pdf'; $icon_color = 'text-danger'; }
                                elseif (strpos($type_lower, 'word') !== false || strpos($type_lower, 'doc') !== false) { $icon_class = 'bi-file-earmark-word'; $icon_color = 'text-primary'; }
                                elseif (strpos($type_lower, 'excel') !== false || strpos($type_lower, 'sheet') !== false || strpos($type_lower, 'csv') !== false) { $icon_class = 'bi-file-earmark-excel'; $icon_color = 'text-success'; }
                                elseif (strpos($type_lower, 'image') !== false || strpos($type_lower, 'photo') !== false) { $icon_class = 'bi-file-earmark-image'; $icon_color = 'text-info'; }
                                elseif (strpos($type_lower, 'powerpoint') !== false || strpos($type_lower, 'ppt') !== false) { $icon_class = 'bi-file-earmark-ppt'; $icon_color = 'text-warning'; }
                                break;
                        }

                        $status_badge = '';
                        switch ($row['status_id']) {
                            case 1: $status_badge = '<span class="status-badge status-draft">Draft</span>'; break;
                            case 2: $status_badge = '<span class="status-badge status-submitted">Submitted</span>'; break;
                            case 3: $status_badge = '<span class="status-badge status-received">In Review</span>'; break;
                            case 4: $status_badge = '<span class="status-badge status-forwarded">For Revision</span>'; break;
                            case 5:
                                    $status_badge = '<span class="status-badge status-approved">Approved</span>';
                                    break;
                                case 6:
                                    $status_badge = '<span class="status-badge status-rejected">Rejected</span>';
                                    break;
                                default:
                                    $status_badge = '<span class="status-badge bg-light text-dark">' . htmlspecialchars($row['status_name']) . '</span>';
                            }
                            
                            if ($current_tab == 'designated') {
                                 if ((int)$row['status_id'] === 4) {
                                     $status_badge = '<span class="status-badge status-forwarded">For Revision</span>';
                                 } elseif ((int)$row['status_id'] === 5) {
                                     $status_badge = '<span class="status-badge status-approved">Approved</span>';
                                 } elseif (isset($row['is_finished_by_recipient']) && $row['is_finished_by_recipient'] > 0) {
                                     $status_badge = '<span class="status-badge bg-warning text-dark">In Review</span>';
                                 } else {
                                     $status_badge = '<span class="status-badge bg-secondary text-white">Pending</span>';
                                 }
                            }
                        
                        $date_uploaded = date('M d, Y', strtotime($row['created_at']));
                        $view_data = [
                            'document_id' => $row['document_id'],
                            'tracking_number' => $row['tracking_number'],
                            'title' => $row['title'],
                            'type_name' => $row['type_name'],
                            'status_name' => $row['status_name'],
                            'status_id' => $row['status_id'],
                            'description' => $row['description'],
                            'date_uploaded' => $date_uploaded,
                            'file_name' => $row['file_name'],
                            'file_path' => $row['file_path'],
                            'created_by_user' => $row['created_by_user'] ?? 'Superadmin',
                            'recipient_full_name' => $row['recipient_full_name'] ?? null,
                            'recipient_id' => $row['recipient_id'] ?? null,
                            'is_finished_by_recipient' => $row['is_finished_by_recipient'] ?? 0,
                            'latest_remark' => $row['latest_remark'] ?? null,
                            'shared_at' => $row['shared_at'] ?? null,
                            'review_remarks' => $row['review_remarks'] ?? null,
                            'review_at' => $row['review_at'] ?? null
                        ];
                        $json_data = json_encode($view_data, JSON_HEX_APOS | JSON_HEX_QUOT);

                        echo '<div class="col-xl-2 col-lg-3 col-md-4 col-sm-6 mb-2 grid-item">';
                        echo '<div class="card h-100 shadow-sm border-0 position-relative" onclick=\'openViewModal('.$json_data.')\' style="cursor: pointer;">';
                        if ($row['status_id'] == 1) {
                            echo '<input type="checkbox" class="form-check-input select-checkbox-grid d-none doc-checkbox" value="'.$row['document_id'].'" onchange="updateSelectedCount()" onclick="event.stopPropagation()">';
                        }
                        echo '<div class="card-body text-center p-2">';
                        echo '<div class="position-absolute top-0 end-0 m-2">'.$status_badge.'</div>';
                        echo '<div class="mt-1 mb-1"><i class="bi '.$icon_class.' display-4 '.$icon_color.'"></i></div>';
                        echo '<h6 class="card-title text-truncate fw-bold mb-1" title="'.htmlspecialchars($row['title']).'">'.htmlspecialchars($row['title']).'</h6>';
                        echo '<small class="text-muted d-block mb-2">'.htmlspecialchars($row['tracking_number']).'</small>';
                        echo '<div class="d-flex justify-content-center gap-1 mt-2">';
                        
                        echo '<button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); confirmDownload('.$row['document_id'].')" title="Download"><i class="bi bi-download"></i></button>';
                        
                        if ($current_tab != 'designated' && $row['created_by'] == $user_id && $row['status_id'] == 1) {
                            echo '<button class="btn btn-sm btn-success" onclick="event.stopPropagation(); confirmSubmit('.$row['document_id'].')" title="Submit"><i class="bi bi-send"></i></button>';
                            echo '<button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation()" data-bs-toggle="modal" data-bs-target="#editModal" data-id="'.$row['document_id'].'" data-title="'.htmlspecialchars($row['title']).'" data-type="'.$row['type_id'].'" data-desc="'.htmlspecialchars($row['description']).'"><i class="bi bi-pencil"></i></button>';
                            echo '<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); confirmDelete('.$row['document_id'].')"><i class="bi bi-trash"></i></button>';
                        } elseif ($current_tab == 'designated' && $row['created_by'] == $user_id) {
                            if ($row['status_id'] == 1) {
                                echo '<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); confirmDelete('.$row['document_id'].')"><i class="bi bi-trash"></i></button>';
                            }
                        }
                        
                        echo '</div></div></div></div>';
                    }
                    echo '</div></div>';
                }
            } else {
                echo '<div class="col-12 text-center py-5 text-secondary"><i class="bi bi-folder2-open display-4 d-block mb-3 opacity-50"></i><p class="mb-0">You haven\'t uploaded any documents yet.</p></div>';
            }
            ?>
        </div>

    </main>

    <!-- View Document Modal -->
    <div class="modal" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg overflow-hidden">
                <div class="modal-header bg-primary text-white py-3 px-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="view_modal_title">
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
                                        <!-- Icon injected here -->
                                    </div>
                                    
                                    <div class="bg-white rounded-4 border shadow-sm p-3 text-start">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="text-secondary small fw-bold text-uppercase" style="letter-spacing: .5px;">Status</div>
                                            <i class="bi bi-info-circle text-secondary"></i>
                                        </div>
                                        <div id="view_status_container" class="mb-3"></div>

                                        <div id="view_recipient_container" class="mb-3 d-none">
                                            <div class="text-secondary small fw-bold text-uppercase mb-1" style="letter-spacing: .5px;">Sent To</div>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 28px; height: 28px; font-size: 13px;">
                                                    <i class="bi bi-person-check-fill"></i>
                                                </div>
                                                <span id="view_recipient" class="text-dark fw-semibold text-truncate" style="max-width: 100%;">Recipient Name</span>
                                            </div>
                                        </div>

                                        <div id="view_shared_at_container" class="mb-3 d-none">
                                            <div class="text-secondary small fw-bold text-uppercase mb-1" style="letter-spacing: .5px;">Sent At</div>
                                            <div id="view_shared_at" class="text-dark fw-semibold"></div>
                                        </div>

                                        <div class="border-top pt-3">
                                            <div class="text-secondary small fw-bold text-uppercase mb-1" style="letter-spacing: .5px;">File</div>
                                            <div id="view_file_name" class="text-dark fw-semibold text-truncate"></div>
                                        </div>

                                        <div class="view_review_actions d-none mt-3">
                                            <div class="d-grid gap-2">
                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="document_id" class="view-review-document-id">
                                                    <input type="hidden" name="recipient_id" class="view-review-recipient-id">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" name="designated_review" value="1" class="btn btn-success btn-sm shadow-sm px-3 w-100">
                                                        <i class="bi bi-check2-circle me-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="m-0">
                                                    <input type="hidden" name="document_id" class="view-review-document-id">
                                                    <input type="hidden" name="recipient_id" class="view-review-recipient-id">
                                                    <input type="hidden" name="action" value="revision">
                                                    <button type="submit" name="designated_review" value="1" class="btn btn-warning btn-sm shadow-sm px-3 text-dark w-100">
                                                        <i class="bi bi-arrow-counterclockwise me-1"></i>Need Revision
                                                    </button>
                                                </form>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2 mt-3">
                                            <button type="button" class="btn btn-outline-primary btn-sm shadow-sm bg-white text-primary w-100" id="view_history_btn" onclick="openActivityModalFromView()">
                                                <i class="bi bi-clock-history me-1"></i>File History Log
                                            </button>
                                            <a href="#" id="view_download_btn" download class="btn btn-outline-secondary btn-sm shadow-sm bg-white text-dark w-100">
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
                                <h4 id="view_title" class="fw-bold text-dark mb-0 display-6" style="font-size: 1.75rem;">Document Title</h4>
                            </div>

                            <div class="p-4">
                                <div class="mb-4">
                                    <label class="text-secondary text-uppercase small fw-bold mb-2">
                                        <i class="bi bi-card-text me-1"></i>Description / Remarks
                                    </label>
                                    <div class="bg-light-subtle p-3 rounded border border-secondary-subtle">
                                        <p id="view_desc" class="text-dark mb-0" style="white-space: pre-wrap; font-size: 1rem; line-height: 1.6;">No description available.</p>
                                    </div>
                                </div>

                                <div id="view_review_remarks_container" class="mb-4 d-none">
                                    <label class="text-secondary text-uppercase small fw-bold mb-2">
                                        <i class="bi bi-chat-left-text me-1"></i>Receiver Feedback
                                    </label>
                                    <div class="bg-warning-subtle p-3 rounded border border-warning-subtle">
                                        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                                            <div class="text-dark fw-semibold">
                                                <span class="text-secondary fw-bold">Reviewer:</span>
                                                <span id="view_review_by"></span>
                                            </div>
                                            <div class="text-dark fw-semibold">
                                                <span class="text-secondary fw-bold">Date/Time:</span>
                                                <span id="view_review_at"></span>
                                            </div>
                                        </div>
                                        <p id="view_review_remarks" class="text-dark mb-0" style="white-space: pre-wrap; font-size: 1rem; line-height: 1.6;"></p>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Tracking Number</label>
                                            <p id="view_tracking" class="font-monospace fw-semibold text-primary mb-0" style="font-size: 0.95rem;">TRACK-000</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Type</label>
                                            <p id="view_type" class="fw-semibold mb-0 text-dark" style="font-size: 0.95rem;">Report</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1 d-block">Date Created</label>
                                            <p id="view_date" class="text-dark mb-0 fw-semibold" style="font-size: 0.95rem;">Jan 01, 2024</p>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-3 border rounded h-100">
                                            <label class="text-secondary text-uppercase small fw-bold mb-1 d-block" id="view_created_by_label">Created By</label>
                                            <div class="d-flex align-items-center mt-1">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2 shadow-sm" style="width: 32px; height: 32px; font-size: 14px;">
                                                    <i class="bi bi-person-fill"></i>
                                                </div>
                                                <span id="view_created_by" class="text-dark fw-semibold" style="font-size: 0.95rem;">User</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Confirmation Modal -->
    <div class="modal fade" id="submitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Submit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit this document? Once submitted, you cannot edit or delete it until it is returned for revision.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="document_id" id="submit_document_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="submit_document" class="btn btn-success">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Scope Modal -->
    <div class="modal fade" id="shareScopeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share Document To</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="card border share-option-card share-option-card-selected h-100" data-value="all">
                                <div class="card-body d-flex align-items-center">
                                    <div class="share-option-icon bg-primary-subtle text-primary me-3">
                                        <i class="bi bi-people-fill fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-semibold">All Users</h6>
                                        <p class="mb-0 text-muted small">Share this document with all users in the system.</p>
                                    </div>
                                    <div>
                                        <input class="form-check-input" type="radio" name="share_scope_choice" id="shareScopeChoiceAll" value="all" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="card border share-option-card h-100" data-value="individual">
                                <div class="card-body d-flex align-items-center">
                                    <div class="share-option-icon bg-info-subtle text-info me-3">
                                        <i class="bi bi-person-check-fill fs-4"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 fw-semibold">Designated Users</h6>
                                        <p class="mb-0 text-muted small">Select specific users who will receive this document.</p>
                                    </div>
                                    <div>
                                        <input class="form-check-input" type="radio" name="share_scope_choice" id="shareScopeChoiceIndividual" value="individual">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="shareScopeNextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Share Users Modal -->
    <div class="modal fade" id="shareUsersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Users to Share With</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($share_users)): ?>
                        <div class="share-user-grid" style="max-height: 340px; overflow-y: auto;">
                            <?php foreach ($share_users as $su): ?>
                                <?php $roleText = ucfirst($su['role'] ?? ''); ?>
                                <div class="share-user-card" data-user-id="<?php echo (int)$su['user_id']; ?>">
                                    <div class="icon"><i class="bi bi-person-fill"></i></div>
                                    <div class="details">
                                        <div class="name"><?php echo htmlspecialchars($su['full_name'] ?: $roleText, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="email"><?php echo htmlspecialchars($su['email'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="role <?php echo $su['role'] === 'superadmin' ? 'role-superadmin' : ($su['role'] === 'admin' ? 'role-admin' : 'role-staff'); ?>"><?php echo htmlspecialchars($roleText, ENT_QUOTES, 'UTF-8'); ?></div>
                                    </div>
                                    <input class="share-user-checkbox d-none" type="checkbox" value="<?php echo (int)$su['user_id']; ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No other users are available to share with.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                    <button type="button" class="btn btn-primary" id="shareUsersNextBtn">Next</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Document Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload New Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="share_scope" id="share_scope_input" value="all">
                        <div class="mb-2">
                            <label class="form-label">File</label>
                            <input type="file" name="document_file[]" class="form-control" required multiple>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Document Type</label>
                            <select name="type_id" class="form-select" id="document_type_select" onchange="toggleCustomType(this)" required>
                                <option value="">Select Type</option>
                                <?php 
                                $types_result->data_seek(0);
                                while($type = $types_result->fetch_assoc()): ?>
                                    <option value="<?php echo $type['type_id']; ?>"><?php echo $type['type_name']; ?></option>
                                <?php endwhile; ?>
                                <option value="other">Others</option>
                            </select>
                        </div>
                        <div class="mb-3 d-none" id="required_action_wrapper">
                            <label class="form-label">Required Action</label>
                            <select class="form-select" id="required_action_select">
                                <option value="">Select Required Action</option>
                                <option value="for information">for information</option>
                                <option value="for appropriate">for appropriate</option>
                                <option value="for review">for review</option>
                                <option value="for comment/recommendation">for comment/recommendation</option>
                                <option value="for approval">for approval</option>
                                <option value="for signature">for signature</option>
                                <option value="for compliance">for compliance</option>
                                <option value="for filing/record">for filing/record</option>
                                <option value="for coordination">for coordination</option>
                                <option value="for reference">for reference</option>
                                <option value="for notation">for notation</option>
                                <option value="for guidance">for guidance</option>
                                <option value="for immediate action">for immediate action</option>
                                <option value="others">others</option>
                            </select>
                            <div class="mt-2 d-none" id="required_action_other_wrapper">
                                <label class="form-label">Please specify</label>
                                <input type="text" class="form-control" id="required_action_other_input" placeholder="Describe required action...">
                            </div>
                        </div>
                        <div class="mb-3" id="custom_type_div" style="display: none;">
                            <label class="form-label">Specify Document Type</label>
                            <input type="text" name="custom_type" id="custom_type_input" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_draft" class="btn btn-outline-secondary">Draft</button>
                        <button type="submit" name="add_document" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    function toggleCustomType(selectElement) {
        var customTypeDiv = document.getElementById('custom_type_div');
        var customTypeInput = document.getElementById('custom_type_input');
        if (selectElement.value === 'other') {
            customTypeDiv.style.display = 'block';
            customTypeInput.setAttribute('required', 'required');
        } else {
            customTypeDiv.style.display = 'none';
            customTypeInput.removeAttribute('required');
            customTypeInput.value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var shareScopeModal = document.getElementById('shareScopeModal');
        var shareUsersModal = document.getElementById('shareUsersModal');
        var uploadModal = document.getElementById('uploadModal');
        var shareScopeNextBtn = document.getElementById('shareScopeNextBtn');
        var shareUsersNextBtn = document.getElementById('shareUsersNextBtn');
        var shareScopeInput = document.getElementById('share_scope_input');
        var uploadForm = uploadModal ? uploadModal.querySelector('form') : null;

        if (shareScopeModal) {
            var optionCards = shareScopeModal.querySelectorAll('.share-option-card');
            optionCards.forEach(function (card) {
                card.addEventListener('click', function () {
                    optionCards.forEach(function (c) {
                        c.classList.remove('share-option-card-selected');
                    });
                    card.classList.add('share-option-card-selected');
                    var radio = card.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
        }

        if (shareScopeNextBtn && shareScopeModal && uploadModal && shareScopeInput) {
            shareScopeNextBtn.addEventListener('click', function () {
                var selected = document.querySelector('input[name="share_scope_choice"]:checked');
                var value = selected ? selected.value : 'all';
                shareScopeInput.value = value;

                var modalInstance = bootstrap.Modal.getInstance(shareScopeModal);
                if (modalInstance) {
                    modalInstance.hide();
                }

                if (value === 'individual' && shareUsersModal) {
                    var usersInstance = new bootstrap.Modal(shareUsersModal);
                    usersInstance.show();
                } else {
                    var uploadInstance = new bootstrap.Modal(uploadModal);
                    uploadInstance.show();
                }
            });
        }

        // Convert share users list into selectable cards
        var shareUserCards = document.querySelectorAll('.share-user-card');
        shareUserCards.forEach(function (card) {
            card.addEventListener('click', function () {
                var checkbox = card.querySelector('.share-user-checkbox');
                if (checkbox) {
                    checkbox.checked = !checkbox.checked;
                }
                card.classList.toggle('share-user-card-selected');
            });
        });

        if (shareUsersNextBtn && shareUsersModal && uploadModal && uploadForm) {
            shareUsersNextBtn.addEventListener('click', function () {
                var container = uploadForm.querySelector('#share_users_container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'share_users_container';
                    uploadForm.appendChild(container);
                }
                container.innerHTML = '';

                var checked = document.querySelectorAll('.share-user-checkbox:checked');
                checked.forEach(function (cb) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'share_user_ids[]';
                    input.value = cb.value;
                    container.appendChild(input);
                });
                var usersInstance = bootstrap.Modal.getInstance(shareUsersModal);
                if (usersInstance) {
                    usersInstance.hide();
                }
                var uploadInstance = new bootstrap.Modal(uploadModal);
                uploadInstance.show();
            });
        }
        
        function setRequiredActionVisibility() {
            var wrap = document.getElementById('required_action_wrapper');
            if (!wrap || !shareScopeInput) return;
            if (shareScopeInput.value === 'individual') {
                wrap.classList.remove('d-none');
            } else {
                wrap.classList.add('d-none');
            }
        }

        var requiredActionSelect = document.getElementById('required_action_select');
        var requiredActionOtherWrapper = document.getElementById('required_action_other_wrapper');
        var requiredActionOtherInput = document.getElementById('required_action_other_input');

        function setRequiredActionOtherVisibility() {
            if (!requiredActionSelect || !requiredActionOtherWrapper || !requiredActionOtherInput) return;
            if (requiredActionSelect.value === 'others') {
                requiredActionOtherWrapper.classList.remove('d-none');
                requiredActionOtherInput.setAttribute('required', 'required');
            } else {
                requiredActionOtherWrapper.classList.add('d-none');
                requiredActionOtherInput.removeAttribute('required');
                requiredActionOtherInput.value = '';
            }
        }

        if (requiredActionSelect) {
            requiredActionSelect.addEventListener('change', setRequiredActionOtherVisibility);
        }

        if (uploadModal) {
            uploadModal.addEventListener('show.bs.modal', function () {
                setRequiredActionVisibility();
                setRequiredActionOtherVisibility();
            });
        }

        if (uploadForm && shareScopeInput) {
            uploadForm.addEventListener('submit', function () {
                var existing = uploadForm.querySelector('input[name="required_action"]');
                if (shareScopeInput.value !== 'individual') {
                    if (existing) existing.remove();
                    return;
                }

                if (!existing) {
                    existing = document.createElement('input');
                    existing.type = 'hidden';
                    existing.name = 'required_action';
                    uploadForm.appendChild(existing);
                }

                var actionValue = '';
                if (requiredActionSelect) {
                    if (requiredActionSelect.value === 'others') {
                        actionValue = requiredActionOtherInput ? requiredActionOtherInput.value.trim() : '';
                    } else {
                        actionValue = requiredActionSelect.value;
                    }
                }

                existing.value = actionValue;
            });
        }
    });
    </script>

    <!-- Edit Document Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="document_id" id="edit_document_id">
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Document Title</label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select name="type_id" id="edit_type_id" class="form-select" required>
                                <option value="">Select Type</option>
                                <?php 
                                $types_result->data_seek(0);
                                while($type = $types_result->fetch_assoc()): ?>
                                    <option value="<?php echo $type['type_id']; ?>"><?php echo $type['type_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Replace File (Optional)</label>
                            <input type="file" name="document_file" class="form-control">
                            <small class="text-muted">Leave empty to keep current file</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_document" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this document? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="document_id" id="delete_document_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_document" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', event => {
                const button = event.relatedTarget;
                const id = button.getAttribute('data-id');
                const title = button.getAttribute('data-title');
                const type = button.getAttribute('data-type');
                const description = button.getAttribute('data-desc');
                
                editModal.querySelector('#edit_document_id').value = id;
                editModal.querySelector('#edit_title').value = title;
                editModal.querySelector('#edit_type_id').value = type;
                editModal.querySelector('#edit_description').value = description;
            });
        }

        let currentViewData = {};

        function openViewModal(data) {
            currentViewData = data;
            document.getElementById('view_title').textContent = data.title;
            document.getElementById('view_tracking').textContent = data.tracking_number;
            document.getElementById('view_type').textContent = data.type_name;
            const createdByEl = document.getElementById('view_created_by');
            if (createdByEl) createdByEl.textContent = data.created_by_user;
            const createdByLabelEl = document.getElementById('view_created_by_label');
            if (createdByLabelEl) createdByLabelEl.textContent = data.recipient_full_name ? 'Sent By' : 'Created By';
            
            const viewDateEl = document.getElementById('view_date');
            if (viewDateEl) viewDateEl.textContent = data.date_uploaded;
            
            document.getElementById('view_desc').textContent = data.description || 'No description provided.';

            const subtitleEl = document.getElementById('view_modal_subtitle');
            if (subtitleEl) {
                const tracking = data.tracking_number ? `Tracking: ${data.tracking_number}` : '';
                const type = data.type_name ? `Type: ${data.type_name}` : '';
                subtitleEl.textContent = [tracking, type].filter(Boolean).join(' • ');
            }
            
            // Show/Hide Recipient
            const recipientContainer = document.getElementById('view_recipient_container');
            const recipientEl = document.getElementById('view_recipient');
            
            if (recipientContainer && recipientEl) {
                if (data.recipient_full_name) {
                    recipientContainer.classList.remove('d-none');
                    recipientEl.textContent = data.recipient_full_name;
                } else {
                    recipientContainer.classList.add('d-none');
                }
            }
            
            const downloadBtn = document.getElementById('view_download_btn');
            
            if (data.file_path) {
                downloadBtn.setAttribute('href', '#');
                downloadBtn.setAttribute('data-document-id', data.document_id);
                downloadBtn.classList.remove('disabled');
            } else {
                downloadBtn.removeAttribute('data-document-id');
                downloadBtn.setAttribute('href', '#');
                downloadBtn.classList.add('disabled');
            }
            
            // Log View Activity
            if (data.document_id) {
                fetch('log_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'document_id=' + data.document_id + '&activity_type=view'
                }).catch(error => console.error('Error logging view:', error));
            }

            const statusDiv = document.getElementById('view_status_container');
            let badgeClass = 'status-draft';
            switch(parseInt(data.status_id)) {
                case 2: badgeClass = 'status-submitted'; break;
                case 3: badgeClass = 'status-received'; break;
                case 4: badgeClass = 'status-forwarded'; break;
                case 5: badgeClass = 'status-approved'; break;
                case 6: badgeClass = 'status-rejected'; break;
            }
            const isDesignated = (parseInt(data.recipient_id || 0) > 0) || !!data.recipient_full_name;
            const isForRevision = isDesignated && parseInt(data.status_id) === 4;
            const isApproved = isDesignated && parseInt(data.status_id) === 5;
            const isRejected = isDesignated && parseInt(data.status_id) === 6;
            const hasReceiverSubmission = isDesignated && parseInt(data.is_finished_by_recipient || 0) > 0;
            if (statusDiv) {
                if (isDesignated) {
                    if (isApproved) statusDiv.innerHTML = `<span class="status-badge status-approved">Approved</span>`;
                    else if (isRejected) statusDiv.innerHTML = `<span class="status-badge status-rejected">Rejected</span>`;
                    else if (isForRevision) statusDiv.innerHTML = `<span class="status-badge status-forwarded">For Revision</span>`;
                    else statusDiv.innerHTML = hasReceiverSubmission
                        ? `<span class="status-badge bg-warning text-dark">In Review</span>`
                        : `<span class="status-badge bg-secondary text-white">Pending</span>`;
                } else {
                    statusDiv.innerHTML = `<span class="status-badge ${badgeClass}">${data.status_name}</span>`;
                }
            }

            const reviewRemarksContainer = document.getElementById('view_review_remarks_container');
            const reviewByEl = document.getElementById('view_review_by');
            const reviewAtEl = document.getElementById('view_review_at');
            const reviewRemarksEl = document.getElementById('view_review_remarks');
            if (reviewRemarksContainer && reviewByEl && reviewAtEl && reviewRemarksEl) {
                if (hasReceiverSubmission && data.review_remarks) {
                    reviewRemarksContainer.classList.remove('d-none');
                    reviewByEl.textContent = data.recipient_full_name || 'Receiver';
                    reviewAtEl.textContent = data.review_at ? new Date(data.review_at).toLocaleString() : '';
                    reviewRemarksEl.textContent = data.review_remarks;
                } else {
                    reviewRemarksContainer.classList.add('d-none');
                    reviewByEl.textContent = '';
                    reviewAtEl.textContent = '';
                    reviewRemarksEl.textContent = '';
                }
            }

            document.querySelectorAll('.view_review_actions').forEach(el => {
                if (hasReceiverSubmission && !isApproved && !isRejected && !isForRevision) {
                    el.classList.remove('d-none');
                } else {
                    el.classList.add('d-none');
                }
            });
            document.querySelectorAll('.view-review-document-id').forEach(el => el.value = data.document_id || '');
            document.querySelectorAll('.view-review-recipient-id').forEach(el => el.value = data.recipient_id || '');

            const fileNameEl = document.getElementById('view_file_name');
            if (fileNameEl) {
                fileNameEl.textContent = data.file_name || '—';
            }
            const sharedAtContainer = document.getElementById('view_shared_at_container');
            const sharedAtEl = document.getElementById('view_shared_at');
            if (sharedAtContainer && sharedAtEl) {
                if (data.shared_at) {
                    sharedAtContainer.classList.remove('d-none');
                    sharedAtEl.textContent = new Date(data.shared_at).toLocaleString();
                } else {
                    sharedAtContainer.classList.add('d-none');
                    sharedAtEl.textContent = '';
                }
            }

            const iconDiv = document.getElementById('view_icon_container');
            let iconClass = 'bi-file-earmark';
            let iconColor = 'text-secondary';
            
            let fileExt = '';
            if (data.file_name) {
                fileExt = data.file_name.split('.').pop().toLowerCase();
            }
            
            if (fileExt) {
                switch (fileExt) {
                    case 'pdf': iconClass = 'bi-file-earmark-pdf'; iconColor = 'text-danger'; break;
                    case 'doc': case 'docx': iconClass = 'bi-file-earmark-word'; iconColor = 'text-primary'; break;
                    case 'xls': case 'xlsx': case 'csv': iconClass = 'bi-file-earmark-excel'; iconColor = 'text-success'; break;
                    case 'ppt': case 'pptx': iconClass = 'bi-file-earmark-ppt'; iconColor = 'text-warning'; break;
                    case 'jpg': case 'jpeg': case 'png': case 'gif': iconClass = 'bi-file-earmark-image'; iconColor = 'text-info'; break;
                    case 'zip': case 'rar': iconClass = 'bi-file-earmark-zip'; iconColor = 'text-dark'; break;
                }
            } else {
                const typeLower = (data.type_name || '').toLowerCase();
                if (typeLower.includes('pdf')) { iconClass = 'bi-file-earmark-pdf'; iconColor = 'text-danger'; }
                else if (typeLower.includes('word') || typeLower.includes('doc')) { iconClass = 'bi-file-earmark-word'; iconColor = 'text-primary'; }
                else if (typeLower.includes('excel') || typeLower.includes('sheet') || typeLower.includes('csv')) { iconClass = 'bi-file-earmark-excel'; iconColor = 'text-success'; }
                else if (typeLower.includes('image') || typeLower.includes('photo')) { iconClass = 'bi-file-earmark-image'; iconColor = 'text-info'; }
                else if (typeLower.includes('powerpoint') || typeLower.includes('ppt')) { iconClass = 'bi-file-earmark-ppt'; iconColor = 'text-warning'; }
            }
            iconDiv.innerHTML = `<i class="bi ${iconClass} display-1 ${iconColor}"></i>`;

            // Clear stale backdrop or stuck open modals before showing new modal
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';

            const viewModalEl = document.getElementById('viewModal');
            if (viewModalEl && viewModalEl.parentElement !== document.body) {
                document.body.appendChild(viewModalEl);
            }

            document.querySelectorAll('.modal.show').forEach(modalEl => {
                if (modalEl !== viewModalEl) {
                    const existing = bootstrap.Modal.getInstance(modalEl);
                    if (existing) existing.hide();
                    modalEl.classList.remove('show');
                }
            });

            var myModal = new bootstrap.Modal(viewModalEl, {
                backdrop: true,
                keyboard: true
            });
            myModal.show();
        }
        
        function confirmDelete(id) {
            document.getElementById('delete_document_id').value = id;
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        function confirmSubmit(id) {
            document.getElementById('submit_document_id').value = id;
            var submitModal = new bootstrap.Modal(document.getElementById('submitModal'));
            submitModal.show();
        }

        function openActivityModalFromView() {
            if (currentViewData && currentViewData.document_id) {
                const docId = currentViewData.document_id;
                const activityModal = new bootstrap.Modal(document.getElementById('activityModal'));
                const subtitle = document.getElementById('activityModalSubtitle');
                if (subtitle) subtitle.textContent = currentViewData.title;
                
                const tbody = document.getElementById('activityTableBody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center">Loading...</td></tr>';
                
                activityModal.show();
                
                fetch('../docs_tracking/get_activity_history.php?document_id=' + docId)
                    .then(r => r.json())
                    .then(res => {
                        if (tbody) {
                            tbody.innerHTML = '';
                            if (res.status === 'success' && res.data.length > 0) {
                                res.data.forEach(act => {
                                    let icon = 'bi-activity';
                                    let iconBg = 'bg-light';
                                    let textClass = 'text-dark';
                                    const type = act.activity_type.toLowerCase();
                                    
                                    if (type.includes('view')) { icon = 'bi-eye'; iconBg = 'bg-info-subtle'; textClass = 'text-info'; }
                                    else if (type.includes('download')) { icon = 'bi-download'; iconBg = 'bg-success-subtle'; textClass = 'text-success'; }
                                    else if (type.includes('upload')) { icon = 'bi-cloud-upload'; iconBg = 'bg-primary-subtle'; textClass = 'text-primary'; }
                                    else if (type.includes('share') || type.includes('sent')) { icon = 'bi-send'; iconBg = 'bg-warning-subtle'; textClass = 'text-warning'; }
                                    else if (type.includes('finish') || type.includes('complete')) { icon = 'bi-check-circle'; iconBg = 'bg-success-subtle'; textClass = 'text-success'; }

                                    const row = `
                                        <tr>
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
                                                    ${act.required_action && act.required_action !== act.details ? `<span class="badge bg-warning text-dark mb-1 align-self-start">${act.required_action}</span>` : ''}
                                                    <span class="text-secondary text-break">${act.details || '-'}</span>
                                                </div>
                                            </td>
                                        </tr>
                                    `;
                                    tbody.innerHTML += row;
                                });
                            } else {
                                tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted py-4">No activity recorded.</td></tr>';
                            }
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        if (tbody) tbody.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-4">Failed to load history.</td></tr>';
                    });
            }
        }
    </script>

    <!-- Bulk Actions Toolbar -->
    <div id="bulkActionsToolbar" class="bulk-actions-toolbar">
        <span class="fw-bold"><span id="selectedCount">0</span> selected</span>
        <div class="vr"></div>
        <button type="button" class="btn btn-success btn-sm rounded-pill" onclick="confirmBulkAction('submit')">
            <i class="bi bi-send me-1"></i> Submit
        </button>
        <button type="button" class="btn btn-danger btn-sm rounded-pill" onclick="confirmBulkAction('delete')">
            <i class="bi bi-trash me-1"></i> Delete
        </button>
        <div class="vr"></div>
        <button type="button" class="btn btn-secondary btn-sm rounded-pill" onclick="toggleSelectionMode()">
            Cancel
        </button>
    </div>
    
    <!-- Hidden Form for Bulk Actions -->
    <form id="bulkActionForm" method="POST" style="display:none;">
        <input type="hidden" name="bulk_submit" id="bulkSubmitInput" disabled>
        <input type="hidden" name="bulk_delete" id="bulkDeleteInput" disabled>
        <div id="bulkInputsContainer"></div>
    </form>

    <!-- Activity History Modal -->
    <div class="modal fade" id="activityModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-bottom-0 pb-0">
                    <div>
                        <h5 class="modal-title fw-bold">Activity History</h5>
                        <p class="text-secondary small mb-0" id="activityModalSubtitle">Loading...</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <div class="table-responsive border rounded-3">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4 py-3" style="width: 50%;">Activity</th>
                                    <th class="py-3" style="width: 50%;">Remarks</th>
                                </tr>
                            </thead>
                            <tbody id="activityTableBody">
                                <!-- Rows will be injected here -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer border-top-0">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Bulk Action Confirm Modal -->
    <div class="modal fade" id="bulkActionConfirmModal" tabindex="-1" aria-labelledby="bulkActionConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkActionConfirmModalLabel">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="bulkActionConfirmMessage">
                    Are you sure?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="bulkActionConfirmButton">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Bulk Selection Logic
        let isSelectionMode = false;

        function toggleSelectionMode() {
            isSelectionMode = !isSelectionMode;
            const checkboxes = document.querySelectorAll('.select-checkbox-col, .select-checkbox-grid');
            const toolbar = document.getElementById('bulkActionsToolbar');
            const selectBtn = document.getElementById('selectMultipleBtn');
            
            if (isSelectionMode) {
                checkboxes.forEach(el => el.classList.remove('d-none'));
                toolbar.style.display = 'flex';
                if (selectBtn) {
                    selectBtn.classList.add('active', 'btn-primary', 'text-white');
                    selectBtn.classList.remove('btn-outline-primary');
                }
            } else {
                checkboxes.forEach(el => el.classList.add('d-none'));
                toolbar.style.display = 'none';
                if (selectBtn) {
                    selectBtn.classList.remove('active', 'btn-primary', 'text-white');
                    selectBtn.classList.add('btn-outline-primary');
                }
                
                // Uncheck all
                document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = false);
                const selectAll = document.getElementById('selectAll');
                if (selectAll) selectAll.checked = false;
                updateSelectedCount();
            }
        }

        function toggleSelectAll(source) {
            // Find the closest table or grid group to scope the selection
            const container = source.closest('table') || source.closest('.status-group-grid');
            if (container) {
                const checkboxes = container.querySelectorAll('.doc-checkbox');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }
            updateSelectedCount();
        }
        
        // Sync checkboxes between views
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('doc-checkbox')) {
                const val = e.target.value;
                const related = document.querySelectorAll(`.doc-checkbox[value="${val}"]`);
                related.forEach(cb => cb.checked = e.target.checked);
                updateSelectedCount();
            }
        });

        function updateSelectedCount() {
            // Count unique selected documents
            const selected = document.querySelectorAll('.doc-checkbox:checked');
            const uniqueIds = new Set();
            selected.forEach(cb => uniqueIds.add(cb.value));
            document.getElementById('selectedCount').textContent = uniqueIds.size;
        }

        let pendingBulkAction = null;

        function confirmBulkAction(action) {
            // Get unique selected IDs
            const selected = document.querySelectorAll('.doc-checkbox:checked');
            const uniqueIds = new Set();
            selected.forEach(cb => uniqueIds.add(cb.value));

            if (uniqueIds.size === 0) {
                alert('Please select at least one document.');
                return;
            }

            pendingBulkAction = action;
            const actionText = action === 'submit' ? 'submit' : 'delete';
            const message = `Are you sure you want to ${actionText} ${uniqueIds.size} document(s)?`;
            document.getElementById('bulkActionConfirmMessage').textContent = message;

            const modalElement = document.getElementById('bulkActionConfirmModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        }

        const bulkConfirmBtn = document.getElementById('bulkActionConfirmButton');
        if (bulkConfirmBtn) {
            bulkConfirmBtn.addEventListener('click', function () {
                if (!pendingBulkAction) return;
                executeBulkAction(pendingBulkAction);
                pendingBulkAction = null;

                const modalElement = document.getElementById('bulkActionConfirmModal');
                const modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            });
        }

        function executeBulkAction(action) {
            // Get unique selected IDs
            const selected = document.querySelectorAll('.doc-checkbox:checked');
            const uniqueIds = new Set();
            selected.forEach(cb => uniqueIds.add(cb.value));

            if (uniqueIds.size === 0) {
                alert('Please select at least one document.');
                return;
            }

            const form = document.getElementById('bulkActionForm');
            const container = document.getElementById('bulkInputsContainer');
            container.innerHTML = ''; // Clear previous

            // Enable correct action input
            document.getElementById('bulkSubmitInput').disabled = (action !== 'submit');
            document.getElementById('bulkDeleteInput').disabled = (action !== 'delete');

            uniqueIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_documents[]';
                input.value = id;
                container.appendChild(input);
            });

            form.submit();
        }
        
        // View Toggle Logic
        document.addEventListener('DOMContentLoaded', function() {
            // List/Grid View Toggle
            const listViewBtn = document.getElementById('listViewBtn');
            const gridViewBtn = document.getElementById('gridViewBtn');
            const listViewContainer = document.getElementById('listViewContainer');
            const gridViewContainer = document.getElementById('gridViewContainer');

            if (listViewBtn && gridViewBtn && listViewContainer && gridViewContainer) {
                listViewBtn.addEventListener('click', function() {
                    listViewBtn.classList.add('active');
                    gridViewBtn.classList.remove('active');
                    listViewContainer.classList.remove('d-none');
                    gridViewContainer.classList.add('d-none');
                    localStorage.setItem('viewPreference', 'list');
                });

                gridViewBtn.addEventListener('click', function() {
                    gridViewBtn.classList.add('active');
                    listViewBtn.classList.remove('active');
                    listViewContainer.classList.add('d-none');
                    gridViewContainer.classList.remove('d-none');
                    localStorage.setItem('viewPreference', 'grid');
                });

                // Check local storage
                const viewPreference = localStorage.getItem('viewPreference');
                if (viewPreference === 'grid') {
                    gridViewBtn.click();
                }
            }
            
            // Realtime Search
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    // Filter List View
                    if (listViewContainer) {
                        const tables = listViewContainer.querySelectorAll('.status-group-list table');
                        tables.forEach(table => {
                            const rows = table.querySelectorAll('tbody tr');
                            let visibleRows = 0;
                            rows.forEach(row => {
                                const text = row.textContent.toLowerCase();
                                if (text.includes(searchTerm)) {
                                    row.style.display = '';
                                    visibleRows++;
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                            
                            // Hide group if no visible rows
                            const groupCard = table.closest('.status-group-list');
                            if (visibleRows === 0 && searchTerm !== '') {
                                groupCard.style.display = 'none';
                            } else {
                                groupCard.style.display = '';
                            }
                        });
                    }
                    
                    // Filter Grid View
                    if (gridViewContainer) {
                        const gridGroups = gridViewContainer.querySelectorAll('.status-group-grid');
                        gridGroups.forEach(group => {
                            const cards = group.querySelectorAll('.grid-item');
                            let visibleCards = 0;
                            cards.forEach(card => {
                                const text = card.textContent.toLowerCase();
                                if (text.includes(searchTerm)) {
                                    card.style.display = '';
                                    visibleCards++;
                                } else {
                                    card.style.display = 'none';
                                }
                            });
                            
                            // Hide group if no visible cards
                            if (visibleCards === 0 && searchTerm !== '') {
                                group.style.display = 'none';
                            } else {
                                group.style.display = '';
                            }
                        });
                    }
                });
            }
        });
    </script>

    <!-- Success Toast -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
        <div id="actionToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div id="actionToastBody" class="toast-body"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <script>
        let pendingDownloadDocId = null;

        function showToast(message) {
            const toastEl = document.getElementById('actionToast');
            const bodyEl = document.getElementById('actionToastBody');
            if (!toastEl || !bodyEl || !message) return;
            bodyEl.textContent = message;
            const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
            toast.show();
        }

        function confirmDownload(documentId) {
            pendingDownloadDocId = documentId;
            const message = 'Are you sure you want to download this file?';
            const messageEl = document.getElementById('downloadConfirmMessage');
            if (messageEl) messageEl.textContent = message;

            const downloadModalEl = document.getElementById('downloadConfirmModal');
            if (downloadModalEl) {
                const downloadModal = new bootstrap.Modal(downloadModalEl);
                downloadModal.show();
            }
        }

        function executeDownload() {
            if (!pendingDownloadDocId) return;
            window.location.href = '../docs_tracking/download_file.php?id=' + encodeURIComponent(pendingDownloadDocId);
        }

        document.addEventListener('DOMContentLoaded', function () {
            const params = new URLSearchParams(window.location.search);
            if (params.has('msg')) {
                let msg = params.get('msg');
                let text = '';
                switch (msg) {
                    case 'added': text = 'Document uploaded successfully!'; break;
                    case 'updated': text = 'Document updated successfully!'; break;
                    case 'deleted': text = 'Document deleted successfully!'; break;
                    case 'submitted': text = 'Document submitted successfully!'; break;
                    case 'approved': text = 'Document approved successfully!'; break;
                    case 'bulk_submitted': text = 'Selected documents submitted successfully!'; break;
                    case 'bulk_deleted': text = 'Selected documents deleted successfully!'; break;
                }
                if (text) showToast(text);
            }
        });
    </script>

    <!-- Download Confirmation Modal -->
    <div class="modal fade" id="downloadConfirmModal" tabindex="-1" aria-labelledby="downloadConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="downloadConfirmModalLabel">Confirm Download</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="downloadConfirmMessage">Are you sure you want to download this document?</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="executeDownload()">Download</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
