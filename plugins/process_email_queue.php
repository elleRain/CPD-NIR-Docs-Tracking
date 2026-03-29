<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script must be run from the command line.');
}

require_once __DIR__ . '/conn.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_queue.php';

$limit = 10;
foreach ($argv as $argument) {
    if (strpos((string)$argument, '--limit=') === 0) {
        $limit = (int)substr((string)$argument, 8);
        break;
    }
}

$summary = process_email_queue($conn, $limit);
echo 'processed=' . (int)$summary['processed'] . ', sent=' . (int)$summary['sent'] . ', failed=' . (int)$summary['failed'] . PHP_EOL;