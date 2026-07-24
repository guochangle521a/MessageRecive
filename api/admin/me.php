<?php
/**
 * 当前用户信息 / 退出登录
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Response::handleCors();

// 退出登录
$action = $_GET['action'] ?? '';
if ($action === 'logout') {
    // 获取 Token
    $token = '';
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $token = trim($m[1]);
    }
    Auth::logout($token);
    Response::success(null, '已退出登录');
}

// 获取用户信息
$user = Auth::requireLogin();
Response::success([
    'id'       => $user['id'],
    'username' => $user['username'],
    'role'     => $user['role'],
    'real_name'=> $user['real_name']
]);
