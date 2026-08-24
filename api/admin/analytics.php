<?php
require_once __DIR__.'/../../config/database.php'; require_once __DIR__.'/../../src/Database.php'; require_once __DIR__.'/../../src/Response.php'; require_once __DIR__.'/../../src/Auth.php';
Response::handleCors(); $db=Database::getInstance(); $db->initTables(); $user=Auth::requireLogin();
$siteId=(int)($_GET['site_id']??0); if(!$siteId||!Auth::canAccessSite($user,$siteId)) Response::error(403,'无权访问该网站',403);
$to=$_GET['end']??gmdate('Y-m-d'); $from=$_GET['start']??gmdate('Y-m-d',strtotime('-29 days'));
$daily=$db->query('SELECT metric_date,pv,uv,sessions,bot_events,data_as_of,definition_version FROM daily_site_metrics WHERE site_id=:sid AND metric_date BETWEEN :a AND :b ORDER BY metric_date',[':sid'=>$siteId,':a'=>$from,':b'=>$to]);
$totals=$db->queryOne("SELECT COUNT(*) pv,COUNT(DISTINCT visitor_hash) uv,COUNT(DISTINCT session_id) sessions FROM analytics_events WHERE site_id=:sid AND is_bot=0 AND event_type='page_view' AND occurred_at>=:a AND occurred_at<DATE_ADD(:b,INTERVAL 1 DAY)",[':sid'=>$siteId,':a'=>$from,':b'=>$to]);
$pages=$db->query("SELECT page_path,MAX(page_title) page_title,COUNT(*) pv,COUNT(DISTINCT visitor_hash) uv FROM analytics_events WHERE site_id=:sid AND is_bot=0 AND event_type='page_view' AND occurred_at>=:a AND occurred_at<DATE_ADD(:b,INTERVAL 1 DAY) GROUP BY page_path ORDER BY pv DESC LIMIT 100",[':sid'=>$siteId,':a'=>$from,':b'=>$to]);
$sources=$db->query("SELECT source,COUNT(*) sessions FROM analytics_sessions WHERE site_id=:sid AND is_bot=0 AND started_at>=:a AND started_at<DATE_ADD(:b,INTERVAL 1 DAY) GROUP BY source ORDER BY sessions DESC",[':sid'=>$siteId,':a'=>$from,':b'=>$to]);
Response::success(['range'=>['start'=>$from,'end'=>$to],'totals'=>array_map('intval',$totals?:['pv'=>0,'uv'=>0,'sessions'=>0]),'daily'=>$daily,'pages'=>$pages,'sources'=>$sources,'session_timeout_seconds'=>TRACK_SESSION_TIMEOUT,'definition_version'=>'1.0']);
