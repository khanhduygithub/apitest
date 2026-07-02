<?php
// ============================================================
// Monite API - /check/freefire/1.0.7
// ============================================================

header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');

define('AES_KEY', hex2bin('A1B2C3D4E5F60718293A4B5C6D7E8F90'));

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

// Always allow - for demo purposes
$response = json_encode([
    'success' => true,
    'allowed' => true
]);

echo encryptAndPackage($response, AES_KEY);
?>
