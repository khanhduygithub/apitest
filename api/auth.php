<?php
// api/auth.php

require_once dirname(__DIR__) . '/includes/crypto.php';

$db = initDB();
$input = json_decode(file_get_contents('php://input'), true);
$encrypted = $input['data'] ?? '';

if (empty($encrypted)) {
    json_response(['success' => false, 'message' => 'No data provided']);
}

// Giải mã
$decrypted = decrypt_package($encrypted, AES_KEY);
if ($decrypted === null) {
    json_response(['success' => false, 'message' => 'Invalid encryption']);
}

$id = $decrypted['id'] ?? '';
$key = $decrypted['key'] ?? '';
$game = $decrypted['game'] ?? 'Free Fire';
$timestamp = $decrypted['timestamp'] ?? 0;

// Kiểm tra key
$stmt = $db->prepare('SELECT * FROM keys WHERE key_value = ? AND game = ?');
$stmt->bindValue(1, $key);
$stmt->bindValue(2, $game);
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);

if (!$row) {
    json_response(['success' => false, 'message' => 'Invalid key']);
}

if ($row['status'] !== 'active') {
    json_response(['success' => false, 'message' => 'Key is ' . $row['status']]);
}

if ($row['expires_at'] && $row['expires_at'] < time()) {
    $db->exec("UPDATE keys SET status = 'expired' WHERE key_value = '$key'");
    json_response(['success' => false, 'message' => 'Key expired']);
}

// Update use count
$stmt = $db->prepare('UPDATE keys SET use_count = use_count + 1, last_used = strftime("%s","now"), device_id = ? WHERE key_value = ?');
$stmt->bindValue(1, $id);
$stmt->bindValue(2, $key);
$stmt->execute();

add_log($db, 'auth', $key, "User: $id, Game: $game");

// Trả về response mã hóa
$responseData = [
    'success' => true,
    'key' => $row['key_value'],
    'version_name' => $row['version_name'],
    'version' => $row['created_at'],
    'expires_at' => $row['expires_at']
];

$encryptedResponse = encrypt_package($responseData, AES_KEY);
json_response(['success' => true, 'data' => $encryptedResponse]);
