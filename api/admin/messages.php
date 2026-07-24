<?php
/**
 * 留言管理 API - CRUD + 批量操作
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Logger.php';

Response::handleCors();

// 初始化数据库
Database::getInstance()->initTables();

$user = Auth::requireLogin();
$method = $_SERVER['REQUEST_METHOD'];

function requireMessageSourceAccess(array $user, array $message): void {
    $allowedSources = Auth::getUserSources($user);
    if ($allowedSources !== null && !in_array($message['source'], $allowedSources, true)) {
        Response::error(403, '无权限操作该留言', 403);
    }
}

// --- GET: 获取留言列表 / 单条详情 ---
if ($method === 'GET') {
    // 单条留言详情
    $singleId = intval($_GET['id'] ?? 0);
    if ($singleId > 0) {
        $db = Database::getInstance();
        $msg = $db->queryOne(
            'SELECT m.*, sd.name as status_name, sd.color as status_color
             FROM messages m
             LEFT JOIN status_dict sd ON sd.code = m.status
             WHERE m.id = :id',
            [':id' => $singleId]
        );
        if (!$msg) {
            Response::error(404, '留言不存在', 404);
        }
        requireMessageSourceAccess($user, $msg);
        Response::success($msg);
    }

    $page     = max(1, intval($_GET['page'] ?? 1));
    $pageSize = max(1, min(100, intval($_GET['page_size'] ?? 20)));
    $keyword  = trim($_GET['keyword'] ?? '');
    $source   = trim($_GET['source'] ?? '');
    $status   = $_GET['status'] ?? '';
    $handler  = trim($_GET['handler'] ?? '');
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo   = $_GET['date_to'] ?? '';

    $db = Database::getInstance();

    // 非管理员用户只能看授权来源
    $allowedSources = Auth::getUserSources($user);

    $where = [];
    $params = [];

    if ($keyword !== '') {
        $where[] = '(m.name LIKE :kw OR m.phone LIKE :kw2 OR m.email LIKE :kw3 OR m.remark LIKE :kw4 OR m.handler LIKE :kw5)';
        $kw = '%' . $keyword . '%';
        $params[':kw'] = $kw;
        $params[':kw2'] = $kw;
        $params[':kw3'] = $kw;
        $params[':kw4'] = $kw;
        $params[':kw5'] = $kw;
    }
    if ($source !== '') {
        $where[] = 'm.source = :src';
        $params[':src'] = $source;
    }
    if ($status !== '') {
        $where[] = 'm.status = :st';
        $params[':st'] = intval($status);
    }
    if ($handler !== '') {
        $where[] = 'm.handler LIKE :hdl';
        $params[':hdl'] = '%' . $handler . '%';
    }
    if ($dateFrom !== '') {
        $where[] = 'm.created_at >= :df';
        $params[':df'] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[] = 'm.created_at <= :dt';
        $params[':dt'] = $dateTo . ' 23:59:59';
    }
    if ($allowedSources !== null) {
        if (empty($allowedSources)) {
            // 无可见来源，返回空
            Response::success([
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => 0,
                'stats' => ['total' => 0, 'new_count' => 0, 'deal_count' => 0, 'contacted_count' => 0]
            ]);
        }
        $placeholders = [];
        foreach ($allowedSources as $i => $src) {
            $key = ':asrc' . $i;
            $placeholders[] = $key;
            $params[$key] = $src;
        }
        $where[] = 'm.source IN (' . implode(',', $placeholders) . ')';
    }

    $whereSQL = '';
    if (!empty($where)) {
        $whereSQL = 'WHERE ' . implode(' AND ', $where);
    }

    // 总数
    $total = $db->queryOne("SELECT COUNT(*) as cnt FROM messages m $whereSQL", $params)['cnt'];

    // 列表 - 联表 status_dict 获取状态名称和颜色
    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT m.*, sd.name as status_name, sd.color as status_color
            FROM messages m
            LEFT JOIN status_dict sd ON sd.code = m.status
            $whereSQL
            ORDER BY m.created_at DESC
            LIMIT $pageSize OFFSET $offset";
    $list = $db->query($sql, $params);

    // 汇总统计
    $statsSQL = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN m.status = 0 THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN m.status = 2 THEN 1 ELSE 0 END) as deal_count,
        SUM(CASE WHEN m.status = 1 THEN 1 ELSE 0 END) as contacted_count
        FROM messages m $whereSQL";
    $stats = $db->queryOne($statsSQL, $params);

    Response::success([
        'list'     => $list,
        'total'    => intval($total),
        'page'     => $page,
        'page_size'=> $pageSize,
        'total_pages' => (int)ceil(intval($total) / $pageSize),
        'stats'    => $stats ?: ['total' => 0, 'new_count' => 0, 'deal_count' => 0, 'contacted_count' => 0]
    ]);
}

// --- PUT: 更新留言（状态/处理记录/处理人） ---
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $messageId = intval($input['id'] ?? 0);

    if ($messageId <= 0) {
        Response::error(400, '缺少留言ID');
    }

    $db = Database::getInstance();
    $msg = $db->queryOne('SELECT * FROM messages WHERE id = :id', [':id' => $messageId]);
    if (!$msg) {
        Response::error(404, '留言不存在', 404);
    }

    requireMessageSourceAccess($user, $msg);

    $updates = [];
    $updateParams = [];

    if (isset($input['status'])) {
        $updates[] = 'status = :status';
        $updateParams[':status'] = intval($input['status']);
    }
    if (isset($input['handle_record'])) {
        $updates[] = 'handle_record = :hr';
        $updateParams[':hr'] = $input['handle_record'];
    }
    if (isset($input['handler'])) {
        $updates[] = 'handler = :h';
        $updateParams[':h'] = trim($input['handler']);
    }

    if (!empty($updates)) {
        $updates[] = 'handled_at = datetime(\'now\',\'localtime\')';
        $updates[] = 'handled_by = :hb';
        $updateParams[':hb'] = $user['real_name'] ?? $user['username'];

        $sql = 'UPDATE messages SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $updateParams[':id'] = $messageId;
        $db->execute($sql, $updateParams);
    }

    $updated = $db->queryOne(
        'SELECT m.*, sd.name as status_name, sd.color as status_color
         FROM messages m LEFT JOIN status_dict sd ON sd.code = m.status
         WHERE m.id = :id',
        [':id' => $messageId]
    );

    Logger::log('message_update', [
        'id'     => $messageId,
        'status' => $input['status'] ?? null,
        'by'     => $user['username']
    ]);

    Response::success($updated, '更新成功');
}

// --- POST: 批量更新状态 ---
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $ids    = $input['ids'] ?? [];
    $status = intval($input['status'] ?? -1);

    if (empty($ids) || $status < 0) {
        Response::error(400, '参数不完整');
    }

    $db = Database::getInstance();
    $placeholders = [];
    $params = [':st' => $status, ':hb' => $user['real_name'] ?? $user['username']];
    $scopeParams = [];
    foreach ($ids as $i => $id) {
        $key = ':id' . $i;
        $placeholders[] = $key;
        $idValue = intval($id);
        $params[$key] = $idValue;
        $scopeParams[$key] = $idValue;
    }

    $allowedSources = Auth::getUserSources($user);
    if ($allowedSources !== null) {
        if (empty($allowedSources)) {
            Response::error(403, '无权限操作留言', 403);
        }
        $sourcePlaceholders = [];
        foreach ($allowedSources as $i => $src) {
            $key = ':src' . $i;
            $sourcePlaceholders[] = $key;
            $params[$key] = $src;
            $scopeParams[$key] = $src;
        }
        $permissionSQL = ' AND source IN (' . implode(',', $sourcePlaceholders) . ')';
    } else {
        $permissionSQL = '';
    }

    $authorizedCount = $db->queryOne(
        'SELECT COUNT(*) as cnt FROM messages WHERE id IN (' . implode(',', $placeholders) . ')' . $permissionSQL,
        $scopeParams
    )['cnt'];
    if (intval($authorizedCount) !== count($placeholders)) {
        Response::error(403, '包含无权限操作的留言', 403);
    }

    $db->execute(
        "UPDATE messages SET status = :st, handled_at = datetime('now','localtime'), handled_by = :hb WHERE id IN (" . implode(',', $placeholders) . ")" . $permissionSQL,
        $params
    );

    Logger::log('message_batch_update', [
        'ids'    => $ids,
        'status' => $status,
        'by'     => $user['username']
    ]);

    Response::success(null, '批量更新成功');
}
