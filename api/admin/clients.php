<?php
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../src/Database.php';
require_once __DIR__.'/../../src/Response.php';
require_once __DIR__.'/../../src/Auth.php';
Response::handleCors();
$db=Database::getInstance(); $db->initTables(); $user=Auth::requireAdmin(); $method=$_SERVER['REQUEST_METHOD'];
if($method==='GET') Response::success($db->query('SELECT c.id,c.name,c.site_id,s.name site_name,c.audience,c.scopes,c.allowed_ips,c.expires_at,c.is_active,c.created_at FROM api_clients c LEFT JOIN sites s ON s.id=c.site_id ORDER BY c.created_at DESC'));
$in=json_decode(file_get_contents('php://input'),true)?:[];
if($method==='POST') {
    $name=trim((string)($in['name']??'')); $siteId=(int)($in['site_id']??0);
    if($name===''||mb_strlen($name)>160||$siteId<=0) Response::error(400,'客户端名称和授权网站不能为空');
    if(!$db->queryOne('SELECT id FROM sites WHERE id=:id AND is_active=1',[':id'=>$siteId])) Response::error(400,'授权网站不存在或已停用');
    $allowedScopes=['manifest.read','metrics.read','content.read','inquiries.summary'];
    $scopes=array_values(array_unique(array_intersect($allowedScopes,is_array($in['scopes']??null)?$in['scopes']:[])));
    if(!$scopes) Response::error(400,'至少选择一个有效的只读权限');
    $ips=[];
    foreach(is_array($in['allowed_ips']??null)?$in['allowed_ips']:[] as $ip) {
        $ip=trim((string)$ip); if($ip==='') continue;
        if(!filter_var($ip,FILTER_VALIDATE_IP)) Response::error(400,'IP地址格式不正确：'.$ip);
        $ips[]=$ip;
    }
    $ips=array_values(array_unique($ips)); $expires=null;
    if(!empty($in['expires_at'])) {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$in['expires_at'],new DateTimeZone('UTC'));
        if(!$date||$date->format('Y-m-d')!==$in['expires_at']) Response::error(400,'到期日期格式不正确');
        if($date<new DateTimeImmutable('today',new DateTimeZone('UTC'))) Response::error(400,'到期日期不能早于今天');
        $expires=$date->format('Y-m-d').' 23:59:59';
    }
    $plain='sqi_'.bin2hex(random_bytes(24)); $db->beginTransaction();
    try {
        $db->execute('INSERT INTO api_clients(name,token_hash,site_id,audience,scopes,allowed_ips,expires_at) VALUES(:n,:h,:s,:aud,:sc,:ips,:exp)',[':n'=>$name,':h'=>hash('sha256',$plain),':s'=>$siteId,':aud'=>'sanqi-central-readonly',':sc'=>json_encode($scopes),':ips'=>$ips?json_encode($ips):null,':exp'=>$expires]);
        $id=(int)$db->lastInsertId();
        $db->execute('INSERT INTO audit_logs(user_id,action,target_type,target_id,detail,ip_address) VALUES(:u,:a,:t,:id,:d,:ip)',[':u'=>$user['id'],':a'=>'api_client_create',':t'=>'api_client',':id'=>(string)$id,':d'=>json_encode(['name'=>$name,'site_id'=>$siteId,'scopes'=>$scopes,'allowed_ips'=>$ips,'expires_at'=>$expires],JSON_UNESCAPED_UNICODE),':ip'=>$_SERVER['REMOTE_ADDR']??null]);
        $db->commit();
    } catch(Throwable $e) { if($db->getPdo()->inTransaction()) $db->rollBack(); Response::error(5000,'客户端创建失败',500); }
    Response::success(['id'=>$id,'token'=>$plain],'凭据仅显示本次，请立即安全保存');
}
if($method==='PUT') {
    $id=(int)($in['id']??0); if($id<=0) Response::error(400,'缺少ID');
    if(!$db->queryOne('SELECT id FROM api_clients WHERE id=:id',[':id'=>$id])) Response::error(404,'客户端不存在',404);
    $active=!empty($in['is_active'])?1:0; $db->execute('UPDATE api_clients SET is_active=:a WHERE id=:id',[':a'=>$active,':id'=>$id]);
    $db->execute('INSERT INTO audit_logs(user_id,action,target_type,target_id,detail,ip_address) VALUES(:u,:a,:t,:id,:d,:ip)',[':u'=>$user['id'],':a'=>$active?'api_client_enable':'api_client_disable',':t'=>'api_client',':id'=>(string)$id,':d'=>json_encode(['is_active'=>$active]),':ip'=>$_SERVER['REMOTE_ADDR']??null]);
    Response::success(['is_active'=>$active]);
}
Response::error(405,'不支持的方法',405);
