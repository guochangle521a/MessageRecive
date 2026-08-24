<?php
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../src/Database.php';
require_once __DIR__.'/../../src/Response.php';
require_once __DIR__.'/../../src/Auth.php';
Response::handleCors();
$db=Database::getInstance(); $db->initTables(); $user=Auth::requireLogin();
$siteId=(int)($_GET['site_id']??0); if($siteId<=0||!Auth::canAccessSite($user,$siteId)) Response::error(403,'无权访问该网站',403);
$mode=(string)($_GET['mode']??'events');
$page=max(1,(int)($_GET['page']??1)); $size=max(1,min(100,(int)($_GET['page_size']??30))); $offset=($page-1)*$size;
$end=trim((string)($_GET['end']??gmdate('Y-m-d'))); $start=trim((string)($_GET['start']??gmdate('Y-m-d',time()-6*86400)));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)) Response::error(400,'日期格式必须为YYYY-MM-DD');
$params=[':sid'=>$siteId,':start'=>$start,':end'=>$end];

function paged($list,$total,$page,$size,$extra=[]){Response::success(array_merge(['list'=>$list,'total'=>(int)$total,'page'=>$page,'page_size'=>$size,'total_pages'=>(int)ceil($total/$size)],$extra));}

if($mode==='events'){
    $where=["e.site_id=:sid","e.occurred_at>=:start","e.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY)"];
    $keyword=trim((string)($_GET['keyword']??'')); $device=trim((string)($_GET['device']??'')); $source=trim((string)($_GET['source']??'')); $bot=$_GET['is_bot']??'';
    if($keyword!==''){$where[]='(e.page_path LIKE :kw OR e.page_title LIKE :kw OR s.ip_address LIKE :kw OR e.visitor_hash LIKE :kw)';$params[':kw']='%'.$keyword.'%';}
    if(in_array($device,['desktop','mobile','tablet'],true)){$where[]='s.device_type=:device';$params[':device']=$device;}
    if($source!==''){$where[]='s.source=:source';$params[':source']=$source;}
    if($bot==='0'||$bot==='1'){$where[]='e.is_bot=:bot';$params[':bot']=(int)$bot;}
    $w=implode(' AND ',$where);$total=$db->queryOne("SELECT COUNT(*) c FROM analytics_events e LEFT JOIN analytics_sessions s ON s.id=e.session_id WHERE $w",$params)['c'];
    $rows=$db->query("SELECT e.id,e.event_type,e.page_title,e.page_path,e.page_url,e.referrer,e.properties,e.is_bot,e.occurred_at,e.received_at,e.visitor_hash,e.session_id,s.session_key,s.ip_address,s.masked_ip,s.source,s.device_type,COALESCE(NULLIF(s.country_code,''),s.country) country FROM analytics_events e LEFT JOIN analytics_sessions s ON s.id=e.session_id WHERE $w ORDER BY e.occurred_at DESC LIMIT $size OFFSET $offset",$params);
    paged($rows,$total,$page,$size);
}

if($mode==='sessions'){
    $where=["s.site_id=:sid","EXISTS(SELECT 1 FROM analytics_events de WHERE de.session_id=s.id AND de.occurred_at>=:start AND de.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY))"];$keyword=trim((string)($_GET['keyword']??''));$bot=$_GET['is_bot']??'';
    if($keyword!==''){$where[]='(s.ip_address LIKE :kw OR s.visitor_hash LIKE :kw OR s.landing_url LIKE :kw OR s.referrer LIKE :kw OR EXISTS(SELECT 1 FROM analytics_events ke WHERE ke.session_id=s.id AND (ke.page_path LIKE :kw2 OR ke.page_title LIKE :kw3)))';$params[':kw']='%'.$keyword.'%';$params[':kw2']='%'.$keyword.'%';$params[':kw3']='%'.$keyword.'%';}
    if($bot==='0'||$bot==='1'){$where[]='s.is_bot=:bot';$params[':bot']=(int)$bot;}$w=implode(' AND ',$where);
    $total=$db->queryOne("SELECT COUNT(*) c FROM analytics_sessions s WHERE $w",$params)['c'];
    $rows=$db->query("SELECT s.*,(SELECT COUNT(*) FROM analytics_events ce WHERE ce.session_id=s.id) event_count,(SELECT COUNT(DISTINCT pe.page_path) FROM analytics_events pe WHERE pe.session_id=s.id AND pe.event_type='page_view') page_count,TIMESTAMPDIFF(SECOND,s.started_at,s.last_seen_at) duration_seconds FROM analytics_sessions s WHERE $w ORDER BY s.started_at DESC LIMIT $size OFFSET $offset",$params);
    paged($rows,$total,$page,$size);
}

