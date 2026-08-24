<?php
require_once __DIR__.'/common.php';
$client=integrationClient();integrationRequireScope($client,'content.read');integrationSiteId($client);
$data=['reason'=>'authoritative_cms_not_connected','items'=>[]];
Response::success(integrationEnvelope($data,'1.1',86400,false));
