<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

if (!isset($_SESSION['user_id'], $_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'admin', 'staff'], true)) {
	header('Location: ../index.php');
	exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['designated_review'], $_POST['action'], $_POST['document_id'], $_POST['recipient_id'])) {
	include dirname(__DIR__) . '/plugins/conn.php';
	$user_id = (int)$_SESSION['user_id'];
	$shared_files_route = 'document_review.php';

	$doc_id = (int)$_POST['document_id'];
	$recipient_id = (int)$_POST['recipient_id'];
	$action = (string)$_POST['action'];
	$approval_remark = '';
	$revision_remark = '';

	if ($action === 'approve') {
		$approval_remark = trim($_POST['approval_remark'] ?? '');
		if ($approval_remark === '') {
			header('Location: ' . $shared_files_route . '?msg=approval_remark_required');
			exit();
		}
	}

	if ($action === 'revision') {
		$revision_remark = trim($_POST['revision_remark'] ?? '');
		if ($revision_remark === '') {
			header('Location: ' . $shared_files_route . '?msg=revision_reason_required');
			exit();
		}
	}

	$new_status = 0;
	$action_text = '';
	if ($action === 'approve') {
		$new_status = 5;
		$action_text = 'Approved shared document';
	} elseif ($action === 'revision') {
		$new_status = 4;
		$action_text = 'Requested revision for shared document';
	}

	if ($doc_id > 0 && $recipient_id > 0 && $new_status > 0) {
		$share_stmt = $conn->prepare("SELECT 1 FROM document_shares WHERE document_id = ? AND shared_by = ? AND recipient_id = ? LIMIT 1");
		if ($share_stmt) {
			$share_stmt->bind_param('iii', $doc_id, $user_id, $recipient_id);
			$share_stmt->execute();
			$share_res = $share_stmt->get_result();
			$is_owner_of_share = $share_res && $share_res->num_rows > 0;
			$share_stmt->close();

			if ($is_owner_of_share) {
				$finished_stmt = $conn->prepare("SELECT COUNT(*) as c FROM document_activity_log WHERE document_id = ? AND user_id = ? AND activity_type = 'finished'");
				if ($finished_stmt) {
					$finished_stmt->bind_param('ii', $doc_id, $recipient_id);
					$finished_stmt->execute();
					$finished_res = $finished_stmt->get_result();
					$finished_row = $finished_res ? $finished_res->fetch_assoc() : null;
					$is_finished = $finished_row && (int)$finished_row['c'] > 0;
					$finished_stmt->close();

					if ($is_finished) {
						$doc_stmt = $conn->prepare("SELECT title, tracking_number FROM documents WHERE document_id = ?");
						$doc = null;
						if ($doc_stmt) {
							$doc_stmt->bind_param('i', $doc_id);
							$doc_stmt->execute();
							$doc_res = $doc_stmt->get_result();
							$doc = $doc_res ? $doc_res->fetch_assoc() : null;
							$doc_stmt->close();
						}

						$update_stmt = $conn->prepare("UPDATE documents SET status_id = ? WHERE document_id = ?");
						if ($update_stmt) {
							$update_stmt->bind_param('ii', $new_status, $doc_id);
							if ($update_stmt->execute()) {
								$action_msg = $action_text;
								if ($doc) {
									$action_msg .= ': ' . $doc['title'] . ' (' . $doc['tracking_number'] . ')';
								}
								$log_stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, document_id) VALUES (?, ?, ?)");
								if ($log_stmt) {
									$log_stmt->bind_param('isi', $user_id, $action_msg, $doc_id);
									$log_stmt->execute();
									$log_stmt->close();
								}

								if ($action === 'approve') {
									$approval_text = 'Approval Remark: ' . $approval_remark;
									$approval_stmt = $conn->prepare("INSERT INTO document_remarks (document_id, user_id, remark) VALUES (?, ?, ?)");
									if ($approval_stmt) {
										$approval_stmt->bind_param('iis', $doc_id, $user_id, $approval_text);
										$approval_stmt->execute();
										$approval_stmt->close();
									}
								}

								if ($action === 'revision') {
									$remark_stmt = $conn->prepare("INSERT INTO document_activity_log (document_id, user_id, activity_type, details, recipient_id, created_at) VALUES (?, ?, 'revision_requested', ?, ?, NOW())");
									if ($remark_stmt) {
										$remark_stmt->bind_param('iisi', $doc_id, $user_id, $revision_remark, $recipient_id);
										$remark_stmt->execute();
										$remark_stmt->close();
									}
								}
							}
							$update_stmt->close();
						}
					}
				}
			}
		}
	}

	header('Location: ' . $shared_files_route);
	exit();
}

$_GET['tab'] = 'designated';
require __DIR__ . '/shared_files_review.php';
