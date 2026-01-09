<?php
/**
 * KB Articles Management - Production Ready
 */

if (!defined('ABSPATH')) exit;
if (!is_user_logged_in() || !current_user_can('oversee_manage_kb')) { 
    wp_safe_redirect(oversee_admin_url()); 
    exit; 
}

$kb = new Oversee_KB();
$total_articles = $kb->get_total_articles();
$total_categories = $kb->get_total_categories();
$branding = Oversee_Branding::get_for_context('admin');
$favicon = Oversee_Branding::get('favicon_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KB Articles - <?php echo esc_html($branding['company_name'] ?: oversee_get_portal_title()); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo esc_url($favicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=' . OVERSEE_VERSION); ?>">
    <?php echo Oversee_Branding::get_css_variables(); ?>
    <style>
        /* Only styles not in admin.css */
        .article-title-cell { display: flex; align-items: center; gap: 12px; }
        .article-icon { width: 36px; height: 36px; border-radius: 8px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0; }
        .article-title { font-weight: 500; color: #1e293b; text-decoration: none; }
        .article-title:hover { color: #f97316; }
        .article-slug { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-pill.published { background: #dcfce7; color: #16a34a; }
        .status-pill.draft { background: #fef3c7; color: #d97706; }
        .status-pill i { font-size: 6px; }
        .action-btns { display: flex; gap: 4px; }
        .action-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; text-decoration: none; }
        .action-btn:hover { background: #f8fafc; color: #1e293b; border-color: #cbd5e1; }
        .action-btn.danger:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .views-count { font-size: 12px; color: #94a3b8; }
        .views-count i { margin-right: 4px; }
        .category-card { display: flex; gap: 12px; cursor: pointer; }
        .category-icon { width: 40px; height: 40px; border-radius: 8px; background: #fff7ed; color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
        .category-info { flex: 1; min-width: 0; }
        .category-name { font-weight: 600; color: #1e293b; margin-bottom: 2px; }
        .category-desc { font-size: 13px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .category-meta { font-size: 12px; color: #94a3b8; margin-top: 8px; }
        .category-actions { display: flex; flex-direction: column; gap: 4px; }
        .category-actions button { width: 28px; height: 28px; border-radius: 4px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; }
        .category-actions button:hover { background: #f8fafc; }
        .category-actions button.danger:hover { background: #fef2f2; color: #dc2626; }
    </style>
</head>
<body class="admin-body">
    <?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
    
    <main class="main">
        <div class="topbar">
            <button class="hamburger-btn" onclick="toggleMobileSidebar()"><i class="fa-solid fa-bars"></i></button>
            <h2>Knowledge Base</h2>
            <div class="topbar-right">
                <button class="btn btn-secondary" onclick="showModal('categoryModal')">
                    <i class="fa-solid fa-folder-plus"></i> New Category
                </button>
                <a href="<?php echo esc_url(oversee_admin_url('articles/new')); ?>" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> New Article
                </a>
            </div>
        </div>
        
        <div class="content">
            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card open">
                    <div>
                        <div class="stat-value" id="statTotal"><?php echo number_format($total_articles); ?></div>
                        <div class="stat-label">Total Articles</div>
                    </div>
                </div>
                <div class="stat-card resolved">
                    <div>
                        <div class="stat-value" id="statPublished">-</div>
                        <div class="stat-label">Published</div>
                    </div>
                </div>
                <div class="stat-card new">
                    <div>
                        <div class="stat-value" id="statDraft">-</div>
                        <div class="stat-label">Drafts</div>
                    </div>
                </div>
                <div class="stat-card high">
                    <div>
                        <div class="stat-value" id="statCategories"><?php echo number_format($total_categories); ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="articles-tabs">
                <button class="articles-tab active" data-tab="articles">
                    <i class="fa-solid fa-file-lines"></i> Articles 
                    <span class="count" id="articlesCount">0</span>
                </button>
                <button class="articles-tab" data-tab="categories">
                    <i class="fa-solid fa-folder"></i> Categories 
                    <span class="count" id="categoriesCount">0</span>
                </button>
            </div>
            
            <!-- Articles Tab -->
            <div id="tab-articles" class="tab-content active">
                <div class="card">
                    <div class="articles-toolbar">
                        <div class="articles-search">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search articles...">
                        </div>
                        <select class="filter-select" id="filterCategory">
                            <option value="">All Categories</option>
                        </select>
                        <select class="filter-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                    <div id="articlesList">
                        <div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i> Loading articles...</div>
                    </div>
                </div>
            </div>
            
            <!-- Categories Tab -->
            <div id="tab-categories" class="tab-content">
                <div class="card">
                    <div class="info-banner">
                        <i class="fa-solid fa-circle-info"></i>
                        <p>Categories help organize your knowledge base. Click on a category to filter articles.</p>
                    </div>
                    <div class="categories-grid" id="categoriesList">
                        <div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Category Modal -->
    <div class="modal" id="categoryModal">
        <div class="modal-backdrop" onclick="closeModal('categoryModal')"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="categoryModalTitle">New Category</h3>
                <button class="modal-close" onclick="closeModal('categoryModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="categoryForm" onsubmit="saveCategory(event)">
                <input type="hidden" id="categoryId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" id="categoryName" class="form-control" required placeholder="e.g., Getting Started">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="categoryDescription" class="form-control" rows="3" placeholder="Brief description of this category"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Icon (Font Awesome class)</label>
                        <input type="text" id="categoryIcon" class="form-control" value="fa-solid fa-folder" placeholder="fa-solid fa-folder">
                        <p class="form-help">
                            <a href="https://fontawesome.com/icons" target="_blank">Browse icons</a> - Use format: fa-solid fa-icon-name
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-backdrop" onclick="closeModal('deleteModal')"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce("wp_rest"); ?>';
        const REST_URL = '<?php echo esc_url(rest_url("oversee/v1")); ?>';
        const EDIT_URL = '<?php echo esc_url(oversee_admin_url("articles/edit/")); ?>';
        const NEW_URL = '<?php echo esc_url(oversee_admin_url("articles/new")); ?>';
        const KB_URL = '<?php echo esc_url(oversee_kb_url()); ?>';
        
        let categories = [];
        let articles = [];
        let searchTimeout;
        let deleteType, deleteId, deleteName;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initTabs();
            initSearch();
            initFilters();
            loadCategories();
            loadArticles();
        });
        
        function initTabs() {
            document.querySelectorAll('.articles-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.dataset.tab;
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    document.querySelectorAll('.articles-tab').forEach(t => t.classList.remove('active'));
                    document.getElementById('tab-' + tabName).classList.add('active');
                    this.classList.add('active');
                });
            });
        }
        
        function initSearch() {
            document.getElementById('searchInput').addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(loadArticles, 300);
            });
        }
        
        function initFilters() {
            document.getElementById('filterCategory').addEventListener('change', loadArticles);
            document.getElementById('filterStatus').addEventListener('change', loadArticles);
        }
        
        // API helper
        async function api(method, endpoint, data = null) {
            const options = {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': WP_NONCE }
            };
            if (data) options.body = JSON.stringify(data);
            const response = await fetch(REST_URL + endpoint, options);
            if (!response.ok) {
                const err = await response.json();
                throw new Error(err.message || 'API Error');
            }
            return response.json();
        }
        
        // Load categories
        async function loadCategories() {
            try {
                const data = await api('GET', '/kb/categories');
                categories = data.categories || data || [];
                renderCategories();
                populateCategoryFilter();
                document.getElementById('categoriesCount').textContent = categories.length;
                document.getElementById('statCategories').textContent = categories.length;
            } catch (e) {
                document.getElementById('categoriesList').innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load categories</p></div>';
            }
        }
        
        function renderCategories() {
            const container = document.getElementById('categoriesList');
            
            if (!categories.length) {
                container.innerHTML = '<div class="add-category-card" onclick="showModal(\'categoryModal\')"><i class="fa-solid fa-plus"></i><span>Create your first category</span></div>';
                return;
            }
            
            let html = categories.map(cat => `
                <div class="category-card" onclick="filterByCategory('${esc(cat.slug)}')">
                    <div class="category-icon"><i class="${esc(cat.icon || 'fa-solid fa-folder')}"></i></div>
                    <div class="category-info">
                        <div class="category-name">${esc(cat.name)}</div>
                        <div class="category-desc">${esc(cat.description || 'No description')}</div>
                        <div class="category-meta">${cat.article_count || 0} articles</div>
                    </div>
                    <div class="category-actions" onclick="event.stopPropagation()">
                        <button onclick="editCategory(${cat.id})" title="Edit"><i class="fa-solid fa-edit"></i></button>
                        <button class="danger" onclick="confirmDelete('category', ${cat.id}, '${esc(cat.name)}')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
            
            html += '<div class="add-category-card" onclick="showModal(\'categoryModal\')"><i class="fa-solid fa-plus"></i><span>Add Category</span></div>';
            container.innerHTML = html;
        }
        
        function populateCategoryFilter() {
            const select = document.getElementById('filterCategory');
            select.innerHTML = '<option value="">All Categories</option>' + 
                categories.map(c => `<option value="${esc(c.slug)}">${esc(c.name)}</option>`).join('');
        }
        
        function filterByCategory(slug) {
            document.getElementById('filterCategory').value = slug;
            document.querySelector('.articles-tab[data-tab="articles"]').click();
            loadArticles();
        }
        
        // Load articles
        async function loadArticles() {
            const container = document.getElementById('articlesList');
            container.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i> Loading articles...</div>';
            
            const cat = document.getElementById('filterCategory').value;
            const status = document.getElementById('filterStatus').value;
            const search = document.getElementById('searchInput').value;
            
            let url = '/kb/articles?per_page=500';
            if (cat) url += '&category=' + encodeURIComponent(cat);
            if (status) url += '&status=' + encodeURIComponent(status);
            if (search) url += '&search=' + encodeURIComponent(search);
            
            try {
                const data = await api('GET', url);
                articles = data.articles || data || [];
                renderArticles(data);
                updateStats(data);
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><h4>Failed to load articles</h4><p>' + esc(e.message) + '</p></div>';
            }
        }
        
        function renderArticles(data) {
            const container = document.getElementById('articlesList');
            const totalAll = data.total_all || articles.length;
            // Show total count in tab badge, not just loaded count
            document.getElementById('articlesCount').textContent = totalAll;
            
            if (!articles.length) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-file-lines"></i>
                        <h4>No articles found</h4>
                        <p>Create your first article or adjust your filters</p>
                        <a href="${NEW_URL}" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Article</a>
                    </div>`;
                return;
            }
            
            let html = '';
            
            // Show note if we're not showing all articles
            if (totalAll > articles.length) {
                html += `<div class="info-banner info-banner-margin"><i class="fa-solid fa-info-circle"></i><p>Showing ${articles.length} of ${totalAll} articles. Use search or filters to find specific articles.</p></div>`;
            }
            
            html += `
                <table class="articles-table">
                    <thead>
                        <tr>
                            <th class="col-title">Title</th>
                            <th class="col-category">Category</th>
                            <th class="col-status">Status</th>
                            <th class="col-views">Views</th>
                            <th class="col-updated">Updated</th>
                            <th class="col-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            articles.forEach(a => {
                const title = a.title || '(Untitled)';
                const slug = a.slug || a.id;
                const catSlug = a.category_slug || 'uncategorized';
                const viewUrl = KB_URL + catSlug + '/' + slug + '/';
                
                html += `
                    <tr>
                        <td>
                            <div class="article-title-cell">
                                <div class="article-icon"><i class="fa-solid fa-file-lines"></i></div>
                                <div>
                                    <a href="${EDIT_URL}${a.id}" class="article-title">${esc(title)}</a>
                                    <div class="article-slug">/${esc(slug)}</div>
                                </div>
                            </div>
                        </td>
                        <td>${esc(a.category_name || a.category_slug || 'Uncategorized')}</td>
                        <td>
                            <span class="status-pill ${a.status}">
                                <i class="fa-solid fa-circle"></i>
                                ${a.status === 'published' ? 'Published' : 'Draft'}
                            </span>
                        </td>
                        <td><span class="views-count"><i class="fa-solid fa-eye"></i> ${a.views || 0}</span></td>
                        <td>${timeAgo(a.updated_at)}</td>
                        <td>
                            <div class="action-btns">
                                <a href="${viewUrl}" target="_blank" class="action-btn" title="View"><i class="fa-solid fa-external-link"></i></a>
                                <a href="${EDIT_URL}${a.id}" class="action-btn" title="Edit"><i class="fa-solid fa-edit"></i></a>
                                <a href="${EDIT_URL}${a.id}?duplicate=1" class="action-btn" title="Duplicate"><i class="fa-solid fa-copy"></i></a>
                                <button class="action-btn danger" onclick="confirmDelete('article', ${a.id}, '${esc(title).replace(/'/g, "\\'")}')" title="Delete"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>`;
            });
            
            html += '</tbody></table>';
            container.innerHTML = html;
        }
        
        function updateStats(data) {
            // Use server-side counts for accuracy
            document.getElementById('statTotal').textContent = data.total_all || 0;
            document.getElementById('statPublished').textContent = data.total_published || 0;
            document.getElementById('statDraft').textContent = data.total_draft || 0;
        }
        
        // Category CRUD
        function editCategory(id) {
            const cat = categories.find(c => c.id === id);
            if (!cat) return;
            
            document.getElementById('categoryId').value = cat.id;
            document.getElementById('categoryName').value = cat.name;
            document.getElementById('categoryDescription').value = cat.description || '';
            document.getElementById('categoryIcon').value = cat.icon || 'fa-solid fa-folder';
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            showModal('categoryModal');
        }
        
        async function saveCategory(e) {
            e.preventDefault();
            
            const id = document.getElementById('categoryId').value;
            const data = {
                name: document.getElementById('categoryName').value,
                description: document.getElementById('categoryDescription').value,
                icon: document.getElementById('categoryIcon').value
            };
            
            try {
                await api(id ? 'PUT' : 'POST', '/kb/categories' + (id ? '/' + id : ''), data);
                closeModal('categoryModal');
                resetCategoryForm();
                loadCategories();
                showToast('Category saved!');
            } catch (e) {
                showToast(e.message || 'Failed to save category', true);
            }
        }
        
        function resetCategoryForm() {
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryIcon').value = 'fa-solid fa-folder';
            document.getElementById('categoryModalTitle').textContent = 'New Category';
        }
        
        // Delete
        function confirmDelete(type, id, name) {
            deleteType = type;
            deleteId = id;
            deleteName = name;
            
            const message = type === 'category' 
                ? `Delete category "<strong>${esc(name)}</strong>"?<br><br><small>Articles in this category will not be deleted but will become uncategorized.</small>`
                : `Delete article "<strong>${esc(name)}</strong>"?<br><br><small>This action cannot be undone.</small>`;
            
            document.getElementById('deleteMessage').innerHTML = message;
            document.getElementById('confirmDeleteBtn').onclick = executeDelete;
            showModal('deleteModal');
        }
        
        async function executeDelete() {
            try {
                await api('DELETE', '/kb/' + (deleteType === 'category' ? 'categories' : 'articles') + '/' + deleteId);
                closeModal('deleteModal');
                
                if (deleteType === 'category') {
                    loadCategories();
                    loadArticles();
                } else {
                    loadArticles();
                }
                
                showToast(deleteType.charAt(0).toUpperCase() + deleteType.slice(1) + ' deleted!');
            } catch (e) {
                showToast('Failed to delete: ' + e.message, true);
            }
        }
        
        // UI Helpers
        function showModal(id) {
            document.getElementById(id).classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            if (id === 'categoryModal') resetCategoryForm();
        }
        
        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            toast.querySelector('i').className = isError ? 'fa-solid fa-exclamation-circle' : 'fa-solid fa-check-circle';
            document.getElementById('toastMessage').textContent = msg;
            toast.classList.toggle('error', isError);
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function esc(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        function timeAgo(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            
            if (seconds < 60) return 'Just now';
            if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
            if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
            if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';
            return date.toLocaleDateString();
        }
        
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) sidebar.classList.toggle('open');
        }
    </script>
</body>
</html>
