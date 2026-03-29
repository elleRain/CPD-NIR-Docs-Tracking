# CPD-NIR-Docs-Tracking

## Gmail SMTP Setup

Outgoing email now uses Gmail SMTP through the shared mail helper.

Update the Gmail app password in [plugins/mail_config.php](plugins/mail_config.php) by setting `APP_MAIL_SMTP_PASSWORD`, or provide it through the `APP_MAIL_SMTP_PASSWORD` environment variable.