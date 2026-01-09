<?php
/**
 * KB Search Results Page - Content Only
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}

$query = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
$results = [];

if ($query) {
    $kb = new Oversee_KB();
    $results = $kb->search($query);
}
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Knowledge Base</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current">Search Results</span>
    </div>
</nav>

<section class="search-results-section">
    <div class="container">
        <form action="" method="GET" class="search-box-inline">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" name="q" value="<?php echo esc_attr($query); ?>" placeholder="Search articles..." class="form-control">
            <button type="submit" class="btn btn-primary">Search</button>
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
            <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Submit a Ticket</a>
        </div>
        <?php else: ?>
        <p class="search-count"><?php echo count($results); ?> result<?php echo count($results) !== 1 ? 's' : ''; ?> for "<?php echo esc_html($query); ?>"</p>
        
        <div class="search-results">
            <?php foreach ($results as $result): ?>
            <a href="<?php echo esc_url(oversee_kb_url($result['category_slug'] . '/' . $result['slug'])); ?>" class="result-item">
                <div class="result-icon"><i class="fa-solid fa-file-lines"></i></div>
                <div class="result-content">
                    <h3><?php echo esc_html($result['title']); ?></h3>
                    <?php if (!empty($result['excerpt'])): ?><p><?php echo esc_html($result['excerpt']); ?></p><?php endif; ?>
                    <span class="result-category"><i class="fa-solid fa-folder"></i> <?php echo esc_html($result['category_name'] ?? ucwords(str_replace('-', ' ', $result['category_slug']))); ?></span>
                </div>
                <i class="fa-solid fa-chevron-right result-arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>
