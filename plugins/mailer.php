<?php

require_once __DIR__ . '/mail_config.php';

function send_app_email($toEmail, $toName, $subject, $htmlBody, $plainBody, &$error, array $attachments = [])
{
    $error = '';

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid recipient email address.';
        return false;
    }

    if (!filter_var(APP_MAIL_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid sender email address configuration.';
        return false;
    }

    if (trim((string)APP_MAIL_SMTP_USERNAME) === '' || trim((string)APP_MAIL_SMTP_PASSWORD) === '') {
        $error = 'Gmail SMTP is not fully configured. Set APP_MAIL_SMTP_PASSWORD in plugins/mail_config.php or as an environment variable.';
        return false;
    }

    return send_app_email_smtp($toEmail, $toName, $subject, $htmlBody, $plainBody, $error, $attachments);
}

function send_app_email_smtp($toEmail, $toName, $subject, $htmlBody, $plainBody, &$error, array $attachments = [])
{
    $host = (string)APP_MAIL_SMTP_HOST;
    $port = (int)APP_MAIL_SMTP_PORT;
    $encryption = strtolower((string)APP_MAIL_SMTP_ENCRYPTION);
    $timeout = (int)APP_MAIL_SMTP_TIMEOUT;
    $username = (string)APP_MAIL_SMTP_USERNAME;
    $password = (string)APP_MAIL_SMTP_PASSWORD;

    $remoteHost = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $socket = @stream_socket_client($remoteHost, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if (!$socket) {
        $error = 'SMTP connection failed: ' . $errstr . ' (' . $errno . ')';
        return false;
    }

    stream_set_timeout($socket, $timeout);

    if (!smtp_expect_response($socket, ['220'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, 'EHLO localhost', ['250'], $error)) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls') {
        if (!smtp_send_command($socket, 'STARTTLS', ['220'], $error)) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            $error = 'Unable to start TLS encryption for SMTP.';
            fclose($socket);
            return false;
        }
        if (!smtp_send_command($socket, 'EHLO localhost', ['250'], $error)) {
            fclose($socket);
            return false;
        }
    }

    if (!smtp_send_command($socket, 'AUTH LOGIN', ['334'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, base64_encode($username), ['334'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, base64_encode($password), ['235'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, 'MAIL FROM:<' . APP_MAIL_FROM_EMAIL . '>', ['250'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, 'RCPT TO:<' . $toEmail . '>', ['250', '251'], $error)) {
        fclose($socket);
        return false;
    }

    if (!smtp_send_command($socket, 'DATA', ['354'], $error)) {
        fclose($socket);
        return false;
    }

    $message = build_smtp_message($toEmail, $toName, $subject, $htmlBody, $plainBody, $attachments);
    if (@fwrite($socket, $message . "\r\n.\r\n") === false) {
        $error = 'Failed to write SMTP message body.';
        fclose($socket);
        return false;
    }

    if (!smtp_expect_response($socket, ['250'], $error)) {
        fclose($socket);
        return false;
    }

    smtp_send_command($socket, 'QUIT', ['221'], $quitError);
    fclose($socket);

    return true;
}

function build_smtp_message($toEmail, $toName, $subject, $htmlBody, $plainBody, array $attachments = [])
{
    $attachments = prepare_mail_attachments($attachments);
    $subjectHeader = '=?UTF-8?B?' . base64_encode((string)$subject) . '?=';
    $headers = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . format_email_header(APP_MAIL_FROM_NAME, APP_MAIL_FROM_EMAIL);
    $headers[] = 'To: ' . format_email_header($toName, $toEmail);
    $headers[] = 'Reply-To: ' . APP_MAIL_FROM_EMAIL;
    $headers[] = 'Subject: ' . $subjectHeader;
    $headers[] = 'MIME-Version: 1.0';
    $alternativeBoundary = 'alt_' . bin2hex(random_bytes(12));

    if (empty($attachments)) {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"';

        $body = build_alternative_mail_body($alternativeBoundary, $plainBody, $htmlBody);
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    $mixedBoundary = 'mix_' . bin2hex(random_bytes(12));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

    $body = [];
    $body[] = '--' . $mixedBoundary;
    $body[] = 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"';
    $body[] = '';
    $body[] = build_alternative_mail_body($alternativeBoundary, $plainBody, $htmlBody);

    foreach ($attachments as $attachment) {
        $disposition = ($attachment['disposition'] ?? 'attachment') === 'inline' ? 'inline' : 'attachment';
        $body[] = '--' . $mixedBoundary;
        $body[] = 'Content-Type: ' . $attachment['mime'] . '; name="' . addcslashes($attachment['name'], '"\\') . '"';
        $body[] = 'Content-Transfer-Encoding: base64';
        $body[] = 'Content-Disposition: ' . $disposition . '; filename="' . addcslashes($attachment['name'], '"\\') . '"';
        if (!empty($attachment['cid'])) {
            $body[] = 'Content-ID: <' . preg_replace('/[^A-Za-z0-9_\.-]/', '', (string)$attachment['cid']) . '>';
        }
        $body[] = '';
        $body[] = chunk_split(base64_encode($attachment['content']));
    }

    $body[] = '--' . $mixedBoundary . '--';
    $body[] = '';

    return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $body);
}

function build_alternative_mail_body($boundary, $plainBody, $htmlBody)
{
    $body = [];
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/plain; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 8bit';
    $body[] = '';
    $body[] = normalize_smtp_body($plainBody);
    $body[] = '--' . $boundary;
    $body[] = 'Content-Type: text/html; charset=UTF-8';
    $body[] = 'Content-Transfer-Encoding: 8bit';
    $body[] = '';
    $body[] = normalize_smtp_body($htmlBody);
    $body[] = '--' . $boundary . '--';
    $body[] = '';

    return implode("\r\n", $body);
}

function prepare_mail_attachments(array $attachments)
{
    $prepared = [];

    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }

        $attachmentPath = isset($attachment['path']) ? resolve_mail_attachment_path((string)$attachment['path']) : '';
        if ($attachmentPath === '' || !is_file($attachmentPath) || !is_readable($attachmentPath)) {
            continue;
        }

        $size = @filesize($attachmentPath);
        if ($size === false || $size <= 0 || $size > APP_MAIL_MAX_ATTACHMENT_BYTES) {
            continue;
        }

        $content = @file_get_contents($attachmentPath);
        if ($content === false) {
            continue;
        }

        $name = trim((string)($attachment['name'] ?? ''));
        if ($name === '') {
            $name = basename($attachmentPath);
        }

        $mime = 'application/octet-stream';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($attachmentPath);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }

        $prepared[] = [
            'name' => $name,
            'mime' => $mime,
            'content' => $content,
            'disposition' => (!empty($attachment['inline']) || (($attachment['disposition'] ?? '') === 'inline')) ? 'inline' : 'attachment',
            'cid' => trim((string)($attachment['cid'] ?? '')),
        ];
    }

    return $prepared;
}

function resolve_mail_attachment_path($path)
{
    $rawPath = trim((string)$path);
    if ($rawPath === '') {
        return '';
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rawPath);
    $isAbsolute = preg_match('/^[A-Za-z]:\\\\|^\\\\\\\\|^\//', $rawPath) === 1;
    $candidates = [];

    if ($isAbsolute) {
        $candidates[] = $normalized;
    } else {
        $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . $normalized;
        $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($normalized, '\\/');
    }

    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved !== false && is_file($resolved)) {
            return $resolved;
        }
    }

    return '';
}

