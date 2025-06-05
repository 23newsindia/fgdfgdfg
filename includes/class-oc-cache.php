<?php
class OC_Cache {
    const CACHE_GROUP = 'offers_carousel';
    const CACHE_EXPIRY = HOUR_IN_SECONDS;
    const TRANSIENT_EXPIRY = 15 * MINUTE_IN_SECONDS;

    public static function init() {
        // Existing hooks
        add_action('oc_carousel_updated', [__CLASS__, 'clear_carousel_cache']);
        add_action('oc_carousel_deleted', [__CLASS__, 'clear_carousel_cache']);
        add_action('updated_option', [__CLASS__, 'maybe_clear_all_cache']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'clear_all_cache']);

        // Add these new hooks for broader cache clearing:
        add_action('save_post', [__CLASS__, 'clear_post_cache']); // Clear if post content changes
        add_action('woocommerce_settings_saved', [__CLASS__, 'clear_all_cache']); // WC settings update
        add_action('switch_theme', [__CLASS__, 'clear_all_cache']); // Theme change
        
        // Additional WooCommerce-specific hooks
        add_action('woocommerce_product_options_pricing', [__CLASS__, 'maybe_clear_product_cache']);
        add_action('woocommerce_update_product', [__CLASS__, 'clear_product_cache']);
    }

    /**
     * Generate a unique cache key for the carousel
     */
    public static function get_cache_key($type, $identifier, $settings = []) {
        $key_parts = [$type, $identifier];
        
        if (!empty($settings)) {
            if (!empty($settings['effect'])) {
                $key_parts[] = 'effect_' . $settings['effect'];
            }
            if (!empty($settings['slides_per_view'])) {
                $key_parts[] = 'slides_' . $settings['slides_per_view'];
            }
            if (!empty($settings['autoplay'])) {
                $key_parts[] = 'autoplay_' . (int)$settings['autoplay'];
            }
        }
        
        return 'oc_' . md5(implode('_', $key_parts)) . '_' . OC_VERSION;
    }

    /**
     * Get cached carousel data
     */
    public static function get_carousel($slug, $settings = []) {
        $key = self::get_cache_key('carousel', $slug, $settings);
        $carousel = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($carousel === false) {
            $transient_key = 'oc_carousel_' . md5($slug);
            $carousel = get_transient($transient_key);
            
            if ($carousel === false) {
                $carousel = OC_DB::get_carousel($slug);
                if ($carousel) {
                    // Cache in both object cache and transient
                    wp_cache_set($key, $carousel, self::CACHE_GROUP, self::CACHE_EXPIRY);
                    set_transient($transient_key, $carousel, self::TRANSIENT_EXPIRY);
                }
            } else {
                // Update object cache from transient
                wp_cache_set($key, $carousel, self::CACHE_GROUP, self::CACHE_EXPIRY);
            }
        }
        
        return $carousel;
    }

    /**
     * Clear cache for a specific carousel
     */
    public static function clear_carousel_cache($carousel_id) {
        global $wpdb;
        $carousel = OC_DB::get_carousel_by_id($carousel_id);

        if ($carousel) {
            // Clear object cache for all possible variations
            $base_key = self::get_cache_key('carousel', $carousel->slug);
            wp_cache_delete($base_key, self::CACHE_GROUP);
            
            // Clear all possible setting combinations
            $effects = ['slide', 'fade', 'coverflow'];
            $slides_options = [1, 2, 3, 4];
            $autoplay_options = [true, false];
            
            foreach ($effects as $effect) {
                foreach ($slides_options as $slides) {
                    foreach ($autoplay_options as $autoplay) {
                        $key = self::get_cache_key('carousel', $carousel->slug, [
                            'effect' => $effect,
                            'slides_per_view' => $slides,
                            'autoplay' => $autoplay
                        ]);
                        wp_cache_delete($key, self::CACHE_GROUP);
                    }
                }
            }

            // Delete transient
            $transient_key = 'oc_carousel_' . md5($carousel->slug);
            delete_transient($transient_key);
            
            // Clear all possible transient variations
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE %s 
                     OR option_name LIKE %s",
                    '_transient_' . $transient_key,
                    '_transient_timeout_' . $transient_key
                )
            );
        }
    }

    /**
     * Clear all cached carousels and transients
     */
    public static function clear_all_cache() {
        global $wpdb;
        
        // Clear object cache
        wp_cache_flush();
        
        // Delete all transients related to offers carousel
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_oc_%' 
             OR option_name LIKE '_transient_timeout_oc_%'"
        );
        
        // Also clear any stored shortcode caches
        $wpdb->query(
            "DELETE FROM {$wpdb->postmeta} 
             WHERE meta_key LIKE '_oc_shortcode_cache_%'"
        );
        
        error_log('Offers Carousel: All cache cleared');
    }

    /**
     * Clear cache if WooCommerce or plugin settings change
     */
    public static function maybe_clear_all_cache($option_name) {
        $critical_options = [
            'woocommerce_currency',
            'woocommerce_default_country',
            'offers_carousel_settings',
        ];
        
        if (in_array($option_name, $critical_options)) {
            self::clear_all_cache();
        }
    }

    /**
     * Cache rendered shortcode HTML to avoid reprocessing
     */
    public static function get_shortcode_cache($carousel_slug) {
        $cache_key = '_oc_shortcode_cache_' . md5($carousel_slug);
        $cached_html = get_post_meta(get_the_ID(), $cache_key, true);
        
        if ($cached_html) {
            return $cached_html;
        }
        
        return false;
    }

    public static function set_shortcode_cache($carousel_slug, $html) {
        $cache_key = '_oc_shortcode_cache_' . md5($carousel_slug);
        update_post_meta(get_the_ID(), $cache_key, $html);
    }

    public static function clear_post_cache($post_id) {
        // Only clear cache for product posts or posts containing our shortcode
        if (get_post_type($post_id) === 'product' || 
            has_shortcode(get_post($post_id)->post_content, 'offers_carousel')) {
            self::clear_all_cache();
        }
    }

    public static function maybe_clear_product_cache() {
        if (isset($_POST['_regular_price']) || isset($_POST['_sale_price'])) {
            self::clear_all_cache();
        }
    }

    public static function clear_product_cache($product_id) {
        self::clear_all_cache();
    }
}