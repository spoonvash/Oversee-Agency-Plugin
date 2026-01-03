<?php
if (!defined('ABSPATH')) { define('ABSPATH', true); }
global $wpdb;
$category = isset($category) ? $category : get_query_var('category');
$categories = $wpdb->get_results("SELECT c.*, COUNT(a.id) as article_count FROM {$wpdb->prefix}oversee_kb_categories c LEFT JOIN {$wpdb->prefix}oversee_kb_articles a ON c.id = a.category_id GROUP BY c.id ORDER BY c.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Knowledge Base - OverseeCRM</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0a0a; color: #fff; min-height: 100vh; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; padding: 50px 20px; }
        .header h1 { font-size: 2.5rem; margin-bottom: 15px; }
        .header p { color: #9ca3af; font-size: 1.1rem; margin-bottom: 30px; }
        .search-box { max-width: 600px; margin: 0 auto; position: relative; }
        .search-box input { width: 100%; padding: 18px 25px 18px 55px; background: #1a1a1a; border: 2px solid #333; border-radius: 12px; color: #fff; font-size: 1.1rem; }
        .search-box input:focus { outline: none; border-color: #FF6B35; }
        .search-box i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: #6b7280; font-size: 1.2rem; }
        .search-results { position: absolute; top: 100%; left: 0; right: 0; background: #1a1a1a; border: 1px solid #333; border-radius: 12px; margin-top: 10px; max-height: 400px; overflow-y: auto; display: none; z-index: 100; }
        .search-results.active { display: block; }
        .search-result-item { padding: 15px 20px; border-bottom: 1px solid #222; cursor: pointer; transition: background 0.3s; }
        .search-result-item:hover { background: #222; }
        .categories-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 50px; }
        .category-card { background: #1a1a1a; border: 1px solid #333; border-radius: 12px; padding: 25px; transition: all 0.3s; cursor: pointer; }
        .category-card:hover { border-color: #FF6B35; transform: translateY(-3px); }
        .category-card h3 { font-size: 1.1rem; margin-bottom: 10px; color: #FF6B35; }
        .category-card p { color: #6b7280; font-size: 0.9rem; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #FF6B35; text-decoration: none; margin-bottom: 30px; }
        .back-link:hover { text-decoration: underline; }
        .articles-list { margin-top: 30px; }
        .article-item { display: block; padding: 20px; background: #1a1a1a; border: 1px solid #333; border-radius: 10px; margin-bottom: 15px; color: #fff; text-decoration: none; transition: all 0.3s; }
        .article-item:hover { border-color: #FF6B35; background: #222; }
        .article-item h4 { margin-bottom: 5px; }
        .article-item span { font-size: 13px; color: #6b7280; }
        .nav-links { margin-top: 40px; display: flex; gap: 20px; flex-wrap: wrap; }
        .nav-links a { color: #FF6B35; text-decoration: none; display: flex; align-items: center; gap: 8px; }
        .nav-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-book" style="color: #FF6B35;"></i> Knowledge Base</h1>
            <p>Find answers to your questions</p>
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search articles...">
                <div class="search-results" id="searchResults"></div>
            </div>
        </div>

        <?php if (empty($category)): ?>
        <div class="categories-grid">
            <?php foreach ($categories as $cat): ?>
            <div class="category-card" onclick="location.href='<?php echo home_url('/support/kb/' . $cat->slug . '/'); ?>'">
                <h3><i class="fas fa-folder"></i> <?php echo esc_html($cat->name); ?></h3>
                <p><?php echo $cat->article_count; ?> articles</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <a href="<?php echo home_url('/support/kb/'); ?>" class="back-link"><i class="fas fa-arrow-left"></i> All Categories</a>
        
        <?php
        $current_cat = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_kb_categories WHERE slug = %s", $category));
        $articles = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}oversee_kb_articles WHERE category_id = %d ORDER BY title", $current_cat->id));
        ?>
        
        <?php if ($current_cat): ?>
        <h2 style="margin-bottom: 20px;"><i class="fas fa-folder-open" style="color: #FF6B35;"></i> <?php echo esc_html($current_cat->name); ?></h2>
        
        <div class="articles-list">
            <?php foreach ($articles as $article): ?>
            <a href="<?php echo home_url('/support/article/' . $article->slug . '/'); ?>" class="article-item">
                <h4><i class="fas fa-file-alt" style="color: #6b7280; margin-right: 10px;"></i><?php echo esc_html($article->title); ?></h4>
                <span><?php echo number_format($article->views); ?> views</span>
            </a>
            <?php endforeach; ?>
            
            <?php if (empty($articles)): ?>
            <p style="color: #6b7280; text-align: center; padding: 40px;">No articles in this category yet.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="nav-links">
            <a href="<?php echo home_url('/support/'); ?>"><i class="fas fa-headset"></i> Contact Support</a>
            <a href="<?php echo home_url('/support/kb/'); ?>"><i class="fas fa-book"></i> Browse All</a>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        let timeout;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(timeout);
            const q = e.target.value.trim();
            if (q.length < 2) { searchResults.classList.remove('active'); return; }
            
            timeout = setTimeout(async () => {
                const res = await fetch('<?php echo rest_url("oversee/v1/kb/search"); ?>?q=' + encodeURIComponent(q));
                const articles = await res.json();
                
                if (articles.length) {
                    searchResults.innerHTML = articles.map(a => `
                        <div class="search-result-item" onclick="location.href='<?php echo home_url('/support/article/'); ?>${a.slug}/'">
                            <strong>${a.title}</strong><br>
                            <small style="color:#6b7280">${a.folder || ''}</small>
                        </div>
                    `).join('');
                    searchResults.classList.add('active');
                } else {
                    searchResults.innerHTML = '<div class="search-result-item">No results found</div>';
                    searchResults.classList.add('active');
                }
            }, 300);
        });

        searchInput.addEventListener('blur', () => setTimeout(() => searchResults.classList.remove('active'), 200));
    </script>
</body>
</html>
