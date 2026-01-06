<?php
/**
 * KB Article Page
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

$portal_title = oversee_get_portal_title();
$category_name = $article['category']['name'] ?? ucwords(str_replace('-', ' ', $category_slug));
$related = $kb->get_related_articles($category_slug, $article_slug, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($article['title']); ?> - <?php echo esc_html($portal_title); ?></title>
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
                <a href="<?php echo esc_url(oversee_kb_url($category_slug)); ?>"><?php echo esc_html($category_name); ?></a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current"><?php echo esc_html($article['title']); ?></span>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="article-layout">
                <article class="article-card">
                    <h1><?php echo esc_html($article['title']); ?></h1>
                    
                    <div class="article-meta">
                        <span><i class="fa-solid fa-folder"></i> <?php echo esc_html($category_name); ?></span>
                    </div>
                    
                    <div class="article-body">
                        <?php echo wp_kses_post($article['content']); ?>
                    </div>
                    
                    <div class="feedback-section">
                        <p>Was this article helpful?</p>
                        <div class="feedback-buttons">
                            <button class="feedback-btn" id="feedbackYes" onclick="submitFeedback(true)">
                                <i class="fa-solid fa-thumbs-up"></i> Yes
                            </button>
                            <button class="feedback-btn" id="feedbackNo" onclick="submitFeedback(false)">
                                <i class="fa-solid fa-thumbs-down"></i> No
                            </button>
                        </div>
                    </div>
                </article>
                
                <aside class="sidebar">
                    <?php if (!empty($related)): ?>
                    <div class="sidebar-card">
                        <h3><i class="fa-solid fa-file-lines"></i> Related Articles</h3>
                        <div class="related-list">
                            <?php foreach ($related as $rel): ?>
                            <a href="<?php echo esc_url(oversee_kb_url($category_slug . '/' . $rel['slug'])); ?>">
                                <?php echo esc_html($rel['title']); ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="sidebar-card">
                        <h3><i class="fa-solid fa-circle-question"></i> Need More Help?</h3>
                        <p>Can't find what you're looking for? Our support team is here to help.</p>
                        <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="sidebar-btn sidebar-btn-primary">
                            Submit a Ticket
                        </a>
                    </div>
                    
                    <div class="sidebar-card">
                        <h3><i class="fa-solid fa-video"></i> Talk to Us</h3>
                        <p>Schedule a live support call with our team.</p>
                        <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>" class="sidebar-btn sidebar-btn-outline">
                            Book a Call
                        </a>
                    </div>
                </aside>
            </div>
        </main>

        <!-- Footer -->
        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>

    <script>
        function submitFeedback(helpful) {
            const yesBtn = document.getElementById('feedbackYes');
            const noBtn = document.getElementById('feedbackNo');
            
            if (helpful) {
                yesBtn.innerHTML = '<i class="fa-solid fa-check"></i> Thanks!';
                yesBtn.classList.add('active');
            } else {
                noBtn.innerHTML = '<i class="fa-solid fa-check"></i> Feedback sent';
                noBtn.classList.add('active');
            }
            
            yesBtn.disabled = true;
            noBtn.disabled = true;
        }
    </script>
</body>
</html>