function format_email_header($name, $email)
{
    $cleanEmail = trim((string)$email);
    $cleanName = trim((string)$name);
    if ($cleanName === '') {
        return $cleanEmail;
    }

    return '=?UTF-8?B?' . base64_encode($cleanName) . '?= <' . $cleanEmail . '>';
}

function normalize_smtp_body($body)
{
    $normalized = str_replace(["\r\n", "\r"], "\n", (string)$body);
    $normalized = preg_replace('/^\./m', '..', $normalized);
    return str_replace("\n", "\r\n", $normalized);
}

function build_mail_image_src($imageReference)
{
    $reference = trim((string)$imageReference);
    if ($reference === '') {
        return '';
    }

    $resolvedPath = resolve_mail_attachment_path($reference);
    if ($resolvedPath !== '' && is_file($resolvedPath) && is_readable($resolvedPath)) {
        $mime = 'image/png';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($resolvedPath);
            if (is_string($detected) && strpos($detected, 'image/') === 0) {
                $mime = $detected;
            }
        }

        $content = @file_get_contents($resolvedPath);
        if ($content !== false) {
            return 'data:' . $mime . ';base64,' . base64_encode($content);
        }
    }

    return $reference;
}

function build_inline_logo_attachment($imageReference, $cid = 'cpdnir_logo')
{
    $reference = trim((string)$imageReference);
    if ($reference === '') {
        return null;
    }

    $resolvedPath = resolve_mail_attachment_path($reference);
    if ($resolvedPath === '' || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
        return null;
    }

    return [
        'path' => $resolvedPath,
        'name' => basename($resolvedPath),
        'inline' => true,
        'disposition' => 'inline',
        'cid' => $cid,
    ];
}

