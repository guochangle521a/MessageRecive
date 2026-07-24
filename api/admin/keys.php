<?php
/**
 * API Key 管理 - 仅管理员可操作
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Response::handleCors();

Database::getInstance()->initTables();

$user = Auth::requireAdmin();
$db   = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

// --- GET: 获取所有 API Keys ---
if ($method === 'GET') {
    $keys = $db->query(
        "SELECT k.*, (SELECT COUNT(*) FROM messages m WHERE m.api_key = k.api_key) as msg_count
         FROM api_keys k ORDER BY k.created_at DESC"
    );
    Response::success($keys);
}

// --- POST: 新增 API Key（只需来源名称） ---
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $siteName = trim($input['site_name'] ?? '');

    if ($siteName === '') {
        Response::error(400, '请输入来源名称');
    }

    // 生成唯一 Key
    $apiKey = 'sk_' . substr(bin2hex(random_bytes(12)), 0, 24);

    $db->execute('INSERT INTO api_keys (api_key, site_name) VALUES (:k, :s)', [
        ':k' => $apiKey,
        ':s' => $siteName
    ]);

    $newKey = $db->queryOne('SELECT * FROM api_keys WHERE api_key = :k', [':k' => $apiKey]);
    Response::success($newKey, 'API Key 创建成功');
}

// --- PUT: 切换启用/禁用 ---
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);

    if ($id <= 0) {
        Response::error(400, '缺少ID');
    }

    $key = $db->queryOne('SELECT * FROM api_keys WHERE id = :id', [':id' => $id]);
    if (!$key) {
        Response::error(404, 'API Key 不存在', 404);
    }

    $newActive = $key['is_active'] ? 0 : 1;
    $db->execute('UPDATE api_keys SET is_active = :a WHERE id = :id', [
        ':a' => $newActive,
        ':id' => $id
    ]);

    Response::success(['is_active' => $newActive], $newActive ? '已启用' : '已禁用');
}

// --- DELETE: 删除 API Key ---
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);

    if ($id <= 0) {
        Response::error(400, '缺少ID');
    }

    $db->execute('DELETE FROM api_keys WHERE id = :id', [':id' => $id]);
    Response::success(null, '已删除');
}
