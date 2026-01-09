<?php
/**
 * Knowledge Base Importer
 * - Phase 1: Discovers ALL articles first (counts them)
 * - Phase 2: Creates categories
 * - Phase 3: Imports articles with X/Y progress
 * - Phase 4: Retries failed articles
 * - Phase 5: Updates term counts
 * 
 * IMPORTANT: Uses direct DB writes for progress to bypass object cache
 */
if (!defined('ABSPATH')) exit;

class Oversee_KB_Importer {
    private $source_url;
    private $all_articles = [];
    private $categories = [];
    private $term_map = [];
    private $stats;
    
    public function __construct() {
        $this->source_url = rtrim(get_option('oversee_static_kb_url', 'https://overseecrm.com/wp-content/uploads/knowledge-base'), '/');
        $this->stats = [
            'phase' => '',
            'categories_added' => 0,
            'categories_updated' => 0,
            'articles_added' => 0,
            'articles_updated' => 0,
            'articles_skipped' => 0,
            'articles_failed' => 0,
            'total_discovered' => 0,
            'current_article' => 0,
            'current_category' => 0,
            'total_categories' => 0,
            'errors' => [],
            'failed_list' => []
        ];
    }
    
    private function fetch_url($url, $retries = 3) {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $response = wp_remote_get($url, [
                'timeout' => 90, 'redirection' => 10,
                'sslverify' => false,
                'httpversion' => '1.1',
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Cache-Control' => 'no-cache'
                ]
            ]);
            
            if (!is_wp_error($response) && in_array(wp_remote_retrieve_response_code($response), [200, 301, 302], true)) {
                return wp_remote_retrieve_body($response);
            }
            
