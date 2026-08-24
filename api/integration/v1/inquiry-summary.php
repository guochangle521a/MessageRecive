<?php
require_once __DIR__.'/common.php';
$client=integrationClient();integrationRequireScope($client,'inquiries.summary');$db=Database::getInstance();$siteId=integrationSiteId($client);[$start,$end]=integrationDateRange();
$params=[':s'=>$siteId,':a'=>$start,':b'=>$end];$where='site_id=:s AND created_at>=:a AND created_at<DATE_ADD(:b,INTERVAL 1 DAY)';
$raw=$db->queryOne("SELECT COUNT(*) total,SUM(validity_status='valid') valid_count,SUM(validity_status='spam') spam_count,SUM(validity_status='pending') pending_count FROM messages WHERE $where",$params);
$totals=['total'=>(int)($raw['total']??0),'valid'=>(int)($raw['valid_count']??0),'spam'=>(int)($raw['spam_count']??0),'pending'=>(int)($raw['pending_count']??0)];
$expressions=[
 'country'=>"CASE WHEN country REGEXP '^[A-Za-z]{2}$' THEN UPPER(country) ELSE 'unknown' END",
 'source'=>"COALESCE(NULLIF(conversion_source,''),NULLIF(inquiry_source,''),'unknown')",
 'product'=>"COALESCE(NULLIF(product_key,''),NULLIF(type,''),'unknown')",
 'status'=>"COALESCE(NULLIF(validity_status,''),'pending')",
 'responseTimeBucket'=>"CASE WHEN handled_at IS NULL THEN 'unknown' WHEN TIMESTAMPDIFF(MINUTE,created_at,handled_at)<60 THEN 'under1h' WHEN TIMESTAMPDIFF(MINUTE,created_at,handled_at)<240 THEN '1to4h' WHEN TIMESTAMPDIFF(MINUTE,created_at,handled_at)<1440 THEN '4to24h' ELSE 'over24h' END",
 'firstTouchSource'=>"COALESCE(NULLIF(first_touch_source,''),'unknown')",
 'conversionSource'=>"COALESCE(NULLIF(conversion_source,''),'unknown')"
];
$breakdowns=[];foreach($expressions as$name=>$expr)$breakdowns[$name]=$db->query("SELECT $expr value,COUNT(*) count FROM messages WHERE $where GROUP BY $expr ORDER BY count DESC",$params);
$data=['periodStart'=>$start.'T00:00:00Z','periodEnd'=>$end.'T23:59:59Z','totals'=>$totals,'breakdowns'=>$breakdowns,'definitions'=>['country'=>'ISO alpha-2 when supplied; otherwise unknown','responseTime'=>'created_at to first handled_at','unknown'=>'source field not historically available']];
Response::success(integrationEnvelope($data,'1.1',3600));
