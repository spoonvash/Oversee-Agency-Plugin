<?php
/**
 * KB Category Page - Content Only
 * All CSS is in /assets/css/public.css
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
$category_name = $category['name'];
$category_icon = $category['icon'] ?? 'fa-folder';
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Knowledge Base</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current"><?php echo esc_html($category_name); ?></span>
    </div>
</nav>

<section class="kb-category-section">
    <div class="container kb-category-container">
        <div class="category-header">
            <div class="category-icon"><i class="fa-solid <?php echo esc_attr($category_icon); ?>"></i></div>
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
            <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back to Knowledge Base</a>
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
</section>
