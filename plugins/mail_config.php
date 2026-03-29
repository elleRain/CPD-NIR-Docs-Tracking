<?php

if (!defined('APP_MAIL_FROM_NAME')) {
    define('APP_MAIL_FROM_NAME', 'CPD-NIR');
}

if (!defined('APP_MAIL_FROM_EMAIL')) {
    define('APP_MAIL_FROM_EMAIL', 'cpdnir.noreply@gmail.com');
}

if (!defined('APP_MAIL_SMTP_HOST')) {
    define('APP_MAIL_SMTP_HOST', 'smtp.gmail.com');
}

if (!defined('APP_MAIL_SMTP_PORT')) {
    define('APP_MAIL_SMTP_PORT', 465);
}

if (!defined('APP_MAIL_SMTP_ENCRYPTION')) {
    define('APP_MAIL_SMTP_ENCRYPTION', 'ssl');
}

if (!defined('APP_MAIL_SMTP_USERNAME')) {
    define('APP_MAIL_SMTP_USERNAME', 'cpdnir.noreply@gmail.com');
}

if (!defined('APP_MAIL_SMTP_PASSWORD')) {
    define('APP_MAIL_SMTP_PASSWORD', getenv('APP_MAIL_SMTP_PASSWORD') ?: 'dxzriaxlbonsfmqb');
}

if (!defined('APP_MAIL_SMTP_TIMEOUT')) {
    define('APP_MAIL_SMTP_TIMEOUT', 20);
}

if (!defined('APP_MAIL_MAX_ATTACHMENT_BYTES')) {
    define('APP_MAIL_MAX_ATTACHMENT_BYTES', 15 * 1024 * 1024);
}