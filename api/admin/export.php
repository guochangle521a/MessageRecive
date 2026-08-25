<?php
/**
 * 数据导出 - CSV
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Response.php';

Response::handleCors();
Database::getInstance()->initTables();

$user = Auth::requireLogin();
$db = Database::getInstance();

$source = trim($_GET['source'] ?? '');
$type = trim($_GET['type'] ?? '');
$keyword = trim($_GET['keyword'] ?? '');
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';

// 来源权限过滤
$allowedSources = Auth::getUserSources($user);

$where = [];
$params = [];

if ($source !== '') {
    $where[] = 'm.source = :src';
    $params[':src'] = $source;
}
if ($type !== '') { $where[] = 'm.type = :type'; $params[':type'] = $type; }
if ($keyword !== '') {
    $where[] = '(m.name LIKE :kw OR m.phone LIKE :kw OR m.email LIKE :kw OR m.company LIKE :kw OR m.remark LIKE :kw OR m.extra_data LIKE :kw)';
    $params[':kw'] = '%' . $keyword . '%';
}
if ($status !== '') {
    $where[] = 'm.status = :st';
    $params[':st'] = intval($status);
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
        $where[] = '1=0';
    } else {
        $placeholders = [];
        foreach ($allowedSources as $i => $src) {
            $key = ':asrc' . $i;
            $placeholders[] = $key;
            $params[$key] = $src;
        }
        $where[] = 'm.source IN (' . implode(',', $placeholders) . ')';
    }
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$data = $db->query(
    "SELECT m.*, sd.name as status_name
     FROM messages m
     LEFT JOIN status_dict sd ON sd.code = m.status
     $whereSQL
     ORDER BY m.created_at DESC",
    $params
);

// 输出 CSV
header('Access-Control-Allow-Origin: *');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="messages_export_' . date('Ymd_His') . '.csv"');
header('Cache-Control: no-cache');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// 表头
fputcsv($out, ['ID', '业务类型', '姓名', '电话', '邮箱', '公司/组织', '国家/地区', '详细内容', '扩展字段(JSON)', '提交页面', '来源', '状态', '处理记录', '处理人', '提交时间', '处理时间', '操作人']);

foreach ($data as $row) {
    fputcsv($out, [
        $row['id'],
        $row['type'] ?? '',
        $row['name'],
        $row['phone'],
        $row['email'] ?? '',
        $row['company'] ?? '',
        $row['country'] ?? '',
        $row['remark'] ?? '',
        $row['extra_data'] ?? '',
        $row['source_url'] ?? '',
        $row['source'],
        $row['status_name'] ?? $row['status'],
        $row['handle_record'] ?? '',
        $row['handler'] ?? '',
        $row['created_at'] ?? '',
        $row['handled_at'] ?? '',
        $row['handled_by'] ?? ''
    ]);
}

fclose($out);
exit;
