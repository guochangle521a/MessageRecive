<?php
/**
 * 状态字典维护
 * GET: 获取状态列表
 * POST: 新增状态
 * PUT: 编辑状态
 * DELETE: 删除状态
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

// --- GET: 状态列表 ---
if ($method === 'GET') {
    $list = $db->query('SELECT * FROM status_dict WHERE is_active = 1 ORDER BY sort_order, id');
    Response::success($list);
}

// --- POST: 新增状态 ---
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name  = trim($input['name'] ?? '');
    $color = trim($input['color'] ?? '#e8710a');

    if ($name === '') {
        Response::error(400, '请输入状态名称');
    }

    // 自动分配 code
    $maxCode = $db->queryOne('SELECT MAX(code) as mc FROM status_dict')['mc'] ?? 0;
    $newCode = $maxCode + 1;

    $db->execute('INSERT INTO status_dict (code, name, color, sort_order) VALUES (:c, :n, :cl, :s)', [
        ':c' => $newCode,
        ':n' => $name,
        ':cl' => $color,
        ':s' => $newCode
    ]);

    $newStatus = $db->queryOne('SELECT * FROM status_dict WHERE code = :c', [':c' => $newCode]);
    Response::success($newStatus, '状态添加成功');
}

// --- PUT: 编辑状态 ---
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code  = intval($input['code'] ?? -1);
    $name  = trim($input['name'] ?? '');
    $color = trim($input['color'] ?? '');

    if ($code < 0 || $name === '') {
        Response::error(400, '参数不完整');
    }

    $db->execute('UPDATE status_dict SET name = :n, color = :c WHERE code = :code', [
        ':n' => $name,
        ':c' => $color,
        ':code' => $code
    ]);

    $updated = $db->queryOne('SELECT * FROM status_dict WHERE code = :code', [':code' => $code]);
    Response::success($updated, '状态更新成功');
}

// --- DELETE: 删除状态（软删：禁用） ---
if ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code  = intval($input['code'] ?? $_GET['code'] ?? -1);

    if ($code < 0) {
        Response::error(400, '缺少状态code');
    }

    $db->execute('UPDATE status_dict SET is_active = 0 WHERE code = :code', [':code' => $code]);
    Response::success(null, '状态已删除');
}