function smtp_send_command($socket, $command, array $expectedCodes, &$error)
{
    if (@fwrite($socket, $command . "\r\n") === false) {
        $error = 'Failed to write SMTP command.';
        return false;
    }

    return smtp_expect_response($socket, $expectedCodes, $error);
}

function smtp_expect_response($socket, array $expectedCodes, &$error)
{
    $response = smtp_read_response($socket);
    if ($response === '') {
        $error = 'Empty response from SMTP server.';
        return false;
    }

    $code = substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        $error = 'SMTP server rejected the request: ' . trim($response);
        return false;
    }

    return true;
}

function smtp_read_response($socket)
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }

        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $response;
}

function build_account_welcome_email($username, $password, $role, $loginUrl, $logoUrl)
{
    $roleLabel = ucfirst((string)$role);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Account Created</title></head><body>';
    $html .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
    if (!empty($logoUrl)) {
        $html .= '<div style="text-align:center; margin-bottom:16px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="max-height:80px;"></div>';
    }
    $html .= '<h2>Welcome to CPD-NIR Inventory System</h2>';
    $html .= '<p>Your account has been created with the following details:</p>';
    $html .= '<ul>';
    $html .= '<li>Username: <strong>' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '</strong></li>';
    $html .= '<li>Role: <strong>' . htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') . '</strong></li>';
    $html .= '</ul>';
    $html .= '<p>You can sign in using the username above and the password you provided during registration.</p>';
    if (!empty($loginUrl)) {
        $html .= '<p><a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">Click here to log in</a></p>';
    }
    $html .= '<p style="margin-top:24px;">Thank you.</p>';
    $html .= '</div>';
    $html .= '</body></html>';

    $plain = "Welcome to CPD-NIR Inventory System\n\n";
    $plain .= "Your account has been created.\n";
    $plain .= "Username: " . $username . "\n";
    $plain .= "Role: " . $roleLabel . "\n\n";
    $plain .= "Use the password you provided during registration to sign in.\n";
    if (!empty($loginUrl)) {
        $plain .= "Login: " . $loginUrl . "\n";
    }
    $plain .= "\nThank you.\n";

    return [$html, $plain];
}

