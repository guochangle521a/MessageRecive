<?php
require_once __DIR__.'/common.php';
$client=integrationClient();integrationRequireScope($client,'content.read');$db=Database::getInstance();$siteId=integrationSiteId($client);
[$start,$end]=integrationDateRange();$limit=max(1,min(INTEGRATION_MAX_LIMIT,(int)($_GET['limit']??500)));$fetch=$limit+1;
$params=[':s'=>$siteId,':a'=>$start,':b'=>$end];$having='';
if(!empty($_GET['cursor'])){$decoded=json_decode(base64_decode(strtr((string)$_GET['cursor'],'-_','+/'),true)?:'',true);if(!is_array($decoded)||empty($decoded['time'])||!isset($decoded['path']))integrationFail('INVALID_ARGUMENT','cursor无效');$having=' HAVING (MAX(occurred_at)<:ct OR (MAX(occurred_at)=:ct2 AND page_path>:cp))';$params[':ct']=$decoded['time'];$params[':ct2']=$decoded['time'];$params[':cp']=$decoded['path'];}
$rows=$db->query("SELECT page_path pagePath,MAX(page_title) title,MIN(occurred_at) firstSeenAt,MAX(occurred_at) lastSeenAt,COUNT(*) pv,COUNT(DISTINCT visitor_hash) uv,COUNT(DISTINCT session_id) sessions FROM analytics_events WHERE site_id=:s AND is_bot=0 AND event_type='page_view' AND occurred_at>=:a AND occurred_at<DATE_ADD(:b,INTERVAL 1 DAY) GROUP BY page_path$having ORDER BY lastSeenAt DESC,pagePath ASC LIMIT $fetch",$params);
$nextCursor=null;if(count($rows)>$limit){array_pop($rows);$last=end($rows);$nextCursor=rtrim(strtr(base64_encode(json_encode(['time'=>$last['lastSeenAt'],'path'=>$last['pagePath']])), '+/', '-_'),'=');}
Response::success(integrationEnvelope(['periodStart'=>$start.'T00:00:00Z','periodEnd'=>$end.'T23:59:59Z','items'=>$rows,'limit'=>$limit,'nextCursor'=>$nextCursor],'1.1',3600));
