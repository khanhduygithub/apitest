<?php
// ============================================================
// Monite API - /check_openid
// ============================================================

header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');

define('DB_FILE', __DIR__ . '/monite.db');
define('AES_KEY', hex2bin('A1B2C3D4E5F60718293A4B5C6D7E8F90'));

function getDB() {
    return new SQLite3(DB_FILE);
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
    return $decrypted;
}

function encryptAndPackage($plaintext, $key) {
    $salt = openssl_random_pseudo_bytes(16);
    $iv = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return false;
    $mac_data = $salt . $iv . $cipher;
    $mac = hash_hmac('sha256', $mac_data, $key, true);
    $packaged = $salt . $iv . $cipher . $mac;
    return base64_encode($packaged);
}

$input = file_get_contents('php://input');
$decrypted = decryptAndValidate($input, AES_KEY);

if (!$decrypted) {
    $response = json_encode(['success' => false]);
    echo encryptAndPackage($response, AES_KEY);
    exit;
}

$data = json_decode($decrypted, true);
$open_id = $data['open_id'] ?? '';

// For demo: always return exists with a UDID
$udid = 'udid_' . bin2hex(openssl_random_pseudo_bytes(8));

$response = json_encode([
    'status' => 'exists',
    'udid' => $udid
]);

echo encryptAndPackage($response, AES_KEY);
?>
