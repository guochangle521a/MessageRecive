<?php
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../src/Database.php';
require_once __DIR__ . '/../../../src/Response.php';
require_once __DIR__ . '/../../../src/RateLimiter.php';
require_once __DIR__ . '/../../../src/Analytics.php';
require_once __DIR__ . '/../../../src/GeoIp.php';
Response::handleCors();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') Response::error(405,'仅支持POST请求',405);
$data=json_decode(file_get_contents('php://input'),true);
if (!is_array($data)) Response::error(1000,'请求体必须为JSON');
$db=Database::getInstance(); $db->initTables();
$key=trim((string)($data['site_key']??''));
$site=$db->queryOne('SELECT * FROM sites WHERE public_site_key=:k AND is_active=1',[':k'=>$key]);
if (!$site) Response::error(2001,'site_key无效',403);
$origin=$_SERVER['HTTP_ORIGIN']??'';
if ($origin) {
    $originHost=strtolower(parse_url($origin,PHP_URL_HOST)?:'');
    $allowed=$db->queryOne('SELECT 1 FROM site_domains WHERE site_id=:sid AND domain=:domain',[':sid'=>$site['id'],':domain'=>$originHost]);
    if (!$allowed && $originHost !== '127.0.0.1' && $originHost !== 'localhost') Response::error(2004,'来源域名未授权',403);
}
$ip=RateLimiter::getClientIP();
if (!RateLimiter::check($ip,TRACK_RATE_LIMIT_MAX,60,'track')) Response::error(2002,'采集频率过高',429);
$ua=(string)($_SERVER['HTTP_USER_AGENT']??''); $now=gmdate('Y-m-d H:i:s'); $day=gmdate('Y-m-d');
$visitor=Analytics::visitorHash($site['id'],$ip,$day); $bot=Analytics::isBot($ua)?1:0;
$short=function($value,$max){return mb_substr(trim((string)$value),0,$max);};
$pagePath=$short($data['page_path']??'',1024);
$referrerDomain=strtolower($short($data['referrer_domain']??'',255));
if($referrerDomain!==''&&!preg_match('/^[a-z0-9.-]+$/',$referrerDomain))$referrerDomain='';
$countryCode=strtoupper($short($_SERVER['HTTP_CF_IPCOUNTRY']??($_SERVER['HTTP_X_COUNTRY_CODE']??''),2));
if(!preg_match('/^[A-Z]{2}$/',$countryCode)||$countryCode==='XX')$countryCode='';
if($countryCode==='')$countryCode=GeoIp::countryCode($ip)?:'';
$locale=$short($data['locale']??($data['language']??''),32);
$utmSource=$short($data['utm_source']??'',255);$utmMedium=$short($data['utm_medium']??'',255);$utmCampaign=$short($data['utm_campaign']??'',255);
$recent=$db->queryOne('SELECT * FROM analytics_sessions WHERE site_id=:sid AND visitor_hash=:vh AND last_seen_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 180 SECOND) ORDER BY last_seen_at DESC LIMIT 1',[':sid'=>$site['id'],':vh'=>$visitor]);
if ($recent) { $sessionId=$recent['id']; $db->execute('UPDATE analytics_sessions SET last_seen_at=UTC_TIMESTAMP(),is_bot=GREATEST(is_bot,:bot) WHERE id=:id',[':bot'=>$bot,':id'=>$sessionId]); }
else {
    $source=$utmSource!==''?Analytics::sourceFromUtm($utmSource,$utmMedium):Analytics::source($data['referrer']??'');
    $db->execute('INSERT INTO analytics_sessions(site_id,session_key,visitor_hash,ip_address,masked_ip,started_at,last_seen_at,landing_url,landing_path,referrer,referrer_domain,source,country,country_code,device_type,locale,utm_source,utm_medium,utm_campaign,is_bot) VALUES(:sid,:sk,:vh,:ip,:mip,UTC_TIMESTAMP(),UTC_TIMESTAMP(),:url,:path,:ref,:refdom,:source,:country_name,:country,:device,:locale,:us,:um,:uc,:bot)',[
        ':sid'=>$site['id'],':sk'=>bin2hex(random_bytes(32)),':vh'=>$visitor,':ip'=>$ip,':mip'=>Analytics::maskIp($ip),':url'=>substr((string)($data['page_url']??''),0,4000),':path'=>$pagePath,':ref'=>substr((string)($data['referrer']??''),0,4000),':refdom'=>$referrerDomain?:null,':source'=>$source,':country_name'=>$countryCode?:null,':country'=>$countryCode?:null,':device'=>Analytics::device($ua),':locale'=>$locale?:null,':us'=>$utmSource?:null,':um'=>$utmMedium?:null,':uc'=>$utmCampaign?:null,':bot'=>$bot]);
    $sessionId=$db->lastInsertId();
}
$eventType=preg_match('/^[a-z0-9_-]{1,64}$/',(string)($data['event_type']??''))?(string)$data['event_type']:'page_view';
$occurred=$now; if (!empty($data['occurred_at'])) { $ts=strtotime((string)$data['occurred_at']); if ($ts && abs(time()-$ts)<86400) $occurred=gmdate('Y-m-d H:i:s',$ts); }
$props=[]; foreach(['screen','language','locale','referrer_domain','utm_source','utm_medium','utm_campaign','product_key'] as $f) if(isset($data[$f])&&$data[$f]!=='') $props[$f]=$short($data[$f],255);
$db->execute('INSERT INTO analytics_events(site_id,session_id,visitor_hash,event_type,page_url,page_path,page_title,referrer,properties,is_bot,occurred_at) VALUES(:sid,:sess,:vh,:type,:url,:path,:title,:ref,:props,:bot,:at)',[
 ':sid'=>$site['id'],':sess'=>$sessionId,':vh'=>$visitor,':type'=>$eventType,':url'=>substr((string)($data['page_url']??''),0,4000),':path'=>substr((string)($data['page_path']??''),0,1024),':title'=>substr((string)($data['page_title']??''),0,500),':ref'=>substr((string)($data['referrer']??''),0,4000),':props'=>json_encode($props,JSON_UNESCAPED_UNICODE),':bot'=>$bot,':at'=>$occurred]);
Response::success(['accepted'=>true]);
