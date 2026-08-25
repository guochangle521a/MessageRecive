// ==========================================
// 留言管理系统 - 前端引擎
// ==========================================

var API_BASE = '../api/admin/';
var PUBLIC_SERVICE_ORIGIN = 'https://message.sanqifz.com:38038';
var userInfo = null;
var currentPage = 1, pageSize = 20;
var selectedIds = [];
var editingUserId = 0;
var allSources = [];
var formTypes = {
    'business-inquiry': '联系我们', 'sample-request': '样品申请',
    'strategic-partnership': '招商合作', 'career-introduction': '人才申请',
    'factory-visit': '工厂参观', 'china-website-inquiry': '国内官网留言'
};

// ========== Token 管理 ==========
function getToken() {
    return localStorage.getItem('api_token') || '';
}

async function apiFetch(url, options) {
    options = options || {};
    options.headers = options.headers || {};
    var token = getToken();
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }
    if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    } else if (!options.method || options.method.toUpperCase() === 'POST' || options.method.toUpperCase() === 'PUT') {
        if (!options.headers['Content-Type']) {
            options.headers['Content-Type'] = 'application/json';
        }
    }
    return fetch(url, options);
}

// ========== Init ==========
(function init() {
    // 尝试获取用户信息
    userInfo = getUserInfoFromStorage();
    if (isLoginPage()) return;
    checkLoginStatus();
})();

function isLoginPage() {
    return window.location.pathname.indexOf('login.html') > -1;
}

function getUserInfoFromStorage() {
    try {
        var info = localStorage.getItem('user_info');
        return info ? JSON.parse(info) : null;
    } catch(e) { return null; }
}

async function checkLoginStatus() {
    try {
        var res = await apiFetch(API_BASE + 'me.php');
        var data = await res.json();
        if (data.code === 0) {
            userInfo = data.data;
            localStorage.setItem('user_info', JSON.stringify(data.data));
            renderLayout();
        } else {
            window.location.href = 'login.html';
        }
    } catch(e) {
        window.location.href = 'login.html';
    }
}

// ========== Layout ==========
function renderLayout() {
    if (!userInfo) return;
    // Top bar
    var userEl = document.getElementById('topUsername');
    var avatarEl = document.getElementById('userAvatar');
    var roleEl = document.getElementById('sidebarUserRole');
    if (userEl) userEl.textContent = userInfo.real_name || userInfo.username;
    if (avatarEl) avatarEl.textContent = (userInfo.real_name || userInfo.username).charAt(0).toUpperCase();
    if (roleEl) roleEl.textContent = userInfo.role === 'admin' ? '管理员' : '普通用户';

    // Sidebar nav
    var navEl = document.getElementById('sidebarNav');
    if (!navEl) return;
    var html = '<div class="nav-section">工作台</div>';
    html += '<a href="analytics.html" class="' + (isPage('analytics') ? 'active' : '') + '"><span class="nav-icon">&#x1F4CA;</span> 集团与网站概览</a>';
    html += '<div class="nav-section">网站数据中心</div>';
    html += '<a href="analytics-detail.html" class="' + (isPage('analytics-detail') ? 'active' : '') + '"><span class="nav-icon">&#x1F50E;</span> 访问与会话明细</a>';
    html += '<div class="nav-section">询盘中心</div>';
    html += '<a href="index.html" class="' + (isPage('index') ? 'active' : '') + '"><span class="nav-icon">&#x1F30D;</span> 海外独立站留言</a>';
    if (userInfo.role === 'admin' || Number(userInfo.can_view_china) === 1) {
        html += '<a href="china.html" class="' + (isPage('china') ? 'active' : '') + '"><span class="nav-icon">&#x1F1E8;&#x1F1F3;</span> 国内官网留言</a>';
    }
    html += '<a href="stats.html" class="' + (isPage('stats') ? 'active' : '') + '"><span class="nav-icon">&#x1F4C8;</span> 询盘统计</a>';
    if (userInfo.role === 'admin') {
        html += '<div class="nav-section">组织与站点</div>';
        html += '<a href="sites.html" class="' + (isPage('sites') ? 'active' : '') + '"><span class="nav-icon">&#x1F310;</span> 网站管理</a>';
        html += '<div class="nav-section">接口与集成</div>';
        html += '<a href="keys.html" class="' + (isPage('keys') ? 'active' : '') + '"><span class="nav-icon">&#x1F511;</span> 表单 API Key</a>';
        html += '<a href="clients.html" class="' + (isPage('clients') ? 'active' : '') + '"><span class="nav-icon">&#x1F6E1;</span> 第三方 API 客户端</a>';
        html += '<div class="nav-section">数据运维</div>';
        html += '<a href="operations.html" class="' + (isPage('operations') ? 'active' : '') + '"><span class="nav-icon">&#x1F6E0;</span> 数据质量与任务</a>';
        html += '<div class="nav-section">系统管理</div>';
        html += '<a href="users.html" class="' + (isPage('users') ? 'active' : '') + '"><span class="nav-icon">&#x1F465;</span> 用户与网站权限</a>';
        html += '<a href="#" onclick="event.preventDefault();openStatusDict();" class="' + (isPage('index') ? '' : '') + '"><span class="nav-icon">&#x2699;</span> 状态字典维护</a>';
    }
    navEl.innerHTML = html;
}

function isPage(name) {
    return window.location.pathname.indexOf(name + '.html') > -1;
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
}

async function doLogout() {
    try {
        await apiFetch(API_BASE + 'me.php?action=logout');
    } catch(e) {}
    localStorage.removeItem('user_info');
    window.location.href = 'login.html';
}

// ========== Toast ==========
function showToast(msg, type) {
    type = type || 'info';
    var container = document.getElementById('toastContainer');
    if (!container) { container = document.createElement('div'); container.className = 'toast-container'; container.id = 'toastContainer'; document.body.appendChild(container); }
    var t = document.createElement('div');
    t.className = 'toast ' + type;
    t.textContent = msg;
    container.appendChild(t);
    setTimeout(function() { t.style.opacity = '0'; setTimeout(function() { t.remove(); }, 300); }, 2500);
}

// ===================================
//  留言列表 (index.html)
// ===================================

if (isPage('index')) {
    document.addEventListener('DOMContentLoaded', async function() {
        await loadStatusDict();
        await loadSources();
        await loadMessages(1);
    });
}
if (isPage('china')) {
    document.addEventListener('DOMContentLoaded', async function() {
        await loadStatusDict();
        await loadMessages(1);
    });
}

if (isPage('stats')) {
    document.addEventListener('DOMContentLoaded', async function() {
        await loadStatusDict();
        await loadSources();
        await loadStats();
    });
}
if (isPage('analytics')) document.addEventListener('DOMContentLoaded', loadAnalyticsDashboard);
if (isPage('sites')) document.addEventListener('DOMContentLoaded', loadSiteManagement);
if (isPage('clients')) document.addEventListener('DOMContentLoaded', function(){ loadApiClients(); initIntegrationDebug(); });
if (isPage('operations')) document.addEventListener('DOMContentLoaded', loadOperations);
if (isPage('analytics-detail')) document.addEventListener('DOMContentLoaded', initAnalyticsDetail);

