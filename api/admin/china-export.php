<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../src/Database.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Response.php';
Database::getInstance()->initTables();
$user=Auth::requireLogin(); if(!Auth::canViewChina($user)) Response::error(403,'无国内官网留言权限',403);
$db=Database::getInstance(); $keyword=trim($_GET['keyword']??''); $status=$_GET['status']??''; $from=trim($_GET['date_from']??''); $to=trim($_GET['date_to']??'');
$where=[];$params=[];
if($keyword!==''){ $where[]='(name LIKE :kw OR phone LIKE :kw OR email LIKE :kw OR company LIKE :kw OR region LIKE :kw OR remark LIKE :kw)';$params[':kw']='%'.$keyword.'%'; }
if($status!==''){ $where[]='status=:st';$params[':st']=intval($status); } if($from!==''){ $where[]='created_at>=:df';$params[':df']=$from; } if($to!==''){ $where[]='created_at<=:dt';$params[':dt']=$to.' 23:59:59'; }
$sql=$where?'WHERE '.implode(' AND ',$where):''; $rows=$db->query("SELECT * FROM china_website_messages $sql ORDER BY created_at DESC",$params);
header('Content-Type:text/csv;charset=utf-8'); header('Content-Disposition:attachment;filename="china_messages_'.date('Ymd_His').'.csv"'); echo "\xEF\xBB\xBF"; $out=fopen('php://output','w');
fputcsv($out,['ID','类型','所在地区','姓名','公司或机构','联系电话','电子邮箱','需求说明','期望回复方式','提交页面','状态','处理记录','处理人','提交时间']);
foreach($rows as $r) fputcsv($out,[$r['id'],$r['inquiry_type'],$r['region'],$r['name'],$r['company'],$r['phone'],$r['email'],$r['remark'],$r['preferred_contact'],$r['source_url'],$r['status'],$r['handle_record'],$r['handler'],$r['created_at']]);
fclose($out);exit;
