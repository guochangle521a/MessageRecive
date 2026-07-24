<?php
/**
 * 管理员登录 - 返回 Token
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Logger.php';

Response::handleCors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(405, '仅支持POST请求', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    Response::error(400, '请求数据格式错误');
}

$username = trim($input['username'] ?? '');
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    Response::error(400, '请输入用户名和密码');
}

// 确保数据库表存在
Database::getInstance()->initTables();

$result = Auth::login($username, $password);

if ($result['success']) {
    Logger::log('login', ['username' => $username, 'result' => 'success']);
    Response::success($result['data'], $result['msg']);
} else {
    Logger::log('login', ['username' => $username, 'result' => 'failed'], 'WARNING');
    Response::error(401, $result['msg']);
}
