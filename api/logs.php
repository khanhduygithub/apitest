<?php
// api/logs.php

$db = initDB();
$result = $db->query('SELECT * FROM logs ORDER BY timestamp DESC LIMIT 100');
$logs = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) $logs[] = $row;
json_response(['success' => true, 'logs' => $logs]);
