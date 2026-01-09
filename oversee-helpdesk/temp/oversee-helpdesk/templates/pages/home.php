<?php
/**
 * Knowledge Base Home Page - Content Only
 * 
 * Rendered through Oversee_Template_Loader - NO inline styles.
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}

$kb = new Oversee_KB();
$categories = $kb->get_categories();
$total_categories = count($categories);
$total_articles = $kb->get_total_articles();

$popular_categories = $categories;
usort($popular_categories, function($a, $b) {
    return ($b['article_count'] ?? 0) - ($a['article_count'] ?? 0);
});
$popular_categories = array_slice($popular_categories, 0, 3);
?>

<section class="hero">
    <h1>How can we help you?</h1>
    <p>Search our knowledge base or browse <?php echo number_format($total_articles); ?> articles</p>
    
    <div class="search-wrapper">
        <div class="search-box">
            <i class="fa-solid fa-search search-icon"></i>
            <input type="text" id="searchInput" placeholder="Search for articles, guides, FAQs..." autocomplete="off" spellcheck="false">
            <button type="button" onclick="performSearch()">Search</button>
        </div>
        <div class="search-dropdown" id="searchDropdown">
            <div class="search-loading" id="searchLoading">
                <i class="fa-solid fa-circle-notch fa-spin"></i>
                <span>Searching...</span>
            </div>
            <div id="searchResults"></div>
        </div>
    </div>
    
    <?php if (!empty($popular_categories)): ?>
    <div class="popular-tags">
        <span>Popular:</span>
        <?php foreach ($popular_categories as $pop_cat): ?>
        <a href="<?php echo esc_url(oversee_kb_url($pop_cat['slug'])); ?>" class="tag"><?php echo esc_html($pop_cat['name']); ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>

<section class="categories-section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title">Browse by Category</h2>
            <p class="section-subtitle"><?php echo number_format($total_categories); ?> categories available</p>
        </div>
        
        <div class="categories-grid">
            <?php 
            $display_count = 0;
            $max_initial = 9;
            foreach ($categories as $category): 
                $display_count++;
                $hidden_class = $display_count > $max_initial ? 'category-hidden' : '';
            ?>
            <a href="<?php echo esc_url(oversee_kb_url($category['slug'])); ?>" class="category-card <?php echo $hidden_class; ?>">
                <div class="category-icon">
                    <i class="fa-solid <?php echo esc_attr($category['icon'] ?? 'fa-folder'); ?>"></i>
                </div>
                <div class="category-content">
                    <h3><?php echo esc_html($category['name']); ?></h3>
                    <span class="article-count"><?php echo number_format($category['article_count'] ?? 0); ?> articles</span>
                </div>
                <i class="fa-solid fa-chevron-right category-arrow"></i>
            </a>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_categories > $max_initial): ?>
        <div class="load-more">
            <button type="button" id="loadMoreBtn" class="btn btn-secondary" onclick="loadMoreCategories()">
                <i class="fa-solid fa-plus"></i> Show All Categories
            </button>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    const searchInput = document.getElementById('searchInput');
    const searchDropdown = document.getElementById('searchDropdown');
    const searchLoading = document.getElementById('searchLoading');
    const searchResults = document.getElementById('searchResults');
    let searchTimeout = null;
    let lastQuery = '';

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            if (query.length < 2) { closeDropdown(); return; }
            searchTimeout = setTimeout(() => { if (query !== lastQuery) performLiveSearch(query); }, 300);
        });
        searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); performSearch(); } });
    }

    window.performSearch = function() {
        const query = searchInput.value.trim();
        if (query.length >= 2) window.location.href = '<?php echo esc_url(oversee_kb_url('search')); ?>?q=' + encodeURIComponent(query);
    };

    async function performLiveSearch(query) {
        lastQuery = query;
        showDropdown();
        searchLoading.style.display = 'flex';
        searchResults.innerHTML = '';
        try {
            let response = await fetch('<?php echo esc_url(rest_url('oversee/v1/kb/search')); ?>?q=' + encodeURIComponent(query), { headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' } });
            if (!response.ok) response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=oversee_kb_search&q=' + encodeURIComponent(query));
            if (!response.ok) throw new Error('Search failed');
            const data = await response.json();
            const articles = data.data || data.articles || data || [];
            searchLoading.style.display = 'none';
            if (articles.length === 0) {
                searchResults.innerHTML = '<div class="no-results"><i class="fa-solid fa-search"></i><p>No articles found</p></div>';
                return;
            }
            let html = '<div class="search-results-list">';
            articles.slice(0, 5).forEach(a => {
                html += '<a href="' + (a.url || '#') + '" class="search-result-item"><div class="result-icon"><i class="fa-solid fa-file-lines"></i></div><div class="result-content"><h4>' + escapeHtml(a.title) + '</h4></div></a>';
            });
            if (articles.length > 5) html += '<a href="<?php echo esc_url(oversee_kb_url('search')); ?>?q=' + encodeURIComponent(query) + '" class="view-all-results">View all results</a>';
            html += '</div>';
            searchResults.innerHTML = html;
        } catch (e) {
            searchLoading.style.display = 'none';
            searchResults.innerHTML = '<div class="no-results"><p>Search unavailable</p></div>';
        }
    }

    function showDropdown() { searchDropdown.classList.add('active'); }
    function closeDropdown() { searchDropdown.classList.remove('active'); searchLoading.style.display = 'none'; }
    function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

    document.addEventListener('click', function(e) { if (!e.target.closest('.search-wrapper')) closeDropdown(); });

    window.loadMoreCategories = function() {
        document.querySelectorAll('.category-hidden').forEach(el => el.classList.remove('category-hidden'));
        document.getElementById('loadMoreBtn').parentElement.remove();
    };
})();
</script>
