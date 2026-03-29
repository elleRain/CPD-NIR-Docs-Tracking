<?php

function ensure_email_queue_table(mysqli $conn)
{
    static $ensured = false;

    if ($ensured) {
        return true;
    }

    $sql = "CREATE TABLE IF NOT EXISTS email_queue_jobs (
        job_id INT AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(255) NOT NULL,
        to_name VARCHAR(255) NOT NULL DEFAULT '',
        subject VARCHAR(255) NOT NULL,
        html_body LONGTEXT NOT NULL,
        plain_body LONGTEXT NOT NULL,
        attachments_json LONGTEXT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempt_count INT NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        locked_at DATETIME NULL,
        processed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email_queue_status_available (status, available_at),
        INDEX idx_email_queue_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $ensured = $conn->query($sql) === true;
    return $ensured;
}

function enqueue_app_email(mysqli $conn, $toEmail, $toName, $subject, $htmlBody, $plainBody, array $attachments = [], $availableAt = null)
{
    if (!ensure_email_queue_table($conn)) {
        return false;
    }

    $recipientEmail = trim((string)$toEmail);
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $availableAtValue = trim((string)$availableAt);
    if ($availableAtValue === '') {
        $availableAtValue = date('Y-m-d H:i:s');
    }

    $attachmentsJson = json_encode(array_values($attachments), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($attachmentsJson === false) {
        $attachmentsJson = '[]';
    }

    $stmt = $conn->prepare("INSERT INTO email_queue_jobs (to_email, to_name, subject, html_body, plain_body, attachments_json, status, available_at, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())");
    if (!$stmt) {
        return false;
    }

    $recipientName = trim((string)$toName);
    $mailSubject = (string)$subject;
    $html = (string)$htmlBody;
    $plain = (string)$plainBody;
    $stmt->bind_param('sssssss', $recipientEmail, $recipientName, $mailSubject, $html, $plain, $attachmentsJson, $availableAtValue);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function claim_email_queue_job(mysqli $conn, $jobId)
{
    $jobId = (int)$jobId;
    if ($jobId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("UPDATE email_queue_jobs SET status = 'processing', locked_at = NOW() WHERE job_id = ? AND status = 'pending' AND available_at <= NOW()");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $jobId);
    $stmt->execute();
    $claimed = $stmt->affected_rows > 0;
    $stmt->close();

    return $claimed;
}

function process_email_queue(mysqli $conn, $limit = 10)
{
    if (!ensure_email_queue_table($conn)) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    }

    $limit = max(1, (int)$limit);
    $processed = 0;
    $sent = 0;
    $failed = 0;

    $result = $conn->query("SELECT job_id, to_email, to_name, subject, html_body, plain_body, attachments_json, attempt_count FROM email_queue_jobs WHERE status = 'pending' AND available_at <= NOW() ORDER BY job_id ASC LIMIT " . $limit);
    if (!$result) {
        return ['processed' => 0, 'sent' => 0, 'failed' => 0];
    }

    while ($job = $result->fetch_assoc()) {
        $jobId = (int)$job['job_id'];
        if (!claim_email_queue_job($conn, $jobId)) {
            continue;
        }

        $processed++;
        $attachments = json_decode((string)($job['attachments_json'] ?? '[]'), true);
        if (!is_array($attachments)) {
            $attachments = [];
        }

        $mailError = '';
        $wasSent = send_app_email(
            (string)$job['to_email'],
            (string)$job['to_name'],
            (string)$job['subject'],
            (string)$job['html_body'],
            (string)$job['plain_body'],
            $mailError,
            $attachments
        );

        if ($wasSent) {
            $stmtSent = $conn->prepare("UPDATE email_queue_jobs SET status = 'sent', last_error = NULL, locked_at = NULL, processed_at = NOW() WHERE job_id = ?");
            if ($stmtSent) {
                $stmtSent->bind_param('i', $jobId);
                $stmtSent->execute();
                $stmtSent->close();
            }
            $sent++;
            continue;
        }

        $attemptCount = ((int)$job['attempt_count']) + 1;
        $nextStatus = $attemptCount >= 3 ? 'failed' : 'pending';
        $retryAt = date('Y-m-d H:i:s', time() + 300);
        $errorMessage = trim((string)$mailError);
        if ($errorMessage === '') {
            $errorMessage = 'Email delivery failed.';
        }

        $stmtFailed = $conn->prepare("UPDATE email_queue_jobs SET status = ?, attempt_count = ?, last_error = ?, available_at = ?, locked_at = NULL, processed_at = CASE WHEN ? = 'failed' THEN NOW() ELSE NULL END WHERE job_id = ?");
        if ($stmtFailed) {
            $stmtFailed->bind_param('sisssi', $nextStatus, $attemptCount, $errorMessage, $retryAt, $nextStatus, $jobId);
            $stmtFailed->execute();
            $stmtFailed->close();
        }
        $failed++;
    }

    $result->close();

    return ['processed' => $processed, 'sent' => $sent, 'failed' => $failed];
}

function trigger_email_queue_worker($limit = 10)
{
    if (PHP_SAPI === 'cli') {
        return false;
    }

    $workerScript = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'process_email_queue.php');
    if ($workerScript === false || !is_file($workerScript)) {
        return false;
    }

    $phpBinary = defined('PHP_BINARY') && trim((string)PHP_BINARY) !== '' ? PHP_BINARY : 'php';
    $limit = max(1, (int)$limit);

    if (DIRECTORY_SEPARATOR === '\\') {
        $command = 'start /B "" ' . escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' --limit=' . $limit . ' > NUL 2>&1';
        $handle = @popen($command, 'r');
        if ($handle === false) {
            return false;
        }
        @pclose($handle);
        return true;
    }

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' --limit=' . $limit . ' > /dev/null 2>&1 &';
    $handle = @popen($command, 'r');
    if ($handle === false) {
        return false;
    }
    @pclose($handle);

    return true;
}