function build_todo_share_email($recipientName, $senderName, $documentTitle, $actionUrl, $loginUrl, $logoUrl)
{
    $recipientLabel = trim((string)$recipientName) !== '' ? trim((string)$recipientName) : 'User';
    $senderLabel = trim((string)$senderName) !== '' ? trim((string)$senderName) : 'A user';
    $documentLabel = trim((string)$documentTitle) !== '' ? trim((string)$documentTitle) : 'a document';

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>To-Do File Shared</title></head><body>';
    $html .= '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; color: #0f172a;">';
    if (!empty($logoUrl)) {
        $html .= '<div style="text-align:center; margin-bottom:16px;"><img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="max-height:80px;"></div>';
    }
    $html .= '<h2 style="margin-bottom:12px;">A To-Do file was shared with you</h2>';
    $html .= '<p>Hello ' . htmlspecialchars($recipientLabel, ENT_QUOTES, 'UTF-8') . ',</p>';
    $html .= '<p><strong>' . htmlspecialchars($senderLabel, ENT_QUOTES, 'UTF-8') . '</strong> shared a file with you and marked it as <strong>To-Do</strong>.</p>';
    $html .= '<div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:16px; margin:16px 0;">';
    $html .= '<div style="font-size:12px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;">Document</div>';
    $html .= '<div style="font-size:16px; font-weight:700; color:#0f172a;">' . htmlspecialchars($documentLabel, ENT_QUOTES, 'UTF-8') . '</div>';
    $html .= '</div>';
    if (!empty($actionUrl)) {
        $html .= '<p style="margin:20px 0;"><a href="' . htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:#003087; color:#ffffff; text-decoration:none; padding:12px 20px; border-radius:8px; font-weight:700;">Open Action Required</a></p>';
    }
    if (!empty($loginUrl)) {
        $html .= '<p style="font-size:13px; color:#475569;">If the button does not work, sign in here: <a href="' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }
    $html .= '<p style="margin-top:24px;">Please review the file in the system.</p>';
    $html .= '</div></body></html>';

    $plain = "A To-Do file was shared with you\n\n";
    $plain .= 'Hello ' . $recipientLabel . ",\n\n";
    $plain .= $senderLabel . ' shared a file with you and marked it as To-Do.' . "\n";
    $plain .= 'Document: ' . $documentLabel . "\n";
    if (!empty($actionUrl)) {
        $plain .= 'Open Action Required: ' . $actionUrl . "\n";
    }
    if (!empty($loginUrl)) {
        $plain .= 'Login: ' . $loginUrl . "\n";
    }
    $plain .= "\nPlease review the file in the system.\n";

    return [$html, $plain];
}

