<?php
/**
 * Knowledge Base Importer - Parses HTML files and imports to database
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_KB_Importer {
    
    private $source_url;
    private $stats = ['categories_added'=>0,'categories_updated'=>0,'articles_added'=>0,'articles_updated'=>0,'articles_skipped'=>0,'errors'=>[]];
    
    private $icons = [
        'ad-manager'=>'fa-solid fa-rectangle-ad','affiliate'=>'fa-solid fa-handshake','calendar'=>'fa-solid fa-calendar',
        'campaign'=>'fa-solid fa-bullhorn','communities'=>'fa-solid fa-users','contacts'=>'fa-solid fa-address-book',
        'conversation'=>'fa-solid fa-message','domain'=>'fa-solid fa-globe','ecommerce'=>'fa-solid fa-cart-shopping',
        'email'=>'fa-solid fa-envelope','facebook'=>'fa-brands fa-facebook','forms'=>'fa-solid fa-rectangle-list',
        'funnel'=>'fa-solid fa-filter','getting-started'=>'fa-solid fa-rocket','google'=>'fa-brands fa-google',
        'integrations'=>'fa-solid fa-plug','invoices'=>'fa-solid fa-file-invoice-dollar','leadconnector'=>'fa-solid fa-plug',
        'linkedin'=>'fa-brands fa-linkedin','memberships'=>'fa-solid fa-id-card','opportunities'=>'fa-solid fa-bullseye',
        'payments'=>'fa-solid fa-credit-card','phone'=>'fa-solid fa-phone','reputation'=>'fa-solid fa-star',
        'review'=>'fa-solid fa-star','seo'=>'fa-solid fa-magnifying-glass','setup'=>'fa-solid fa-gear',
        'sms'=>'fa-solid fa-comment-sms','smtp'=>'fa-solid fa-server','social'=>'fa-solid fa-share-nodes',
        'statistics'=>'fa-solid fa-chart-bar','templates'=>'fa-solid fa-copy','tiktok'=>'fa-brands fa-tiktok',
        'webchat'=>'fa-solid fa-comments','wordpress'=>'fa-brands fa-wordpress','workflow'=>'fa-solid fa-sitemap',
    ];
    
    public function __construct() {
        $this->source_url = rtrim(get_option('oversee_static_kb_url','https://overseecrm.com/wp-content/uploads/knowledge-base'),'/');
    }
    
    public function run_full_import() {
        set_time_limit(300);
        $categories = $this->parse_main_index();
        if(empty($categories)){$this->stats['errors'][]='No categories found';return $this->stats;}
        foreach($categories as $cat){$this->import_category($cat);}
        update_option('oversee_kb_last_sync',current_time('mysql'));
        update_option('oversee_kb_sync_stats',$this->stats);
        return $this->stats;
    }
    
    private function parse_main_index() {
        $response = wp_remote_get($this->source_url.'/index.html',['timeout'=>30]);
        if(is_wp_error($response))return[];
        $html = wp_remote_retrieve_body($response);
        $categories = [];
        if(preg_match_all('/\[###\s*([^\n]+)\n+(\d+)\s*articles?\]\(([^)]+)\/index\.html\)/i',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $m){$categories[]=['name'=>trim($m[1]),'article_count'=>(int)$m[2],'slug'=>trim($m[3],'./')];}}
        return $categories;
    }
    
    private function import_category($cat) {
        $slug = sanitize_title($cat['slug']);
        $name = sanitize_text_field($cat['name']);
        $existing = get_term_by('slug',$slug,Oversee_KB_CPT::TAXONOMY);
        if($existing){wp_update_term($existing->term_id,Oversee_KB_CPT::TAXONOMY,['name'=>$name]);$term_id=$existing->term_id;$this->stats['categories_updated']++;}
        else{$r=wp_insert_term($name,Oversee_KB_CPT::TAXONOMY,['slug'=>$slug]);if(is_wp_error($r))return;$term_id=$r['term_id'];$this->stats['categories_added']++;}
        update_term_meta($term_id,'icon',$this->get_icon($slug));
        update_term_meta($term_id,'original_slug',$cat['slug']);
        $articles = $this->parse_category_index($cat['slug']);
        foreach($articles as $art){$this->import_article($art,$term_id,$cat['slug']);}
    }
    
    private function parse_category_index($cat_slug) {
        $response = wp_remote_get($this->source_url.'/'.$cat_slug.'/index.html',['timeout'=>30]);
        if(is_wp_error($response))return[];
        $html = wp_remote_retrieve_body($response);
        $articles = [];
        if(preg_match_all('/\[([^\]]+)\]\(([^)]+)\.html\)/i',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $m){$s=trim($m[2],'./');if($s==='index'||strpos($s,'/')!==false)continue;$articles[]=['title'=>trim($m[1]),'slug'=>$s];}}
        return $articles;
    }
    
    private function import_article($art,$term_id,$cat_slug) {
        $slug = sanitize_title($art['slug']);
        $existing = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'meta_key'=>'_kb_original_slug','meta_value'=>$art['slug'],'posts_per_page'=>1,'post_status'=>'any']);
        $content = $this->fetch_article_content($cat_slug,$art['slug']);
        if(empty($content)){$this->stats['articles_skipped']++;return;}
        $data = ['post_title'=>$content['title'],'post_content'=>$content['content'],'post_excerpt'=>$content['excerpt'],'post_status'=>'publish','post_type'=>Oversee_KB_CPT::POST_TYPE,'post_name'=>$slug];
        if(!empty($existing)){$data['ID']=$existing[0]->ID;$post_id=wp_update_post($data);$this->stats['articles_updated']++;}
        else{$post_id=wp_insert_post($data);$this->stats['articles_added']++;}
        if(!$post_id||is_wp_error($post_id))return;
        wp_set_object_terms($post_id,$term_id,Oversee_KB_CPT::TAXONOMY);
        update_post_meta($post_id,'_kb_original_slug',$art['slug']);
        update_post_meta($post_id,'_kb_category_slug',$cat_slug);
        if(!get_post_meta($post_id,'_kb_views',true))update_post_meta($post_id,'_kb_views',0);
    }
    
    private function fetch_article_content($cat_slug,$art_slug) {
        $response = wp_remote_get($this->source_url.'/'.$cat_slug.'/'.$art_slug.'.html',['timeout'=>30]);
        if(is_wp_error($response)||wp_remote_retrieve_response_code($response)!==200)return null;
        $html = wp_remote_retrieve_body($response);
        $title = $art_slug;
        if(preg_match('/<h1[^>]*>([^<]+)<\/h1>/i',$html,$m))$title=trim(strip_tags($m[1]));
        elseif(preg_match('/<title[^>]*>([^<]+)<\/title>/i',$html,$m))$title=trim(preg_replace('/\s*[-|].+$/','',$m[1]));
        $content = $html;
        if(preg_match('/<body[^>]*>(.*?)<\/body>/is',$html,$m))$content=$m[1];
        if(preg_match('/<main[^>]*>(.*?)<\/main>/is',$content,$m))$content=$m[1];
        elseif(preg_match('/<article[^>]*>(.*?)<\/article>/is',$content,$m))$content=$m[1];
        $content = preg_replace(['/<h1[^>]*>.*?<\/h1>/is','/<nav[^>]*>.*?<\/nav>/is','/<script[^>]*>.*?<\/script>/is','/<style[^>]*>.*?<\/style>/is'],'',$content);
        $content = str_replace(['../images/','src="images/'],[$this->source_url.'/images/',$this->source_url.'/'.$cat_slug.'/images/'],$content);
        return ['title'=>html_entity_decode($title,ENT_QUOTES,'UTF-8'),'content'=>trim($content),'excerpt'=>wp_trim_words(strip_tags($content),30)];
    }
    
    private function get_icon($slug) {
        foreach($this->icons as $k=>$v)if(strpos(strtolower($slug),$k)!==false)return $v;
        return 'fa-solid fa-folder';
    }
    
    public function get_stats(){return $this->stats;}
    
    public static function clear_all_data() {
        $posts = get_posts(['post_type'=>Oversee_KB_CPT::POST_TYPE,'posts_per_page'=>-1,'post_status'=>'any','fields'=>'ids']);
        foreach($posts as $id)wp_delete_post($id,true);
        $terms = get_terms(['taxonomy'=>Oversee_KB_CPT::TAXONOMY,'hide_empty'=>false,'fields'=>'ids']);
        foreach($terms as $id)wp_delete_term($id,Oversee_KB_CPT::TAXONOMY);
        delete_option('oversee_kb_last_sync');
        delete_option('oversee_kb_sync_stats');
        return true;
    }
}
