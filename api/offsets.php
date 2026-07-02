<?php
// api/offsets.php

$db = initDB();

// GET: offsets
if ($method === 'GET') {
    $game = $_GET['game'] ?? 'Free Fire';
    $version = $_GET['version'] ?? '1.114.1';
    $stmt = $db->prepare('SELECT offsets FROM offsets WHERE game = ? AND version = ?');
    $stmt->bindValue(1, $game);
    $stmt->bindValue(2, $version);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row) {
        json_response(['success' => true, 'offsets' => json_decode($row['offsets'], true)]);
    } else {
        json_response(['success' => false, 'error' => 'Offsets not found']);
    }
}

// POST: save offsets
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $game = $input['game'] ?? 'Free Fire';
    $version = $input['version'] ?? '1.114.1';
    $offsets = json_encode($input['offsets'] ?? []);
    
    $stmt = $db->prepare('INSERT OR REPLACE INTO offsets (game, version, offsets, updated_at) VALUES (?, ?, ?, strftime("%s","now"))');
    $stmt->bindValue(1, $game);
    $stmt->bindValue(2, $version);
    $stmt->bindValue(3, $offsets);
    if ($stmt->execute()) {
        add_log($db, 'save_offsets', null, "Game: $game, Version: $version");
        json_response(['success' => true]);
    } else {
        json_response(['success' => false, 'error' => 'Failed to save offsets']);
    }
}
