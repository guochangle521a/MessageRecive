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

    $newActive = isset($input['is_active']) ? (intval($input['is_active']) ? 1 : 0) : ($key['is_active'] ? 0 : 1);
    $db->execute('UPDATE api_keys SET is_active = :a WHERE id = :id', [
        ':a' => $newActive,
        ':id' => $id
    ]);

    Response::success(['is_active' => $newActive], $newActive ? '已启用' : '已禁用');
}

Response::error(405, '仅支持查询和启用/停用操作', 405);
