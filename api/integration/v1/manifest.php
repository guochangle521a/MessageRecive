<?php
require_once __DIR__.'/common.php';
$c=integrationClient();
$data=[
 'siteId'=>$c['site_id']?intval($c['site_id']):null,'sourceSiteId'=>'sanqi-overseas','timezone'=>'UTC',
 'capabilities'=>[
  'siteMetrics'=>['available'=>true,'scope'=>'metrics.read','dimensions'=>['country','device','source','landingPage','locale','utmSource','utmMedium','utmCampaign','referrerDomain','product']],
  'pageActivity'=>['available'=>true,'scope'=>'content.read'],
  'contentStatus'=>['available'=>false,'scope'=>'content.read','reason'=>'authoritative_cms_not_connected'],
  'inquirySummary'=>['available'=>true,'scope'=>'inquiries.summary','dimensions'=>['country','source','product','status','responseTimeBucket']]
 ],
 'endpoints'=>['siteMetrics'=>'site-metrics.php','pageActivity'=>'page-activity.php','contentStatus'=>'content-status.php','inquirySummary'=>'inquiry-summary.php'],
 'limits'=>['maxLimit'=>INTEGRATION_MAX_LIMIT,'maxDateRangeDays'=>INTEGRATION_MAX_RANGE_DAYS,'rateLimit'=>['requests'=>120,'windowSeconds'=>60,'retryAfterSeconds'=>60]],
 'definitions'=>['pv'=>'non_bot_page_view_events','uv'=>'daily_ip_hmac_hash','sessionTimeoutSeconds'=>180,'rawEventRetentionDays'=>90,'unknownDimensionValue'=>'unknown']
];
Response::success(integrationEnvelope($data,'1.1',3600));