            if ($attempt < $retries) {
                sleep(2);
            }
        }
        return false;
    }
    
    /**
     * Update progress using DIRECT database write to bypass object cache
     * This ensures polling requests get fresh data
     */
    private function update_progress($phase, $current, $total, $message) {
        global $wpdb;
        
        $percent = $total > 0 ? round(($current / $total) * 100) : 0;
        $this->stats['phase'] = $phase;
        
        $progress_data = [
            'status' => $phase,
            'phase' => $phase,
            'percent' => $percent,
            'current' => $current,
            'total' => $total,
            'message' => $message,
            'stats' => $this->stats,
            'time' => current_time('mysql')
        ];
        
        $serialized = maybe_serialize($progress_data);
        $option_name = 'oversee_kb_sync_progress';
        
        // Direct DB update bypassing cache
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name = %s",
            $option_name
        ));
        
        if ($exists) {
            $wpdb->update(
                $wpdb->options,
                ['option_value' => $serialized],
                ['option_name' => $option_name],
                ['%s'],
                ['%s']
            );
        } else {
            $wpdb->insert(
                $wpdb->options,
                [
                    'option_name' => $option_name,
                    'option_value' => $serialized,
                    'autoload' => 'no'
                ],
                ['%s', '%s', '%s']
            );
        }
        
        // Clear object cache for this option
        wp_cache_delete($option_name, 'options');
        wp_cache_delete('alloptions', 'options');
    }
    
    public function run_full_import() {
        // Increase limits for long-running process
        @set_time_limit(0);
        @ignore_user_abort(true);
        @ini_set('memory_limit', '512M');
        
        // Close session to allow concurrent requests (for progress polling)
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // ========== PHASE 1: DISCOVER ==========
        $this->update_progress('discovering', 0, 100, 'Phase 1/5: Discovering all articles...');
        
        if (!$this->discover_all_content()) {
            $this->update_progress('error', 0, 0, 'Failed to discover content');
            return $this->stats;
        }
        
        $this->stats['total_discovered'] = count($this->all_articles);
        $this->stats['total_categories'] = count($this->categories);
        
        $this->update_progress('discovered', 100, 100, 
            "Found {$this->stats['total_discovered']} articles in {$this->stats['total_categories']} categories"
        );
        sleep(1);
        
        // ========== PHASE 2: CATEGORIES ==========
        $total_cats = count($this->categories);
        foreach ($this->categories as $i => $cat) {
            $this->stats['current_category'] = $i + 1;
            $this->update_progress('categories', $i + 1, $total_cats, 
                "Phase 2/5: Creating category " . ($i + 1) . "/{$total_cats}: {$cat['name']}"
            );
            
            $term_id = $this->create_category($cat);
            if ($term_id) {
                $this->term_map[$cat['slug']] = $term_id;
            }
        }
        
        // ========== PHASE 3: ARTICLES ==========
        $total_articles = count($this->all_articles);
        foreach ($this->all_articles as $i => $article) {
            $this->stats['current_article'] = $i + 1;
            
            // Update progress every article (direct DB write is fast)
            $this->update_progress('articles', $i + 1, $total_articles,
                "Phase 3/5: Importing " . ($i + 1) . " of {$total_articles}"
            );
            
            $term_id = isset($this->term_map[$article['category_slug']]) ? $this->term_map[$article['category_slug']] : 0;
            $this->import_article($article, $term_id);
            
            // Brief pause every 10 articles
            if (($i + 1) % 10 === 0) {
                usleep(50000); // 0.05s
            }
        }
        
        // ========== PHASE 4: RETRY FAILED ==========
        if (!empty($this->stats['failed_list'])) {
            $failed = $this->stats['failed_list'];
            $this->stats['failed_list'] = [];
            $total_retry = count($failed);
            
            foreach ($failed as $i => $article) {
                $this->update_progress('retrying', $i + 1, $total_retry,
                    "Phase 4/5: Retrying " . ($i + 1) . " of {$total_retry}"
                );
                
                sleep(1);
                $term_id = isset($this->term_map[$article['category_slug']]) ? $this->term_map[$article['category_slug']] : 0;
                $this->import_article($article, $term_id, true);
            }
        } else {
            $this->update_progress('retrying', 1, 1, "Phase 4/5: No failed articles to retry");
        }
        
        // ========== PHASE 5: FINALIZE ==========
        $this->update_progress('finalizing', 1, 2, "Phase 5/5: Updating category counts...");
        
        $terms = get_terms(['taxonomy' => Oversee_KB_CPT::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids']);
        if (!is_wp_error($terms)) {
            foreach ($terms as $tid) {
                wp_update_term_count_now([$tid], Oversee_KB_CPT::TAXONOMY);
            }
        }
        
        // Save final stats
        update_option('oversee_kb_last_sync', current_time('mysql'));
        update_option('oversee_kb_sync_stats', $this->stats);
        
        // IMPORTANT: Clear category cache so fresh data is displayed
        delete_transient('oversee_kb_cats_db');
        wp_cache_delete('oversee_kb_cats_db', 'transient');
        wp_cache_flush();
        
        $success_count = $this->stats['articles_added'] + $this->stats['articles_updated'];
        $fail_count = $this->stats['articles_failed'];
        
        $this->update_progress('complete', 100, 100,
            "Complete! {$success_count} of {$this->stats['total_discovered']} articles imported" .
            ($fail_count > 0 ? " ({$fail_count} failed)" : "")
        );
        
        return $this->stats;
    }
    
    private function discover_all_content() {
        $html = $this->fetch_url($this->source_url . '/index.html');
        if (!$html) {
            $this->stats['errors'][] = 'Failed to fetch main index';
            return false;
        }
        
        // Parse categories - primary pattern
        if (!preg_match_all('/<a[^>]+href=["\']([^"\']+)\/index\.html["\'][^>]*>\s*<h3>([^<]+)<\/h3>\s*<span>(\d+)\s*articles?<\/span>/is', $html, $matches, PREG_SET_ORDER)) {
            // Fallback pattern - more lenient, allows content between elements
            if (!preg_match_all('/<a[^>]+href=["\']([^"\']+)\/index\.html["\'][^>]*>.*?<h3>([^<]+)<\/h3>.*?<span>(\d+)/is', $html, $matches, PREG_SET_ORDER)) {
                $this->stats['errors'][] = 'No categories found in index';
                return false;
            }
        }
        
        $total_cats = count($matches);
        foreach ($matches as $i => $m) {
            $cat_slug = trim($m[1], './');
            $cat_name = html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8');
            
            $this->categories[] = [
                'slug' => $cat_slug,
                'name' => $cat_name,
                'expected_count' => (int)$m[3]
            ];
            
            $this->update_progress('discovering', $i + 1, $total_cats,
                "Phase 1/5: Scanning {$cat_name}... (found " . count($this->all_articles) . " articles)"
            );
            
            $this->discover_category_articles($cat_slug);
            usleep(50000);
        }
        
        $this->stats['total_discovered'] = count($this->all_articles);
        return true;
    }
    
    private function discover_category_articles($cat_slug) {
        $url = $this->source_url . '/' . $cat_slug . '/index.html';
        $html = $this->fetch_url($url);
        
        if (!$html) {
            $this->stats['errors'][] = "Failed to fetch category: {$cat_slug}";
            return;
        }
        
        // Primary pattern - <a href="xxx.html"><h3>Title</h3></a>
        if (preg_match_all('/<a[^>]+href=["\']([^"\']+)\.html["\'][^>]*>\s*<h3>([^<]+)<\/h3>/is', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $slug = basename($m[1]);
                if ($slug === 'index' || empty($slug)) continue;
                
                $this->all_articles[] = [
                    'slug' => $slug,
                    'title' => html_entity_decode(trim($m[2]), ENT_QUOTES, 'UTF-8'),
                    'category_slug' => $cat_slug
                ];
            }
        }
        
        // Fallback pattern if no articles found - simpler <a href="xxx.html">Title</a>
        if (empty($this->all_articles) || count(array_filter($this->all_articles, function($a) use ($cat_slug) { return $a['category_slug'] === $cat_slug; })) === 0) {
            if (preg_match_all('/<a[^>]+href=["\']([^"\']+)\.html["\'][^>]*>([^<]+)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $slug = basename($m[1]);
                    $title = trim($m[2]);
                    // Skip index, empty, or back links
                    if ($slug === 'index' || empty($slug) || empty($title)) continue;
                    if (stripos($title, 'back') !== false || stripos($title, '←') !== false) continue;
                    
                    $this->all_articles[] = [
                        'slug' => $slug,
                        'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
                        'category_slug' => $cat_slug
                    ];
                }
            }
        }
    }
    
    private function create_category($cat) {
        $slug = sanitize_title($cat['slug']);
        $name = sanitize_text_field($cat['name']);
        $existing = get_term_by('slug', $slug, Oversee_KB_CPT::TAXONOMY);
        
        if ($existing) {
            $term_id = $existing->term_id;
            $this->stats['categories_updated']++;
        } else {
            $result = wp_insert_term($name, Oversee_KB_CPT::TAXONOMY, ['slug' => $slug]);
            if (is_wp_error($result)) {
                $this->stats['errors'][] = "Category '{$name}': " . $result->get_error_message();
                return false;
            }
            $term_id = $result['term_id'];
            $this->stats['categories_added']++;
        }
        
        update_term_meta($term_id, 'original_slug', $cat['slug']);
        update_term_meta($term_id, 'icon', $this->get_icon($slug));
        
        return $term_id;
    }
    
    private function import_article($article, $term_id, $is_retry = false) {
        $slug = sanitize_title($article['slug']);
        $cat_slug = $article['category_slug'];
        
        // Check for existing by original slug
        $existing = get_posts([
            'post_type' => Oversee_KB_CPT::POST_TYPE,
            'meta_key' => '_kb_original_slug',
            'meta_value' => $article['slug'],
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);
        
        $content = $this->fetch_article_content($cat_slug, $article['slug']);
        
        if (empty($content)) {
            $this->stats['articles_failed']++;
            if (!$is_retry) {
                $this->stats['failed_list'][] = $article;
            }
            return false;
        }
        
        // Check for duplicate by title (if no existing found by slug)
        if (empty($existing)) {
            $duplicate = get_posts([
                'post_type' => Oversee_KB_CPT::POST_TYPE,
                'title' => $content['title'],
                'posts_per_page' => 1,
                'post_status' => 'any',
                'tax_query' => $term_id ? [
                    ['taxonomy' => Oversee_KB_CPT::TAXONOMY, 'field' => 'term_id', 'terms' => $term_id]
                ] : []
            ]);
            
            if (!empty($duplicate)) {
                // Skip this duplicate
                $this->stats['articles_skipped']++;
                return true;
            }
        }
        
        $post_data = [
            'post_title' => $content['title'],
            'post_content' => $content['content'],
            'post_excerpt' => $content['excerpt'],
            'post_status' => 'publish',
            'post_type' => Oversee_KB_CPT::POST_TYPE,
            'post_name' => $slug
        ];
        
        if (!empty($existing)) {
            $post_data['ID'] = $existing[0]->ID;
            $post_id = wp_update_post($post_data);
            if (!$is_retry) $this->stats['articles_updated']++;
        } else {
            $post_id = wp_insert_post($post_data);
            if (!$is_retry) $this->stats['articles_added']++;
        }
        
        if (!$post_id || is_wp_error($post_id)) {
            $this->stats['articles_failed']++;
            return false;
        }
        
        if ($term_id) {
            wp_set_object_terms($post_id, $term_id, Oversee_KB_CPT::TAXONOMY);
        }
        update_post_meta($post_id, '_kb_original_slug', $article['slug']);
        update_post_meta($post_id, '_kb_category_slug', $cat_slug);
        update_post_meta($post_id, '_kb_views', 0);
        
        return true;
    }
    
    private function fetch_article_content($cat_slug, $art_slug) {
        $url = $this->source_url . '/' . $cat_slug . '/' . $art_slug . '.html';
        $html = $this->fetch_url($url, 2);
        
        if (!$html) return null;
        
        // Title
        $title = ucwords(str_replace('-', ' ', $art_slug));
        
        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
            $title = trim($m[1]);
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $title = trim(preg_replace('/\s*[-|–].+$/', '', $m[1]));
        }
        
        // Content
        $content = $html;
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $m)) {
            $content = $m[1];
        }
        if (preg_match('/<div[^>]*class=["\'][^"\']*container[^"\']*["\'][^>]*>(.*)/is', $content, $m)) {
            $content = $m[1];
        }
        
        // Clean - but KEEP iframes (YouTube videos)
        $content = preg_replace([
            '/<script[^>]*>.*?<\/script>/is',
            '/<style[^>]*>.*?<\/style>/is',
            '/<nav[^>]*>.*?<\/nav>/is',
            '/<h1[^>]*>.*?<\/h1>/is',
            '/<a[^>]*class=["\']back["\'][^>]*>.*?<\/a>/is',
            '/<a[^>]*>.*?←.*?<\/a>/is',
            '/<a[^>]*>.*?All Categories.*?<\/a>/is'
        ], '', $content);
        
        // Remove duplicate "Screenshots" section (dumps all images again)
        $content = preg_replace('/<h[23][^>]*>\s*Screenshots\s*<\/h[23]>\s*((<p>)?\s*(<a[^>]*>)?<img[^>]*>(<\/a>)?(\s*<\/p>)?\s*(<br\s*\/?>)?\s*)+/is', '', $content);
        
        // Remove any trailing "Back to" navigation
        $content = preg_replace('/<p[^>]*>\s*<a[^>]*>.*?Back to.*?<\/a>\s*<\/p>/is', '', $content);
        
        // Fix images
        $base = $this->source_url;
        $cat_base = $this->source_url . '/' . $cat_slug;
        $content = preg_replace('/src=["\']\.\.\/([^"\']+)["\']/i', 'src="'.$base.'/$1"', $content);
        $content = preg_replace('/src=["\']\.\/([^"\']+)["\']/i', 'src="'.$cat_base.'/$1"', $content);
        $content = preg_replace('/src=["\'](?!http|data:)([^"\']+)["\']/i', 'src="'.$cat_base.'/$1"', $content);
        
        return [
            'title' => html_entity_decode($title, ENT_QUOTES, 'UTF-8'),
            'content' => trim($content),
            'excerpt' => wp_trim_words(strip_tags($content), 30)
        ];
    }
    
    private function get_icon($slug) {
        $icons = [
            'ad-manager' => 'fa-solid fa-rectangle-ad',
            'affiliate' => 'fa-solid fa-handshake',
            'calendar' => 'fa-solid fa-calendar',
            'campaign' => 'fa-solid fa-bullhorn',
            'communities' => 'fa-solid fa-users',
            'contacts' => 'fa-solid fa-address-book',
            'conversation' => 'fa-solid fa-message',
            'domain' => 'fa-solid fa-globe',
            'ecommerce' => 'fa-solid fa-cart-shopping',
            'email' => 'fa-solid fa-envelope',
            'facebook' => 'fa-brands fa-facebook',
            'forms' => 'fa-solid fa-rectangle-list',
            'funnel' => 'fa-solid fa-filter',
            'getting-started' => 'fa-solid fa-rocket',
            'google' => 'fa-brands fa-google',
            'integrations' => 'fa-solid fa-plug',
            'invoices' => 'fa-solid fa-file-invoice-dollar',
            'workflow' => 'fa-solid fa-sitemap',
            'phone' => 'fa-solid fa-phone',
            'whatsapp' => 'fa-brands fa-whatsapp',
            'sms' => 'fa-solid fa-comment-sms',
            'payment' => 'fa-solid fa-credit-card',
            'membership' => 'fa-solid fa-id-card',
            'reputation' => 'fa-solid fa-star',
            'tiktok' => 'fa-brands fa-tiktok',
            'linkedin' => 'fa-brands fa-linkedin',
            'wordpress' => 'fa-brands fa-wordpress',
        ];
        foreach ($icons as $k => $v) {
            if (stripos($slug, $k) !== false) return $v;
        }
        return 'fa-solid fa-folder';
    }
    
    public function get_stats() {
        return $this->stats;
    }
    
    /**
     * Get progress using DIRECT database read to bypass object cache
     */
    public static function get_progress() {
        global $wpdb;
        
        // Direct DB read to bypass cache
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            'oversee_kb_sync_progress'
        ));
        
        if ($value) {
            $progress = maybe_unserialize($value);
            if (is_array($progress)) {
                return $progress;
            }
        }
        
        return [
            'status' => 'idle',
            'phase' => 'idle',
            'percent' => 0,
            'current' => 0,
            'total' => 0,
            'message' => '',
            'stats' => []
        ];
    }
    
    public static function clear_all_data() {
        global $wpdb;
        
        // Delete all KB articles
        $posts = get_posts([
            'post_type' => Oversee_KB_CPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids'
        ]);
        foreach ($posts as $id) {
            wp_delete_post($id, true);
        }
        
        // Delete all KB categories
        $terms = get_terms([
            'taxonomy' => Oversee_KB_CPT::TAXONOMY,
            'hide_empty' => false,
            'fields' => 'ids'
        ]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $id) {
                wp_delete_term($id, Oversee_KB_CPT::TAXONOMY);
            }
        }
        
        // Clear options
        delete_option('oversee_kb_last_sync');
        delete_option('oversee_kb_sync_stats');
        delete_option('oversee_kb_sync_progress');
        
        // IMPORTANT: Clear transient caches so counts update immediately
        delete_transient('oversee_kb_cats_db');
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oversee_kb_%' OR option_name LIKE '_transient_timeout_oversee_kb_%'");
        
        // Clear object cache
        wp_cache_delete('oversee_kb_cats_db', 'transient');
        wp_cache_flush();
        
        return true;
    }
    
    /**
     * Remove duplicate articles (same title in same category)
     * Keeps the first one, deletes the rest
     */
    public static function remove_duplicates() {
        global $wpdb;
        
        $removed = 0;
        
        // Find articles with duplicate titles
        $duplicates = $wpdb->get_results("
            SELECT p.post_title, 
                   GROUP_CONCAT(p.ID ORDER BY p.ID ASC) as ids,
                   COUNT(*) as cnt
            FROM {$wpdb->posts} p
            WHERE p.post_type = '" . Oversee_KB_CPT::POST_TYPE . "'
            AND p.post_status = 'publish'
            GROUP BY p.post_title
            HAVING cnt > 1
        ");
        
        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                $ids = explode(',', $dup->ids);
                // Keep the first one, delete the rest
                array_shift($ids);
                foreach ($ids as $id) {
                    wp_delete_post($id, true);
                    $removed++;
                }
            }
        }
        
        // Update term counts
        $terms = get_terms(['taxonomy' => Oversee_KB_CPT::TAXONOMY, 'hide_empty' => false, 'fields' => 'ids']);
        if (!is_wp_error($terms)) {
            foreach ($terms as $tid) {
                wp_update_term_count_now([$tid], Oversee_KB_CPT::TAXONOMY);
            }
        }
        
        // Clear caches
        delete_transient('oversee_kb_cats_db');
        wp_cache_flush();
        
        return $removed;
    }
}
