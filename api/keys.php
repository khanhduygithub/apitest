<?php
// api/keys.php

$db = initDB();

// GET: stats
if ($action === 'stats') {
    $result = $db->query('SELECT status, COUNT(*) as cnt FROM keys GROUP BY status');
    $stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'revoked' => 0];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $stats[$row['status']] = (int)$row['cnt'];
        $stats['total'] += (int)$row['cnt'];
    }
    json_response(['success' => true] + $stats);
}

// GET: recent
if ($action === 'recent') {
    $result = $db->query('SELECT * FROM keys ORDER BY created_at DESC LIMIT 10');
    $keys = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) $keys[] = $row;
    json_response(['success' => true, 'keys' => $keys]);
}

// GET: keys
if ($action === 'keys' && $method === 'GET') {
    $search = $_GET['search'] ?? '';
    $sql = 'SELECT * FROM keys ORDER BY created_at DESC';
    if ($search) {
        $search = SQLite3::escapeString($search);
        $sql = "SELECT * FROM keys WHERE key_value LIKE '%$search%' ORDER BY created_at DESC";
    }
    $result = $db->query($sql);
    $keys = [];
    while ($row = $result->fetchArray(SQLite3_ASSOC)) $keys[] = $row;
    json_response(['success' => true, 'keys' => $keys]);
}

// POST: generate
if ($action === 'generate' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $game = $input['game'] ?? 'Free Fire';
    $version = $input['version'] ?? '1.0.7';
    $gameUid = $input['game_uid'] ?? '00006';
    $days = (int)($input['days'] ?? 30);
    
    $keyValue = generate_key();
    $expires = time() + $days * 24 * 3600;
    
    $stmt = $db->prepare('INSERT INTO keys (key_value, game, game_uid, version_name, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->bindValue(1, $keyValue);
    $stmt->bindValue(2, $game);
    $stmt->bindValue(3, $gameUid);
    $stmt->bindValue(4, $version);
    $stmt->bindValue(5, $expires);
    
    if ($stmt->execute()) {
        add_log($db, 'generate', $keyValue, "Game: $game, Version: $version, Days: $days");
        json_response(['success' => true, 'key' => $keyValue]);
    } else {
        json_response(['success' => false, 'error' => 'Failed to create key']);
    }
}

// POST: revoke
if ($action === 'revoke' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $keyValue = $input['key'] ?? '';
    if (!$keyValue) json_response(['success' => false, 'error' => 'Key is required']);
    
    $stmt = $db->prepare('UPDATE keys SET status = "revoked" WHERE key_value = ? AND status = "active"');
    $stmt->bindValue(1, $keyValue);
    if ($stmt->execute() && $stmt->changes() > 0) {
        add_log($db, 'revoke', $keyValue);
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'error' => 'Key not found or already revoked']);
    }
}

// POST: extend
if ($action === 'extend' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $keyValue = $input['key'] ?? '';
    $days = (int)($input['days'] ?? 30);
    if (!$keyValue || $days <= 0) json_response(['success' => false, 'error' => 'Invalid input']);
    
    $stmt = $db->prepare('UPDATE keys SET expires_at = expires_at + ? WHERE key_value = ? AND status = "active"');
    $stmt->bindValue(1, $days * 24 * 3600);
    $stmt->bindValue(2, $keyValue);
    if ($stmt->execute() && $stmt->changes() > 0) {
        add_log($db, 'extend', $keyValue, "Extended by $days days");
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'error' => 'Key not found or not active']);
    }
}
