<?php
/**
 * Admin Articles Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in() || !current_user_can('oversee_manage_kb')) {
    wp_safe_redirect(oversee_admin_url());
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KB Articles - <?php echo esc_html(oversee_get_portal_title()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=2.0.0'); ?>">
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-topbar">
                <button class="hamburger-btn" onclick="toggleMobileSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <h1 class="page-title">Knowledge Base Articles</h1>
                <div class="topbar-actions">
                    <button class="btn btn-secondary" onclick="openModal('categoryModal')">
                        <i class="fa-solid fa-folder-plus"></i>
                        <span>New Category</span>
                    </button>
                    <a href="<?php echo esc_url(oversee_admin_url('articles/new')); ?>" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>New Article</span>
                    </a>
                </div>
            </header>
            
            <div class="admin-content">
                <!-- Stats -->
                <div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
                    <div class="stat-card">
                        <div class="stat-value" id="statRemote">-</div>
                        <div class="stat-label">Remote Articles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statCustom">-</div>
                        <div class="stat-label">Custom Articles</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statPublished">-</div>
                        <div class="stat-label">Published</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="statDraft">-</div>
                        <div class="stat-label">Drafts</div>
                    </div>
                </div>
                
                <!-- Categories Section -->
                <div class="card" style="margin-bottom: 24px;">
                    <div class="card-header">
                        <h3 class="card-title">Custom Categories</h3>
                    </div>
                    <div id="categoriesList" class="categories-grid-admin">
                        <div class="loading-spinner">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Articles Section -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Custom Articles</h3>
                        <div class="card-actions">
                            <select id="filterCategory" onchange="loadArticles()">
                                <option value="">All Categories</option>
                            </select>
                            <select id="filterStatus" onchange="loadArticles()">
                                <option value="">All Status</option>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                    </div>
                    <div id="articlesList">
                        <div class="loading-spinner">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                        </div>
                    </div>
                    <div id="articlesPagination" class="ticket-pagination"></div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Category Modal -->
    <div class="modal" id="categoryModal">
        <div class="modal-backdrop" onclick="closeModal('categoryModal')"></div>
        <div class="modal-content modal-sm">
            <div class="modal-header">
                <h3 id="categoryModalTitle">New Category</h3>
                <button class="modal-close" onclick="closeModal('categoryModal')">
                    <i class="fa-solid fa-times"></i>
                </button>
            </div>
            <form id="categoryForm" onsubmit="saveCategory(event)">
                <input type="hidden" id="categoryId">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="categoryName">Name *</label>
                        <input type="text" id="categoryName" required>
                    </div>
                    <div class="form-group">
                        <label for="categoryDescription">Description</label>
                        <textarea id="categoryDescription" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="categoryIcon">Icon Class</label>
                        <input type="text" id="categoryIcon" placeholder="fa-solid fa-folder" value="fa-solid fa-folder">
                        <p class="help-text">Use Font Awesome class names</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script src="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/js/api-client.js'); ?>"></script>
    <script src="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/js/admin.js'); ?>"></script>
    <script>
        // Initialize API with WordPress nonce
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        if (typeof overseeAPI !== 'undefined') {
            overseeAPI.setNonce(WP_NONCE);
        }
        
        let categories = [];
        let currentPage = 1;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            loadArticles();
        });
        
        async function loadCategories() {
            try {
                const data = await overseeAPI.get('/kb/categories?include_remote=false');
                categories = data.categories || [];
                renderCategories();
                populateCategoryFilter();
            } catch (error) {
                document.getElementById('categoriesList').innerHTML = '<p class="error">Failed to load categories</p>';
            }
        }
        
        function renderCategories() {
            const container = document.getElementById('categoriesList');
            
            if (!categories || categories.length === 0) {
                container.innerHTML = '<p class="text-muted">No custom categories yet. Create one to organize your articles.</p>';
                return;
            }
            
            container.innerHTML = `
                ${categories.map(cat => `
                    <div class="category-card-admin">
                        <div class="category-icon-admin">
                            <i class="${escapeHtml(cat.icon || 'fa-solid fa-folder')}"></i>
                        </div>
                        <div class="category-info-admin">
                            <div class="category-name-admin">${escapeHtml(cat.name)}</div>
                            <div class="category-count-admin">${cat.article_count || 0} articles</div>
                        </div>
                        <div class="category-actions-admin">
                            <button class="btn btn-sm btn-icon" onclick="editCategory(${cat.id})" title="Edit">
                                <i class="fa-solid fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-icon" onclick="deleteCategory(${cat.id})" title="Delete">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('')}
            `;
        }
        
        function populateCategoryFilter() {
            const select = document.getElementById('filterCategory');
            select.innerHTML = '<option value="">All Categories</option>';
            categories.forEach(cat => {
                select.innerHTML += `<option value="${escapeHtml(cat.slug)}">${escapeHtml(cat.name)}</option>`;
            });
        }
        
        async function loadArticles(page = 1) {
            currentPage = page;
            const category = document.getElementById('filterCategory').value;
            const status = document.getElementById('filterStatus').value;
            
            const params = new URLSearchParams({ page, per_page: 20 });
            if (category) params.append('category', category);
            if (status) params.append('status', status);
            
            try {
                const data = await overseeAPI.get('/kb/articles?' + params.toString());
                renderArticles(data.articles);
                renderPagination(data);
                updateStats();
            } catch (error) {
                document.getElementById('articlesList').innerHTML = '<p class="error">Failed to load articles</p>';
            }
        }
        
        function renderArticles(articles) {
            const container = document.getElementById('articlesList');
            
            if (!articles || articles.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fa-solid fa-file-lines"></i>
                        <p>No articles found</p>
                        <a href="${escapeHtml('<?php echo esc_url(oversee_admin_url('articles/new')); ?>')}" class="btn btn-primary btn-sm">
                            Create First Article
                        </a>
                    </div>
                `;
                return;
            }
            
            container.innerHTML = `
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${articles.map(article => `
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url(oversee_admin_url('articles/edit/')); ?>${article.id}">
                                        ${escapeHtml(article.title)}
                                    </a>
                                </td>
                                <td>${escapeHtml(article.category_name || article.category_slug)}</td>
                                <td>
                                    <span class="badge ${article.status === 'published' ? 'badge-agent' : 'badge-admin'}">
                                        ${article.status}
                                    </span>
                                </td>
                                <td>${formatDate(article.updated_at)}</td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <a href="<?php echo esc_url(oversee_admin_url('articles/edit/')); ?>${article.id}" 
                                           class="btn btn-sm btn-icon" title="Edit">
                                            <i class="fa-solid fa-edit"></i>
                                        </a>
                                        <button class="btn btn-sm btn-icon" onclick="deleteArticle(${article.id})" title="Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;
        }
        
        function renderPagination(data) {
            const container = document.getElementById('articlesPagination');
            if (data.total_pages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '<div class="pagination">';
            for (let i = 1; i <= data.total_pages; i++) {
                html += `<button class="${i === data.page ? 'active' : ''}" onclick="loadArticles(${i})">${i}</button>`;
            }
            html += '</div>';
            container.innerHTML = html;
        }
        
        async function updateStats() {
            // This would need a stats endpoint, for now just count
            document.getElementById('statCustom').textContent = '-';
            document.getElementById('statPublished').textContent = '-';
            document.getElementById('statDraft').textContent = '-';
            document.getElementById('statRemote').textContent = '549';
        }
        
        function editCategory(id) {
            const cat = categories.find(c => c.id == id);
            if (!cat) return;
            
            document.getElementById('categoryId').value = cat.id;
            document.getElementById('categoryName').value = cat.name;
            document.getElementById('categoryDescription').value = cat.description || '';
            document.getElementById('categoryIcon').value = cat.icon || 'fa-solid fa-folder';
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            
            openModal('categoryModal');
        }
        
        async function saveCategory(event) {
            event.preventDefault();
            
            const id = document.getElementById('categoryId').value;
            const data = {
                name: document.getElementById('categoryName').value,
                description: document.getElementById('categoryDescription').value,
                icon: document.getElementById('categoryIcon').value
            };
            
            try {
                if (id) {
                    await overseeAPI.put(`/kb/categories/${id}`, data);
                } else {
                    await overseeAPI.post('/kb/categories', data);
                }
                
                closeModal('categoryModal');
                loadCategories();
                showToast('Category saved');
                
                // Reset form
                document.getElementById('categoryId').value = '';
                document.getElementById('categoryForm').reset();
                document.getElementById('categoryModalTitle').textContent = 'New Category';
            } catch (error) {
                showToast('Failed to save category', 'error');
            }
        }
        
        async function deleteCategory(id) {
            if (!confirm('Delete this category? Articles in this category will need to be reassigned.')) return;
            
            try {
                await overseeAPI.delete(`/kb/categories/${id}`);
                loadCategories();
                showToast('Category deleted');
            } catch (error) {
                showToast(error.message || 'Failed to delete category', 'error');
            }
        }
        
        async function deleteArticle(id) {
            if (!confirm('Delete this article?')) return;
            
            try {
                await overseeAPI.delete(`/kb/articles/${id}`);
                loadArticles(currentPage);
                showToast('Article deleted');
            } catch (error) {
                showToast('Failed to delete article', 'error');
            }
        }
    </script>
</body>
</html>