if($mode==='session'){
    $id=(int)($_GET['id']??0);$session=$db->queryOne('SELECT s.*,TIMESTAMPDIFF(SECOND,s.started_at,s.last_seen_at) duration_seconds FROM analytics_sessions s WHERE s.id=:id AND s.site_id=:sid',[':id'=>$id,':sid'=>$siteId]);
    if(!$session) Response::error(404,'会话不存在',404);
    $events=$db->query('SELECT id,event_type,page_title,page_path,page_url,referrer,properties,is_bot,occurred_at FROM analytics_events WHERE session_id=:id ORDER BY occurred_at,id',[':id'=>$id]);
    Response::success(['session'=>$session,'events'=>$events]);
}

if($mode==='visitors'){
    $where=["s.site_id=:sid","EXISTS(SELECT 1 FROM analytics_events de WHERE de.session_id=s.id AND de.occurred_at>=:start AND de.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY))"];$keyword=trim((string)($_GET['keyword']??''));
    if($keyword!==''){$where[]='(s.ip_address LIKE :kw OR s.visitor_hash LIKE :kw OR EXISTS(SELECT 1 FROM analytics_events ke WHERE ke.session_id=s.id AND (ke.page_path LIKE :kw2 OR ke.page_title LIKE :kw3)))';$params[':kw']='%'.$keyword.'%';$params[':kw2']='%'.$keyword.'%';$params[':kw3']='%'.$keyword.'%';}$w=implode(' AND ',$where);
    $count=$db->queryOne("SELECT COUNT(DISTINCT visitor_hash) c FROM analytics_sessions s WHERE $w",$params)['c'];
    $rows=$db->query("SELECT s.visitor_hash,MAX(s.ip_address) ip_address,MAX(s.masked_ip) masked_ip,MIN(s.started_at) first_seen_at,MAX(s.last_seen_at) last_seen_at,COUNT(DISTINCT s.id) session_count,COUNT(e.id) event_count,COUNT(DISTINCT e.page_path) page_count,MAX(s.device_type) device_type,MAX(s.source) source,MAX(s.is_bot) is_bot FROM analytics_sessions s LEFT JOIN analytics_events e ON e.session_id=s.id WHERE $w GROUP BY s.visitor_hash ORDER BY last_seen_at DESC LIMIT $size OFFSET $offset",$params);
    paged($rows,$count,$page,$size);
}

if($mode==='visitor'){
    $hash=trim((string)($_GET['visitor_hash']??''));if(!preg_match('/^[a-f0-9]{64}$/',$hash)) Response::error(400,'访客标识无效');
    $summary=$db->queryOne('SELECT visitor_hash,MAX(ip_address) ip_address,MIN(started_at) first_seen_at,MAX(last_seen_at) last_seen_at,COUNT(*) session_count,MAX(device_type) device_type,MAX(source) source,MAX(is_bot) is_bot FROM analytics_sessions WHERE site_id=:sid AND visitor_hash=:hash GROUP BY visitor_hash',[':sid'=>$siteId,':hash'=>$hash]);
    if(!$summary) Response::error(404,'访客不存在',404);
    $sessions=$db->query('SELECT s.id,s.started_at,s.last_seen_at,s.landing_url,s.referrer,s.source,s.device_type,s.is_bot,COUNT(e.id) event_count FROM analytics_sessions s LEFT JOIN analytics_events e ON e.session_id=s.id WHERE s.site_id=:sid AND s.visitor_hash=:hash GROUP BY s.id ORDER BY s.started_at DESC LIMIT 100',[':sid'=>$siteId,':hash'=>$hash]);
    $pages=$db->query('SELECT e.page_path,MAX(e.page_title) page_title,COUNT(*) pv,MAX(e.occurred_at) last_seen_at FROM analytics_events e WHERE e.site_id=:sid AND e.visitor_hash=:hash GROUP BY e.page_path ORDER BY last_seen_at DESC LIMIT 100',[':sid'=>$siteId,':hash'=>$hash]);
    Response::success(['visitor'=>$summary,'sessions'=>$sessions,'pages'=>$pages]);
}

