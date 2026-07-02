<?php
// includes/config.php - Cấu hình

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', ROOT_PATH . '/data');
define('DB_PATH', DATA_PATH . '/license.db');

// AES Key - KHỚP VỚI CODE C++
define('AES_KEY', hex2bin('A1B2C3D4E5F60718293A4B5C6D7E8F9012233445566778899AABBCCDDEEFF001'));

// Tự động tạo thư mục data
if (!file_exists(DATA_PATH)) {
    mkdir(DATA_PATH, 0777, true);
    chmod(DATA_PATH, 0777);
}

// Hàm lấy database
function getDB() {
    if (!file_exists(DB_PATH)) {
        touch(DB_PATH);
        chmod(DB_PATH, 0666);
    }
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    return $db;
}
