<?php
include "plugins/conn.php";
$sql = "SELECT d.document_id, d.tracking_number, d.status_id,
(SELECT dal.details FROM document_activity_log dal WHERE dal.document_id=d.document_id AND dal.activity_type='revision_requested' ORDER BY dal.created_at DESC LIMIT 1) AS rev_log,
(SELECT dr.remark FROM document_remarks dr WHERE dr.document_id=d.document_id AND dr.remark NOT LIKE 'Required Action:%' ORDER BY dr.created_at DESC LIMIT 1) AS rev_remark
FROM documents d
WHERE d.status_id=4
ORDER BY d.created_at DESC
LIMIT 20";
$res = $conn->query($sql);
if (!$res) {
    echo "SQL_ERR: " . $conn->error . PHP_EOL;
    exit;
}
while ($r = $res->fetch_assoc()) {
    echo $r['document_id'] . " | " . $r['tracking_number'] . " | log=" . var_export($r['rev_log'], true) . " | remark=" . var_export($r['rev_remark'], true) . PHP_EOL;
}
