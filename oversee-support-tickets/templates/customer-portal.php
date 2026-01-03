<?php if (!defined('ABSPATH')) define('ABSPATH', true); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Support - OverseeCRM</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; color: #fff; }
.container { max-width: 1100px; margin: 0 auto; padding: 20px; }
.header { text-align: center; padding: 40px 0 30px; }
.header h1 { font-size: 2.2rem; margin-bottom: 8px; }
.header p { color: #9ca3af; }
.tabs { display: flex; justify-content: center; gap: 10px; margin-bottom: 25px; flex-wrap: wrap; }
.tab { padding: 12px 20px; background: rgba(255,255,255,0.08); border: none; border-radius: 8px; color: #fff; cursor: pointer; font-size: 14px; transition: all 0.2s; }
.tab:hover, .tab.active { background: #FF6B35; }
.panel { display: none; }
.panel.active { display: block; }
.card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; padding: 25px; margin-bottom: 20px; }
.card h2 { font-size: 1.3rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
.card h2 i { color: #FF6B35; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
.form-group { margin-bottom: 15px; }
.form-group label { display: block; margin-bottom: 6px; font-size: 14px; color: #ccc; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.15); border-radius: 8px; color: #fff; font-size: 14px; }
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #FF6B35; }
.form-group textarea { min-height: 120px; resize: vertical; }
.form-group select { cursor: pointer; }
.btn { padding: 12px 24px; background: #FF6B35; border: none; border-radius: 8px; color: #fff; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.2s; }
.btn:hover { background: #e55a2b; }
.btn-secondary { background: rgba(255,255,255,0.1); }
.btn-secondary:hover { background: rgba(255,255,255,0.15); }
.tickets-list { margin-top: 20px; }
.ticket-item { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 15px; margin-bottom: 10px; cursor: pointer; border-left: 4px solid #FF6B35; transition: all 0.2s; }
.ticket-item:hover { background: rgba(0,0,0,0.3); }
.ticket-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.ticket-id { color: #FF6B35; font-weight: 600; font-size: 14px; }
.ticket-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; text-transform: uppercase; }
.status-open { background: rgba(34,197,94,0.2); color: #22c55e; }
.status-in_progress { background: rgba(245,158,11,0.2); color: #f59e0b; }
.status-pending { background: rgba(99,102,241,0.2); color: #6366f1; }
.status-resolved { background: rgba(6,182,212,0.2); color: #06b6d4; }
.status-closed { background: rgba(107,114,128,0.2); color: #9ca3af; }
.ticket-subject { font-weight: 500; margin-bottom: 4px; }
.ticket-meta { font-size: 12px; color: #9ca3af; }
.kb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 20px; }
.kb-card { background: rgba(0,0,0,0.2); border-radius: 8px; padding: 20px; text-decoration: none; color: #fff; transition: all 0.2s; border: 1px solid transparent; }
.kb-card:hover { border-color: #FF6B35; background: rgba(0,0,0,0.3); }
.kb-card h4 { color: #FF6B35; margin-bottom: 5px; }
.kb-card span { font-size: 13px; color: #9ca3af; }
.modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.85); z-index: 1000; align-items: center; justify-content: center; padding: 20px; }
.modal.active { display: flex; }
.modal-content { background: #1a1a2e; border-radius: 12px; max-width: 650px; width: 100%; max-height: 85vh; overflow-y: auto; }
.modal-header { padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 1.1rem; }
.modal-close { background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; line-height: 1; }
.modal-body { padding: 20px; }
.comment { padding: 12px; background: rgba(0,0,0,0.2); border-radius: 8px; margin-bottom: 10px; }
.comment.agent { border-left: 3px solid #FF6B35; }
.comment-header { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
.comment-author { font-weight: 600; }
.comment-date { color: #9ca3af; }
.comment-content { font-size: 14px; line-height: 1.6; }
.empty { text-align: center; padding: 40px; color: #9ca3af; }
.iframe-wrap { background: #fff; border-radius: 12px; overflow: hidden; height: 550px; }
.iframe-wrap iframe { width: 100%; height: 100%; border: none; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container">
<div class="header">
<h1>🎧 Support Center</h1>
<p>How can we help you today?</p>
</div>

<div class="tabs">
<button class="tab active" onclick="showPanel('tickets', this)"><i class="fas fa-ticket-alt"></i> Submit Ticket</button>
<button class="tab" onclick="showPanel('kb', this)"><i class="fas fa-book"></i> Knowledge Base</button>
<button class="tab" onclick="showPanel('chat', this)"><i class="fas fa-comments"></i> Live Chat</button>
<button class="tab" onclick="showPanel('call', this)"><i class="fas fa-calendar"></i> Schedule Call</button>
</div>

<!-- TICKETS PANEL -->
<div id="tickets" class="panel active">
<div class="card">
<h2><i class="fas fa-plus-circle"></i> Submit a New Ticket</h2>
<form id="ticketForm">
<div class="form-row">
<div class="form-group"><label>Your Name *</label><input type="text" id="name" required></div>
<div class="form-group"><label>Email *</label><input type="email" id="email" required></div>
</div>
<div class="form-row">
<div class="form-group"><label>Phone</label><input type="tel" id="phone"></div>
<div class="form-group"><label>Category</label>
<select id="category">
<option>General</option><option>Dashboard</option><option>Conversations</option><option>Calendars</option>
<option>Contacts</option><option>Opportunities</option><option>Payments</option><option>Marketing</option>
<option>Automation</option><option>Sites</option><option>Reputation</option><option>Phone</option>
<option>Email</option><option>Integrations</option><option>Reporting</option><option>Mobile App</option>
</select></div>
</div>
<div class="form-group"><label>Subject *</label><input type="text" id="subject" required></div>
<div class="form-group"><label>Description *</label><textarea id="description" required placeholder="Describe your issue in detail..."></textarea></div>
<button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
</form>
</div>

<div class="card">
<h2><i class="fas fa-list"></i> Your Tickets</h2>
<div class="form-row" style="align-items:end">
<div class="form-group" style="margin:0"><label>Email</label><input type="email" id="lookupEmail" placeholder="Enter your email"></div>
<button class="btn btn-secondary" onclick="loadTickets()"><i class="fas fa-search"></i> Find</button>
</div>
<div id="ticketsList" class="tickets-list"></div>
</div>
</div>

<!-- KB PANEL -->
<div id="kb" class="panel">
<div class="card">
<h2><i class="fas fa-book"></i> Knowledge Base</h2>
<p style="margin-bottom:15px;color:#9ca3af">Browse our help articles or search for answers</p>
<a href="<?php echo plugin_dir_url(__FILE__); ?>../knowledge-base/index.html" class="btn" target="_blank"><i class="fas fa-external-link-alt"></i> Open Knowledge Base</a>
</div>
</div>

<!-- CHAT PANEL -->
<div id="chat" class="panel">
<div class="card">
<h2><i class="fas fa-comments"></i> Live Chat</h2>
<p style="margin-bottom:15px;color:#9ca3af">Chat with our support team</p>
<div class="iframe-wrap">
<iframe src="<?php echo esc_url(get_option('oversee_chat_url', 'about:blank')); ?>"></iframe>
</div>
</div>
</div>

<!-- CALL PANEL -->
<div id="call" class="panel">
<div class="card">
<h2><i class="fas fa-calendar"></i> Schedule a Call</h2>
<p style="margin-bottom:15px;color:#9ca3af">Book a time with our team</p>
<div class="iframe-wrap">
<iframe src="<?php echo esc_url(get_option('oversee_calendar_url', 'about:blank')); ?>"></iframe>
</div>
</div>
</div>
</div>

<!-- TICKET MODAL -->
<div id="ticketModal" class="modal">
<div class="modal-content">
<div class="modal-header">
<h3 id="modalTitle">Ticket Details</h3>
<button class="modal-close" onclick="closeModal()">&times;</button>
</div>
<div class="modal-body" id="modalBody"></div>
</div>
</div>

<script>
const API = '<?php echo rest_url("oversee/v1"); ?>';
document.getElementById('email').value = localStorage.getItem('oversee_email') || '';
document.getElementById('lookupEmail').value = localStorage.getItem('oversee_email') || '';

function showPanel(id, btn) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
}

document.getElementById('ticketForm').onsubmit = async (e) => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    
    const data = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        category: document.getElementById('category').value,
        subject: document.getElementById('subject').value,
        description: document.getElementById('description').value
    };
    localStorage.setItem('oversee_email', data.email);
    
    try {
        const res = await fetch(API + '/tickets', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) });
        const r = await res.json();
        if (r.success) { alert('Ticket created: ' + r.ticket_id); e.target.reset(); document.getElementById('email').value = data.email; loadTickets(); }
        else alert('Error creating ticket');
    } catch(err) { alert('Error: ' + err.message); }
    
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Ticket';
};

async function loadTickets() {
    const email = document.getElementById('lookupEmail').value;
    if (!email) return alert('Enter your email');
    localStorage.setItem('oversee_email', email);
    
    try {
        const res = await fetch(API + '/tickets?email=' + encodeURIComponent(email));
        const tickets = await res.json();
        document.getElementById('ticketsList').innerHTML = tickets.length ? tickets.map(t => `
            <div class="ticket-item" onclick="viewTicket('${t.ticket_id}')">
                <div class="ticket-header">
                    <span class="ticket-id">${t.ticket_id}</span>
                    <span class="ticket-status status-${t.status}">${t.status.replace('_',' ')}</span>
                </div>
                <div class="ticket-subject">${esc(t.subject)}</div>
                <div class="ticket-meta">${t.category} • ${new Date(t.created_at).toLocaleDateString()}</div>
            </div>
        `).join('') : '<div class="empty">No tickets found</div>';
    } catch(err) { alert('Error loading tickets'); }
}

async function viewTicket(id) {
    try {
        const res = await fetch(API + '/tickets/' + id);
        const t = await res.json();
        document.getElementById('modalTitle').textContent = t.ticket_id + ': ' + t.subject;
        document.getElementById('modalBody').innerHTML = `
            <p><strong>Status:</strong> <span class="ticket-status status-${t.status}">${t.status}</span></p>
            <p><strong>Category:</strong> ${t.category}</p>
            <p><strong>Created:</strong> ${new Date(t.created_at).toLocaleString()}</p>
            <hr style="margin:20px 0;border-color:rgba(255,255,255,0.1)">
            <h4>Description</h4>
            <p style="margin-top:10px">${esc(t.description)}</p>
            <hr style="margin:20px 0;border-color:rgba(255,255,255,0.1)">
            <h4>Conversation</h4>
            <div style="margin-top:15px">${(t.comments||[]).map(c => `
                <div class="comment ${c.is_agent?'agent':''}">
                    <div class="comment-header"><span class="comment-author">${esc(c.author_name)}${c.is_agent?' (Support)':''}</span><span class="comment-date">${new Date(c.created_at).toLocaleString()}</span></div>
                    <div class="comment-content">${esc(c.content)}</div>
                </div>
            `).join('') || '<p style="color:#9ca3af">No replies yet</p>'}</div>
            <hr style="margin:20px 0;border-color:rgba(255,255,255,0.1)">
            <div class="form-group"><label>Add Reply</label><textarea id="replyContent" placeholder="Type your reply..."></textarea></div>
            <button class="btn" onclick="addReply('${t.ticket_id}')"><i class="fas fa-reply"></i> Send</button>
        `;
        document.getElementById('ticketModal').classList.add('active');
    } catch(err) { alert('Error loading ticket'); }
}

async function addReply(id) {
    const content = document.getElementById('replyContent').value;
    if (!content.trim()) return;
    try {
        await fetch(API + '/tickets/' + id + '/comments', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ name: document.getElementById('name').value || 'Customer', email: localStorage.getItem('oversee_email'), content }) });
        viewTicket(id);
    } catch(err) { alert('Error'); }
}

function closeModal() { document.getElementById('ticketModal').classList.remove('active'); }
function esc(s) { if(!s)return''; const d=document.createElement('div'); d.textContent=s; return d.innerHTML; }
if (localStorage.getItem('oversee_email')) loadTickets();
</script>
</body>
</html>