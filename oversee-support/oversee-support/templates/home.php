<?php
/**
 * Knowledge Base Home Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$kb = new Oversee_KB();
$categories = $kb->get_categories();
$total_categories = count($categories);
$total_articles = $kb->get_total_articles();
$portal_title = oversee_get_portal_title();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($portal_title); ?> - Help Center</title>
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

        <!-- Hero -->
        <section class="hero">
            <h1>How can we help you?</h1>
            <p>Search our knowledge base or browse <?php echo number_format($total_articles); ?> articles</p>
            
            <div class="search-wrapper">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search for articles, guides, FAQs..." autocomplete="off">
                    <button type="button" onclick="performSearch()">Search</button>
                </div>
                <div class="search-dropdown" id="searchDropdown"></div>
            </div>
            
            <div class="popular-tags">
                <span>Popular:</span>
                <a href="<?php echo esc_url(oversee_kb_url('getting-started')); ?>" class="tag">Getting Started</a>
                <a href="<?php echo esc_url(oversee_kb_url('billing')); ?>" class="tag">Billing</a>
                <a href="<?php echo esc_url(oversee_kb_url('integrations')); ?>" class="tag">Integrations</a>
            </div>
        </section>

        <!-- Categories -->
        <main class="main-content">
            <section class="categories-section">
                <div class="container">
                    <div class="section-header">
                        <h2 class="section-title">Browse by Category</h2>
                        <p class="section-subtitle"><?php echo number_format($total_categories); ?> categories available</p>
                    </div>
                    
                    <div class="categories-grid" id="categoriesGrid">
                        <?php 
                        $count = 0;
                        foreach ($categories as $category): 
                            $hidden = $count >= 9 ? ' style="display:none" data-hidden="true"' : '';
                            $count++;
                            $article_count = isset($category['article_count']) ? $category['article_count'] : 0;
                            $icon = isset($category['icon']) ? $category['icon'] : 'fa-solid fa-folder';
                        ?>
                        <a href="<?php echo esc_url(oversee_kb_url($category['slug'])); ?>" class="category-card"<?php echo $hidden; ?>>
                            <div class="category-icon">
                                <i class="<?php echo esc_attr($icon); ?>"></i>
                            </div>
                            <div class="category-info">
                                <h3><?php echo esc_html($category['name']); ?></h3>
                                <span><?php echo intval($article_count); ?> article<?php echo $article_count !== 1 ? 's' : ''; ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($total_categories > 9): ?>
                    <div class="load-more-wrapper">
                        <button class="btn btn-outline" id="loadMoreBtn" onclick="loadMoreCategories()">
                            <i class="fa-solid fa-chevron-down"></i>
                            Show More Categories
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const searchDropdown = document.getElementById('searchDropdown');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                searchDropdown.classList.remove('active');
                return;
            }
            
            searchTimeout = setTimeout(() => doLiveSearch(query), 300);
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function performSearch() {
            const query = searchInput.value.trim();
            if (query.length >= 2) {
                window.location.href = '<?php echo esc_url(oversee_kb_url('search')); ?>?q=' + encodeURIComponent(query);
            }
        }

        async function doLiveSearch(query) {
            try {
                const response = await fetch('<?php echo esc_url(rest_url('oversee/v1/kb/search')); ?>?q=' + encodeURIComponent(query));
                const results = await response.json();
                
                if (!results || results.length === 0) {
                    searchDropdown.innerHTML = '<div class="search-item"><span>No results found</span></div>';
                } else {
                    searchDropdown.innerHTML = results.slice(0, 6).map(r => `
                        <a href="<?php echo esc_url(oversee_kb_url()); ?>${r.category_slug}/${r.slug}/" class="search-item">
                            <h4>${escapeHtml(r.title)}</h4>
                            <span><i class="fa-solid fa-folder"></i> ${escapeHtml(r.category_name || r.category_slug)}</span>
                        </a>
                    `).join('');
                }
                searchDropdown.classList.add('active');
            } catch (e) {
                searchDropdown.classList.remove('active');
            }
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-wrapper')) {
                searchDropdown.classList.remove('active');
            }
        });

        // Load more categories
        function loadMoreCategories() {
            document.querySelectorAll('[data-hidden="true"]').forEach(el => {
                el.style.display = '';
                el.removeAttribute('data-hidden');
            });
            document.getElementById('loadMoreBtn').parentElement.style.display = 'none';
        }

        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