async function loadAnalyticsDashboard() {
    var sitesRes=await apiFetch(API_BASE+'sites.php'), sitesData=await sitesRes.json();
    if(sitesData.code!==0) return showToast(sitesData.msg||'网站读取失败','error');
    var select=document.getElementById('analyticsSite'); select.innerHTML='';
    sitesData.data.forEach(function(s){select.innerHTML+='<option value="'+s.id+'">'+esc(s.name)+' — '+esc(s.domain)+'</option>';});
    select.onchange=loadAnalyticsData; await loadAnalyticsData();
}
async function loadAnalyticsData(){
    var select=document.getElementById('analyticsSite'); if(!select||!select.value)return;
    var end=new Date().toISOString().slice(0,10), start=new Date(Date.now()-29*86400000).toISOString().slice(0,10);
    var res=await apiFetch(API_BASE+'analytics.php?site_id='+encodeURIComponent(select.value)+'&start='+start+'&end='+end), data=await res.json();
    if(data.code!==0)return showToast(data.msg||'统计读取失败','error'); var d=data.data;
    document.getElementById('pvTotal').textContent=d.totals.pv; document.getElementById('uvTotal').textContent=d.totals.uv; document.getElementById('sessionTotal').textContent=d.totals.sessions;
    document.getElementById('pageRows').innerHTML=d.pages.map(function(p){return '<tr><td>'+esc(p.page_title||p.page_path||'-')+'</td><td>'+esc(p.page_path||'-')+'</td><td>'+p.pv+'</td><td>'+p.uv+'</td></tr>';}).join('')||'<tr><td colspan="4">暂无数据</td></tr>';
    document.getElementById('sourceRows').innerHTML=d.sources.map(function(s){return '<tr><td>'+esc(s.source||'direct')+'</td><td>'+s.sessions+'</td></tr>';}).join('')||'<tr><td colspan="2">暂无数据</td></tr>';
}
async function loadSiteManagement(){
 var res=await apiFetch(API_BASE+'sites.php'),data=await res.json();if(data.code!==0)return showToast(data.msg||'读取失败','error');
 window.siteManagementData=data.data||[];
 document.getElementById('siteRows').innerHTML=window.siteManagementData.map(function(s){return '<tr><td>'+s.id+'</td><td>'+esc(s.name)+'</td><td>'+esc(s.domain)+'</td><td>'+esc(s.category)+'</td><td><code>'+esc(s.public_site_key)+'</code></td><td>'+(Number(s.is_active)?'启用':'停用')+'</td><td><button class="btn btn-primary btn-sm" onclick="showTrackerCode('+Number(s.id)+')">接入代码</button></td></tr>';}).join('')||'<tr><td colspan="7">暂无网站</td></tr>';
}
function trackerSnippet(site){return '<script\n  src="'+PUBLIC_SERVICE_ORIGIN+'/public/tracker.js"\n  data-site-key="'+site.public_site_key+'">\n<\/script>';}
function showTrackerCode(id){var site=(window.siteManagementData||[]).find(function(x){return Number(x.id)===Number(id);});if(!site)return showToast('网站不存在','error');document.getElementById('trackerCodeTitle').textContent=site.name+' — 统计接入代码';document.getElementById('trackerCodeText').value=trackerSnippet(site);document.getElementById('trackerCopyStatus').textContent='';document.getElementById('trackerCodeModal').style.display='flex';}
function closeTrackerCode(){document.getElementById('trackerCodeModal').style.display='none';}
async function copyTrackerCode(){var el=document.getElementById('trackerCodeText');try{await navigator.clipboard.writeText(el.value);}catch(e){el.select();document.execCommand('copy');}document.getElementById('trackerCopyStatus').textContent='已复制，可直接粘贴到网站公共页脚。';}
function openSiteCreate(){['newSiteName','newSiteCode','newSiteDomain'].forEach(function(id){document.getElementById(id).value='';});document.getElementById('newSiteCategory').value='overseas';document.getElementById('siteCreateModal').style.display='flex';}
function closeSiteCreate(){document.getElementById('siteCreateModal').style.display='none';}
async function createSite(){var name=document.getElementById('newSiteName').value.trim(),code=document.getElementById('newSiteCode').value.trim(),domain=document.getElementById('newSiteDomain').value.trim().toLowerCase(),category=document.getElementById('newSiteCategory').value;if(!name||!code||!domain)return showToast('请完整填写网站名称、代码和主域名','error');if(!/^[a-z0-9][a-z0-9-]{1,62}$/.test(code))return showToast('网站代码只能使用小写字母、数字和短横线','error');if(domain.indexOf('://')>=0||domain.indexOf('/')>=0)return showToast('主域名不要包含协议或路径','error');var btn=document.getElementById('createSiteButton');btn.disabled=true;btn.textContent='正在创建...';try{var r=await apiFetch(API_BASE+'sites.php',{method:'POST',body:{name:name,code:code,domain:domain,category:category}}),d=await r.json();if(d.code!==0)return showToast(d.msg||'创建失败','error');closeSiteCreate();await loadSiteManagement();showTrackerCode(d.data.id);showToast('网站已创建并生成统计接入代码','success');}catch(e){showToast('创建失败','error');}finally{btn.disabled=false;btn.textContent='创建并生成接入代码';}}
var apiClientSites=[];
async function loadApiClients(){var results=await Promise.all([apiFetch(API_BASE+'clients.php'),apiFetch(API_BASE+'sites.php')]),d=await results[0].json(),sd=await results[1].json();if(d.code!==0)return showToast(d.msg||'读取失败','error');if(sd.code===0)apiClientSites=sd.data||[];document.getElementById('clientRows').innerHTML=d.data.map(function(c){var scopes=c.scopes,ips=c.allowed_ips;try{scopes=JSON.parse(scopes).join(', ')}catch(e){}try{var parsed=JSON.parse(ips);ips=parsed&&parsed.length?parsed.join(', '):'未限制';}catch(e){ips=ips||'未限制';}var active=Number(c.is_active)===1;return '<tr><td>'+esc(c.name)+'</td><td>'+esc(c.site_name||'-')+'</td><td>'+esc(scopes||'-')+'</td><td>'+esc(ips)+'</td><td>'+esc(c.expires_at||'长期')+'</td><td>'+(active?'启用':'停用')+'</td><td><button class="btn btn-sm '+(active?'btn-outline':'btn-success')+'" onclick="toggleApiClient('+Number(c.id)+','+(active?'0':'1')+')">'+(active?'停用':'启用')+'</button></td></tr>';}).join('')||'<tr><td colspan="7">暂无客户端，请点击“新建客户端”。</td></tr>';}
function openClientCreate(){var site=document.getElementById('clientSite');site.innerHTML='<option value="">请选择网站</option>'+apiClientSites.map(function(s){return '<option value="'+Number(s.id)+'">'+esc(s.name)+' — '+esc(s.domain)+'</option>';}).join('');document.getElementById('clientName').value='';document.getElementById('clientAllowedIps').value='';document.getElementById('clientExpires').value='';document.querySelectorAll('input[name="clientScope"]').forEach(function(x){x.checked=true;});document.getElementById('clientCreateModal').style.display='flex';}
function closeClientCreate(){document.getElementById('clientCreateModal').style.display='none';}
function validIp(ip){if(/^((25[0-5]|2[0-4]\d|1?\d?\d)(\.|$)){4}$/.test(ip))return true;return /^[0-9a-f:]+$/i.test(ip)&&ip.indexOf(':')>=0;}
function integrationUsage(token,siteId,scopes){var base=PUBLIC_SERVICE_ORIGIN+'/api/integration/v1/',end=new Date(),start=new Date(end.getTime()-29*86400000),to=end.toISOString().slice(0,10),from=start.toISOString().slice(0,10);var lines=['# Bearer Token 只显示本次，请先安全保存','TOKEN="'+token+'"',''];lines.push('# V1.1 能力清单','curl -H "Authorization: Bearer $TOKEN" "'+base+'manifest.php?siteId='+siteId+'"','');if(scopes.indexOf('metrics.read')>=0)lines.push('# 网站统计及维度聚合（最近30天）','curl -H "Authorization: Bearer $TOKEN" "'+base+'site-metrics.php?siteId='+siteId+'&start='+from+'&end='+to+'&granularity=day"','');if(scopes.indexOf('content.read')>=0)lines.push('# 页面访问活动','curl -H "Authorization: Bearer $TOKEN" "'+base+'page-activity.php?siteId='+siteId+'&start='+from+'&end='+to+'"','','# CMS内容状态（未接入CMS时 capabilityAvailable=false）','curl -H "Authorization: Bearer $TOKEN" "'+base+'content-status.php?siteId='+siteId+'"','');if(scopes.indexOf('inquiries.summary')>=0)lines.push('# 询盘维度汇总','curl -H "Authorization: Bearer $TOKEN" "'+base+'inquiry-summary.php?siteId='+siteId+'&start='+from+'&end='+to+'"','');lines.push('# 服务状态（无需 Token）','curl "'+base+'health-live.php"','curl "'+base+'health-ready.php"');return lines.join('\n');}
async function createApiClient(){var name=document.getElementById('clientName').value.trim(),siteId=Number(document.getElementById('clientSite').value),scopes=Array.from(document.querySelectorAll('input[name="clientScope"]:checked')).map(function(x){return x.value;}),ips=document.getElementById('clientAllowedIps').value.split(/[\n,]+/).map(function(x){return x.trim();}).filter(Boolean),expires=document.getElementById('clientExpires').value;if(!name)return showToast('请输入客户端名称','error');if(!siteId)return showToast('请选择授权网站','error');if(!scopes.length)return showToast('至少选择一个只读权限','error');var invalid=ips.filter(function(ip){return !validIp(ip);});if(invalid.length)return showToast('IP 地址格式不正确：'+invalid[0],'error');var btn=document.getElementById('createClientButton');btn.disabled=true;btn.textContent='正在生成...';try{var r=await apiFetch(API_BASE+'clients.php',{method:'POST',body:{name:name,site_id:siteId,scopes:scopes,allowed_ips:ips,expires_at:expires||null}}),d=await r.json();if(d.code!==0)return showToast(d.msg||'生成失败','error');closeClientCreate();document.getElementById('createdClientToken').value=d.data.token;document.getElementById('createdClientUsage').value=integrationUsage(d.data.token,siteId,scopes);document.getElementById('tokenCopyStatus').textContent='';document.getElementById('usageCopyStatus').textContent='';document.getElementById('clientTokenModal').style.display='flex';await loadApiClients();}catch(e){showToast('生成失败','error');}finally{btn.disabled=false;btn.textContent='生成 Bearer Token';}}
async function copyCreatedClientToken(){var el=document.getElementById('createdClientToken');try{await navigator.clipboard.writeText(el.value);document.getElementById('tokenCopyStatus').textContent='已复制到剪贴板。';}catch(e){el.select();document.execCommand('copy');document.getElementById('tokenCopyStatus').textContent='已复制到剪贴板。';}}
function closeClientToken(){document.getElementById('createdClientToken').value='';document.getElementById('clientTokenModal').style.display='none';}
async function copyCreatedClientUsage(){var el=document.getElementById('createdClientUsage');try{await navigator.clipboard.writeText(el.value);}catch(e){el.select();document.execCommand('copy');}document.getElementById('usageCopyStatus').textContent='调用示例已复制。';}
async function toggleApiClient(id,active){var r=await apiFetch(API_BASE+'clients.php',{method:'PUT',body:{id:id,is_active:active}}),d=await r.json();if(d.code!==0)return showToast(d.msg||'操作失败','error');showToast(active?'客户端已启用':'客户端已停用','success');loadApiClients();}
function initIntegrationDebug(){var end=new Date(),start=new Date(end.getTime()-29*86400000);document.getElementById('debugEnd').value=end.toISOString().slice(0,10);document.getElementById('debugStart').value=start.toISOString().slice(0,10);updateIntegrationDebugFields();}
function updateIntegrationDebugFields(){var endpoint=document.getElementById('debugEndpoint').value;var auth=endpoint.indexOf('health-')!==0;var dated=endpoint==='site-metrics.php'||endpoint==='page-activity.php'||endpoint==='inquiry-summary.php';document.getElementById('debugTokenWrap').style.display=auth?'flex':'none';document.getElementById('debugSiteWrap').style.display=auth?'flex':'none';document.getElementById('debugStartWrap').style.display=dated?'flex':'none';document.getElementById('debugEndWrap').style.display=dated?'flex':'none';document.getElementById('debugRequestUrl').textContent='../api/integration/v1/'+endpoint;}
async function runIntegrationDebug(){var endpoint=document.getElementById('debugEndpoint').value;var auth=endpoint.indexOf('health-')!==0;var token=document.getElementById('debugToken').value.trim();if(auth&&!token)return showToast('请输入第三方客户端 Bearer Token','error');var params=new URLSearchParams();var siteId=document.getElementById('debugSiteId').value;if(auth&&siteId)params.set('siteId',siteId);if(endpoint==='site-metrics.php'||endpoint==='page-activity.php'||endpoint==='inquiry-summary.php'){var start=document.getElementById('debugStart').value,end=document.getElementById('debugEnd').value;if(start)params.set('start',start);if(end)params.set('end',end);}if(endpoint==='site-metrics.php')params.set('granularity','day');var url='../api/integration/v1/'+endpoint+(params.toString()?'?'+params.toString():'');var headers={Accept:'application/json'};if(auth)headers.Authorization='Bearer '+token;var button=document.getElementById('debugRunButton'),output=document.getElementById('debugResponse');button.disabled=true;button.textContent='请求中...';output.textContent='正在调用接口...';document.getElementById('debugRequestUrl').textContent=url;var started=performance.now();try{var response=await fetch(url,{method:'GET',headers:headers,cache:'no-store'});var text=await response.text(),pretty=text;try{pretty=JSON.stringify(JSON.parse(text),null,2);}catch(e){}document.getElementById('debugHttpStatus').textContent=response.status+' '+response.statusText;document.getElementById('debugHttpStatus').style.color=response.ok?'#1e8e3e':'#d93025';document.getElementById('debugElapsed').textContent=Math.round(performance.now()-started)+' ms';output.textContent=pretty||'(空响应)';}catch(e){document.getElementById('debugHttpStatus').textContent='请求失败';document.getElementById('debugElapsed').textContent=Math.round(performance.now()-started)+' ms';output.textContent=String(e&&e.message?e.message:e);}finally{button.disabled=false;button.textContent='发送测试请求';}}
function clearIntegrationDebug(){document.getElementById('debugToken').value='';document.getElementById('debugSiteId').value='';document.getElementById('debugHttpStatus').textContent='-';document.getElementById('debugElapsed').textContent='-';document.getElementById('debugResponse').textContent='请选择接口并发送测试请求。';updateIntegrationDebugFields();}
async function loadOperations(){var r=await apiFetch(API_BASE+'operations.php'),d=await r.json();if(d.code!==0)return showToast(d.msg||'读取失败','error');var x=d.data;document.getElementById('storageSummary').textContent='原始事件 '+(x.storage.raw_events||0)+' 条；保留 '+x.retention_days+' 天；会话超时 '+x.session_timeout_seconds+' 秒；服务器时间 '+x.server_time_utc;document.getElementById('operationRows').innerHTML=x.sites.map(function(s){return '<tr><td>'+esc(s.name)+'</td><td>'+esc(s.domain)+'</td><td>'+s.events_24h+'</td><td>'+s.bots_24h+'</td><td>'+esc(s.last_event_at||'尚未采集')+'</td><td>'+esc(s.last_aggregate_at||'尚未聚合')+'</td></tr>';}).join('');}

