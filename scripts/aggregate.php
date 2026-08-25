<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php'; require_once __DIR__.'/../src/Database.php';
$db=Database::getInstance(); $db->initTables();
$from=$argv[1]??gmdate('Y-m-d',time()-2*86400); $to=$argv[2]??gmdate('Y-m-d');
$sites=$db->query('SELECT id FROM sites WHERE is_active=1');
$utc=new DateTimeZone('UTC'); $startDate=new DateTimeImmutable($from,$utc); $endDate=new DateTimeImmutable($to,$utc);
foreach($sites as $site){
 for($cursor=$startDate;$cursor<=$endDate;$cursor=$cursor->modify('+1 day')){
  $day=$cursor->format('Y-m-d'); $next=$cursor->modify('+1 day')->format('Y-m-d');
  $m=$db->queryOne("SELECT SUM(CASE WHEN is_bot=0 AND event_type='page_view' THEN 1 ELSE 0 END) pv,COUNT(DISTINCT CASE WHEN is_bot=0 THEN visitor_hash END) uv,SUM(CASE WHEN is_bot=1 THEN 1 ELSE 0 END) bot_events FROM analytics_events WHERE site_id=:sid AND occurred_at>=:a AND occurred_at<:b",[':sid'=>$site['id'],':a'=>$day,':b'=>$next]);
  $s=$db->queryOne('SELECT COUNT(*) c FROM analytics_sessions WHERE site_id=:sid AND is_bot=0 AND started_at>=:a AND started_at<:b',[':sid'=>$site['id'],':a'=>$day,':b'=>$next]);
  $db->execute('INSERT INTO daily_site_metrics(site_id,metric_date,pv,uv,sessions,bot_events,data_as_of) VALUES(:sid,:d,:pv,:uv,:ss,:bots,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE pv=VALUES(pv),uv=VALUES(uv),sessions=VALUES(sessions),bot_events=VALUES(bot_events),data_as_of=UTC_TIMESTAMP()',[':sid'=>$site['id'],':d'=>$day,':pv'=>(int)($m['pv']??0),':uv'=>(int)($m['uv']??0),':ss'=>(int)($s['c']??0),':bots'=>(int)($m['bot_events']??0)]);
  $db->execute('DELETE FROM daily_site_metric_dimensions WHERE site_id=:sid AND metric_date=:d',[':sid'=>$site['id'],':d'=>$day]);
  $dimensions=[
   'country'=>"COALESCE(NULLIF(s.country_code,''),'unknown')",
   'device'=>"COALESCE(NULLIF(s.device_type,''),'other')",
   'source'=>"COALESCE(NULLIF(s.source,''),'unknown')",
   'landingPage'=>"COALESCE(NULLIF(s.landing_path,''),'/')",
   'locale'=>"COALESCE(NULLIF(s.locale,''),'unknown')",
   'utmSource'=>"COALESCE(NULLIF(s.utm_source,''),'unknown')",
   'utmMedium'=>"COALESCE(NULLIF(s.utm_medium,''),'unknown')",
   'utmCampaign'=>"COALESCE(NULLIF(s.utm_campaign,''),'unknown')",
   'referrerDomain'=>"COALESCE(NULLIF(s.referrer_domain,''),'unknown')"
  ];
  foreach($dimensions as $type=>$expr){
   $sql="INSERT INTO daily_site_metric_dimensions(site_id,metric_date,dimension_type,dimension_value,pv,uv,sessions,bot_events,data_as_of,definition_version)
    SELECT :sid,:d,:dtype,LEFT($expr,255),SUM(CASE WHEN e.is_bot=0 AND e.event_type='page_view' THEN 1 ELSE 0 END),COUNT(DISTINCT CASE WHEN e.is_bot=0 THEN e.visitor_hash END),COUNT(DISTINCT CASE WHEN s.is_bot=0 THEN s.id END),SUM(CASE WHEN e.is_bot=1 THEN 1 ELSE 0 END),UTC_TIMESTAMP(),'1.1'
    FROM analytics_sessions s LEFT JOIN analytics_events e ON e.session_id=s.id AND e.occurred_at>=:a AND e.occurred_at<:b WHERE s.site_id=:sid2 AND s.started_at<:b2 AND s.last_seen_at>=:a2 GROUP BY LEFT($expr,255)";
   $db->execute($sql,[':sid'=>$site['id'],':d'=>$day,':dtype'=>$type,':a'=>$day,':b'=>$next,':sid2'=>$site['id'],':b2'=>$next,':a2'=>$day]);
  }
  $db->execute("INSERT INTO daily_site_metric_dimensions(site_id,metric_date,dimension_type,dimension_value,pv,uv,sessions,bot_events,data_as_of,definition_version)
   SELECT :sid,:d,'product',LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.properties,'$.product_key')),''),'unknown'),255),COUNT(*),COUNT(DISTINCT e.visitor_hash),COUNT(DISTINCT e.session_id),0,UTC_TIMESTAMP(),'1.1'
   FROM analytics_events e WHERE e.site_id=:sid2 AND e.is_bot=0 AND e.event_type='page_view' AND e.occurred_at>=:a AND e.occurred_at<:b GROUP BY LEFT(COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(e.properties,'$.product_key')),''),'unknown'),255)",[':sid'=>$site['id'],':d'=>$day,':sid2'=>$site['id'],':a'=>$day,':b'=>$next]);
 }
}
$db->execute('DELETE FROM analytics_events WHERE occurred_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.RAW_EVENT_RETENTION_DAYS.' DAY)');
$db->execute('DELETE FROM rate_limits WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)');
echo "aggregation completed\n";
