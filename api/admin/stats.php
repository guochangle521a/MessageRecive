<?php
/**
 * 汇总统计 API
 * GET /api/admin/stats.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Response::handleCors();
Database::getInstance()->initTables();

$user = Auth::requireLogin();
$db = Database::getInstance();

$source = trim($_GET['source'] ?? '');
$status = $_GET['status'] ?? '';
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

if ($dateTo === '') {
    $dateTo = date('Y-m-d');
}
if ($dateFrom === '') {
    $dateFrom = date('Y-m-d', strtotime($dateTo . ' -29 days'));
}

$where = ['date(m.created_at) >= :df', 'date(m.created_at) <= :dt'];
$params = [
    ':df' => $dateFrom,
    ':dt' => $dateTo
];

if ($source !== '') {
    $where[] = 'm.source = :src';
    $params[':src'] = $source;
}
if ($status !== '') {
    $where[] = 'm.status = :st';
    $params[':st'] = intval($status);
}

$allowedSources = Auth::getUserSources($user);
if ($allowedSources !== null) {
    if (empty($allowedSources)) {
        Response::success([
            'range' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'totals' => ['total' => 0, 'new_count' => 0, 'contacted_count' => 0, 'deal_count' => 0, 'invalid_count' => 0],
            'daily' => [],
            'by_source' => [],
            'by_status' => []
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

$whereSQL = 'WHERE ' . implode(' AND ', $where);

$totals = $db->queryOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN m.status = 0 THEN 1 ELSE 0 END) as new_count,
        SUM(CASE WHEN m.status = 1 THEN 1 ELSE 0 END) as contacted_count,
        SUM(CASE WHEN m.status = 2 THEN 1 ELSE 0 END) as deal_count,
        SUM(CASE WHEN m.status = 3 THEN 1 ELSE 0 END) as invalid_count
     FROM messages m $whereSQL",
    $params
) ?: [];

$dailyRows = $db->query(
    "SELECT date(m.created_at) as day, COUNT(*) as count
     FROM messages m
     $whereSQL
     GROUP BY date(m.created_at)
     ORDER BY day ASC",
    $params
);
$dailyMap = [];
foreach ($dailyRows as $row) {
    $dailyMap[$row['day']] = intval($row['count']);
}

$daily = [];
$cursor = strtotime($dateFrom);
$end = strtotime($dateTo);
while ($cursor !== false && $cursor <= $end) {
    $day = date('Y-m-d', $cursor);
    $daily[] = ['day' => $day, 'count' => $dailyMap[$day] ?? 0];
    $cursor = strtotime('+1 day', $cursor);
}

$bySource = $db->query(
    "SELECT m.source as name, COUNT(*) as count
     FROM messages m
     $whereSQL
     GROUP BY m.source
     ORDER BY count DESC, m.source ASC",
    $params
);

$byStatus = $db->query(
    "SELECT m.status as code, COALESCE(sd.name, '未知状态') as name, COALESCE(sd.color, '#8892a4') as color, COUNT(*) as count
     FROM messages m
     LEFT JOIN status_dict sd ON sd.code = m.status
     $whereSQL
     GROUP BY m.status, sd.name, sd.color
     ORDER BY count DESC, m.status ASC",
    $params
);

Response::success([
    'range' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
    'totals' => [
        'total' => intval($totals['total'] ?? 0),
        'new_count' => intval($totals['new_count'] ?? 0),
        'contacted_count' => intval($totals['contacted_count'] ?? 0),
        'deal_count' => intval($totals['deal_count'] ?? 0),
        'invalid_count' => intval($totals['invalid_count'] ?? 0)
    ],
    'daily' => $daily,
    'by_source' => array_map(function($row) {
        return ['name' => $row['name'], 'count' => intval($row['count'])];
    }, $bySource),
    'by_status' => array_map(function($row) {
        return [
            'code' => intval($row['code']),
            'name' => $row['name'],
            'color' => $row['color'],
            'count' => intval($row['count'])
        ];
    }, $byStatus)
]);
