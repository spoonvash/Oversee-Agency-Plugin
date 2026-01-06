<?php
/**
 * Knowledge Base Manager
 * 
 * Fetches KB categories and articles from remote overseecrm.com server.
 * Uses PHP to pull content from the central knowledge base.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB {
    
    /**
     * Remote KB base URL
     */
    private $remote_url = 'https://overseecrm.com/wp-content/uploads/knowledge-base';
    
    /**
     * Remote API URL
     */
    private $api_url = 'https://overseecrm.com/wp-json/kb/v1';
    
    /**
     * Cache duration in seconds
     */
    private $cache_duration = 3600; // 1 hour
    
    /**
     * Icon mapping for categories
     */
    private $icons = [
        'ad-manager' => 'fa-rectangle-ad',
        'affiliate' => 'fa-handshake',
        'api' => 'fa-code',
        'app' => 'fa-grid-2',
        'associations' => 'fa-sitemap',
        'authentication' => 'fa-shield-halved',
        'automation' => 'fa-robot',
        'blog' => 'fa-pen-nib',
        'brand' => 'fa-palette',
        'calendar' => 'fa-calendar',
        'campaign' => 'fa-bullhorn',
        'certificates' => 'fa-award',
        'chat' => 'fa-comments',
        'client' => 'fa-user-tie',
        'communities' => 'fa-users',
        'contacts' => 'fa-address-book',
        'content' => 'fa-file-lines',
        'conversation' => 'fa-message',
        'courses' => 'fa-graduation-cap',
        'crm' => 'fa-database',
        'custom' => 'fa-sliders',
        'dashboard' => 'fa-gauge',
        'documents' => 'fa-file-contract',
        'domain' => 'fa-globe',
        'ecommerce' => 'fa-cart-shopping',
        'email' => 'fa-envelope',
        'facebook' => 'fa-facebook',
        'forms' => 'fa-rectangle-list',
        'funnels' => 'fa-filter',
        'getting-started' => 'fa-rocket',
        'google' => 'fa-google',
        'ideas' => 'fa-lightbulb',
        'import' => 'fa-file-import',
        'integrations' => 'fa-plug',
        'invoices' => 'fa-file-invoice-dollar',
        'lc-phone' => 'fa-phone',
        'lead' => 'fa-user-plus',
        'marketplace' => 'fa-store',
        'media' => 'fa-photo-film',
        'memberships' => 'fa-id-card',
        'mobile' => 'fa-mobile',
        'opportunities' => 'fa-bullseye',
        'payments' => 'fa-credit-card',
        'phone' => 'fa-phone',
        'pipeline' => 'fa-bars-progress',
        'portal' => 'fa-door-open',
        'quickstart' => 'fa-bolt',
        'reporting' => 'fa-chart-bar',
        'reputation' => 'fa-star',
        'reselling' => 'fa-store',
        'saas' => 'fa-cloud',
        'security' => 'fa-shield',
        'settings' => 'fa-gear',
        'sms' => 'fa-comment-sms',
        'social' => 'fa-share-nodes',
        'surveys' => 'fa-clipboard-question',
        'tags' => 'fa-tags',
        'tasks' => 'fa-list-check',
        'templates' => 'fa-copy',
        'tracking' => 'fa-location-dot',
        'triggers' => 'fa-bolt',
        'users' => 'fa-users-gear',
        'websites' => 'fa-browser',
        'whatsapp' => 'fa-whatsapp',
        'wordpress' => 'fa-wordpress',
        'workflows' => 'fa-sitemap',
        'yext' => 'fa-location-dot',
        'zapier' => 'fa-bolt',
        'zoom' => 'fa-video',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        // Allow override via options
        $custom_url = get_option('oversee_static_kb_url');
        if (!empty($custom_url)) {
            $this->remote_url = rtrim($custom_url, '/');
        }
        
        $custom_api = get_option('oversee_remote_kb_url');
        if (!empty($custom_api)) {
            $this->api_url = rtrim($custom_api, '/');
        }
        
        $custom_cache = get_option('oversee_kb_cache_duration');
        if (!empty($custom_cache)) {
            $this->cache_duration = intval($custom_cache);
        }
    }
    
    /**
     * Get all categories with article counts
     */
    public function get_categories() {
        // Try cache first
        $cache_key = 'oversee_kb_categories';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Try fetching from API
        $categories = $this->fetch_categories_from_api();
        
        if (empty($categories)) {
            // Fallback: try index.json
            $categories = $this->fetch_categories_from_index();
        }
        
        if (!empty($categories)) {
            // Add icons to categories
            foreach ($categories as &$cat) {
                if (empty($cat['icon'])) {
                    $cat['icon'] = 'fa-solid ' . $this->get_category_icon($cat['slug']);
                }
            }
            
            // Sort alphabetically
            usort($categories, function($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            });
            
            // Cache the result
            set_transient($cache_key, $categories, $this->cache_duration);
        }
        
        return $categories ?: [];
    }
    
    /**
     * Fetch categories from REST API
     */
    private function fetch_categories_from_api() {
        $response = wp_remote_get($this->api_url . '/categories', [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            Oversee_Logger::warning('Failed to fetch KB categories from API', [
                'error' => $response->get_error_message()
            ]);
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            return [];
        }
        
        return $data;
    }
    
    /**
     * Fetch categories from index.json file
     */
    private function fetch_categories_from_index() {
        $response = wp_remote_get($this->remote_url . '/index.json', [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data) || !isset($data['categories'])) {
            return [];
        }
        
        return $data['categories'];
    }
    
    /**
     * Get icon for a category based on slug
     */
    public function get_category_icon($slug) {
        $slug_lower = strtolower($slug);
        
        foreach ($this->icons as $key => $icon) {
            if (strpos($slug_lower, $key) !== false) {
                return $icon;
            }
        }
        
        return 'fa-folder';
    }
    
    /**
     * Get a single category with its articles
     */
    public function get_category($slug) {
        $slug = sanitize_file_name($slug);
        
        // Try cache first
        $cache_key = 'oversee_kb_category_' . $slug;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Try API first
        $category = $this->fetch_category_from_api($slug);
        
        if (empty($category)) {
            // Fallback: build from articles list
            $articles = $this->get_category_articles($slug);
            
            if (!empty($articles)) {
                $name = ucwords(str_replace('-', ' ', $slug));
                $category = [
                    'slug' => $slug,
                    'name' => $name,
                    'icon' => 'fa-solid ' . $this->get_category_icon($slug),
                    'article_count' => count($articles),
                    'articles' => $articles
                ];
            }
        }
        
        if (!empty($category)) {
            // Ensure icon is set
            if (empty($category['icon'])) {
                $category['icon'] = 'fa-solid ' . $this->get_category_icon($slug);
            }
            
            // Cache the result
            set_transient($cache_key, $category, $this->cache_duration);
        }
        
        return $category;
    }
    
    /**
     * Fetch category from API
     */
    private function fetch_category_from_api($slug) {
        $response = wp_remote_get($this->api_url . '/categories/' . urlencode($slug), [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return is_array($data) ? $data : null;
    }
    
    /**
     * Get articles for a category
     */
    public function get_category_articles($category_slug) {
        $category_slug = sanitize_file_name($category_slug);
        
        // Try cache first
        $cache_key = 'oversee_kb_articles_' . $category_slug;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Try API
        $articles = $this->fetch_articles_from_api($category_slug);
        
        if (empty($articles)) {
            // Fallback: try category index.json
            $articles = $this->fetch_articles_from_index($category_slug);
        }
        
        if (!empty($articles)) {
            // Sort alphabetically by title
            usort($articles, function($a, $b) {
                return strcasecmp($a['title'], $b['title']);
            });
            
            // Cache the result
            set_transient($cache_key, $articles, $this->cache_duration);
        }
        
        return $articles ?: [];
    }
    
    /**
     * Fetch articles from API
     */
    private function fetch_articles_from_api($category_slug) {
        $response = wp_remote_get($this->api_url . '/categories/' . urlencode($category_slug) . '/articles', [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Fetch articles from category index.json
     */
    private function fetch_articles_from_index($category_slug) {
        $response = wp_remote_get($this->remote_url . '/' . urlencode($category_slug) . '/index.json', [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data) || !isset($data['articles'])) {
            return [];
        }
        
        return $data['articles'];
    }
    
    /**
     * Get a single article
     */
    public function get_article($category_slug, $article_slug) {
        $category_slug = sanitize_file_name($category_slug);
        $article_slug = sanitize_file_name($article_slug);
        
        // Try cache first
        $cache_key = 'oversee_kb_article_' . $category_slug . '_' . $article_slug;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Try API first
        $article = $this->fetch_article_from_api($category_slug, $article_slug);
        
        if (empty($article)) {
            // Fallback: fetch HTML file directly
            $article = $this->fetch_article_from_html($category_slug, $article_slug);
        }
        
        if (!empty($article)) {
            // Fix image paths
            if (!empty($article['content'])) {
                $article['content'] = $this->fix_image_paths($article['content']);
            }
            
            // Add category info if missing
            if (empty($article['category'])) {
                $category_name = ucwords(str_replace('-', ' ', $category_slug));
                $article['category'] = [
                    'slug' => $category_slug,
                    'name' => $category_name,
                    'icon' => 'fa-solid ' . $this->get_category_icon($category_slug)
                ];
            }
            
            // Cache the result
            set_transient($cache_key, $article, $this->cache_duration);
        }
        
        return $article;
    }
    
    /**
     * Fetch article from API
     */
    private function fetch_article_from_api($category_slug, $article_slug) {
        $response = wp_remote_get(
            $this->api_url . '/articles/' . urlencode($category_slug) . '/' . urlencode($article_slug),
            ['timeout' => 15, 'sslverify' => true]
        );
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return is_array($data) ? $data : null;
    }
    
    /**
     * Fetch article from HTML file
     */
    private function fetch_article_from_html($category_slug, $article_slug) {
        $url = $this->remote_url . '/' . urlencode($category_slug) . '/' . urlencode($article_slug) . '.html';
        
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'sslverify' => true
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return null;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // Extract title
        $title = $this->extract_title($html, $article_slug);
        
        // Extract body content
        $content = $html;
        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $body_match)) {
            $content = $body_match[1];
        }
        
        // Remove h1, nav, header elements
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/is', '', $content);
        $content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
        $content = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $content);
        $content = preg_replace('/<a[^>]*class="[^"]*back-link[^"]*"[^>]*>.*?<\/a>/is', '', $content);
        
        $category_name = ucwords(str_replace('-', ' ', $category_slug));
        
        return [
            'slug' => $article_slug,
            'title' => $title,
            'content' => $content,
            'category_slug' => $category_slug,
            'category' => [
                'slug' => $category_slug,
                'name' => $category_name,
                'icon' => 'fa-solid ' . $this->get_category_icon($category_slug)
            ]
        ];
    }
    
    /**
     * Extract title from HTML
     */
    private function extract_title($html, $fallback_slug) {
        // Try h1 tag
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
            if (!empty($title)) {
                return $title;
            }
        }
        
        // Try title tag
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(strip_tags($matches[1]));
            if (!empty($title)) {
                return $title;
            }
        }
        
        // Fallback to filename
        $title = preg_replace('/^\d+-/', '', $fallback_slug);
        $title = ucwords(str_replace('-', ' ', $title));
        
        return $title;
    }
    
    /**
     * Fix image paths in content
     */
    private function fix_image_paths($content) {
        // Fix relative paths
        $content = str_replace('../images/', $this->remote_url . '/images/', $content);
        $content = str_replace('src="images/', 'src="' . $this->remote_url . '/images/', $content);
        $content = str_replace("src='images/", "src='" . $this->remote_url . "/images/", $content);
        
        return $content;
    }
    
    /**
     * Search articles
     */
    public function search($query, $limit = 20) {
        if (strlen($query) < 2) {
            return [];
        }
        
        // Try API search
        $results = $this->search_via_api($query, $limit);
        
        if (!empty($results)) {
            return $results;
        }
        
        // Fallback: search through cached categories/articles
        return $this->search_locally($query, $limit);
    }
    
    /**
     * Search via API
     */
    private function search_via_api($query, $limit) {
        $response = wp_remote_get(
            $this->api_url . '/search?' . http_build_query(['q' => $query, 'limit' => $limit]),
            ['timeout' => 15, 'sslverify' => true]
        );
        
        if (is_wp_error($response)) {
            return [];
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Search through cached data
     */
    private function search_locally($query, $limit) {
        $results = [];
        $categories = $this->get_categories();
        
        foreach ($categories as $category) {
            $articles = $this->get_category_articles($category['slug']);
            
            foreach ($articles as $article) {
                // Check title match
                if (stripos($article['title'], $query) !== false) {
                    $results[] = [
                        'slug' => $article['slug'],
                        'title' => $article['title'],
                        'excerpt' => $article['excerpt'] ?? '',
                        'category_slug' => $category['slug'],
                        'category_name' => $category['name']
                    ];
                }
                
                if (count($results) >= $limit) {
                    break 2;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Get related articles from the same category
     */
    public function get_related_articles($category_slug, $current_article_slug, $limit = 5) {
        $articles = $this->get_category_articles($category_slug);
        $related = [];
        
        // Filter out current article and shuffle
        $filtered = array_filter($articles, function($a) use ($current_article_slug) {
            return $a['slug'] !== $current_article_slug;
        });
        
        shuffle($filtered);
        
        return array_slice($filtered, 0, $limit);
    }
    
    /**
     * Get total article count
     */
    public function get_total_articles() {
        $categories = $this->get_categories();
        $total = 0;
        
        foreach ($categories as $cat) {
            $total += isset($cat['article_count']) ? intval($cat['article_count']) : 0;
        }
        
        return $total;
    }
    
    /**
     * Clear all KB caches
     */
    public function clear_cache() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oversee_kb_%' OR option_name LIKE '_transient_timeout_oversee_kb_%'"
        );
        
        return true;
    }
}