var analyticsDetailMode='events',analyticsDetailPage=1,analyticsDetailPages=1,analyticsDetailRequestId=0;
async function initAnalyticsDetail(){var r=await apiFetch(API_BASE+'sites.php'),d=await r.json();if(d.code!==0)return showToast(d.msg||'网站读取失败','error');var site=document.getElementById('adSite');site.innerHTML=(d.data||[]).map(function(s){return '<option value="'+Number(s.id)+'">'+esc(s.name)+' — '+esc(s.domain)+'</option>';}).join('');var end=new Date(),start=new Date(end.getTime()-6*86400000);document.getElementById('adEnd').value=end.toISOString().slice(0,10);document.getElementById('adStart').value=start.toISOString().slice(0,10);site.onchange=function(){loadAnalyticsDetail(1);};loadAnalyticsDetail(1);}
function switchAnalyticsDetail(mode,button){analyticsDetailMode=mode;document.querySelectorAll('.analytics-tabs button').forEach(function(x){x.classList.remove('active');});button.classList.add('active');var names={events:'访问事件',sessions:'会话明细',pages:'页面分析',visitors:'访客分析'};document.getElementById('adTableTitle').textContent=names[mode];document.getElementById('adDevice').style.display=mode==='events'?'':'none';document.getElementById('adBot').style.display=(mode==='events'||mode==='sessions')?'':'none';if(mode!=='events')document.getElementById('adDevice').value='';if(mode!=='events'&&mode!=='sessions')document.getElementById('adBot').value='';loadAnalyticsDetail(1);}
function analyticsDetailParams(page){var p=new URLSearchParams({mode:analyticsDetailMode,site_id:document.getElementById('adSite').value,page:String(page||1),page_size:'30',start:document.getElementById('adStart').value,end:document.getElementById('adEnd').value});var k=document.getElementById('adKeyword').value.trim(),device=document.getElementById('adDevice').value,bot=document.getElementById('adBot').value;if(k)p.set('keyword',k);if(device&&analyticsDetailMode==='events')p.set('device',device);if(bot!=='')p.set('is_bot',bot);return p;}
async function loadAnalyticsDetail(page){if(!document.getElementById('adSite').value)return;analyticsDetailPage=page||1;var requestedMode=analyticsDetailMode,requestId=++analyticsDetailRequestId;document.getElementById('adCount').textContent='正在读取...';try{var r=await apiFetch(API_BASE+'analytics-detail.php?'+analyticsDetailParams(analyticsDetailPage)),d=await r.json();if(requestId!==analyticsDetailRequestId||requestedMode!==analyticsDetailMode)return;if(d.code!==0)throw new Error(d.msg||'读取失败');renderAnalyticsDetail(d.data);}catch(e){if(requestId!==analyticsDetailRequestId||requestedMode!==analyticsDetailMode)return;document.getElementById('adCount').textContent='读取失败';showToast(e&&e.message?e.message:'读取失败','error');}}
function renderAnalyticsDetail(data){analyticsDetailPages=data.total_pages||1;document.getElementById('adCount').textContent='共 '+data.total+' 条';var heads=[],rows=[];
 if(analyticsDetailMode==='events'){heads=['时间','页面','来源','设备','IP地址','访客/会话','流量','操作'];rows=data.list.map(function(x){return [x.occurred_at,'<strong>'+esc(x.page_title||'-')+'</strong><br><span class="mono">'+esc(x.page_path||'-')+'</span>',esc(x.source||'direct')+'<br><span class="mono">'+esc(x.referrer||'-')+'</span>',esc(x.device_type||'-'),'<span class="mono">'+esc(x.ip_address||x.masked_ip||'-')+'</span>','<span class="mono">'+esc((x.visitor_hash||'').slice(0,12))+'</span><br>会话 #'+esc(x.session_id||'-'),Number(x.is_bot)?'<span class="badge">机器人</span>':'正常','<button class="btn btn-outline btn-sm" onclick="showSessionDetail('+Number(x.session_id)+')">会话路径</button>'];});}
 if(analyticsDetailMode==='sessions'){heads=['开始时间','持续/事件','入口页面','来源','设备','IP地址','访客标识','操作'];rows=data.list.map(function(x){return [x.started_at,Number(x.duration_seconds||0)+'秒 / '+Number(x.event_count||0)+'事件','<span class="mono">'+esc(x.landing_url||'-')+'</span>',esc(x.source||'direct')+'<br>'+esc(x.referrer||'-'),esc(x.device_type||'-'),'<span class="mono">'+esc(x.ip_address||x.masked_ip||'-')+'</span>','<span class="mono">'+esc((x.visitor_hash||'').slice(0,16))+'</span>','<button class="btn btn-outline btn-sm" onclick="showSessionDetail('+Number(x.id)+')">访问路径</button>'];});}
 if(analyticsDetailMode==='pages'){heads=['页面','PV','UV','会话','首次访问','最近访问','操作'];rows=data.list.map(function(x){return ['<strong>'+esc(x.page_title||'-')+'</strong><br><span class="mono">'+esc(x.page_path||'-')+'</span>',x.pv,x.uv,x.sessions,x.first_seen_at,x.last_seen_at,'<button class="btn btn-outline btn-sm" onclick="showPageDetail(\''+encodeURIComponent(x.page_path||'')+'\')">分析</button>'];});}
 if(analyticsDetailMode==='visitors'){heads=['最近访问','IP地址','访客标识','设备/来源','会话','事件','页面','操作'];rows=data.list.map(function(x){return [x.last_seen_at,'<span class="mono">'+esc(x.ip_address||x.masked_ip||'-')+'</span>','<span class="mono">'+esc((x.visitor_hash||'').slice(0,18))+'</span>',esc(x.device_type||'-')+' / '+esc(x.source||'-'),x.session_count,x.event_count,x.page_count,'<button class="btn btn-outline btn-sm" onclick="showVisitorDetail(\''+esc(x.visitor_hash)+'\')">详情</button>'];});}
 document.getElementById('adHead').innerHTML='<tr>'+heads.map(function(h){return '<th>'+h+'</th>';}).join('')+'</tr>';document.getElementById('adRows').innerHTML=rows.map(function(row){return '<tr>'+row.map(function(c){return '<td>'+c+'</td>';}).join('')+'</tr>';}).join('')||'<tr><td colspan="'+heads.length+'" style="text-align:center;padding:30px;color:#667085">暂无数据</td></tr>';renderAnalyticsDetailPages();}
