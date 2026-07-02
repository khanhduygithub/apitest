<?php
// api/verify.php

$db = initDB();
$keyValue = $_GET['key'] ?? '';
$deviceId = $_GET['device'] ?? '';

if (!$keyValue) {
    json_response(['success' => false, 'error' => 'Key is required']);
}

$stmt = $db->prepare('SELECT * FROM keys WHERE key_value = ?');
$stmt->bindValue(1, $keyValue);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    json_response(['success' => false, 'error' => 'Key not found']);
}

if ($row['status'] !== 'active') {
    json_response(['success' => false, 'error' => 'Key is ' . $row['status']]);
}

if ($row['expires_at'] && $row['expires_at'] < time()) {
    $db->exec("UPDATE keys SET status = 'expired' WHERE key_value = '$keyValue'");
    json_response(['success' => false, 'error' => 'Key expired']);
}

if ($row['use_count'] >= $row['max_uses']) {
    json_response(['success' => false, 'error' => 'Key usage limit exceeded']);
}

// Update use count
$stmt = $db->prepare('UPDATE keys SET use_count = use_count + 1, last_used = strftime("%s","now"), device_id = ? WHERE key_value = ?');
$stmt->bindValue(1, $deviceId);
$stmt->bindValue(2, $keyValue);
$stmt->execute();

add_log($db, 'verify', $keyValue, "Device: $deviceId");

json_response([
    'success' => true,
    'key' => $row['key_value'],
    'game' => $row['game'],
    'version' => $row['version_name'],
    'expires_at' => $row['expires_at']
]);
