<?php
// api/index.php - Router API

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Route
switch ($action) {
    case 'stats':
    case 'recent':
    case 'keys':
    case 'generate':
    case 'revoke':
    case 'extend':
        require_once __DIR__ . '/keys.php';
        break;
    case 'offsets':
        require_once __DIR__ . '/offsets.php';
        break;
    case 'logs':
        require_once __DIR__ . '/logs.php';
        break;
    case 'auth':
        require_once __DIR__ . '/auth.php';
        break;
    case 'verify':
        require_once __DIR__ . '/verify.php';
        break;
    default:
        json_response(['success' => false, 'error' => 'Invalid action']);
}
