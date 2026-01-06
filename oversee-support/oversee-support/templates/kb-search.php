<?php
/**
 * KB Search Results Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
$portal_title = oversee_get_portal_title();

$results = [];
if (!empty($query)) {
    $kb = new Oversee_KB();
    $results = $kb->search($query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search: <?php echo esc_html($query); ?> - <?php echo esc_html($portal_title); ?></title>
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
                <span class="current">Search Results</span>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="search-results-section">
                <!-- Search Box -->
                <form action="" method="GET" class="search-box" style="margin-bottom: 32px;">
                    <input type="text" name="q" value="<?php echo esc_attr($query); ?>" placeholder="Search articles...">
                    <button type="submit">Search</button>
                </form>
                
                <?php if (empty($query)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-search"></i>
                    <h3>Search the Knowledge Base</h3>
                    <p>Enter a search term above to find articles.</p>
                </div>
                <?php elseif (empty($results)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-search"></i>
                    <h3>No results found</h3>
                    <p>We couldn't find any articles matching "<?php echo esc_html($query); ?>"</p>
                    <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary" style="margin-top: 16px;">
                        <i class="fa-solid fa-plus"></i> Submit a Ticket
                    </a>
                </div>
                <?php else: ?>
                <p class="search-count"><?php echo count($results); ?> result<?php echo count($results) !== 1 ? 's' : ''; ?> for "<?php echo esc_html($query); ?>"</p>
                
                <div class="search-results">
                    <?php foreach ($results as $result): ?>
                    <a href="<?php echo esc_url(oversee_kb_url($result['category_slug'] . '/' . $result['slug'])); ?>" class="result-item">
                        <div class="result-icon">
                            <i class="fa-solid fa-file-lines"></i>
                        </div>
                        <div class="result-content">
                            <h3><?php echo esc_html($result['title']); ?></h3>
                            <?php if (!empty($result['excerpt'])): ?>
                            <p><?php echo esc_html($result['excerpt']); ?></p>
                            <?php endif; ?>
                            <span class="result-category">
                                <i class="fa-solid fa-folder"></i>
                                <?php echo esc_html($result['category_name'] ?? ucwords(str_replace('-', ' ', $result['category_slug']))); ?>
                            </span>
                        </div>
                        <i class="fa-solid fa-chevron-right result-arrow"></i>
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
