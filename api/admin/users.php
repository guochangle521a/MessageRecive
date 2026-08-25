<?php
/**
 * 用户管理 - 仅管理员可操作
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

// --- GET: 用户列表 ---
if ($method === 'GET') {
    $users = $db->query('SELECT u.id,u.username,u.role,u.role_id,r.name role_name,u.real_name,u.can_view_china,u.is_active,u.created_at FROM users u LEFT JOIN roles r ON r.id=u.role_id ORDER BY u.id');
    $allSources = array_column(
        $db->query("SELECT site_name FROM api_keys WHERE COALESCE(site_scope,'overseas')='overseas' ORDER BY created_at DESC"),
        'site_name'
    );
    // 附带每个用户的来源权限
    foreach ($users as &$u) {
        $sources = $db->query('SELECT source FROM user_sources WHERE user_id = :uid', [':uid' => $u['id']]);
        $u['sources'] = array_column($sources, 'source');
        $u['all_sources'] = $allSources;
        $u['site_ids'] = array_map('intval', array_column($db->query('SELECT site_id FROM user_sites WHERE user_id=:uid', [':uid'=>$u['id']]), 'site_id'));
        $u['all_sites'] = $db->query('SELECT id,name,domain,category FROM sites WHERE is_active=1 ORDER BY id');
    }
    Response::success([
        'list' => $users,
        'total' => count($users)
    ]);
}

// --- POST: 新增用户 ---
if ($method === 'POST') {
    $input     = json_decode(file_get_contents('php://input'), true);
    $username  = trim($input['username'] ?? '');
    $password  = $input['password'] ?? '';
    $realName  = trim($input['real_name'] ?? '');
    $role      = in_array($input['role'] ?? '', ['admin', 'user']) ? $input['role'] : 'user';
    $sources   = $input['sources'] ?? [];
    $canViewChina = !empty($input['can_view_china']) ? 1 : 0;

    if ($username === '' || $password === '') {
        Response::error(400, '用户名和密码不能为空');
    }
    if (strlen($password) < 6) {
        Response::error(400, '密码至少6位');
    }
    if ($role === 'user' && !$canViewChina && empty(array_filter($sources, function($src) { return trim($src) !== ''; }))) {
        Response::error(400, '普通用户必须至少选择海外来源或国内官网权限');
    }

    // 检查重名
    $exist = $db->queryOne('SELECT id FROM users WHERE username = :u', [':u' => $username]);
    if ($exist) {
        Response::error(400, '用户名已存在');
    }

    $hashedPwd = password_hash($password, PASSWORD_BCRYPT);
    $db->execute('INSERT INTO users (username, password, role, real_name, can_view_china) VALUES (:u, :p, :r, :n, :c)', [
        ':u' => $username,
        ':p' => $hashedPwd,
        ':r' => $role,
        ':n' => $realName,
        ':c' => $canViewChina
    ]);

    $newUserId = $db->lastInsertId();

    // 保存来源权限
    foreach ($sources as $src) {
        if (trim($src) !== '') {
            $db->execute('INSERT IGNORE INTO user_sources (user_id, source) VALUES (:uid, :s)', [
                ':uid' => $newUserId,
                ':s' => trim($src)
            ]);
        }
    }
    if ($canViewChina) $db->execute('INSERT IGNORE INTO user_sites(user_id,site_id) VALUES(:u,2)', [':u'=>$newUserId]);
    if (!empty($sources)) $db->execute('INSERT IGNORE INTO user_sites(user_id,site_id) VALUES(:u,1)', [':u'=>$newUserId]);

    Response::success(['id' => $newUserId], '用户创建成功');
}

// --- PUT: 编辑用户 ---
if ($method === 'PUT') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $editId   = intval($input['id'] ?? 0);
    $realName = trim($input['real_name'] ?? '');
    $role     = in_array($input['role'] ?? '', ['admin', 'user']) ? $input['role'] : 'user';
    $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
    $password = $input['password'] ?? '';
    $sources  = $input['sources'] ?? [];
    $canViewChina = !empty($input['can_view_china']) ? 1 : 0;

    if ($editId <= 0) {
        Response::error(400, '缺少用户ID');
    }

    $editUser = $db->queryOne('SELECT * FROM users WHERE id = :id', [':id' => $editId]);
    if (!$editUser) {
        Response::error(404, '用户不存在', 404);
    }
    if ($role === 'user' && !$canViewChina && empty(array_filter($sources, function($src) { return trim($src) !== ''; }))) {
        Response::error(400, '普通用户必须至少选择海外来源或国内官网权限');
    }

    $updates = 'real_name = :n, role = :r, is_active = :a, can_view_china = :c';
    $params = [
        ':n' => $realName,
        ':r' => $role,
        ':a' => $isActive,
        ':c' => $canViewChina,
        ':id' => $editId
    ];

    if ($password !== '') {
        if (strlen($password) < 6) {
            Response::error(400, '密码至少6位');
        }
        $updates .= ', password = :p';
        $params[':p'] = password_hash($password, PASSWORD_BCRYPT);
    }

    $db->execute("UPDATE users SET $updates WHERE id = :id", $params);

    // 更新来源权限（先删后增）
    $db->execute('DELETE FROM user_sources WHERE user_id = :uid', [':uid' => $editId]);
    $db->execute('DELETE FROM user_sites WHERE user_id = :uid', [':uid' => $editId]);
    foreach ($sources as $src) {
        if (trim($src) !== '') {
            $db->execute('INSERT IGNORE INTO user_sources (user_id, source) VALUES (:uid, :s)', [
                ':uid' => $editId,
                ':s' => trim($src)
            ]);
        }
    }
    if ($canViewChina) $db->execute('INSERT IGNORE INTO user_sites(user_id,site_id) VALUES(:u,2)', [':u'=>$editId]);
    if (!empty($sources)) $db->execute('INSERT IGNORE INTO user_sites(user_id,site_id) VALUES(:u,1)', [':u'=>$editId]);

    Response::success(null, '用户更新成功');
}

// --- DELETE: 删除用户 ---
if ($method === 'DELETE') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $delId  = intval($input['id'] ?? $_GET['id'] ?? 0);

    if ($delId <= 0) {
        Response::error(400, '缺少用户ID');
    }
    if ($delId == 1) {
        Response::error(400, '不能删除超级管理员');
    }

    $db->execute('DELETE FROM users WHERE id = :id', [':id' => $delId]);
    Response::success(null, '用户已删除');
}
