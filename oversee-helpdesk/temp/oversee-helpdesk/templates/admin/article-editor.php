<?php
/**
 * Admin Article Editor - Production Ready
 * Handles both synced articles and new articles
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in() || !current_user_can('oversee_manage_kb')) {
    wp_safe_redirect(oversee_admin_url());
    exit;
}

global $oversee_vars;
$article_id = isset($oversee_vars['article_id']) ? (int) $oversee_vars['article_id'] : 0;
$is_edit = $article_id > 0;
$is_duplicate = isset($_GET['duplicate']) && $_GET['duplicate'] === '1';

// Load article if editing
$article = null;
$article_category = '';
$is_synced = false;
$original_slug = '';

if ($is_edit) {
    $post = get_post($article_id);
    if (!$post || $post->post_type !== Oversee_KB_CPT::POST_TYPE) {
        wp_safe_redirect(oversee_admin_url('articles'));
        exit;
    }
    
    // Get category
    $terms = wp_get_post_terms($post->ID, Oversee_KB_CPT::TAXONOMY);
    if (!empty($terms) && !is_wp_error($terms)) {
        $article_category = $terms[0]->slug;
    }
    
    // Check if synced from external source
    $original_slug = get_post_meta($post->ID, '_kb_original_slug', true);
    $is_synced = !empty($original_slug);
    
    $article = [
        'id' => $post->ID,
        'title' => $post->post_title,
        'content' => $post->post_content,
        'excerpt' => $post->post_excerpt,
        'status' => $post->post_status === 'publish' ? 'published' : 'draft',
        'category_slug' => $article_category,
        'slug' => $post->post_name,
        'original_slug' => $original_slug,
        'created_at' => $post->post_date,
        'updated_at' => $post->post_modified,
        'views' => (int) get_post_meta($post->ID, '_kb_views', true),
    ];
    
    // If duplicating, modify for new article
    if ($is_duplicate) {
        $article['title'] = $article['title'] . ' (Copy)';
        $article['slug'] = '';
        $article['status'] = 'draft';
        $article_id = 0;
        $is_edit = false;
        $is_synced = false;
    }
}

// Get categories
$kb = new Oversee_KB();
$categories = $kb->get_categories();

// Branding
$branding = Oversee_Branding::get_for_context('admin');
$favicon = Oversee_Branding::get('favicon_url');

// Page title
$page_title = $is_edit ? 'Edit Article' : ($is_duplicate ? 'Duplicate Article' : 'New Article');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($page_title); ?> - <?php echo esc_html($branding['company_name'] ?: oversee_get_portal_title()); ?></title>
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
        .editor-layout { display: grid; grid-template-columns: 1fr 320px; gap: 24px; }
        @media (max-width: 1024px) { .editor-layout { grid-template-columns: 1fr; } }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; font-family: inherit; transition: border-color 0.15s, box-shadow 0.15s; }
        .form-control:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249,115,22,0.1); }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 6px; font-size: 13px; color: #374151; }
        .form-help { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .slug-preview { font-size: 12px; color: #6b7280; margin-top: 4px; word-break: break-all; }
        .slug-preview code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .synced-badge { display: inline-flex; align-items: center; gap: 6px; background: #dbeafe; color: #1d4ed8; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .synced-info { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
        .synced-info p { margin: 0; font-size: 13px; color: #0369a1; }
        .stats-mini { display: flex; gap: 16px; margin-top: 8px; }
        .stats-mini .stat { font-size: 12px; color: #64748b; }
        .stats-mini .stat strong { color: #374151; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; }
        .card-header { padding: 16px; border-bottom: 1px solid #e2e8f0; }
        .card-header h3 { margin: 0; font-size: 14px; font-weight: 600; color: #1e293b; }
        .card-body { padding: 16px; }
        .title-input { font-size: 24px !important; font-weight: 600 !important; border: none !important; padding: 16px !important; box-shadow: none !important; }
        .title-input:focus { box-shadow: none !important; }
        .wp-editor-wrap { border: 1px solid #e2e8f0; border-radius: 0 0 6px 6px; }
        .actions-group { display: flex; flex-direction: column; gap: 8px; }
        .btn-block { width: 100%; justify-content: center; }
        .btn-danger-outline { color: #dc2626; border-color: #fecaca; }
        .btn-danger-outline:hover { background: #fef2f2; }
        .divider { height: 1px; background: #e2e8f0; margin: 16px 0; }
        .char-count { font-size: 11px; color: #9ca3af; text-align: right; margin-top: 4px; }
        .saving-indicator { display: none; align-items: center; gap: 8px; font-size: 13px; color: #6b7280; }
        .saving-indicator.show { display: flex; }
        .saving-indicator i { animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    </style>
    <?php wp_head(); ?>
</head>
<body class="admin-body">
    <?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
    
    <main class="main">
        <div class="topbar">
            <button class="hamburger-btn" onclick="toggleMobileSidebar()">
                <i class="fa-solid fa-bars"></i>
            </button>
            <a href="<?php echo esc_url(oversee_admin_url('articles')); ?>" class="btn btn-secondary btn-sm mr-12">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
            <h2><?php echo esc_html($page_title); ?></h2>
            <?php if ($is_synced): ?>
                <span class="synced-badge"><i class="fa-solid fa-cloud-arrow-down"></i> Synced Article</span>
            <?php endif; ?>
            <div class="topbar-right">
                <span class="saving-indicator" id="savingIndicator">
                    <i class="fa-solid fa-spinner"></i> Saving...
                </span>
                <button type="button" class="btn btn-secondary" onclick="saveArticle('draft')" id="btnSaveDraft">
                    <i class="fa-solid fa-save"></i> Save Draft
                </button>
                <button type="button" class="btn btn-primary" onclick="saveArticle('published')" id="btnPublish">
                    <i class="fa-solid fa-check"></i> <?php echo $is_edit ? 'Update' : 'Publish'; ?>
                </button>
            </div>
        </div>
        
        <div class="content-area editor-content-area">
            <?php if ($is_synced): ?>
            <div class="synced-info">
                <p><i class="fa-solid fa-info-circle"></i> This article was imported from your static knowledge base. Changes made here will be preserved during future syncs.</p>
            </div>
            <?php endif; ?>
            
            <div class="editor-layout">
                <!-- Main Content Area -->
                <div>
                    <!-- Title -->
                    <div class="card">
                        <div class="card-body p-0">
                            <input type="text" id="articleTitle" 
                                   class="form-control title-input" 
                                   placeholder="Enter article title..." 
                                   value="<?php echo esc_attr($article['title'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <!-- Slug -->
                    <div class="card mt-16">
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <label for="articleSlug">URL Slug</label>
                                <input type="text" id="articleSlug" class="form-control" 
                                       placeholder="article-url-slug"
                                       value="<?php echo esc_attr($article['slug'] ?? ''); ?>">
                                <div class="slug-preview">
                                    Preview: <code id="slugPreview"><?php echo esc_url(oversee_kb_url()); ?><span id="catSlugPart"><?php echo esc_html($article_category ?: 'category'); ?></span>/<span id="artSlugPart"><?php echo esc_html($article['slug'] ?? 'article-slug'); ?></span>/</code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content Editor -->
                    <div class="card mt-16">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-pen-to-square"></i> Content</h3>
                        </div>
                        <div class="card-body p-0">
                            <?php
                            wp_editor(
                                $article['content'] ?? '',
                                'articleContent',
                                [
                                    'textarea_name' => 'content',
                                    'textarea_rows' => 20,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                    'tinymce' => [
                                        'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,blockquote,|,link,unlink,|,image,media,|,alignleft,aligncenter,alignright,|,undo,redo',
                                        'toolbar2' => 'forecolor,backcolor,|,hr,|,removeformat,|,outdent,indent,|,fullscreen,|,wp_help',
                                    ]
                                ]
                            );
                            ?>
                        </div>
                    </div>
                    
                    <!-- Excerpt -->
                    <div class="card mt-16">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-align-left"></i> Excerpt</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group mb-0">
                                <textarea id="articleExcerpt" class="form-control" rows="3" 
                                          placeholder="Brief summary for search results and previews. Leave empty to auto-generate from content."
                                          maxlength="300"><?php echo esc_textarea($article['excerpt'] ?? ''); ?></textarea>
                                <div class="char-count"><span id="excerptCount"><?php echo strlen($article['excerpt'] ?? ''); ?></span>/300</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div>
                    <!-- Publish Box -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-cog"></i> Publish</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label for="articleCategory">Category *</label>
                                <select id="articleCategory" class="form-control" required>
                                    <option value="">Select category...</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat['slug']); ?>" 
                                                <?php selected($article_category, $cat['slug']); ?>>
                                            <?php echo esc_html($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($categories)): ?>
                                    <p class="form-help form-help-warning">
                                        <a href="<?php echo esc_url(oversee_admin_url('articles')); ?>">Create a category first</a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label for="articleStatus">Status</label>
                                <select id="articleStatus" class="form-control">
                                    <option value="draft" <?php selected($article['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                                    <option value="published" <?php selected($article['status'] ?? '', 'published'); ?>>Published</option>
                                </select>
                            </div>
                            
                            <div class="divider"></div>
                            
                            <div class="actions-group">
                                <button type="button" class="btn btn-primary btn-block" onclick="saveArticle()">
                                    <i class="fa-solid fa-save"></i> 
                                    <?php echo $is_edit ? 'Save Changes' : 'Create Article'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($is_edit): ?>
                    <!-- Article Info -->
                    <div class="card mt-16">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-info-circle"></i> Article Info</h3>
                        </div>
                        <div class="card-body">
                            <div class="stats-mini">
                                <div class="stat">
                                    <strong><?php echo number_format($article['views']); ?></strong> views
                                </div>
                            </div>
                            <div class="divider"></div>
                            <p class="article-meta-info">
                                <strong>Created:</strong><br>
                                <?php echo esc_html(date('M j, Y \a\t g:i a', strtotime($article['created_at']))); ?><br><br>
                                <strong>Last Updated:</strong><br>
                                <?php echo esc_html(date('M j, Y \a\t g:i a', strtotime($article['updated_at']))); ?>
                                <?php if ($is_synced && $original_slug): ?>
                                <br><br>
                                <strong>Original Slug:</strong><br>
                                <code class="code-small"><?php echo esc_html($original_slug); ?></code>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="card mt-16">
                        <div class="card-header">
                            <h3><i class="fa-solid fa-bolt"></i> Actions</h3>
                        </div>
                        <div class="card-body">
                            <div class="actions-group">
                                <a href="<?php echo esc_url(oversee_kb_url($article['category_slug'] . '/' . $article['slug'])); ?>" 
                                   target="_blank" class="btn btn-secondary btn-block">
                                    <i class="fa-solid fa-external-link"></i> View Article
                                </a>
                                <a href="<?php echo esc_url(oversee_admin_url('articles/edit/' . $article_id . '?duplicate=1')); ?>" 
                                   class="btn btn-secondary btn-block">
                                    <i class="fa-solid fa-copy"></i> Duplicate
                                </a>
                                <button type="button" class="btn btn-secondary btn-block btn-danger-outline" onclick="deleteArticle()">
                                    <i class="fa-solid fa-trash"></i> Delete Article
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <?php wp_footer(); ?>
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        const ARTICLES_URL = '<?php echo esc_url(oversee_admin_url('articles')); ?>';
        const EDIT_URL = '<?php echo esc_url(oversee_admin_url('articles/edit/')); ?>';
        const KB_URL = '<?php echo esc_url(oversee_kb_url()); ?>';
        let articleId = <?php echo $article_id ?: 'null'; ?>;
        let isSaving = false;
        let hasChanges = false;
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initSlugGeneration();
            initExcerptCounter();
            initChangeTracking();
            initAutoSave();
        });
        
        // Slug generation from title
        function initSlugGeneration() {
            const titleInput = document.getElementById('articleTitle');
            const slugInput = document.getElementById('articleSlug');
            
            titleInput.addEventListener('input', function() {
                // Only auto-generate slug if it's empty or matches previous auto-generation
                if (!articleId && (!slugInput.value || slugInput.dataset.auto === 'true')) {
                    slugInput.value = generateSlug(this.value);
                    slugInput.dataset.auto = 'true';
                    updateSlugPreview();
                }
                hasChanges = true;
            });
            
            slugInput.addEventListener('input', function() {
                this.value = generateSlug(this.value);
                this.dataset.auto = 'false';
                updateSlugPreview();
                hasChanges = true;
            });
            
            document.getElementById('articleCategory').addEventListener('change', function() {
                updateSlugPreview();
                hasChanges = true;
            });
        }
        
        function generateSlug(text) {
            return text.toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 100);
        }
        
        function updateSlugPreview() {
            const category = document.getElementById('articleCategory').value || 'category';
            const slug = document.getElementById('articleSlug').value || 'article-slug';
            document.getElementById('catSlugPart').textContent = category;
            document.getElementById('artSlugPart').textContent = slug;
        }
        
        // Excerpt character counter
        function initExcerptCounter() {
            const excerpt = document.getElementById('articleExcerpt');
            const counter = document.getElementById('excerptCount');
            
            excerpt.addEventListener('input', function() {
                counter.textContent = this.value.length;
                hasChanges = true;
            });
        }
        
        // Track changes for unsaved warning
        function initChangeTracking() {
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('change', () => hasChanges = true);
            });
            
            // TinyMCE change tracking
            if (typeof tinymce !== 'undefined') {
                tinymce.on('AddEditor', function(e) {
                    e.editor.on('change', function() {
                        hasChanges = true;
                    });
                });
            }
            
            window.addEventListener('beforeunload', function(e) {
                if (hasChanges && !isSaving) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        }
        
        // Auto-save draft every 60 seconds
        function initAutoSave() {
            setInterval(function() {
                if (hasChanges && articleId && document.getElementById('articleStatus').value === 'draft') {
                    saveArticle(null, true);
                }
            }, 60000);
        }
        
        // Get editor content
        function getEditorContent() {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('articleContent')) {
                return tinyMCE.get('articleContent').getContent();
            }
            return document.getElementById('articleContent').value;
        }
        
        // Collect form data
        function getFormData() {
            return {
                title: document.getElementById('articleTitle').value.trim(),
                slug: document.getElementById('articleSlug').value.trim(),
                content: getEditorContent(),
                excerpt: document.getElementById('articleExcerpt').value.trim(),
                category_slug: document.getElementById('articleCategory').value,
                status: document.getElementById('articleStatus').value
            };
        }
        
        // API call helper
        async function api(method, endpoint, data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': WP_NONCE
                }
            };
            if (data) options.body = JSON.stringify(data);
            
            const response = await fetch(REST_URL + endpoint, options);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.message || 'API Error');
            }
            return result;
        }
        
        // Save article
        async function saveArticle(status = null, isAutoSave = false) {
            if (isSaving) return;
            
            const data = getFormData();
            
            // Validation
            if (!data.title) {
                showToast('Please enter a title', true);
                document.getElementById('articleTitle').focus();
                return;
            }
            
            if (!data.category_slug) {
                showToast('Please select a category', true);
                document.getElementById('articleCategory').focus();
                return;
            }
            
            // Override status if specified
            if (status) {
                data.status = status;
            }
            
            // Auto-generate slug if empty
            if (!data.slug) {
                data.slug = generateSlug(data.title);
            }
            
            isSaving = true;
            if (!isAutoSave) {
                document.getElementById('savingIndicator').classList.add('show');
                document.getElementById('btnSaveDraft').disabled = true;
                document.getElementById('btnPublish').disabled = true;
            }
            
            try {
                let result;
                if (articleId) {
                    result = await api('PUT', '/kb/articles/' + articleId, data);
                    if (!isAutoSave) {
                        showToast('Article saved successfully!');
                    }
                } else {
                    result = await api('POST', '/kb/articles', data);
                    showToast('Article created successfully!');
                    
                    // Redirect to edit page for the new article
                    if (result.id) {
                        articleId = result.id;
                        hasChanges = false;
                        window.location.href = EDIT_URL + result.id;
                        return;
                    }
                }
                
                hasChanges = false;
                
                // Update slug display if it changed
                if (result.slug) {
                    document.getElementById('articleSlug').value = result.slug;
                    updateSlugPreview();
                }
                
            } catch (error) {
                showToast(error.message || 'Failed to save article', true);
            } finally {
                isSaving = false;
                document.getElementById('savingIndicator').classList.remove('show');
                document.getElementById('btnSaveDraft').disabled = false;
                document.getElementById('btnPublish').disabled = false;
            }
        }
        
        // Delete article
        async function deleteArticle() {
            if (!articleId) return;
            
            if (!confirm('Are you sure you want to delete this article?\n\nThis action cannot be undone.')) {
                return;
            }
            
            try {
                await api('DELETE', '/kb/articles/' + articleId);
                showToast('Article deleted');
                hasChanges = false;
                setTimeout(() => {
                    window.location.href = ARTICLES_URL;
                }, 1000);
            } catch (error) {
                showToast('Failed to delete article', true);
            }
        }
        
        // Toast notification
        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            document.getElementById('toastMessage').textContent = msg;
            icon.className = isError ? 'fa-solid fa-exclamation-circle' : 'fa-solid fa-check-circle';
            toast.classList.toggle('error', isError);
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        // Mobile sidebar toggle
        function toggleMobileSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) sidebar.classList.toggle('open');
        }
    </script>
    <?php wp_footer(); ?>
</body>
</html>
