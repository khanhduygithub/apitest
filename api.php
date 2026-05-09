<?php
function decryptData($base64) {
    $key = hex2bin('A1B2C3D4E5F60718293A4B5C6D7E8F9012233445566778899AABBCCDDEEFF001');
    $data = base64_decode($base64);
    if (strlen($data) < 16) return null;
    $iv = substr($data, 0, 16);
    $ciphertext = substr($data, 16);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
}

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

// Đọc input đã mã hóa (CHỈ 1 LẦN)
$rawInput = file_get_contents('php://input');
$input = [];
if (!empty($rawInput)) {
    $decrypted = decryptData($rawInput);
    if ($decrypted) {
        $input = json_decode($decrypted, true) ?: [];
    }
}

switch ($action) {
    // ... (giữ nguyên tất cả case bên dưới)
}