function build_document_share_email($recipientName, $senderName, array $document, $actionUrl, $loginUrl, $logoUrl, &$inlineAttachments = [])
{
    $recipientLabel = trim((string)$recipientName) !== '' ? trim((string)$recipientName) : 'User';
    $senderLabel = trim((string)$senderName) !== '' ? trim((string)$senderName) : 'A user';
    $title = trim((string)($document['title'] ?? 'Document'));
    $trackingNumber = trim((string)($document['tracking_number'] ?? ''));
    $description = trim((string)($document['description'] ?? ''));
    $fileName = trim((string)($document['file_name'] ?? ''));
    $typeName = trim((string)($document['type_name'] ?? ''));
    $requiredAction = trim((string)($document['required_action'] ?? ''));
    $sharedAt = trim((string)($document['shared_at'] ?? date('M d, Y h:i A')));
    $fileUrl = trim((string)($document['file_url'] ?? ''));
    $logoSrc = '';

    $detailRows = [];
    $detailRows[] = ['Sender', $senderLabel];
    $detailRows[] = ['Title', $title !== '' ? $title : 'Document'];
    if ($trackingNumber !== '') {
        $detailRows[] = ['Tracking Number', $trackingNumber];
    }
    if ($typeName !== '') {
        $detailRows[] = ['Document Type', $typeName];
    }
    if ($fileName !== '') {
        $detailRows[] = ['Attached File', $fileName];
    }
    if ($requiredAction !== '') {
        $detailRows[] = ['Required Action', $requiredAction];
    }
    $detailRows[] = ['Shared On', $sharedAt];
    $buttonUrl = $fileUrl !== '' ? $fileUrl : trim((string)$actionUrl);

    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>File Shared</title></head><body style="margin:0;padding:12px;background:#ffffff;font-family:Arial,sans-serif;color:#16324f;word-break:break-word;overflow-wrap:anywhere;">';
    $html .= '<div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #d8e5fb;border-radius:24px;box-shadow:0 18px 46px rgba(9,44,135,0.10);overflow:hidden;">';
    $html .= '<div style="height:6px;background:#061f63;"></div>';
    $html .= '<div style="background:#092c87;padding:18px 24px;color:#ffffff;">';
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
    $html .= '<tr>';
    $html .= '<td style="vertical-align:top;">';
    $html .= '<div style="display:inline-block;padding:4px 10px;border-radius:999px;background:rgba(255,255,255,0.18);font-size:10px;letter-spacing:.08em;text-transform:uppercase;font-weight:700;margin-bottom:8px;">Shared File Notification</div>';
    $html .= '<div style="font-size:10px;letter-spacing:.12em;text-transform:uppercase;opacity:.82;margin-bottom:8px;">CPD-NIR Document Tracking System</div>';
    $html .= '<h1 style="margin:0;font-size:22px;line-height:1.2;font-weight:700;">A file has been shared with you</h1>';
    $html .= '<p style="margin:8px 0 0;font-size:13px;line-height:1.55;opacity:.95;">' . htmlspecialchars($senderLabel, ENT_QUOTES, 'UTF-8') . ' shared a file with you in CPD-NIR.</p>';
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '<div style="padding:32px;">';
    $html .= '<p style="margin:0 0 18px;font-size:15px;line-height:1.7;color:#24405f;">Hello ' . htmlspecialchars($recipientLabel, ENT_QUOTES, 'UTF-8') . ',</p>';
    if ($description !== '') {
        $html .= '<div style="margin-bottom:22px;">';
        $html .= '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#5a7194;font-weight:700;margin-bottom:10px;">Description</div>';
        $html .= '<div style="background:#f8fbff;border:1px solid #d8e5fb;border-radius:18px;padding:18px 20px;font-size:15px;line-height:1.75;color:#3a4a63;white-space:pre-wrap;word-break:break-word;overflow-wrap:anywhere;">' . nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')) . '</div>';
        $html .= '</div>';
    }
    $html .= '<div style="background:#f4f8ff;border:1px solid #cfe0ff;border-radius:18px;padding:22px 24px;margin-bottom:22px;">';
    $html .= '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#5a7194;font-weight:700;margin-bottom:12px;">Shared File Details</div>';
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;">';
    foreach ($detailRows as $detailRow) {
        $html .= '<tr>';
        $html .= '<td style="padding:9px 0;width:180px;font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:#5a7194;font-weight:700;vertical-align:top;">' . htmlspecialchars($detailRow[0], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td style="padding:9px 0;font-size:15px;color:#0f2340;font-weight:600;">' . htmlspecialchars($detailRow[1], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    $html .= '</div>';
    if ($buttonUrl !== '') {
        $html .= '<div style="margin-top:4px;"><a href="' . htmlspecialchars($buttonUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:12px 22px;border-radius:999px;background:#0b3a9a;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;border:1px solid #0a3182;">Open Shared File</a></div>';
    }
    $html .= '</div>';
    $html .= '<div style="padding:18px 32px 28px;border-top:1px solid #d9e6fb;font-size:12px;color:#5a7194;background:#f8fbff;">This is an automated notification from CPD-NIR. Please do not reply directly to this email.</div>';
    $html .= '</div></body></html>';

    $plain = "A file has been shared with you\n\n";
    $plain .= 'Hello ' . $recipientLabel . ",\n\n";
    $plain .= $senderLabel . ' shared a document with you in CPD-NIR.' . "\n\n";
    foreach ($detailRows as $detailRow) {
        $plain .= $detailRow[0] . ': ' . $detailRow[1] . "\n";
    }
    if ($description !== '') {
        $plain .= "\nDescription:\n" . $description . "\n";
    }
    if ($buttonUrl !== '') {
        $plain .= 'Open Shared File: ' . $buttonUrl . "\n";
    }
    $plain .= "\nThis is an automated notification from CPD-NIR.\n";

    return [$html, $plain];
}

