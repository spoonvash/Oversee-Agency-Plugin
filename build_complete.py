import json, os, re

print("Loading articles...")
with open('articles_full.json') as f:
    articles = json.load(f)

print(f"Found {len(articles)} articles")

# Base directory
base = 'oversee-support-tickets'
kb = f'{base}/knowledge-base'

# Create directories
os.makedirs(f'{base}/templates', exist_ok=True)
os.makedirs(kb, exist_ok=True)

# Fix image URL
def fix_url(url):
    if not url: return ''
    if url.startswith('../../../../'):
        return 'https://' + url[12:]
    if url.startswith('//'):
        return 'https:' + url
    if not url.startswith('http'):
        return 'https://s3.amazonaws.com/cdn.freshdesk.com/' + url
    return url

# Group articles by folder
folders = {}
for a in articles:
    folder = a.get('folder', 'General') or 'General'
    slug = re.sub(r'[^a-z0-9]+', '-', folder.lower()).strip('-') or 'general'
    if slug not in folders:
        folders[slug] = {'name': folder, 'articles': []}
    folders[slug]['articles'].append(a)

print(f"Found {len(folders)} categories")

# Create article HTML files
article_count = 0
for folder_slug, data in folders.items():
    folder_path = f"{kb}/{folder_slug}"
    os.makedirs(folder_path, exist_ok=True)
    
    for a in data['articles']:
        title = a.get('title', 'Untitled')
        slug = a.get('slug', f'article-{article_count}')
        content = a.get('content', '')
        images = a.get('images', [])
        videos = a.get('videos', [])
        
        # Fix image URLs in content
        content = re.sub(r'src="\.\.\/\.\.\/\.\.\/\.\.\/([^"]+)"', r'src="https://\1"', content)
        content = re.sub(r"src='\.\.\/\.\.\/\.\.\/\.\.\/([^']+)'", r"src='https://\1'", content)
        
        # Build images section
        img_html = ''
        if images:
            img_html = '<div class="article-images"><h3>Screenshots</h3>'
            for img in images:
                img_url = fix_url(img)
                img_html += f'<img src="{img_url}" alt="" loading="lazy" onclick="window.open(this.src)">\n'
            img_html += '</div>'
        
        # Build videos section
        vid_html = ''
        if videos:
            vid_html = '<div class="article-videos"><h3>Videos</h3>'
            for vid in videos:
                if 'youtube.com/embed' in vid or 'youtu.be' in vid:
                    vid_id = vid.split('/')[-1].split('?')[0]
                    vid_html += f'<iframe src="https://www.youtube.com/embed/{vid_id}" allowfullscreen></iframe>\n'
                elif 'loom.com' in vid:
                    vid_html += f'<iframe src="{vid}" allowfullscreen></iframe>\n'
                elif 'vimeo.com' in vid:
                    vid_html += f'<iframe src="{vid}" allowfullscreen></iframe>\n'
            vid_html += '</div>'
        
        html = f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{title} - OverseeCRM Help</title>
