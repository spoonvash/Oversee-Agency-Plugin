<?php
/**
 * Knowledge Base Manager - Queries from WordPress database
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB {
    
    private $cache_duration = 3600;
    
    public function __construct() {
        $c = get_option('oversee_kb_cache_duration');
        if(!empty($c))$this->cache_duration = intval($c);
    }
    
    public function get_categories() {
        $cache_key = 'oversee_kb_cats_db';
        $cached = get_transient($cache_key);
        if($cached !== false) return $cached;
        
        $terms = get_terms(['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'hide_empty'=>false,'orderby'=>'name','order'=>'ASC']);
        if(is_wp_error($terms)) return [];
        
        $categories = [];
        foreach($terms as $t) {
            $categories[] = [
                'id'=>$t->term_id,'slug'=>$t->slug,'name'=>$t->name,'description'=>$t->description,
                'article_count'=>$t->count,'icon'=>get_term_meta($t->term_id,'icon',true)?:'fa-solid fa-folder',
                'original_slug'=>get_term_meta($t->term_id,'original_slug',true)?:$t->slug,
            ];
        }
        set_transient($cache_key,$categories,$this->cache_duration);
        return $categories;
    }
    
    public function get_category($slug) {
        $slug = sanitize_title($slug);
        $term = get_term_by('slug',$slug,Oversee_KB_CPT::TAXONOMY);
        if(!$term){
            $terms = get_terms(['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'meta_key'=>'original_slug','meta_value'=>$slug,'hide_empty'=>false,'number'=>1]);
            if(!empty($terms)&&!is_wp_error($terms))$term=$terms[0];
        }
        if(!$term||is_wp_error($term)) return null;
        $articles = $this->get_category_articles($term->slug);
        return ['id'=>$term->term_id,'slug'=>$term->slug,'name'=>$term->name,'description'=>$term->description,
            'article_count'=>count($articles),'icon'=>get_term_meta($term->term_id,'icon',true)?:'fa-solid fa-folder','articles'=>$articles];
    }
    
    public function get_category_icon($slug) {
        $term = get_term_by('slug',$slug,Oversee_KB_CPT::TAXONOMY);
        return $term ? (get_term_meta($term->term_id,'icon',true)?:'fa-solid fa-folder') : 'fa-solid fa-folder';
    }
    
    public function get_category_articles($cat_slug) {
        $cat_slug = sanitize_title($cat_slug);
        $term = get_term_by('slug',$cat_slug,Oversee_KB_CPT::TAXONOMY);
        if(!$term){
            $terms = get_terms(['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'meta_key'=>'original_slug','meta_value'=>$cat_slug,'hide_empty'=>false,'number'=>1]);
            if(!empty($terms)&&!is_wp_error($terms))$term=$terms[0];
        }
        if(!$term) return [];
        
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'posts_per_page'=>-1,'post_status'=>'publish','orderby'=>'title','order'=>'ASC',
            'tax_query'=>[['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'field'=>'term_id','terms'=>$term->term_id]]]);
        
        $articles = [];
        foreach($posts as $p) {
            $articles[] = ['id'=>$p->ID,'slug'=>$p->post_name,'title'=>$p->post_title,
                'excerpt'=>$p->post_excerpt?:wp_trim_words($p->post_content,20),
                'original_slug'=>get_post_meta($p->ID,'_kb_original_slug',true)?:$p->post_name,
                'views'=>(int)get_post_meta($p->ID,'_kb_views',true)];
        }
        return $articles;
    }
    
    public function get_article($cat_slug,$art_slug) {
        $art_slug = sanitize_title($art_slug);
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'name'=>$art_slug,'posts_per_page'=>1,'post_status'=>'publish']);
        if(empty($posts)){
            $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'meta_key'=>'_kb_original_slug','meta_value'=>$art_slug,'posts_per_page'=>1,'post_status'=>'publish']);
        }
        if(empty($posts)) return null;
        
        $post = $posts[0];
        $terms = wp_get_post_terms($post->ID,Oversee_KB_CPT::TAXONOMY);
        $category = null;
        if(!empty($terms)&&!is_wp_error($terms)){
            $t=$terms[0];
            $category = ['id'=>$t->term_id,'slug'=>$t->slug,'name'=>$t->name,'icon'=>get_term_meta($t->term_id,'icon',true)?:'fa-solid fa-folder'];
        }
        Oversee_KB_CPT::increment_views($post->ID);
        return ['id'=>$post->ID,'slug'=>$post->post_name,'title'=>$post->post_title,
            'content'=>apply_filters('the_content',$post->post_content),'excerpt'=>$post->post_excerpt,
            'original_slug'=>get_post_meta($post->ID,'_kb_original_slug',true)?:$post->post_name,
            'category_slug'=>$category?$category['slug']:$cat_slug,'category'=>$category,
            'views'=>(int)get_post_meta($post->ID,'_kb_views',true),'created_at'=>$post->post_date,'updated_at'=>$post->post_modified];
    }
    
    public function search($query,$limit=20) {
        if(strlen($query)<2) return [];
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'posts_per_page'=>$limit,'post_status'=>'publish','s'=>$query]);
        $results = [];
        foreach($posts as $p){
            $terms = wp_get_post_terms($p->ID,Oversee_KB_CPT::TAXONOMY);
            $results[] = ['id'=>$p->ID,'slug'=>get_post_meta($p->ID,'_kb_original_slug',true)?:$p->post_name,'title'=>$p->post_title,
                'excerpt'=>$p->post_excerpt?:wp_trim_words($p->post_content,20),
                'category_slug'=>!empty($terms)?$terms[0]->slug:'','category_name'=>!empty($terms)?$terms[0]->name:''];
        }
        return $results;
    }
    
    public function get_related_articles($cat_slug,$current_slug,$limit=5) {
        $term = get_term_by('slug',$cat_slug,Oversee_KB_CPT::TAXONOMY);
        if(!$term) return [];
        $current = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'name'=>$current_slug,'posts_per_page'=>1,'fields'=>'ids']);
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'posts_per_page'=>$limit,'post_status'=>'publish','orderby'=>'rand','exclude'=>$current,
            'tax_query'=>[['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'field'=>'term_id','terms'=>$term->term_id]]]);
        $articles = [];
        foreach($posts as $p){$articles[]=['id'=>$p->ID,'slug'=>get_post_meta($p->ID,'_kb_original_slug',true)?:$p->post_name,'title'=>$p->post_title,'excerpt'=>$p->post_excerpt?:wp_trim_words($p->post_content,15)];}
        return $articles;
    }
    
    public function get_popular_articles($limit=10) {
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'posts_per_page'=>$limit,'post_status'=>'publish','meta_key'=>'_kb_views','orderby'=>'meta_value_num','order'=>'DESC']);
        $articles = [];
        foreach($posts as $p){
            $terms = wp_get_post_terms($p->ID,Oversee_KB_CPT::TAXONOMY);
            $articles[]=['id'=>$p->ID,'slug'=>get_post_meta($p->ID,'_kb_original_slug',true)?:$p->post_name,'title'=>$p->post_title,'excerpt'=>$p->post_excerpt,
                'views'=>(int)get_post_meta($p->ID,'_kb_views',true),'category_slug'=>!empty($terms)?$terms[0]->slug:'','category_name'=>!empty($terms)?$terms[0]->name:''];
        }
        return $articles;
    }
    
    public function get_total_articles() {
        $c = wp_count_posts(Oversee_KB_CPT::POST_TYPE);
        return isset($c->publish)?(int)$c->publish:0;
    }
    
    public function get_total_categories() {
        return wp_count_terms(['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'hide_empty'=>false]);
    }
    
    public function clear_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oversee_kb_%' OR option_name LIKE '_transient_timeout_oversee_kb_%'");
        return true;
    }
    
    public function get_sync_info() {
        return ['last_sync'=>get_option('oversee_kb_last_sync'),'stats'=>get_option('oversee_kb_sync_stats'),'total_articles'=>$this->get_total_articles(),'total_categories'=>$this->get_total_categories()];
    }
    
    public function sync() {
        $importer = new Oversee_KB_Importer();
        return $importer->run_full_import();
    }
}
