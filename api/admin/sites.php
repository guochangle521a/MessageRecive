<?php
require_once __DIR__.'/../../config/database.php'; require_once __DIR__.'/../../src/Database.php'; require_once __DIR__.'/../../src/Response.php'; require_once __DIR__.'/../../src/Auth.php';
Response::handleCors(); $db=Database::getInstance(); $db->initTables(); $user=Auth::requireLogin();
if($_SERVER['REQUEST_METHOD']==='GET'){
 $ids=Auth::getUserSiteIds($user); $sql='SELECT s.*,o.name organization_name FROM sites s JOIN organizations o ON o.id=s.organization_id'; $params=[];
 if($ids!==null){if(!$ids) Response::success([]); $ph=[];foreach($ids as $i=>$id){$ph[]=':i'.$i;$params[':i'.$i]=$id;}$sql.=' WHERE s.id IN('.implode(',',$ph).')';}
 Response::success($db->query($sql.' ORDER BY s.id',$params));
}
$user=Auth::requireAdmin(); $in=json_decode(file_get_contents('php://input'),true)?:[];
if($_SERVER['REQUEST_METHOD']==='POST'){
 foreach(['name','code','domain'] as $f) if(trim((string)($in[$f]??''))==='') Response::error(400,$f.'不能为空');
 $db->execute('INSERT INTO sites(organization_id,name,code,domain,category,public_site_key) VALUES(1,:n,:c,:d,:cat,:k)',[':n'=>trim($in['name']),':c'=>trim($in['code']),':d'=>strtolower(trim($in['domain'])),':cat'=>$in['category']??'other',':k'=>bin2hex(random_bytes(20))]);
 $id=$db->lastInsertId(); $db->execute('INSERT INTO site_domains(site_id,domain,is_primary) VALUES(:id,:d,1)',[':id'=>$id,':d'=>strtolower(trim($in['domain']))]); Response::success(['id'=>(int)$id]);
}
Response::error(405,'不支持的方法',405);
