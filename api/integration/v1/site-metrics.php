<?php
require_once __DIR__.'/common.php';
$client=integrationClient();integrationRequireScope($client,'metrics.read');$db=Database::getInstance();$siteId=integrationSiteId($client);[$start,$end]=integrationDateRange();
$granularity=$_GET['granularity']??'day';if($granularity!=='day')integrationFail('INVALID_ARGUMENT','当前生产版本仅支持day粒度');
$daily=$db->query('SELECT metric_date date,pv,uv,sessions,bot_events botVisits,data_as_of dataAsOf FROM daily_site_metrics WHERE site_id=:s AND metric_date BETWEEN :a AND :b ORDER BY metric_date',[':s'=>$siteId,':a'=>$start,':b'=>$end]);
$types=['country','device','source','landingPage','locale','utmSource','utmMedium','utmCampaign','referrerDomain','product'];
$requested=array_values(array_intersect($types,array_filter(array_map('trim',explode(',',$_GET['dimensions']??implode(',',$types))))));$breakdowns=[];
foreach($requested as$type)$breakdowns[$type]=$db->query('SELECT dimension_value value,SUM(pv) pv,SUM(uv) uv,SUM(sessions) sessions,SUM(bot_events) botVisits,MAX(data_as_of) dataAsOf FROM daily_site_metric_dimensions WHERE site_id=:s AND dimension_type=:t AND metric_date BETWEEN :a AND :b GROUP BY dimension_value ORDER BY pv DESC LIMIT 1000',[':s'=>$siteId,':t'=>$type,':a'=>$start,':b'=>$end]);
$totals=['pv'=>0,'uv'=>0,'sessions'=>0,'botVisits'=>0];foreach($daily as$row)foreach($totals as$k=>$v)$totals[$k]+=(int)$row[$k];
$data=['periodStart'=>$start.'T00:00:00Z','periodEnd'=>$end.'T23:59:59Z','granularity'=>'day','totals'=>$totals,'daily'=>$daily,'breakdowns'=>$breakdowns];
Response::success(integrationEnvelope($data,'1.1',3600));
