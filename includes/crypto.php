<?php
// includes/crypto.php - Hàm mã hóa (KHỚP VỚI CODE C++)

function encrypt_package($data, $key) {
    if (is_array($data) || is_object($data)) {
        $data = json_encode($data);
    }
    
    $salt = random_bytes(16);
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return false;
    
    $mac_data = $salt . $iv . $cipher;
    $mac = hash_hmac('sha256', $mac_data, $key, true);
    
    return base64_encode($salt . $iv . $cipher . $mac);
}

function decrypt_package($base64_data, $key) {
    $data = base64_decode($base64_data);
    if ($data === false || strlen($data) < 64) return null;
    
    $salt = substr($data, 0, 16);
    $iv = substr($data, 16, 16);
    $cipher_len = strlen($data) - 16 - 16 - 32;
    $cipher = substr($data, 32, $cipher_len);
    $mac = substr($data, 32 + $cipher_len);
    
    $mac_data = $salt . $iv . $cipher;
    $expected_mac = hash_hmac('sha256', $mac_data, $key, true);
    if (!hash_equals($expected_mac, $mac)) return null;
    
    $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) return null;
    
    return json_decode($decrypted, true);
}
