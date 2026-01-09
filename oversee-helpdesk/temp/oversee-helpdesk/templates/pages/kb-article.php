<?php
/**
 * KB Article Page - Content Only
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}

global $oversee_vars;
$category_slug = $oversee_vars['category'] ?? '';
$article_slug = $oversee_vars['article'] ?? '';

$kb = new Oversee_KB();
$article = $kb->get_article($category_slug, $article_slug);

if (!$article) {
    wp_safe_redirect(oversee_kb_url($category_slug));
    exit;
}

$category_name = $article['category']['name'] ?? ucwords(str_replace('-', ' ', $category_slug));
$related = $kb->get_related_articles($category_slug, $article_slug, 5);
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>">Knowledge Base</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="<?php echo esc_url(oversee_kb_url($category_slug)); ?>"><?php echo esc_html($category_name); ?></a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current"><?php echo esc_html($article['title']); ?></span>
    </div>
</nav>

<section class="article-page">
    <div class="container">
        <div class="article-layout">
            <article class="article-content">
                <header class="article-header">
                    <h1><?php echo esc_html($article['title']); ?></h1>
                    <div class="article-meta">
                        <?php if (!empty($article['updated'])): ?>
                            <span><i class="fa-solid fa-clock"></i> Updated <?php echo esc_html(human_time_diff(strtotime($article['updated']))); ?> ago</span>
                        <?php endif; ?>
                        <?php if (!empty($article['author'])): ?>
                            <span><i class="fa-solid fa-user"></i> <?php echo esc_html($article['author']); ?></span>
                        <?php endif; ?>
                    </div>
                </header>
                
                <div class="article-body prose"><?php echo wp_kses_post($article['content'] ?? ''); ?></div>
                
                <footer class="article-footer">
                    <div class="feedback-section">
                        <p>Was this article helpful?</p>
                        <div class="feedback-buttons">
                            <button type="button" id="feedbackYes" class="btn btn-secondary btn-sm"><i class="fa-solid fa-thumbs-up"></i> Yes</button>
                            <button type="button" id="feedbackNo" class="btn btn-secondary btn-sm"><i class="fa-solid fa-thumbs-down"></i> No</button>
                        </div>
                    </div>
                    <div class="contact-cta">
                        <p>Still need help?</p>
                        <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-envelope"></i> Contact Support</a>
                    </div>
                </footer>
            </article>
            
            <?php if (!empty($related)): ?>
            <aside class="article-sidebar">
                <div class="sidebar-section">
                    <h3>Related Articles</h3>
                    <ul class="related-articles">
                        <?php foreach ($related as $rel): ?>
                        <li><a href="<?php echo esc_url($rel['url'] ?? oversee_kb_url($category_slug . '/' . ($rel['slug'] ?? ''))); ?>"><i class="fa-solid fa-file-lines"></i> <?php echo esc_html($rel['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>
            <?php endif; ?>
        </div>
    </div>
</section>

<script>
document.getElementById('feedbackYes')?.addEventListener('click', function() {
    this.innerHTML = '<i class="fa-solid fa-check"></i> Thanks!';
    this.disabled = true;
    document.getElementById('feedbackNo').disabled = true;
});
document.getElementById('feedbackNo')?.addEventListener('click', function() {
    this.innerHTML = '<i class="fa-solid fa-check"></i> Feedback sent';
    this.disabled = true;
    document.getElementById('feedbackYes').disabled = true;
});
</script>
