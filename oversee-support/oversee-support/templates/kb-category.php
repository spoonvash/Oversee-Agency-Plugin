<?php
/**
 * KB Category Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $oversee_vars;
$category_slug = $oversee_vars['category'] ?? '';

$kb = new Oversee_KB();
$category = $kb->get_category($category_slug);

if (!$category) {
    wp_safe_redirect(oversee_kb_url());
    exit;
}

$articles = isset($category['articles']) ? $category['articles'] : [];
$portal_title = oversee_get_portal_title();
$category_name = $category['name'];
$category_icon = $category['icon'] ?? 'fa-solid fa-folder';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($category_name); ?> - <?php echo esc_html($portal_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/public.css?v=2.0.0'); ?>">
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <header class="site-header">
            <div class="header-inner">
                <nav class="header-nav">
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="active">
                        <i class="fa-solid fa-book"></i>
                        <span>Knowledge Base</span>
                    </a>
                    <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>">
                        <i class="fa-solid fa-ticket"></i>
                        <span>My Tickets</span>
                    </a>
                    <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>">
                        <i class="fa-solid fa-video"></i>
                        <span>Live Support</span>
                    </a>
                </nav>
                <div class="header-actions">
                    <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary">
                        <i class="fa-solid fa-plus"></i>
                        <span>Submit Ticket</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <div class="breadcrumb-inner">
                <a href="<?php echo esc_url(oversee_kb_url()); ?>">
                    <i class="fa-solid fa-home"></i> Knowledge Base
                </a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current"><?php echo esc_html($category_name); ?></span>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="container" style="padding-top: 40px; padding-bottom: 60px;">
                <div class="category-header">
                    <div class="category-icon">
                        <i class="<?php echo esc_attr($category_icon); ?>"></i>
                    </div>
                    <div class="category-title">
                        <h1><?php echo esc_html($category_name); ?></h1>
                        <p><?php echo count($articles); ?> article<?php echo count($articles) !== 1 ? 's' : ''; ?> in this category</p>
                    </div>
                </div>
                
                <?php if (empty($articles)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No articles yet</h3>
                    <p>This category doesn't have any articles yet.</p>
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="btn btn-primary">
                        <i class="fa-solid fa-arrow-left"></i> Back to Knowledge Base
                    </a>
                </div>
                <?php else: ?>
                <div class="articles-list">
                    <?php foreach ($articles as $article): ?>
                    <a href="<?php echo esc_url(oversee_kb_url($category_slug . '/' . $article['slug'])); ?>" class="article-item">
                        <i class="fa-solid fa-file-lines"></i>
                        <h4><?php echo esc_html($article['title']); ?></h4>
                        <i class="fa-solid fa-chevron-right chevron"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>
</body>
</html>
