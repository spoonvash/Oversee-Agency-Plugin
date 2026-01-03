<?php if (!defined('ABSPATH')) define('ABSPATH', true); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Support Tickets</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #e0e0e0; min-height: 100vh; }
.login-wrap { display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
.login-box { background: #1a1a1a; padding: 40px; border-radius: 12px; max-width: 400px; width: 100%; }
.login-box h1 { margin-bottom: 25px; text-align: center; }
.login-box input { width: 100%; padding: 12px; margin-bottom: 15px; background: #111; border: 1px solid #333; border-radius: 8px; color: #fff; }
.login-box button { width: 100%; padding: 12px; background: #FF6B35; border: none; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer; }
.dashboard { display: none; }
.dashboard.active { display: block; }
.topbar { background: #1a1a1a; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; }
.topbar h1 { font-size: 1.3rem; }
.content { padding: 25px; max-width: 1200px; margin: 0 auto; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 25px; }
.stat { background: #1a1a1a; padding: 20px; border-radius: 10px; text-align: center; }
.stat-value { font-size: 2rem; font-weight: 700; color: #FF6B35; }
.stat-label { font-size: 13px; color: #888; margin-top: 5px; }
.card { background: #1a1a1a; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
.card h2 { margin-bottom: 15px; font-size: 1.1rem; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; text-align: left; border-bottom: 1px solid #333; }
th { color: #888; font-weight: 500; font-size: 13px; }
.status { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
.status-open { background: rgba(34,197,94,0.2); color: #22c55e; }
.status-in_progress { background: rgba(245,158,11,0.2); color: #f59e0b; }
.status-resolved { background: rgba(6,182,212,0.2); color: #06b6d4; }
.btn { padding: 8px 16px; background: #FF6B35; border: none; border-radius: 6px; color: #fff; cursor: pointer; font-size: 13px; }
.btn:hover { background: #e55a2b; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
select { padding: 8px; background: #111; border: 1px solid #333; border-radius: 6px; color: #fff; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="login-wrap" id="loginWrap">
<div class="login-box">
<h1>🔐 Admin Login</h1>
<input type="password" id="apiKey" placeholder="Enter API Key">
<button onclick="login()">Login</button>
</div>
</div>

<div class="dashboard" id="dashboard">
<div class="topbar">
<h1>📊 Support Dashboard</h1>
<button class="btn" onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
</div>
<div class="content">
<div class="stats" id="stats"></div>
<div class="card">
<h2>Recent Tickets</h2>
<div style="margin-bottom:15px">
<select id="statusFilter" onchange="loadAdminTickets()">
<option value="">All Status</option>
<option value="open">Open</option>
<option value="in_progress">In Progress</option>
<option value="resolved">Resolved</option>
<option value="closed">Closed</option>
</select>
</div>
<table>
<thead><tr><th>ID</th><th>Subject</th><th>From</th><th>Category</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
<tbody id="ticketsTable"></tbody>
</table>
</div>
</div>
</div>

<script>
const API = '<?php echo rest_url("oversee/v1"); ?>';
let apiKey = localStorage.getItem('oversee_admin_key') || '';

if (apiKey) login();

function login() {
    apiKey = document.getElementById('apiKey')?.value || apiKey;
    if (!apiKey) return alert('Enter API key');
    localStorage.setItem('oversee_admin_key', apiKey);
    document.getElementById('loginWrap').style.display = 'none';
    document.getElementById('dashboard').classList.add('active');
    loadStats();
    loadAdminTickets();
}

function logout() {
    localStorage.removeItem('oversee_admin_key');
    location.reload();
}

async function loadStats() {
    try {
        const res = await fetch(API + '/admin/stats', { headers: { 'X-API-Key': apiKey } });
        const s = await res.json();
        document.getElementById('stats').innerHTML = `
            <div class="stat"><div class="stat-value">${s.total||0}</div><div class="stat-label">Total</div></div>
            <div class="stat"><div class="stat-value">${s.open||0}</div><div class="stat-label">Open</div></div>
            <div class="stat"><div class="stat-value">${s.in_progress||0}</div><div class="stat-label">In Progress</div></div>
            <div class="stat"><div class="stat-value">${s.resolved||0}</div><div class="stat-label">Resolved</div></div>
            <div class="stat"><div class="stat-value">${s.kb_articles||0}</div><div class="stat-label">KB Articles</div></div>
        `;
    } catch(e) { console.error(e); }
}

async function loadAdminTickets() {
    const status = document.getElementById('statusFilter').value;
    try {
        const res = await fetch(API + '/admin/tickets' + (status ? '?status=' + status : ''), { headers: { 'X-API-Key': apiKey } });
        const tickets = await res.json();
        document.getElementById('ticketsTable').innerHTML = tickets.map(t => `
            <tr>
                <td>${t.ticket_id}</td>
                <td>${t.subject}</td>
                <td>${t.name}<br><small style="color:#888">${t.email}</small></td>
                <td>${t.category}</td>
                <td><span class="status status-${t.status}">${t.status}</span></td>
                <td>${new Date(t.created_at).toLocaleDateString()}</td>
                <td>
                    <select onchange="updateStatus(${t.id}, this.value)" style="padding:5px">
                        <option value="open" ${t.status=='open'?'selected':''}>Open</option>
                        <option value="in_progress" ${t.status=='in_progress'?'selected':''}>In Progress</option>
                        <option value="resolved" ${t.status=='resolved'?'selected':''}>Resolved</option>
                        <option value="closed" ${t.status=='closed'?'selected':''}>Closed</option>
                    </select>
                </td>
            </tr>
        `).join('');
    } catch(e) { console.error(e); }
}

async function updateStatus(id, status) {
    await fetch(API + '/admin/tickets/' + id, { method: 'PUT', headers: { 'X-API-Key': apiKey, 'Content-Type': 'application/json' }, body: JSON.stringify({ status }) });
    loadStats();
    loadAdminTickets();
}
</script>
</body>
</html>