if($mode==='pages'){
    $where=["e.site_id=:sid","e.is_bot=0","e.event_type='page_view'","e.occurred_at>=:start","e.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY)"];$keyword=trim((string)($_GET['keyword']??''));
    if($keyword!==''){$where[]='(e.page_path LIKE :kw OR e.page_title LIKE :kw)';$params[':kw']='%'.$keyword.'%';}$w=implode(' AND ',$where);
    $count=$db->queryOne("SELECT COUNT(DISTINCT page_path) c FROM analytics_events e WHERE $w",$params)['c'];
    $rows=$db->query("SELECT e.page_path,MAX(e.page_title) page_title,COUNT(*) pv,COUNT(DISTINCT e.visitor_hash) uv,COUNT(DISTINCT e.session_id) sessions,MIN(e.occurred_at) first_seen_at,MAX(e.occurred_at) last_seen_at FROM analytics_events e WHERE $w GROUP BY e.page_path ORDER BY pv DESC LIMIT $size OFFSET $offset",$params);
    paged($rows,$count,$page,$size);
}

if($mode==='page'){
    $path=(string)($_GET['path']??'');if($path==='') Response::error(400,'页面路径不能为空');
    $p=[':sid'=>$siteId,':path'=>$path,':start'=>$start,':end'=>$end];
    $summary=$db->queryOne("SELECT page_path,MAX(page_title) page_title,COUNT(*) pv,COUNT(DISTINCT visitor_hash) uv,COUNT(DISTINCT session_id) sessions,MIN(occurred_at) first_seen_at,MAX(occurred_at) last_seen_at FROM analytics_events WHERE site_id=:sid AND is_bot=0 AND event_type='page_view' AND page_path=:path AND occurred_at>=:start AND occurred_at<DATE_ADD(:end,INTERVAL 1 DAY) GROUP BY page_path",$p);
    if(!$summary) Response::error(404,'所选日期内没有该页面数据',404);
    $daily=$db->query("SELECT DATE(occurred_at) day,COUNT(*) pv,COUNT(DISTINCT visitor_hash) uv FROM analytics_events WHERE site_id=:sid AND is_bot=0 AND event_type='page_view' AND page_path=:path AND occurred_at>=:start AND occurred_at<DATE_ADD(:end,INTERVAL 1 DAY) GROUP BY DATE(occurred_at) ORDER BY day",$p);
    $sources=$db->query("SELECT COALESCE(s.source,'unknown') source,COUNT(*) pv FROM analytics_events e LEFT JOIN analytics_sessions s ON s.id=e.session_id WHERE e.site_id=:sid AND e.is_bot=0 AND e.event_type='page_view' AND e.page_path=:path AND e.occurred_at>=:start AND e.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY) GROUP BY s.source ORDER BY pv DESC",$p);
    $recent=$db->query("SELECT e.occurred_at,e.visitor_hash,e.session_id,s.ip_address,s.source,s.device_type FROM analytics_events e LEFT JOIN analytics_sessions s ON s.id=e.session_id WHERE e.site_id=:sid AND e.is_bot=0 AND e.event_type='page_view' AND e.page_path=:path AND e.occurred_at>=:start AND e.occurred_at<DATE_ADD(:end,INTERVAL 1 DAY) ORDER BY e.occurred_at DESC LIMIT 50",$p);
    Response::success(['summary'=>$summary,'daily'=>$daily,'sources'=>$sources,'recent'=>$recent]);
}
Response::error(400,'不支持的明细类型');