function renderAnalyticsDetailPages(){document.getElementById('adPageInfo').textContent='第 '+analyticsDetailPage+' 页 / 共 '+analyticsDetailPages+' 页';var html='<button '+(analyticsDetailPage<=1?'disabled':'')+' onclick="loadAnalyticsDetail('+(analyticsDetailPage-1)+')">«</button>';for(var i=Math.max(1,analyticsDetailPage-2);i<=Math.min(analyticsDetailPages,analyticsDetailPage+2);i++)html+='<button class="'+(i===analyticsDetailPage?'active':'')+'" onclick="loadAnalyticsDetail('+i+')">'+i+'</button>';html+='<button '+(analyticsDetailPage>=analyticsDetailPages?'disabled':'')+' onclick="loadAnalyticsDetail('+(analyticsDetailPage+1)+')">»</button>';document.getElementById('adPageButtons').innerHTML=html;}
function resetAnalyticsDetailFilters(){document.getElementById('adKeyword').value='';document.getElementById('adDevice').value='';document.getElementById('adBot').value='';var end=new Date(),start=new Date(end.getTime()-6*86400000);document.getElementById('adEnd').value=end.toISOString().slice(0,10);document.getElementById('adStart').value=start.toISOString().slice(0,10);loadAnalyticsDetail(1);}
function closeAnalyticsDetailModal(){document.getElementById('analyticsDetailModal').style.display='none';}
function openAnalyticsDetailModal(title,html){document.getElementById('analyticsDetailTitle').textContent=title;document.getElementById('analyticsDetailBody').innerHTML=html;document.getElementById('analyticsDetailModal').style.display='flex';}
async function showSessionDetail(id){var p=new URLSearchParams({mode:'session',site_id:document.getElementById('adSite').value,id:String(id)}),r=await apiFetch(API_BASE+'analytics-detail.php?'+p),d=await r.json();if(d.code!==0)return showToast(d.msg||'读取失败','error');var s=d.data.session,html='<div class="detail-grid"><div class="detail-item"><div class="detail-label">完整 IP</div><div class="mono">'+esc(s.ip_address||s.masked_ip||'-')+'</div></div><div class="detail-item"><div class="detail-label">访客标识</div><div class="mono">'+esc(s.visitor_hash)+'</div></div><div class="detail-item"><div class="detail-label">开始/结束</div><div>'+esc(s.started_at)+'<br>'+esc(s.last_seen_at)+'</div></div><div class="detail-item"><div class="detail-label">设备/来源</div><div>'+esc(s.device_type||'-')+' / '+esc(s.source||'-')+'</div></div></div><h4>访问路径</h4><ol class="path-list">'+d.data.events.map(function(e){return '<li><strong>'+esc(e.occurred_at)+'</strong> '+esc(e.page_title||'-')+'<br><span class="mono">'+esc(e.page_path||'-')+'</span></li>';}).join('')+'</ol>';openAnalyticsDetailModal('会话 #'+id,html);}
async function showVisitorDetail(hash){var p=new URLSearchParams({mode:'visitor',site_id:document.getElementById('adSite').value,visitor_hash:hash}),r=await apiFetch(API_BASE+'analytics-detail.php?'+p),d=await r.json();if(d.code!==0)return showToast(d.msg||'读取失败','error');var v=d.data.visitor,html='<div class="detail-grid"><div class="detail-item"><div class="detail-label">完整 IP</div><div class="mono">'+esc(v.ip_address||'-')+'</div></div><div class="detail-item"><div class="detail-label">访客标识</div><div class="mono">'+esc(v.visitor_hash)+'</div></div><div class="detail-item"><div class="detail-label">首次/最近</div><div>'+esc(v.first_seen_at)+'<br>'+esc(v.last_seen_at)+'</div></div><div class="detail-item"><div class="detail-label">会话数</div><div>'+v.session_count+'</div></div></div><h4>浏览页面</h4><table><thead><tr><th>页面</th><th>PV</th><th>最近访问</th></tr></thead><tbody>'+d.data.pages.map(function(x){return '<tr><td>'+esc(x.page_title||x.page_path||'-')+'<br><span class="mono">'+esc(x.page_path||'-')+'</span></td><td>'+x.pv+'</td><td>'+x.last_seen_at+'</td></tr>';}).join('')+'</tbody></table>';openAnalyticsDetailModal('访客详情',html);}
async function showPageDetail(encodedPath){var path=decodeURIComponent(encodedPath),p=new URLSearchParams({mode:'page',site_id:document.getElementById('adSite').value,path:path,start:document.getElementById('adStart').value,end:document.getElementById('adEnd').value}),r=await apiFetch(API_BASE+'analytics-detail.php?'+p),d=await r.json();if(d.code!==0)return showToast(d.msg||'读取失败','error');var s=d.data.summary,html='<div class="stats-grid"><div class="stat-card"><div class="stat-info"><div class="stat-value">'+s.pv+'</div><div class="stat-label">PV</div></div></div><div class="stat-card"><div class="stat-info"><div class="stat-value">'+s.uv+'</div><div class="stat-label">UV</div></div></div><div class="stat-card"><div class="stat-info"><div class="stat-value">'+s.sessions+'</div><div class="stat-label">会话</div></div></div></div><h4>每日趋势</h4><table><thead><tr><th>日期</th><th>PV</th><th>UV</th></tr></thead><tbody>'+d.data.daily.map(function(x){return '<tr><td>'+x.day+'</td><td>'+x.pv+'</td><td>'+x.uv+'</td></tr>';}).join('')+'</tbody></table><h4>最近访问</h4><table><thead><tr><th>时间</th><th>IP</th><th>来源</th><th>设备</th></tr></thead><tbody>'+d.data.recent.map(function(x){return '<tr><td>'+x.occurred_at+'</td><td class="mono">'+esc(x.ip_address||'-')+'</td><td>'+esc(x.source||'-')+'</td><td>'+esc(x.device_type||'-')+'</td></tr>';}).join('')+'</tbody></table>';openAnalyticsDetailModal(s.page_title||path,html);}

var statusDict = [];
var statusOptions = {};

async function loadStatusDict() {
    try {
        var res = await apiFetch(API_BASE + 'status.php');
        var data = await res.json();
        if (data.code === 0) {
            statusDict = data.data;
            statusOptions = {};
            statusDict.forEach(function(s) { statusOptions[s.code] = s; });
            // 填充筛选下拉
            var sel = document.getElementById('fStatus');
            if (sel) {
                sel.innerHTML = '<option value="">全部状态</option>';
                statusDict.forEach(function(s) {
                    sel.innerHTML += '<option value="' + esc(s.code) + '">' + esc(s.name) + '</option>';
                });
            }
        }
    } catch(e) {}
}

async function loadSources() {
    try {
        var res = await apiFetch(API_BASE + 'keys.php');
        var data = await res.json();
        if (data.code === 0) {
            allSources = data.data.filter(function(k) { return (k.site_scope || 'overseas') === 'overseas'; }).map(function(k) { return k.site_name; });
            var sel = document.getElementById('fSource');
            if (sel) {
                sel.innerHTML = '<option value="">全部来源</option>';
                allSources.forEach(function(s) {
                    sel.innerHTML += '<option value="' + esc(s) + '">' + esc(s) + '</option>';
                });
            }
        }
    } catch(e) {}
}

function getCurrentFilters() {
    var filters = {
        keyword: '',
        source: '', type: '',
        status: '',
        date_from: '',
        date_to: ''
    };
    var kwEl = document.getElementById('fKeyword');
    var srcEl = document.getElementById('fSource');
    var typeEl = document.getElementById('fType');
    var stEl = document.getElementById('fStatus');
    var sdEl = document.getElementById('fStartDate');
    var edEl = document.getElementById('fEndDate');
    if (kwEl) filters.keyword = kwEl.value.trim();
    if (srcEl) filters.source = srcEl.value;
    if (typeEl) filters.type = typeEl.value;
    if (stEl) filters.status = stEl.value;
    if (sdEl) filters.date_from = sdEl.value;
    if (edEl) filters.date_to = edEl.value;
    return filters;
}

