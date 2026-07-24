<?php
/**
 * 留言接收 API（核心对外接口）
 * POST /api/message.php
 *
 * 各网站前端可直接调用此接口提交留言
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/Auth.php';
require_once __DIR__ . '/../src/RateLimiter.php';
require_once __DIR__ . '/../src/Logger.php';

// 处理 CORS 预检
Response::handleCors();

// 仅允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error(2000, '仅支持POST请求', 405);
}

// 获取请求体
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!$data) {
    Response::error(1000, '请求体格式错误，请使用JSON格式');
}

// XSS 过滤
$data = Validator::xss($data);

// 初始化数据库表（首次自动创建）
Database::getInstance()->initTables();

// 参数校验
Validator::validateMessage($data);

// 校验 API Key，获取来源名称
$apiKeyInfo = Auth::validateApiKey($data['api_key']);
if (!$apiKeyInfo) {
    Response::error(2001, 'API Key无效或已禁用');
}

// 频率限制
$clientIP = RateLimiter::getClientIP();
if (!RateLimiter::check($clientIP)) {
    Response::error(2002, '请求过于频繁，请稍后再试', 429);
}

// 防重复提交
if (!RateLimiter::checkDuplicate($clientIP, $data['phone'])) {
    Response::error(2003, '请勿重复提交，60秒内同一手机号仅可提交一次');
}

// 来源名称以 API Key 绑定站点为准，不能信任客户端传入的 source。
$source = $apiKeyInfo['site_name'];

// 写入数据库
$db = Database::getInstance();
try {
    $db->execute(
        'INSERT INTO messages (name, phone, email, remark, source, api_key, ip_address, user_agent, status)
         VALUES (:name, :phone, :email, :remark, :source, :api_key, :ip, :ua, 0)',
        [
            ':name'    => trim($data['name']),
            ':phone'   => trim($data['phone']),
            ':email'   => isset($data['email']) ? trim($data['email']) : null,
            ':remark'  => isset($data['remark']) ? trim($data['remark']) : null,
            ':source'  => $source,
            ':api_key' => $data['api_key'],
            ':ip'      => $clientIP,
            ':ua'      => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );

    $newId = $db->lastInsertId();

    // 记录日志
    Logger::log('submit', [
        'id'     => $newId,
        'source' => $source,
        'phone'  => trim($data['phone'])
    ]);

    Response::success(['id' => $newId], '提交成功');
} catch (Exception $e) {
    Logger::error('submit', ['error' => $e->getMessage(), 'source' => $source ?? 'unknown']);
    Response::error(5000, '服务器内部错误', 500);
}