<style>
* {{ margin: 0; padding: 0; box-sizing: border-box; }}
body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #0f0f0f; color: #e0e0e0; line-height: 1.7; }}
.container {{ max-width: 900px; margin: 0 auto; padding: 20px; }}
.breadcrumb {{ margin-bottom: 20px; font-size: 14px; }}
.breadcrumb a {{ color: #FF6B35; text-decoration: none; }}
.breadcrumb a:hover {{ text-decoration: underline; }}
h1 {{ font-size: 2rem; margin-bottom: 20px; color: #fff; }}
.article-content {{ background: #1a1a1a; padding: 30px; border-radius: 12px; margin-bottom: 30px; }}
.article-content img {{ max-width: 100%; height: auto; border-radius: 8px; margin: 15px 0; cursor: pointer; }}
.article-content h2 {{ color: #FF6B35; margin: 25px 0 15px 0; font-size: 1.4rem; }}
.article-content h3 {{ color: #fff; margin: 20px 0 10px 0; font-size: 1.2rem; }}
.article-content p {{ margin-bottom: 15px; }}
.article-content ul, .article-content ol {{ margin: 15px 0 15px 25px; }}
.article-content li {{ margin-bottom: 8px; }}
.article-content a {{ color: #FF6B35; }}
.article-content pre, .article-content code {{ background: #111; padding: 2px 6px; border-radius: 4px; font-family: monospace; }}
.article-content pre {{ padding: 15px; overflow-x: auto; }}
.article-content blockquote {{ border-left: 4px solid #FF6B35; padding-left: 20px; margin: 20px 0; color: #aaa; }}
.article-content table {{ width: 100%; border-collapse: collapse; margin: 20px 0; }}
.article-content th, .article-content td {{ padding: 12px; border: 1px solid #333; text-align: left; }}
.article-content th {{ background: #222; }}
.article-images img {{ max-width: 100%; margin: 10px 0; border-radius: 8px; cursor: pointer; }}
.article-videos iframe {{ width: 100%; height: 400px; border: none; border-radius: 8px; margin: 10px 0; }}
.back-link {{ display: inline-block; margin-top: 30px; padding: 12px 24px; background: #FF6B35; color: #fff; text-decoration: none; border-radius: 8px; }}
.back-link:hover {{ background: #e55a2b; }}
</style>
</head>
<body>
<div class="container">
<nav class="breadcrumb">
<a href="../index.html">Knowledge Base</a> &rsaquo; 
<a href="index.html">{data['name']}</a> &rsaquo; 
<span>{title}</span>
</nav>
<h1>{title}</h1>
<div class="article-content">
{content if content else '<p>No content available.</p>'}
</div>
{img_html}
{vid_html}
<a href="index.html" class="back-link">&larr; Back to {data['name']}</a>
</div>
</body>
</html>'''
        
        with open(f"{folder_path}/{slug}.html", 'w', encoding='utf-8') as f:
            f.write(html)
        article_count += 1
    
    # Create folder index
    folder_index = f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{data['name']} - OverseeCRM Help</title>
<style>
* {{ margin: 0; padding: 0; box-sizing: border-box; }}
body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #e0e0e0; }}
.container {{ max-width: 900px; margin: 0 auto; padding: 20px; }}
h1 {{ font-size: 2rem; margin: 20px 0; color: #fff; }}
.back {{ color: #FF6B35; text-decoration: none; }}
.back:hover {{ text-decoration: underline; }}
.count {{ color: #888; margin-bottom: 30px; }}
.article {{ display: block; padding: 20px; background: #1a1a1a; margin-bottom: 10px; border-radius: 8px; text-decoration: none; color: #e0e0e0; border-left: 4px solid transparent; }}
.article:hover {{ background: #222; border-left-color: #FF6B35; }}
.article h3 {{ color: #fff; margin-bottom: 5px; font-size: 1rem; }}
</style>
</head>
<body>
<div class="container">
<a href="../index.html" class="back">&larr; All Categories</a>
<h1>{data['name']}</h1>
<p class="count">{len(data['articles'])} articles</p>
'''
    for a in sorted(data['articles'], key=lambda x: x.get('title', '')):
        folder_index += f'''<a href="{a.get('slug')}.html" class="article"><h3>{a.get('title', 'Untitled')}</h3></a>\n'''
    
    folder_index += '</div></body></html>'
    
    with open(f"{folder_path}/index.html", 'w', encoding='utf-8') as f:
        f.write(folder_index)

print(f"Created {article_count} article files")

# Create main KB index
main_index = f'''<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Knowledge Base - OverseeCRM</title>
<style>
* {{ margin: 0; padding: 0; box-sizing: border-box; }}
body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f0f0f; color: #e0e0e0; }}
.container {{ max-width: 1000px; margin: 0 auto; padding: 20px; }}
.header {{ text-align: center; padding: 50px 20px; }}
.header h1 {{ font-size: 2.5rem; color: #fff; margin-bottom: 10px; }}
.header p {{ color: #888; }}
.search {{ max-width: 600px; margin: 30px auto; }}
.search input {{ width: 100%; padding: 15px 20px; font-size: 16px; background: #1a1a1a; border: 2px solid #333; border-radius: 10px; color: #fff; }}
.search input:focus {{ outline: none; border-color: #FF6B35; }}
.categories {{ display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; margin-top: 40px; }}
.category {{ display: block; padding: 25px; background: #1a1a1a; border-radius: 10px; text-decoration: none; color: #e0e0e0; border: 1px solid #333; }}
.category:hover {{ border-color: #FF6B35; background: #222; }}
.category h3 {{ color: #FF6B35; margin-bottom: 8px; }}
.category span {{ color: #888; font-size: 14px; }}
.back-support {{ display: inline-block; margin-top: 40px; color: #FF6B35; text-decoration: none; }}
</style>
</head>
<body>
<div class="container">
<div class="header">
<h1>📚 Knowledge Base</h1>
<p>{len(articles)} articles in {len(folders)} categories</p>
</div>
<div class="search">
<input type="text" id="search" placeholder="Search articles..." oninput="searchArticles(this.value)">
</div>
<div id="results"></div>
<div class="categories" id="categories">
'''

for slug, data in sorted(folders.items(), key=lambda x: x[1]['name']):
    main_index += f'''<a href="{slug}/index.html" class="category"><h3>{data['name']}</h3><span>{len(data['articles'])} articles</span></a>\n'''

main_index += '''</div>
<a href="../templates/customer-portal.php" class="back-support">&larr; Back to Support</a>
</div>
<script>
const articles = ''' + json.dumps([{'title': a.get('title',''), 'slug': a.get('slug',''), 'folder': re.sub(r'[^a-z0-9]+', '-', (a.get('folder','General') or 'General').lower()).strip('-')} for a in articles]) + ''';

function searchArticles(q) {
    const results = document.getElementById('results');
    const cats = document.getElementById('categories');
    if (q.length < 2) { results.innerHTML = ''; cats.style.display = 'grid'; return; }
    
    const matches = articles.filter(a => a.title.toLowerCase().includes(q.toLowerCase())).slice(0, 20);
    cats.style.display = 'none';
    results.innerHTML = '<div class="categories">' + matches.map(a => 
        '<a href="' + a.folder + '/' + a.slug + '.html" class="category"><h3>' + a.title + '</h3></a>'
    ).join('') + '</div>';
}
</script>
</body>
</html>'''

with open(f"{kb}/index.html", 'w', encoding='utf-8') as f:
    f.write(main_index)

print("Created main index")

# Create customer portal
portal = '''<?php if (!defined('ABSPATH')) define('ABSPATH', true); ?>
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
</html>'''

with open(f'{base}/templates/customer-portal.php', 'w') as f:
    f.write(portal)

print("Created customer portal")

# Create admin dashboard
admin = '''<?php if (!defined('ABSPATH')) define('ABSPATH', true); ?>
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
</html>'''

with open(f'{base}/templates/admin-dashboard.php', 'w') as f:
    f.write(admin)

print("Created admin dashboard")

# Create main plugin PHP file
plugin = '''<?php
/**
 * Plugin Name: OverseeCRM Support Tickets
 * Description: Support ticket system with 553-article knowledge base
 * Version: 2.0.0
 * Author: OverseeCRM
 */

if (!defined('ABSPATH')) exit;

class OverseeCRM_Support {
    
    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'routes']);
        add_action('rest_api_init', [$this, 'api']);
        add_action('admin_menu', [$this, 'menu']);
    }
    
    public function activate() {
        global $wpdb;
        $c = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta("CREATE TABLE {$wpdb->prefix}oversee_tickets (
            id bigint AUTO_INCREMENT PRIMARY KEY,
            ticket_id varchar(20) UNIQUE,
            name varchar(255),
            email varchar(255),
            phone varchar(50),
            subject varchar(500),
            description longtext,
            category varchar(100) DEFAULT 'General',
            priority varchar(20) DEFAULT 'medium',
            status varchar(20) DEFAULT 'open',
            location_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $c;");
        
        dbDelta("CREATE TABLE {$wpdb->prefix}oversee_comments (
            id bigint AUTO_INCREMENT PRIMARY KEY,
            ticket_id bigint,
            author_name varchar(255),
            author_email varchar(255),
            content longtext,
            is_agent tinyint DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP
        ) $c;");
        
        if (!get_option('oversee_api_key')) {
            update_option('oversee_api_key', wp_generate_password(32, false));
        }
        
        flush_rewrite_rules();
    }
    
    public function routes() {
        add_rewrite_rule('^support/?$', 'index.php?oversee_page=portal', 'top');
        add_rewrite_rule('^support/admin/?$', 'index.php?oversee_page=admin', 'top');
        add_filter('query_vars', function($v) { $v[] = 'oversee_page'; return $v; });
        add_action('template_redirect', [$this, 'render']);
    }
    
    public function render() {
        $page = get_query_var('oversee_page');
        if ($page === 'portal') { include __DIR__ . '/templates/customer-portal.php'; exit; }
        if ($page === 'admin') { include __DIR__ . '/templates/admin-dashboard.php'; exit; }
    }
    
    public function api() {
        register_rest_route('oversee/v1', '/tickets', [
            ['methods' => 'GET', 'callback' => [$this, 'get_tickets'], 'permission_callback' => '__return_true'],
            ['methods' => 'POST', 'callback' => [$this, 'create_ticket'], 'permission_callback' => '__return_true']
        ]);
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\\w-]+)', [
            'methods' => 'GET', 'callback' => [$this, 'get_ticket'], 'permission_callback' => '__return_true'
        ]);
        register_rest_route('oversee/v1', '/tickets/(?P<id>[\\w-]+)/comments', [
            'methods' => 'POST', 'callback' => [$this, 'add_comment'], 'permission_callback' => '__return_true'
        ]);
        register_rest_route('oversee/v1', '/admin/tickets', [
            'methods' => 'GET', 'callback' => [$this, 'admin_tickets'], 'permission_callback' => [$this, 'check_key']
        ]);
        register_rest_route('oversee/v1', '/admin/tickets/(?P<id>\\d+)', [
            'methods' => 'PUT', 'callback' => [$this, 'update_ticket'], 'permission_callback' => [$this, 'check_key']
        ]);
        register_rest_route('oversee/v1', '/admin/stats', [
            'methods' => 'GET', 'callback' => [$this, 'stats'], 'permission_callback' => [$this, 'check_key']
        ]);
    }
    
    public function check_key($r) { return $r->get_header('X-API-Key') === get_option('oversee_api_key'); }
    
    public function get_tickets($r) {
        global $wpdb;
        $email = sanitize_email($r->get_param('email'));
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE email=%s ORDER BY created_at DESC", $email));
    }
    
    public function create_ticket($r) {
        global $wpdb;
        $d = $r->get_json_params();
        $tid = 'TKT-' . str_pad(mt_rand(1,99999), 5, '0', STR_PAD_LEFT);
        $wpdb->insert("{$wpdb->prefix}oversee_tickets", [
            'ticket_id' => $tid,
            'name' => sanitize_text_field($d['name']),
            'email' => sanitize_email($d['email']),
            'phone' => sanitize_text_field($d['phone'] ?? ''),
            'subject' => sanitize_text_field($d['subject']),
            'description' => wp_kses_post($d['description']),
            'category' => sanitize_text_field($d['category'] ?? 'General')
        ]);
        return ['success' => true, 'ticket_id' => $tid];
    }
    
    public function get_ticket($r) {
        global $wpdb;
        $id = sanitize_text_field($r['id']);
        $t = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id=%s OR id=%s", $id, $id));
        if ($t) $t->comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_comments WHERE ticket_id=%d ORDER BY created_at", $t->id));
        return $t;
    }
    
    public function add_comment($r) {
        global $wpdb;
        $id = sanitize_text_field($r['id']);
        $d = $r->get_json_params();
        $t = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}oversee_tickets WHERE ticket_id=%s OR id=%s", $id, $id));
        if ($t) $wpdb->insert("{$wpdb->prefix}oversee_comments", [
            'ticket_id' => $t->id,
            'author_name' => sanitize_text_field($d['name']),
            'author_email' => sanitize_email($d['email'] ?? ''),
            'content' => wp_kses_post($d['content']),
            'is_agent' => intval($d['is_agent'] ?? 0)
        ]);
        return ['success' => true];
    }
    
    public function admin_tickets($r) {
        global $wpdb;
        $status = sanitize_text_field($r->get_param('status'));
        $where = $status ? $wpdb->prepare(" WHERE status=%s", $status) : "";
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}oversee_tickets $where ORDER BY created_at DESC LIMIT 100");
    }
    
    public function update_ticket($r) {
        global $wpdb;
        $d = $r->get_json_params();
        $wpdb->update("{$wpdb->prefix}oversee_tickets", ['status' => sanitize_text_field($d['status'])], ['id' => intval($r['id'])]);
        return ['success' => true];
    }
    
    public function stats() {
        global $wpdb;
        $p = $wpdb->prefix;
        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets"),
            'open' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='open'"),
            'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='in_progress'"),
            'resolved' => $wpdb->get_var("SELECT COUNT(*) FROM {$p}oversee_tickets WHERE status='resolved'"),
            'kb_articles' => 553
        ];
    }
    
    public function menu() {
        add_menu_page('Support', 'Support Tickets', 'manage_options', 'oversee-support', [$this, 'admin_page'], 'dashicons-tickets-alt', 30);
    }
    
    public function admin_page() {
        $key = get_option('oversee_api_key');
        echo '<div class="wrap"><h1>OverseeCRM Support</h1>';
        echo '<div class="card"><h2>Links</h2>';
        echo '<p><a href="' . home_url('/support/') . '" target="_blank">Customer Portal</a></p>';
        echo '<p><a href="' . home_url('/support/admin/') . '" target="_blank">Admin Dashboard</a></p>';
        echo '<p><a href="' . plugin_dir_url(__FILE__) . 'knowledge-base/index.html" target="_blank">Knowledge Base (553 articles)</a></p></div>';
        echo '<div class="card"><h2>API Key</h2><code style="background:#f0f0f0;padding:10px;display:block">' . esc_html($key) . '</code></div></div>';
    }
}

new OverseeCRM_Support();
'''

with open(f'{base}/oversee-support-tickets.php', 'w') as f:
    f.write(plugin)

print("Created main plugin file")
print("")
print("=" * 50)
print("DONE!")
print("=" * 50)
print(f"Created: {article_count} articles in {len(folders)} categories")
print(f"Location: {base}/")
print("")
print("Files created:")
print(f"  - {base}/oversee-support-tickets.php (main plugin)")
print(f"  - {base}/templates/customer-portal.php")
print(f"  - {base}/templates/admin-dashboard.php")
print(f"  - {base}/knowledge-base/ ({len(folders)} folders, {article_count} HTML files)")
