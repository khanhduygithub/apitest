<?php
// includes/functions.php - Hàm dùng chung

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data);
    exit;
}

function generate_key() {
    return 'KEY-' . strtoupper(bin2hex(random_bytes(16)));
}

function add_log($db, $action, $key_value = null, $detail = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $db->prepare('INSERT INTO logs (action, key_value, detail, ip) VALUES (?, ?, ?, ?)');
    $stmt->bindValue(1, $action);
    $stmt->bindValue(2, $key_value);
    $stmt->bindValue(3, $detail);
    $stmt->bindValue(4, $ip);
    $stmt->execute();
}

function initDB() {
    $db = getDB();
    
    $db->exec('CREATE TABLE IF NOT EXISTS keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_value TEXT UNIQUE NOT NULL,
        game TEXT DEFAULT "Free Fire",
        game_uid TEXT DEFAULT "00006",
        version_name TEXT DEFAULT "1.0.7",
        version INTEGER DEFAULT 0,
        status TEXT DEFAULT "active",
        created_at INTEGER DEFAULT (strftime("%s","now")),
        expires_at INTEGER,
        max_uses INTEGER DEFAULT 999,
        use_count INTEGER DEFAULT 0,
        last_used INTEGER DEFAULT 0,
        device_id TEXT,
        metadata TEXT DEFAULT "{}"
    )');
    
    $db->exec('CREATE TABLE IF NOT EXISTS offsets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game TEXT NOT NULL,
        version TEXT NOT NULL,
        offsets TEXT NOT NULL,
        updated_at INTEGER DEFAULT (strftime("%s","now")),
        UNIQUE(game, version)
    )');
    
    $db->exec('CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp INTEGER DEFAULT (strftime("%s","now")),
        action TEXT NOT NULL,
        key_value TEXT,
        detail TEXT,
        ip TEXT
    )');
    
    // Tạo admin key
    $result = $db->query('SELECT COUNT(*) as cnt FROM keys');
    $row = $result->fetchArray(SQLITE3_ASSOC);
    if ($row['cnt'] == 0) {
        $adminKey = 'ADMIN-' . strtoupper(bin2hex(random_bytes(16)));
        $expires = time() + 365 * 24 * 3600;
        $stmt = $db->prepare('INSERT INTO keys (key_value, game, version_name, expires_at, max_uses) VALUES (?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $adminKey);
        $stmt->bindValue(2, 'Free Fire');
        $stmt->bindValue(3, '1.0.7-Admin');
        $stmt->bindValue(4, $expires);
        $stmt->bindValue(5, 99999);
        $stmt->execute();
        
        // Lưu admin key
        file_put_contents(DATA_PATH . '/admin_key.txt', "Admin Key: $adminKey\nCreated: " . date('Y-m-d H:i:s'));
    }
    
    return $db;
}
