// ==========================================
// 留言管理系统 - 前端引擎
// ==========================================

var API_BASE = '../api/admin/';
var userInfo = null;
var currentPage = 1, pageSize = 20;
var selectedIds = [];
var editingUserId = 0;
var allSources = [];

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
    var html = '<div class="nav-section">主菜单</div>';
    html += '<a href="index.html" class="' + (isPage('index') ? 'active' : '') + '"><span class="nav-icon">&#x1F4E9;</span> 留言列表</a>';
    if (userInfo.role === 'admin') {
        html += '<a href="keys.html" class="' + (isPage('keys') ? 'active' : '') + '"><span class="nav-icon">&#x1F511;</span> API Key 管理</a>';
        html += '<a href="users.html" class="' + (isPage('users') ? 'active' : '') + '"><span class="nav-icon">&#x1F465;</span> 用户管理</a>';
        html += '<div class="nav-section" style="margin-top:16px;">系统设置</div>';
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
        await loadStats();
    });
}

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
            allSources = data.data.map(function(k) { return k.site_name; });
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
        source: '',
        status: '',
        date_from: '',
        date_to: ''
    };
    var kwEl = document.getElementById('fKeyword');
    var srcEl = document.getElementById('fSource');
    var stEl = document.getElementById('fStatus');
    var sdEl = document.getElementById('fStartDate');
    var edEl = document.getElementById('fEndDate');
    if (kwEl) filters.keyword = kwEl.value.trim();
    if (srcEl) filters.source = srcEl.value;
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
    if (filters.status !== '') params.set('status', filters.status);
    if (filters.date_from) params.set('date_from', filters.date_from);
    if (filters.date_to) params.set('date_to', filters.date_to);
    return params;
}

function applyFilters() {
    loadMessages(1);
    loadStats();
}

async function loadMessages(page) {
    page = page || currentPage;
    currentPage = page;

    var params = buildFilterParams(true);
    params.set('page', page);
    params.set('page_size', pageSize);

    try {
        var res = await apiFetch(API_BASE + 'messages.php?' + params.toString());
        var data = await res.json();
        if (data.code === 0) {
            renderTable(data.data.list, data.data.total, data.data.page, data.data.total_pages, data.data.page_size);
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
    document.getElementById('fKeyword').value = '';
    document.getElementById('fSource').value = '';
    document.getElementById('fStatus').value = '';
    document.getElementById('fStartDate').value = '';
    document.getElementById('fEndDate').value = '';
    loadMessages(1);
    loadStats();
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
        var res = await apiFetch(API_BASE + 'messages.php?id=' + id);
        var data = await res.json();
        if (data.code === 0) {
            var m = data.data;
            detailMsgId = m.id;
            document.getElementById('dId').textContent = m.id;
            document.getElementById('dName').textContent = m.name;
            document.getElementById('dPhone').textContent = m.phone;
            document.getElementById('dEmail').textContent = m.email || '-';
            document.getElementById('dSource').textContent = m.source;
            document.getElementById('dRemark').textContent = m.remark || '-';
            document.getElementById('dIP').textContent = m.ip_address || '-';
            document.getElementById('dTime').textContent = m.created_at;
            document.getElementById('dHandler').value = m.handler || '';
            document.getElementById('dHandleRecord').value = m.handle_record || '';

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
        var res = await apiFetch(API_BASE + 'messages.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: detailMsgId,
                status: parseInt(document.getElementById('dStatus').value),
                handler: document.getElementById('dHandler').value.trim(),
                handle_record: document.getElementById('dHandleRecord').value.trim()
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
        var res = await apiFetch(API_BASE + 'messages.php', {
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
    var kw = document.getElementById('fKeyword').value.trim();
    var src = document.getElementById('fSource').value;
    var st = document.getElementById('fStatus').value;
    var sd = document.getElementById('fStartDate').value;
    var ed = document.getElementById('fEndDate').value;
    if (kw) params.set('keyword', kw);
    if (src) params.set('source', src);
    if (st !== '') params.set('status', st);
    if (sd) params.set('date_from', sd);
    if (ed) params.set('date_to', ed);

    try {
        var res = await apiFetch(API_BASE + 'export.php?' + params.toString());
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
            renderKeys(data.data);
        }
    } catch(e) {
        showToast('加载失败', 'error');
    }
}

function renderKeys(list) {
    document.getElementById('keyCount').textContent = list.length;
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
        html += '<button class="btn btn-sm btn-danger" onclick="deleteKey(' + k.id + ')">删除</button>';
        html += '</td></tr>';
    });
    document.getElementById('keyTbody').innerHTML = html;
}

async function addKey() {
    var name = document.getElementById('newSourceName').value.trim();
    if (!name) { showToast('请输入来源名称', 'error'); return; }
    try {
        var res = await apiFetch(API_BASE + 'keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ site_name: name })
        });
        var data = await res.json();
        if (data.code === 0) {
            document.getElementById('newSourceName').value = '';
            showToast('API Key 创建成功', 'success');
            loadKeys();
        } else {
            showToast(data.msg, 'error');
        }
    } catch(e) {
        showToast('创建失败', 'error');
    }
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

async function deleteKey(id) {
    if (!confirm('确定删除该 Key？')) return;
    try {
        var res = await apiFetch(API_BASE + 'keys.php?id=' + id, { method: 'DELETE' });
        var data = await res.json();
        if (data.code === 0) { showToast('已删除', 'success'); loadKeys(); }
    } catch(e) { showToast('删除失败', 'error'); }
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
            availableSources = data.data.map(function(k) { return k.site_name; });
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
        html += '<td style="font-size:12px;">' + (u.role === 'admin' ? '<span style="color:#1e8e3e;">全部来源</span>' : (u.sources && u.sources.length > 0 ? u.sources.map(esc).join(', ') : '<span style="color:#d93025;">无权限</span>')) + '</td>';
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

    if (!username || !password) { showToast('用户名和密码不能为空', 'error'); return; }
    if (role === 'user' && sources.length === 0) {
        showToast('普通用户必须至少选择一个可见来源', 'error');
        return;
    }

    try {
        var res = await apiFetch(API_BASE + 'users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username: username, password: password, real_name: realName, role: role, sources: sources })
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
        sources: getCheckedSources('euSources')
    };
    if (body.role === 'user' && body.sources.length === 0) {
        showToast('普通用户必须至少选择一个可见来源', 'error');
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