function buildFilterParams(includeKeyword) {
    var filters = getCurrentFilters();
    var params = new URLSearchParams();
    if (includeKeyword && filters.keyword) params.set('keyword', filters.keyword);
    if (filters.source) params.set('source', filters.source);
    if (filters.type) params.set('type', filters.type);
    if (filters.status !== '') params.set('status', filters.status);
    if (filters.date_from) params.set('date_from', filters.date_from);
    if (filters.date_to) params.set('date_to', filters.date_to);
    return params;
}

function applyFilters() {
    if (isPage('index')) loadMessages(1);
    if (isPage('china')) loadMessages(1);
    if (isPage('stats')) loadStats();
}

async function loadMessages(page) {
    page = page || currentPage;
    currentPage = page;

    var params = buildFilterParams(true);
    params.set('page', page);
    params.set('page_size', pageSize);

    try {
        var endpoint = isPage('china') ? 'china-messages.php' : 'messages.php';
        var res = await apiFetch(API_BASE + endpoint + '?' + params.toString());
        var data = await res.json();
        if (data.code === 0) {
            renderTable(data.data.list, data.data.total, data.data.page, data.data.total_pages, data.data.page_size);
            if (isPage('china') && data.data.stats) {
                setText('chinaTotal', data.data.stats.total || 0); setText('chinaNew', data.data.stats.new_count || 0);
                setText('chinaContacted', data.data.stats.contacted_count || 0); setText('chinaDeal', data.data.stats.deal_count || 0);
            }
        }
    } catch(e) {
        showToast('加载留言失败：网络错误', 'error');
    }
}

function renderTable(list, total, page, totalPages) {
    var html = '';
    list.forEach(function(m) {
        var st = statusOptions[m.status] || { name: '未知', color: '#888' };
        html += '<tr>';
        html += '<td><input type="checkbox" value="' + m.id + '" class="msg-cb" onchange="updateSelection()"></td>';
        html += '<td>' + m.id + '</td>';
        html += '<td class="cell-name">' + esc(m.name) + '</td>';
        html += '<td class="cell-phone">' + esc(m.phone) + '</td>';
        var businessLabel = isPage('china') ? (m.inquiry_type || '-') : (formTypes[m.type] || m.type || '历史留言');
        html += '<td><span class="badge" style="background:#eef4ff;color:#1a73e8;">' + esc(businessLabel) + '</span></td>';
        html += '<td><span class="badge" style="background:#f0f2f5;color:#555;">' + esc(m.source) + '</span></td>';
        html += '<td><span class="badge" style="background:' + esc(st.color) + '20;color:' + esc(st.color) + ';">' + esc(st.name) + '</span></td>';
        html += '<td>' + (m.handler ? esc(m.handler) : '<span style="color:#ccc;">-</span>') + '</td>';
        html += '<td style="white-space:nowrap;font-size:12px;color:#8892a4;">' + esc(m.created_at) + '</td>';
        html += '<td><div class="table-actions"><button class="action-view" onclick="openDetail(' + m.id + ')">详情</button></div></td>';
        html += '</tr>';
    });

    document.getElementById('msgTbody').innerHTML = html;
    document.getElementById('resultCount').textContent = '共 ' + total + ' 条';
    document.getElementById('pageInfo').textContent = '第 ' + page + ' 页 / 共 ' + Math.max(1, totalPages) + ' 页';
    renderPagination(page, totalPages);
    document.getElementById('selectAll').checked = false;
    selectedIds = [];
}

function renderPagination(page, totalPages) {
    totalPages = Math.max(1, totalPages);
    var html = '';
    html += '<button ' + (page <= 1 ? 'disabled' : '') + ' onclick="loadMessages(' + (page - 1) + ')">&laquo;</button>';
    for (var i = 1; i <= totalPages; i++) {
        if (totalPages <= 7 || i === 1 || i === totalPages || Math.abs(i - page) <= 1) {
            html += '<button class="' + (i === page ? 'active' : '') + '" onclick="loadMessages(' + i + ')">' + i + '</button>';
        } else if (i === 2 && page > 4) {
            html += '<button disabled>...</button>';
        } else if (i === totalPages - 1 && page < totalPages - 3) {
            html += '<button disabled>...</button>';
        }
    }
    html += '<button ' + (page >= totalPages ? 'disabled' : '') + ' onclick="loadMessages(' + (page + 1) + ')">&raquo;</button>';
    document.getElementById('pageBtns').innerHTML = html;
}

function toggleSelectAll(el) {
    var cbs = document.querySelectorAll('.msg-cb');
    cbs.forEach(function(cb) { cb.checked = el.checked; });
    updateSelection();
}

function updateSelection() {
    var cbs = document.querySelectorAll('.msg-cb:checked');
    selectedIds = [];
    cbs.forEach(function(cb) { selectedIds.push(parseInt(cb.value)); });
}

function resetFilter() {
    var keywordEl = document.getElementById('fKeyword');
    var sourceEl = document.getElementById('fSource');
    var typeEl = document.getElementById('fType');
    var statusEl = document.getElementById('fStatus');
    var startEl = document.getElementById('fStartDate');
    var endEl = document.getElementById('fEndDate');
    if (keywordEl) keywordEl.value = '';
    if (sourceEl) sourceEl.value = '';
    if (typeEl) typeEl.value = '';
    if (statusEl) statusEl.value = '';
    if (startEl) startEl.value = '';
    if (endEl) endEl.value = '';
    applyFilters();
}

// ========== Stats ==========
async function loadStats() {
    try {
        var params = buildFilterParams(false);
        var res = await apiFetch(API_BASE + 'stats.php?' + params.toString());
        var data = await res.json();
        if (data.code === 0) {
            renderSummaryStats(data.data);
        }
    } catch(e) {}
}

function renderSummaryStats(summary) {
    var totals = summary.totals || {};
    setText('statTotal', totals.total || 0);
    setText('statNew', totals.new_count || 0);
    setText('statContacted', totals.contacted_count || 0);
    setText('statDeal', totals.deal_count || 0);
    var range = summary.range || {};
    setText('summaryRange', range.date_from && range.date_to ? range.date_from + ' 至 ' + range.date_to : '');
    renderDailyTrend(summary.daily || []);
    renderBarList('sourceChart', summary.by_source || [], '#1a73e8');
    renderBarList('statusChart', summary.by_status || [], '#1e8e3e');
}

function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
}

function renderDailyTrend(items) {
    var el = document.getElementById('dailyTrendChart');
    if (!el) return;
    if (!items.length) {
        el.innerHTML = '<div class="chart-empty">暂无统计数据</div>';
        return;
    }
    var max = Math.max.apply(null, items.map(function(item) { return item.count || 0; })) || 1;
    var html = '';
    items.forEach(function(item) {
        var height = Math.max(3, Math.round((item.count || 0) / max * 100));
        var label = (item.day || '').slice(5);
        html += '<div class="trend-item" title="' + esc(item.day) + '：' + (item.count || 0) + ' 条">'
            + '<div class="trend-value">' + (item.count || 0) + '</div>'
            + '<div class="trend-bar-wrap"><div class="trend-bar" style="height:' + height + '%;"></div></div>'
            + '<div class="trend-label">' + esc(label) + '</div>'
            + '</div>';
    });
    el.innerHTML = html;
}

function renderBarList(id, items, fallbackColor) {
    var el = document.getElementById(id);
    if (!el) return;
    if (!items.length) {
        el.innerHTML = '<div class="chart-empty">暂无统计数据</div>';
        return;
    }
    var max = Math.max.apply(null, items.map(function(item) { return item.count || 0; })) || 1;
    var html = '';
    items.forEach(function(item) {
        var color = item.color || fallbackColor;
        var percent = Math.max(2, Math.round((item.count || 0) / max * 100));
        html += '<div class="bar-row">'
            + '<div class="bar-row-head"><span>' + esc(item.name || '未命名') + '</span><strong>' + (item.count || 0) + '</strong></div>'
            + '<div class="bar-track"><div class="bar-fill" style="width:' + percent + '%;background:' + esc(color) + ';"></div></div>'
            + '</div>';
    });
    el.innerHTML = html;
}

// ========== Detail Modal ==========
var detailMsgId = 0;

