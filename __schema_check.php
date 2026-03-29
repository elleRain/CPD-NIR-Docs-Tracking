<?php
include 'plugins/conn.php';
$tables = ['audit_logs', 'documents', 'document_activity_log'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $r = $conn->query("DESCRIBE $t");
    while ($row = $r->fetch_assoc()) {
        echo $row['Field'] . ' ' . $row['Type'] . ' ' . $row['Default'] . "\n";
    }
    echo "\n";
}
