<?php
/**
 * Admin Article Editor
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

// Load article if editing
$article = null;
if ($is_edit) {
    global $wpdb;
    $article = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}oversee_kb_articles WHERE id = %d",
        $article_id
    ), ARRAY_A);
    
    if (!$article) {
        wp_safe_redirect(oversee_admin_url('articles'));
        exit;
    }
}

// Get categories
$kb = new Oversee_KB();
$categories = $kb->get_custom_categories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Article' : 'New Article'; ?> - <?php echo esc_html(oversee_get_portal_title()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=2.0.0'); ?>">
    <?php wp_head(); ?>
</head>
<body class="admin-body">
    <div class="admin-layout">
        <?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
        
        <main class="admin-main">
            <header class="admin-topbar">
                <button class="hamburger-btn" onclick="toggleMobileSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="<?php echo esc_url(oversee_admin_url('articles')); ?>" class="btn btn-icon" title="Back">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <h1 class="page-title"><?php echo $is_edit ? 'Edit Article' : 'New Article'; ?></h1>
                <div class="topbar-actions">
                    <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                        Save Draft
                    </button>
                    <button type="button" class="btn btn-primary" onclick="publishArticle()">
                        <i class="fa-solid fa-check"></i>
                        <?php echo $is_edit ? 'Update' : 'Publish'; ?>
                    </button>
                </div>
            </header>
            
            <div class="admin-content">
                <form id="articleForm" class="editor-layout">
                    <div class="editor-main">
                        <div class="card">
                            <div class="form-group">
                                <input type="text" id="articleTitle" name="title" 
                                       placeholder="Article title..." 
                                       value="<?php echo esc_attr($article['title'] ?? ''); ?>"
                                       style="font-size: 24px; font-weight: 600; border: none; padding: 16px; width: 100%;">
                            </div>
                        </div>
                        
                        <div class="card" style="margin-top: 16px;">
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
                                        'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,|,link,unlink,|,image,media,|,alignleft,aligncenter,alignright,|,undo,redo',
                                        'toolbar2' => '',
                                    ]
                                ]
                            );
                            ?>
                        </div>
                        
                        <div class="card" style="margin-top: 16px;">
                            <div class="form-group">
                                <label for="articleExcerpt">Excerpt (Optional)</label>
                                <textarea id="articleExcerpt" name="excerpt" rows="3" 
                                          placeholder="Brief description for search results..."><?php echo esc_textarea($article['excerpt'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="editor-sidebar">
                        <div class="card">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="articleCategory">Category *</label>
                                    <select id="articleCategory" name="category_slug" required>
                                        <option value="">Select category...</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat['slug']); ?>" 
                                                    <?php selected($article['category_slug'] ?? '', $cat['slug']); ?>>
                                                <?php echo esc_html($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (empty($categories)): ?>
                                        <p class="help-text">
                                            <a href="<?php echo esc_url(oversee_admin_url('articles')); ?>">Create a category first</a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="articleStatus">Status</label>
                                    <select id="articleStatus" name="status">
                                        <option value="draft" <?php selected($article['status'] ?? 'draft', 'draft'); ?>>Draft</option>
                                        <option value="published" <?php selected($article['status'] ?? '', 'published'); ?>>Published</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="articleOrder">Sort Order</label>
                                    <input type="number" id="articleOrder" name="sort_order" 
                                           value="<?php echo esc_attr($article['sort_order'] ?? 0); ?>" min="0">
                                    <p class="help-text">Lower numbers appear first</p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($is_edit): ?>
                        <div class="card">
                            <div class="card-body">
                                <p class="text-muted" style="font-size: 12px; margin: 0;">
                                    <strong>Created:</strong> <?php echo esc_html($article['created_at']); ?><br>
                                    <strong>Updated:</strong> <?php echo esc_html($article['updated_at']); ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-body">
                                <a href="<?php echo esc_url(oversee_kb_url($article['category_slug'] . '/' . $article['slug'])); ?>" 
                                   target="_blank" class="btn btn-secondary btn-sm" style="width: 100%;">
                                    <i class="fa-solid fa-external-link"></i> Preview Article
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <div class="toast-container" id="toastContainer"></div>
    
    <?php wp_footer(); ?>
    <script src="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/js/api-client.js'); ?>"></script>
    <script src="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/js/admin.js'); ?>"></script>
    <script>
        // Initialize API with WordPress nonce
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        if (typeof overseeAPI !== 'undefined') {
            overseeAPI.setNonce(WP_NONCE);
        }
        
        const articleId = <?php echo $article_id ?: 'null'; ?>;
        
        function getEditorContent() {
            // Try TinyMCE first
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('articleContent')) {
                return tinyMCE.get('articleContent').getContent();
            }
            // Fallback to textarea
            return document.getElementById('articleContent').value;
        }
        
        function getFormData() {
            return {
                title: document.getElementById('articleTitle').value,
                content: getEditorContent(),
                excerpt: document.getElementById('articleExcerpt').value,
                category_slug: document.getElementById('articleCategory').value,
                status: document.getElementById('articleStatus').value,
                sort_order: parseInt(document.getElementById('articleOrder').value) || 0
            };
        }
        
        async function saveArticle(status) {
            const data = getFormData();
            
            if (!data.title.trim()) {
                showToast('Please enter a title', 'error');
                return;
            }
            
            if (!data.category_slug) {
                showToast('Please select a category', 'error');
                return;
            }
            
            if (status) {
                data.status = status;
            }
            
            try {
                let result;
                if (articleId) {
                    result = await overseeAPI.put(`/kb/articles/${articleId}`, data);
                } else {
                    result = await overseeAPI.post('/kb/articles', data);
                }
                
                showToast('Article saved');
                
                // Redirect to edit page if new article
                if (!articleId && result.id) {
                    window.location.href = '<?php echo esc_url(oversee_admin_url('articles/edit/')); ?>' + result.id;
                }
            } catch (error) {
                showToast(error.message || 'Failed to save article', 'error');
            }
        }
        
        function saveDraft() {
            saveArticle('draft');
        }
        
        function publishArticle() {
            saveArticle('published');
        }
        
        // Auto-save draft every 60 seconds
        let autoSaveTimer;
        function startAutoSave() {
            autoSaveTimer = setInterval(() => {
                if (articleId && document.getElementById('articleStatus').value === 'draft') {
                    saveArticle();
                }
            }, 60000);
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            startAutoSave();
        });
        
        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            // Could implement change detection here
        });
    </script>
</body>
</html>