async function openDetail(id) {
    try {
        var endpoint = isPage('china') ? 'china-messages.php' : 'messages.php';
        var res = await apiFetch(API_BASE + endpoint + '?id=' + id);
        var data = await res.json();
        if (data.code === 0) {
            var m = data.data;
            detailMsgId = m.id;
            document.getElementById('dId').textContent = m.id;
            document.getElementById('dName').textContent = m.name;
            document.getElementById('dPhone').textContent = m.phone;
            document.getElementById('dEmail').textContent = m.email || '-';
            document.getElementById('dType').textContent = isPage('china') ? (m.inquiry_type || '-') : (formTypes[m.type] || m.type || '历史留言');
            document.getElementById('dCompany').textContent = m.company || '-';
            document.getElementById('dCountry').textContent = isPage('china') ? (m.region || '-') : (m.country || '-');
            document.getElementById('dSource').textContent = m.source;
            document.getElementById('dSourceUrl').textContent = m.source_url || '-';
            document.getElementById('dExtra').textContent = isPage('china') ? ('期望回复方式: ' + (m.preferred_contact || '-')) : formatExtraData(m.extra_data);
            document.getElementById('dRemark').textContent = m.remark || '-';
            document.getElementById('dIP').textContent = m.ip_address || '-';
            document.getElementById('dTime').textContent = m.created_at;
            document.getElementById('dHandler').value = m.handler || '';
            document.getElementById('dHandleRecord').value = m.handle_record || '';
            document.getElementById('dValidity').value = m.validity_status || 'pending';
            document.getElementById('dValidityNote').value = m.validity_note || '';

            // 状态下拉
            var sel = document.getElementById('dStatus');
            sel.innerHTML = '';
            statusDict.forEach(function(s) {
                sel.innerHTML += '<option value="' + s.code + '" ' + (s.code == m.status ? 'selected' : '') + '>' + s.name + '</option>';
            });

            document.getElementById('detailModal').style.display = 'flex';
        }
    } catch(e) {
        showToast('获取留言详情失败', 'error');
    }
}

function closeDetail() {
    document.getElementById('detailModal').style.display = 'none';
    detailMsgId = 0;
}

async function saveDetail() {
    if (!detailMsgId) return;
    try {
        var endpoint = isPage('china') ? 'china-messages.php' : 'messages.php';
        var res = await apiFetch(API_BASE + endpoint, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: detailMsgId,
                status: parseInt(document.getElementById('dStatus').value),
                handler: document.getElementById('dHandler').value.trim(),
                handle_record: document.getElementById('dHandleRecord').value.trim()
                ,validity_status: document.getElementById('dValidity').value
                ,validity_note: document.getElementById('dValidityNote').value.trim()
            })
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast('保存成功！', 'success');
            closeDetail();
            loadMessages(currentPage);
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) {
        showToast('保存失败', 'error');
    }
}

async function batchUpdate(statusCode) {
    if (selectedIds.length === 0) { showToast('请先选择留言', 'error'); return; }
    try {
        var endpoint = isPage('china') ? 'china-messages.php' : 'messages.php';
        var res = await apiFetch(API_BASE + endpoint, {
            method: 'POST',
            body: JSON.stringify({ ids: selectedIds, status: statusCode })
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast('已批量更新 ' + selectedIds.length + ' 条', 'success');
            selectedIds = [];
            loadMessages(currentPage);
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) {
        showToast('批量更新失败', 'error');
    }
}

async function exportCSV() {
    var params = new URLSearchParams();
    var filters = getCurrentFilters();
    var kw = filters.keyword, src = filters.source, type = filters.type, st = filters.status, sd = filters.date_from, ed = filters.date_to;
    if (kw) params.set('keyword', kw);
    if (src) params.set('source', src);
    if (type) params.set('type', type);
    if (st !== '') params.set('status', st);
    if (sd) params.set('date_from', sd);
    if (ed) params.set('date_to', ed);

    try {
        var endpoint = isPage('china') ? 'china-export.php' : 'export.php';
        var res = await apiFetch(API_BASE + endpoint + '?' + params.toString());
        if (!res.ok) {
            showToast('导出失败', 'error');
            return;
        }
        var blob = await res.blob();
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'messages_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast('导出成功', 'success');
    } catch(e) {
        showToast('导出失败', 'error');
    }
}

// Click overlay to close
document.addEventListener('click', function(e) {
    if (e.target.id === 'detailModal') closeDetail();
    if (e.target.id === 'statusDictModal') closeStatusDict();
    if (e.target.id === 'editUserModal') closeEditUser();
});

// ========== Status Dict Modal (Admin) ==========
function openStatusDict() {
    if (userInfo && userInfo.role !== 'admin') {
        showToast('仅管理员可操作', 'error');
        return;
    }
    var html = '<table><thead><tr><th>状态码</th><th>状态名称</th><th>颜色</th><th>排序</th><th></th></tr></thead><tbody>';
    statusDict.forEach(function(s) {
        html += '<tr>';
        html += '<td>' + s.code + '</td>';
        html += '<td><input type="text" value="' + esc(s.name) + '" data-code="' + s.code + '" data-field="name" style="width:120px;padding:6px;border:1px solid #d1d5db;border-radius:4px;"></td>';
        html += '<td><input type="color" value="' + s.color + '" data-code="' + s.code + '" data-field="color" style="width:50px;height:32px;border:1px solid #d1d5db;border-radius:4px;"></td>';
        html += '<td><input type="number" value="' + s.sort_order + '" data-code="' + s.code + '" data-field="sort_order" style="width:60px;padding:6px;border:1px solid #d1d5db;border-radius:4px;"></td>';
        if (s.code > 3) {
            html += '<td><button class="btn btn-sm btn-danger" onclick="deleteStatusDict(' + s.code + ')">删除</button></td>';
        } else {
            html += '<td><span style="font-size:11px;color:#8892a4;">系统保留</span></td>';
        }
        html += '</tr>';
    });
    html += '</tbody></table>';
    document.getElementById('statusDictBody').innerHTML = html;
    document.getElementById('statusDictModal').style.display = 'flex';
}

function closeStatusDict() {
    document.getElementById('statusDictModal').style.display = 'none';
}

function addStatusRow() {
    var name = prompt('请输入新状态名称：');
    if (!name) return;
    apiFetch(API_BASE + 'status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name, color: '#888', sort_order: 99 })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.code === 0) {
            showToast('状态添加成功', 'success');
            loadStatusDict().then(function() { openStatusDict(); });
        } else { showToast(d.msg, 'error'); }
    });
}

async function saveStatusDict() {
    var inputs = document.querySelectorAll('#statusDictBody input[data-field]');
    var updates = {};
    inputs.forEach(function(inp) {
        var code = inp.getAttribute('data-code');
        if (!updates[code]) updates[code] = { code: parseInt(code) };
        updates[code][inp.getAttribute('data-field')] = inp.value;
    });
    var keys = Object.keys(updates);
    for (var i = 0; i < keys.length; i++) {
        await apiFetch(API_BASE + 'status.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(updates[keys[i]])
        });
    }
    showToast('状态字典已更新', 'success');
    await loadStatusDict();
    closeStatusDict();
    loadMessages(currentPage);
}

