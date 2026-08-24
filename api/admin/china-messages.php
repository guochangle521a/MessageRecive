<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Logger.php';

Response::handleCors();
Database::getInstance()->initTables();
$user = Auth::requireLogin();
if (!Auth::canViewChina($user)) Response::error(403, '无国内官网留言权限', 403);
$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = intval($_GET['id'] ?? 0);
    if ($id > 0) {
        $row = $db->queryOne('SELECT m.*, sd.name status_name, sd.color status_color FROM china_website_messages m LEFT JOIN status_dict sd ON sd.code=m.status WHERE m.id=:id', [':id'=>$id]);
        if (!$row) Response::error(404, '留言不存在', 404);
        Response::success($row);
    }
    $page=max(1,intval($_GET['page']??1)); $size=max(1,min(100,intval($_GET['page_size']??20)));
    $keyword=trim($_GET['keyword']??''); $status=$_GET['status']??''; $from=trim($_GET['date_from']??''); $to=trim($_GET['date_to']??'');
    $where=[]; $params=[];
    if ($keyword!=='') { $where[]='(m.name LIKE :kw OR m.phone LIKE :kw OR m.email LIKE :kw OR m.company LIKE :kw OR m.region LIKE :kw OR m.remark LIKE :kw)'; $params[':kw']='%'.$keyword.'%'; }
    if ($status!=='') { $where[]='m.status=:st'; $params[':st']=intval($status); }
    if ($from!=='') { $where[]='m.created_at>=:df'; $params[':df']=$from; }
    if ($to!=='') { $where[]='m.created_at<=:dt'; $params[':dt']=$to.' 23:59:59'; }
    $whereSql=$where?'WHERE '.implode(' AND ',$where):'';
    $total=intval($db->queryOne("SELECT COUNT(*) cnt FROM china_website_messages m $whereSql",$params)['cnt']);
    $offset=($page-1)*$size;
    $list=$db->query("SELECT m.*, sd.name status_name, sd.color status_color FROM china_website_messages m LEFT JOIN status_dict sd ON sd.code=m.status $whereSql ORDER BY m.created_at DESC LIMIT $size OFFSET $offset",$params);
    $stats=$db->queryOne("SELECT COUNT(*) total,SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) new_count,SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) contacted_count,SUM(CASE WHEN status=2 THEN 1 ELSE 0 END) deal_count FROM china_website_messages m $whereSql",$params);
    Response::success(['list'=>$list,'total'=>$total,'page'=>$page,'page_size'=>$size,'total_pages'=>(int)ceil($total/$size),'stats'=>$stats]);
}

if ($method === 'PUT') {
    $input=json_decode(file_get_contents('php://input'),true)?:[]; $id=intval($input['id']??0);
    if ($id<=0 || !$db->queryOne('SELECT id FROM china_website_messages WHERE id=:id',[':id'=>$id])) Response::error(404,'留言不存在',404);
    $sets=[]; $params=[':id'=>$id,':by'=>$user['real_name']??$user['username']];
    if (isset($input['status'])) { $sets[]='status=:st'; $params[':st']=intval($input['status']); }
    if (isset($input['handler'])) { $sets[]='handler=:handler'; $params[':handler']=trim($input['handler']); }
    if (isset($input['handle_record'])) { $sets[]='handle_record=:record'; $params[':record']=$input['handle_record']; }
    if (isset($input['validity_status'])) { $v=(string)$input['validity_status']; if(!in_array($v,['pending','valid','spam'],true)) Response::error(400,'询盘判断状态无效'); $sets[]='validity_status=:validity';$sets[]='validity_decided_at=UTC_TIMESTAMP()';$sets[]='validity_decided_by=:vby';$params[':validity']=$v;$params[':vby']=$user['id']; }
    if (isset($input['validity_note'])) { $sets[]='validity_note=:vnote';$params[':vnote']=trim((string)$input['validity_note']); }
    if ($sets) { $sets[]="handled_at=UTC_TIMESTAMP()"; $sets[]='handled_by=:by'; $db->execute('UPDATE china_website_messages SET '.implode(',',$sets).' WHERE id=:id',$params); }
    Response::success($db->queryOne('SELECT * FROM china_website_messages WHERE id=:id',[':id'=>$id]),'更新成功');
}

if ($method === 'POST') {
    $input=json_decode(file_get_contents('php://input'),true)?:[]; $ids=array_values(array_filter(array_map('intval',$input['ids']??[]))); $status=intval($input['status']??-1);
    if (!$ids || $status<0) Response::error(400,'参数不完整');
    $marks=implode(',',array_fill(0,count($ids),'?'));
    $stmt=$db->getPdo()->prepare("UPDATE china_website_messages SET status=?,handled_at=UTC_TIMESTAMP(),handled_by=? WHERE id IN ($marks)");
    $stmt->execute(array_merge([$status,$user['real_name']??$user['username']],$ids));
    Response::success(null,'批量更新成功');
}
Response::error(405,'不支持的请求方法',405);
