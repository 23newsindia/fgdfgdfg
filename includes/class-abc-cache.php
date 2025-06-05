<?php
class ABC_Cache {
    const CACHE_GROUP = 'abc_banners';
    const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;
    const TRANSIENT_EXPIRY = 24 * HOUR_IN_SECONDS;
    const CACHE_VERSION = '1'; // Add cache versioning
    
    public function __construct() {
        add_action('abc_banner_updated', [$this, 'clear_banner_cache']);
        add_action('abc_banner_deleted', [$this, 'clear_banner_cache']);
        add_action('save_post', [$this, 'clear_post_banners_cache']);
        add_action('delete_post', [$this, 'clear_post_banners_cache']);
        add_action('upgrader_process_complete', [$this, 'clear_all_cache'], 10, 2);
        add_action('update_option', [$this, 'maybe_clear_cache_on_option_change'], 10, 3);
        
        // Add new hooks for better cache management
        add_action('switch_theme', [$this, 'clear_all_cache']);
        add_action('wp_update_nav_menu', [$this, 'clear_all_cache']);
        add_action('update_option_sidebars_widgets', [$this, 'clear_all_cache']);
        
        // Schedule cache cleanup
        if (!wp_next_scheduled('abc_cache_cleanup')) {
            wp_schedule_event(time(), 'daily', 'abc_cache_cleanup');
        }
        add_action('abc_cache_cleanup', [$this, 'cleanup_expired_cache']);
    }
    
    public static function get_cache_key($type, $identifier, $context = '') {
        $key_parts = ['abc', $type, $identifier, self::CACHE_VERSION];
        
        if (!empty($context)) {
            $key_parts[] = md5(serialize($context));
        }
        
        return implode('_', array_filter($key_parts));
    }

    public static function get_banner($slug, $force_fresh = false) {
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $force_fresh = true; // Always fresh data for AJAX requests
        }
        
        $cache_key = self::get_cache_key('banner', $slug);
        
        if (!$force_fresh) {
            // Try object cache first
            $banner = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($banner !== false) {
                return $banner;
            }
            
            // Try transient cache
            $transient_key = 'abc_banner_' . md5($slug);
            $banner = get_transient($transient_key);
            if ($banner !== false) {
                wp_cache_set($cache_key, $banner, self::CACHE_GROUP, self::CACHE_EXPIRY);
                return $banner;
            }
        }
        
        // Get fresh data
        $banner = ABC_DB::get_banner($slug);
        if ($banner) {
            self::set_banner_cache($slug, $banner);
        }
        
        return $banner;
    }

    public static function set_banner_cache($slug, $banner) {
        if (empty($banner)) return;
        
        $cache_key = self::get_cache_key('banner', $slug);
        $transient_key = 'abc_banner_' . md5($slug);
        
        wp_cache_set($cache_key, $banner, self::CACHE_GROUP, self::CACHE_EXPIRY);
        set_transient($transient_key, $banner, self::TRANSIENT_EXPIRY);
    }

    public static function clear_banner_cache($banner_id_or_slug) {
        if (is_numeric($banner_id_or_slug)) {
            $banner = ABC_DB::get_banner_by_id($banner_id_or_slug);
            $slug = $banner ? $banner->slug : null;
        } else {
            $slug = $banner_id_or_slug;
        }
        
        if (!$slug) return;
        
        $cache_key = self::get_cache_key('banner', $slug);
        $transient_key = 'abc_banner_' . md5($slug);
        
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        delete_transient($transient_key);
        
        ABC_Frontend::clear_shortcode_cache($slug);
        
        do_action('abc_cache_cleared', $slug);
    }

    public static function maybe_clear_cache_on_option_change($option, $old_value, $new_value) {
        $critical_options = [
            'home', 'siteurl', 'permalink_structure', 'rewrite_rules',
            'widget_abc_banner', 'theme_mods_' . get_stylesheet()
        ];
        
        if (in_array($option, $critical_options)) {
            self::clear_all_cache();
        }
    }

    public static function clear_post_banners_cache($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        
        $post = get_post($post_id);
        if (!$post || !has_shortcode($post->post_content, 'abc_banner')) {
            return;
        }

        preg_match_all('/\[abc_banner\s+slug=["\']([^"\']+)["\']/', $post->post_content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $slug) {
                self::clear_banner_cache($slug);
            }
        }
    }

    public static function preload_banners() {
        if (defined('DOING_AJAX') || defined('DOING_CRON')) return;
        
        $banners = ABC_DB::get_all_banners();
        foreach ($banners as $banner) {
            self::set_banner_cache($banner->slug, $banner);
        }
        
        do_action('abc_cache_preloaded');
    }

    public static function register_hooks() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }

    public static function clear_all_cache() {
        global $wpdb;
        
        wp_cache_flush_group(self::CACHE_GROUP);
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_abc_banner_%' 
             OR option_name LIKE '_transient_timeout_abc_banner_%'"
        );
        
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key LIKE '_abc_shortcode_cache_%'"
        );
        
        do_action('abc_all_cache_cleared');
    }
    
    public function cleanup_expired_cache() {
        global $wpdb;
        
        // Clean expired transients
        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
             WHERE a.option_name LIKE '_transient_timeout_abc_banner_%'
             AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
             AND a.option_value < UNIX_TIMESTAMP()"
        );
        
        do_action('abc_cache_cleaned');
    }
    
    public static function get_cache_stats() {
        global $wpdb;
        
        $stats = [
            'object_cache_count' => wp_cache_get_group_keys(self::CACHE_GROUP),
            'transient_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_abc_banner_%'"
            ),
            'shortcode_cache_count' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} 
                 WHERE meta_key LIKE '_abc_shortcode_cache_%'"
            )
        ];
        
        return $stats;
    }
}