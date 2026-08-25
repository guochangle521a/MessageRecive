<?php
require_once __DIR__.'/../../../config/database.php';
require_once __DIR__.'/../../../src/Database.php';
require_once __DIR__.'/../../../src/Response.php';
require_once __DIR__.'/../../../src/RateLimiter.php';
require_once __DIR__.'/../../../src/Analytics.php';

const INTEGRATION_CONTRACT_VERSION='1.1';
const INTEGRATION_SERVICE_VERSION='1.1.0';
const INTEGRATION_AUDIENCE='sanqi-central-readonly';
const INTEGRATION_MAX_RANGE_DAYS=90;
const INTEGRATION_MAX_LIMIT=1000;

function integrationRequestId(){static $id=null;if($id===null)$id=bin2hex(random_bytes(16));return $id;}
function integrationFail($code,$message,$http=400){$id=integrationRequestId();http_response_code($http);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Request-Id: '.$id);echo json_encode(['code'=>$code,'msg'=>$message,'data'=>['requestId'=>$id,'traceId'=>$id]],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);exit;}
function integrationClient(){
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')integrationFail('METHOD_NOT_ALLOWED','只读接口仅支持GET',405);
 $h=function_exists('getallheaders')?getallheaders():[];$auth=$h['Authorization']??$h['authorization']??'';
 if(!preg_match('/^Bearer\s+(.+)$/i',$auth,$m))integrationFail('AUTH_REQUIRED','缺少访问凭据',401);
 $db=Database::getInstance();$db->initTables();$c=$db->queryOne('SELECT * FROM api_clients WHERE token_hash=:h AND is_active=1 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())',[':h'=>hash('sha256',trim($m[1]))]);
 if(!$c)integrationFail('TOKEN_INVALID','访问凭据无效',401);
 if(($c['audience']??INTEGRATION_AUDIENCE)!==INTEGRATION_AUDIENCE)integrationFail('AUDIENCE_DENIED','Token audience不匹配',403);
 $allowed=json_decode($c['allowed_ips']??'[]',true)?:[];$ip=RateLimiter::getClientIP();
 if($allowed&&!in_array($ip,$allowed,true))integrationFail('SOURCE_IP_DENIED','当前IP未获授权',403);
 if(!RateLimiter::check($ip,120,60,'integration_'.$c['id'])){header('Retry-After: 60');integrationFail('RATE_LIMITED','只读接口调用频率过高',429);}
 $db->execute('INSERT INTO integration_audit_logs(client_id,site_id,request_id,endpoint,method,ip_address) VALUES(:c,:s,:r,:e,:m,:ip)',[':c'=>$c['id'],':s'=>$c['site_id']?:null,':r'=>integrationRequestId(),':e'=>basename($_SERVER['SCRIPT_NAME']??'unknown'),':m'=>'GET',':ip'=>Analytics::maskIp($ip)]);
 return $c;
}
function integrationRequireScope($client,$scope){$scopes=json_decode($client['scopes']??'[]',true)?:[];if(!in_array('*',$scopes,true)&&!in_array($scope,$scopes,true))integrationFail('SCOPE_DENIED','客户端权限不足',403);}
function integrationSite($client){
 static $cache=[];$siteId=(int)($client['site_id']?:($_GET['siteId']??0));if(!$siteId)integrationFail('INVALID_ARGUMENT','siteId不能为空');
 if(!isset($cache[$siteId])){$db=Database::getInstance();$site=$db->queryOne('SELECT id,name,code,domain,category,is_active FROM sites WHERE id=:id',[':id'=>$siteId]);if(!$site||!(int)$site['is_active'])integrationFail('SITE_DENIED','授权网站不存在或已停用',403);$cache[$siteId]=$site;}
 $GLOBALS['integration_site_context']=$cache[$siteId];return$cache[$siteId];
}
function integrationSiteId($client){$site=integrationSite($client);return(int)$site['id'];}
function integrationDateRange(){$end=$_GET['end']??gmdate('Y-m-d');$start=$_GET['start']??gmdate('Y-m-d',time()-29*86400);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||strtotime($end)<strtotime($start))integrationFail('INVALID_ARGUMENT','日期范围无效');if((strtotime($end)-strtotime($start))/86400>INTEGRATION_MAX_RANGE_DAYS)integrationFail('INVALID_ARGUMENT','查询范围不能超过'.INTEGRATION_MAX_RANGE_DAYS.'天');return[$start,$end];}
function integrationCanonical($value){if(!is_array($value))return$value;if($value&&array_keys($value)!==range(0,count($value)-1))ksort($value,SORT_STRING);foreach($value as$k=>$v)$value[$k]=integrationCanonical($v);return$value;}
function integrationEnvelope($data,$definitionVersion='1.1',$freshnessSlaSeconds=86400,$capabilityAvailable=true){$id=integrationRequestId();header('Cache-Control: no-store');header('X-Request-Id: '.$id);$canonical=json_encode(integrationCanonical($data),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$site=$GLOBALS['integration_site_context']??['code'=>'sanqi-overseas','category'=>'overseas'];$sourceSiteId=(string)($site['code']??'sanqi-overseas');$systemId=$sourceSiteId.'-site';return['requestId'=>$id,'traceId'=>$id,'systemId'=>$systemId,'sourceSiteId'=>$sourceSiteId,'contractVersion'=>'v'.INTEGRATION_CONTRACT_VERSION,'serviceVersion'=>INTEGRATION_SERVICE_VERSION,'sourceGeneratedAt'=>gmdate('c'),'dataAsOf'=>gmdate('c'),'definitionVersion'=>$definitionVersion,'freshnessSlaSeconds'=>$freshnessSlaSeconds,'isStale'=>false,'capabilityAvailable'=>$capabilityAvailable,'sourceDigest'=>'sha256:'.hash('sha256',$canonical?:'null'),'data'=>$data];}
