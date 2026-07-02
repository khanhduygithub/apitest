<?php
// ============================================================
// Monite API - Main Backend
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, User-Agent, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ============================================================
// CONFIG
// ============================================================
define('DB_FILE', __DIR__ . '/monite.db');
define('ADMIN_USER', 'admin1');
define('ADMIN_PASS', 'admin2010');
define('KEY_PREFIX', 'KhanhDuy_');
define('KEY_LENGTH', 12);
define('AES_KEY', hex2bin('A1B2C3D4E5F60718293A4B5C6D7E8F90'));

// ============================================================
// DATABASE
// ============================================================
function getDB() {
    $db = new SQLite3(DB_FILE);
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE,
        password TEXT,
        token TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS keys (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        key_value TEXT UNIQUE,
        active INTEGER DEFAULT 1,
        expired INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS offsets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT UNIQUE,
        value TEXT DEFAULT '0x0'
    )");
    
    // Insert default admin if not exists
    $check = $db->querySingle("SELECT id FROM users WHERE username = 'admin1'");
    if (!$check) {
        $hashed = password_hash('admin2010', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password) VALUES ('admin1', '$hashed')");
    }
    
    // Insert default offsets if not exists
    $offsetNames = [
        'get_main', 'get_transform', 'get_transformNode', 'WorldToViewpoint',
        'get_position', 'Team', 'Local', 'get_HP', 'get_maxHP', 'get_IsDieing',
        'get_IsVisible', 'GetLocalPlayer', 'CurrentMatch', 'Camera_main',
        'GetRotation', 'get_isLocalTeam', 'get_IsSighting', 'get_IsFiring',
        'WorldToScreenPoint', 'GetHeadPositions', 'Component_GetTransform',
        'GetForward', 'Player_GetHeadCollider', 'Transform_GetPosition',
        'GetAnimator', 'Physics_Raycast', 'set_aim', 'HipPosition',
        'LeftShoulderPosition', 'RightShoulderPosition', 'LeftAnklePosition',
        'RightAnklePosition', 'LeftToePosition', 'RightToePosition',
        'LeftHandPosition', 'RightHandPosition', 'RightForeArmPosition',
        'LeftForeArmPosition', 'CameraMain', 'IsClientBot', 'IsAvatarInit',
        'MatchPlayers'
    ];
    foreach ($offsetNames as $name) {
        $exists = $db->querySingle("SELECT id FROM offsets WHERE name = '$name'");
        if (!$exists) {
            $db->exec("INSERT INTO offsets (name, value) VALUES ('$name', '0x0')");
        }
    }
    
    return $db;
}

// ============================================================
// CRYPTO FUNCTIONS (same as load.mm)
// ============================================================
function generateRandomIv() { return openssl_random_pseudo_bytes(16); }
function generateRandomSalt() { return openssl_random_pseudo_bytes(16); }

function encryptAndPackage($plaintext, $key) {
    $salt = generateRandomSalt();
    $iv = generateRandomIv();
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return false;
    $mac_data = $salt . $iv . $cipher;
    $mac = hash_hmac('sha256', $mac_data, $key, true);
    $packaged = $salt . $iv . $cipher . $mac;
    return base64_encode($packaged);
}

function decryptAndValidate($base64Data, $key) {
    $data = base64_decode($base64Data);
    if (strlen($data) < 80) return false;
    $salt = substr($data, 0, 16);
    $iv = substr($data, 16, 16);
    $cipher_len = strlen($data) - 16 - 16 - 32;
    $cipher = substr($data, 32, $cipher_len);
    $mac = substr($data, 32 + $cipher_len, 32);
    
    $mac_data = $salt . $iv . $cipher;
    $computed = hash_hmac('sha256', $mac_data, $key, true);
    if ($mac !== $computed) return false;
    
    $decrypted = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) return false;
    return $decrypted;
}

function generateKey() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $random = '';
    for ($i = 0; $i < KEY_LENGTH; $i++) {
        $random .= $chars[rand(0, strlen($chars) - 1)];
    }
    return KEY_PREFIX . $random;
}

// ============================================================
// AUTH FUNCTIONS
// ============================================================
function generateToken() {
    return bin2hex(openssl_random_pseudo_bytes(32));
}

function verifyToken($token) {
    $db = getDB();
    $result = $db->querySingle("SELECT username FROM users WHERE token = '$token'", true);
    return $result ? $result['username'] : false;
}

// ============================================================
// HANDLE REQUESTS
// ============================================================
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';
$token = $input['token'] ?? '';
$db = getDB();

$response = ['success' => false, 'message' => 'Invalid action'];

// === LOGIN ===
if ($action === 'login') {
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';
    
    if ($username === ADMIN_USER && $password === ADMIN_PASS) {
        $token = generateToken();
        $db->exec("UPDATE users SET token = '$token' WHERE username = '$username'");
        $response = ['success' => true, 'token' => $token, 'user' => $username];
    } else {
        $response = ['success' => false, 'message' => 'Invalid credentials'];
    }
}

// === AUTHENTICATED ACTIONS ===
if ($token) {
    $user = verifyToken($token);
    if ($user) {
        
        // === STATS ===
        if ($action === 'stats') {
            $total = $db->querySingle("SELECT COUNT(*) FROM keys");
            $active = $db->querySingle("SELECT COUNT(*) FROM keys WHERE active = 1 AND expired = 0");
            $expired = $db->querySingle("SELECT COUNT(*) FROM keys WHERE expired = 1");
            $response = ['success' => true, 'total' => $total, 'active' => $active, 'expired' => $expired];
        }
        
        // === LIST KEYS ===
        elseif ($action === 'list_keys') {
            $result = $db->query("SELECT * FROM keys ORDER BY id DESC");
            $keys = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $keys[] = $row;
            }
            $response = ['success' => true, 'keys' => $keys];
        }
        
        // === CREATE KEY ===
        elseif ($action === 'create_key') {
            $custom = $input['custom_key'] ?? '';
            $key = !empty($custom) ? $custom : generateKey();
            $days = intval($input['expiry_days'] ?? 30);
            $expires = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            $exists = $db->querySingle("SELECT id FROM keys WHERE key_value = '$key'");
            if ($exists) {
                $response = ['success' => false, 'message' => 'Key already exists'];
            } else {
                $db->exec("INSERT INTO keys (key_value, expires_at) VALUES ('$key', '$expires')");
                $response = ['success' => true, 'key' => $key];
            }
        }
        
        // === DELETE KEY ===
        elseif ($action === 'delete_key') {
            $key = $input['key'] ?? '';
            $db->exec("DELETE FROM keys WHERE key_value = '$key'");
            $response = ['success' => true];
        }
        
        // === GET OFFSETS ===
        elseif ($action === 'get_offsets') {
            $result = $db->query("SELECT name, value FROM offsets");
            $offsets = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $offsets[$row['name']] = $row['value'];
            }
            $response = ['success' => true, 'offsets' => $offsets];
        }
        
        // === SAVE OFFSETS ===
        elseif ($action === 'save_offsets') {
            $offsets = $input['offsets'] ?? [];
            foreach ($offsets as $name => $value) {
                $db->exec("UPDATE offsets SET value = '$value' WHERE name = '$name'");
            }
            $response = ['success' => true];
        }
    } else {
        $response = ['success' => false, 'message' => 'Invalid token'];
    }
}

echo json_encode($response);
?>