async function deleteStatusDict(code) {
    if (!confirm('确定删除该状态？')) return;
    try {
        var res = await apiFetch(API_BASE + 'status.php?code=' + code, {
            method: 'DELETE'
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast('状态已删除', 'success');
            await loadStatusDict();
            openStatusDict();
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) {
        showToast('删除失败', 'error');
    }
}

// ===================================
//  API Key 管理 (keys.html)
// ===================================

if (isPage('keys')) {
    document.addEventListener('DOMContentLoaded', function() {
        if (userInfo && userInfo.role !== 'admin') {
            document.querySelector('.page-content').innerHTML = '<div class="panel"><div class="panel-body"><div class="empty-state"><div class="empty-icon">&#x1F6AB;</div><p>仅管理员可访问此页面</p></div></div></div>';
            return;
        }
        loadKeys();
    });
}

async function loadKeys() {
    try {
        var res = await apiFetch(API_BASE + 'keys.php');
        var data = await res.json();
        if (data.code === 0) {
            allApiKeys = data.data;
            filterKeyLists();
            renderApiExamples(data.data);
        }
    } catch(e) {
        showToast('加载失败', 'error');
    }
}

var allApiKeys = [];

function keyMatches(k, keyword, status) {
    var text = ((k.api_key || '') + ' ' + (k.site_name || '')).toLowerCase();
    return (!keyword || text.indexOf(keyword.toLowerCase()) > -1) && (status === '' || String(Number(k.is_active)) === status);
}

function filterKeyLists() {
    var overseasSearch = document.getElementById('overseasKeySearch');
    var overseasStatus = document.getElementById('overseasKeyStatus');
    var chinaSearch = document.getElementById('chinaKeySearch');
    var chinaStatus = document.getElementById('chinaKeyStatus');
    if (!overseasSearch || !chinaSearch) return;
    var overseas = allApiKeys.filter(function(k) { return (k.site_scope || 'overseas') === 'overseas' && keyMatches(k, overseasSearch.value.trim(), overseasStatus.value); });
    var china = allApiKeys.filter(function(k) { return k.site_scope === 'china' && keyMatches(k, chinaSearch.value.trim(), chinaStatus.value); });
    renderKeys(overseas, 'overseasKeyTbody', 'overseasKeyCount');
    renderKeys(china, 'chinaKeyTbody', 'chinaKeyCount');
}

function renderKeys(list, tbodyId, countId) {
    document.getElementById(countId).textContent = list.length;
    var html = '';
    list.forEach(function(k) {
        html += '<tr>';
        html += '<td>' + k.id + '</td>';
        html += '<td><code class="key-code">' + esc(k.api_key) + '</code></td>';
        html += '<td><strong>' + esc(k.site_name) + '</strong></td>';
        html += '<td><span class="key-dot ' + (k.is_active ? 'active' : 'inactive') + '"></span>' + (k.is_active ? '启用' : '已禁用') + '</td>';
        html += '<td style="font-size:12px;color:#8892a4;">' + esc(k.created_at) + '</td>';
        html += '<td>';
        if (k.is_active) {
            html += '<button class="btn btn-sm btn-warning" onclick="toggleKey(' + k.id + ',0)" style="margin-right:6px;">禁用</button>';
        } else {
            html += '<button class="btn btn-sm btn-success" onclick="toggleKey(' + k.id + ',1)" style="margin-right:6px;">启用</button>';
        }
        html += '</td></tr>';
    });
    document.getElementById(tbodyId).innerHTML = html || '<tr><td colspan="6" style="text-align:center;color:#8892a4;padding:24px;">没有匹配的 API Key</td></tr>';
}

async function toggleKey(id, active) {
    try {
        var res = await apiFetch(API_BASE + 'keys.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, is_active: active })
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast(data.msg, 'success');
            loadKeys();
        }
    } catch(e) { showToast('操作失败', 'error'); }
}

// ===================================
//  用户管理 (users.html)
// ===================================

if (isPage('users')) {
    document.addEventListener('DOMContentLoaded', function() {
        if (userInfo && userInfo.role !== 'admin') {
            document.querySelector('.page-content').innerHTML = '<div class="panel"><div class="panel-body"><div class="empty-state"><div class="empty-icon">&#x1F6AB;</div><p>仅管理员可访问此页面</p></div></div></div>';
            return;
        }
        loadUsers();
        loadSourcesForUser();
    });
}

var availableSources = [];

async function loadSourcesForUser() {
    try {
        var res = await apiFetch(API_BASE + 'keys.php');
        var data = await res.json();
        if (data.code === 0) {
            availableSources = data.data.filter(function(k) { return (k.site_scope || 'overseas') === 'overseas'; }).map(function(k) { return k.site_name; });
            renderSourceCheckboxes('newUserSources', availableSources, []);
        }
    } catch(e) {}
}

function renderSourceCheckboxes(containerId, allSrcs, selected) {
    var el = document.getElementById(containerId);
    if (!el) return;
    el.innerHTML = '';
    allSrcs.forEach(function(s) {
        el.innerHTML += '<label style="display:flex;align-items:center;gap:4px;font-size:13px;cursor:pointer;padding:4px 8px;border:1px solid #e1e5eb;border-radius:6px;">'
            + '<input type="checkbox" value="' + esc(s) + '" ' + (selected.indexOf(s) > -1 ? 'checked' : '') + '> ' + esc(s) + '</label>';
    });
}

function getCheckedSources(containerId) {
    var cbs = document.querySelectorAll('#' + containerId + ' input[type="checkbox"]:checked');
    var result = [];
    cbs.forEach(function(cb) { result.push(cb.value); });
    return result;
}

async function loadUsers() {
    try {
        var res = await apiFetch(API_BASE + 'users.php');
        var data = await res.json();
        if (data.code === 0) {
            renderUsers(data.data.list);
            document.getElementById('userCount').textContent = data.data.total;
        }
    } catch(e) { showToast('加载用户列表失败', 'error'); }
}

function renderUsers(list) {
    var html = '';
    list.forEach(function(u) {
        html += '<tr>';
        html += '<td>' + u.id + '</td>';
        html += '<td><strong>' + esc(u.username) + '</strong></td>';
        html += '<td>' + (u.real_name ? esc(u.real_name) : '-') + '</td>';
        html += '<td><span class="badge ' + (u.role === 'admin' ? 'badge-new' : 'badge-contacted') + '">' + (u.role === 'admin' ? '管理员' : '普通用户') + '</span></td>';
        html += '<td style="font-size:12px;">' + (u.role === 'admin' ? '<span style="color:#1e8e3e;">全部海外来源</span>' : (u.sources && u.sources.length > 0 ? u.sources.map(esc).join(', ') : '<span style="color:#aaa;">未授权</span>')) + '</td>';
        html += '<td>' + (u.role === 'admin' ? '<span class="badge" style="background:#e8f5e9;color:#1e8e3e;">全部权限</span>' : (Number(u.can_view_china) === 1 ? '<span class="badge" style="background:#fff3e0;color:#e8710a;">已授权</span>' : '<span style="color:#aaa;">未授权</span>')) + '</td>';
        html += '<td><span class="key-dot ' + (u.is_active ? 'active' : 'inactive') + '"></span>' + (u.is_active ? '启用' : '禁用') + '</td>';
        html += '<td style="font-size:12px;color:#8892a4;">' + esc(u.created_at) + '</td>';
        html += '<td><button class="btn btn-sm btn-outline" onclick="openEditUser(' + u.id + ')">编辑</button> '
            + (Number(u.id) !== 1 ? '<button class="btn btn-sm btn-danger" onclick="deleteUser(' + u.id + ')">删除</button>' : '')
            + '</td>';
        html += '</tr>';
    });
    document.getElementById('userTbody').innerHTML = html;
}

async function addUser() {
    var username = document.getElementById('newUsername').value.trim();
    var password = document.getElementById('newPassword').value;
    var realName = document.getElementById('newRealName').value.trim();
    var role = document.getElementById('newRole').value;
    var sources = getCheckedSources('newUserSources');
    var canViewChina = document.getElementById('newCanViewChina').checked ? 1 : 0;

    if (!username || !password) { showToast('用户名和密码不能为空', 'error'); return; }
    if (role === 'user' && sources.length === 0 && !canViewChina) {
        showToast('普通用户必须至少选择海外来源或国内官网权限', 'error');
        return;
    }

    try {
        var res = await apiFetch(API_BASE + 'users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username, password: password, real_name: realName, role: role, sources: sources, can_view_china: canViewChina })
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast('用户创建成功', 'success');
            document.getElementById('newUsername').value = '';
            document.getElementById('newPassword').value = '';
            document.getElementById('newRealName').value = '';
            loadUsers();
            loadSourcesForUser();
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) { showToast('创建失败', 'error'); }
}

async function openEditUser(id) {
    try {
        // 从用户列表获取当前数据
        var res = await apiFetch(API_BASE + 'users.php');
        var data = await res.json();
        var user = null;
        if (data.code === 0) {
            for (var i = 0; i < data.data.list.length; i++) {
                if (Number(data.data.list[i].id) === Number(id)) { user = data.data.list[i]; break; }
            }
        }
        if (!user) { showToast('用户不存在', 'error'); return; }

        editingUserId = user.id;
        document.getElementById('euUsername').textContent = user.username;
        document.getElementById('euRealName').value = user.real_name || '';
        document.getElementById('euRole').value = user.role;
        document.getElementById('euActive').value = user.is_active;
        document.getElementById('euCanViewChina').checked = Number(user.can_view_china) === 1;
        document.getElementById('euPassword').value = '';

        var allSrc = user.all_sources || availableSources;
        renderSourceCheckboxes('euSources', allSrc, user.sources || []);

        document.getElementById('editUserModal').style.display = 'flex';
    } catch(e) { showToast('加载失败', 'error'); }
}

function closeEditUser() {
    document.getElementById('editUserModal').style.display = 'none';
    editingUserId = 0;
}

async function saveEditUser() {
    if (!editingUserId) return;
    var body = {
        id: editingUserId,
        real_name: document.getElementById('euRealName').value.trim(),
        role: document.getElementById('euRole').value,
        is_active: parseInt(document.getElementById('euActive').value),
        sources: getCheckedSources('euSources'),
        can_view_china: document.getElementById('euCanViewChina').checked ? 1 : 0
    };
    if (body.role === 'user' && body.sources.length === 0 && !body.can_view_china) {
        showToast('普通用户必须至少选择海外来源或国内官网权限', 'error');
        return;
    }
    var pw = document.getElementById('euPassword').value;
    if (pw) body.password = pw;

    try {
        var res = await apiFetch(API_BASE + 'users.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        var data = await res.json();
        if (data.code === 0) {
            showToast('用户更新成功', 'success');
            closeEditUser();
            loadUsers();
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) { showToast('保存失败', 'error'); }
}

async function deleteUser(id) {
    if (!confirm('确定删除该用户？')) return;
    try {
        var res = await apiFetch(API_BASE + 'users.php?id=' + id, { method: 'DELETE' });
        var data = await res.json();
        if (data.code === 0) { showToast('用户已删除', 'success'); loadUsers(); }
        else { showToast(data.msg, 'error'); }
    } catch(e) { showToast('删除失败', 'error'); }
}

// ========== Helpers ==========
function esc(str) {
    if (!str) return '';
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

var apiExamples = [
    {
        site: 'overseas', title: '联系我们', endpoint: 'business-inquiry.php',
        payload: { name: 'Alex Morgan', email: 'alex@example.com', phone: '+1 202 555 0123', company: 'Example Inc.', country: 'United States', industry: 'Healthcare', inquiry_type: 'Product Inquiry', remark: 'Please send product and pricing information.', marketing_consent: false, source_url: 'https://example.com/business-inquiries.html' }
    },
    {
        site: 'overseas', title: '样品申请', endpoint: 'sample-request.php',
        payload: { name: 'Maria Garcia', email: 'maria@example.com', phone: '+34 612 345 678', company: 'Example Medical', country: 'Spain', target_product: 'Medical protective product sample', remark: 'For hospital evaluation; estimated order quantity 10000.', privacy_consent: true, source_url: 'https://example.com/sample-request.html' }
    },
    {
        site: 'overseas', title: '招商合作', endpoint: 'strategic-partnership.php',
        payload: { name: 'David Lee', email: 'david@example.com', phone: '+65 6123 4567', company: 'Example Distribution', country: 'Singapore', partnership_type: 'Distribution Partnerships', remark: 'We would like to discuss distribution cooperation in Southeast Asia.', source_url: 'https://example.com/investment-opportunities.html' }
    },
    {
        site: 'overseas', title: '人才申请', endpoint: 'career-introduction.php',
        payload: { name: 'Emma Wilson', email: 'emma@example.com', phone: '+44 20 7946 0958', company: 'Example University', country: 'United Kingdom', expertise: 'R&D & Product Development', role: 'Product Development Engineer', current_position: 'Materials Engineering Graduate', experience_years: 2, languages: 'English, Chinese', preferred_location: 'Shandong, China', strengths: 'Medical material research and international project experience.', resume_url: 'https://example.com/resume.pdf', remark: 'Available to relocate.', source_url: 'https://example.com/business-inquiries.html?topic=Global-Careers' }
    },
    {
        site: 'overseas', title: '工厂参观', endpoint: 'factory-visit.php',
        payload: { name: 'John Smith', email: 'john@example.com', phone: '+61 2 9374 4000', company: 'Example Procurement', country: 'Australia', visit_date: '2026-09-15', visitor_count: 3, factory: 'SAN QI MEDICAL — Rizhao, Shandong, China', remark: 'We would like to review production and quality-control facilities.', privacy_consent: true, source_url: 'https://example.com/factory-visit.html' }
    },
    {
        site: 'china', title: '官网留言', endpoint: 'china-website-inquiry.php',
        payload: { inquiry_type: '代理与经销', region: '山东省日照市', name: '张先生', company: '示例医疗科技有限公司', phone: '13800138000', email: 'contact@example.cn', remark: '希望了解产品代理政策、起订量和区域合作要求。', preferred_contact: '电话', source_url: 'https://www.example.cn/contact.html' }
    }
];

function renderApiExamples(keys) {
    var keySelect = document.getElementById('exampleApiKey');
    var container = document.getElementById('apiExampleCards');
    if (!keySelect || !container) return;
    keySelect.innerHTML = '<option value="">请选择一个已启用的 API Key</option>';
    keys.filter(function(k) { return Number(k.is_active) === 1; }).forEach(function(k) {
        keySelect.innerHTML += '<option data-scope="' + esc(k.site_scope || 'overseas') + '" value="' + esc(k.api_key) + '">' + esc(k.site_name) + ' — ' + esc(k.api_key) + '</option>';
    });
    if (keySelect.options.length > 1) keySelect.selectedIndex = 1;

    renderApiExampleCards();
}

var apiSiteNames = { overseas: '海外独立站', china: '三奇国内官网' };

function renderApiExampleCards() {
    var container = document.getElementById('apiExampleCards');
    var siteFilter = document.getElementById('apiSiteFilter');
    var searchInput = document.getElementById('apiInterfaceSearch');
    if (!container || !siteFilter || !searchInput) return;
    var selectedSite = siteFilter.value;
    var keyword = searchInput.value.trim().toLowerCase();
    var matches = apiExamples.map(function(item, index) { return { item: item, index: index }; }).filter(function(entry) {
        var item = entry.item;
        var haystack = (item.title + ' ' + item.endpoint + ' ' + Object.keys(item.payload).join(' ') + ' ' + (apiSiteNames[item.site] || item.site)).toLowerCase();
        return (!selectedSite || item.site === selectedSite) && (!keyword || haystack.indexOf(keyword) > -1);
    });
    var groups = ['overseas', 'china'];
    var html = '';
    groups.forEach(function(site) {
        var groupItems = matches.filter(function(entry) { return entry.item.site === site; });
        if (!groupItems.length) return;
        var accent = site === 'china' ? '#e8710a' : '#1a73e8';
        html += '<div style="margin-top:22px;padding:14px 16px;border-left:4px solid ' + accent + ';background:#f8fafc;border-radius:8px;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;"><h4 style="margin:0;color:' + accent + ';">' + esc(apiSiteNames[site]) + '</h4>'
            + '<span style="font-size:12px;color:#8892a4;">' + groupItems.length + ' 个接口</span></div></div>';
        groupItems.forEach(function(entry) {
            var item = entry.item, index = entry.index;
        var sample = JSON.stringify(Object.assign({ api_key: 'YOUR_KEY_HERE' }, item.payload), null, 2);
        html += '<section style="margin-top:12px;border:1px solid #e1e5eb;border-radius:10px;overflow:hidden;">'
            + '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;padding:12px 14px;background:#f5f7fa;">'
            + '<strong>' + esc(item.title) + '</strong><code>' + esc(PUBLIC_SERVICE_ORIGIN + '/api/' + item.endpoint) + '</code></div>'
            + '<div style="padding:10px 14px 0;color:#5f6775;font-size:12px;">字段：<code>' + esc(Object.keys(item.payload).join(', ')) + '</code></div>'
            + '<div style="padding:14px;"><label style="font-size:12px;color:#5f6775;">可编辑请求 JSON</label>'
            + '<textarea id="apiPayload' + index + '" style="width:100%;min-height:230px;margin-top:6px;padding:12px;background:#1a1a2e;color:#e0e0e0;border:0;border-radius:8px;font:12px/1.55 Consolas,monospace;resize:vertical;">' + esc(sample) + '</textarea>'
            + '<div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap;">'
            + '<button class="btn btn-primary" onclick="runApiExample(' + index + ')">发送测试请求</button>'
            + '<button class="btn btn-outline" onclick="copyApiExample(' + index + ')">复制可运行 fetch 代码</button></div>'
            + '<pre id="apiResult' + index + '" style="display:none;margin-top:10px;padding:12px;background:#101828;color:#d1e9ff;border-radius:8px;white-space:pre-wrap;"></pre></div></section>';
        });
    });
    container.innerHTML = html || '<div class="empty-state" style="padding:32px;"><p>没有匹配的接口</p></div>';
}

function getApiExamplePayload(index) {
    var select = document.getElementById('exampleApiKey');
    var expectedScope = apiExamples[index].site;
    var selectedOption = select.options[select.selectedIndex];
    if (!selectedOption || selectedOption.getAttribute('data-scope') !== expectedScope) {
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].getAttribute('data-scope') === expectedScope) { select.selectedIndex = i; break; }
        }
    }
    var key = select.value;
    if (!key) throw new Error('请先选择一个已启用的 API Key');
    var payload = JSON.parse(document.getElementById('apiPayload' + index).value);
    payload.api_key = key;
    return payload;
}

async function runApiExample(index) {
    var result = document.getElementById('apiResult' + index);
    try {
        var payload = getApiExamplePayload(index);
        result.style.display = 'block';
        result.textContent = '正在发送...';
        var response = await fetch(PUBLIC_SERVICE_ORIGIN + '/api/' + apiExamples[index].endpoint, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        var data = await response.json();
        result.textContent = 'HTTP ' + response.status + '\n' + JSON.stringify(data, null, 2);
        showToast(data.code === 0 ? '测试提交成功，已生成留言记录' : data.msg, data.code === 0 ? 'success' : 'error');
    } catch(e) {
        result.style.display = 'block';
        result.textContent = '请求失败：' + e.message;
        showToast(e.message, 'error');
    }
}

async function copyApiExample(index) {
    try {
        var payload = getApiExamplePayload(index);
        var url = PUBLIC_SERVICE_ORIGIN + '/api/' + apiExamples[index].endpoint;
        var code = "fetch('" + url + "', {\n  method: 'POST',\n  headers: { 'Content-Type': 'application/json' },\n  body: JSON.stringify(" + JSON.stringify(payload, null, 2).replace(/^/gm, '  ') + ")\n})\n.then(response => response.json())\n.then(data => console.log(data))\n.catch(error => console.error(error));";
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(code);
        } else {
            var copyBox = document.createElement('textarea');
            copyBox.value = code;
            copyBox.style.position = 'fixed';
            copyBox.style.opacity = '0';
            document.body.appendChild(copyBox);
            copyBox.select();
            if (!document.execCommand('copy')) throw new Error('浏览器不允许自动复制，请手动复制请求 JSON');
            copyBox.remove();
        }
        showToast('可运行 fetch 代码已复制', 'success');
    } catch(e) { showToast(e.message || '复制失败', 'error'); }
}

function formatExtraData(raw) {
    if (!raw) return '-';
    try {
        var value = typeof raw === 'string' ? JSON.parse(raw) : raw;
        return Object.keys(value).map(function(key) { return key + ': ' + String(value[key]); }).join('\n') || '-';
    } catch(e) { return String(raw); }
}

document.addEventListener('DOMContentLoaded', function() {
    var typeEl = document.getElementById('fType');
    if (!typeEl) return;
    Object.keys(formTypes).forEach(function(type) {
        typeEl.innerHTML += '<option value="' + esc(type) + '">' + esc(formTypes[type]) + '</option>';
    });
});
