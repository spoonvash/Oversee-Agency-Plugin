<?php
/**
 * Knowledge Base Home Page
 */
if (!defined('ABSPATH')) exit;

$kb = new Oversee_KB();
$categories = $kb->get_categories();
$total_categories = count($categories);

// Get REAL article count directly from database
global $wpdb;
$total_articles = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
    Oversee_KB_CPT::POST_TYPE
));

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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #f9fafb; color: #1f2937; line-height: 1.5; }
        
        /* Header */
        .site-header { background: #fff; border-bottom: 1px solid #e5e7eb; position: sticky; top: 0; z-index: 100; }
        .header-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: 64px; }
        .header-nav { display: flex; align-items: center; gap: 4px; }
        .header-nav a { display: flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 8px; text-decoration: none; color: #4b5563; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .header-nav a:hover, .header-nav a.active { background: #fff7ed; color: #ea580c; }
        .header-nav a i { font-size: 16px; }
        
        /* Buttons */
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; }
        .btn-primary { background: #ea580c; color: #fff; }
        .btn-primary:hover { background: #c2410c; }
        .btn-secondary { background: #fff; color: #4b5563; border: 1px solid #e5e7eb; }
        .btn-secondary:hover { background: #f9fafb; border-color: #d1d5db; }
        
        /* Hero */
        .hero { background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); color: #fff; padding: 80px 24px; }
        .hero-content { max-width: 800px; margin: 0 auto; text-align: center; }
        .hero h1 { font-size: 40px; font-weight: 700; margin-bottom: 16px; }
        .hero-subtitle { color: rgba(255,255,255,0.85); font-size: 18px; margin-bottom: 40px; }
        
        /* Search */
        .search-container { max-width: 600px; margin: 0 auto; }
        .search-wrapper { position: relative; }
        .search-box { display: flex; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.15); }
        .search-box input { flex: 1; padding: 18px 24px; border: none; font-size: 16px; outline: none; min-width: 0; }
        .search-box input::placeholder { color: #9ca3af; }
        .search-box button { padding: 18px 28px; background: #ea580c; color: #fff; border: none; font-weight: 600; font-size: 15px; cursor: pointer; transition: background 0.2s; white-space: nowrap; }
        .search-box button:hover { background: #c2410c; }
        
        /* Search Results Dropdown */
        .search-results { position: absolute; top: calc(100% + 8px); left: 0; right: 0; background: #fff; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.15); max-height: 400px; overflow-y: auto; z-index: 200; display: none; }
        .search-results.show { display: block; }
        .search-result-item { display: block; padding: 16px 20px; border-bottom: 1px solid #f3f4f6; text-decoration: none; color: #1f2937; transition: background 0.15s; }
        .search-result-item:last-child { border-bottom: none; }
        .search-result-item:hover { background: #fff7ed; }
        .search-result-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; color: #1f2937; }
        .search-result-category { font-size: 12px; color: #ea580c; display: flex; align-items: center; gap: 6px; }
        .search-no-results, .search-loading { padding: 32px 20px; text-align: center; color: #6b7280; }
        .search-loading i { margin-right: 8px; }
        
        /* Popular Tags */
        .popular-tags { margin-top: 28px; display: flex; align-items: center; justify-content: center; gap: 10px; flex-wrap: wrap; }
        .popular-tags span { color: rgba(255,255,255,0.6); font-size: 14px; }
        .popular-tags .tag { background: rgba(255,255,255,0.12); color: #fff; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; transition: background 0.2s; }
        .popular-tags .tag:hover { background: rgba(255,255,255,0.22); }
        
        /* Main Content */
        .main-content { padding: 60px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .section-header { margin-bottom: 28px; }
        .section-header h2 { font-size: 26px; font-weight: 700; color: #1f2937; margin-bottom: 6px; }
        .section-header p { color: #6b7280; font-size: 15px; }
        
        /* Categories Grid */
        .categories-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        @media (max-width: 900px) { .categories-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 600px) { .categories-grid { grid-template-columns: 1fr; } }
        
        .category-card { display: flex; align-items: center; gap: 16px; padding: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; text-decoration: none; transition: all 0.2s; }
        .category-card:hover { border-color: #ea580c; box-shadow: 0 4px 12px rgba(234,88,12,0.08); transform: translateY(-2px); }
        .category-icon { width: 48px; height: 48px; background: #fff7ed; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #ea580c; font-size: 20px; flex-shrink: 0; }
        .category-info { min-width: 0; }
        .category-info h3 { font-size: 15px; font-weight: 600; color: #1f2937; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .category-info span { font-size: 13px; color: #ea580c; font-weight: 500; }
        
        /* Show More */
        .show-more-container { text-align: center; margin-top: 32px; }
        
        /* Footer */
        .site-footer { text-align: center; padding: 32px 24px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; background: #fff; }
        .site-footer a { color: #ea580c; text-decoration: none; font-weight: 500; }
        .site-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
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
            <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i>
                <span>Submit Ticket</span>
            </a>
        </div>
    </header>
    
    <section class="hero">
        <div class="hero-content">
            <h1>How can we help you?</h1>
            <p class="hero-subtitle">Search our knowledge base or browse <?php echo number_format($total_articles); ?> articles</p>
            
            <div class="search-container">
                <div class="search-wrapper">
                    <div class="search-box">
                        <input type="text" id="searchInput" placeholder="Search for articles, guides, FAQs..." autocomplete="off">
                        <button type="button" id="searchBtn">Search</button>
                    </div>
                    <div class="search-results" id="searchResults"></div>
                </div>
            </div>
            
            <div class="popular-tags">
                <span>Popular:</span>
                <a href="<?php echo esc_url(oversee_kb_url('getting-started')); ?>" class="tag">Getting Started</a>
                <a href="<?php echo esc_url(oversee_kb_url('calendars')); ?>" class="tag">Calendars</a>
                <a href="<?php echo esc_url(oversee_kb_url('workflow')); ?>" class="tag">Workflows</a>
            </div>
        </div>
    </section>
    
    <main class="main-content">
        <div class="container">
            <div class="section-header">
                <h2>Browse by Category</h2>
                <p><?php echo $total_categories; ?> categories available</p>
            </div>
            
            <div class="categories-grid" id="categoriesGrid">
                <?php 
                $count = 0;
                foreach ($categories as $cat): 
                    $hidden = $count >= 9 ? 'style="display:none;"' : '';
                    $data_attr = $count >= 9 ? 'data-hidden="1"' : '';
                ?>
                <a href="<?php echo esc_url(oversee_kb_url($cat['slug'])); ?>" class="category-card" <?php echo $hidden; ?> <?php echo $data_attr; ?>>
                    <div class="category-icon">
                        <i class="<?php echo esc_attr($cat['icon'] ?: 'fa-solid fa-folder'); ?>"></i>
                    </div>
                    <div class="category-info">
                        <h3><?php echo esc_html($cat['name']); ?></h3>
                        <span><?php echo (int)$cat['article_count']; ?> articles</span>
                    </div>
                </a>
                <?php $count++; endforeach; ?>
            </div>
            
            <?php if ($total_categories > 9): ?>
            <div class="show-more-container">
                <button class="btn btn-secondary" id="showMoreBtn">
                    <i class="fa-solid fa-chevron-down"></i>
                    <span>Show More Categories</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer class="site-footer">
        <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
    </footer>
    
    <script>
    (function() {
        var REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        var KB_URL = '<?php echo esc_url(oversee_kb_url()); ?>';
        var searchInput = document.getElementById('searchInput');
        var searchResults = document.getElementById('searchResults');
        var searchBtn = document.getElementById('searchBtn');
        var showMoreBtn = document.getElementById('showMoreBtn');
        var searchTimeout;
        
        // Live search
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            var q = this.value.trim();
            
            if (q.length < 2) {
                searchResults.classList.remove('show');
                return;
            }
            
            searchResults.innerHTML = '<div class="search-loading"><i class="fa-solid fa-spinner fa-spin"></i>Searching...</div>';
            searchResults.classList.add('show');
            
            searchTimeout = setTimeout(function() {
                fetch(REST_URL + '/kb/search?q=' + encodeURIComponent(q) + '&limit=8')
                .then(function(r) { return r.json(); })
                .then(function(results) {
                    if (!results || results.length === 0) {
                        searchResults.innerHTML = '<div class="search-no-results">No results found for "' + escapeHtml(q) + '"</div>';
                        return;
                    }
                    var html = '';
                    for (var i = 0; i < results.length; i++) {
                        var item = results[i];
                        html += '<a href="' + KB_URL + '/' + item.category_slug + '/' + item.slug + '" class="search-result-item">';
                        html += '<div class="search-result-title">' + escapeHtml(item.title) + '</div>';
                        html += '<div class="search-result-category"><i class="fa-solid fa-folder"></i>' + escapeHtml(item.category_name || '') + '</div>';
                        html += '</a>';
                    }
                    searchResults.innerHTML = html;
                })
                .catch(function() {
                    searchResults.innerHTML = '<div class="search-no-results">Search failed. Please try again.</div>';
                });
            }, 300);
        });
        
        // Enter key
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') doSearch();
        });
        
        // Search button
        searchBtn.addEventListener('click', doSearch);
        
        // Click outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.classList.remove('show');
            }
        });
        
        function doSearch() {
            var q = searchInput.value.trim();
            if (q.length >= 2) {
                window.location.href = KB_URL + '/search?q=' + encodeURIComponent(q);
            }
        }
        
        // Show more categories
        if (showMoreBtn) {
            var expanded = false;
            showMoreBtn.addEventListener('click', function() {
                var hidden = document.querySelectorAll('.category-card[data-hidden]');
                expanded = !expanded;
                for (var i = 0; i < hidden.length; i++) {
                    hidden[i].style.display = expanded ? 'flex' : 'none';
                }
                showMoreBtn.innerHTML = expanded 
                    ? '<i class="fa-solid fa-chevron-up"></i><span>Show Less</span>'
                    : '<i class="fa-solid fa-chevron-down"></i><span>Show More Categories</span>';
            });
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    })();
    </script>
</body>
</html>