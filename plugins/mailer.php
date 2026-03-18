<?php

function send_app_email($toEmail, $toName, $subject, $htmlBody, $plainBody, &$error)
{
    $error = '';

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid recipient email address.';
        return false;
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=UTF-8';
    $headers[] = 'From: CPD-NIR <no-reply@example.com>';

    $success = @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));

    if (!$success) {
        $error = 'Mail function failed or is not configured on this server.';
    }

    return $success;
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

