<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$dbFile = __DIR__ . '/api/database.json';

function readDB() {
    global $dbFile;
    if (!file_exists($dbFile)) {
        $initial = [
            'keys' => [],
            'offsets' => [
                "get_main" => "0x4A8478C", "GetLocalPlayer" => "0x4C5A64C", "CurrentMatch" => "0x4E355B0",
                "get_HP" => "0x58691B8", "get_IsFiring" => "0x56D1580", "get_NickName" => "0x4A16D38",
                "get_isLocalTeam" => "0x55A0560", "get_Rotation" => "0x5081084", "GetHeadPositions" => "0x4AA1A28",
                "set_aim" => "0x4A1C91C", "WorldToViewpoint" => "0x84E6AC8", "get_position" => "0x8552BAC",
                "Camera_main" => "0x84E7148", "MatchPlayers" => "0x4C869DC", "FindBone" => "0x470F5D8", "IsDead" => "0x2FECBA0"
            ],
            'settings' => ['maintenance_mode' => false]
        ];
        file_put_contents($dbFile, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $initial;
    }
    return json_decode(file_get_contents($dbFile), true) ?: ['keys' => [], 'offsets' => []];
}

function writeDB($data) { global $dbFile; file_put_contents($dbFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {
    case 'auth':
        $key = $input['key'] ?? '';
        $db = readDB();
        foreach ($db['keys'] as $i => $k) {
            if ($k['key'] === $key && $k['active'] && $k['expires_at'] > time()) {
                $db['keys'][$i]['last_used'] = time();
                $db['keys'][$i]['usage_count'] = ($db['keys'][$i]['usage_count'] ?? 0) + 1;
                writeDB($db);
                echo json_encode(['success' => true, 'key' => $k['key'], 'version_name' => $k['version_name'], 'version' => $k['created_at'], 'expires_at' => $k['expires_at'], 'message' => 'Login successful']);
                exit;
            }
        }
        echo json_encode(['success' => false, 'message' => 'Invalid or expired key']);
        break;
        
    case 'check':
        $db = readDB();
        echo json_encode(['success' => true, 'allowed' => !($db['settings']['maintenance_mode'] ?? false)]);
        break;
        
    case 'check_openid':
        $openId = $input['open_id'] ?? '';
        echo json_encode(['status' => 'exists', 'udid' => 'DEVICE-' . strtoupper(substr(md5($openId ?: 'unknown'), 0, 16))]);
        break;
        
    case 'request_open_id':
        echo json_encode(['open_id' => 'OPEN-' . strtoupper(bin2hex(random_bytes(12))), 'url' => 'https://khanhduyapi.free.nf/']);
        break;
        
    case 'generate_key':
        $versionName = $input['version_name'] ?? 'OB53-Free';
        $expiryDays = max(1, (int)($input['expiry_days'] ?? 30));
        $random = bin2hex(random_bytes(12));
        $key = 'MONITE-' . strtoupper(substr($random, 0, 8) . '-' . substr($random, 8, 8) . '-' . substr($random, 16, 8));
        $db = readDB();
        $db['keys'][] = ['key' => $key, 'created_at' => time(), 'expires_at' => time() + ($expiryDays * 86400), 'version_name' => $versionName, 'active' => true, 'usage_count' => 0, 'last_used' => null];
        writeDB($db);
        echo json_encode(['success' => true, 'key' => $key, 'message' => 'Key created!']);
        break;
        
    case 'list_keys':
        echo json_encode(['keys' => readDB()['keys']]);
        break;
        
    case 'delete_key':
        $key = $input['key'] ?? '';
        $db = readDB();
        foreach ($db['keys'] as $i => $k) { if ($k['key'] === $key) { unset($db['keys'][$i]); $db['keys'] = array_values($db['keys']); writeDB($db); echo json_encode(['success' => true, 'message' => 'Key deleted!']); exit; } }
        echo json_encode(['success' => false, 'message' => 'Key not found']);
        break;
        
    case 'extend_key':
        $key = $input['key'] ?? ''; $days = max(1, (int)($input['days'] ?? 30));
        $db = readDB();
        foreach ($db['keys'] as $i => $k) { if ($k['key'] === $key) { $db['keys'][$i]['expires_at'] = max($db['keys'][$i]['expires_at'], time()) + ($days * 86400); writeDB($db); echo json_encode(['success' => true, 'message' => "Extended {$days} days!"]); exit; } }
        echo json_encode(['success' => false, 'message' => 'Key not found']);
        break;
        
    case 'save_offsets':
        $offsets = $input['offsets'] ?? [];
        if (empty($offsets)) { echo json_encode(['success' => false, 'message' => 'No offsets']); exit; }
        $db = readDB(); $db['offsets'] = $offsets; writeDB($db);
        echo json_encode(['success' => true, 'message' => 'Offsets saved!']);
        break;
        
    case 'get_offsets':
        echo json_encode(['success' => true, 'offsets' => readDB()['offsets']]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
